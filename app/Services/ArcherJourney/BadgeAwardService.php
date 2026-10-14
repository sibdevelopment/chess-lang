<?php

namespace App\Services\ArcherJourney;

use App\Models\ArcherActivityEvent;
use App\Models\ArcherBadge;
use App\Models\ArcherBadgeAward;
use App\Models\User;
use Carbon\Carbon;

class BadgeAwardService
{
    public function evaluate(ArcherActivityEvent $event, User $student): void
    {
        if (! app(FeatureFlagService::class)->enabled('archer_badges_enabled', $student)) {
            return;
        }

        ArcherBadge::query()
            ->active()
            ->where('is_automatic', true)
            ->where('criteria_event_type', $event->event_type)
            ->whereDoesntHave('awards', fn ($query) => $query->where('user_id', $student->id))
            ->get()
            ->each(function (ArcherBadge $badge) use ($event, $student) {
                if (! $this->criteriaMet($badge, $event, $student)) {
                    return;
                }

                $this->award($badge, $student, $event, null, 'Automatically earned from Archer activity.');
            });
    }

    public function award(
        ArcherBadge $badge,
        User $student,
        ?ArcherActivityEvent $event = null,
        ?User $actor = null,
        ?string $note = null,
    ): ArcherBadgeAward {
        $award = ArcherBadgeAward::firstOrCreate(
            ['badge_id' => $badge->id, 'user_id' => $student->id],
            [
                'activity_event_id' => $event?->id,
                'awarded_by' => $actor?->id,
                'earned_at' => $event
                    ? Carbon::parse($event->getRawOriginal('occurred_at'), 'UTC')
                    : now()->utc(),
                'note' => $note,
            ]
        );

        if ($award->wasRecentlyCreated) {
            app(ArcherNotificationService::class)->create(
                $student,
                'badge_earned',
                '🏅 New Badge!',
                'You earned '.$badge->name.'.',
                ['badge_id' => $badge->id, 'icon' => $badge->icon]
            );
            app(ArcherPassportService::class)->recordMilestone(
                $student,
                'badge:'.$badge->id.':student:'.$student->id,
                'badge',
                $badge->name,
                $badge->icon ?: '🏅',
                $badge->description,
                ['badge_id' => $badge->id]
            );
        }

        return $award;
    }

    protected function criteriaMet(ArcherBadge $badge, ArcherActivityEvent $event, User $student): bool
    {
        if ($event->event_type === ArcherJourneyEngine::RATING_IMPROVED) {
            return (int) data_get($event->context, 'rating_gain_from_start', 0) >= $badge->criteria_target;
        }

        $count = ArcherActivityEvent::query()
            ->where('user_id', $student->id)
            ->where('event_type', $badge->criteria_event_type)
            ->count();

        return $count >= $badge->criteria_target;
    }
}
