<?php

namespace App\Services\ArcherJourney;

use App\Models\ArcherActivityEvent;
use App\Models\ArcherPassportEntry;
use App\Models\User;
use Carbon\Carbon;

class ArcherPassportService
{
    private const EVENT_LABELS = [
        ArcherJourneyEngine::CLASS_ATTENDED => ['Class attended', '🏹'],
        ArcherJourneyEngine::HOMEWORK_COMPLETED => ['Homework completed', '📘'],
        ArcherJourneyEngine::PUZZLE_COMPLETED => ['Puzzle completed', '🧩'],
        ArcherJourneyEngine::TOURNAMENT_PARTICIPATED => ['Tournament participation', '🏆'],
        ArcherJourneyEngine::TOURNAMENT_WON => ['Tournament game won', '🏆'],
        ArcherJourneyEngine::TOURNAMENT_CHECKMATE => ['Tournament checkmate', '♟'],
        ArcherJourneyEngine::RATING_IMPROVED => ['Chess rating improved', '📈'],
        ArcherJourneyEngine::ACHIEVEMENT_CREATED => ['Coach achievement', '⭐'],
        ArcherJourneyEngine::MISSION_COMPLETED => ['Mission completed', '🎯'],
        ArcherJourneyEngine::MONTHLY_CHALLENGE_COMPLETED => ['Monthly challenge completed', '🏹'],
    ];

    public function recordEvent(ArcherActivityEvent $event, User $student): void
    {
        if (! app(FeatureFlagService::class)->enabled('archer_passport_enabled', $student)) {
            return;
        }

        $label = self::EVENT_LABELS[$event->event_type] ?? null;
        if (! $label) {
            return;
        }

        ArcherPassportEntry::firstOrCreate(
            ['entry_key' => 'event:'.$event->event_key],
            [
                'user_id' => $student->id,
                'activity_event_id' => $event->id,
                'entry_type' => strtolower($event->event_type),
                'title' => $event->context['title'] ?? $label[0],
                'description' => $event->context['description'] ?? $event->context['reason'] ?? null,
                'icon' => $label[1],
                'source_type' => $event->source_type,
                'source_id' => $event->source_id,
                'occurred_at' => Carbon::parse($event->getRawOriginal('occurred_at'), 'UTC'),
                'metadata' => $event->context,
            ]
        );
    }

    public function recordMilestone(
        User $student,
        string $key,
        string $type,
        string $title,
        string $icon,
        ?string $description = null,
        array $metadata = [],
    ): void {
        ArcherPassportEntry::firstOrCreate(
            ['entry_key' => $key],
            [
                'user_id' => $student->id,
                'entry_type' => $type,
                'title' => $title,
                'description' => $description,
                'icon' => $icon,
                'occurred_at' => now()->utc(),
                'metadata' => $metadata,
            ]
        );
    }
}
