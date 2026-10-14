<?php

namespace App\Http\Controllers\Live;

use App\Http\Controllers\Controller;
use App\Models\ClassQuestion;
use App\Models\ClassQuestionAttempt;
use App\Models\LiveClass;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class LeaderboardController extends Controller
{
    /**
     * Cumulative live-class leaderboard — sums points across EVERY question
     * the coach has asked in this class, lists every student in the group,
     * and is always visible to both the coach and all students.
     *
     * Response shape:
     * {
     *   questions_asked: int,
     *   students: [
     *     { user_id, name, initials, avatar_color,
     *       total_points, total_wrong, questions_attempted, questions_solved }
     *   ]
     * }
     */
    public function show(LiveClass $class, string $role)
    {
        $this->authorizeAccess($class, $role);

        // ── Question / attempts stats — cumulative across this whole class ─
        // We use a single aggregate query so the leaderboard scales even when
        // the coach has asked dozens of questions in a class.
        $questionIds   = ClassQuestion::where('live_class_id', $class->id)->pluck('id');
        $questionsAsked = $questionIds->count();

        $stats = collect();
        if ($questionsAsked > 0) {
            $stats = ClassQuestionAttempt::whereIn('class_question_id', $questionIds)
                ->select('user_id',
                    DB::raw('SUM(correct_count)                                   AS total_points'),
                    DB::raw('SUM(wrong_count)                                     AS total_wrong'),
                    DB::raw('COUNT(*)                                             AS questions_attempted'),
                    DB::raw('SUM(CASE WHEN completed THEN 1 ELSE 0 END)           AS questions_solved')
                )
                ->groupBy('user_id')
                ->get()
                ->keyBy('user_id');
        }

        // ── Roster: ALL students in the group, even with zero points ───────
        $students = $class->group?->students ?? collect();

        $rows = $students->map(function ($s) use ($stats) {
            $st = $stats->get($s->id);
            return [
                'user_id'             => $s->id,
                'name'                => $s->name,
                'initials'            => $s->initials,
                'avatar_color'        => $s->avatar_color,
                'total_points'        => (int) ($st->total_points ?? 0),
                'total_wrong'         => (int) ($st->total_wrong ?? 0),
                'questions_attempted' => (int) ($st->questions_attempted ?? 0),
                'questions_solved'    => (int) ($st->questions_solved ?? 0),
            ];
        })->sort(function ($a, $b) {
            // Higher points first; tie-break on fewer wrongs; then name.
            if ($b['total_points'] !== $a['total_points']) return $b['total_points'] - $a['total_points'];
            if ($a['total_wrong']  !== $b['total_wrong'])  return $a['total_wrong']  - $b['total_wrong'];
            return strcasecmp($a['name'], $b['name']);
        })->values();

        return response()->json([
            'questions_asked' => $questionsAsked,
            'students'        => $rows,
            'me_id'           => Auth::id(),
        ]);
    }

    /** Make sure the requester is the class coach, or a student in its group. */
    protected function authorizeAccess(LiveClass $class, string $role): void
    {
        abort_unless(in_array($role, ['coach', 'student'], true), 404);
        $user = Auth::user();

        if ($role === 'coach') {
            abort_unless($class->coach_id === $user->id, 403);
        } else {
            $inGroup = $class->group
                && $class->group->students()->where('users.id', $user->id)->exists();
            abort_unless($inGroup, 403);
        }
    }
}
