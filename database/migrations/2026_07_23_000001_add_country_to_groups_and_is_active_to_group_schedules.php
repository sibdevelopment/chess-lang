<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            if (!Schema::hasColumn('groups', 'country')) {
                $table->string('country', 20)->nullable()->after('name');
            }
        });

        Schema::table('group_schedules', function (Blueprint $table) {
            if (!Schema::hasColumn('group_schedules', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('start_time');
            }
        });
    }

    public function down(): void
    {
        Schema::table('group_schedules', function (Blueprint $table) {
            if (Schema::hasColumn('group_schedules', 'is_active')) {
                $table->dropColumn('is_active');
            }
        });

        Schema::table('groups', function (Blueprint $table) {
            if (Schema::hasColumn('groups', 'country')) {
                $table->dropColumn('country');
            }
        });
    }
};
