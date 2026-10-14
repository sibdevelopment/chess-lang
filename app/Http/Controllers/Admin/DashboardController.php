<?php namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Chapter;
use App\Models\Group;
use App\Models\Homework;
use App\Models\HomeworkSubmission;
use App\Models\LiveClass;
use App\Models\Tournament;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    protected const PEOPLE_PER_PAGE = 50;
    protected const STUDENT_USERNAME_PREFIX = 'ARCHERCHESS';
    protected const STUDENT_USERNAME_PAD_LENGTH = 2;
    protected const ROLE_USERNAME_PAD_LENGTH = 2;

    public function index(Request $request)
    {
        $user = request()->user();

        $view = 'admin.dashboard';
        $routePrefix = $user?->isStaff() ? 'staff' : 'admin';

        $tab = $request->get('tab', 'students');
        if (!in_array($tab, ['students', 'coaches', 'staff', 'groups'], true)) {
            $tab = 'students';
        }

        $data = [
            'routePrefix' => $routePrefix,
            'tab' => $tab,
            'isStaffView' => (bool) $user?->isStaff(),
        ];

        if ($tab === 'students') {
            $search = $request->get('search');
            $studentsQuery = User::students()
                ->where('is_active', true)
                ->when($search, fn($q) => $q->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('username', 'like', "%{$search}%");
                }));

            $data += [
                'search' => $search,
                'counts' => [
                    'new_id' => User::students()
                        ->where(function ($inner) {
                            $inner->whereNull('country')->orWhere('country', '');
                        })
                        ->where('is_active', false)
                        ->count(),
                    'active' => User::students()
                        ->whereNotNull('country')
                        ->where('country', '!=', '')
                        ->where('is_active', true)
                        ->count(),
                    'inactive' => User::students()
                        ->whereNotNull('country')
                        ->where('country', '!=', '')
                        ->where('is_active', false)
                        ->count(),
                ],
                'students' => $studentsQuery
                    ->with('groups')
                    ->orderByRaw('CAST(SUBSTRING(username, 12) AS UNSIGNED)')
                    ->paginate(self::PEOPLE_PER_PAGE)
                    ->withQueryString(),
                'assignableGroups' => Group::with('coach:id,name')
                    ->where('is_active', true)
                    ->orderByDesc('is_active')
                    ->orderBy('name')
                    ->get(['id', 'name', 'level', 'coach_id', 'is_active']),
                'nextUsername' => $this->nextStudentUsername(),
                'defaultPassword' => 'ARCHERCHESS',
                'countryCodes' => User::studentCountryCodes(),
            ];
        } elseif ($tab === 'coaches') {
            $search = $request->get('search');

            $data += [
                'search' => $search,
                'counts' => [
                    'active' => User::coaches()->where('is_active', true)->count(),
                    'inactive' => User::coaches()->where('is_active', false)->count(),
                ],
                'coaches' => User::coaches()
                    ->when($search, fn($q) => $q->where(function ($inner) use ($search) {
                        $inner->where('name', 'like', "%{$search}%")
                            ->orWhere('username', 'like', "%{$search}%");
                    }))
                    ->with('coachedGroups:id,name,coach_id,level,is_active')
                    ->withCount('coachedGroups')
                    ->orderBy('name')
                    ->paginate(self::PEOPLE_PER_PAGE)
                    ->withQueryString(),
                'assignableGroups' => Group::with('coach:id,name')
                    ->where('is_active', true)
                    ->orderByDesc('is_active')
                    ->orderBy('name')
                    ->get(['id', 'name', 'level', 'coach_id', 'is_active']),
                'nextUsername' => $this->nextRoleUsername('ARCHERCOACH'),
                'defaultPassword' => '',
            ];
        } elseif ($tab === 'staff') {
            $search = $request->get('search');

            $data += [
                'search' => $search,
                'counts' => [
                    'active' => User::staff()->where('is_active', true)->count(),
                    'inactive' => User::staff()->where('is_active', false)->count(),
                ],
                'staff' => User::staff()
                    ->whereKeyNot($request->user()->id)
                    ->when($search, fn($q) => $q->where(function ($inner) use ($search) {
                        $inner->where('name', 'like', "%{$search}%")
                            ->orWhere('username', 'like', "%{$search}%");
                    }))
                    ->orderBy('name')
                    ->paginate(self::PEOPLE_PER_PAGE)
                    ->withQueryString(),
                'nextUsername' => $this->nextRoleUsername('ARCHERSTAFF'),
                'defaultPassword' => '',
            ];
        } elseif ($tab === 'groups') {
            $search = $request->get('search');
            $status = in_array($request->get('status', 'active'), ['active', 'inactive'], true)
                ? $request->get('status', 'active')
                : 'active';

            $data += [
                'search' => $search,
                'status' => $status,
                'coaches' => User::coaches()->orderBy('name')->get(['id', 'name']),
                'assignableStudents' => User::students()
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->get(['id', 'name', 'username', 'country', 'is_active']),
                'curriculumLevels' => Group::LEVELS,
                'curriculumChapters' => Chapter::query()
                    ->orderedForLevel()
                    ->get(['id', 'name', 'level', 'sequence_number']),
                'counts' => [
                    'active' => Group::where('is_active', true)->count(),
                    'inactive' => Group::where('is_active', false)->count(),
                ],
                'groups' => Group::query()
                    ->with(['coach', 'schedules', 'students:id,name', 'chapters'])
                    ->withCount(['students as active_students_count' => fn($q) => $q->where('is_active', true)])
                    ->when($search, fn($q) => $q->where(function ($inner) use ($search) {
                        $inner->where('name', 'like', "%{$search}%")
                            ->orWhereHas('coach', fn($coach) => $coach->where('name', 'like', "%{$search}%"));
                    }))
                    ->when($status === 'active', fn($q) => $q->where('is_active', true))
                    ->when($status === 'inactive', fn($q) => $q->where('is_active', false))
                    ->orderBy('name')
                    ->paginate(self::PEOPLE_PER_PAGE)
                    ->withQueryString(),
            ];
        } elseif ($tab === 'classes') {
            $classScope = $request->get('class_scope', 'today');
            if (!in_array($classScope, ['live', 'today', 'upcoming', 'past', 'cancelled'], true)) {
                $classScope = 'today';
            }

            $classRequest = Request::create(route($routePrefix.'.classes'), 'GET', array_merge(
                $request->query(),
                ['tab' => $classScope]
            ));
            $classRequest->setUserResolver(fn () => $request->user());

            /** @var \Illuminate\View\View $classView */
            $classView = app(ClassesController::class)->index($classRequest);
            $classData = method_exists($classView, 'getData') ? $classView->getData() : [];

            if (isset($classData['classes'])) {
                $classData['classes']->withPath(route('admin.dashboard'));
                $classData['classes']->appends(['tab' => 'classes', 'class_scope' => $classScope]);
            }

            $data += $classData + [
                'classScope' => $classScope,
            ];
        } else {
            $homeworkScope = $request->get('homework_scope', 'list');
            if (!in_array($homeworkScope, ['list', 'submissions', 'results'], true)) {
                $homeworkScope = 'list';
            }

            $homeworkRequest = Request::create(route($routePrefix.'.homework'), 'GET', $request->query());
            $homeworkRequest->setUserResolver(fn () => $request->user());

            /** @var \Illuminate\View\View $homeworkView */
            $homeworkView = app(HomeworkController::class)->index($homeworkRequest);
            $homeworkData = method_exists($homeworkView, 'getData') ? $homeworkView->getData() : [];

            $groupId = $request->input('group_id');
            $coachId = $request->input('coach_id');

            $submissionQuery = HomeworkSubmission::with(['homework.chapter', 'homework.group', 'student']);
            if ($groupId) {
                $submissionQuery->whereHas('homework', fn ($query) => $query->where('group_id', $groupId));
            }
            if ($coachId) {
                $submissionQuery->whereHas('homework', fn ($query) => $query->where('assigned_by', $coachId));
            }

            $submissions = $submissionQuery
                ->orderByDesc(DB::raw('COALESCE(submitted_at, created_at)'))
                ->paginate(20, ['*'], 'submissions_page')
                ->withQueryString();
            $submissions->withPath(route('admin.dashboard'));
            $submissions->appends(['tab' => 'homework', 'homework_scope' => 'submissions']);

            $resultsQuery = Homework::with(['chapter', 'group', 'assigner', 'submissions']);
            if ($groupId) {
                $resultsQuery->where('group_id', $groupId);
            }
            if ($coachId) {
                $resultsQuery->where('assigned_by', $coachId);
            }

            $resultsHomework = $resultsQuery
                ->orderByDesc('created_at')
                ->paginate(20, ['*'], 'results_page')
                ->withQueryString();
            $resultsHomework->withPath(route('admin.dashboard'));
            $resultsHomework->appends(['tab' => 'homework', 'homework_scope' => 'results']);

            if (isset($homeworkData['homework'])) {
                $homeworkData['homework']->withPath(route('admin.dashboard'));
                $homeworkData['homework']->appends(['tab' => 'homework', 'homework_scope' => 'list']);
            }

            $data += $homeworkData + [
                'homeworkScope' => $homeworkScope,
                'homeworkSubmissions' => $submissions,
                'homeworkResults' => $resultsHomework,
            ];
        }

        return view($view, $data);
    }

    protected function staffDashboardData(): array
    {
        $tz = 'Asia/Kolkata';
        $startOfDay = Carbon::now($tz)->startOfDay()->utc();
        $endOfDay = Carbon::now($tz)->endOfDay()->utc();

        $todayClassesQuery = LiveClass::query()
            ->whereBetween('scheduled_for', [$startOfDay, $endOfDay]);

        $stats = [
            'active_students' => User::students()->where('is_active', true)->count(),
            'active_coaches' => User::coaches()->where('is_active', true)->count(),
            'active_groups' => Group::where('is_active', true)->count(),
            'today_classes' => (clone $todayClassesQuery)->count(),
            'live_now' => LiveClass::where('status', 'live')->count(),
            'missed_today' => (clone $todayClassesQuery)->where('status', 'cancelled')->count(),
            'students_without_group' => User::students()->where('is_active', true)->whereDoesntHave('groups')->count(),
            'groups_without_coach' => Group::whereNull('coach_id')->count(),
            'upcoming_tournaments' => Tournament::where('status', Tournament::STATUS_UPCOMING)->count(),
            'chapters' => Chapter::count(),
            'homework' => Homework::count(),
        ];

        $todayClasses = (clone $todayClassesQuery)
            ->with(['coach', 'group', 'chapter'])
            ->orderBy('scheduled_for')
            ->limit(5)
            ->get();

        $liveNow = LiveClass::with(['coach', 'group', 'chapter'])
            ->where('status', 'live')
            ->latest('started_at')
            ->limit(5)
            ->get();

        $missedToday = (clone $todayClassesQuery)
            ->with(['coach', 'group', 'chapter'])
            ->where('status', 'cancelled')
            ->orderByDesc(DB::raw(LiveClass::statusTimestampOrderExpression()))
            ->limit(5)
            ->get();

        $upcomingTournaments = Tournament::with('creator')
            ->where('status', Tournament::STATUS_UPCOMING)
            ->where('scheduled_at', '>=', now()->subMinute())
            ->orderBy('scheduled_at')
            ->limit(5)
            ->get();

        $studentsNeedingAssignment = User::students()
            ->where('is_active', true)
            ->whereDoesntHave('groups')
            ->orderBy('name')
            ->limit(5)
            ->get();

        $recentUsers = User::whereIn('role', ['student', 'coach', 'staff'])
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        $recentGroups = Group::with(['coach'])
            ->withCount('students')
            ->orderByDesc('updated_at')
            ->limit(5)
            ->get();

        return compact(
            'stats',
            'todayClasses',
            'liveNow',
            'missedToday',
            'upcomingTournaments',
            'studentsNeedingAssignment',
            'recentUsers',
            'recentGroups',
            'tz'
        );
    }

    protected function nextStudentUsername(): string
    {
        return $this->nextRoleUsername(self::STUDENT_USERNAME_PREFIX);
    }

    protected function nextRoleUsername(string $prefix): string
    {
        $highest = User::where('username', 'like', $prefix.'%')
            ->pluck('username')
            ->map(function ($username) use ($prefix) {
                $pattern = '/^' . preg_quote($prefix, '/') . '(\d+)$/i';

                return preg_match($pattern, $username, $matches) ? (int) $matches[1] : 0;
            })
            ->max() ?? 0;

        $padLength = $prefix === self::STUDENT_USERNAME_PREFIX
            ? self::STUDENT_USERNAME_PAD_LENGTH
            : self::ROLE_USERNAME_PAD_LENGTH;

        return $prefix
            . str_pad((string) ($highest + 1), $padLength, '0', STR_PAD_LEFT);
    }
}
