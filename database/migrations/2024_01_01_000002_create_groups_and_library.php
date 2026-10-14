<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        // Groups
        Schema::create('groups', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('level', ['beginner', 'intermediate', 'advanced']);
            $table->foreignId('coach_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users');
            $table->unsignedInteger('unlocked_chapter_index')->default(0);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Group <-> Student pivot
        Schema::create('group_student', function (Blueprint $table) {
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['group_id', 'user_id']);
        });

        // PGN Folders (supports subfolders)
        Schema::create('pgn_folders', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('level', ['beginner', 'intermediate', 'advanced']);
            $table->foreignId('parent_id')->nullable()->constrained('pgn_folders')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users');
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // Chapters (PGN files) — folders deprecated; we now key on level + sequence_number
        Schema::create('chapters', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->longText('pgn');
            $table->enum('level', ['beginner', 'intermediate', 'advanced'])->index();
            $table->unsignedInteger('sequence_number')->nullable()->unique();
            $table->foreignId('folder_id')->nullable()->constrained('pgn_folders')->nullOnDelete();
            $table->foreignId('uploaded_by')->constrained('users');
            $table->string('file_path')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->text('description')->nullable();
            $table->unsignedInteger('move_count')->default(0);
            $table->timestamps();
        });

        // Chapter assigned to groups
        Schema::create('chapter_group', function (Blueprint $table) {
            $table->foreignId('chapter_id')->constrained()->cascadeOnDelete();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sequence_order')->default(0);
            $table->primary(['chapter_id', 'group_id']);
        });

        // Database (coach-specific PGN library assignments)
        Schema::create('database_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('folder_id')->constrained('pgn_folders')->cascadeOnDelete();
            $table->foreignId('coach_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('assigned_by')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('database_assignments');
        Schema::dropIfExists('chapter_group');
        Schema::dropIfExists('chapters');
        Schema::dropIfExists('pgn_folders');
        Schema::dropIfExists('group_student');
        Schema::dropIfExists('groups');
    }
};
