<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('puzzle_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('puzzle_sets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('level', ['beginner', 'intermediate', 'advanced', 'expert'])->index();
            $table->foreignId('category_id')->nullable()->constrained('puzzle_categories')->nullOnDelete();
            $table->string('category_name');
            $table->longText('source_pgn');
            $table->string('source_file_name')->nullable();
            $table->string('source_file_path')->nullable();
            $table->unsignedInteger('total_puzzles')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });

        Schema::create('puzzles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('puzzle_set_id')->constrained('puzzle_sets')->cascadeOnDelete();
            $table->unsignedInteger('puzzle_order');
            $table->longText('source_pgn');
            $table->text('original_fen');
            $table->char('side_to_move', 1)->default('w');
            $table->longText('answer_pgn');
            $table->json('main_line')->nullable();
            $table->json('solution_uci')->nullable();
            $table->text('final_fen')->nullable();
            $table->longText('comments')->nullable();
            $table->timestamps();

            $table->unique(['puzzle_set_id', 'puzzle_order']);
            $table->index(['puzzle_set_id', 'puzzle_order']);
        });

        Schema::create('student_puzzle_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('puzzle_id')->constrained('puzzles')->cascadeOnDelete();
            $table->enum('status', ['not_started', 'in_progress', 'completed'])->default('not_started')->index();
            $table->json('student_moves')->nullable();
            $table->unsignedInteger('current_ply_index')->default(0);
            $table->unsignedInteger('wrong_attempts')->default(0);
            $table->unsignedTinyInteger('hint_count')->default(0);
            $table->unsignedTinyInteger('current_hint_level')->default(0);
            $table->boolean('hint_used')->default(false);
            $table->text('current_fen')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'puzzle_id']);
            $table->index(['user_id', 'status']);
        });

        $now = now();
        $categories = [
            'Checkmate',
            'Tactics',
            'Fork',
            'Pin',
            'Skewer',
            'Discovery',
            'Deflection',
            'Attraction',
            'Removing the Defender',
            'Opening',
            'Endgame',
            'Calculation',
            'Mixed',
        ];

        DB::table('puzzle_categories')->insert(
            collect($categories)->map(fn (string $name) => [
                'name' => $name,
                'slug' => \Illuminate\Support\Str::slug($name),
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all()
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('student_puzzle_attempts');
        Schema::dropIfExists('puzzles');
        Schema::dropIfExists('puzzle_sets');
        Schema::dropIfExists('puzzle_categories');
    }
};
