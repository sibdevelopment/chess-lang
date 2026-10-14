<?php

namespace App\Services\ArcherJourney;

use App\Models\ArcherNotification;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ArcherNotificationService
{
    public function create(User $student, string $type, string $title, string $message, array $data = []): ?ArcherNotification
    {
        if (! app(FeatureFlagService::class)->enabled('archer_notifications_enabled', $student)) {
            return null;
        }

        $enabled = DB::table('archer_notification_preferences')
            ->where('user_id', $student->id)
            ->first();

        if ($enabled && ! $enabled->in_app_enabled) {
            return null;
        }

        $preference = match ($type) {
            'level_up' => 'level_updates',
            'badge_earned' => 'badge_updates',
            'mission_completed', 'monthly_challenge_completed' => 'mission_updates',
            'tournament_achievement' => 'tournament_updates',
            default => null,
        };
        if ($preference && $enabled && ! $enabled->{$preference}) {
            return null;
        }

        return ArcherNotification::create([
            'user_id' => $student->id,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'data' => $data,
        ]);
    }
}
