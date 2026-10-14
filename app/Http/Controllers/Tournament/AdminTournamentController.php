<?php

namespace App\Http\Controllers\Tournament;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\Tournament;
use App\Models\User;
use App\Support\TournamentScheduler;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class AdminTournamentController extends Controller
{
    protected function tournamentValidationRules(): array
    {
        return [
            'name' => 'required|string|max:120',
            'event_series' => 'required|in:standard,archer_sunday',
            'format' => 'required|in:blitz,rapid,classical',
            'time_control' => 'required|string|max:16',
            'rounds' => 'required|integer|min:1|max:15',
            'scheduled_at' => 'required|date',
            'audience_type' => 'required|in:all_students,specific_groups,specific_students',
            'group_ids' => 'array',
            'group_ids.*' => 'integer|exists:groups,id',
            'user_ids' => 'array',
            'user_ids.*' => 'integer|exists:users,id',
        ];
    }

    protected function validateTournamentPayload(Request $request): array
    {
        $data = $request->validate($this->tournamentValidationRules());
        $user = $request->user();

        $allowed = collect(self::TIME_CONTROLS[$data['format']] ?? [])->pluck('value');
        if (! $allowed->contains($data['time_control'])) {
            throw ValidationException::withMessages([
                'time_control' => 'Pick a valid time control for the chosen mode.',
            ]);
        }

        if ($data['audience_type'] === Tournament::AUDIENCE_SPECIFIC_GROUPS && empty($data['group_ids'])) {
            throw ValidationException::withMessages([
                'group_ids' => 'Select at least one group.',
            ]);
        }

        if ($data['audience_type'] === Tournament::AUDIENCE_SPECIFIC_STUDENTS && empty($data['user_ids'])) {
            throw ValidationException::withMessages([
                'user_ids' => 'Select at least one student.',
            ]);
        }

        if ($data['audience_type'] === Tournament::AUDIENCE_ALL_STUDENTS) {
            $data['group_ids'] = [];
            $data['user_ids'] = [];
        }

        if ($user->isCoach()) {
            $allowedGroupIds = Group::where('coach_id', $user->id)->pluck('id');
            $allowedStudentIds = User::where('role', 'student')
                ->where('is_active', true)
                ->whereHas('groups', fn ($q) => $q->where('coach_id', $user->id))
                ->pluck('id');

            if (! collect($data['group_ids'] ?? [])->every(fn ($id) => $allowedGroupIds->contains($id))) {
                throw ValidationException::withMessages([
                    'group_ids' => 'Coaches may only target their own groups.',
                ]);
            }

            if (! collect($data['user_ids'] ?? [])->every(fn ($id) => $allowedStudentIds->contains($id))) {
                throw ValidationException::withMessages([
                    'user_ids' => 'Coaches may only target their own students.',
                ]);
            }
        }

        return $data;
    }

    protected function formOptions(): array
    {
        $user = Auth::user();
        $groups = $user->isCoach()
            ? Group::where('coach_id', $user->id)->withCount('students')->orderBy('name')->get()
            : Group::withCount('students')->orderBy('name')->get();

        $students = $user->isCoach()
            ? User::where('role', 'student')
                ->where('is_active', true)
                ->whereHas('groups', fn ($q) => $q->where('coach_id', $user->id))
                ->orderBy('name')
                ->get()
            : User::where('role', 'student')->where('is_active', true)->orderBy('name')->get();

        return [
            'groups' => $groups,
            'students' => $students,
            'timeControls' => self::TIME_CONTROLS,
        ];
    }

    protected function syncTargets(Tournament $tournament, array $data): void
    {
        $tournament->targetGroups()->sync(
            $data['audience_type'] === Tournament::AUDIENCE_SPECIFIC_GROUPS ? ($data['group_ids'] ?? []) : []
        );

        $tournament->targetUsers()->sync(
            $data['audience_type'] === Tournament::AUDIENCE_SPECIFIC_STUDENTS ? ($data['user_ids'] ?? []) : []
        );
    }

    protected function authorizeModify(Tournament $tournament): void
    {
        abort_unless($tournament->canBeManagedBy(Auth::user()), 403);
    }

    protected function authorizeView(Tournament $tournament): void
    {
        abort_unless($tournament->canBeViewedBy(Auth::user()), 403);
    }

    protected function buildRoundMinutes(string $timeControl): int
    {
        $base = (int) explode('+', $timeControl)[0];

        return max(5, $base * 2 + 5);
    }

    protected function normalizeScheduledAt(string $value): Carbon
    {
        return Carbon::parse($value, Auth::user()->preferredTimezone())
            ->timezone(config('app.timezone'));
    }

    public function index()
    {
        TournamentScheduler::tick();

        $upcoming = Tournament::whereIn('status', [Tournament::STATUS_UPCOMING, Tournament::STATUS_LIVE])
            ->orderBy('scheduled_at')
            ->with('creator')
            ->get();

        $past = Tournament::whereIn('status', [
            Tournament::STATUS_COMPLETED,
            Tournament::STATUS_CANCELLED,
            Tournament::STATUS_CANCELLED_INSUFFICIENT_PLAYERS,
        ])
            ->orderByDesc('scheduled_at')
            ->with('creator')
            ->limit(30)
            ->get();

        return view('tournaments.index', [
            'upcoming' => $upcoming,
            'past' => $past,
            'mode' => 'manage',
        ]);
    }

    public function create()
    {
        abort_unless(in_array(Auth::user()->role, ['admin', 'staff', 'coach'], true), 403);

        return view('tournaments.create', $this->formOptions());
    }

    public function store(Request $request)
    {
        $data = $this->validateTournamentPayload($request);

        $tournament = Tournament::create([
            'name' => $data['name'],
            'event_series' => $data['event_series'],
            'format' => $data['format'],
            'time_control' => $data['time_control'],
            'rounds' => $data['rounds'],
            'minimum_players' => 4,
            'scheduled_at' => $this->normalizeScheduledAt($data['scheduled_at']),
            'tournament_timezone' => Auth::user()->preferredTimezone(),
            'round_minutes' => $this->buildRoundMinutes($data['time_control']),
            'pairing_window_minutes' => 0,
            'round_break_minutes' => 2,
            'audience_type' => $data['audience_type'],
            'status' => Tournament::STATUS_UPCOMING,
            'created_by' => Auth::id(),
        ]);

        $this->syncTargets($tournament, $data);

        return redirect()
            ->route(self::roleHome())
            ->with('success', 'Tournament scheduled. Cron will auto-start it at the configured time.');
    }

    public function edit(Tournament $tournament)
    {
        $this->authorizeModify($tournament);
        abort_if($tournament->isLive() || $tournament->isCompleted(), 403, 'Live or completed tournaments cannot be edited.');

        return view('tournaments.create', array_merge($this->formOptions(), [
            'tournament' => $tournament->load(['targetGroups:id', 'targetUsers:id']),
        ]));
    }

    public function update(Request $request, Tournament $tournament)
    {
        $this->authorizeModify($tournament);
        abort_if($tournament->isLive() || $tournament->isCompleted(), 403, 'Live or completed tournaments cannot be edited.');

        $data = $this->validateTournamentPayload($request);

        $tournament->update([
            'name' => $data['name'],
            'event_series' => $data['event_series'],
            'format' => $data['format'],
            'time_control' => $data['time_control'],
            'rounds' => $data['rounds'],
            'scheduled_at' => $this->normalizeScheduledAt($data['scheduled_at']),
            'tournament_timezone' => Auth::user()->preferredTimezone(),
            'round_minutes' => $this->buildRoundMinutes($data['time_control']),
            'audience_type' => $data['audience_type'],
            'cancellation_reason' => null,
        ]);

        $this->syncTargets($tournament, $data);

        return redirect()
            ->route(self::roleShow(), $tournament)
            ->with('success', 'Tournament updated.');
    }

    public function show(Tournament $tournament)
    {
        $this->authorizeView($tournament);

        TournamentScheduler::advance($tournament);
        $tournament->refresh();

        $standings = $tournament->standings();
        $rounds = $tournament->pairings()
            ->with(['white', 'black', 'winner'])
            ->orderBy('round')
            ->orderBy('board')
            ->get()
            ->groupBy('round');

        $joinedStudents = $tournament->joinedStudents();
        $pendingStudents = $tournament->pendingStudents();

        return view('tournaments.show', [
            'tournament' => $tournament,
            'standings' => $standings,
            'rounds' => $rounds,
            'joinedStudents' => $joinedStudents,
            'pendingStudents' => $pendingStudents,
            'mode' => 'manage',
        ]);
    }

    public function destroy(Tournament $tournament)
    {
        $this->authorizeModify($tournament);
        abort_if($tournament->isLive(), 403, 'Live tournaments must be cancelled, not deleted.');

        try {
            $tournament->delete();
        } catch (QueryException $e) {
            Log::warning('Tournament delete blocked.', [
                'user_id' => Auth::id(),
                'action' => 'tournament.delete',
                'model_type' => Tournament::class,
                'model_id' => $tournament->id,
                'route' => request()->route()?->getName(),
                'exception' => $e::class,
            ]);

            return back()->with('error', 'This tournament cannot be deleted because it already has dependent pairings, participants, or historical results.');
        }

        return redirect()->route(self::roleHome())->with('success', 'Tournament deleted.');
    }

    public function cancel(Tournament $tournament)
    {
        abort_unless($tournament->canBeCancelledBy(Auth::user()), 403);

        TournamentScheduler::cancelTournament(
            $tournament,
            Tournament::STATUS_CANCELLED,
            $tournament->isLive() ? 'Cancelled by admin emergency action.' : 'Cancelled by tournament manager.'
        );

        return back()->with('success', 'Tournament cancelled.');
    }

    public const TIME_CONTROLS = [
        'blitz' => [
            ['value' => '3+0', 'label' => '3 min'],
            ['value' => '3+2', 'label' => '3 min + 2 sec'],
            ['value' => '5+0', 'label' => '5 min'],
            ['value' => '5+3', 'label' => '5 min + 3 sec'],
            ['value' => '10+0', 'label' => '10 min'],
        ],
        'rapid' => [
            ['value' => '10+5', 'label' => '10 min + 5 sec'],
            ['value' => '15+10', 'label' => '15 min + 10 sec'],
            ['value' => '25+10', 'label' => '25 min + 10 sec'],
            ['value' => '30+0', 'label' => '30 min'],
        ],
        'classical' => [
            ['value' => '60+0', 'label' => '60 min'],
            ['value' => '60+30', 'label' => '60 min + 30 sec'],
            ['value' => '90+30', 'label' => '90 min + 30 sec'],
            ['value' => '120+30', 'label' => '120 min + 30 sec'],
        ],
    ];

    public static function roleHome(): string
    {
        $u = Auth::user();
        if ($u->isAdmin()) {
            return 'admin.tournaments.index';
        }
        if ($u->isStaff()) {
            return 'staff.tournaments.index';
        }
        if ($u->isCoach()) {
            return 'coach.tournaments.index';
        }

        return 'student.tournaments.index';
    }

    public static function roleShow(): string
    {
        $u = Auth::user();
        if ($u->isAdmin()) {
            return 'admin.tournaments.show';
        }
        if ($u->isStaff()) {
            return 'staff.tournaments.show';
        }
        if ($u->isCoach()) {
            return 'coach.tournaments.show';
        }

        return 'student.tournaments.show';
    }
}
