<?php

namespace App\Support;

use App\Models\Chapter;
use App\Models\Group;
use App\Models\GroupSchedule;
use App\Models\LiveClass;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ClassScheduler
{
    public const ROLLING_DAYS = 30;
    public const UPCOMING_CLASSES_TARGET = 10;
    protected const WINDOW_EXTENSION_DAYS = 14;
    protected const MAX_SCHEDULING_DAYS = 365;

    public static function syncForGroups(iterable $groups): array
    {
        $collection = $groups instanceof Collection ? $groups : collect($groups);
        $collection->loadMissing(['coach', 'chapters', 'schedules', 'scheduleSlots', 'chapterCompletions']);

        return $collection->map(fn (Group $group) => self::syncForGroup($group))->all();
    }

    public static function syncForGroup(Group $group): array
    {
        return DB::transaction(function () use ($group) {
            /** @var Group $lockedGroup */
            $lockedGroup = Group::query()
                ->with(['coach', 'chapters', 'scheduleSlots', 'schedules', 'chapterCompletions'])
                ->lockForUpdate()
                ->findOrFail($group->id);

            self::ensureChapterSequence($lockedGroup);

            $scheduled = LiveClass::query()
                ->where('group_id', $lockedGroup->id)
                ->where('status', 'scheduled')
                ->lockForUpdate()
                ->orderBy('scheduled_for')
                ->get()
                ->values();

            if (!$lockedGroup->is_active || !$lockedGroup->coach_id) {
                return self::cancelScheduledClasses($scheduled, 'Group inactive or unassigned.');
            }

            $activeSchedulesQuery = $lockedGroup->scheduleSlots();
            if (Schema::hasColumn('group_schedules', 'is_active')) {
                $activeSchedulesQuery->where('is_active', true);
            }
            $activeSchedules = $activeSchedulesQuery
                ->orderBy('day_of_week')
                ->orderBy('start_time')
                ->orderBy('id')
                ->get();

            if ($activeSchedules->isEmpty() || $lockedGroup->chapters->isEmpty() || $lockedGroup->hasFinishedLevel()) {
                return self::cancelScheduledClasses($scheduled, 'No active schedules or curriculum left.');
            }

            $desired = self::buildDesiredScheduleMap($lockedGroup, $activeSchedules);

            return self::reconcileScheduledClasses($lockedGroup, $scheduled, $desired);
        });
    }

    protected static function ensureChapterSequence(Group $group): void
    {
        if ($group->chapters->isNotEmpty()) {
            return;
        }

        $startingChapter = Chapter::where('level', $group->level)
            ->orderedForLevel()
            ->first();

        if (!$startingChapter) {
            return;
        }

        app(GroupChapterProgressionService::class)->assignStartingPoint($group, $group->level, $startingChapter->id);
        $group->load('chapters');
    }

    protected static function buildDesiredScheduleMap(Group $group, Collection $schedules): array
    {
        $timezone = $group->scheduleTimezone();
        $orderedChapters = $group->chapters
            ->sortBy(fn (Chapter $chapter) => (int) ($chapter->pivot->sequence_order ?? PHP_INT_MAX))
            ->values();
        $startingIndex = max(0, (int) $group->unlocked_chapter_index);

        if (!$orderedChapters->get($startingIndex)) {
            return [];
        }

        $windowStartUtc = now()->utc()->startOfMinute();
        $windowStartLocal = $windowStartUtc->copy()->timezone($timezone);
        $windowEndLocal = $windowStartLocal->copy()->addDays(self::ROLLING_DAYS)->endOfMinute();
        $absoluteWindowEndLocal = $windowStartLocal->copy()->addDays(self::MAX_SCHEDULING_DAYS)->endOfMinute();

        if ($group->start_date) {
            $groupStartLocal = Carbon::parse($group->start_date->toDateString(), $timezone)->startOfDay();
            if ($groupStartLocal->greaterThan($windowStartLocal)) {
                $windowStartLocal = $groupStartLocal;
                $windowEndLocal = $windowStartLocal->copy()->addDays(self::ROLLING_DAYS)->endOfMinute();
                $absoluteWindowEndLocal = $windowStartLocal->copy()->addDays(self::MAX_SCHEDULING_DAYS)->endOfMinute();
            }
        }

        if ($group->end_date) {
            $groupEndLocal = Carbon::parse($group->end_date->toDateString(), $timezone)->endOfDay();
            if ($groupEndLocal->lessThan($windowEndLocal)) {
                $windowEndLocal = $groupEndLocal;
            }
            if ($groupEndLocal->lessThan($absoluteWindowEndLocal)) {
                $absoluteWindowEndLocal = $groupEndLocal;
            }
        }

        if ($windowEndLocal->lessThan($windowStartLocal)) {
            return [];
        }

        $targetUpcomingClasses = min(
            self::UPCOMING_CLASSES_TARGET,
            max(0, $orderedChapters->count() - $startingIndex)
        );
        $futureBoundary = now($timezone)->endOfDay();
        $desired = [];

        while (true) {
            $occurrences = self::collectOccurrences($schedules, $windowStartLocal, $windowEndLocal, $timezone);
            $desired = self::planDesiredScheduleMap($group, $schedules, $orderedChapters, $startingIndex, $windowStartLocal, $occurrences);

            $futureGeneratedCount = collect($desired)
                ->filter(fn (array $payload) => $payload['scheduled_for']->copy()->timezone($timezone)->greaterThan($futureBoundary))
                ->count();

            if (
                $futureGeneratedCount >= $targetUpcomingClasses
                || count($desired) >= max(0, $orderedChapters->count() - $startingIndex)
                || $windowEndLocal->greaterThanOrEqualTo($absoluteWindowEndLocal)
            ) {
                break;
            }

            $windowEndLocal = $windowEndLocal->copy()->addDays(self::WINDOW_EXTENSION_DAYS)->endOfMinute();
            if ($windowEndLocal->greaterThan($absoluteWindowEndLocal)) {
                $windowEndLocal = $absoluteWindowEndLocal->copy();
            }
        }

        return $desired;
    }

    protected static function occurrencesForSchedule(GroupSchedule $schedule, Carbon $fromLocal, Carbon $untilLocal, string $timezone): array
    {
        $cursor = $fromLocal->copy()->startOfDay();
        $daysUntil = ($schedule->day_of_week - $cursor->dayOfWeek + 7) % 7;
        $cursor->addDays($daysUntil)->setTimeFromTimeString(substr((string) $schedule->start_time, 0, 8));

        if ($cursor->lt($fromLocal)) {
            $cursor->addWeek();
        }

        $occurrences = [];
        while ($cursor->lessThanOrEqualTo($untilLocal)) {
            $occurrences[] = $cursor->copy()->timezone($timezone)->startOfMinute();
            $cursor->addWeek();
        }

        return $occurrences;
    }

    protected static function collectOccurrences(Collection $schedules, Carbon $fromLocal, Carbon $untilLocal, string $timezone): array
    {
        $occurrences = [];

        foreach ($schedules as $schedule) {
            foreach (self::occurrencesForSchedule($schedule, $fromLocal, $untilLocal, $timezone) as $localOccurrence) {
                $utcOccurrence = $localOccurrence->copy()->utc()->startOfMinute();
                $occurrences[] = [
                    'schedule' => $schedule,
                    'local' => $localOccurrence->copy(),
                    'utc' => $utcOccurrence,
                ];
            }
        }

        usort($occurrences, function (array $a, array $b) {
            if ($a['utc']->equalTo($b['utc'])) {
                return $a['schedule']->id <=> $b['schedule']->id;
            }

            return $a['utc']->lt($b['utc']) ? -1 : 1;
        });

        return $occurrences;
    }

    protected static function planDesiredScheduleMap(
        Group $group,
        Collection $schedules,
        Collection $orderedChapters,
        int $startingIndex,
        Carbon $windowStartLocal,
        array $occurrences
    ): array {
        $desired = [];
        $plannedChapterIndex = $startingIndex;
        $pendingMissingSlots = self::remainingMissingSlotsBeforeCurrentChapter(
            $group,
            $schedules,
            $orderedChapters,
            $windowStartLocal->copy()
        );

        foreach ($occurrences as $occurrence) {
            if ($pendingMissingSlots > 0) {
                $pendingMissingSlots--;
                continue;
            }

            /** @var Chapter|null $scheduledChapter */
            $scheduledChapter = $orderedChapters->get($plannedChapterIndex);
            if (!$scheduledChapter) {
                break;
            }

            $scheduledStart = $occurrence['utc']->copy();
            $desired[self::scheduleKey($occurrence['schedule']->id, $occurrence['utc'])] = [
                'name' => $group->name . ' — ' . $occurrence['schedule']->day_label . ' Class',
                'coach_id' => $group->coach_id,
                'group_id' => $group->id,
                'chapter_id' => $scheduledChapter->id,
                'chapter_index' => $plannedChapterIndex,
                'group_schedule_id' => $occurrence['schedule']->id,
                'status' => 'scheduled',
                'scheduled_for' => $scheduledStart->copy(),
                'scheduled_start_at' => $scheduledStart->copy(),
                'scheduled_end_at' => $scheduledStart->copy()->addMinutes(Group::DEFAULT_DURATION_MINUTES),
                'duration_minutes' => Group::DEFAULT_DURATION_MINUTES,
                'completion_type' => null,
                'chapter_completed' => false,
                'actual_start_at' => null,
                'actual_end_at' => null,
                'started_at' => null,
                'ended_at' => null,
            ];

            $nextChapter = $orderedChapters->get($plannedChapterIndex + 1);
            $pendingMissingSlots = self::missingSlotsBetween($scheduledChapter, $nextChapter);
            $plannedChapterIndex++;
        }

        return $desired;
    }

    protected static function remainingMissingSlotsBeforeCurrentChapter(
        Group $group,
        Collection $schedules,
        Collection $orderedChapters,
        Carbon $windowStartLocal
    ): int {
        $currentIndex = max(0, (int) $group->unlocked_chapter_index);
        if ($currentIndex <= 0) {
            return 0;
        }

        /** @var Chapter|null $currentChapter */
        $currentChapter = $orderedChapters->get($currentIndex);
        /** @var Chapter|null $previousChapter */
        $previousChapter = $orderedChapters->get($currentIndex - 1);
        $missingSlots = self::missingSlotsBetween($previousChapter, $currentChapter);

        if ($missingSlots === 0 || !$previousChapter) {
            return 0;
        }

        $completion = $group->relationLoaded('chapterCompletions')
            ? $group->chapterCompletions
                ->where('chapter_id', $previousChapter->id)
                ->sortByDesc('completed_at')
                ->first()
            : $group->chapterCompletions()
                ->where('chapter_id', $previousChapter->id)
                ->latest('completed_at')
                ->first();

        if (!$completion?->completed_at) {
            return $missingSlots;
        }

        $referenceLocal = $completion->completed_at->copy()->timezone($group->scheduleTimezone());
        $consumedOccurrences = self::countOccurrencesBetween(
            $schedules,
            $referenceLocal,
            $windowStartLocal->copy(),
            $group->scheduleTimezone()
        );

        return max(0, $missingSlots - $consumedOccurrences);
    }

    protected static function countOccurrencesBetween(
        Collection $schedules,
        Carbon $referenceLocal,
        Carbon $untilLocal,
        string $timezone
    ): int {
        $count = 0;
        $fromLocal = $referenceLocal->copy()->addMinute();
        $toLocal = $untilLocal->copy()->subMinute();

        if ($toLocal->lt($fromLocal)) {
            return 0;
        }

        foreach ($schedules as $schedule) {
            $count += count(self::occurrencesForSchedule($schedule, $fromLocal, $toLocal, $timezone));
        }

        return $count;
    }

    protected static function missingSlotsBetween(?Chapter $currentChapter, ?Chapter $nextChapter): int
    {
        if (!$currentChapter || !$nextChapter) {
            return 0;
        }

        if ($currentChapter->level !== $nextChapter->level) {
            return 0;
        }

        return max(0, ((int) $nextChapter->sequence_number) - ((int) $currentChapter->sequence_number) - 1);
    }

    protected static function reconcileScheduledClasses(Group $group, Collection $scheduled, array $desired): array
    {
        $created = 0;
        $updated = 0;
        $cancelled = 0;
        $now = now()->utc();

        $existingByKey = [];
        foreach ($scheduled as $class) {
            $key = self::scheduleKey($class->group_schedule_id, $class->scheduled_for?->copy()->utc());
            $existingByKey[$key][] = $class;
        }

        foreach ($desired as $key => $payload) {
            $bucket = $existingByKey[$key] ?? [];
            $match = array_shift($bucket);
            $existingByKey[$key] = $bucket;

            if ($match) {
                $dirty = collect($payload)->contains(function ($value, string $field) use ($match) {
                    if ($field === 'scheduled_for') {
                        return !$match->scheduled_for || !$match->scheduled_for->copy()->utc()->equalTo($value);
                    }

                    return $match->{$field} !== $value;
                });

                if ($dirty) {
                    $match->update($payload);
                    $updated++;
                }

                continue;
            }

            LiveClass::create($payload + [
                'room_id' => (string) Str::uuid(),
            ]);
            $created++;
        }

        foreach ($existingByKey as $duplicates) {
            foreach ($duplicates as $class) {
                $class->update(LiveClass::cancellationUpdatePayload($now));
                $cancelled++;
            }
        }

        return compact('created', 'updated', 'cancelled');
    }

    protected static function cancelScheduledClasses(Collection $scheduled, string $reason): array
    {
        $cancelled = 0;
        $now = now()->utc();

        foreach ($scheduled as $class) {
            $class->update(LiveClass::cancellationUpdatePayload($now));
            $cancelled++;
        }

        return ['created' => 0, 'updated' => 0, 'cancelled' => $cancelled, 'reason' => $reason];
    }

    protected static function scheduleKey(?int $scheduleId, ?Carbon $scheduledFor): string
    {
        return ($scheduleId ?? 0) . '|' . ($scheduledFor?->copy()->utc()->format('Y-m-d H:i:s') ?? 'null');
    }
}
