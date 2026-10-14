-- ──────────────────────────────────────────────────────────────────
-- Chess Academy — wipe all data, keep schema.
--
-- Run via phpMyAdmin:
--   1. Open phpMyAdmin → select the `chess_db` database
--   2. Click the SQL tab
--   3. Paste this whole file → Go
--
-- Or via mysql CLI:
--   /Applications/XAMPP/xamppfiles/bin/mysql -u root chess_db < database/wipe.sql
-- ──────────────────────────────────────────────────────────────────

USE `chess_db`;

SET FOREIGN_KEY_CHECKS = 0;

TRUNCATE TABLE `homework_submissions`;
TRUNCATE TABLE `homework`;
TRUNCATE TABLE `class_participants`;
TRUNCATE TABLE `live_classes`;
TRUNCATE TABLE `database_assignments`;
TRUNCATE TABLE `chapter_group`;
TRUNCATE TABLE `chapters`;
TRUNCATE TABLE `pgn_folders`;
TRUNCATE TABLE `group_student`;
TRUNCATE TABLE `groups`;
TRUNCATE TABLE `password_reset_tokens`;
TRUNCATE TABLE `sessions`;
TRUNCATE TABLE `cache`;
TRUNCATE TABLE `cache_locks`;
TRUNCATE TABLE `jobs`;
TRUNCATE TABLE `job_batches`;
TRUNCATE TABLE `failed_jobs`;
TRUNCATE TABLE `users`;

SET FOREIGN_KEY_CHECKS = 1;

-- After running this, create your first admin user via tinker:
--   /Applications/XAMPP/xamppfiles/bin/php artisan tinker
--   >>> \App\Models\User::create(['name'=>'Admin','username'=>'admin','password'=>\Hash::make('your-password'),'role'=>'admin','is_active'=>true]);
--   >>> exit
