<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('feature_flags')
            ->where('key', 'archer_discovery_enabled')
            ->update([
                'is_enabled' => false,
                'updated_at' => $now,
            ]);

        foreach ([
            [
                'code' => 'tournament_checkmate',
                'event_type' => 'TOURNAMENT_CHECKMATE',
                'name' => 'Tournament checkmate',
                'stars' => 5,
                'daily_limit' => 3,
                'weekly_limit' => 10,
                'monthly_limit' => 30,
            ],
            [
                'code' => 'rating_improved',
                'event_type' => 'RATING_IMPROVED',
                'name' => 'Tournament rating improved',
                'stars' => 5,
                'daily_limit' => null,
                'weekly_limit' => null,
                'monthly_limit' => null,
            ],
        ] as $rule) {
            DB::table('archer_reward_rules')->updateOrInsert(
                ['code' => $rule['code']],
                [
                    'event_type' => $rule['event_type'],
                    'name' => $rule['name'],
                    'description' => 'Automatically awarded from verified LMS activity.',
                    'stars' => $rule['stars'],
                    'frequency' => 'once_per_source',
                    'daily_limit' => $rule['daily_limit'],
                    'weekly_limit' => $rule['weekly_limit'],
                    'monthly_limit' => $rule['monthly_limit'],
                    'criteria' => json_encode([]),
                    'is_enabled' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }

    public function down(): void
    {
        DB::table('archer_reward_rules')
            ->whereIn('code', ['tournament_checkmate', 'rating_improved'])
            ->delete();

        DB::table('feature_flags')
            ->where('key', 'archer_discovery_enabled')
            ->update([
                'is_enabled' => true,
                'updated_at' => now(),
            ]);
    }
};
