<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('student_analyses', function (Blueprint $table) {
            // Full study tree per game: { games: [ { name, startFen, root: <node> } ] }
            // Each node: { san, uci, fen, moveNumber, side, nag?, comment?, ai?, review?, children: [<node>...] }
            // children[0] is the main line at that point; children[1..] are variations.
            if (!Schema::hasColumn('student_analyses', 'tree')) {
                $table->json('tree')->nullable()->after('commentary');
            }
        });
    }

    public function down(): void
    {
        Schema::table('student_analyses', function (Blueprint $table) {
            if (Schema::hasColumn('student_analyses', 'tree')) {
                $table->dropColumn('tree');
            }
        });
    }
};
