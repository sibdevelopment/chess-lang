<?php

namespace App\Http\Controllers\Tournament;

use App\Http\Controllers\Controller;
use App\Models\StudentAnalysis;
use App\Models\Tournament;
use App\Models\TournamentPairing;
use App\Support\TournamentScheduler;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Lichess-style spectator endpoints, shared by every role:
 *   - admin / staff / coach can watch any pairing, any time.
 *   - students can only watch when they don't have a live game of their own
 *     (the EnsureActiveTournamentGame middleware already redirects them away
 *     if they do, so we get this for free).
 *
 * Also hosts the per-pairing PGN download (any finished game) and the
 * student's "Export to Analyse" action that turns a finished tournament
 * game into a saved study in their Analyse tab.
 */
class SpectateController extends Controller
{
    /**
     * Render the read-only spectate board for a single pairing.
     */
    public function view(Tournament $tournament, TournamentPairing $pairing)
    {
        TournamentScheduler::advance($tournament);
        $pairing->refresh();

        abort_unless($pairing->tournament_id === $tournament->id, 404);
        $this->authorizeSpectate($pairing);
        abort_if($pairing->is_bye, 410, 'This is a bye — there is no game to watch.');

        $pairing->load(['white', 'black', 'winner']);

        return view('tournaments.spectate', [
            'tournament' => $tournament,
            'pairing'    => $pairing,
            'backUrl'    => $this->roleShowUrl($tournament),
        ]);
    }

    /**
     * JSON poll used by the spectate view to keep the board in sync.
     * Identical shape to PlayController::poll so the same board update
     * code can render either view.
     */
    public function poll(Tournament $tournament, TournamentPairing $pairing)
    {
        abort_unless($pairing->tournament_id === $tournament->id, 404);
        $this->authorizeSpectate($pairing);

        $clocks = $pairing->liveClocks();

        return response()->json([
            'status'         => $pairing->status,
            'fen'            => $pairing->current_fen,
            'move_count'     => $pairing->move_count,
            'moves_pgn'      => $pairing->moves_pgn,
            'white_clock_ms' => $clocks['white'],
            'black_clock_ms' => $clocks['black'],
            'side_to_move'   => $pairing->sideToMove(),
            'result'         => $pairing->result,
            'winner_id'      => $pairing->winner_user_id,
            'redirect_url'   => $this->roleShowUrl($tournament),
        ])->withHeaders([
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma'        => 'no-cache',
            'Expires'       => '0',
        ]);
    }

    /**
     * Stream the pairing's PGN as an attachment.
     * Admin / staff / coach can download any pairing.
     * Students can download only their own games.
     */
    public function downloadPgn(Tournament $tournament, TournamentPairing $pairing): StreamedResponse
    {
        abort_unless($pairing->tournament_id === $tournament->id, 404);
        $this->authorizePgnAccess($pairing);
        abort_if($pairing->is_bye, 410, 'A bye has no PGN to download.');

        $pairing->load(['white', 'black']);
        $pgn = self::buildPgn($tournament, $pairing);

        $filename = self::pgnFilename($tournament, $pairing);

        return response()->streamDownload(function () use ($pgn) {
            echo $pgn;
        }, $filename, [
            'Content-Type' => 'application/x-chess-pgn',
        ]);
    }

    /**
     * Student-only: take this finished pairing and create a saved Analyse
     * study from it. Lands the student straight in their study viewer.
     */
    public function exportToAnalyse(Tournament $tournament, TournamentPairing $pairing)
    {
        $student = Auth::user();
        abort_unless($student && $student->role === 'student', 403);
        abort_unless($pairing->tournament_id === $tournament->id, 404);
        abort_unless($pairing->isPlayer($student->id), 403, 'You can only export your own games.');
        abort_if($pairing->is_bye, 410, 'A bye has no PGN.');

        $pgn = self::buildPgn($tournament, $pairing);

        $opp = $pairing->opponentOf($student->id);
        $name = sprintf(
            '%s · R%d vs %s',
            $tournament->name,
            $pairing->round,
            $opp->name ?? 'Bye'
        );

        $study = StudentAnalysis::create([
            'user_id'    => $student->id,
            'name'       => mb_substr($name, 0, 200),
            'pgn'        => $pgn,
            'reviews'    => [],
            'commentary' => [],
            'opened_at'  => now(),
        ]);

        return redirect()
            ->route('student.analyse.show', $study)
            ->with('success', 'Saved to your Analyse tab. Open any move for engine + AI commentary.');
    }

    // ── Helpers ─────────────────────────────────────────────────────────

    /**
     * Spectator access check. Admin/staff/coach: yes always.
     * Students: only when they aren't a live player elsewhere
     * (middleware already blocks that case).
     */
    protected function authorizeSpectate(TournamentPairing $pairing): void
    {
        $u = Auth::user();
        abort_unless($u, 403);

        if (in_array($u->role, ['admin', 'staff', 'coach'], true)) {
            return;
        }

        // Student spectator. Disallow watching your own live game from the
        // spectate URL — you should be on the play page, full controls.
        if ($u->role === 'student' && $pairing->isActive() && $pairing->isPlayer($u->id)) {
            abort(403, 'Use the play page for your own active game.');
        }
    }

    /**
     * PGN download access. Admin/staff/coach: any. Student: own only.
     */
    protected function authorizePgnAccess(TournamentPairing $pairing): void
    {
        $u = Auth::user();
        abort_unless($u, 403);

        if (in_array($u->role, ['admin', 'staff', 'coach'], true)) {
            return;
        }

        abort_unless(
            $u->role === 'student' && $pairing->isPlayer($u->id),
            403,
            'You can only download your own games.'
        );
    }

    /**
     * Build a PGN with proper Seven Tag Roster tags, suitable for chess
     * databases and our own Analyse importer.
     */
    public static function buildPgn(Tournament $tournament, TournamentPairing $pairing): string
    {
        $white  = optional($pairing->white)->name  ?? '?';
        $black  = optional($pairing->black)->name  ?? '?';
        $date   = optional($tournament->scheduled_at)->format('Y.m.d') ?? '????.??.??';
        $event  = $tournament->name;
        $round  = (string) $pairing->round;
        $result = $pairing->result ?: '*';

        $headers = [
            '[Event "'      . self::esc($event) . '"]',
            '[Site "Archer Chess Academy"]',
            '[Date "'       . $date . '"]',
            '[Round "'      . $round . '"]',
            '[White "'      . self::esc($white) . '"]',
            '[Black "'      . self::esc($black) . '"]',
            '[Result "'     . $result . '"]',
            '[TimeControl "' . self::esc((string) $tournament->time_control) . '"]',
            '[Variant "Standard"]',
        ];

        $body = trim((string) $pairing->moves_pgn);
        // moves_pgn is a flat space-separated SAN list; nicely group with move
        // numbers so importers (and humans) read it cleanly.
        $body = self::numberMoves($body);

        return implode("\n", $headers) . "\n\n" . $body . ' ' . $result . "\n";
    }

    public static function pgnFilename(Tournament $tournament, TournamentPairing $pairing): string
    {
        $slug = preg_replace('/[^A-Za-z0-9._-]+/', '_', $tournament->name);
        $slug = trim($slug, '_') ?: 'tournament';
        $white = preg_replace('/[^A-Za-z0-9]+/', '', optional($pairing->white)->name ?? 'White');
        $black = preg_replace('/[^A-Za-z0-9]+/', '', optional($pairing->black)->name ?? 'Black');
        return sprintf('%s_R%d_%s_vs_%s.pgn', $slug, $pairing->round, $white, $black);
    }

    protected static function esc(string $s): string
    {
        return str_replace(['"', "\n", "\r"], ['\"', ' ', ' '], $s);
    }

    /**
     * Walk a flat SAN list and re-emit it with move numbers (1. e4 e5 2. Nf3 …).
     */
    protected static function numberMoves(string $sanList): string
    {
        $tokens = preg_split('/\s+/', trim($sanList)) ?: [];
        $out = [];
        $moveNo = 1;
        $whiteToMove = true;
        foreach ($tokens as $san) {
            if ($san === '') continue;
            if ($whiteToMove) {
                $out[] = $moveNo . '.';
                $out[] = $san;
            } else {
                $out[] = $san;
                $moveNo++;
            }
            $whiteToMove = !$whiteToMove;
        }
        return implode(' ', $out);
    }

    protected function roleShowUrl(Tournament $tournament): string
    {
        $u = Auth::user();
        $role = $u?->role ?? 'student';
        $route = match ($role) {
            'admin' => 'admin.tournaments.show',
            'staff' => 'staff.tournaments.show',
            'coach' => 'coach.tournaments.show',
            default => 'student.tournaments.show',
        };
        return route($route, $tournament);
    }
}
