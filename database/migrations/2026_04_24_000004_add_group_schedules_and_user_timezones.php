<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('country')->nullable()->after('phone');
            $table->string('timezone')->nullable()->after('country');
        });

        Schema::create('group_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week');
            $table->time('start_time');
            $table->timestamps();
        });

        Schema::table('live_classes', function (Blueprint $table) {
            $table->foreignId('group_schedule_id')->nullable()->after('group_id')->constrained('group_schedules')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('live_classes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('group_schedule_id');
        });

        Schema::dropIfExists('group_schedules');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['country', 'timezone']);
        });
    }
};
