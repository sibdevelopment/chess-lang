<?php

namespace App\Support;

use App\Models\Tournament;
use App\Models\TournamentPairing;
use App\Models\TournamentParticipant;
use App\Services\ArcherJourney\ArcherLmsEventBridge;
use App\Services\ArcherJourney\FideRatingService;
use Illuminate\Support\Facades\DB;

class TournamentScheduler
{
    public static function tick(): void
    {
        Tournament::query()
            ->whereIn('status', [Tournament::STATUS_UPCOMING, Tournament::STATUS_LIVE])
            ->orderBy('scheduled_at')
            ->pluck('id')
            ->each(fn (int $id) => self::advance($id));
    }

    public static function advance(Tournament|int $tournament): void
    {
        $tournamentId = $tournament instanceof Tournament ? $tournament->id : $tournament;
        $t = Tournament::find($tournamentId);
        if (! $t || $t->isTerminal()) {
            return;
        }

        if ($t->isUpcoming() && $t->scheduled_at && $t->scheduled_at->lessThanOrEqualTo(now())) {
            self::startTournament($t->id);
        }

        $t = Tournament::find($tournamentId);
        if (! $t || ! $t->isLive()) {
            return;
        }

        self::timeoutExpiredGames($t->id);
        self::advanceRoundIfReady($t->id);
    }

    public static function startTournament(Tournament|int $tournament): void
    {
        $tournamentId = $tournament instanceof Tournament ? $tournament->id : $tournament;

        DB::transaction(function () use ($tournamentId) {
            /** @var Tournament|null $t */
            $t = Tournament::query()->lockForUpdate()->find($tournamentId);
            if (! $t || ! $t->isUpcoming() || ! $t->scheduled_at || $t->scheduled_at->isFuture()) {
                return;
            }

            if ($t->pairings()->where('round', 1)->exists()) {
                $t->update([
                    'status' => Tournament::STATUS_LIVE,
                    'current_round' => max(1, (int) $t->current_round),
                    'round_started_at' => $t->round_started_at ?: $t->scheduled_at,
                    'next_round_at' => null,
                    'cancellation_reason' => null,
                ]);

                return;
            }

            $joinedCount = $t->participants()->where('withdrawn', false)->count();
            if ($joinedCount < $t->minimumPlayers()) {
                $t->update([
                    'status' => Tournament::STATUS_CANCELLED_INSUFFICIENT_PLAYERS,
                    'cancellation_reason' => "Only {$joinedCount} joined players; minimum {$t->minimumPlayers()} required.",
                    'next_round_at' => null,
                    'round_started_at' => null,
                ]);

                return;
            }

            $t->update([
                'status' => Tournament::STATUS_LIVE,
                'current_round' => 1,
                'round_started_at' => $t->scheduled_at->copy(),
                'next_round_at' => null,
                'completed_at' => null,
                'cancellation_reason' => null,
            ]);

            SwissPairing::generateRound($t->fresh(), 1, $t->scheduled_at->copy());
        }, 5);

        $started = Tournament::find($tournamentId);
        if ($started?->isLive()) {
            app(ArcherLmsEventBridge::class)->tournamentStarted($started);
        }
    }

    public static function timeoutExpiredGames(Tournament|int $tournament): void
    {
        $tournamentId = $tournament instanceof Tournament ? $tournament->id : $tournament;
        $t = Tournament::find($tournamentId);
        if (! $t || ! $t->isLive()) {
            return;
        }

        $active = $t->pairings()
            ->where('round', $t->current_round)
            ->where('status', 'active')
            ->get();

        foreach ($active as $pairing) {
            $remaining = $pairing->liveClockForSideToMove();
            if ($remaining <= 0) {
                self::flagPairing($pairing);
            }
        }
    }

    public static function flagPairing(TournamentPairing|int $pairing): void
    {
        $p = $pairing instanceof TournamentPairing ? $pairing->fresh() : TournamentPairing::find($pairing);
        if (! $p || ! $p->isActive()) {
            return;
        }

        $sideToMove = $p->sideToMove();
        if ($sideToMove === 'w') {
            self::setResult($p, '0-1', $p->black_user_id);
        } elseif ($sideToMove === 'b') {
            self::setResult($p, '1-0', $p->white_user_id);
        } else {
            self::setResult($p, '0.5-0.5', null);
        }
    }

    public static function setResult(TournamentPairing|int $pairing, string $result, ?int $winnerId): TournamentPairing
    {
        $pairingId = $pairing instanceof TournamentPairing ? $pairing->id : $pairing;

        DB::transaction(function () use ($pairingId, $result, $winnerId) {
            /** @var TournamentPairing|null $p */
            $p = TournamentPairing::query()->lockForUpdate()->find($pairingId);
            if (! $p || $p->isCancelled() || $p->isComplete()) {
                return;
            }

            $p->update([
                'status' => 'completed',
                'result' => $result,
                'winner_user_id' => $winnerId,
                'completed_at' => now(),
            ]);

            $delta = match ($result) {
                '1-0' => ['white' => 1.0, 'black' => 0.0],
                '0-1' => ['white' => 0.0, 'black' => 1.0],
                '0.5-0.5' => ['white' => 0.5, 'black' => 0.5],
                default => ['white' => 0.0, 'black' => 0.0],
            };

            if ($p->white_user_id) {
                TournamentParticipant::query()
                    ->where('tournament_id', $p->tournament_id)
                    ->where('user_id', $p->white_user_id)
                    ->lockForUpdate()
                    ->first()?->increment('score', $delta['white']);
            }

            if ($p->black_user_id) {
                TournamentParticipant::query()
                    ->where('tournament_id', $p->tournament_id)
                    ->where('user_id', $p->black_user_id)
                    ->lockForUpdate()
                    ->first()?->increment('score', $delta['black']);
            }
        }, 5);

        $fresh = TournamentPairing::findOrFail($pairingId);
        app(ArcherLmsEventBridge::class)->tournamentGameCompleted($fresh);
        self::advance($fresh->tournament_id);

        return $fresh;
    }

    public static function advanceRoundIfReady(Tournament|int $tournament): void
    {
        $tournamentId = $tournament instanceof Tournament ? $tournament->id : $tournament;

        DB::transaction(function () use ($tournamentId) {
            /** @var Tournament|null $t */
            $t = Tournament::query()->lockForUpdate()->find($tournamentId);
            if (! $t || ! $t->isLive()) {
                return;
            }

            $unfinished = $t->pairings()
                ->where('round', $t->current_round)
                ->whereNotIn('status', ['completed', 'cancelled'])
                ->count();

            if ($unfinished > 0) {
                return;
            }

            self::recomputeBuchholz($t);

            if ($t->current_round >= $t->rounds) {
                $t->update([
                    'status' => Tournament::STATUS_COMPLETED,
                    'completed_at' => $t->completed_at ?: now(),
                    'next_round_at' => null,
                ]);

                return;
            }

            if (! $t->next_round_at) {
                $t->update([
                    'next_round_at' => now()->copy()->addMinutes(max(1, (int) ($t->round_break_minutes ?? 2))),
                ]);

                return;
            }

            if ($t->next_round_at->isFuture()) {
                return;
            }

            $nextRound = $t->current_round + 1;
            if ($t->pairings()->where('round', $nextRound)->exists()) {
                $t->update([
                    'current_round' => $nextRound,
                    'round_started_at' => now(),
                    'next_round_at' => null,
                ]);

                return;
            }

            $t->update([
                'current_round' => $nextRound,
                'round_started_at' => now(),
                'next_round_at' => null,
            ]);

            SwissPairing::generateRound($t->fresh(), $nextRound, now());
        }, 5);

        $completed = Tournament::find($tournamentId);
        if ($completed?->isCompleted()) {
            app(FideRatingService::class)->processTournament($completed);
        }
    }

    public static function cancelTournament(Tournament|int $tournament, string $status, ?string $reason = null): void
    {
        $tournamentId = $tournament instanceof Tournament ? $tournament->id : $tournament;

        DB::transaction(function () use ($tournamentId, $status, $reason) {
            /** @var Tournament|null $t */
            $t = Tournament::query()->lockForUpdate()->find($tournamentId);
            if (! $t || $t->isTerminal()) {
                return;
            }

            $t->update([
                'status' => $status,
                'cancellation_reason' => $reason,
                'next_round_at' => null,
                'completed_at' => $status === Tournament::STATUS_COMPLETED ? now() : $t->completed_at,
            ]);

            $t->pairings()
                ->whereIn('status', ['pending', 'active'])
                ->update([
                    'status' => 'cancelled',
                    'completed_at' => now(),
                ]);
        }, 5);
    }

    public static function recomputeBuchholz(Tournament|int $tournament): void
    {
        $t = $tournament instanceof Tournament
            ? $tournament->fresh(['participants'])
            : Tournament::with('participants')->find($tournament);

        if (! $t) {
            return;
        }

        $scores = $t->participants->keyBy('user_id')->map->score;

        foreach ($t->participants as $participant) {
            $opponents = $t->pairings()
                ->where('is_bye', false)
                ->where(function ($q) use ($participant) {
                    $q->where('white_user_id', $participant->user_id)
                        ->orWhere('black_user_id', $participant->user_id);
                })
                ->get();

            $sum = 0.0;
            foreach ($opponents as $game) {
                $opponentId = $game->white_user_id === $participant->user_id ? $game->black_user_id : $game->white_user_id;
                if ($opponentId && isset($scores[$opponentId])) {
                    $sum += (float) $scores[$opponentId];
                }
            }

            $participant->update(['buchholz' => round($sum, 1)]);
        }
    }
}
