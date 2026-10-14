<?php

namespace App\Http\Controllers\Tournament;

use App\Http\Controllers\Controller;
use App\Models\Tournament;
use App\Models\TournamentPairing;
use App\Models\TournamentParticipant;
use App\Support\TournamentScheduler;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class StudentTournamentController extends Controller
{
    public function index()
    {
        TournamentScheduler::tick();

        $student = Auth::user();
        $activePairing = $this->activePairingFor($student->id);
        if ($activePairing && $activePairing->tournament) {
            return redirect()->route('student.tournaments.play', [$activePairing->tournament, $activePairing]);
        }

        $visible = Tournament::with(['participants', 'targetGroups:id', 'targetUsers:id'])
            ->orderByDesc('scheduled_at')
            ->get()
            ->filter(fn (Tournament $t) => $t->isStudentEligible($student) || $t->hasJoined($student->id))
            ->values();

        $today = $visible->filter(function (Tournament $t) {
            return $t->scheduled_at
                && $t->scheduled_at->isToday()
                && in_array($t->status, [
                    Tournament::STATUS_UPCOMING,
                    Tournament::STATUS_LIVE,
                    Tournament::STATUS_COMPLETED,
                    Tournament::STATUS_CANCELLED,
                    Tournament::STATUS_CANCELLED_INSUFFICIENT_PLAYERS,
                ], true);
        })->values();

        $upcoming = $visible->filter(fn (Tournament $t) => $t->scheduled_at && $t->scheduled_at->isFuture() && $t->isUpcoming())->values();

        $past = $visible->filter(function (Tournament $t) {
            if (in_array($t->status, [Tournament::STATUS_COMPLETED, Tournament::STATUS_CANCELLED, Tournament::STATUS_CANCELLED_INSUFFICIENT_PLAYERS], true)) {
                return !$t->scheduled_at || !$t->scheduled_at->isToday();
            }
            return false;
        })->values();

        return view('tournaments.index', [
            'today' => $today,
            'upcoming' => $upcoming,
            'past' => $past,
            'mode' => 'play',
        ]);
    }

    public function show(Tournament $tournament)
    {
        TournamentScheduler::advance($tournament);
        $tournament->refresh();

        $student = Auth::user();
        $hasJoined = $tournament->hasJoined($student->id);
        abort_unless($tournament->isStudentEligible($student) || $hasJoined, 403);

        $standings = $tournament->standings();
        $rounds = $tournament->pairings()
            ->with(['white', 'black', 'winner'])
            ->orderBy('round')
            ->orderBy('board')
            ->get()
            ->groupBy('round');

        $myPairing = $tournament->pairings()
            ->where('round', $tournament->current_round)
            ->where(function ($q) use ($student) {
                $q->where('white_user_id', $student->id)
                    ->orWhere('black_user_id', $student->id);
            })
            ->latest('id')
            ->first();

        if ($hasJoined && $myPairing && $myPairing->isActive() && !$myPairing->is_bye) {
            return redirect()->route('student.tournaments.play', [$tournament, $myPairing]);
        }

        return view('tournaments.show', [
            'tournament' => $tournament,
            'standings' => $standings,
            'rounds' => $rounds,
            'myPairing' => $myPairing,
            'mode' => 'play',
            'joinedStudents' => $tournament->joinedStudents(),
            'pendingStudents' => collect(),
            'hasJoined' => $hasJoined,
            'eligible' => $tournament->isStudentEligible($student),
        ]);
    }

    public function join(Request $request, Tournament $tournament)
    {
        $student = Auth::user();

        abort_unless($student->isStudent() && $student->is_active, 403, 'Only active students can join.');
        abort_unless($tournament->isStudentEligible($student), 403, 'You are not eligible for this tournament.');
        abort_unless($tournament->isUpcoming(), 403, 'Registration is closed.');
        abort_if($tournament->registrationClosesAt()->lessThanOrEqualTo(now()), 403, 'Registration is closed.');

        DB::transaction(function () use ($tournament, $student) {
            TournamentParticipant::firstOrCreate(
                [
                    'tournament_id' => $tournament->id,
                    'user_id' => $student->id,
                ],
                [
                    'score' => 0.0,
                    'buchholz' => 0.0,
                    'bye_received' => false,
                    'withdrawn' => false,
                ]
            );
        });

        return redirect()
            ->route('student.tournaments.show', $tournament)
            ->with('joined_message', "You've joined the tournament. Pairings will appear automatically when it starts.");
    }

    public function leave(Tournament $tournament)
    {
        $student = Auth::user();

        abort_unless($student->isStudent(), 403);
        abort_unless($tournament->isUpcoming(), 403, 'You can only leave before the tournament starts.');
        abort_if($tournament->registrationClosesAt()->lessThanOrEqualTo(now()), 403, 'Registration is closed.');

        $tournament->participants()->where('user_id', $student->id)->delete();

        return redirect()
            ->route('student.tournaments.show', $tournament)
            ->with('success', 'You left the tournament.');
    }

    public function play(Tournament $tournament, TournamentPairing $pairing)
    {
        TournamentScheduler::advance($tournament);
        $pairing->refresh();

        $student = Auth::user();
        abort_unless($pairing->tournament_id === $tournament->id, 404);
        abort_unless($pairing->isPlayer($student->id), 403, 'You are not a player in this game.');
        abort_if($pairing->is_bye, 410, 'This is a bye — no game to play.');
        abort_unless($pairing->isActive() || $pairing->isComplete(), 410, 'This game is not available.');

        \App\Http\Controllers\Tournament\PlayController::ensureOpeningClocks($pairing);
        $pairing->refresh();

        return view('tournaments.play', [
            'tournament' => $tournament,
            'pairing' => $pairing,
            'student' => $student,
        ]);
    }

    public function activeGameCheck()
    {
        TournamentScheduler::tick();

        $student = Auth::user();
        if (!$student) {
            return response()->json(['has_game' => false]);
        }

        $activePairing = $this->activePairingFor($student->id);
        if ($activePairing && $activePairing->tournament) {
            return response()->json([
                'has_game' => true,
                'url' => route('student.tournaments.play', [$activePairing->tournament, $activePairing]),
            ]);
        }

        return response()->json(['has_game' => false]);
    }

    protected function activePairingFor(int $studentId): ?TournamentPairing
    {
        return TournamentPairing::with('tournament')
            ->where('status', 'active')
            ->where('is_bye', false)
            ->where(function ($q) use ($studentId) {
                $q->where('white_user_id', $studentId)
                    ->orWhere('black_user_id', $studentId);
            })
            ->latest('id')
            ->first();
    }
}
