-- ─────────────────────────────────────────────────────────────
-- Chess Academy: wipe all content, keep only the admin user.
-- Run from the project root with:
--   /Applications/XAMPP/xamppfiles/bin/mysql -u root chess_db < database/reset_to_admin_only.sql
-- ─────────────────────────────────────────────────────────────

SET FOREIGN_KEY_CHECKS = 0;

-- Homework + submissions
TRUNCATE TABLE homework_submissions;
TRUNCATE TABLE homework;

-- Live classes and participants
TRUNCATE TABLE class_participants;
TRUNCATE TABLE live_classes;

-- PGN library: folders, chapters, chapter-group pivot, coach assignments
TRUNCATE TABLE chapter_group;
TRUNCATE TABLE chapters;
TRUNCATE TABLE pgn_folders;
TRUNCATE TABLE database_assignments;

-- Groups and group membership
TRUNCATE TABLE group_student;
TRUNCATE TABLE `groups`;

-- Sessions (so stale sessions from deleted users don't linger)
TRUNCATE TABLE sessions;

-- Users: keep only the admin
DELETE FROM users WHERE username <> 'admin';

SET FOREIGN_KEY_CHECKS = 1;

-- Confirmation
SELECT id, name, username, role, is_active FROM users;
