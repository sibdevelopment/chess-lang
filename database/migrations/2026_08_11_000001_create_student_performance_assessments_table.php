<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_performance_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('assessor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assessed_at')->index();

            $table->unsignedTinyInteger('opening_knowledge')->default(0);
            $table->unsignedTinyInteger('tactical_awareness')->default(0);
            $table->unsignedTinyInteger('calculation')->default(0);
            $table->unsignedTinyInteger('strategy')->default(0);
            $table->unsignedTinyInteger('positional_understanding')->default(0);
            $table->unsignedTinyInteger('endgame_knowledge')->default(0);
            $table->unsignedTinyInteger('board_vision')->default(0);
            $table->unsignedTinyInteger('decision_making')->default(0);
            $table->unsignedTinyInteger('time_management')->default(0);
            $table->unsignedTinyInteger('game_analysis')->default(0);

            $table->unsignedSmallInteger('total_marks')->default(0);
            $table->decimal('percentage', 5, 2)->default(0);

            $table->text('strengths')->nullable();
            $table->text('areas_to_improve')->nullable();
            $table->text('coach_remarks')->nullable();
            $table->text('next_focus')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'assessed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_performance_assessments');
    }
};
