<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // ── Tournaments themselves ─────────────────────────────────────
        Schema::create('tournaments', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('format', ['blitz', 'rapid', 'classical'])->index();
            // FIDE-style time control encoded as "5+0", "10+5", "60+30", etc.
            // Two integers separated by '+' (base minutes + increment seconds).
            $table->string('time_control', 16);
            $table->unsignedSmallInteger('rounds');
            // First round kicks off at scheduled_at (IST). Each round auto-
            // advances after round_minutes (computed: 2 × base + a 5-min buffer).
            $table->timestamp('scheduled_at')->index();
            $table->unsignedSmallInteger('round_minutes')->default(15);
            // Audience: which students are eligible to participate.
            // - all_students      : everyone with role=student
            // - all_groups        : legacy mode preserved for old rows
            // - specific_groups   : only students in tournament_groups pivot
            // - specific_students : only users in tournament_users pivot
            $table->enum('audience_type', ['all_students', 'all_groups', 'specific_groups', 'specific_students']);
            $table->enum('status', ['draft', 'upcoming', 'live', 'completed', 'cancelled', 'cancelled_insufficient_players'])->default('upcoming')->index();
            $table->unsignedSmallInteger('current_round')->default(0);
            $table->timestamp('round_started_at')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });

        // Specific groups (when audience_type = specific_groups).
        Schema::create('tournament_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->unique(['tournament_id', 'group_id']);
        });

        // Specific students (when audience_type = specific_students).
        Schema::create('tournament_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unique(['tournament_id', 'user_id']);
        });

        // The active roster — created when the tournament starts (one row per
        // eligible student), or appended as students join an in-progress event.
        Schema::create('tournament_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('score', 4, 1)->default(0);              // 1.0, 0.5, 0.0
            $table->decimal('buchholz', 6, 1)->default(0);            // tiebreak (sum of opponents' scores)
            $table->boolean('withdrawn')->default(false);
            $table->boolean('bye_received')->default(false);          // each player gets at most one bye
            $table->timestamps();
            $table->unique(['tournament_id', 'user_id']);
        });

        // Round pairings — one row per board, generated at the start of each
        // round by the Swiss pairing service.
        Schema::create('tournament_pairings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('round')->index();
            $table->unsignedSmallInteger('board');
            // white_user_id is null for a bye (black is the byed player); we
            // use bye flag to distinguish unambiguously.
            $table->foreignId('white_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('black_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_bye')->default(false);
            // Result: '1-0', '0-1', '0.5-0.5', or null while in progress.
            $table->string('result', 8)->nullable();
            $table->foreignId('winner_user_id')->nullable()->constrained('users')->nullOnDelete();
            // Live game state — same idea as live_classes.board_state.
            $table->string('current_fen', 100)->default('rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1');
            $table->unsignedSmallInteger('move_count')->default(0);
            $table->text('moves_pgn')->nullable();                    // optional running SAN string
            // Clocks in milliseconds. Decay is computed on the fly from
            // last_move_at; these columns store the snapshot at last move.
            $table->integer('white_clock_ms');
            $table->integer('black_clock_ms');
            $table->timestamp('last_move_at')->nullable();
            $table->enum('status', ['pending', 'active', 'completed', 'cancelled'])->default('pending')->index();
            // Resignation / draw offers
            $table->foreignId('resigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('draw_offer_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['tournament_id', 'round']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tournament_pairings');
        Schema::dropIfExists('tournament_participants');
        Schema::dropIfExists('tournament_users');
        Schema::dropIfExists('tournament_groups');
        Schema::dropIfExists('tournaments');
    }
};
