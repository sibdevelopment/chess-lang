<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('erp_user_id')->nullable()->unique()->after('id');
            $table->unsignedBigInteger('erp_student_id')->nullable()->unique()->after('erp_user_id');
            $table->unsignedBigInteger('erp_coach_id')->nullable()->unique()->after('erp_student_id');
            $table->boolean('managed_by_erp')->default(false)->index()->after('erp_coach_id');
        });

        Schema::table('groups', function (Blueprint $table) {
            $table->unsignedBigInteger('erp_batch_id')->nullable()->unique()->after('id');
            $table->boolean('managed_by_erp')->default(false)->index()->after('erp_batch_id');
        });

        Schema::table('group_schedules', function (Blueprint $table) {
            $table->unsignedBigInteger('erp_batch_schedule_id')->nullable()->unique()->after('id');
            $table->boolean('managed_by_erp')->default(false)->index()->after('erp_batch_schedule_id');
        });
    }

    public function down(): void
    {
        Schema::table('group_schedules', function (Blueprint $table) {
            $table->dropUnique(['erp_batch_schedule_id']);
            $table->dropIndex(['managed_by_erp']);
            $table->dropColumn(['erp_batch_schedule_id', 'managed_by_erp']);
        });

        Schema::table('groups', function (Blueprint $table) {
            $table->dropUnique(['erp_batch_id']);
            $table->dropIndex(['managed_by_erp']);
            $table->dropColumn(['erp_batch_id', 'managed_by_erp']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['erp_user_id']);
            $table->dropUnique(['erp_student_id']);
            $table->dropUnique(['erp_coach_id']);
            $table->dropIndex(['managed_by_erp']);
            $table->dropColumn(['erp_user_id', 'erp_student_id', 'erp_coach_id', 'managed_by_erp']);
        });
    }
};
