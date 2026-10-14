<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Two new columns:
     *   - next_round_at: when the NEXT round will begin pairing. Powers the
     *     2-minute break between rounds and the live countdown banner.
     *   - pairing_window_minutes: how many minutes BEFORE scheduled_at we
     *     pre-build pairings for round 1 (default 3, per spec).
     */
    public function up(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            if (!Schema::hasColumn('tournaments', 'next_round_at')) {
                $table->timestamp('next_round_at')->nullable()->after('round_started_at');
            }
            if (!Schema::hasColumn('tournaments', 'pairing_window_minutes')) {
                $table->unsignedSmallInteger('pairing_window_minutes')->default(3)->after('round_minutes');
            }
            if (!Schema::hasColumn('tournaments', 'round_break_minutes')) {
                $table->unsignedSmallInteger('round_break_minutes')->default(2)->after('pairing_window_minutes');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            if (Schema::hasColumn('tournaments', 'next_round_at'))           $table->dropColumn('next_round_at');
            if (Schema::hasColumn('tournaments', 'pairing_window_minutes'))  $table->dropColumn('pairing_window_minutes');
            if (Schema::hasColumn('tournaments', 'round_break_minutes'))     $table->dropColumn('round_break_minutes');
        });
    }
};
