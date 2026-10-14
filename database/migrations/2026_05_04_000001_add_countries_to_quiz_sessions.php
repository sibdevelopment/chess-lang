<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Multi-country targeting for quiz sessions.
 *
 *   - countries: JSON array of short region codes (e.g. ["IND","USA","UK"])
 *                whose students may join this session.
 *                NULL or [] => open to all regions (legacy behaviour).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('quiz_sessions', function (Blueprint $table) {
            if (!Schema::hasColumn('quiz_sessions', 'countries')) {
                $table->json('countries')->nullable()->after('level');
            }
        });
    }

    public function down(): void
    {
        Schema::table('quiz_sessions', function (Blueprint $table) {
            if (Schema::hasColumn('quiz_sessions', 'countries')) {
                $table->dropColumn('countries');
            }
        });
    }
};
