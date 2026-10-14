<?php

namespace App\Http\Controllers\Tournament;

use App\Http\Controllers\Controller;
use App\Models\TournamentPairing;
use App\Support\TournamentScheduler;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use PChess\Chess\Chess;
use PChess\Chess\Move;

class PlayController extends Controller
{
    private const START_FEN = 'rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1';

    public function poll($tournament, TournamentPairing $pairing)
    {
        $student = Auth::user();
        abort_unless($pairing->isPlayer($student->id), 403);

        $tournament = $pairing->tournament;
        if ($tournament && $tournament->isCancelled() && $pairing->isActive()) {
            $pairing->update(['status' => 'cancelled', 'completed_at' => now()]);
            $pairing->refresh();
        }

        self::ensureOpeningClocks($pairing);
        self::settleTimeoutIfAny($pairing);
        $pairing->refresh();
        $clocks = $pairing->liveClocks();

        return response()->json([
            'status' => $pairing->status,
            'tournament_status' => $tournament ? $tournament->status : null,
            'fen' => $pairing->current_fen,
            'move_count' => $pairing->move_count,
            'moves_pgn' => $pairing->moves_pgn,
            'white_clock_ms' => $clocks['white'],
            'black_clock_ms' => $clocks['black'],
            'last_move_at' => optional($pairing->last_move_at)->toIso8601String(),
            'side_to_move' => $pairing->sideToMove(),
            'result' => $pairing->result,
            'winner_id' => $pairing->winner_user_id,
            'draw_offer_by' => $pairing->draw_offer_by,
            'redirect_url' => route('student.tournaments.show', $pairing->tournament),
        ])->withHeaders([
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }

    public function move(Request $request, $tournament, TournamentPairing $pairing)
    {
        $student = Auth::user();
        abort_unless($pairing->isPlayer($student->id), 403);

        $data = $request->validate([
            'from' => 'required|string|size:2',
            'to' => 'required|string|size:2',
            'promotion' => 'nullable|string|size:1',
            'resulting_fen' => 'nullable|string|max:128',
            'san' => 'nullable|string|max:32',
        ]);

        self::ensureOpeningClocks($pairing);
        self::settleTimeoutIfAny($pairing);
        $pairing->refresh();

        if (!$pairing->isActive()) {
            return response()->json(['ok' => false, 'error' => 'Game ended.'], 410);
        }

        $myColor = $pairing->colorOf($student->id);
        if ($pairing->sideToMove() !== $myColor) {
            return response()->json(['ok' => false, 'error' => 'Not your turn.'], 409);
        }

        $postResult = null;

        DB::transaction(function () use ($pairing, $data, $myColor, &$postResult) {
            $locked = TournamentPairing::query()->lockForUpdate()->findOrFail($pairing->id);
            if (!$locked->isActive()) {
                return;
            }

            $now = now();
            $remaining = self::remainingClockMs($locked, $myColor, $now);
            if ($remaining <= 0) {
                $postResult = $myColor === 'w'
                    ? ['result' => '0-1', 'winner_id' => $locked->black_user_id]
                    : ['result' => '1-0', 'winner_id' => $locked->white_user_id];
                return;
            }

            $game = self::gameFromPairing($locked);
            $legalMove = self::findLegalMove($game, strtolower($data['from']), strtolower($data['to']), $data['promotion'] ?? null);
            if (!$legalMove) {
                abort(422, 'Illegal move.');
            }

            $executed = $game->move([
                'from' => $legalMove->from,
                'to' => $legalMove->to,
                'promotion' => $legalMove->promotion,
            ]);

            if (!$executed) {
                abort(422, 'Illegal move.');
            }

            $whiteMs = (int) $locked->white_clock_ms;
            $blackMs = (int) $locked->black_clock_ms;
            $increment = $locked->tournament->incrementMs();

            if ($myColor === 'w') {
                $whiteMs = $remaining + $increment;
            } else {
                $blackMs = $remaining + $increment;
            }

            $locked->update([
                'current_fen' => $game->fen(),
                'move_count' => $locked->move_count + 1,
                'moves_pgn' => trim(($locked->moves_pgn ?? '') . ' ' . $executed->san),
                'white_clock_ms' => $whiteMs,
                'black_clock_ms' => $blackMs,
                'last_move_at' => $now,
                'draw_offer_by' => null,
            ]);

            if ($game->inCheckmate()) {
                $postResult = [
                    'result' => $myColor === 'w' ? '1-0' : '0-1',
                    'winner_id' => $myColor === 'w' ? $locked->white_user_id : $locked->black_user_id,
                ];
            } elseif ($game->inDraw()) {
                $postResult = ['result' => '0.5-0.5', 'winner_id' => null];
            }
        }, 5);

        if ($postResult) {
            TournamentScheduler::setResult($pairing->id, $postResult['result'], $postResult['winner_id']);
        }

        $pairing->refresh();
        $clocks = $pairing->liveClocks();

        return response()->json([
            'ok' => true,
            'status' => $pairing->status,
            'fen' => $pairing->current_fen,
            'move_count' => $pairing->move_count,
            'moves_pgn' => $pairing->moves_pgn,
            'white_clock_ms' => $clocks['white'],
            'black_clock_ms' => $clocks['black'],
            'side_to_move' => $pairing->sideToMove(),
            'result' => $pairing->result,
            'winner_id' => $pairing->winner_user_id,
            'draw_offer_by' => $pairing->draw_offer_by,
            'redirect_url' => route('student.tournaments.show', $pairing->tournament),
        ]);
    }

    public function resign($tournament, TournamentPairing $pairing)
    {
        $student = Auth::user();
        abort_unless($pairing->isPlayer($student->id), 403);

        self::ensureOpeningClocks($pairing);
        self::settleTimeoutIfAny($pairing);
        $pairing->refresh();

        if ($pairing->isComplete()) {
            return response()->json([
                'ok' => true,
                'status' => 'completed',
                'result' => $pairing->result,
                'redirect_url' => route('student.tournaments.show', $pairing->tournament),
            ]);
        }

        abort_unless($pairing->isActive(), 410, 'Game is not active.');

        $myColor = $pairing->colorOf($student->id);
        $result = $myColor === 'w' ? '0-1' : '1-0';
        $winner = $myColor === 'w' ? $pairing->black_user_id : $pairing->white_user_id;

        $pairing->update(['resigned_by' => $student->id]);
        $pairing = TournamentScheduler::setResult($pairing->id, $result, $winner);

        return response()->json([
            'ok' => true,
            'status' => 'completed',
            'result' => $pairing->result,
            'redirect_url' => route('student.tournaments.show', $pairing->tournament),
        ]);
    }

    public function drawOffer($tournament, TournamentPairing $pairing)
    {
        $student = Auth::user();
        abort_unless($pairing->isPlayer($student->id), 403);

        self::ensureOpeningClocks($pairing);
        self::settleTimeoutIfAny($pairing);
        $pairing->refresh();

        if ($pairing->isComplete()) {
            return response()->json([
                'ok' => true,
                'accepted' => $pairing->result === '0.5-0.5',
                'status' => 'completed',
                'result' => $pairing->result,
                'redirect_url' => route('student.tournaments.show', $pairing->tournament),
            ]);
        }

        abort_unless($pairing->isActive(), 410);

        if ($pairing->draw_offer_by && $pairing->draw_offer_by !== $student->id) {
            $pairing = TournamentScheduler::setResult($pairing->id, '0.5-0.5', null);

            return response()->json([
                'ok' => true,
                'accepted' => true,
                'status' => 'completed',
                'result' => $pairing->result,
                'redirect_url' => route('student.tournaments.show', $pairing->tournament),
            ]);
        }

        if ((int) $pairing->draw_offer_by !== (int) $student->id) {
            $pairing->update(['draw_offer_by' => $student->id]);
        }

        return response()->json([
            'ok' => true,
            'accepted' => false,
            'status' => 'active',
            'draw_offer_by' => $pairing->draw_offer_by,
        ]);
    }

    public static function ensureOpeningClocks(TournamentPairing $pairing): void
    {
        if (!$pairing->isActive() || (int) $pairing->move_count !== 0) {
            return;
        }

        $startingMs = $pairing->tournament->startingClockMs();
        if ($startingMs <= 0) {
            return;
        }

        $updates = [];
        if ((int) $pairing->white_clock_ms <= 0) {
            $updates['white_clock_ms'] = $startingMs;
        }
        if ((int) $pairing->black_clock_ms <= 0) {
            $updates['black_clock_ms'] = $startingMs;
        }
        if (!$pairing->last_move_at) {
            $updates['last_move_at'] = now();
        }
        if (!self::looksLikeFen($pairing->current_fen) || !str_contains((string) $pairing->current_fen, ' w ')) {
            $updates['current_fen'] = self::START_FEN;
        }

        if ($updates !== []) {
            $pairing->update($updates);
        }
    }

    protected static function settleTimeoutIfAny(TournamentPairing $pairing): void
    {
        $locked = TournamentPairing::query()->find($pairing->id);
        if (!$locked || !$locked->isActive()) {
            return;
        }

        $turn = $locked->sideToMove();
        if (!$turn) {
            return;
        }

        $remaining = self::remainingClockMs($locked, $turn, now());
        if ($remaining > 0) {
            return;
        }

        TournamentScheduler::flagPairing($locked);
    }

    protected static function remainingClockMs(TournamentPairing $pairing, string $side, $now): int
    {
        $stored = $side === 'w' ? (int) $pairing->white_clock_ms : (int) $pairing->black_clock_ms;
        if (!$pairing->last_move_at || $pairing->sideToMove() !== $side) {
            return $stored;
        }

        $elapsed = max(0, $now->diffInMilliseconds($pairing->last_move_at, false) * -1);
        return max(0, $stored - (int) $elapsed);
    }

    protected static function gameFromPairing(TournamentPairing $pairing): Chess
    {
        $fen = self::looksLikeFen($pairing->current_fen) ? $pairing->current_fen : self::START_FEN;
        return new Chess($fen);
    }

    protected static function findLegalMove(Chess $game, string $from, string $to, ?string $promotion = null): ?Move
    {
        $candidates = collect($game->moves())
            ->filter(fn (Move $move) => $move->from === $from && $move->to === $to)
            ->values();

        if ($candidates->isEmpty()) {
            return null;
        }

        if ($promotion) {
            $found = $candidates->first(fn (Move $move) => $move->promotion === strtolower($promotion));
            if ($found) {
                return $found;
            }
        }

        $queenPromotion = $candidates->first(fn (Move $move) => $move->promotion === 'q');
        return $queenPromotion ?: $candidates->first();
    }

    protected static function looksLikeFen(?string $fen): bool
    {
        return is_string($fen) && count(explode(' ', $fen)) >= 6;
    }
}
