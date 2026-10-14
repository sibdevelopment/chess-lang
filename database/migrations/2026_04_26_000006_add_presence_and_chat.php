<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('class_participants', function (Blueprint $table) {
            $table->timestamp('last_seen_at')->nullable()->after('left_at');
        });

        Schema::create('class_chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('live_class_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('role', ['coach', 'student'])->index();
            $table->text('body');
            $table->timestamps();
            $table->index(['live_class_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_chat_messages');
        Schema::table('class_participants', function (Blueprint $table) {
            $table->dropColumn('last_seen_at');
        });
    }
};
