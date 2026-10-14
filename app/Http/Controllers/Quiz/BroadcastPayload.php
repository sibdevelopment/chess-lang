<?php

namespace App\Http\Controllers\Quiz;

use App\Models\QuizSession;
use App\Models\QuizSessionResponse;

/**
 * Shared shape-builder used by both the coach and student polling endpoints.
 * Coach view: includes per-student pick grid for the current quiz.
 * Student view: includes only what THIS student answered (no leak of others).
 */
class BroadcastPayload
{
    public static function buildStudentPayload(QuizSession $session, int $userId): array
    {
        $session->loadMissing(['currentQuiz']);

        $base = self::sessionState($session);

        if (!$session->currentQuiz) {
            $base['quiz'] = null;
            return $base;
        }

        $quiz = $session->currentQuiz;
        $myResp = QuizSessionResponse::where('quiz_session_id', $session->id)
            ->where('quiz_id',         $quiz->id)
            ->where('user_id',         $userId)
            ->first();

        $base['quiz'] = [
            'id'              => $quiz->id,
            'type'            => $quiz->type,
            'category'        => $quiz->category,
            'week'            => $quiz->week,
            'question_text'   => $quiz->question_text,
            'image_url'       => $quiz->questionImageUrl(),
            'options'         => $quiz->optionsForRender(),
            'time_limit'      => (int) $quiz->time_limit_seconds,
            'remaining'       => $session->secondsRemainingOnCurrent(),
            'my_pick'         => $myResp ? (int) $myResp->picked_index : null,
            'my_correct'      => $myResp ? (bool) $myResp->is_correct : null,
            'correct_index'   => $myResp ? (int) $quiz->correct_index : null, // only revealed AFTER pick
        ];
        return $base;
    }

    public static function buildCoachPayload(QuizSession $session): array
    {
        $session->loadMissing(['currentQuiz', 'attendees.user', 'responses']);

        $base = self::sessionState($session);
        $base['attendees'] = $session->attendees->map(fn ($a) => [
            'id'   => $a->user_id,
            'name' => $a->user->name ?? '—',
        ])->values();

        if (!$session->currentQuiz) {
            $base['quiz'] = null;
            return $base;
        }

        $quiz = $session->currentQuiz;
        $picks = QuizSessionResponse::where('quiz_session_id', $session->id)
            ->where('quiz_id',         $quiz->id)
            ->get()
            ->keyBy('user_id');

        $picksOut = [];
        $tally    = [0, 0, 0, 0];
        $correctCount = 0;
        foreach ($session->attendees as $a) {
            $r = $picks->get($a->user_id);
            $picksOut[] = [
                'user_id'    => $a->user_id,
                'name'       => $a->user->name ?? '—',
                'picked'     => $r ? (int) $r->picked_index : null,
                'is_correct' => $r ? (bool) $r->is_correct : null,
            ];
            if ($r) {
                $tally[(int) $r->picked_index]++;
                if ($r->is_correct) $correctCount++;
            }
        }

        $base['quiz'] = [
            'id'              => $quiz->id,
            'type'            => $quiz->type,
            'category'        => $quiz->category,
            'week'            => $quiz->week,
            'question_text'   => $quiz->question_text,
            'image_url'       => $quiz->questionImageUrl(),
            'options'         => $quiz->optionsForRender(),
            'correct_index'   => (int) $quiz->correct_index,
            'time_limit'      => (int) $quiz->time_limit_seconds,
            'remaining'       => $session->secondsRemainingOnCurrent(),
            'tally'           => $tally,
            'correct_count'   => $correctCount,
            'picks'           => $picksOut,
        ];
        return $base;
    }

    protected static function sessionState(QuizSession $session): array
    {
        return [
            'session_id' => $session->id,
            'status'     => $session->status,
            'name'       => $session->name,
            'level'      => $session->level,
            'week'       => $session->week,
            'started_at' => optional($session->started_at)->toIso8601String(),
            'ended_at'   => optional($session->ended_at)->toIso8601String(),
        ];
    }
}
