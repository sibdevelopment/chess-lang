<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One-shot reset for the academy.
 *
 * Snapshots every row in `users` with role=admin (you almost always only have
 * one — yours — but if multiple exist they all survive), drops every table,
 * re-runs migrations from zero, then re-inserts the admin rows verbatim so
 * you can log in with the same username / password as before.
 *
 * Everything else — students, coaches, staff, groups, classes, tournaments,
 * pairings, scores, quizzes, quiz sessions, attendance, chats, analyses,
 * homework, highlights — is gone.
 *
 *   php artisan academy:reset
 *   php artisan academy:reset --force        (skip the confirmation)
 *
 * Designed to be safe to run on production-ish data: if the snapshot step
 * fails (e.g. no admins exist), the wipe is NOT attempted.
 */
class AcademyReset extends Command
{
    protected $signature   = 'academy:reset {--force : Skip the are-you-sure prompt}';
    protected $description = 'Wipe ALL academy data, keep only existing admin user(s).';

    public function handle(): int
    {
        // ── 1. Snapshot admins ────────────────────────────────────────────
        if (!Schema::hasTable('users')) {
            $this->error('users table is missing — nothing to snapshot. Run `php artisan migrate` first.');
            return self::FAILURE;
        }

        $admins = DB::table('users')->where('role', 'admin')->get();
        if ($admins->isEmpty()) {
            $this->error('No admin users found in the current database. Aborting so you do not lock yourself out.');
            $this->line('Tip: seed one with `php artisan migrate:fresh --seeder=Database\\Seeders\\AdminOnlySeeder` then re-run this command later.');
            return self::FAILURE;
        }

        $this->info(sprintf('Found %d admin user(s) to preserve:', $admins->count()));
        foreach ($admins as $a) {
            $this->line(sprintf('  • %s  (username: %s, email: %s)', $a->name, $a->username, $a->email));
        }

        // ── 2. Confirm (unless --force) ───────────────────────────────────
        if (!$this->option('force')) {
            $this->newLine();
            $this->warn('This will DROP every table in the database and re-run migrations from zero.');
            $this->warn('Students, coaches, staff, groups, classes, tournaments, quizzes — ALL gone.');
            if (!$this->confirm('Continue?', false)) {
                $this->line('Aborted. Nothing was changed.');
                return self::SUCCESS;
            }
        }

        // ── 3. Wipe + migrate ─────────────────────────────────────────────
        $this->newLine();
        $this->info('Dropping all tables and re-running migrations…');
        Artisan::call('migrate:fresh', ['--force' => true], $this->getOutput());

        // ── 4. Restore admin row(s) ───────────────────────────────────────
        $this->newLine();
        $this->info('Restoring admin user(s)…');
        $cols = Schema::getColumnListing('users');
        foreach ($admins as $a) {
            $row = collect((array) $a)
                ->only($cols)        // only columns that survived the migration
                ->all();
            // The fresh users table will auto-increment IDs from 1 again;
            // keeping the original id preserves any FK references (none should
            // exist after wipe, but it also keeps your login URL stable).
            DB::table('users')->insert($row);
            $this->line(sprintf('  ✓ %s restored', $a->username));
        }

        $this->newLine();
        $this->info('✅ Academy reset complete. Log in with your existing admin credentials.');
        return self::SUCCESS;
    }
}
