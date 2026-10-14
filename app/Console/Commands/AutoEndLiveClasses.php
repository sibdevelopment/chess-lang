<?php

namespace App\Console\Commands;

use App\Models\Group;
use App\Models\LiveClass;
use App\Services\ArcherJourney\ArcherLmsEventBridge;
use App\Support\ClassScheduler;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AutoEndLiveClasses extends Command
{
    protected $signature = 'classes:auto-end-live {--dry-run : List candidates without writing changes}';

    protected $description = 'Automatically complete live classes at the 60-minute end time without advancing the chapter.';

    public function handle(): int
    {
        $now = Carbon::now('UTC');

        $due = LiveClass::query()
            ->where('status', 'live')
            ->get()
            ->filter(function (LiveClass $class) use ($now) {
                return ! $class->actualEnd() && $class->scheduledEnd() && $class->scheduledEnd()->lessThanOrEqualTo($now);
            })
            ->sortBy(fn (LiveClass $class) => optional($class->scheduledEnd())->timestamp ?? PHP_INT_MAX)
            ->values();

        if ($due->isEmpty()) {
            $this->info('No live classes require automatic ending.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            foreach ($due as $class) {
                $this->line(sprintf(
                    '  · #%d %s — group %s — scheduled end %s',
                    $class->id,
                    $class->name,
                    $class->group_id ?? '—',
                    optional($class->scheduledEnd())->format('d M Y · g:i A')
                ));
            }

            $this->warn('Dry-run: no changes written.');

            return self::SUCCESS;
        }

        $affectedGroupIds = [];

        foreach ($due as $class) {
            try {
                DB::transaction(function () use ($class, &$affectedGroupIds) {
                    /** @var LiveClass|null $fresh */
                    $fresh = LiveClass::query()->lockForUpdate()->find($class->id);

                    if (! $fresh || $fresh->status !== 'live' || $fresh->actualEnd()) {
                        return;
                    }

                    $scheduledEnd = $fresh->scheduledEnd() ?? now()->utc();
                    $fresh->update([
                        'status' => 'completed',
                        'ended_at' => $scheduledEnd,
                        'actual_end_at' => $scheduledEnd,
                        'duration_minutes' => 60,
                        'completion_type' => 'automatic',
                        'chapter_completed' => false,
                    ]);

                    if ($fresh->group_id) {
                        $affectedGroupIds[$fresh->group_id] = true;
                    }

                    $this->line("  · auto-ended #{$fresh->id} ({$fresh->name})");
                });

                $completed = LiveClass::find($class->id);
                if ($completed?->isCompleted()) {
                    app(ArcherLmsEventBridge::class)->classCompleted($completed);
                }
            } catch (\Throwable $e) {
                Log::error('Failed to auto-end live class.', [
                    'live_class_id' => $class->id,
                    'group_id' => $class->group_id,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        $groupIds = array_keys($affectedGroupIds);
        if (! empty($groupIds)) {
            Group::with(['coach', 'chapters', 'schedules', 'scheduleSlots'])
                ->whereIn('id', $groupIds)
                ->get()
                ->each(fn (Group $group) => ClassScheduler::syncForGroup($group));
        }

        $this->info('Automatic class end complete.');

        return self::SUCCESS;
    }
}
