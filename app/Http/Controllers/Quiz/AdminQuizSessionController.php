<?php

namespace App\Http\Controllers\Quiz;

use App\Http\Controllers\Controller;
use App\Models\Quiz;
use App\Models\QuizSession;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Admin/Staff: schedule a quiz session.
 *   - name
 *   - coach (any user with role=coach)
 *   - level (beginner/intermediate/advanced) — used to match students by Group level
 *   - day + date + time → scheduled_at
 *
 * The week is NOT fixed at scheduling time; the coach picks a week when they
 * actually start the session (see CoachQuizSessionController).
 */
class AdminQuizSessionController extends Controller
{
    public function index()
    {
        $upcoming = QuizSession::whereIn('status', ['scheduled', 'live'])
            ->orderBy('scheduled_at')
            ->with(['coach', 'creator'])
            ->get();

        $past = QuizSession::whereIn('status', ['ended', 'cancelled'])
            ->orderByDesc('scheduled_at')
            ->with(['coach', 'creator'])
            ->limit(40)
            ->get();

        return view('quiz-sessions.index', [
            'upcoming' => $upcoming,
            'past'     => $past,
            'mode'     => 'manage',
        ]);
    }

    public function create()
    {
        return view('quiz-sessions.create', [
            'session' => null,
            'coaches' => User::where('role', 'coach')->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $countryCodes = array_keys(User::STUDENT_COUNTRY_CODES);

        $data = $request->validate([
            'name'         => 'required|string|max:120',
            'coach_id'     => 'required|integer|exists:users,id',
            'level'        => 'required|in:beginner,intermediate,advanced',
            'countries'    => 'nullable|array',
            'countries.*'  => 'in:' . implode(',', $countryCodes),
            'scheduled_at' => 'required|date',
        ]);

        // Sanity: coach must have role=coach.
        $coach = User::findOrFail($data['coach_id']);
        if ($coach->role !== 'coach') {
            throw ValidationException::withMessages([
                'coach_id' => 'Selected user is not a coach.',
            ]);
        }

        $s = QuizSession::create([
            'name'         => $data['name'],
            'coach_id'     => $data['coach_id'],
            'level'        => $data['level'],
            'countries'    => !empty($data['countries']) ? array_values(array_unique($data['countries'])) : null,
            'scheduled_at' => $data['scheduled_at'],
            'status'       => 'scheduled',
            'created_by'   => Auth::id(),
        ]);

        return redirect()->route(self::roleHome())->with('success', 'Quiz session scheduled.');
    }

    public function edit(QuizSession $quizSession)
    {
        abort_if($quizSession->isLive() || $quizSession->isEnded(), 410, 'Cannot edit a session that has started.');
        return view('quiz-sessions.create', [
            'session' => $quizSession,
            'coaches' => User::where('role', 'coach')->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, QuizSession $quizSession)
    {
        abort_if($quizSession->isLive() || $quizSession->isEnded(), 410, 'Cannot edit a session that has started.');

        $countryCodes = array_keys(User::STUDENT_COUNTRY_CODES);

        $data = $request->validate([
            'name'         => 'required|string|max:120',
            'coach_id'     => 'required|integer|exists:users,id',
            'level'        => 'required|in:beginner,intermediate,advanced',
            'countries'    => 'nullable|array',
            'countries.*'  => 'in:' . implode(',', $countryCodes),
            'scheduled_at' => 'required|date',
        ]);

        $data['countries'] = !empty($data['countries'])
            ? array_values(array_unique($data['countries']))
            : null;

        $coach = User::findOrFail($data['coach_id']);
        if ($coach->role !== 'coach') {
            throw ValidationException::withMessages([
                'coach_id' => 'Selected user is not a coach.',
            ]);
        }

        $quizSession->update($data);
        return redirect()->route(self::roleHome())->with('success', 'Session updated.');
    }

    public function show(QuizSession $quizSession)
    {
        $quizSession->load(['coach', 'creator', 'attendees.user', 'currentQuiz', 'quizQueue.quiz', 'responses']);

        // Build the leaderboard for ended sessions.
        $leaderboard = [];
        if ($quizSession->isEnded()) {
            $leaderboard = $this->buildLeaderboard($quizSession);
        }

        return view('quiz-sessions.show', [
            'session'      => $quizSession,
            'leaderboard'  => $leaderboard,
            'mode'         => 'manage',
        ]);
    }

    public function cancel(QuizSession $quizSession)
    {
        if (!$quizSession->isEnded()) {
            $quizSession->update(['status' => 'cancelled']);
        }
        return back()->with('success', 'Session cancelled.');
    }

    public function destroy(QuizSession $quizSession)
    {
        if ($quizSession->isLive()) {
            return back()->with('error', 'Live sessions must be ended or cancelled before deletion.');
        }

        try {
            $quizSession->delete();
        } catch (QueryException $e) {
            Log::warning('Quiz session delete blocked.', [
                'user_id' => Auth::id(),
                'action' => 'quiz_session.delete',
                'model_type' => QuizSession::class,
                'model_id' => $quizSession->id,
                'route' => request()->route()?->getName(),
                'exception' => $e::class,
            ]);

            return back()->with('error', 'This quiz session cannot be deleted because it is already linked to live or historical data.');
        }

        return redirect()->route(self::roleHome())->with('success', 'Session deleted.');
    }

    // ── Shared helpers ───────────────────────────────────────────────────

    /**
     * Build a leaderboard array of [user, score, picks-by-quiz] for a session.
     */
    public static function buildLeaderboard(QuizSession $session): array
    {
        $session->loadMissing(['attendees.user', 'responses.user', 'responses.quiz', 'quizQueue.quiz']);

        $rows = [];
        foreach ($session->attendees as $att) {
            if (!$att->user) continue;
            $rows[$att->user_id] = [
                'user'     => $att->user,
                'correct'  => 0,
                'wrong'    => 0,
                'answered' => 0,
                'picks'    => [],   // [quiz_id => ['picked' => int, 'is_correct' => bool]]
            ];
        }

        foreach ($session->responses as $r) {
            if (!isset($rows[$r->user_id])) {
                // Student answered but somehow isn't in attendees — add row anyway.
                $rows[$r->user_id] = [
                    'user'     => $r->user,
                    'correct'  => 0,
                    'wrong'    => 0,
                    'answered' => 0,
                    'picks'    => [],
                ];
            }
            $rows[$r->user_id]['answered']++;
            if ($r->is_correct) $rows[$r->user_id]['correct']++;
            else                $rows[$r->user_id]['wrong']++;
            $rows[$r->user_id]['picks'][$r->quiz_id] = [
                'picked'     => (int) $r->picked_index,
                'is_correct' => (bool) $r->is_correct,
            ];
        }

        // Sort by correct desc, answered asc (faster = better), name asc.
        usort($rows, function ($a, $b) {
            if ($a['correct'] !== $b['correct']) return $b['correct'] <=> $a['correct'];
            if ($a['answered'] !== $b['answered']) return $a['answered'] <=> $b['answered'];
            return strcmp(($a['user']->name ?? ''), ($b['user']->name ?? ''));
        });

        return $rows;
    }

    public static function roleHome(): string
    {
        $u = Auth::user();
        if ($u->isAdmin()) return 'admin.quiz-sessions.index';
        if ($u->isStaff()) return 'staff.quiz-sessions.index';
        return 'admin.quiz-sessions.index';
    }
}
