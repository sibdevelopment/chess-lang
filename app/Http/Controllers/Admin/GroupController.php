<?php namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{Chapter, Group, GroupSchedule, User};
use App\Support\ClassScheduler;
use App\Support\GroupChapterProgressionService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class GroupController extends Controller
{
    protected const GROUPS_PER_PAGE = 50;

    protected function orderedLevelChapters(string $level): Collection
    {
        return Chapter::where('level', $level)
            ->orderedForLevel()
            ->get();
    }

    public function index(Request $request)
    {
        $search = $request->get('search');
        $status = in_array($request->get('status', 'active'), ['active', 'inactive'], true)
            ? $request->get('status', 'active')
            : 'active';

        $groups = Group::with(['coach', 'schedules', 'chapters'])
            ->withCount('chapters')
            ->withCount([
                'students as active_students_count' => fn ($query) => $query->where('users.is_active', true),
            ])
            ->with('students:id,name')
            ->when($status === 'active',   fn($q) => $q->where('is_active', true))
            ->when($status === 'inactive', fn($q) => $q->where('is_active', false))
            ->when($search, function ($query) use ($search) {
                $query->where(function ($nested) use ($search) {
                    $nested->where('name', 'like', "%{$search}%")
                        ->orWhereHas('coach', fn ($coach) => $coach->where('name', 'like', "%{$search}%"));
                });
            })
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->paginate(self::GROUPS_PER_PAGE)->withQueryString();

        ClassScheduler::syncForGroups($groups->getCollection());
        $groups->getCollection()->load([
            'liveClasses' => fn ($query) => $query
                ->where('status', 'scheduled')
                ->orderBy('scheduled_for')
                ->with('chapter:id,name,level,sequence_number'),
        ]);

        $counts = [
            'active'   => Group::where('is_active', true)->count(),
            'inactive' => Group::where('is_active', false)->count(),
        ];

        $coaches = User::coaches()->active()->orderBy('name')->get(['id', 'name']);
        $assignableStudents = User::students()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'username', 'country', 'is_active']);
        $curriculumLevels = Group::LEVELS;
        $curriculumChapters = Chapter::query()
            ->orderedForLevel()
            ->get(['id', 'name', 'level', 'sequence_number']);

        return view('admin.groups.index', compact('groups','search','status','counts', 'coaches', 'assignableStudents', 'curriculumLevels', 'curriculumChapters'));
    }

    public function create()
    {
        return redirect()->route(auth()->user()?->isStaff() ? 'staff.groups' : 'admin.groups');
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->groupRules());
        $scheduleRows = $this->normalizeSchedules($data['schedules']);
        $this->validateScheduleOverlaps($scheduleRows);

        $startingChapter = $this->resolveStartingChapter($data['level'], $data['first_chapter_id'] ?? null);
        $level = $startingChapter->level;

        $group = DB::transaction(function () use ($data, $scheduleRows, $level, $startingChapter) {
            $defaults = $this->groupDefaults($data);

            $group = Group::create([
                'name'        => trim($data['name']),
                'level'       => $level,
                'timezone'    => $defaults['timezone'],
                'start_date'  => $defaults['start_date'],
                'end_date'    => $defaults['end_date'],
                'is_active'   => $defaults['is_active'],
                'coach_id'    => $data['coach_id'],
                'created_by'  => auth()->id(),
            ]);

            if (!empty($data['students'])) {
                $group->students()->sync(array_values(array_unique($data['students'])));
            }

            app(GroupChapterProgressionService::class)->assignStartingPoint($group, $level, $startingChapter->id);

            $group->schedules()->createMany($scheduleRows);

            return $group;
        });

        $group->load(['chapters', 'schedules']);
        ClassScheduler::syncForGroup($group);

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'message' => "Group \"{$group->name}\" created successfully.",
            ]);
        }

        return redirect()->route(auth()->user()->isStaff() ? 'staff.groups' : 'admin.groups')
            ->with('success', "Group \"{$group->name}\" created successfully.");
    }

    public function show(Group $group)
    {
        return redirect()->route((request()->user()?->isStaff() ? 'staff' : 'admin').'.groups.edit', $group);
    }

    public function edit(Group $group)
    {
        return redirect()->route(auth()->user()?->isStaff() ? 'staff.groups' : 'admin.groups');
    }

    public function update(Request $request, Group $group)
    {
        $data = $request->validate($this->groupRules(isUpdate: true));
        $scheduleRows = $this->normalizeSchedules($data['schedules']);
        $this->validateScheduleOverlaps($scheduleRows);

        DB::transaction(function () use ($group, $data, $scheduleRows) {
            $defaults = $this->groupDefaults($data, $group);
            $wasActive = (bool) $group->is_active;
            $levelChanged = $group->level !== $data['level'];

            $group->update([
                'name'       => trim($data['name']),
                'level'      => $group->level,
                'timezone'   => $defaults['timezone'],
                'start_date' => $defaults['start_date'],
                'end_date'   => $defaults['end_date'],
                'is_active'  => $defaults['is_active'],
                'coach_id'   => $data['coach_id'],
            ]);

            if (array_key_exists('students', $data)) {
                $group->students()->sync(array_values(array_unique($data['students'] ?? [])));
            }

            if ($levelChanged && !$group->liveClasses()->whereIn('status', ['live', 'ended'])->exists()) {
                $this->syncStartingChapter($group, $data['level'], $this->resolveStartingChapter($data['level'])->id);
            }

            $this->syncSchedules($group, $scheduleRows);

            if ($wasActive && !$group->is_active) {
                $group->students()->detach();
            }
        });

        $group->load(['chapters', 'schedules']);
        ClassScheduler::syncForGroup($group);

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'message' => 'Group updated.']);
        }

        return back()->with('success', 'Group updated.');
    }

    protected function groupRules(bool $isUpdate = false): array
    {
        return [
            'name'       => 'required|string|max:100',
            'level'      => 'required|in:' . implode(',', Group::LEVELS),
            'timezone'   => 'nullable|timezone:all',
            'start_date' => 'nullable|date',
            'end_date'   => 'nullable|date|after_or_equal:start_date',
            'is_active'  => 'sometimes|boolean',
            'coach_id'   => [
                'required',
                Rule::exists('users', 'id')->where(fn ($q) => $q->where('role', 'coach')->where('is_active', true)),
            ],
            'students'   => $isUpdate ? 'sometimes|nullable|array' : 'nullable|array',
            'students.*' => [
                Rule::exists('users', 'id')->where(fn ($q) => $q->where('role', 'student')->where('is_active', true)),
            ],
            'first_chapter_id' => 'nullable|exists:chapters,id',
            'schedules' => 'required|array|min:1',
            'schedules.*.day' => 'required|integer|between:0,6',
            'schedules.*.time' => 'required|date_format:H:i',
            'schedules.*.duration' => 'nullable|integer|min:1|max:1440',
        ];
    }

    public function addStudent(Request $request, Group $group)
    {
        $request->validate([
            'student_ids' => 'nullable|array|min:1',
            'student_id' => [
                'nullable',
                Rule::exists('users', 'id')->where(fn ($q) => $q->where('role', 'student')->where('is_active', true)),
            ],
            'student_ids.*' => [
                Rule::exists('users', 'id')->where(fn ($q) => $q->where('role', 'student')->where('is_active', true)),
            ],
        ]);

        if (!$group->is_active) {
            return back()->with('error', 'Activate the group before assigning students.');
        }

        $studentIds = collect($request->input('student_ids', []))
            ->push($request->input('student_id'))
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if (empty($studentIds)) {
            throw ValidationException::withMessages([
                'student_ids' => 'Please choose at least one student to assign.',
            ]);
        }

        $group->students()->syncWithoutDetaching($studentIds);

        return back()->with('success', count($studentIds) === 1 ? 'Student added to group.' : 'Students added to group.');
    }

    public function removeStudent(Request $request, Group $group)
    {
        $request->validate([
            'student_ids' => 'nullable|array|min:1',
            'student_id' => 'nullable',
        ]);

        $studentIds = collect($request->input('student_ids', []))
            ->push($request->input('student_id'))
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if (empty($studentIds)) {
            throw ValidationException::withMessages([
                'student_ids' => 'Please choose at least one student to remove.',
            ]);
        }

        $group->students()->detach($studentIds);
        return back()->with('success', count($studentIds) === 1 ? 'Student removed from group.' : 'Students removed from group.');
    }

    public function assignStudents(Request $request, Group $group)
    {
        $data = $request->validate([
            'student_ids' => 'nullable|array',
            'student_ids.*' => [
                'integer',
                Rule::exists('users', 'id')->where(fn ($q) => $q->where('role', 'student')),
            ],
        ]);

        $studentIds = collect($data['student_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if (!$group->is_active && !empty($studentIds)) {
            throw ValidationException::withMessages([
                'student_ids' => 'Activate the group before assigning students.',
            ]);
        }

        $group->students()->sync($studentIds);

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'message' => 'Students updated.']);
        }

        return back()->with('success', 'Students updated.');
    }

    public function assignChapters(Request $request, Group $group)
    {
        abort_if($group->liveClasses()->whereIn('status', ['live', 'ended'])->exists(), 422, 'Cannot rewrite the curriculum after classes have started.');

        $data = $request->validate([
            'starting_level' => 'required|in:' . implode(',', Group::LEVELS),
            'starting_chapter_id' => 'required|exists:chapters,id',
        ]);

        app(GroupChapterProgressionService::class)->assignStartingPoint(
            $group,
            $data['starting_level'],
            (int) $data['starting_chapter_id']
        );
        $group->load(['chapters', 'schedules']);
        ClassScheduler::syncForGroup($group);

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'message' => 'Curriculum starting point updated.']);
        }

        return back()->with('success', 'Curriculum starting point updated.');
    }

    public function toggleStatus(Group $group)
    {
        DB::transaction(function () use ($group) {
            $group->refresh();
            $newState = !$group->is_active;

            $group->update(['is_active' => $newState]);

            if (!$newState) {
                $group->students()->detach();
            }
        });

        ClassScheduler::syncForGroup($group->fresh()->load(['chapters', 'schedules']));

        if (request()->expectsJson()) {
            return response()->json([
                'ok' => true,
                'message' => $group->fresh()->is_active ? 'Group activated.' : 'Group inactivated and student assignments cleared.',
            ]);
        }

        return back()->with('success', $group->fresh()->is_active ? 'Group activated.' : 'Group inactivated and student assignments cleared.');
    }

    public function destroy(Group $group)
    {
        if ($group->liveClasses()->exists()) {
            return back()->with('error', 'This group cannot be deleted because classes already exist.');
        }

        if ($group->homework()->exists()) {
            return back()->with('error', 'This group cannot be deleted because homework already exists.');
        }

        DB::transaction(function () use ($group) {
            $group->students()->detach();
            $group->chapters()->detach();
            $group->scheduleSlots()->delete();
            $group->delete();
        });

        return redirect()->route(request()->user()?->isStaff() ? 'staff.groups' : 'admin.groups')
            ->with('success', 'Group deleted.');
    }

    protected function normalizeSchedules(array $schedules): array
    {
        $rows = collect($schedules)
            ->filter(fn ($schedule) => !empty($schedule['time']))
            ->map(function ($schedule) {
                $row = [
                    'day_of_week' => (int) $schedule['day'],
                    'start_time'  => $schedule['time'],
                    'duration_minutes' => (int) ($schedule['duration'] ?? Group::DEFAULT_DURATION_MINUTES),
                    'is_active'   => true,
                ];

                if (!Schema::hasColumn('group_schedules', 'duration_minutes')) {
                    unset($row['duration_minutes']);
                }

                if (!Schema::hasColumn('group_schedules', 'is_active')) {
                    unset($row['is_active']);
                }

                return $row;
            })
            ->values();

        $duplicate = $rows
            ->map(fn ($row) => $row['day_of_week'] . '|' . $row['start_time'])
            ->duplicates()
            ->isNotEmpty();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'schedules' => 'Duplicate weekly day/time slots are not allowed for the same group.',
            ]);
        }

        return $rows->all();
    }

    protected function validateScheduleOverlaps(array $scheduleRows): void
    {
        $grouped = collect($scheduleRows)->groupBy('day_of_week');

        foreach ($grouped as $rows) {
            $sorted = $rows->sortBy('start_time')->values();
            for ($i = 0; $i < $sorted->count() - 1; $i++) {
                $current = $sorted[$i];
                $next = $sorted[$i + 1];

                $currentStart = Carbon::createFromFormat('H:i', substr((string) $current['start_time'], 0, 5), 'UTC');
                $currentEnd = $currentStart->copy()->addMinutes((int) ($current['duration_minutes'] ?? Group::DEFAULT_DURATION_MINUTES));
                $nextStart = Carbon::createFromFormat('H:i', substr((string) $next['start_time'], 0, 5), 'UTC');

                if ($nextStart->lt($currentEnd)) {
                    throw ValidationException::withMessages([
                        'schedules' => 'Weekly schedule slots may not overlap within the same group.',
                    ]);
                }
            }
        }
    }

    protected function groupDefaults(array $data, ?Group $group = null): array
    {
        return [
            'timezone' => filled($data['timezone'] ?? null) ? $data['timezone'] : ($group?->timezone ?: Group::DEFAULT_TIMEZONE),
            'start_date' => filled($data['start_date'] ?? null) ? $data['start_date'] : null,
            'end_date' => filled($data['end_date'] ?? null) ? $data['end_date'] : null,
            'is_active' => array_key_exists('is_active', $data)
                ? (bool) $data['is_active']
                : ($group?->is_active ?? true),
        ];
    }

    protected function syncSchedules(Group $group, array $scheduleRows): void
    {
        $existing = GroupSchedule::where('group_id', $group->id)
            ->orderBy('id')
            ->get();

        $usedIds = [];

        foreach ($scheduleRows as $row) {
            $match = $existing
                ->first(function (GroupSchedule $schedule) use ($row, $usedIds) {
                    return !in_array($schedule->id, $usedIds, true)
                        && (int) $schedule->day_of_week === (int) $row['day_of_week']
                        && substr((string) $schedule->start_time, 0, 5) === substr((string) $row['start_time'], 0, 5);
                });

            if ($match) {
                $payload = [
                    'day_of_week' => $row['day_of_week'],
                    'start_time'  => $row['start_time'],
                ];
                if (Schema::hasColumn('group_schedules', 'duration_minutes')) {
                    $payload['duration_minutes'] = $row['duration_minutes'] ?? Group::DEFAULT_DURATION_MINUTES;
                }
                if (Schema::hasColumn('group_schedules', 'is_active')) {
                    $payload['is_active'] = true;
                }
                $match->update($payload);
                $usedIds[] = $match->id;
                continue;
            }

            $created = GroupSchedule::create(['group_id' => $group->id] + $row);
            $usedIds[] = $created->id;
        }

        foreach ($existing as $schedule) {
            if (in_array($schedule->id, $usedIds, true)) {
                continue;
            }

            if ($schedule->liveClasses()->exists()) {
                if (Schema::hasColumn('group_schedules', 'is_active')) {
                    $schedule->update(['is_active' => false]);
                }
            } else {
                $schedule->delete();
            }
        }
    }

    protected function syncStartingChapter(Group $group, string $level, int $firstChapterId): void
    {
        $firstChapter = Chapter::findOrFail($firstChapterId);

        $currentFirst = $group->chapters()->orderByPivot('sequence_order')->first();
        if ($currentFirst && (int) $currentFirst->id === $firstChapterId) {
            return;
        }

        if ($group->liveClasses()->whereIn('status', ['live', 'ended'])->exists()) {
            throw ValidationException::withMessages([
                'first_chapter_id' => 'Starting chapter cannot be changed after classes have started.',
            ]);
        }

        app(GroupChapterProgressionService::class)->assignStartingPoint($group, $level, $firstChapterId);
    }

    protected function resolveStartingChapter(string $level, ?int $firstChapterId = null): Chapter
    {
        if ($firstChapterId) {
            $chapter = Chapter::findOrFail($firstChapterId);
            if ($chapter->level !== $level) {
                throw ValidationException::withMessages([
                    'first_chapter_id' => 'Selected chapter does not match the chosen level.',
                ]);
            }

            return $chapter;
        }

        $chapter = Chapter::where('level', $level)
            ->orderedForLevel()
            ->first();

        if (!$chapter) {
            throw ValidationException::withMessages([
                'level' => 'No curriculum chapter exists yet for the selected level.',
            ]);
        }

        return $chapter;
    }
}
