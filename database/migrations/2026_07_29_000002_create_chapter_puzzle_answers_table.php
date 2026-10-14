<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chapter_puzzle_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chapter_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('puzzle_index');
            $table->string('original_checksum', 64);
            $table->longText('answer_pgn');
            $table->string('final_fen')->nullable();
            $table->foreignId('saved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['chapter_id', 'puzzle_index']);
            $table->index(['chapter_id', 'puzzle_index', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chapter_puzzle_answers');
    }
};
