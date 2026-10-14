<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use App\Models\StudentPerformanceAssessment;
use App\Models\User;
use App\Services\Reports\StudentPerformanceReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class StudentPerformanceReportController extends Controller
{
    public function __construct(
        protected StudentPerformanceReportService $reports
    ) {}

    public function index(Request $request): View
    {
        $viewer = $request->user();
        abort_unless($viewer && in_array($viewer->role, ['admin', 'staff', 'coach', 'student'], true), 403);

        $validated = $request->validate([
            'status' => ['nullable', Rule::in(['saved', 'need_assessment'])],
            'search' => ['nullable', 'string', 'max:120'],
            'date_from' => ['nullable', 'required_with:date_to', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'required_with:date_from', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ]);

        $canCreateReports = $this->canEditAssessment($viewer);
        $status = $canCreateReports ? ($validated['status'] ?? 'saved') : 'saved';
        $search = trim((string) ($validated['search'] ?? ''));
        [$dateFromValue, $dateToValue, $dateFrom, $dateTo] = $this->dateRange($validated);
        $hasPeriod = $dateFrom !== null && $dateTo !== null;
        $assessmentPeriod = $this->assessmentPeriod($dateFromValue, $dateToValue);

        $savedReportsQuery = $this->reports->visiblePublishedAssessmentsQuery($viewer)
            ->when($hasPeriod, $assessmentPeriod)
            ->when($search !== '', function ($query) use ($search) {
                $query->whereHas('student', function ($studentQuery) use ($search) {
                    $studentQuery->where(function ($inner) use ($search) {
                        $inner->where('name', 'like', "%{$search}%")
                            ->orWhere('username', 'like', "%{$search}%")
                            ->orWhere('country', 'like', "%{$search}%");
                    });
                });
            });

        $needAssessmentQuery = $this->reports->visibleStudentsQuery($viewer)
            ->when(
                $hasPeriod,
                fn ($query) => $query->whereDoesntHave('publishedPerformanceAssessments', $assessmentPeriod)
            )
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('username', 'like', "%{$search}%")
                        ->orWhere('country', 'like', "%{$search}%");
                });
            });

        $savedCount = (clone $savedReportsQuery)->count();
        $needAssessmentCount = $canCreateReports ? (clone $needAssessmentQuery)->count() : 0;
        $savedReports = $status === 'saved'
            ? $savedReportsQuery->paginate(50, ['*'], 'report_page')->withQueryString()
            : collect();
        $students = $status === 'need_assessment'
            ? $needAssessmentQuery->paginate(50, ['*'], 'student_page')->withQueryString()
            : collect();

        return view('reports.students-index', [
            'students' => $students,
            'savedReports' => $savedReports,
            'search' => $search,
            'routePrefix' => $this->routePrefix($viewer),
            'canCreateReports' => $canCreateReports,
            'reportStatusTab' => $status,
            'savedReportCount' => $savedCount,
            'needAssessmentCount' => $needAssessmentCount,
            'dateFrom' => $dateFromValue,
            'dateTo' => $dateToValue,
        ]);
    }

    public function show(Request $request, User $student): View
    {
        $viewer = $request->user();
        $this->authorizeViewer($viewer, $student);

        if (! $this->canEditAssessment($viewer)) {
            return $this->renderSavedReport($viewer, $student, $this->latestPublishedAssessment($student));
        }

        $routePrefix = $this->routePrefix($viewer);
        $validated = $request->validate([
            'date_from' => ['nullable', 'required_with:date_to', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'required_with:date_from', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ]);
        [$dateFromValue, $dateToValue, $dateFrom, $dateTo] = $this->dateRange($validated, true);

        return view('reports.student-performance', [
            'report' => $this->reports->build($student, true, $dateFrom, $dateTo),
            'student' => $student,
            'routePrefix' => $routePrefix,
            'canEditAssessment' => true,
            'isSavedReport' => false,
            'pdfRoute' => route($routePrefix.'.reports.students.pdf', [
                'student' => $student,
                'date_from' => $dateFromValue,
                'date_to' => $dateToValue,
            ]),
            'assessmentSaveRoute' => route($routePrefix.'.reports.students.assessments.store', $student),
            'dateFrom' => $dateFromValue,
            'dateTo' => $dateToValue,
        ]);
    }

    public function showSaved(Request $request, User $student, StudentPerformanceAssessment $assessment): View
    {
        $viewer = $request->user();
        $this->authorizePublishedAssessment($viewer, $student, $assessment);

        return $this->renderSavedReport($viewer, $student, $assessment);
    }

    public function showOwn(Request $request): View
    {
        $viewer = $request->user();
        abort_unless($viewer && $viewer->isStudent(), 403);

        return $this->renderSavedReport($viewer, $viewer, $this->latestPublishedAssessment($viewer));
    }

    public function showOwnSaved(Request $request, StudentPerformanceAssessment $assessment): View
    {
        $viewer = $request->user();
        abort_unless($viewer && $viewer->isStudent(), 403);
        $this->authorizePublishedAssessment($viewer, $viewer, $assessment);

        return $this->renderSavedReport($viewer, $viewer, $assessment);
    }

    public function storeAssessment(Request $request, User $student): RedirectResponse
    {
        $viewer = $request->user();
        $this->authorizeViewer($viewer, $student);
        abort_unless($this->canEditAssessment($viewer), 403);

        $validated = $request->validate($this->assessmentRules());
        [, , $periodStart, $periodEnd] = $this->dateRange([
            'date_from' => $validated['period_start'],
            'date_to' => $validated['period_end'],
        ]);
        $totalMarks = collect(StudentPerformanceAssessment::scoreFields())
            ->sum(fn (string $field) => (int) ($validated[$field] ?? 0));

        $assessment = StudentPerformanceAssessment::create(array_merge($validated, [
            'student_id' => $student->id,
            'assessor_id' => $viewer->id,
            'assessor_role' => $viewer->role,
            'assessed_at' => now(),
            'period_start' => $validated['period_start'],
            'period_end' => $validated['period_end'],
            'total_marks' => $totalMarks,
            'percentage' => round(($totalMarks / 100) * 100, 2),
            'report_snapshot' => $this->reports->captureSnapshot($student, $periodStart, $periodEnd),
        ]));

        return redirect()
            ->route($this->routePrefix($viewer).'.reports.students.saved.show', [$student, $assessment])
            ->with('success', 'Student performance report saved and published.');
    }

    public function downloadPdf(Request $request, User $student)
    {
        $viewer = $request->user();
        $this->authorizeViewer($viewer, $student);

        if (! $this->canEditAssessment($viewer)) {
            return $this->downloadSavedReport($student, $this->latestPublishedAssessment($student));
        }

        $validated = $request->validate([
            'date_from' => ['nullable', 'required_with:date_to', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'required_with:date_from', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ]);
        [, , $periodStart, $periodEnd] = $this->dateRange($validated, true);

        return $this->downloadReport(
            $student,
            $this->reports->build($student, true, $periodStart, $periodEnd),
            null
        );
    }

    public function downloadSavedPdf(Request $request, User $student, StudentPerformanceAssessment $assessment)
    {
        $viewer = $request->user();
        $this->authorizePublishedAssessment($viewer, $student, $assessment);

        return $this->downloadSavedReport($student, $assessment);
    }

    public function downloadOwnPdf(Request $request)
    {
        $viewer = $request->user();
        abort_unless($viewer && $viewer->isStudent(), 403);

        return $this->downloadSavedReport($viewer, $this->latestPublishedAssessment($viewer));
    }

    public function downloadOwnSavedPdf(Request $request, StudentPerformanceAssessment $assessment)
    {
        $viewer = $request->user();
        abort_unless($viewer && $viewer->isStudent(), 403);
        $this->authorizePublishedAssessment($viewer, $viewer, $assessment);

        return $this->downloadSavedReport($viewer, $assessment);
    }

    protected function renderSavedReport(User $viewer, User $student, StudentPerformanceAssessment $assessment): View
    {
        $this->authorizePublishedAssessment($viewer, $student, $assessment);
        $routePrefix = $this->routePrefix($viewer);
        $studentViewer = $viewer->isStudent();

        return view('reports.student-performance', [
            'report' => $this->reports->buildSaved($student, $assessment),
            'student' => $student,
            'routePrefix' => $routePrefix,
            'canEditAssessment' => false,
            'isSavedReport' => true,
            'pdfRoute' => $studentViewer
                ? route('student.reports.saved.pdf', $assessment)
                : route($routePrefix.'.reports.students.saved.pdf', [$student, $assessment]),
            'assessmentSaveRoute' => null,
            'dateFrom' => $assessment->period_start?->format('Y-m-d') ?? '',
            'dateTo' => $assessment->period_end?->format('Y-m-d') ?? '',
        ]);
    }

    protected function downloadSavedReport(User $student, StudentPerformanceAssessment $assessment)
    {
        return $this->downloadReport(
            $student,
            $this->reports->buildSaved($student, $assessment),
            $assessment
        );
    }

    protected function downloadReport(User $student, array $report, ?StudentPerformanceAssessment $assessment)
    {
        $pdf = Pdf::loadView('reports.student-performance-pdf', [
            'report' => $report,
            'student' => $student,
            'generatedAt' => now()->timezone(config('app.timezone')),
        ])->setPaper('a4');

        $date = ($assessment?->assessed_at ?? now())
            ->timezone(config('app.timezone'))
            ->format('Y-m-d');

        return $pdf->download("archer-report-{$student->username}-{$date}.pdf");
    }

    protected function latestPublishedAssessment(User $student): StudentPerformanceAssessment
    {
        return $student->publishedPerformanceAssessments()
            ->with('assessor:id,name,username,role')
            ->latest('assessed_at')
            ->latest('id')
            ->firstOrFail();
    }

    protected function authorizePublishedAssessment(?User $viewer, User $student, StudentPerformanceAssessment $assessment): void
    {
        $this->authorizeViewer($viewer, $student);
        abort_unless((int) $assessment->student_id === (int) $student->id, 404);
        abort_unless(
            StudentPerformanceAssessment::query()->published()->whereKey($assessment->id)->exists(),
            404
        );
    }

    protected function authorizeViewer(?User $viewer, User $student): void
    {
        abort_unless($viewer, 403);
        abort_unless($student->isStudent(), 404);

        if ($viewer->isAdmin() || $viewer->isStaff()) {
            return;
        }

        if ($viewer->isStudent()) {
            abort_unless((int) $viewer->id === (int) $student->id, 403);

            return;
        }

        abort_unless($viewer->isCoach(), 403);
        abort_unless(
            $student->groups()->where('coach_id', $viewer->id)->exists(),
            403,
            'You can only access reports for students assigned to your batches.'
        );
    }

    protected function routePrefix(User $viewer): string
    {
        return $viewer->isCoach()
            ? 'coach'
            : ($viewer->isStaff() ? 'staff' : ($viewer->isStudent() ? 'student' : 'admin'));
    }

    protected function canEditAssessment(User $viewer): bool
    {
        return $viewer->isAdmin() || $viewer->isCoach();
    }

    protected function dateRange(array $validated, bool $defaultToCurrentMonth = false): array
    {
        $dateFromValue = $validated['date_from'] ?? '';
        $dateToValue = $validated['date_to'] ?? '';

        if ($defaultToCurrentMonth && $dateFromValue === '' && $dateToValue === '') {
            $dateFromValue = now()->timezone(config('app.timezone'))->startOfMonth()->format('Y-m-d');
            $dateToValue = now()->timezone(config('app.timezone'))->endOfMonth()->format('Y-m-d');
        }

        $dateFrom = $dateFromValue !== ''
            ? Carbon::createFromFormat('Y-m-d', $dateFromValue, config('app.timezone'))->startOfDay()->utc()
            : null;
        $dateTo = $dateToValue !== ''
            ? Carbon::createFromFormat('Y-m-d', $dateToValue, config('app.timezone'))->endOfDay()->utc()
            : null;

        return [$dateFromValue, $dateToValue, $dateFrom, $dateTo];
    }

    protected function assessmentPeriod(string $dateFrom, string $dateTo): callable
    {
        return fn ($query) => $query
            ->whereDate('period_start', $dateFrom)
            ->whereDate('period_end', $dateTo);
    }

    protected function assessmentRules(): array
    {
        $rules = [
            'period_start' => ['required', 'date_format:Y-m-d'],
            'period_end' => ['required', 'date_format:Y-m-d', 'after_or_equal:period_start'],
            'strengths' => ['nullable', 'string'],
            'areas_to_improve' => ['nullable', 'string'],
            'coach_remarks' => ['nullable', 'string'],
            'next_focus' => ['nullable', 'string'],
        ];

        foreach (StudentPerformanceAssessment::scoreFields() as $field) {
            $rules[$field] = ['required', 'integer', 'between:0,10'];
        }

        return $rules;
    }
}
