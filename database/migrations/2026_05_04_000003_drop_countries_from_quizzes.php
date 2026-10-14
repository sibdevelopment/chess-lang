<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Drop the `countries` column from `quizzes` if a previous run added it.
 * Quiz-bank country tagging was rolled back; per-country week sequencing
 * lives entirely on quiz_sessions.countries now.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasColumn('quizzes', 'countries')) {
            Schema::table('quizzes', function (Blueprint $table) {
                $table->dropColumn('countries');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasColumn('quizzes', 'countries')) {
            Schema::table('quizzes', function (Blueprint $table) {
                $table->json('countries')->nullable()->after('category');
            });
        }
    }
};
