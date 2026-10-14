<?php

namespace App\Http\Controllers;

use App\Models\ArcherDiscoverySnapshot;
use App\Models\User;
use App\Services\ArcherJourney\FeatureFlagService;
use Illuminate\Http\Request;

class ArcherDiscoveryController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(app(FeatureFlagService::class)->enabled('archer_discovery_enabled'), 404);
        $user = $request->user();
        $studentQuery = User::students()->active();
        if ($user->isCoach()) {
            $studentQuery->whereHas('groups', fn ($query) => $query->where('coach_id', $user->id));
        }
        $studentIds = (clone $studentQuery)->pluck('id');
        $snapshots = ArcherDiscoverySnapshot::with(['student', 'assessor'])
            ->when($user->isCoach(), fn ($query) => $query->where(function ($scope) use ($studentIds, $user) {
                $scope->whereIn('user_id', $studentIds)->orWhere('assessed_by', $user->id);
            }))
            ->latest('assessed_at')
            ->paginate(25);

        return view('archer-discovery.index', [
            'students' => $studentQuery->orderBy('name')->get(),
            'snapshots' => $snapshots,
            'routePrefix' => $user->role,
        ]);
    }

    public function store(Request $request)
    {
        abort_unless(app(FeatureFlagService::class)->enabled('archer_discovery_enabled'), 404);
        $data = $this->validated($request);
        $student = filled($data['user_id'] ?? null) ? User::students()->findOrFail($data['user_id']) : null;
        $this->authorizeStudent($request, $student);
        $data['student_name'] = $student?->name ?? $data['student_name'];
        $data['assessed_by'] = $request->user()->id;
        $data['assessed_at'] = now()->utc();
        ArcherDiscoverySnapshot::create($data);

        return back()->with('success', 'Archer Discovery snapshot created.');
    }

    public function update(Request $request, ArcherDiscoverySnapshot $snapshot)
    {
        abort_unless($request->user()->isAdmin() || $request->user()->isStaff() || $snapshot->assessed_by === $request->user()->id, 403);
        $data = $this->validated($request);
        $student = filled($data['user_id'] ?? null) ? User::students()->findOrFail($data['user_id']) : null;
        $this->authorizeStudent($request, $student);
        $data['student_name'] = $student?->name ?? $data['student_name'];
        $data['assessed_at'] = now()->utc();
        $snapshot->update($data);

        return back()->with('success', 'Archer Discovery snapshot updated.');
    }

    protected function validated(Request $request): array
    {
        return $request->validate([
            'user_id' => 'nullable|integer|exists:users,id',
            'student_name' => 'nullable|required_without:user_id|string|max:120',
            'chess_experience' => 'nullable|string|max:120',
            'strength' => 'nullable|string|max:255',
            'focus' => 'nullable|string|max:255',
            'recommended_level' => 'nullable|in:beginner,intermediate,advanced,expert',
            'next_step' => 'nullable|string|max:1000',
        ]);
    }

    protected function authorizeStudent(Request $request, ?User $student): void
    {
        if (! $student || $request->user()->isAdmin() || $request->user()->isStaff()) {
            return;
        }

        abort_unless($student->groups()->where('coach_id', $request->user()->id)->exists(), 403);
    }
}
