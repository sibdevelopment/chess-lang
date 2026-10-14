<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quiz system schema.
 *
 *   - quizzes:                  the bank of questions, grouped by week + category.
 *   - quiz_sessions:            a scheduled (and then live) classroom-style quiz
 *                               run with a coach + level-targeted students.
 *   - quiz_session_attendees:   roster of students who clicked Join on a session.
 *   - quiz_session_responses:   one row per (session, quiz, student) — the pick.
 *   - quiz_session_quizzes:     the frozen ordered list of quiz IDs the coach
 *                               chose for this session (i.e. the "week 1 set").
 */
return new class extends Migration {
    public function up(): void
    {
        // ── 1. Quiz bank ───────────────────────────────────────────────
        if (!Schema::hasTable('quizzes')) {
            Schema::create('quizzes', function (Blueprint $table) {
                $table->id();
                $table->unsignedSmallInteger('week')->index();
                $table->string('category', 40)->index();         // GK / Maths / Chess / Fun / Other (free-form, validated in controller)
                $table->enum('type', ['text', 'picture'])->default('text');     // is the QUESTION text or a picture
                $table->text('question_text')->nullable();        // for text type, the question
                $table->string('image_path')->nullable();         // for picture type, path under storage/app/public

                // Options can be text OR image, per quiz (admin chooses).
                // We store a JSON array of 4 entries, each either:
                //   { "kind": "text",  "value": "64" }
                //   { "kind": "image", "value": "quizzes/options/abc.png" }
                $table->json('options');                          // exactly 4 entries, see above
                $table->unsignedTinyInteger('correct_index');     // 0..3
                $table->unsignedSmallInteger('time_limit_seconds')->default(120);

                $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
                $table->timestamps();
            });
        }

        // ── 2. Quiz sessions ───────────────────────────────────────────
        if (!Schema::hasTable('quiz_sessions')) {
            Schema::create('quiz_sessions', function (Blueprint $table) {
                $table->id();
                $table->string('name', 120);
                $table->foreignId('coach_id')->constrained('users')->cascadeOnDelete();
                $table->enum('level', ['beginner', 'intermediate', 'advanced'])->index();
                $table->dateTime('scheduled_at')->index();
                $table->enum('status', ['scheduled', 'live', 'ended', 'cancelled'])->default('scheduled')->index();

                // Which "week" did the coach pick when starting? Frozen at start.
                $table->unsignedSmallInteger('week')->nullable();

                // Live-broadcast cursor: which quiz is currently on screen?
                $table->foreignId('current_quiz_id')->nullable()->constrained('quizzes')->nullOnDelete();
                $table->dateTime('current_quiz_started_at')->nullable();
                $table->dateTime('started_at')->nullable();
                $table->dateTime('ended_at')->nullable();

                $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
                $table->timestamps();
            });
        }

        // ── 3. Frozen quiz set per session ─────────────────────────────
        // When coach picks "Week 1", we snapshot the quiz IDs into this table
        // in order, so the broadcast walks them sequentially even if the bank
        // changes mid-session.
        if (!Schema::hasTable('quiz_session_quizzes')) {
            Schema::create('quiz_session_quizzes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('quiz_session_id')->constrained()->cascadeOnDelete();
                $table->foreignId('quiz_id')->constrained()->cascadeOnDelete();
                $table->unsignedSmallInteger('position');        // 1..N
                $table->boolean('shown')->default(false);
                $table->timestamps();
                $table->unique(['quiz_session_id', 'position']);
                $table->unique(['quiz_session_id', 'quiz_id']);
            });
        }

        // ── 4. Attendees (students who joined live) ────────────────────
        if (!Schema::hasTable('quiz_session_attendees')) {
            Schema::create('quiz_session_attendees', function (Blueprint $table) {
                $table->id();
                $table->foreignId('quiz_session_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->dateTime('joined_at')->useCurrent();
                $table->timestamps();
                $table->unique(['quiz_session_id', 'user_id']);
            });
        }

        // ── 5. Per-question response per student ───────────────────────
        if (!Schema::hasTable('quiz_session_responses')) {
            Schema::create('quiz_session_responses', function (Blueprint $table) {
                $table->id();
                $table->foreignId('quiz_session_id')->constrained()->cascadeOnDelete();
                $table->foreignId('quiz_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->unsignedTinyInteger('picked_index');       // 0..3
                $table->boolean('is_correct');
                $table->dateTime('picked_at')->useCurrent();
                $table->timestamps();
                // Each student answers each quiz at most once per session.
                $table->unique(['quiz_session_id', 'quiz_id', 'user_id'], 'qsr_unique_pick');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_session_responses');
        Schema::dropIfExists('quiz_session_attendees');
        Schema::dropIfExists('quiz_session_quizzes');
        Schema::dropIfExists('quiz_sessions');
        Schema::dropIfExists('quizzes');
    }
};
