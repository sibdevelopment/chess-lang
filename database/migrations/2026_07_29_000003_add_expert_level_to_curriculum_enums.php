<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE `groups` MODIFY level ENUM('beginner','intermediate','advanced','expert') NOT NULL");
        DB::statement("ALTER TABLE chapters MODIFY level ENUM('beginner','intermediate','advanced','expert') NOT NULL");
        DB::statement("ALTER TABLE pgn_folders MODIFY level ENUM('beginner','intermediate','advanced','expert') NOT NULL");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("UPDATE `groups` SET level = 'advanced' WHERE level = 'expert'");
        DB::statement("UPDATE chapters SET level = 'advanced' WHERE level = 'expert'");
        DB::statement("UPDATE pgn_folders SET level = 'advanced' WHERE level = 'expert'");

        DB::statement("ALTER TABLE `groups` MODIFY level ENUM('beginner','intermediate','advanced') NOT NULL");
        DB::statement("ALTER TABLE chapters MODIFY level ENUM('beginner','intermediate','advanced') NOT NULL");
        DB::statement("ALTER TABLE pgn_folders MODIFY level ENUM('beginner','intermediate','advanced') NOT NULL");
    }
};
