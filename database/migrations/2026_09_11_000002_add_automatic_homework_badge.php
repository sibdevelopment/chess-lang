<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('archer_badges')->updateOrInsert(
            ['code' => 'homework_hero'],
            [
                'name' => 'Homework Hero',
                'description' => 'Complete the first assigned Archer homework.',
                'icon' => '📘',
                'criteria_event_type' => 'HOMEWORK_COMPLETED',
                'criteria_target' => 1,
                'criteria' => json_encode([]),
                'is_automatic' => true,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );
    }

    public function down(): void
    {
        DB::table('archer_badges')->where('code', 'homework_hero')->delete();
    }
};
