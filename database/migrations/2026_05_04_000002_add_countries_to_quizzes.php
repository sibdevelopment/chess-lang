<?php

use Illuminate\Database\Migrations\Migration;

/*
 * Superseded — country-tagging the quiz bank was removed in favor of
 * per-country week tracking on quiz_sessions. See migration
 * 2026_05_04_000003_drop_countries_from_quizzes.php which removes
 * the column if it landed before this feature was reverted.
 */
return new class extends Migration {
    public function up(): void
    {
        // intentionally left blank — feature reverted
    }

    public function down(): void
    {
        // intentionally left blank
    }
};
