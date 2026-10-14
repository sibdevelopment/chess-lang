<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Chapter;
use App\Models\ClassQuestion;
use App\Models\ClassQuestionAttempt;
use App\Models\Group;
use App\Models\GroupSchedule;
use App\Models\LiveClass;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class ClassesController extends Controller
{
    private const UPCOMING_CLASSES_PER_PAGE = 10;

    public function index(Request $request)
    {
        $filters = [
            'tab'        => 'today',
            'search'     => trim((string) $request->string('search')),
            'coach_id'   => $request->integer('coach_id') ?: null,
            'group_id'   => $request->integer('group_id') ?: null,
            'student_id' => $request->integer('student_id') ?: null,
            'date_from'  => $request->input('date_from') ?: null,
            'date_to'    => $request->input('date_to') ?: null,
        ];

        $sharedQuery = $this->makeSharedFilteredQuery($filters);
        $counts = $this->buildTabCounts(clone $sharedQuery);

        $sectionLoader = function (string $scope, ?string $pageName = null) use ($sharedQuery) {
            $query = $this->applyOrdering($this->applyTabScope(clone $sharedQuery, $scope), $scope)
                ->with([
                    'coach:' . implode(',', $this->coachIndexColumns()),
                    'group' => fn ($builder) => $builder->select('id', 'name', 'level')->withCount('students'),
                    'chapter:id,name,level,sequence_number,move_count,pgn',
                    'schedule:' . implode(',', $this->scheduleListColumns()),
                ])
                ->withCount('participants');

            if ($pageName) {
                $perPage = $scope === 'upcoming'
                    ? self::UPCOMING_CLASSES_PER_PAGE
                    : 12;

                return $query->paginate($perPage, ['*'], $pageName)->withQueryString();
            }

            return $query->get();
        };

        $liveClasses = $sectionLoader('live');
        $todayClasses = $sectionLoader('today', 'today_page');
        $upcomingClasses = $sectionLoader('upcoming', 'upcoming_page');
        $pastClasses = $sectionLoader('past', 'past_page');
        $cancelledClasses = $sectionLoader('cancelled', 'cancelled_page');

        $this->attachReplacementCoachOptions($todayClasses);
        $this->attachReplacementCoachOptions($upcomingClasses);

        $summary = $this->buildSummary(clone $this->applyTabScope(clone $sharedQuery, 'past'));

        $viewData = [
            'filters' => $filters,
            'counts' => $counts,
            'summary' => $summary,
            'liveClasses' => $liveClasses,
            'todayClasses' => $todayClasses,
            'upcomingClasses' => $upcomingClasses,
            'pastClasses' => $pastClasses,
            'cancelledClasses' => $cancelledClasses,
            'coaches' => User::coaches()->orderBy('name')->get(['id', 'name']),
            'groups' => Group::orderBy('name')->get(['id', 'name']),
            // Filters should only offer students who can currently take part in LMS
            // activity. Past-class rows still keep their historical participants.
            'students' => User::students()->active()->orderBy('name')->get(['id', 'name', 'username']),
        ];

        return view('admin.classes.index', $viewData);
    }

    public function changeCoach(Request $request, LiveClass $class)
    {
        abort_unless($class->status === 'scheduled', 403, 'Only scheduled classes can be reassigned.');

        $data = $request->validate([
            'coach_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where(fn ($query) => $query
                    ->where('role', 'coach')
                    ->where('is_active', true)),
            ],
        ]);

        $availableCoachIds = $this->availableReplacementCoachesForClass($class)->pluck('id')->map(fn ($id) => (int) $id)->all();
        if (! in_array((int) $data['coach_id'], $availableCoachIds, true)) {
            return back()->with('error', 'Selected coach is not available for this class time.');
        }

        $coach = User::query()->select('id', 'name')->findOrFail((int) $data['coach_id']);
        $class->update(['coach_id' => $coach->id]);

        return back()->with('success', "Class reassigned to {$coach->name}.");
    }

    public function show(LiveClass $class)
    {
        $class->load([
            'coach:' . implode(',', $this->coachShowColumns()),
            'schedule:' . implode(',', $this->scheduleDetailColumns()),
            'chapter.uploader:id,name',
            'group' => fn ($query) => $query
                ->select('id', 'name', 'level', 'coach_id')
                ->with(['students:id,name,username'])
                ->withCount('students'),
            'participants' => fn ($query) => $query->select('users.id', 'users.name', 'users.username')
                ->withPivot('joined_at', 'left_at', 'last_seen_at'),
            'homework' => fn ($query) => $query
                ->with(['chapter:id,name'])
                ->withCount('submissions')
                ->latest('created_at'),
            'questions' => fn ($query) => $query
                ->with(['attempts.user:id,name,username'])
                ->oldest('created_at')
                ->oldest('id'),
        ]);

        $studentRoster = $class->group?->students ?? collect();
        $participants = $class->participants->keyBy('id');
        $attendedStudents = $studentRoster
            ->filter(fn ($student) => $participants->has($student->id))
            ->values();
        $absentStudents = $studentRoster
            ->reject(fn ($student) => $participants->has($student->id))
            ->values();

        $leaderboard = $this->buildLeaderboard($class, $studentRoster, $participants);
        $puzzleAudit = $this->buildPuzzleAudit($class, $studentRoster, $participants);
        $detailsData = compact('class', 'attendedStudents', 'absentStudents', 'leaderboard', 'puzzleAudit');

        if ($class->status === 'cancelled') {
            $detailsData['assignedStudents'] = $studentRoster;
        }

        if ($class->status === 'cancelled' && !$class->cancelled_at && $class->scheduled_for) {
            $class->cancelled_at = $class->scheduled_for->copy()->addMinutes(10);
        }

        if (request()->ajax()) {
            return response()->json([
                'html' => view('admin.classes._details', $detailsData)->render(),
                'title' => $class->name,
            ]);
        }

        return view('admin.classes.show', $detailsData);
    }

    protected function resolveTab(Request $request): string
    {
        $tab = $request->input('tab', $request->input('range', 'today'));

        return match ($tab) {
            'future' => 'upcoming',
            'all' => 'today',
            default => in_array($tab, ['live', 'today', 'upcoming', 'past', 'cancelled'], true) ? $tab : 'today',
        };
    }

    protected function makeSharedFilteredQuery(array $filters)
    {
        $query = LiveClass::query();

        $query->when($filters['search'], function ($builder, $search) {
            $builder->where('name', 'like', "%{$search}%");
        });

        $query->when($filters['coach_id'], function ($builder, $coachId) {
            $builder->where('coach_id', $coachId);
        });

        $query->when($filters['group_id'], function ($builder, $groupId) {
            $builder->where('group_id', $groupId);
        });

        $query->when($filters['student_id'], function ($builder, $studentId) {
            $builder->whereHas('group.students', fn ($studentQuery) => $studentQuery->where('users.id', $studentId));
        });

        [$fromUtc, $toUtc] = $this->resolveDateRange($filters['date_from'], $filters['date_to']);
        if ($fromUtc && $toUtc) {
            $query->whereBetween(
                DB::raw(LiveClass::statusTimestampOrderExpression()),
                [$fromUtc->format('Y-m-d H:i:s'), $toUtc->format('Y-m-d H:i:s')]
            );
        }

        return $query;
    }

    protected function applyTabScope($query, string $tab)
    {
        $startOfDayUtc = Carbon::now('Asia/Kolkata')->startOfDay()->utc();
        $endOfDayUtc = Carbon::now('Asia/Kolkata')->endOfDay()->utc();

        return match ($tab) {
            'live' => $query->where('status', 'live'),
            'today' => $query->where('status', 'scheduled')
                ->whereBetween('scheduled_for', [$startOfDayUtc, $endOfDayUtc]),
            'upcoming' => $query->where('status', 'scheduled')
                ->where('scheduled_for', '>', $endOfDayUtc),
            'past' => $query->whereIn('status', ['ended', 'completed']),
            'cancelled' => $query->where('status', 'cancelled'),
            default => $query,
        };
    }

    protected function applyOrdering($query, string $tab)
    {
        $chapterSequenceOrder = Chapter::query()
            ->selectRaw('COALESCE(sequence_number, 2147483647)')
            ->whereColumn('chapters.id', 'live_classes.chapter_id')
            ->limit(1);

        return match ($tab) {
            'live' => $query->orderByDesc('started_at')->orderBy($chapterSequenceOrder)->orderByDesc('id'),
            'today', 'upcoming' => $query->orderBy('scheduled_for')->orderBy($chapterSequenceOrder)->orderBy('id'),
            'cancelled' => LiveClass::supportsCancelledAt()
                ? $query->orderByDesc('cancelled_at')->orderBy($chapterSequenceOrder)->orderByDesc('scheduled_for')
                : $query->orderByDesc('scheduled_for')->orderBy($chapterSequenceOrder)->orderByDesc('id'),
            default => $query->orderByDesc(DB::raw(LiveClass::statusTimestampOrderExpression()))->orderBy($chapterSequenceOrder),
        };
    }

    protected function buildTabCounts($sharedQuery): array
    {
        return [
            'live' => (clone $this->applyTabScope(clone $sharedQuery, 'live'))->count(),
            'today' => (clone $this->applyTabScope(clone $sharedQuery, 'today'))->count(),
            'upcoming' => (clone $this->applyTabScope(clone $sharedQuery, 'upcoming'))->count(),
            'past' => (clone $this->applyTabScope(clone $sharedQuery, 'past'))->count(),
            'cancelled' => (clone $this->applyTabScope(clone $sharedQuery, 'cancelled'))->count(),
        ];
    }

    protected function buildSummary($query): array
    {
        $columns = [
            'id',
            'status',
            'scheduled_for',
            'started_at',
            'ended_at',
            'group_schedule_id',
        ];

        if (LiveClass::supportsCancelledAt()) {
            $columns[] = 'cancelled_at';
        }

        $classes = $query->with(['schedule:' . implode(',', $this->scheduleSummaryColumns())])->get($columns);

        $totalMinutes = $classes->sum(fn (LiveClass $class) => $this->classDurationMinutes($class));

        return [
            'total_classes' => $classes->count(),
            'total_duration_hours' => number_format($totalMinutes / 60, 1),
        ];
    }

    protected function classDurationMinutes(LiveClass $class): int
    {
        $planned = (int) ($class->duration_minutes ?: Group::DEFAULT_DURATION_MINUTES);

        if ($class->status === 'cancelled') {
            return $planned;
        }

        if ($class->actualStart() || $class->started_at) {
            if ($class->duration_minutes && in_array($class->status, ['ended', 'completed'], true)) {
                return (int) $class->duration_minutes;
            }

            $start = $class->actualStart() ?? $class->started_at;
            $end = $class->actualEnd() ?? $class->ended_at ?? now()->utc();
            return max(1, $start->diffInMinutes($end));
        }

        return $planned;
    }

    protected function attachReplacementCoachOptions($classes): void
    {
        $items = $classes instanceof \Illuminate\Contracts\Pagination\Paginator
            || $classes instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator
            ? $classes->getCollection()
            : collect($classes);

        $items->each(function (LiveClass $class) {
            $class->replacementCoachOptions = $this->availableReplacementCoachesForClass($class);
        });
    }

    protected function availableReplacementCoachesForClass(LiveClass $class): Collection
    {
        $class->loadMissing(['schedule:id,group_id,day_of_week,start_time,is_active', 'group:id,name,coach_id,is_active']);

        $start = $class->scheduledStart();
        $end = $class->scheduledEnd()
            ?? ($start ? $start->copy()->addMinutes((int) ($class->duration_minutes ?: Group::DEFAULT_DURATION_MINUTES)) : null);

        if (! $start || ! $end) {
            return collect();
        }

        $conflictingCoachIds = LiveClass::query()
            ->whereKeyNot($class->id)
            ->whereIn('status', ['scheduled', 'live'])
            ->whereRaw('COALESCE(scheduled_start_at, scheduled_for) < ?', [$end->format('Y-m-d H:i:s')])
            ->whereRaw(
                'COALESCE(scheduled_end_at, DATE_ADD(COALESCE(scheduled_start_at, scheduled_for), INTERVAL COALESCE(duration_minutes, '.(int) Group::DEFAULT_DURATION_MINUTES.') MINUTE)) > ?',
                [$start->format('Y-m-d H:i:s')]
            )
            ->pluck('coach_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->all();

        $slotDay = $class->schedule?->day_of_week ?? $start->copy()->timezone(Group::DEFAULT_TIMEZONE)->dayOfWeek;
        $slotTime = $class->schedule?->start_time
            ? substr((string) $class->schedule->start_time, 0, 8)
            : $start->copy()->timezone(Group::DEFAULT_TIMEZONE)->format('H:i:s');

        $recurringConflictCoachIds = GroupSchedule::query()
            ->join('groups', 'groups.id', '=', 'group_schedules.group_id')
            ->where('groups.is_active', true)
            ->whereNotNull('groups.coach_id')
            ->where('groups.id', '!=', $class->group_id)
            ->where('group_schedules.day_of_week', (int) $slotDay)
            ->where('group_schedules.start_time', $slotTime)
            ->when(
                \Illuminate\Support\Facades\Schema::hasColumn('group_schedules', 'is_active'),
                fn ($query) => $query->where('group_schedules.is_active', true)
            )
            ->pluck('groups.coach_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $blockedCoachIds = array_values(array_unique(array_merge($conflictingCoachIds, $recurringConflictCoachIds)));

        return User::coaches()
            ->where('is_active', true)
            ->whereKeyNot($class->coach_id)
            ->whereNotIn('id', $blockedCoachIds)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    protected function resolveDateRange(?string $dateFrom, ?string $dateTo): array
    {
        if (!$dateFrom && !$dateTo) {
            return [null, null];
        }

        $from = $dateFrom
            ? Carbon::parse($dateFrom, 'Asia/Kolkata')->startOfDay()->utc()
            : Carbon::createFromTimestamp(0, 'UTC');

        $to = $dateTo
            ? Carbon::parse($dateTo, 'Asia/Kolkata')->endOfDay()->utc()
            : Carbon::now('Asia/Kolkata')->addYears(10)->endOfDay()->utc();

        return [$from, $to];
    }

    protected function buildLeaderboard(LiveClass $class, Collection $studentRoster, Collection $participants): Collection
    {
        $questionIds = ClassQuestion::where('live_class_id', $class->id)->pluck('id');
        $stats = collect();

        if ($questionIds->isNotEmpty()) {
            $stats = ClassQuestionAttempt::whereIn('class_question_id', $questionIds)
                ->select(
                    'user_id',
                    DB::raw('SUM(correct_count) AS total_points'),
                    DB::raw('SUM(wrong_count) AS total_wrong'),
                    DB::raw('COUNT(*) AS attempts')
                )
                ->groupBy('user_id')
                ->get()
                ->keyBy('user_id');
        }

        return $studentRoster->map(function ($student) use ($stats, $participants) {
            $row = $stats->get($student->id);
            $participant = $participants->get($student->id);
            $correct = (int) ($row->total_points ?? 0);
            $wrong = (int) ($row->total_wrong ?? 0);
            $attempts = max(0, $correct + $wrong);
            $joinedAt = $participant?->pivot?->joined_at;
            $leftAt = $participant?->pivot?->left_at;
            $joinedAt = $joinedAt ? Carbon::parse($joinedAt) : null;
            $leftAt = $leftAt ? Carbon::parse($leftAt) : null;
            $timeMinutes = $joinedAt
                ? max(1, $joinedAt->diffInMinutes($leftAt ?? now()))
                : null;

            return [
                'student' => $student,
                'score' => $correct,
                'accuracy' => $attempts > 0 ? round(($correct / $attempts) * 100) . '%' : '—',
                'time' => $timeMinutes ? "{$timeMinutes}m" : '—',
            ];
        })->sort(function (array $a, array $b) {
            if ($b['score'] !== $a['score']) {
                return $b['score'] <=> $a['score'];
            }

            return strcasecmp($a['student']->name, $b['student']->name);
        })->values();
    }

    protected function buildPuzzleAudit(LiveClass $class, Collection $studentRoster, Collection $participants): Collection
    {
        $questions = $class->questions ?? collect();
        $attemptStudents = $questions
            ->flatMap(fn (ClassQuestion $question) => $question->attempts->pluck('user'))
            ->filter();

        $students = $studentRoster
            ->concat($class->participants)
            ->concat($attemptStudents)
            ->filter()
            ->unique('id')
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        return $questions->values()->map(function (ClassQuestion $question, int $index) use ($students, $participants) {
            $expectedMoves = collect($question->expected_moves ?? [])
                ->map(fn ($move) => trim((string) $move))
                ->filter()
                ->values();
            $attempts = $question->attempts->keyBy('user_id');

            return [
                'id' => $question->id,
                'number' => $index + 1,
                'prompt' => trim((string) $question->text),
                'position_fen' => $question->position_fen,
                'answer_moves' => $expectedMoves,
                'max_score' => $expectedMoves->count(),
                'arrows' => $question->arrows ?? [],
                'highlights' => $question->highlights ?? [],
                'asked_at' => $question->created_at,
                'ended_at' => $question->ended_at,
                'students' => $students->map(function (User $student) use ($attempts, $participants, $expectedMoves) {
                    $attempt = $attempts->get($student->id);
                    $correct = (int) ($attempt?->correct_count ?? 0);
                    $wrong = (int) ($attempt?->wrong_count ?? 0);
                    $totalJudgedMoves = $correct + $wrong;

                    return [
                        'student' => $student,
                        'attended' => $participants->has($student->id),
                        'score' => $correct,
                        'max_score' => $expectedMoves->count(),
                        'wrong_count' => $wrong,
                        'accuracy' => $totalJudgedMoves > 0 ? round(($correct / $totalJudgedMoves) * 100).'%' : '—',
                        'moves_played' => collect($attempt?->moves_played ?? [])
                            ->map(fn ($move) => trim((string) $move))
                            ->filter()
                            ->values(),
                        'status' => ! $attempt
                            ? 'Not attempted'
                            : ($attempt->completed ? 'Solved' : 'Attempted'),
                        'completed' => (bool) ($attempt?->completed ?? false),
                    ];
                })->values(),
            ];
        });
    }

    protected function coachIndexColumns(): array
    {
        return $this->userColumns(['id', 'name'], ['avatar_color']);
    }

    protected function coachShowColumns(): array
    {
        return $this->userColumns(['id', 'name', 'username'], ['avatar_color']);
    }

    protected function scheduleListColumns(): array
    {
        return $this->scheduleColumns(['id', 'group_id', 'day_of_week', 'start_time'], ['duration_minutes']);
    }

    protected function scheduleDetailColumns(): array
    {
        return $this->scheduleColumns(['id', 'group_id', 'day_of_week', 'start_time'], ['duration_minutes']);
    }

    protected function scheduleSummaryColumns(): array
    {
        return $this->scheduleColumns(['id', 'group_id'], ['duration_minutes']);
    }

    protected function userColumns(array $required, array $optional = []): array
    {
        $availableOptional = array_values(array_filter(
            $optional,
            fn (string $column) => Schema::hasColumn('users', $column)
        ));

        return array_merge($required, $availableOptional);
    }

    protected function scheduleColumns(array $required, array $optional = []): array
    {
        $availableOptional = array_values(array_filter(
            $optional,
            fn (string $column) => Schema::hasColumn('group_schedules', $column)
        ));

        return array_merge($required, $availableOptional);
    }
}
