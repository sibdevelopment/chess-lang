<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feature_flags', function (Blueprint $table) {
            $table->string('key', 96)->primary();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_enabled')->default(false)->index();
            $table->unsignedTinyInteger('rollout_percentage')->default(100);
            $table->json('config')->nullable();
            $table->timestamps();
        });

        Schema::create('archer_levels', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedInteger('minimum_stars')->default(0)->index();
            $table->unsignedInteger('minimum_rating')->nullable();
            $table->string('icon', 32)->nullable();
            $table->string('image_path')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('archer_reward_rules', function (Blueprint $table) {
            $table->id();
            $table->string('code', 96)->unique();
            $table->string('event_type', 96)->index();
            $table->string('name');
            $table->text('description')->nullable();
            $table->integer('stars')->default(0);
            $table->string('frequency', 32)->default('once_per_source');
            $table->unsignedInteger('daily_limit')->nullable();
            $table->unsignedInteger('weekly_limit')->nullable();
            $table->unsignedInteger('monthly_limit')->nullable();
            $table->json('criteria')->nullable();
            $table->boolean('is_enabled')->default(true)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['event_type', 'is_enabled'], 'archer_rules_event_enabled_idx');
        });

        Schema::create('archer_activity_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_key', 191)->unique();
            $table->string('event_type', 96)->index();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('source_type', 120)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('occurred_at')->index();
            $table->string('user_timezone', 64)->default('UTC');
            $table->string('event_timezone', 64)->default('UTC');
            $table->json('context')->nullable();
            $table->timestamp('processed_at')->nullable()->index();
            $table->unsignedSmallInteger('processing_attempts')->default(0);
            $table->text('failure_reason')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'occurred_at'], 'archer_events_user_occurred_idx');
            $table->index(['source_type', 'source_id'], 'archer_events_source_idx');
        });

        Schema::create('archer_star_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('activity_event_id')->nullable()->constrained('archer_activity_events')->nullOnDelete();
            $table->foreignId('reward_rule_id')->nullable()->constrained('archer_reward_rules')->nullOnDelete();
            $table->string('transaction_type', 32)->default('award')->index();
            $table->integer('stars');
            $table->string('source_type', 120)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reversal_of_id')->nullable()->constrained('archer_star_transactions')->nullOnDelete();
            $table->string('reason');
            $table->timestamp('occurred_at')->index();
            $table->string('event_timezone', 64)->default('UTC');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['activity_event_id', 'reward_rule_id'], 'archer_stars_event_rule_unique');
            $table->index(['user_id', 'occurred_at'], 'archer_stars_user_occurred_idx');
            $table->index(['user_id', 'reward_rule_id', 'occurred_at'], 'archer_stars_limit_lookup_idx');
            $table->index(['source_type', 'source_id'], 'archer_stars_source_idx');
        });

        Schema::create('archer_star_balances', function (Blueprint $table) {
            $table->foreignId('user_id')->primary()->constrained('users')->cascadeOnDelete();
            $table->integer('lifetime_stars')->default(0)->index();
            $table->unsignedInteger('current_streak')->default(0);
            $table->unsignedInteger('longest_streak')->default(0);
            $table->date('last_activity_on')->nullable();
            $table->timestamps();
        });

        Schema::create('archer_star_period_totals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('period_type', 16);
            $table->string('period_key', 16);
            $table->string('timezone', 64)->default('UTC');
            $table->integer('stars')->default(0);
            $table->unsignedInteger('reward_count')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'period_type', 'period_key'], 'archer_period_user_type_key_unique');
            $table->index(['period_type', 'period_key', 'stars'], 'archer_period_leaderboard_idx');
        });

        Schema::create('student_chess_ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('rating_type', 16);
            $table->integer('initial_rating')->default(1400);
            $table->integer('rating')->default(1400)->index();
            $table->integer('peak_rating')->default(1400);
            $table->unsignedInteger('games_played')->default(0);
            $table->boolean('is_provisional')->default(true)->index();
            $table->boolean('ever_reached_2400')->default(false);
            $table->string('source', 32)->default('lms');
            $table->timestamp('last_rated_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'rating_type']);
            $table->index(['rating_type', 'rating'], 'student_ratings_type_rating_idx');
        });

        Schema::create('student_chess_rating_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('tournament_id')->constrained('tournaments')->cascadeOnDelete();
            $table->string('rating_type', 16);
            $table->integer('rating_before');
            $table->integer('rating_change');
            $table->integer('rating_after');
            $table->unsignedSmallInteger('k_factor');
            $table->unsignedSmallInteger('games_rated');
            $table->decimal('score', 6, 2)->default(0);
            $table->decimal('expected_score', 8, 4)->default(0);
            $table->timestamp('calculated_at')->index();
            $table->json('calculation')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'tournament_id', 'rating_type'], 'student_rating_period_unique');
            $table->index(['user_id', 'rating_type', 'calculated_at'], 'student_rating_history_idx');
        });

        Schema::create('student_chess_rating_games', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rating_period_id')->constrained('student_chess_rating_periods')->cascadeOnDelete();
            $table->foreignId('pairing_id')->constrained('tournament_pairings')->cascadeOnDelete();
            $table->foreignId('opponent_id')->constrained('users')->cascadeOnDelete();
            $table->integer('opponent_rating');
            $table->decimal('score', 3, 1);
            $table->decimal('expected_score', 6, 4);
            $table->decimal('raw_delta', 8, 4);
            $table->timestamps();

            $table->unique(['rating_period_id', 'pairing_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_chess_rating_games');
        Schema::dropIfExists('student_chess_rating_periods');
        Schema::dropIfExists('student_chess_ratings');
        Schema::dropIfExists('archer_star_period_totals');
        Schema::dropIfExists('archer_star_balances');
        Schema::dropIfExists('archer_star_transactions');
        Schema::dropIfExists('archer_activity_events');
        Schema::dropIfExists('archer_reward_rules');
        Schema::dropIfExists('archer_levels');
        Schema::dropIfExists('feature_flags');
    }
};
