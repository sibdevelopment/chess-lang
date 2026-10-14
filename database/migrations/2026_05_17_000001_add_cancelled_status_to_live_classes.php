<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

/**
 * Widen live_classes.status to allow 'cancelled' and add a nullable
 * cancelled_at timestamp so we can distinguish a no-show class from a
 * normally-ended one. Auto-cancellation happens when a coach hasn't
 * started a class within 10 minutes of its scheduled_for time.
 */
return new class extends Migration {
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            // MySQL enum widening — use raw SQL since Blueprint doesn't have a
            // first-class "change enum values" helper that works without DBAL.
            DB::statement(
                "ALTER TABLE live_classes MODIFY COLUMN status " .
                "ENUM('scheduled','live','ended','cancelled') NOT NULL DEFAULT 'scheduled'"
            );
        }

        if (!Schema::hasColumn('live_classes', 'cancelled_at')) {
            Schema::table('live_classes', function (Blueprint $table) {
                $table->timestamp('cancelled_at')->nullable()->after('ended_at');
            });
        }
    }

    public function down(): void
    {
        // First, normalise any cancelled rows so the narrower enum accepts them.
        DB::statement("UPDATE live_classes SET status = 'ended' WHERE status = 'cancelled'");

        if (DB::getDriverName() === 'mysql') {
            DB::statement(
                "ALTER TABLE live_classes MODIFY COLUMN status " .
                "ENUM('scheduled','live','ended') NOT NULL DEFAULT 'scheduled'"
            );
        }

        if (Schema::hasColumn('live_classes', 'cancelled_at')) {
            Schema::table('live_classes', function (Blueprint $table) {
                $table->dropColumn('cancelled_at');
            });
        }
    }
};
