<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('student_analyses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 200);
            $table->longText('pgn');
            $table->json('reviews')->nullable();      // { "<gameIdx>-<moveIdx>": { class, drop, evalBefore, evalAfter, bestSan, pv } }
            $table->json('commentary')->nullable();   // { "<gameIdx>-<moveIdx>": "AI text" }
            $table->timestamp('opened_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_analyses');
    }
};
