<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        // Live Classes
        Schema::create('live_classes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('coach_id')->constrained('users');
            $table->foreignId('group_id')->nullable()->constrained('groups')->nullOnDelete();
            $table->foreignId('chapter_id')->constrained('chapters');
            $table->unsignedInteger('chapter_index')->default(0);
            $table->enum('status', ['scheduled', 'live', 'ended'])->default('scheduled')->index();
            $table->string('room_id')->unique();
            $table->unsignedInteger('current_move_index')->default(0);
            $table->text('board_state')->nullable();
            $table->text('arrows')->nullable();
            $table->unsignedInteger('students_joined')->default(0);
            $table->timestamp('scheduled_for')->nullable()->index();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();
        });

        // Students who joined a class
        Schema::create('class_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('live_class_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('joined_at');
            $table->timestamp('left_at')->nullable();
            $table->unique(['live_class_id', 'user_id']);
        });

        // Homework
        Schema::create('homework', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->foreignId('chapter_id')->constrained('chapters');
            $table->foreignId('group_id')->nullable()->constrained('groups')->nullOnDelete();
            $table->foreignId('assigned_by')->constrained('users');
            $table->foreignId('source_class_id')->nullable()->constrained('live_classes')->nullOnDelete();
            $table->boolean('auto_assigned')->default(false);
            $table->date('due_date')->nullable();
            $table->timestamps();
        });

        // Homework completions per student
        Schema::create('homework_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('homework_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('status', ['pending', 'in_progress', 'done'])->default('pending');
            $table->text('moves_played')->nullable();
            $table->string('result')->nullable();
            $table->boolean('marked_correct')->nullable();
            $table->text('coach_note')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
            $table->unique(['homework_id', 'user_id']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('homework_submissions');
        Schema::dropIfExists('homework');
        Schema::dropIfExists('class_participants');
        Schema::dropIfExists('live_classes');
    }
};
