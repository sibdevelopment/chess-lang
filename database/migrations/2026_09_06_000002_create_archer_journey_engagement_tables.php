<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('archer_badges', function (Blueprint $table) {
            $table->id();
            $table->string('code', 96)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('icon', 32)->nullable();
            $table->string('image_path')->nullable();
            $table->string('criteria_event_type', 96)->nullable()->index();
            $table->unsignedInteger('criteria_target')->default(1);
            $table->json('criteria')->nullable();
            $table->boolean('is_automatic')->default(true);
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('archer_badge_awards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('badge_id')->constrained('archer_badges')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('activity_event_id')->nullable()->constrained('archer_activity_events')->nullOnDelete();
            $table->foreignId('awarded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('earned_at')->index();
            $table->text('note')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['badge_id', 'user_id']);
            $table->index(['user_id', 'earned_at'], 'archer_badge_user_earned_idx');
        });

        Schema::create('archer_missions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('icon', 32)->nullable();
            $table->dateTime('starts_at')->index();
            $table->dateTime('ends_at')->index();
            $table->string('criteria_event_type', 96)->index();
            $table->unsignedInteger('target')->default(1);
            $table->integer('reward_stars')->default(0);
            $table->json('criteria')->nullable();
            $table->foreignId('applicable_level_id')->nullable()->constrained('archer_levels')->nullOnDelete();
            $table->string('applicable_country', 64)->nullable()->index();
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['is_active', 'starts_at', 'ends_at'], 'archer_missions_active_window_idx');
        });

        Schema::create('archer_mission_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mission_id')->constrained('archer_missions')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('progress')->default(0);
            $table->string('status', 24)->default('active')->index();
            $table->dateTime('completed_at')->nullable();
            $table->dateTime('rewarded_at')->nullable();
            $table->timestamps();

            $table->unique(['mission_id', 'user_id']);
            $table->index(['user_id', 'status'], 'archer_mission_user_status_idx');
        });

        Schema::create('archer_monthly_challenges', function (Blueprint $table) {
            $table->id();
            $table->string('code', 96)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('icon', 32)->nullable();
            $table->date('month_start')->index();
            $table->date('month_end')->index();
            $table->json('criteria');
            $table->integer('reward_stars')->default(0);
            $table->foreignId('badge_id')->nullable()->constrained('archer_badges')->nullOnDelete();
            $table->foreignId('applicable_level_id')->nullable()->constrained('archer_levels')->nullOnDelete();
            $table->string('applicable_country', 64)->nullable()->index();
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['is_active', 'month_start', 'month_end'], 'archer_challenges_active_window_idx');
        });

        Schema::create('archer_monthly_challenge_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('challenge_id')->constrained('archer_monthly_challenges')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->json('progress')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->dateTime('rewarded_at')->nullable();
            $table->timestamps();

            $table->unique(['challenge_id', 'user_id']);
        });

        Schema::create('archer_achievements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('achievement_type', 96)->default('sportsmanship')->index();
            $table->string('title');
            $table->text('note')->nullable();
            $table->foreignId('awarded_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('activity_event_id')->nullable()->constrained('archer_activity_events')->nullOnDelete();
            $table->dateTime('awarded_at')->index();
            $table->timestamps();
        });

        Schema::create('archer_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 64)->index();
            $table->string('title');
            $table->text('message');
            $table->json('data')->nullable();
            $table->dateTime('read_at')->nullable()->index();
            $table->timestamps();

            $table->index(['user_id', 'read_at', 'created_at'], 'archer_notifications_inbox_idx');
        });

        Schema::create('archer_notification_preferences', function (Blueprint $table) {
            $table->foreignId('user_id')->primary()->constrained('users')->cascadeOnDelete();
            $table->boolean('in_app_enabled')->default(true);
            $table->boolean('email_enabled')->default(false);
            $table->boolean('level_updates')->default(true);
            $table->boolean('badge_updates')->default(true);
            $table->boolean('mission_updates')->default(true);
            $table->boolean('tournament_updates')->default(true);
            $table->timestamps();
        });

        Schema::create('archer_passport_entries', function (Blueprint $table) {
            $table->id();
            $table->string('entry_key', 191)->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('activity_event_id')->nullable()->constrained('archer_activity_events')->nullOnDelete();
            $table->string('entry_type', 64)->index();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('icon', 32)->nullable();
            $table->string('source_type', 120)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->dateTime('occurred_at')->index();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'occurred_at'], 'archer_passport_user_occurred_idx');
        });

        Schema::create('archer_certificates', function (Blueprint $table) {
            $table->id();
            $table->string('certificate_key', 191)->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('file_path')->nullable();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('issued_at')->index();
            $table->timestamps();
        });

        Schema::create('archer_houses', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name');
            $table->string('icon', 32)->nullable();
            $table->string('color', 16)->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(false)->index();
            $table->timestamps();
        });

        Schema::create('archer_house_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('house_id')->constrained('archer_houses')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('joined_at');
            $table->timestamps();

            $table->unique('user_id');
            $table->index(['house_id', 'joined_at']);
        });

        Schema::create('archer_house_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('house_id')->constrained('archer_houses')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('activity_event_id')->nullable()->constrained('archer_activity_events')->nullOnDelete();
            $table->integer('points');
            $table->string('reason');
            $table->dateTime('occurred_at')->index();
            $table->timestamps();

            $table->index(['house_id', 'occurred_at'], 'archer_house_points_idx');
        });

        Schema::create('archer_discovery_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('student_name');
            $table->string('chess_experience')->nullable();
            $table->string('strength')->nullable();
            $table->string('focus')->nullable();
            $table->string('recommended_level')->nullable();
            $table->text('next_step')->nullable();
            $table->foreignId('assessed_by')->constrained('users')->cascadeOnDelete();
            $table->dateTime('assessed_at')->index();
            $table->timestamps();
        });

        Schema::create('archer_referrals', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->foreignId('referrer_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('referred_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 24)->default('pending')->index();
            $table->dateTime('completed_at')->nullable();
            $table->foreignId('activity_event_id')->nullable()->constrained('archer_activity_events')->nullOnDelete();
            $table->timestamps();

            $table->unique(['referrer_user_id', 'referred_user_id'], 'archer_referral_pair_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('archer_referrals');
        Schema::dropIfExists('archer_discovery_snapshots');
        Schema::dropIfExists('archer_house_transactions');
        Schema::dropIfExists('archer_house_memberships');
        Schema::dropIfExists('archer_houses');
        Schema::dropIfExists('archer_certificates');
        Schema::dropIfExists('archer_passport_entries');
        Schema::dropIfExists('archer_notification_preferences');
        Schema::dropIfExists('archer_notifications');
        Schema::dropIfExists('archer_achievements');
        Schema::dropIfExists('archer_monthly_challenge_progress');
        Schema::dropIfExists('archer_monthly_challenges');
        Schema::dropIfExists('archer_mission_progress');
        Schema::dropIfExists('archer_missions');
        Schema::dropIfExists('archer_badge_awards');
        Schema::dropIfExists('archer_badges');
    }
};
