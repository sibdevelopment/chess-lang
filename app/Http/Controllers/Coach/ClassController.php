<?php

namespace App\Http\Controllers\Coach;

use App\Events\BoardMoved;
use App\Events\ClassEnded;
use App\Http\Controllers\Controller;
use App\Models\Chapter;
use App\Models\Group;
use App\Models\Homework;
use App\Models\HomeworkSubmission;
use App\Models\LiveClass;
use App\Services\ArcherJourney\ArcherLmsEventBridge;
use App\Support\ClassScheduler;
use App\Support\GroupChapterProgressionService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ClassController extends Controller
{
    private const UPCOMING_CLASSES_LIMIT = 10;

    public function index(Request $request)
    {
        $coach = Auth::user();
        $range = $request->get('range', 'today'); // live | today | past | upcoming | cancelled

        $groups = Group::where('coach_id', $coach->id)
            ->where('is_active', true)
            ->with(['students', 'schedules', 'scheduleSlots', 'chapters'])
            ->orderBy('name')
            ->get();

        ClassScheduler::syncForGroups($groups);

        $live = LiveClass::with(['group', 'chapter'])
            ->where('coach_id', $coach->id)
            ->where('status', 'live')
            ->first();

        $scheduledClasses = LiveClass::with(['group', 'chapter'])
            ->where('coach_id', $coach->id)
            ->where('status', 'scheduled')
            ->where('scheduled_for', '>=', now()->utc()->subDay())
            ->orderBy('scheduled_for')
            ->get();

        $todayClasses = $this->todaysClasses($scheduledClasses);
        $upcomingClasses = $this->futureClasses($scheduledClasses);

        $history = LiveClass::with(['group', 'chapter', 'homework'])
            ->withCount('participants')
            ->where('coach_id', $coach->id)
            ->whereIn('status', ['ended', 'completed', 'cancelled'])
            ->orderByDesc(DB::raw(LiveClass::statusTimestampOrderExpression()))
            ->limit(50)
            ->get();

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

        return view('coach.classes.index', compact(
            'groups', 'live', 'todayClasses', 'upcomingClasses', 'pastClasses', 'cancelledClasses', 'range', 'counts'
        ));
    }

    public function create()
    {
        return redirect()
            ->route('coach.classes')
            ->with('success', 'Classes follow the group schedule. Start them from the Classes page when the slot opens.');
    }

    public function store()
    {
        return redirect()
            ->route('coach.classes')
            ->with('info', 'Classes are generated from the weekly group schedule. Start them from the Classes page when the slot opens.');
    }

    /**
     * Transition a scheduled class to live.
     */
    public function start(LiveClass $class)
    {
        $coachId = Auth::id();

        $started = DB::transaction(function () use ($class, $coachId) {
            /** @var LiveClass $lockedClass */
            $lockedClass = LiveClass::query()
                ->with(['group.chapters'])
                ->lockForUpdate()
                ->findOrFail($class->id);

            abort_unless($lockedClass->coach_id === $coachId, 403);
            abort_unless($lockedClass->status === 'scheduled', 409, 'This class is not in a scheduled state.');

            $group = $lockedClass->group;
            abort_unless($group && $group->coach_id === $coachId, 403);

            $now = now()->utc();
            $scheduledStart = $lockedClass->scheduledStart() ?? $lockedClass->scheduled_for;
            $scheduledEnd = $lockedClass->scheduledEnd() ?? $scheduledStart?->copy()->addMinutes(60);
            $startWindow = $scheduledStart->copy()->subMinutes(5)->utc();
            $deadline = $scheduledStart->copy()->addMinutes(30)->utc();

            if ($now->lt($startWindow)) {
                return ['state' => 'too-early'];
            }

            abort_if($now->gt($deadline), 409, 'This class missed its 30-minute start window.');

            $currentChapter = app(GroupChapterProgressionService::class)->currentChapter($group);
            abort_if(! $currentChapter, 422, 'This group has no current curriculum chapter.');

            if ((int) $lockedClass->chapter_id !== (int) $currentChapter->id || (int) $lockedClass->chapter_index !== (int) $group->unlocked_chapter_index) {
                $lockedClass->update([
                    'chapter_id' => $currentChapter->id,
                    'chapter_index' => (int) $group->unlocked_chapter_index,
                ]);
            }

            $lockedClass->update([
                'status' => 'live',
                'started_at' => $now,
                'actual_start_at' => $now,
                'scheduled_start_at' => $scheduledStart,
                'scheduled_end_at' => $scheduledEnd,
                'duration_minutes' => 60,
                'completion_type' => null,
                'chapter_completed' => false,
            ]);

            return ['state' => 'started', 'class' => $lockedClass];
        });

        if ($started['state'] === 'too-early') {
            return back()->with('error', 'You can start this class only 5 minutes before the scheduled time.');
        }

        return redirect()->route('coach.class.live', $started['class']->id);
    }

    public function live(LiveClass $class)
    {
        abort_unless($class->coach_id === Auth::id(), 403);

        $class->load(['group.students', 'chapter.puzzleAnswers']);

        // Past PGNs already taught in earlier completed classes of this group
        $pastChapterIds = LiveClass::where('group_id', $class->group_id)
            ->whereIn('status', ['ended', 'completed'])
            ->where('id', '!=', $class->id)
            ->whereNotNull('chapter_id')
            ->orderByDesc('ended_at')
            ->pluck('chapter_id')
            ->unique()
            ->values();

        $pastChapters = Chapter::with('puzzleAnswers')
            ->whereIn('id', $pastChapterIds)
            ->orderBy('level')->orderBy('sequence_number')
            ->get();

        // The view still uses $liveClass — keep that name.
        $liveClass = $class;

        return view('coach.classes.live', compact('liveClass', 'pastChapters'));
    }

    public function end(LiveClass $class)
    {
        $actor = Auth::user();
        abort_unless($this->canOperateClass($class, $actor->id), 403);

        $result = DB::transaction(function () use ($class) {
            /** @var LiveClass $lockedClass */
            $lockedClass = LiveClass::with(['chapter', 'group.students'])
                ->lockForUpdate()
                ->findOrFail($class->id);

            abort_unless($this->canOperateClass($lockedClass, Auth::id()), 403);

            if (in_array($lockedClass->status, ['ended', 'completed'], true)) {
                return ['state' => 'already-ended', 'class' => $lockedClass];
            }

            abort_unless($lockedClass->status === 'live', 409, 'Only a live class can be ended.');
            abort_unless($lockedClass->started_at !== null, 409, 'Only a started class can be ended.');

            $group = $lockedClass->group()
                ->with('chapters', 'students')
                ->lockForUpdate()
                ->first();

            abort_unless($group, 409, 'This class is not attached to a valid group.');

            $progress = app(GroupChapterProgressionService::class)->markCurrentChapterComplete($group, Auth::id(), $lockedClass);

            if (! in_array($progress['state'], ['completed', 'already-completed'], true)) {
                return ['state' => 'chapter-mismatch', 'class' => $lockedClass, 'group' => $group];
            }

            $actualEnd = now()->utc();
            $actualStart = $lockedClass->actualStart() ?? $lockedClass->started_at ?? $actualEnd;
            $durationMinutes = max(1, $actualStart->diffInMinutes($actualEnd));

            $lockedClass->update([
                'status' => 'completed',
                'ended_at' => $actualEnd,
                'actual_end_at' => $actualEnd,
                'duration_minutes' => $durationMinutes,
                'completion_type' => 'manual',
                'chapter_completed' => true,
            ]);

            $homework = Homework::firstOrCreate(
                ['source_class_id' => $lockedClass->id],
                [
                    'title' => 'Homework: '.$lockedClass->chapter->name,
                    'chapter_id' => $lockedClass->chapter_id,
                    'group_id' => $lockedClass->group_id,
                    'assigned_by' => Auth::id(),
                    'auto_assigned' => true,
                    'due_date' => now()->addDays(7),
                ]
            );

            foreach ($group->students as $student) {
                HomeworkSubmission::firstOrCreate(
                    ['homework_id' => $homework->id, 'user_id' => $student->id],
                    ['status' => 'pending']
                );
            }

            return ['state' => 'ended', 'class' => $lockedClass, 'group' => $group];
        });

        if ($result['state'] === 'chapter-mismatch') {
            return back()->with('error', 'This class is not on the current curriculum chapter, so it was not ended.');
        }

        if ($result['state'] === 'already-ended') {
            return redirect()
                ->route($this->homeworkRouteForActor())
                ->with('success', 'Class was already ended. Curriculum was not advanced again.');
        }

        /** @var Group $group */
        $group = $result['group']->fresh()->load(['chapters', 'schedules']);
        app(ArcherLmsEventBridge::class)->classCompleted($result['class']->fresh());
        ClassScheduler::syncForGroup($group);

        try {
            broadcast(new ClassEnded($result['class']->room_id));
        } catch (\Throwable $e) {
        }

        return redirect()
            ->route($this->homeworkRouteForActor())
            ->with('success', 'Class ended. Homework auto-assigned to group.');
    }

    // AJAX: update board state during a live class.
    // The DB row is the source of truth — students poll /poll every ~1.2s and
    // mirror the FEN + arrows + highlights from this row in real time.
    public function updateBoard(Request $request, LiveClass $class)
    {
        abort_unless($class->coach_id === Auth::id(), 403);

        $data = $request->validate([
            'move_index' => 'required|integer',
            'board_state' => 'required|string',
            'move' => 'nullable|string',
            'arrows' => 'nullable|string',
            'highlights' => 'nullable|string',
        ]);

        // Persist coach state. Students see this within one poll cycle.
        $class->forceFill([
            'current_move_index' => $data['move_index'],
            'board_state' => $data['board_state'],
            'arrows' => $data['arrows'] ?? null,
            'highlights' => $data['highlights'] ?? null,
        ])->save();

        // Push to anyone with a websocket connected (Echo is optional).
        // Polling already covers the no-websocket case; broadcast failures
        // must not fail the request.
        try {
            broadcast(new BoardMoved(
                $class->room_id,
                $data['move_index'],
                $data['board_state'],
                $data['move'] ?? '',
                $data['arrows'] ?? '',
                $data['highlights'] ?? ''
            ))->toOthers();
        } catch (\Throwable $e) {
        }

        return response()->json([
            'ok' => true,
            'move_index' => $class->current_move_index,
            'arrows' => $class->arrows,
            'highlights' => $class->highlights,
        ]);
    }

    public function getChaptersForGroup(Group $group)
    {
        $coach = Auth::user();
        abort_unless($group->coach_id === $coach->id, 403);

        $pointer = (int) $group->unlocked_chapter_index;

        $chapters = $group->chapters()
            ->orderByPivot('sequence_order')
            ->get()
            ->filter(fn ($ch) => (int) $ch->pivot->sequence_order <= $pointer)
            ->map(fn ($ch) => [
                'id' => $ch->id,
                'name' => $ch->name,
                'index' => $ch->pivot->sequence_order,
                'locked' => false,
                'done' => (int) $ch->pivot->sequence_order < $pointer,
                'current' => (int) $ch->pivot->sequence_order === $pointer,
            ]);

        return response()->json($chapters);
    }

    private function todaysClasses(Collection $classes): Collection
    {
        return $this->sortClassesByChapterSequence($classes->filter(function (LiveClass $class) {
            $local = $class->scheduled_for?->copy()->timezone($class->displayTimezone());

            return $local && $local->isToday();
        }))->values();
    }

    private function futureClasses(Collection $classes): Collection
    {
        return $this->sortClassesByChapterSequence($classes->filter(function (LiveClass $class) {
            $local = $class->scheduled_for?->copy()->timezone($class->displayTimezone());

            return $local && $local->greaterThan(Carbon::now($class->displayTimezone())->endOfDay());
        }))->values()->take(self::UPCOMING_CLASSES_LIMIT)->values();
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

    private function canOperateClass(LiveClass $class, int $userId): bool
    {
        $user = Auth::user();

        if (! $user || $user->id !== $userId) {
            return false;
        }

        return $class->coach_id === $userId || $user->isAdmin() || $user->isStaff();
    }

    private function homeworkRouteForActor(): string
    {
        $user = Auth::user();

        if ($user?->isAdmin()) {
            return 'admin.homework';
        }

        if ($user?->isStaff()) {
            return 'staff.homework';
        }

        return 'coach.homework';
    }
}
