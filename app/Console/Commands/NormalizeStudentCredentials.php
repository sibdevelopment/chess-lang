<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class NormalizeStudentCredentials extends Command
{
    private const USERNAME_PREFIX = 'ARCHERCHESS';
    private const USERNAME_PAD_LENGTH = 2;
    private const DEFAULT_PASSWORD = 'ARCHERCHESS';

    protected $signature = 'students:normalize-credentials {--dry-run : Show planned usernames without saving} {--force : Apply without confirmation}';
    protected $description = 'Rename all existing students to the ARCHERCHESS01 sequence and reset their password to ARCHERCHESS.';

    public function handle(): int
    {
        $students = User::query()
            ->where('role', 'student')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get(['id', 'name', 'username', 'is_active', 'created_at']);

        if ($students->isEmpty()) {
            $this->info('No student accounts found.');
            return self::SUCCESS;
        }

        $planned = $students->values()->map(function (User $student, int $index) {
            return [
                'id' => $student->id,
                'name' => $student->name,
                'old_username' => $student->username,
                'new_username' => self::USERNAME_PREFIX . str_pad((string) ($index + 1), self::USERNAME_PAD_LENGTH, '0', STR_PAD_LEFT),
            ];
        });

        $this->info('Student credential normalization plan:');
        foreach ($planned->take(15) as $row) {
            $this->line(sprintf(
                '  #%d %s: %s -> %s',
                $row['id'],
                $row['name'],
                $row['old_username'],
                $row['new_username'],
            ));
        }
        if ($planned->count() > 15) {
            $this->line('  ...');
            $this->line(sprintf('  Total students to update: %d', $planned->count()));
        }

        if ($this->option('dry-run')) {
            $this->warn('Dry run only. No student credentials were changed.');
            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm('Update ALL student usernames and reset ALL student passwords to ARCHERCHESS?', false)) {
            $this->info('Aborted. Nothing was changed.');
            return self::SUCCESS;
        }

        DB::transaction(function () use ($planned) {
            $hashedPassword = Hash::make(self::DEFAULT_PASSWORD);

            foreach ($planned as $row) {
                User::whereKey($row['id'])->update([
                    'username' => $row['new_username'],
                    'password' => $hashedPassword,
                ]);
            }
        });

        $this->info(sprintf(
            'Updated %d student account(s). New default password: %s',
            $planned->count(),
            self::DEFAULT_PASSWORD
        ));

        return self::SUCCESS;
    }
}
