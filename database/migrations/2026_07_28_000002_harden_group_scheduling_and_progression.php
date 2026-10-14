<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            if (!Schema::hasColumn('groups', 'timezone')) {
                $table->string('timezone', 64)->nullable()->after('country');
            }
            if (!Schema::hasColumn('groups', 'start_date')) {
                $table->date('start_date')->nullable()->after('timezone');
            }
            if (!Schema::hasColumn('groups', 'end_date')) {
                $table->date('end_date')->nullable()->after('start_date');
            }
        });

        Schema::table('group_schedules', function (Blueprint $table) {
            if (!Schema::hasColumn('group_schedules', 'duration_minutes')) {
                $table->unsignedInteger('duration_minutes')->default(60)->after('start_time');
            }
        });

        DB::table('groups')->whereNull('timezone')->update([
            'timezone' => config('app.timezone'),
        ]);

        Schema::create('group_chapter_completions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('chapter_id')->constrained()->cascadeOnDelete();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('live_class_id')->nullable()->constrained('live_classes')->nullOnDelete();
            $table->timestamp('completed_at');
            $table->timestamps();

            $table->unique(['group_id', 'chapter_id']);
            $table->index(['group_id', 'completed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_chapter_completions');

        Schema::table('group_schedules', function (Blueprint $table) {
            if (Schema::hasColumn('group_schedules', 'duration_minutes')) {
                $table->dropColumn('duration_minutes');
            }
        });

        Schema::table('groups', function (Blueprint $table) {
            if (Schema::hasColumn('groups', 'end_date')) {
                $table->dropColumn('end_date');
            }
            if (Schema::hasColumn('groups', 'start_date')) {
                $table->dropColumn('start_date');
            }
            if (Schema::hasColumn('groups', 'timezone')) {
                $table->dropColumn('timezone');
            }
        });
    }
};
