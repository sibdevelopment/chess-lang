<?php

namespace App\Console\Commands;

use App\Models\Group;
use App\Support\ClassScheduler;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GenerateClassSchedule extends Command
{
    protected $signature = 'classes:generate-schedule {--group-id=* : Limit generation to one or more groups}';
    protected $description = 'Generate or reconcile recurring scheduled classes for active groups.';

    public function handle(): int
    {
        try {
            $groupIds = collect((array) $this->option('group-id'))
                ->filter(fn ($value) => $value !== null && $value !== '')
                ->map(fn ($value) => (int) $value)
                ->values();

            $groups = Group::query()
                ->with(['coach', 'chapters', 'schedules', 'scheduleSlots'])
                ->when($groupIds->isNotEmpty(), fn ($query) => $query->whereIn('id', $groupIds))
                ->orderBy('id')
                ->get();

            if ($groups->isEmpty()) {
                $this->warn('No groups matched the generation criteria.');
                return self::SUCCESS;
            }

            $generated = 0;
            $cancelled = 0;
            $updated = 0;

            foreach ($groups as $group) {
                $summary = ClassScheduler::syncForGroup($group);
                $generated += $summary['created'];
                $cancelled += $summary['cancelled'];
                $updated += $summary['updated'];

                $this->line(sprintf(
                    '#%d %s — created %d, updated %d, cancelled %d',
                    $group->id,
                    $group->name,
                    $summary['created'],
                    $summary['updated'],
                    $summary['cancelled']
                ));
            }

            $this->info("Schedule generation complete. Created {$generated}, updated {$updated}, cancelled {$cancelled}.");

            return self::SUCCESS;
        } catch (\Throwable $e) {
            Log::error('classes:generate-schedule failed', [
                'message' => $e->getMessage(),
                'exception' => $e,
            ]);

            $this->error('Schedule generation failed. See the application log for details.');

            return self::FAILURE;
        }
    }
}
