<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class StudentSeeder extends Seeder
{
    private const TOTAL_STUDENTS = 3000;
    private const DEFAULT_PASSWORD = 'ARCHERCHESS';
    private const USERNAME_PREFIX = 'ARCHERCHESS';
    private const USERNAME_PAD_LENGTH = 2;

    public function run(): void
    {
        DB::transaction(function () {
            User::query()->where('role', 'student')->delete();

            $hashedPassword = Hash::make(self::DEFAULT_PASSWORD);
            $rows = [];
            $timestamp = now();

            for ($i = 1; $i <= self::TOTAL_STUDENTS; $i++) {
                $rows[] = [
                    'name' => fake()->unique()->name(),
                    'username' => self::USERNAME_PREFIX . str_pad((string) $i, self::USERNAME_PAD_LENGTH, '0', STR_PAD_LEFT),
                    'email' => null,
                    'email_verified_at' => null,
                    'password' => $hashedPassword,
                    'role' => 'student',
                    'avatar' => null,
                    'phone' => null,
                    'country' => null,
                    'timezone' => null,
                    'is_active' => false,
                    'last_seen' => null,
                    'remember_token' => Str::random(10),
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ];

                if (count($rows) === 500) {
                    User::insert($rows);
                    $rows = [];
                }
            }

            if ($rows !== []) {
                User::insert($rows);
            }
        });

        $this->command?->info('Seeded exactly 3,000 students in New ID status. Default password: ' . self::DEFAULT_PASSWORD);
    }
}
