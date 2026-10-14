<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('live_classes', function (Blueprint $table) {
            if (!Schema::hasColumn('live_classes', 'scheduled_start_at')) {
                $table->timestamp('scheduled_start_at')->nullable()->after('scheduled_for')->index();
            }
            if (!Schema::hasColumn('live_classes', 'scheduled_end_at')) {
                $table->timestamp('scheduled_end_at')->nullable()->after('scheduled_start_at')->index();
            }
            if (!Schema::hasColumn('live_classes', 'actual_start_at')) {
                $table->timestamp('actual_start_at')->nullable()->after('started_at');
            }
            if (!Schema::hasColumn('live_classes', 'actual_end_at')) {
                $table->timestamp('actual_end_at')->nullable()->after('ended_at');
            }
            if (!Schema::hasColumn('live_classes', 'duration_minutes')) {
                $table->unsignedInteger('duration_minutes')->nullable()->after('actual_end_at');
            }
            if (!Schema::hasColumn('live_classes', 'completion_type')) {
                $table->string('completion_type', 20)->nullable()->after('duration_minutes');
            }
            if (!Schema::hasColumn('live_classes', 'chapter_completed')) {
                $table->boolean('chapter_completed')->default(false)->after('completion_type');
            }
        });

        DB::table('live_classes')
            ->whereNull('scheduled_start_at')
            ->update(['scheduled_start_at' => DB::raw('scheduled_for')]);

        DB::table('live_classes')
            ->select('id', 'scheduled_for')
            ->whereNull('scheduled_end_at')
            ->whereNotNull('scheduled_for')
            ->orderBy('id')
            ->get()
            ->each(function ($class) {
                DB::table('live_classes')
                    ->where('id', $class->id)
                    ->update([
                        'scheduled_end_at' => Carbon::parse($class->scheduled_for, 'UTC')
                            ->addMinutes(60)
                            ->format('Y-m-d H:i:s'),
                    ]);
            });

        DB::table('live_classes')
            ->whereNull('actual_start_at')
            ->whereNotNull('started_at')
            ->update(['actual_start_at' => DB::raw('started_at')]);

        DB::table('live_classes')
            ->whereNull('actual_end_at')
            ->whereNotNull('ended_at')
            ->update(['actual_end_at' => DB::raw('ended_at')]);

        DB::table('live_classes')
            ->whereNull('duration_minutes')
            ->update(['duration_minutes' => 60]);

        DB::table('live_classes')
            ->whereIn('status', ['ended', 'completed'])
            ->whereNull('completion_type')
            ->update(['completion_type' => 'manual']);

        DB::table('live_classes')
            ->where('status', 'cancelled')
            ->whereNull('completion_type')
            ->update(['completion_type' => 'cancelled']);

        DB::table('live_classes')
            ->whereNull('chapter_completed')
            ->update(['chapter_completed' => false]);

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement(
                "ALTER TABLE live_classes MODIFY COLUMN status ENUM('scheduled','live','ended','completed','cancelled') NOT NULL DEFAULT 'scheduled'"
            );
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement(
                "ALTER TABLE live_classes MODIFY COLUMN status ENUM('scheduled','live','ended','cancelled') NOT NULL DEFAULT 'scheduled'"
            );
        }

        Schema::table('live_classes', function (Blueprint $table) {
            foreach ([
                'scheduled_start_at',
                'scheduled_end_at',
                'actual_start_at',
                'actual_end_at',
                'duration_minutes',
                'completion_type',
                'chapter_completed',
            ] as $column) {
                if (Schema::hasColumn('live_classes', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
