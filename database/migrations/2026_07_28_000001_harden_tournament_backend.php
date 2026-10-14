<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            if (!Schema::hasColumn('tournaments', 'minimum_players')) {
                $table->unsignedSmallInteger('minimum_players')->default(4)->after('rounds');
            }
            if (!Schema::hasColumn('tournaments', 'tournament_timezone')) {
                $table->string('tournament_timezone', 64)->nullable()->after('scheduled_at');
            }
            if (!Schema::hasColumn('tournaments', 'cancellation_reason')) {
                $table->string('cancellation_reason')->nullable()->after('status');
            }
            if (!Schema::hasColumn('tournaments', 'completed_at')) {
                $table->timestamp('completed_at')->nullable()->after('next_round_at');
            }
        });

        Schema::table('tournament_pairings', function (Blueprint $table) {
            if (!Schema::hasColumn('tournament_pairings', 'completed_at')) {
                $table->timestamp('completed_at')->nullable()->after('last_move_at');
            }
        });

        DB::table('tournaments')->where('status', 'scheduled')->update(['status' => 'upcoming']);
        DB::table('tournaments')->where('status', 'in_progress')->update(['status' => 'live']);
        DB::table('tournaments')->whereNull('minimum_players')->update(['minimum_players' => 4]);

        $driver = DB::getDriverName();
        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE tournaments MODIFY status ENUM('draft','upcoming','live','completed','cancelled','cancelled_insufficient_players') NOT NULL DEFAULT 'upcoming'");
            DB::statement("ALTER TABLE tournament_pairings MODIFY status ENUM('pending','active','completed','cancelled') NOT NULL DEFAULT 'pending'");
        }

        try {
            Schema::table('tournaments', function (Blueprint $table) {
                $table->index(['status', 'scheduled_at'], 'tournaments_status_scheduled_at_idx');
            });
        } catch (\Throwable $e) {
            // Index already exists or cannot be created safely on this platform.
        }

        try {
            Schema::table('tournament_pairings', function (Blueprint $table) {
                $table->unique(['tournament_id', 'round', 'board'], 'tournament_pairings_round_board_unique');
            });
        } catch (\Throwable $e) {
            // Existing production data may already contain duplicates.
        }
    }

    public function down(): void
    {
        try {
            Schema::table('tournament_pairings', function (Blueprint $table) {
                $table->dropUnique('tournament_pairings_round_board_unique');
            });
        } catch (\Throwable $e) {
        }

        try {
            Schema::table('tournaments', function (Blueprint $table) {
                $table->dropIndex('tournaments_status_scheduled_at_idx');
            });
        } catch (\Throwable $e) {
        }

        Schema::table('tournament_pairings', function (Blueprint $table) {
            if (Schema::hasColumn('tournament_pairings', 'completed_at')) {
                $table->dropColumn('completed_at');
            }
        });

        Schema::table('tournaments', function (Blueprint $table) {
            if (Schema::hasColumn('tournaments', 'completed_at')) {
                $table->dropColumn('completed_at');
            }
            if (Schema::hasColumn('tournaments', 'cancellation_reason')) {
                $table->dropColumn('cancellation_reason');
            }
            if (Schema::hasColumn('tournaments', 'tournament_timezone')) {
                $table->dropColumn('tournament_timezone');
            }
            if (Schema::hasColumn('tournaments', 'minimum_players')) {
                $table->dropColumn('minimum_players');
            }
        });

        DB::table('tournaments')->where('status', 'upcoming')->update(['status' => 'scheduled']);
        DB::table('tournaments')->where('status', 'live')->update(['status' => 'in_progress']);

        $driver = DB::getDriverName();
        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE tournaments MODIFY status ENUM('scheduled','in_progress','completed','cancelled') NOT NULL DEFAULT 'scheduled'");
            DB::statement("ALTER TABLE tournament_pairings MODIFY status ENUM('pending','active','completed') NOT NULL DEFAULT 'pending'");
        }
    }
};
