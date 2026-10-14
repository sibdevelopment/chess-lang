<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\ArcherNotification;
use App\Models\ArcherNotificationPreference;
use App\Services\ArcherJourney\ArcherJourneyViewService;
use App\Services\ArcherJourney\FeatureFlagService;
use Illuminate\Http\Request;

class ArcherJourneyController extends Controller
{
    public function show(Request $request, ArcherJourneyViewService $journey)
    {
        $student = $request->user();
        abort_unless(app(FeatureFlagService::class)->enabled('archer_stars_enabled', $student), 404);

        $tab = in_array($request->string('tab')->toString(), ['journey', 'passport', 'leaderboards'], true)
            ? $request->string('tab')->toString()
            : 'journey';
        $board = $request->string('board', 'stars')->toString();
        $scope = $request->string('scope', 'global')->toString();
        $data = $journey->profile($student);
        $data['tab'] = $tab;
        $data['board'] = $board;
        $data['scope'] = $scope;
        $data['leaders'] = $tab === 'leaderboards' && app(FeatureFlagService::class)->enabled('archer_leaderboard_enabled', $student)
            ? $journey->leaderboard($student, $board, $scope)
            : collect();

        return view('student.archer-journey.show', $data);
    }

    public function readNotifications(Request $request)
    {
        ArcherNotification::where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()->utc()]);

        return back()->with('success', 'Achievement notifications marked as read.');
    }

    public function updateNotificationPreferences(Request $request)
    {
        ArcherNotificationPreference::updateOrCreate(
            ['user_id' => $request->user()->id],
            [
                'in_app_enabled' => $request->boolean('in_app_enabled'),
                'email_enabled' => false,
                'level_updates' => $request->boolean('level_updates'),
                'badge_updates' => $request->boolean('badge_updates'),
                'mission_updates' => $request->boolean('mission_updates'),
                'tournament_updates' => $request->boolean('tournament_updates'),
            ]
        );

        return back()->with('success', 'Achievement notification preferences saved.');
    }
}
