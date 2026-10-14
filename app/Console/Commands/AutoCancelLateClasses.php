<?php

namespace App\Console\Commands;

use App\Models\Group;
use App\Models\LiveClass;
use App\Support\ClassScheduler;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Auto-cancel "no-show" classes.
 *
 *   php artisan classes:auto-cancel-late
 *
 * If a coach hasn't started a scheduled class within 30 minutes of its
 * scheduled_for time, we mark it cancelled. The chapter that was assigned
 * to the cancelled slot DOES NOT get burned — re-running
 * ClassScheduler::syncForGroup for each affected group refreshes future
 * scheduled rows so they still point at the group's current chapter.
 *
 * Designed to run every minute from the kernel schedule.
 */
class AutoCancelLateClasses extends Command
{
    /** Grace period (in minutes) after scheduled_for before we cancel. */
    public const GRACE_MINUTES = 30;

    protected $signature   = 'classes:auto-cancel-late {--dry-run : List candidates without writing anything}';
    protected $description = 'Mark scheduled classes as cancelled if the coach has not started them within 30 minutes, without advancing the group chapter sequence.';

    public function handle(): int
    {
        $now    = Carbon::now('UTC');
        $cutoff = $now->copy()->subMinutes(self::GRACE_MINUTES);

        // Scheduled classes whose start time was more than GRACE_MINUTES ago
        // and that the coach never moved into 'live'.
        $stale = LiveClass::where('status', 'scheduled')
            ->whereNotNull('scheduled_for')
            ->where('scheduled_for', '<=', $cutoff)
            ->orderBy('scheduled_for')
            ->get();

        if ($stale->isEmpty()) {
            $this->info('No late classes — nothing to cancel.');
            return self::SUCCESS;
        }

        $this->line("Found {$stale->count()} late class(es) past the {$cutoff->format('d M Y · g:i A')} cutoff.");

        if ($this->option('dry-run')) {
            foreach ($stale as $cls) {
                $this->line(sprintf(
                    '  · #%d %s — group %s — scheduled %s (%d min late)',
                    $cls->id,
                    $cls->name,
                    $cls->group_id ?? '—',
                    optional($cls->scheduled_for)->format('d M Y · g:i A'),
                    $cls->scheduled_for ? $cls->scheduled_for->diffInMinutes($now) : 0
                ));
            }
            $this->warn('Dry-run: no changes written.');
            return self::SUCCESS;
        }

        // Cancel in bulk-by-row so we can collect the affected groups, then
        // resync each group exactly once.
        $affectedGroupIds = [];
        foreach ($stale as $cls) {
            try {
                DB::transaction(function () use ($cls, $cutoff, $now, &$affectedGroupIds) {
                    $fresh = LiveClass::query()->lockForUpdate()->find($cls->id);
                    if (!$fresh || $fresh->status !== 'scheduled' || !$fresh->scheduled_for || $fresh->scheduled_for->gt($cutoff)) {
                        return;
                    }

                    $fresh->update(LiveClass::cancellationUpdatePayload($now));

                    $this->line("  · cancelled #{$fresh->id} ({$fresh->name})");

                    if ($fresh->group_id) {
                        $affectedGroupIds[$fresh->group_id] = true;
                    }
                });
            } catch (\Throwable $e) {
                Log::error('Failed to auto-cancel missed class.', [
                    'live_class_id' => $cls->id,
                    'group_id' => $cls->group_id,
                    'scheduled_for' => optional($cls->scheduled_for)->toIso8601String(),
                    'message' => $e->getMessage(),
                ]);
            }
        }

        // Re-sync each affected group so future scheduled rows continue to
        // point at the current chapter after the missed slot is cancelled.
        $groupIds = array_keys($affectedGroupIds);
        if (!empty($groupIds)) {
            $groups = Group::with(['coach', 'chapters', 'schedules'])
                ->whereIn('id', $groupIds)
                ->get();

            foreach ($groups as $group) {
                ClassScheduler::syncForGroup($group);
                $this->line("  · resynced chapter sequence for group '{$group->name}'");
            }
        }

        $this->info("Cancelled {$stale->count()} class(es) and resynced " . count($groupIds) . ' group(s).');
        return self::SUCCESS;
    }
}
