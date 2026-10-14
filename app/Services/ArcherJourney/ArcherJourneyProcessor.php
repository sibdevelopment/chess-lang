<?php

namespace App\Services\ArcherJourney;

use App\Models\ArcherActivityEvent;
use App\Models\ArcherLevel;
use App\Models\ArcherRewardRule;
use App\Models\ArcherStarBalance;
use App\Models\ArcherStarTransaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class ArcherJourneyProcessor
{
    private const STREAK_EVENTS = [
        ArcherJourneyEngine::CLASS_ATTENDED,
        ArcherJourneyEngine::HOMEWORK_COMPLETED,
        ArcherJourneyEngine::PUZZLE_COMPLETED,
        ArcherJourneyEngine::TOURNAMENT_PARTICIPATED,
        ArcherJourneyEngine::TOURNAMENT_GAME_PLAYED,
        ArcherJourneyEngine::ACHIEVEMENT_CREATED,
    ];

    public function process(int|ArcherActivityEvent $activityEvent): void
    {
        $eventId = $activityEvent instanceof ArcherActivityEvent ? $activityEvent->id : $activityEvent;
        $followUps = [];

        try {
            $followUps = DB::transaction(function () use ($eventId) {
                $event = ArcherActivityEvent::query()->lockForUpdate()->findOrFail($eventId);
                if ($event->processed_at) {
                    return [];
                }

                $event->increment('processing_attempts');
                $student = User::query()->findOrFail($event->user_id);
                if (! $student->isStudent()) {
                    throw new RuntimeException('Archer activity belongs to a non-student user.');
                }

                if (app(FeatureFlagService::class)->enabled('archer_stars_enabled', $student)) {
                    ArcherRewardRule::query()
                        ->enabled()
                        ->where('event_type', $event->event_type)
                        ->orderBy('id')
                        ->get()
                        ->each(fn (ArcherRewardRule $rule) => $this->awardForRule($event, $student, $rule));
                }

                app(BadgeAwardService::class)->evaluate($event, $student);
                $followUps = array_merge(
                    app(MissionProgressService::class)->apply($event, $student),
                    app(MonthlyChallengeService::class)->apply($event, $student),
                );
                app(ArcherPassportService::class)->recordEvent($event, $student);

                $event->update([
                    'processed_at' => now()->utc(),
                    'failure_reason' => null,
                ]);

                return $followUps;
            }, 5);
        } catch (Throwable $exception) {
            ArcherActivityEvent::whereKey($eventId)->update([
                'failure_reason' => mb_substr($exception->getMessage(), 0, 2000),
            ]);

            throw $exception;
        }

        foreach ($followUps as $followUp) {
            $followUpEvent = app(ArcherJourneyEngine::class)->record(
                $followUp['event_type'],
                $followUp['student'],
                $followUp['source'],
                $followUp['context'],
                null,
                $followUp['occurred_at'],
                $followUp['event_timezone'],
                $followUp['event_key'],
            );

            $this->completeFollowUp($followUp, $followUpEvent);
        }
    }

    public function adjust(User $student, int $stars, User $actor, string $reason): ArcherStarTransaction
    {
        if ($stars === 0) {
            throw new RuntimeException('Adjustment must add or remove at least one Star.');
        }

        return DB::transaction(function () use ($student, $stars, $actor, $reason) {
            $occurredAt = now()->utc();
            $balance = $this->lockedBalance($student);
            $transaction = ArcherStarTransaction::create([
                'user_id' => $student->id,
                'transaction_type' => 'adjustment',
                'stars' => $stars,
                'actor_id' => $actor->id,
                'reason' => $reason,
                'occurred_at' => $occurredAt,
                'event_timezone' => $student->preferredTimezone(),
            ]);

            $balance->update(['lifetime_stars' => $balance->lifetime_stars + $stars]);
            $this->updatePeriodTotals($student, $occurredAt, $student->preferredTimezone(), $stars, 0);

            return $transaction;
        }, 5);
    }

    public function reverse(ArcherStarTransaction $original, User $actor, string $reason): ArcherStarTransaction
    {
        return DB::transaction(function () use ($original, $actor, $reason) {
            $original = ArcherStarTransaction::query()->lockForUpdate()->findOrFail($original->id);
            $existing = ArcherStarTransaction::where('reversal_of_id', $original->id)->first();
            if ($existing) {
                return $existing;
            }

            if ($original->transaction_type === 'reversal') {
                throw new RuntimeException('A reversal transaction cannot be reversed again.');
            }

            $student = User::findOrFail($original->user_id);
            $occurredAt = now()->utc();
            $balance = $this->lockedBalance($student);
            $transaction = ArcherStarTransaction::create([
                'user_id' => $student->id,
                'reward_rule_id' => $original->reward_rule_id,
                'transaction_type' => 'reversal',
                'stars' => -$original->stars,
                'source_type' => $original->source_type,
                'source_id' => $original->source_id,
                'actor_id' => $actor->id,
                'reversal_of_id' => $original->id,
                'reason' => $reason,
                'occurred_at' => $occurredAt,
                'event_timezone' => $student->preferredTimezone(),
                'metadata' => ['original_transaction_id' => $original->id],
            ]);

            $balance->update(['lifetime_stars' => $balance->lifetime_stars - $original->stars]);
            $this->updatePeriodTotals($student, $occurredAt, $student->preferredTimezone(), -$original->stars, 0);

            return $transaction;
        }, 5);
    }

    protected function awardForRule(ArcherActivityEvent $event, User $student, ArcherRewardRule $rule): void
    {
        if (ArcherStarTransaction::where('activity_event_id', $event->id)->where('reward_rule_id', $rule->id)->exists()) {
            return;
        }

        $occurredAt = Carbon::parse($event->getRawOriginal('occurred_at'), 'UTC');
        $timezone = $this->validTimezone($event->user_timezone) ?: $this->validTimezone($event->event_timezone) ?: 'UTC';
        $balance = $this->lockedBalance($student);

        if (! $this->withinLimits($event, $rule, $occurredAt, $timezone)) {
            return;
        }

        $stars = in_array($event->event_type, [
            ArcherJourneyEngine::MISSION_COMPLETED,
            ArcherJourneyEngine::MONTHLY_CHALLENGE_COMPLETED,
        ], true)
            ? (int) data_get($event->context, 'custom_stars', $rule->stars)
            : $rule->stars;

        if ($stars === 0) {
            return;
        }

        $oldStars = $balance->lifetime_stars;
        $transaction = ArcherStarTransaction::create([
            'user_id' => $student->id,
            'activity_event_id' => $event->id,
            'reward_rule_id' => $rule->id,
            'transaction_type' => 'award',
            'stars' => $stars,
            'source_type' => $event->source_type,
            'source_id' => $event->source_id,
            'actor_id' => $event->actor_id,
            'reason' => data_get($event->context, 'reason', $rule->name),
            'occurred_at' => $occurredAt,
            'event_timezone' => $event->event_timezone,
            'metadata' => ['event_type' => $event->event_type, 'rule_code' => $rule->code],
        ]);

        $newStars = $oldStars + $stars;
        $balance->update(['lifetime_stars' => $newStars]);
        $this->updateStreak($balance, $event, $occurredAt, $timezone);
        $this->updatePeriodTotals($student, $occurredAt, $timezone, $stars, 1);
        $this->recordHousePoints($student, $event, $transaction);
        $this->celebrateLevelUp($student, $oldStars, $newStars);
    }

    protected function withinLimits(
        ArcherActivityEvent $event,
        ArcherRewardRule $rule,
        Carbon $occurredAt,
        string $timezone,
    ): bool {
        $local = $occurredAt->copy()->timezone($timezone);
        $windows = [
            'daily_limit' => [$local->copy()->startOfDay()->utc(), $local->copy()->endOfDay()->utc()],
            'weekly_limit' => [$local->copy()->startOfWeek()->utc(), $local->copy()->endOfWeek()->utc()],
            'monthly_limit' => [$local->copy()->startOfMonth()->utc(), $local->copy()->endOfMonth()->utc()],
        ];

        foreach ($windows as $field => [$start, $end]) {
            $limit = $rule->{$field};
            if ($limit === null) {
                continue;
            }

            $count = ArcherStarTransaction::query()
                ->where('user_id', $event->user_id)
                ->where('reward_rule_id', $rule->id)
                ->where('transaction_type', 'award')
                ->whereBetween('occurred_at', [$start, $end])
                ->count();

            if ($count >= $limit) {
                return false;
            }
        }

        return true;
    }

    protected function lockedBalance(User $student): ArcherStarBalance
    {
        DB::table('archer_star_balances')->insertOrIgnore([
            'user_id' => $student->id,
            'lifetime_stars' => 0,
            'current_streak' => 0,
            'longest_streak' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return ArcherStarBalance::query()->lockForUpdate()->findOrFail($student->id);
    }

    protected function updateStreak(
        ArcherStarBalance $balance,
        ArcherActivityEvent $event,
        Carbon $occurredAt,
        string $timezone,
    ): void {
        if (! in_array($event->event_type, self::STREAK_EVENTS, true)) {
            return;
        }

        $activityDate = $occurredAt->copy()->timezone($timezone)->startOfDay();
        $lastDate = $balance->last_activity_on?->copy()->startOfDay();

        if ($lastDate && $activityDate->equalTo($lastDate)) {
            return;
        }

        $streak = $lastDate && $activityDate->diffInDays($lastDate) === 1
            ? $balance->current_streak + 1
            : 1;

        $balance->update([
            'current_streak' => $streak,
            'longest_streak' => max($balance->longest_streak, $streak),
            'last_activity_on' => $activityDate->toDateString(),
        ]);
    }

    protected function updatePeriodTotals(
        User $student,
        Carbon $occurredAt,
        string $timezone,
        int $stars,
        int $rewardCount,
    ): void {
        $local = $occurredAt->copy()->timezone($timezone);
        $periods = [
            'day' => $local->format('Y-m-d'),
            'week' => $local->format('o-\WW'),
            'month' => $local->format('Y-m'),
        ];

        foreach ($periods as $periodType => $periodKey) {
            $row = DB::table('archer_star_period_totals')
                ->where('user_id', $student->id)
                ->where('period_type', $periodType)
                ->where('period_key', $periodKey)
                ->lockForUpdate()
                ->first();

            if ($row) {
                DB::table('archer_star_period_totals')->where('id', $row->id)->update([
                    'stars' => $row->stars + $stars,
                    'reward_count' => $row->reward_count + $rewardCount,
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('archer_star_period_totals')->insert([
                    'user_id' => $student->id,
                    'period_type' => $periodType,
                    'period_key' => $periodKey,
                    'timezone' => $timezone,
                    'stars' => $stars,
                    'reward_count' => $rewardCount,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    protected function celebrateLevelUp(User $student, int $oldStars, int $newStars): void
    {
        if ($newStars <= $oldStars || ! app(FeatureFlagService::class)->enabled('archer_levels_enabled', $student)) {
            return;
        }

        $oldLevel = $this->levelForStars($oldStars);
        $newLevel = $this->levelForStars($newStars);
        if (! $newLevel || $oldLevel?->id === $newLevel->id) {
            return;
        }

        app(ArcherNotificationService::class)->create(
            $student,
            'level_up',
            '🎉 Level Up!',
            'You reached '.$newLevel->name.' Archer.',
            ['level_id' => $newLevel->id, 'icon' => $newLevel->icon]
        );
        app(ArcherPassportService::class)->recordMilestone(
            $student,
            'level:'.$newLevel->id.':student:'.$student->id,
            'level',
            $newLevel->name.' Archer',
            $newLevel->icon ?: '🏹',
            $newLevel->description,
            ['stars' => $newStars]
        );
    }

    protected function levelForStars(int $stars): ?ArcherLevel
    {
        return ArcherLevel::active()
            ->where('minimum_stars', '<=', $stars)
            ->orderByDesc('minimum_stars')
            ->first()
            ?? ArcherLevel::active()->orderBy('minimum_stars')->first();
    }

    protected function recordHousePoints(
        User $student,
        ArcherActivityEvent $event,
        ArcherStarTransaction $transaction,
    ): void {
        if (! app(FeatureFlagService::class)->enabled('archer_houses_enabled', $student)) {
            return;
        }

        $membership = DB::table('archer_house_memberships')->where('user_id', $student->id)->first();
        if (! $membership) {
            return;
        }

        DB::table('archer_house_transactions')->insert([
            'house_id' => $membership->house_id,
            'user_id' => $student->id,
            'activity_event_id' => $event->id,
            'points' => $transaction->stars,
            'reason' => $transaction->reason,
            'occurred_at' => $transaction->occurred_at,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function completeFollowUp(array $followUp, ArcherActivityEvent $event): void
    {
        $event->refresh();

        if (! $event->processed_at) {
            return;
        }

        $student = $followUp['student'];
        if ($followUp['event_type'] === ArcherJourneyEngine::MISSION_COMPLETED) {
            DB::table('archer_mission_progress')
                ->where('id', data_get($followUp, 'context.mission_progress_id'))
                ->update(['rewarded_at' => now()->utc(), 'updated_at' => now()]);
            app(ArcherNotificationService::class)->create(
                $student,
                'mission_completed',
                '🎯 Mission Complete!',
                data_get($followUp, 'context.title').' +'.data_get($followUp, 'context.custom_stars').' Archer Stars.',
                $followUp['context']
            );
        }

        if ($followUp['event_type'] === ArcherJourneyEngine::MONTHLY_CHALLENGE_COMPLETED) {
            DB::table('archer_monthly_challenge_progress')
                ->where('id', data_get($followUp, 'context.challenge_progress_id'))
                ->update(['rewarded_at' => now()->utc(), 'updated_at' => now()]);
            app(ArcherNotificationService::class)->create(
                $student,
                'monthly_challenge_completed',
                '🏹 Monthly Challenge Complete!',
                data_get($followUp, 'context.title').' +'.data_get($followUp, 'context.custom_stars').' Archer Stars.',
                $followUp['context']
            );
        }
    }

    protected function validTimezone(?string $timezone): ?string
    {
        return $timezone && in_array($timezone, timezone_identifiers_list(), true) ? $timezone : null;
    }
}
