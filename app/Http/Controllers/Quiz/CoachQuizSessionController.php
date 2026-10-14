<?php

namespace App\Http\Controllers\Quiz;

use App\Http\Controllers\Controller;
use App\Models\Quiz;
use App\Models\QuizSession;
use App\Models\QuizSessionQuiz;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CoachQuizSessionController extends Controller
{
    /**
     * Coach lobby — list of sessions assigned to me.
     */
    public function index()
    {
        $coach = Auth::user();

        $upcoming = QuizSession::where('coach_id', $coach->id)
            ->whereIn('status', ['scheduled', 'live'])
            ->orderBy('scheduled_at')
            ->get();

        $past = QuizSession::where('coach_id', $coach->id)
            ->whereIn('status', ['ended', 'cancelled'])
            ->orderByDesc('scheduled_at')
            ->limit(40)
            ->get();

        return view('quiz-sessions.index', [
            'upcoming' => $upcoming,
            'past'     => $past,
            'mode'     => 'coach',
        ]);
    }

    /**
     * Coach views a session before/during/after live.
     *   - Before live: show a "Start session" button — week is auto-picked.
     *   - During live: show live-broadcast control panel.
     *   - After live:  show leaderboard.
     */
    public function show(QuizSession $quizSession)
    {
        $coach = Auth::user();
        abort_unless($quizSession->coach_id === $coach->id, 403);

        $quizSession->load(['coach', 'attendees.user', 'currentQuiz', 'quizQueue.quiz', 'responses']);

        [$suggestedWeek, $availableWeeks] = $this->resolveNextWeek($quizSession);

        $leaderboard = $quizSession->isEnded()
            ? AdminQuizSessionController::buildLeaderboard($quizSession)
            : [];

        return view('quiz-sessions.show', [
            'session'        => $quizSession,
            'mode'           => 'coach',
            'leaderboard'    => $leaderboard,
            'suggestedWeek'  => $suggestedWeek,
            'availableWeeks' => $availableWeeks,
        ]);
    }

    /**
     * Decide which week THIS session should broadcast. Pre-defined by the system:
     *   - Open session (no countries) → cycle across past open sessions.
     *   - Country-targeted session    → cycle across past sessions whose target list
     *     overlaps ANY of this session's countries.
     *
     * The chosen week walks forward through whichever weeks the admin has uploaded
     * to the quiz bank and wraps back to the first week once it reaches the end.
     * So with weeks 1-4 in the bank, the sequence is 1 → 2 → 3 → 4 → 1 → 2 → …
     *
     * @return array{0:int,1:\Illuminate\Support\Collection}  [suggestedWeek, availableWeeks]
     */
    private function resolveNextWeek(QuizSession $quizSession): array
    {
        $targetCountries = is_array($quizSession->countries) ? $quizSession->countries : [];

        $priorQuery = QuizSession::where('id', '!=', $quizSession->id)
            ->whereNotNull('week')
            ->orderByDesc('started_at')
            ->orderByDesc('id');

        if (empty($targetCountries)) {
            $priorQuery->where(function ($q) {
                $q->whereNull('countries')->orWhere('countries', '[]');
            });
        } else {
            $priorQuery->where(function ($q) use ($targetCountries) {
                foreach ($targetCountries as $code) {
                    $q->orWhereJsonContains('countries', $code);
                }
            });
        }

        $lastUsedWeek = $priorQuery->value('week');

        $availableWeeks = Quiz::query()->select('week')
            ->distinct()->orderBy('week')->pluck('week');

        if ($availableWeeks->isEmpty()) {
            $suggestedWeek = $lastUsedWeek ? ((int) $lastUsedWeek + 1) : 1;
        } elseif (!$lastUsedWeek) {
            $suggestedWeek = (int) $availableWeeks->first();
        } else {
            $next = $availableWeeks->first(fn ($w) => (int) $w > (int) $lastUsedWeek);
            $suggestedWeek = (int) ($next ?? $availableWeeks->first());
        }

        return [$suggestedWeek, $availableWeeks];
    }

    /**
     * Coach starts the session — week is pre-defined by the system based on
     * the scheduling rules (open vs. country-targeted cycle). The coach has
     * no say in which week broadcasts; anything submitted in the request is
     * ignored. We snapshot the quiz IDs into quiz_session_quizzes so the
     * broadcast queue is stable.
     */
    public function start(Request $request, QuizSession $quizSession)
    {
        $coach = Auth::user();
        abort_unless($quizSession->coach_id === $coach->id, 403);
        abort_if($quizSession->isLive() || $quizSession->isEnded(), 410, 'Session already started.');

        // Auto-resolve the week — admin/staff scheduled the session, the
        // system decides which week to broadcast. Coach just hits Start.
        [$resolvedWeek, $availableWeeks] = $this->resolveNextWeek($quizSession);

        if ($availableWeeks->isEmpty()) {
            return back()->with('error',
                'No quizzes in the bank yet. Ask an admin to add questions to the quiz bank first.');
        }

        // Build the queue from the quiz bank for this week. The bank itself is
        // not country-tagged anymore — country targeting lives on the session,
        // and per-country week progression decides which week is shown next.
        $quizzes = Quiz::where('week', $resolvedWeek)
            ->orderBy('category')
            ->orderBy('id')
            ->get();

        if ($quizzes->isEmpty()) {
            return back()->with('error',
                "No quizzes found for week {$resolvedWeek}. " .
                'Ask an admin to add questions for this week to the bank.');
        }

        DB::transaction(function () use ($quizSession, $quizzes, $resolvedWeek) {
            $quizSession->update([
                'status'     => 'live',
                'week'       => $resolvedWeek,
                'started_at' => now(),
            ]);
            // Snapshot the queue.
            $quizSession->quizQueue()->delete();
            foreach ($quizzes as $i => $q) {
                QuizSessionQuiz::create([
                    'quiz_session_id' => $quizSession->id,
                    'quiz_id'         => $q->id,
                    'position'        => $i + 1,
                    'shown'           => false,
                ]);
            }
        });

        return redirect()->route('coach.quiz-sessions.show', $quizSession)
            ->with('success', "Session started — broadcasting Week {$resolvedWeek}. Pick a quiz to begin.");
    }

    /**
     * Push the next (or a specific) quiz onto the live broadcast.
     */
    public function broadcast(Request $request, QuizSession $quizSession)
    {
        $coach = Auth::user();
        abort_unless($quizSession->coach_id === $coach->id, 403);
        abort_unless($quizSession->isLive(), 410, 'Session is not live.');

        $queueId = $request->input('queue_id'); // optional explicit quiz pick
        DB::transaction(function () use ($quizSession, $queueId) {
            // Mark previous current as shown.
            if ($quizSession->current_quiz_id) {
                QuizSessionQuiz::where('quiz_session_id', $quizSession->id)
                    ->where('quiz_id', $quizSession->current_quiz_id)
                    ->update(['shown' => true]);
            }

            // Pick next.
            $next = $queueId
                ? QuizSessionQuiz::where('quiz_session_id', $quizSession->id)->where('id', $queueId)->first()
                : QuizSessionQuiz::where('quiz_session_id', $quizSession->id)->where('shown', false)->orderBy('position')->first();

            if (!$next) {
                $quizSession->update([
                    'current_quiz_id'         => null,
                    'current_quiz_started_at' => null,
                ]);
                return;
            }

            $quizSession->update([
                'current_quiz_id'         => $next->quiz_id,
                'current_quiz_started_at' => now(),
            ]);
            $next->update(['shown' => false]);
        });

        return back();
    }

    /**
     * Force-end the currently-shown quiz immediately (advance to next on next click).
     */
    public function endCurrent(Request $request, QuizSession $quizSession)
    {
        $coach = Auth::user();
        abort_unless($quizSession->coach_id === $coach->id, 403);

        if ($quizSession->current_quiz_id) {
            QuizSessionQuiz::where('quiz_session_id', $quizSession->id)
                ->where('quiz_id', $quizSession->current_quiz_id)
                ->update(['shown' => true]);
            $quizSession->update([
                'current_quiz_id'         => null,
                'current_quiz_started_at' => null,
            ]);
        }
        return back();
    }

    /**
     * End the entire session.
     */
    public function endSession(Request $request, QuizSession $quizSession)
    {
        $coach = Auth::user();
        abort_unless($quizSession->coach_id === $coach->id, 403);

        $quizSession->update([
            'status'                  => 'ended',
            'ended_at'                => now(),
            'current_quiz_id'         => null,
            'current_quiz_started_at' => null,
        ]);

        return redirect()->route('coach.quiz-sessions.show', $quizSession)->with('success', 'Session ended. Leaderboard is now visible.');
    }

    /**
     * Polling endpoint for the coach's live screen — returns the live grid
     * of who-picked-what for the current quiz, plus session state.
     */
    public function pollCoach(QuizSession $quizSession)
    {
        $coach = Auth::user();
        abort_unless($quizSession->coach_id === $coach->id, 403);

        return response()->json(BroadcastPayload::buildCoachPayload($quizSession));
    }
}
