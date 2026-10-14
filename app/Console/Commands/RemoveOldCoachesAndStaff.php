<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class RemoveOldCoachesAndStaff extends Command
{
    /**
     * Usage:
     *   php artisan users:remove-old-coach-staff                 # delete coaches, staff & students whose username doesn't match the new naming convention
     *   php artisan users:remove-old-coach-staff --all           # delete EVERY coach, staff and student record (fresh start, sequences reset to 1)
     */
    protected $signature = 'users:remove-old-coach-staff {--all : Delete every coach, staff & student record, not just legacy-named ones}';

    protected $description = 'Remove old / legacy coach, staff and student accounts so the current coach, staff and ARCHERCHESS student sequences can start fresh.';

    public function handle(): int
    {
        $all = (bool) $this->option('all');

        $coachQ   = User::where('role', 'coach');
        $staffQ   = User::where('role', 'staff');
        $studentQ = User::where('role', 'student');

        if (! $all) {
            $coachQ->where('username', 'not like', 'archer_coach%');
            $staffQ->where('username', 'not like', 'archer_staff%');
            $studentQ->where('username', 'not like', 'ARCHERCHESS%');
        }

        $coaches  = $coachQ->get(['id', 'name', 'username']);
        $staff    = $staffQ->get(['id', 'name', 'username']);
        $students = $studentQ->get(['id', 'name', 'username']);

        if ($coaches->isEmpty() && $staff->isEmpty() && $students->isEmpty()) {
            $this->info('Nothing to remove — no matching coach, staff or student records found.');
            return self::SUCCESS;
        }

        $this->warn(($all ? 'ALL ' : 'LEGACY ') . 'coach + staff + student accounts that will be deleted:');
        foreach ($coaches as $u) {
            $this->line("  · COACH    #{$u->id}  {$u->name}  ({$u->username})");
        }
        foreach ($staff as $u) {
            $this->line("  · STAFF    #{$u->id}  {$u->name}  ({$u->username})");
        }
        foreach ($students as $u) {
            $this->line("  · STUDENT  #{$u->id}  {$u->name}  ({$u->username})");
        }

        if (! $this->confirm('Delete these records? This cannot be undone.', false)) {
            $this->info('Aborted. Nothing was deleted.');
            return self::SUCCESS;
        }

        $ids = array_merge(
            $coaches->pluck('id')->all(),
            $staff->pluck('id')->all(),
            $students->pluck('id')->all(),
        );

        $deleted = User::whereIn('id', $ids)->delete();

        $this->info("Deleted {$deleted} account(s).");
        $this->info('Next usernames: ARCHERCHESS01 (student), archer_coach1 (coach), archer_staff1 (staff).');

        return self::SUCCESS;
    }
}
