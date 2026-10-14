<?php

namespace App\Services\ArcherJourney;

use App\Models\ArcherActivityEvent;
use App\Models\ArcherBadge;
use App\Models\ArcherLevel;
use App\Models\ArcherMonthlyChallenge;
use App\Models\ArcherMonthlyChallengeProgress;
use App\Models\ArcherStarBalance;
use App\Models\User;
use Carbon\Carbon;

class MonthlyChallengeService
{
    public function apply(ArcherActivityEvent $event, User $student): array
    {
        if (! app(FeatureFlagService::class)->enabled('archer_monthly_challenge_enabled', $student)) {
            return [];
        }

        $occurredAt = Carbon::parse($event->getRawOriginal('occurred_at'), 'UTC');
        $timezone = in_array($event->user_timezone, timezone_identifiers_list(), true)
            ? $event->user_timezone
            : 'UTC';
        $localDate = $occurredAt->timezone($timezone)->toDateString();
        $levelId = $this->currentLevelId($student);
        $followUps = [];

        $challenges = ArcherMonthlyChallenge::query()
            ->where('is_active', true)
            ->whereDate('month_start', '<=', $localDate)
            ->whereDate('month_end', '>=', $localDate)
            ->where(fn ($query) => $query->whereNull('applicable_country')->orWhere('applicable_country', $student->country))
            ->where(fn ($query) => $query->whereNull('applicable_level_id')->orWhere('applicable_level_id', $levelId))
            ->get();

        foreach ($challenges as $challenge) {
            $criteria = $challenge->criteria ?? [];
            if (! array_key_exists($event->event_type, $criteria)) {
                continue;
            }

            $row = ArcherMonthlyChallengeProgress::firstOrCreate(
                ['challenge_id' => $challenge->id, 'user_id' => $student->id],
                ['progress' => []]
            );
            $row = ArcherMonthlyChallengeProgress::query()->lockForUpdate()->findOrFail($row->id);
            if ($row->completed_at) {
                continue;
            }

            $progress = $row->progress ?? [];
            $progress[$event->event_type] = min(
                (int) $criteria[$event->event_type],
                (int) ($progress[$event->event_type] ?? 0) + max(1, (int) data_get($event->context, 'progress_increment', 1))
            );
            $completed = collect($criteria)->every(
                fn ($target, $eventType) => (int) ($progress[$eventType] ?? 0) >= (int) $target
            );

            $row->update([
                'progress' => $progress,
                'completed_at' => $completed ? $occurredAt : null,
            ]);

            if ($completed) {
                if ($challenge->badge_id) {
                    $badge = ArcherBadge::find($challenge->badge_id);
                    if ($badge) {
                        app(BadgeAwardService::class)->award($badge, $student, $event);
                    }
                }

                $followUps[] = [
                    'event_type' => ArcherJourneyEngine::MONTHLY_CHALLENGE_COMPLETED,
                    'student' => $student,
                    'source' => $challenge,
                    'occurred_at' => $occurredAt,
                    'event_timezone' => $event->event_timezone,
                    'event_key' => 'monthly_challenge_completed:'.$student->id.':'.$challenge->id,
                    'context' => [
                        'title' => $challenge->name.' completed',
                        'reason' => 'Completed monthly challenge: '.$challenge->name,
                        'custom_stars' => $challenge->reward_stars,
                        'challenge_id' => $challenge->id,
                        'challenge_progress_id' => $row->id,
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
