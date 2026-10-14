<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        // A puzzle/question the coach broadcasts during a live class
        Schema::create('class_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('live_class_id')->constrained()->cascadeOnDelete();
            $table->foreignId('coach_id')->constrained('users');
            $table->text('position_fen');                 // starting puzzle FEN
            $table->json('expected_moves')->nullable();   // ['e4','e5','Nf3', ...] coach's solution line in coordinate form like "e2-e4"
            $table->json('arrows')->nullable();           // [{from:'e2',to:'e4',color:'green'}, ...]
            $table->json('highlights')->nullable();       // [{sq:'e4',color:'red'}, ...]
            $table->string('text', 500)->nullable();      // optional text prompt
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();
        });

        // Per-student attempt — tracks live FEN, played moves, score
        Schema::create('class_question_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_question_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('current_fen');
            $table->json('moves_played')->nullable();     // ['e2-e4', 'g1-f3', ...] every attempt incl wrong
            $table->unsignedInteger('correct_count')->default(0);
            $table->unsignedInteger('wrong_count')->default(0);
            $table->boolean('completed')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['class_question_id', 'user_id']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('class_question_attempts');
        Schema::dropIfExists('class_questions');
    }
};
