<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LiveClass;
use App\Models\User;
use App\Services\Reports\StudentPerformanceReportService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function __construct(
        protected StudentPerformanceReportService $studentReports
    ) {}

    public function index(Request $request): View
    {
        $validated = $request->validate([
            'tab' => ['nullable', Rule::in(['student', 'coach'])],
            'status' => ['nullable', Rule::in(['saved', 'need_assessment'])],
            'search' => ['nullable', 'string', 'max:120'],
            'type' => ['nullable', Rule::in(['attendance', 'cancelled', 'late'])],
            'coach_id' => ['nullable', 'integer'],
            'date_from' => ['nullable', 'required_with:date_to', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'required_with:date_from', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ]);

        $activeTab = $validated['tab'] ?? 'student';
        $studentStatus = $validated['status'] ?? 'saved';
        $reportType = $validated['type'] ?? 'attendance';
        $studentSearch = trim((string) ($validated['search'] ?? ''));
        $dateFromValue = $validated['date_from'] ?? '';
        $dateToValue = $validated['date_to'] ?? '';
        $dateFrom = $dateFromValue !== ''
            ? Carbon::createFromFormat('Y-m-d', $dateFromValue, config('app.timezone'))->startOfDay()->utc()
            : null;
        $dateTo = $dateToValue !== ''
            ? Carbon::createFromFormat('Y-m-d', $dateToValue, config('app.timezone'))->endOfDay()->utc()
            : null;
        $hasPeriod = $dateFrom !== null && $dateTo !== null;
        $assessmentPeriod = fn ($query) => $query
            ->whereDate('period_start', $dateFromValue)
            ->whereDate('period_end', $dateToValue);

        $savedReportsQuery = $this->studentReports
            ->visiblePublishedAssessmentsQuery($request->user())
            ->when($hasPeriod, $assessmentPeriod)
            ->when($studentSearch !== '', function ($query) use ($studentSearch) {
                $query->whereHas('student', function ($studentQuery) use ($studentSearch) {
                    $studentQuery->where(function ($inner) use ($studentSearch) {
                        $inner->where('name', 'like', "%{$studentSearch}%")
                            ->orWhere('username', 'like', "%{$studentSearch}%")
                            ->orWhere('country', 'like', "%{$studentSearch}%");
                    });
                });
            });

        $needAssessmentQuery = $this->studentReports
            ->visibleStudentsQuery($request->user())
            ->when(
                $hasPeriod,
                fn ($query) => $query->whereDoesntHave('publishedPerformanceAssessments', $assessmentPeriod)
            )
            ->when($studentSearch !== '', function ($query) use ($studentSearch) {
                $query->where(function ($inner) use ($studentSearch) {
                    $inner->where('name', 'like', "%{$studentSearch}%")
                        ->orWhere('username', 'like', "%{$studentSearch}%")
                        ->orWhere('country', 'like', "%{$studentSearch}%");
                });
            });

        $savedReportCount = (clone $savedReportsQuery)->count();
        $needAssessmentCount = (clone $needAssessmentQuery)->count();
        $savedReports = $activeTab === 'student' && $studentStatus === 'saved'
            ? $savedReportsQuery->paginate(50, ['*'], 'report_page')->withQueryString()
            : collect();
        $students = $activeTab === 'student' && $studentStatus === 'need_assessment'
            ? $needAssessmentQuery->paginate(50, ['*'], 'student_page')->withQueryString()
            : collect();

        $coachOptions = User::coaches()
            ->orderBy('name')
            ->get(['id', 'name', 'username', 'is_active']);

        $coachId = isset($validated['coach_id'])
            && $coachOptions->contains('id', (int) $validated['coach_id'])
                ? (int) $validated['coach_id']
                : null;

        $classTimestamp = DB::raw('COALESCE(scheduled_start_at, scheduled_for, created_at)');

        $classes = LiveClass::query()
            ->with(['coach:id,name,username,is_active', 'group:id,name'])
            ->whereNotNull('coach_id')
            ->when($coachId, fn ($query) => $query->where('coach_id', $coachId))
            ->when($dateFrom, fn ($query) => $query->where($classTimestamp, '>=', $dateFrom->format('Y-m-d H:i:s')))
            ->when($dateTo, fn ($query) => $query->where($classTimestamp, '<=', $dateTo->format('Y-m-d H:i:s')))
            ->orderByDesc($classTimestamp)
            ->get()
            ->filter(function (LiveClass $class) {
                $scheduledStart = $class->scheduledStart();

                return ! $scheduledStart
                    || $scheduledStart->lte(now()->utc())
                    || $class->status !== 'scheduled';
            })
            ->values();

        $visibleCoaches = $coachId
            ? $coachOptions->where('id', $coachId)->values()
            : $coachOptions;

        $classesByCoach = $classes->groupBy('coach_id');
        $coachRows = $visibleCoaches->map(function (User $coach) use ($classesByCoach) {
            $coachClasses = $classesByCoach->get($coach->id, collect());
            $total = $coachClasses->count();
            $attended = $coachClasses->filter(fn (LiveClass $class) => $this->wasAttended($class))->count();
            $cancelled = $coachClasses->where('status', 'cancelled')->count();
            $late = $coachClasses->filter(fn (LiveClass $class) => $this->wasJoinedLate($class))->count();

            return [
                'coach' => $coach,
                'total' => $total,
                'attended' => $attended,
                'cancelled' => $cancelled,
                'late' => $late,
                'attendance_percent' => $total > 0 ? round(($attended / $total) * 100, 1) : 0.0,
            ];
        });

        $summary = [
            'coaches' => $visibleCoaches->count(),
            'classes' => $classes->count(),
            'attended' => $classes->filter(fn (LiveClass $class) => $this->wasAttended($class))->count(),
            'cancelled' => $classes->where('status', 'cancelled')->count(),
            'late' => $classes->filter(fn (LiveClass $class) => $this->wasJoinedLate($class))->count(),
        ];

        $activity = match ($reportType) {
            'cancelled' => $classes->where('status', 'cancelled')->values(),
            'late' => $classes->filter(fn (LiveClass $class) => $this->wasJoinedLate($class))->values(),
            default => collect(),
        };

        $activityPaginator = null;
        if ($reportType !== 'attendance') {
            $perPage = 25;
            $page = LengthAwarePaginator::resolveCurrentPage();
            $activityPaginator = new LengthAwarePaginator(
                $activity->forPage($page, $perPage)->values(),
                $activity->count(),
                $perPage,
                $page,
                [
                    'path' => route('admin.reports'),
                    'query' => $request->except('page'),
                ]
            );
        }

        return view('admin.reports', [
            'activeTab' => $activeTab,
            'students' => $students,
            'savedReports' => $savedReports,
            'studentSearch' => $studentSearch,
            'studentStatus' => $studentStatus,
            'savedReportCount' => $savedReportCount,
            'needAssessmentCount' => $needAssessmentCount,
            'reportType' => $reportType,
            'coachOptions' => $coachOptions,
            'coachRows' => $coachRows,
            'summary' => $summary,
            'activityPaginator' => $activityPaginator,
            'filters' => [
                'coach_id' => $coachId,
                'date_from' => $dateFromValue,
                'date_to' => $dateToValue,
            ],
        ]);
    }

    protected function wasAttended(LiveClass $class): bool
    {
        return $class->actualStart() !== null
            && in_array($class->status, ['live', 'ended', 'completed'], true);
    }

    protected function wasJoinedLate(LiveClass $class): bool
    {
        $scheduledStart = $class->scheduledStart();
        $actualStart = $class->actualStart();

        return $this->wasAttended($class)
            && $scheduledStart !== null
            && $actualStart !== null
            && $actualStart->gt($scheduledStart);
    }
}
