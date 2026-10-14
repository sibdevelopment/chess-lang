<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Chapter;
use App\Models\ClassParticipant;
use App\Models\LiveClass;
use App\Support\ClassScheduler;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ClassController extends Controller
{
    private const UPCOMING_CLASSES_LIMIT = 10;

    public function index(Request $request)
    {
        $student = Auth::user();
        $tz = $student->preferredTimezone();
        $range = $request->get('range', 'today');
        $groups = $student->groups()->with(['coach', 'chapters', 'schedules', 'scheduleSlots'])->get();
        $groupIds = $groups->pluck('id');

        ClassScheduler::syncForGroups($groups);

        $live = LiveClass::where('status', 'live')
            ->whereIn('group_id', $groupIds)
            ->with(['coach', 'chapter', 'group'])
            ->latest('started_at')
            ->first();

        $history = LiveClass::whereIn('status', ['ended', 'completed', 'cancelled'])
            ->whereIn('group_id', $groupIds)
            ->with(['coach', 'chapter', 'group'])
            ->orderByDesc(DB::raw(LiveClass::statusTimestampOrderExpression()))
            ->limit(50)
            ->get();

        $scheduledClasses = LiveClass::where('status', 'scheduled')
            ->whereIn('group_id', $groupIds)
            ->where('scheduled_for', '>=', now()->utc()->subDay())
            ->with(['coach', 'chapter', 'group', 'schedule'])
            ->orderBy('scheduled_for')
            ->get();

        $todayClasses = $this->sortClassesByChapterSequence($scheduledClasses->filter(function (LiveClass $class) {
            $local = $class->scheduled_for?->copy()->timezone($class->displayTimezone());

            return $local && $local->isToday();
        }))->values();

        $upcomingClasses = $this->sortClassesByChapterSequence($scheduledClasses->reject(function (LiveClass $class) {
            $local = $class->scheduled_for?->copy()->timezone($class->displayTimezone());

            return $local && $local->isToday();
        }))->values()->take(self::UPCOMING_CLASSES_LIMIT)->values();

        $pastClasses = $this->sortClassesByChapterSequence(
            $history->filter(fn (LiveClass $class) => in_array($class->status, ['ended', 'completed'], true))
        )->values();
        $cancelledClasses = $this->sortClassesByChapterSequence(
            $history->where('status', 'cancelled')
        )->values();

        $counts = [
            'live' => $live ? 1 : 0,
            'today' => $todayClasses->count(),
            'past' => $pastClasses->count(),
            'upcoming' => $upcomingClasses->count(),
            'cancelled' => $cancelledClasses->count(),
        ];

        return view('student.classes.index', compact(
            'live', 'todayClasses', 'upcomingClasses', 'pastClasses', 'cancelledClasses', 'range', 'counts'
        ));
    }

    public function join(LiveClass $class)
    {
        $student = Auth::user();

        $this->assertStudentAssignedToClassGroup($class, $student->id);
        abort_unless($class->isLive(), 403, 'This class is not live.');
        abort_unless((int) $class->chapter_index === (int) $class->group->unlocked_chapter_index, 403, 'This chapter is not currently available.');

        $participant = ClassParticipant::firstOrCreate(
            ['live_class_id' => $class->id, 'user_id' => $student->id],
            ['joined_at' => now(), 'last_seen_at' => now()]
        );
        $participant->update(['last_seen_at' => now()]);

        // Past chapters taught in this group's earlier ended classes (for revision)
        $pastChapterIds = LiveClass::where('group_id', $class->group_id)
            ->whereIn('status', ['ended', 'completed'])
            ->where('id', '!=', $class->id)
            ->whereNotNull('chapter_id')
            ->orderByDesc('ended_at')
            ->pluck('chapter_id')
            ->unique()
            ->values();

        $pastChapters = Chapter::whereIn('id', $pastChapterIds)
            ->orderBy('level')->orderBy('sequence_number')
            ->get();

        // View still references $liveClass.
        $liveClass = $class;

        return view('student.classes.live', compact('liveClass', 'pastChapters'));
    }

    // AJAX: poll for current board state.
    // Real-time mirroring of the coach's board, arrows, and highlights to
    // every student in the group — polled at ~1.2s by the live page.
    public function pollBoard(LiveClass $class)
    {
        $this->assertStudentAssignedToClassGroup($class, Auth::id());

        // Always read the row fresh from DB so we never serve a stale
        // route-bound copy.
        $class->refresh();

        return response()->json([
            'move_index' => (int) $class->current_move_index,
            'board_state' => $class->board_state,
            'arrows' => $class->arrows,
            'highlights' => $class->highlights,
            'status' => $class->status,
            'updated_at' => optional($class->updated_at)->toIso8601String(),
        ])->withHeaders([
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }

    protected function assertStudentAssignedToClassGroup(LiveClass $class, int $studentId): void
    {
        $assigned = $class->group
            && $class->group->students()->where('users.id', $studentId)->exists();

        abort_unless($assigned, 403, 'You are not in this class group.');
    }

    private function sortClassesByChapterSequence(Collection $classes): Collection
    {
        return $classes->sort(function (LiveClass $left, LiveClass $right) {
            $leftAt = optional($left->scheduled_for)->getTimestamp() ?? optional($left->started_at)->getTimestamp() ?? 0;
            $rightAt = optional($right->scheduled_for)->getTimestamp() ?? optional($right->started_at)->getTimestamp() ?? 0;

            if ($leftAt !== $rightAt) {
                return $leftAt <=> $rightAt;
            }

            $leftSequence = (int) ($left->chapter->sequence_number ?? PHP_INT_MAX);
            $rightSequence = (int) ($right->chapter->sequence_number ?? PHP_INT_MAX);

            if ($leftSequence !== $rightSequence) {
                return $leftSequence <=> $rightSequence;
            }

            return $left->id <=> $right->id;
        });
    }
}
