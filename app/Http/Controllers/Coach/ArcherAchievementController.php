<?php

namespace App\Http\Controllers\Coach;

use App\Http\Controllers\Controller;
use App\Models\ArcherAchievement;
use App\Models\User;
use App\Services\ArcherJourney\ArcherLmsEventBridge;
use Illuminate\Http\Request;

class ArcherAchievementController extends Controller
{
    public function index(Request $request)
    {
        $coach = $request->user();
        $studentIds = User::students()
            ->active()
            ->whereHas('groups', fn ($query) => $query->where('coach_id', $coach->id))
            ->pluck('id');

        return view('coach.archer-achievements.index', [
            'students' => User::whereIn('id', $studentIds)->orderBy('name')->get(),
            'achievements' => ArcherAchievement::with(['student', 'awarder'])
                ->whereIn('user_id', $studentIds)
                ->latest('awarded_at')
                ->paginate(25),
        ]);
    }

    public function store(Request $request, ArcherLmsEventBridge $bridge)
    {
        $data = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'achievement_type' => 'required|in:sportsmanship,effort,focus,improvement,leadership,other',
            'title' => 'required|string|max:120',
            'note' => 'nullable|string|max:1000',
        ]);
        $student = User::students()->active()->findOrFail($data['user_id']);
        abort_unless($student->groups()->where('coach_id', $request->user()->id)->exists(), 403);

        $achievement = ArcherAchievement::create($data + [
            'awarded_by' => $request->user()->id,
            'awarded_at' => now()->utc(),
        ]);
        $bridge->achievementCreated($achievement);

        return back()->with('success', 'Achievement given. Archer Stars are calculated automatically.');
    }
}
