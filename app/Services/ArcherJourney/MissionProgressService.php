<?php

namespace App\Services\ArcherJourney;

use App\Models\ArcherActivityEvent;
use App\Models\ArcherLevel;
use App\Models\ArcherMission;
use App\Models\ArcherMissionProgress;
use App\Models\ArcherStarBalance;
use App\Models\User;
use Carbon\Carbon;

class MissionProgressService
{
    public function apply(ArcherActivityEvent $event, User $student): array
    {
        if (! app(FeatureFlagService::class)->enabled('archer_missions_enabled', $student)) {
            return [];
        }

        $levelId = $this->currentLevelId($student);
        $followUps = [];
        $occurredAt = Carbon::parse($event->getRawOriginal('occurred_at'), 'UTC');

        $missions = ArcherMission::query()
            ->where('is_active', true)
            ->where('criteria_event_type', $event->event_type)
            ->where('starts_at', '<=', $occurredAt)
            ->where('ends_at', '>=', $occurredAt)
            ->where(fn ($query) => $query->whereNull('applicable_country')->orWhere('applicable_country', $student->country))
            ->where(fn ($query) => $query->whereNull('applicable_level_id')->orWhere('applicable_level_id', $levelId))
            ->get();

        foreach ($missions as $mission) {
            $progress = ArcherMissionProgress::firstOrCreate(
                ['mission_id' => $mission->id, 'user_id' => $student->id],
                ['progress' => 0, 'status' => 'active']
            );
            $progress = ArcherMissionProgress::query()->lockForUpdate()->findOrFail($progress->id);

            if ($progress->status === 'completed') {
                continue;
            }

            $increment = max(1, (int) data_get($event->context, 'progress_increment', 1));
            $newProgress = min($mission->target, $progress->progress + $increment);
            $completed = $newProgress >= $mission->target;

            $progress->update([
                'progress' => $newProgress,
                'status' => $completed ? 'completed' : 'active',
                'completed_at' => $completed ? $occurredAt : null,
            ]);

            if ($completed) {
                $followUps[] = [
                    'event_type' => ArcherJourneyEngine::MISSION_COMPLETED,
                    'student' => $student,
                    'source' => $mission,
                    'occurred_at' => $occurredAt,
                    'event_timezone' => $event->event_timezone,
                    'event_key' => 'mission_completed:'.$student->id.':'.$mission->id,
                    'context' => [
                        'title' => $mission->name.' completed',
                        'reason' => 'Completed mission: '.$mission->name,
                        'custom_stars' => $mission->reward_stars,
                        'mission_id' => $mission->id,
                        'mission_progress_id' => $progress->id,
                    ],
                ];
            }
        }

        return $followUps;
    }

    protected function currentLevelId(User $student): ?int
    {
        $stars = (int) ArcherStarBalance::where('user_id', $student->id)->value('lifetime_stars');

        return ArcherLevel::active()
            ->where('minimum_stars', '<=', $stars)
            ->orderByDesc('minimum_stars')
            ->value('id');
    }
}
