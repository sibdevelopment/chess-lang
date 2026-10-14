<?php

namespace App\Http\Controllers\Quiz;

use App\Http\Controllers\Controller;
use App\Models\QuizSession;
use App\Models\QuizSessionAttendee;
use App\Models\QuizSessionResponse;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StudentQuizSessionController extends Controller
{
    /**
     * Sessions the student can see — must match their group level AND, if
     * the session is country-targeted, must include their country.
     */
    public function index()
    {
        $student = Auth::user();
        $levels  = $student->groups->pluck('level')->unique()->filter()->values();

        $upcoming = QuizSession::whereIn('level', $levels)
            ->whereIn('status', ['scheduled', 'live'])
            ->orderBy('scheduled_at')
            ->get()
            ->filter(fn ($s) => $s->isCountryAllowed($student->country))
            ->values();

        $past = QuizSession::whereIn('level', $levels)
            ->whereIn('status', ['ended'])
            ->whereHas('attendees', fn ($q) => $q->where('user_id', $student->id))
            ->orderByDesc('scheduled_at')
            ->limit(20)
            ->get();

        return view('quiz-sessions.index', [
            'upcoming' => $upcoming,
            'past'     => $past,
            'mode'     => 'student',
        ]);
    }

    /**
     * Lobby + live screen. If the session is live and student has joined,
     * we render the live page; otherwise the lobby with a Join button.
     */
    public function show(QuizSession $quizSession)
    {
        $student = Auth::user();

        // Eligibility: student's groups include the session's level AND
        // (if country-targeted) the student's country is on the list.
        $levels = $student->groups->pluck('level')->unique()->filter()->values();
        $eligible = $levels->contains($quizSession->level)
                    && $quizSession->isCountryAllowed($student->country);
        $hasJoined = $quizSession->hasJoined($student->id);

        abort_unless($eligible || $hasJoined, 403, 'You are not eligible for this quiz session.');

        $quizSession->load(['coach', 'attendees.user', 'currentQuiz']);

        return view('quiz-sessions.show', [
            'session'   => $quizSession,
            'mode'      => 'student',
            'hasJoined' => $hasJoined,
            'eligible'  => $eligible,
        ]);
    }

    /**
     * Join the session.
     */
    public function join(Request $request, QuizSession $quizSession)
    {
        $student = Auth::user();
        $levels = $student->groups->pluck('level')->unique()->filter()->values();
        abort_unless($levels->contains($quizSession->level), 403);
        abort_unless($quizSession->isCountryAllowed($student->country), 403, 'This session is not open to your region.');
        abort_if($quizSession->isEnded() || $quizSession->isCancelled(), 410, 'Session is over.');

        try {
            DB::transaction(function () use ($quizSession, $student) {
                QuizSessionAttendee::firstOrCreate(
                    [
                        'quiz_session_id' => $quizSession->id,
                        'user_id' => $student->id,
                    ],
                    [
                        'joined_at' => now(),
                    ]
                );
            });
        } catch (QueryException $e) {
            Log::warning('Quiz session join failed.', [
                'user_id' => $student->id,
                'action' => 'quiz_session.join',
                'model_type' => QuizSession::class,
                'model_id' => $quizSession->id,
                'route' => $request->route()?->getName(),
                'exception' => $e::class,
            ]);

            return back()->with('error', 'Could not join the session right now. Please try again.');
        }

        return redirect()->route('student.quiz-sessions.show', $quizSession)->with('success', 'You\'ve joined! Wait for your coach to push the first question.');
    }

    /**
     * Polling endpoint for the student live screen.
     */
    public function poll(QuizSession $quizSession)
    {
        $student = Auth::user();
        abort_unless($quizSession->hasJoined($student->id), 403);

        return response()->json(BroadcastPayload::buildStudentPayload($quizSession, $student->id));
    }

    /**
     * Student picks an option. Idempotent — the unique key on
     * (quiz_session_id, quiz_id, user_id) means a student answers each quiz
     * at most once.
     */
    public function answer(Request $request, QuizSession $quizSession)
    {
        $student = Auth::user();
        abort_unless($quizSession->isLive(), 410, 'Session is not live.');
        abort_unless($quizSession->hasJoined($student->id), 403);

        $data = $request->validate([
            'quiz_id'      => 'required|integer|exists:quizzes,id',
            'picked_index' => 'required|integer|min:0|max:3',
        ]);

        $quizSession->loadMissing('currentQuiz');
        // Must be answering the currently-broadcast quiz.
        abort_unless((int) $quizSession->current_quiz_id === (int) $data['quiz_id'], 422, 'This quiz is not live.');

        // Time-up check — refuse late answers.
        $remaining = $quizSession->secondsRemainingOnCurrent();
        if ($remaining !== null && $remaining <= 0) {
            return response()->json(['ok' => false, 'reason' => 'time_up'], 422);
        }

        $quiz = $quizSession->currentQuiz;
        $isCorrect = $quiz->isCorrect((int) $data['picked_index']);

        DB::transaction(function () use ($quizSession, $quiz, $student, $data, $isCorrect) {
            QuizSessionResponse::firstOrCreate(
                [
                    'quiz_session_id' => $quizSession->id,
                    'quiz_id'         => $quiz->id,
                    'user_id'         => $student->id,
                ],
                [
                    'picked_index' => (int) $data['picked_index'],
                    'is_correct'   => $isCorrect,
                    'picked_at'    => now(),
                ]
            );
        });

        return response()->json([
            'ok'            => true,
            'is_correct'    => $isCorrect,
            'correct_index' => (int) $quiz->correct_index,
        ]);
    }
}
