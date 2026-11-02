<?php

namespace App\Console\Commands;

use App\Services\ErpSyncService;
use Illuminate\Console\Command;

class SyncErpData extends Command
{
    protected $signature = 'erp:sync
        {--dry-run : Execute the complete sync and roll back all destination changes}
        {--users-only : Synchronize students and coaches without batches}
        {--no-passwords : Do not import ERP portal passwords}';

    protected $description = 'Synchronize ArcherKids ERP users, batches, schedules, and memberships into Chesslang';

    public function handle(ErpSyncService $service): int
    {
        if (! config('erp-sync.enabled')) {
            $this->error('ERP synchronization is disabled. Set ERP_SYNC_ENABLED=true.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $syncPasswords = config('erp-sync.sync_passwords') && ! $this->option('no-passwords');

        $this->info($dryRun ? 'Running ERP synchronization dry run...' : 'Synchronizing ERP data...');

        $report = $service->sync($dryRun, (bool) $this->option('users-only'), $syncPasswords);
        $this->table(['Item', 'Count'], collect($report)
            ->reject(fn ($value) => is_array($value))
            ->map(fn ($value, $key) => [str_replace('_', ' ', $key), $value])
            ->values()
            ->all());

        foreach ($report['conflicts'] as $conflict) {
            $this->warn("Conflict: {$conflict}");
        }
        foreach ($report['missing_passwords'] as $missing) {
            $this->warn("Missing portal password: {$missing}");
        }

        if ($dryRun) {
            $this->comment('Dry run complete. All Chesslang database changes were rolled back.');
        } else {
            $this->info('ERP synchronization complete.');
        }

        return $report['conflicts'] === [] ? self::SUCCESS : self::INVALID;
    }
}
