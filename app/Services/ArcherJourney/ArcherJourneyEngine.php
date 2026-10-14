<?php

namespace App\Services\ArcherJourney;

use App\Jobs\ProcessArcherJourneyEvent;
use App\Models\ArcherActivityEvent;
use App\Models\Group;
use App\Models\Homework;
use App\Models\LiveClass;
use App\Models\Tournament;
use App\Models\User;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ArcherJourneyEngine
{
    public const CLASS_ATTENDED = 'CLASS_ATTENDED';

    public const HOMEWORK_COMPLETED = 'HOMEWORK_COMPLETED';

    public const PUZZLE_COMPLETED = 'PUZZLE_COMPLETED';

    public const ENDGAME_PUZZLE_COMPLETED = 'ENDGAME_PUZZLE_COMPLETED';

    public const TOURNAMENT_PARTICIPATED = 'TOURNAMENT_PARTICIPATED';

    public const TOURNAMENT_GAME_PLAYED = 'TOURNAMENT_GAME_PLAYED';

    public const TOURNAMENT_WON = 'TOURNAMENT_WON';

    public const TOURNAMENT_CHECKMATE = 'TOURNAMENT_CHECKMATE';

    public const INTERNATIONAL_EVENT_PARTICIPATED = 'INTERNATIONAL_EVENT_PARTICIPATED';

    public const RATING_IMPROVED = 'RATING_IMPROVED';

    public const ACHIEVEMENT_CREATED = 'ACHIEVEMENT_CREATED';

    public const MISSION_COMPLETED = 'MISSION_COMPLETED';

    public const MONTHLY_CHALLENGE_COMPLETED = 'MONTHLY_CHALLENGE_COMPLETED';

    public const MONTHLY_ATTENDANCE_100 = 'MONTHLY_ATTENDANCE_100';

    public static function eventTypes(): array
    {
        return [
            self::CLASS_ATTENDED,
            self::HOMEWORK_COMPLETED,
            self::PUZZLE_COMPLETED,
            self::ENDGAME_PUZZLE_COMPLETED,
            self::TOURNAMENT_PARTICIPATED,
            self::TOURNAMENT_GAME_PLAYED,
            self::TOURNAMENT_WON,
            self::TOURNAMENT_CHECKMATE,
            self::INTERNATIONAL_EVENT_PARTICIPATED,
            self::RATING_IMPROVED,
            self::ACHIEVEMENT_CREATED,
            self::MISSION_COMPLETED,
            self::MONTHLY_CHALLENGE_COMPLETED,
            self::MONTHLY_ATTENDANCE_100,
        ];
    }

    public function record(
        string $eventType,
        User|int $student,
        Model|string|null $source = null,
        array $context = [],
        User|int|null $actor = null,
        DateTimeInterface|string|null $occurredAt = null,
        ?string $eventTimezone = null,
        ?string $eventKey = null,
    ): ArcherActivityEvent {
        $student = $student instanceof User ? $student : User::findOrFail($student);
        if (! $student->isStudent()) {
            throw new InvalidArgumentException('Archer Journey events can only be recorded for students.');
        }

        [$sourceType, $sourceId] = $this->sourceIdentity($source);
        $eventTimezone = $this->validTimezone($eventTimezone)
            ?? $this->timezoneFromSource($source)
            ?? $student->preferredTimezone();
        $occurredAt = match (true) {
            $occurredAt instanceof DateTimeInterface => Carbon::instance($occurredAt)->utc(),
            is_string($occurredAt) => Carbon::parse($occurredAt, $eventTimezone)->utc(),
            default => now()->utc(),
        };
        $userTimezone = $this->validTimezone($student->timezone) ?? $eventTimezone;
        $actorId = $actor instanceof User ? $actor->id : $actor;
        $eventKey ??= $this->defaultEventKey($eventType, $student->id, $sourceType, $sourceId, $context);

        $event = ArcherActivityEvent::firstOrCreate(
            ['event_key' => Str::limit($eventKey, 191, '')],
            [
                'event_type' => strtoupper($eventType),
                'user_id' => $student->id,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'actor_id' => $actorId,
                'occurred_at' => $occurredAt,
                'user_timezone' => $userTimezone,
                'event_timezone' => $eventTimezone,
                'context' => $context,
            ]
        );

        if ($event->wasRecentlyCreated || (! $event->processed_at && ! $event->failure_reason)) {
            ProcessArcherJourneyEvent::dispatch($event->id)->afterCommit();
        }

        return $event;
    }

    protected function sourceIdentity(Model|string|null $source): array
    {
        if ($source instanceof Model) {
            return [$source->getMorphClass(), $source->getKey()];
        }

        return [is_string($source) ? $source : null, null];
    }

    protected function defaultEventKey(
        string $eventType,
        int $studentId,
        ?string $sourceType,
        int|string|null $sourceId,
        array $context,
    ): string {
        $scope = $context['scope_key'] ?? ($sourceId ?? hash('sha256', json_encode($context)));

        return strtolower($eventType).':'.$studentId.':'.($sourceType ?: 'system').':'.$scope;
    }

    protected function timezoneFromSource(Model|string|null $source): ?string
    {
        return match (true) {
            $source instanceof LiveClass => $this->validTimezone($source->displayTimezone()),
            $source instanceof Homework => $this->validTimezone($source->group?->scheduleTimezone()),
            $source instanceof Tournament => $this->validTimezone($source->tournament_timezone),
            $source instanceof Group => $this->validTimezone($source->scheduleTimezone()),
            default => null,
        };
    }

    protected function validTimezone(?string $timezone): ?string
    {
        $timezone = trim((string) $timezone);

        return $timezone !== '' && in_array($timezone, timezone_identifiers_list(), true)
            ? $timezone
            : null;
    }
}
