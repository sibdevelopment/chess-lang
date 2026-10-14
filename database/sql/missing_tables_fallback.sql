-- Fallback SQL — apply if `php artisan migrate` is unavailable.
-- Run this in phpMyAdmin against the `chess_db` database.
-- These statements match migrations 0006, 0007, and 0008 exactly.

-- ── 1) Presence column on class_participants (skip if already present) ──
SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'class_participants'
      AND COLUMN_NAME = 'last_seen_at'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE `class_participants` ADD COLUMN `last_seen_at` TIMESTAMP NULL AFTER `left_at`',
    'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ── 2) Chat messages table ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `class_chat_messages` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `live_class_id` BIGINT UNSIGNED NOT NULL,
    `user_id`       BIGINT UNSIGNED NOT NULL,
    `role`          ENUM('coach','student') NOT NULL,
    `body`          TEXT NOT NULL,
    `created_at`    TIMESTAMP NULL,
    `updated_at`    TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    KEY `class_chat_messages_role_index` (`role`),
    KEY `class_chat_messages_live_class_id_created_at_index` (`live_class_id`, `created_at`),
    CONSTRAINT `class_chat_messages_live_class_id_foreign`
        FOREIGN KEY (`live_class_id`) REFERENCES `live_classes` (`id`) ON DELETE CASCADE,
    CONSTRAINT `class_chat_messages_user_id_foreign`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 3) Student analyses (saved studies) ───────────────────────────────
CREATE TABLE IF NOT EXISTS `student_analyses` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`    BIGINT UNSIGNED NOT NULL,
    `name`       VARCHAR(200) NOT NULL,
    `pgn`        LONGTEXT NOT NULL,
    `reviews`    JSON NULL,
    `commentary` JSON NULL,
    `tree`       JSON NULL,
    `opened_at`  TIMESTAMP NULL,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    KEY `student_analyses_user_id_updated_at_index` (`user_id`, `updated_at`),
    CONSTRAINT `student_analyses_user_id_foreign`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 3b) `tree` column — added by migration 0008 (idempotent) ──────────
SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'student_analyses'
      AND COLUMN_NAME = 'tree'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE `student_analyses` ADD COLUMN `tree` JSON NULL AFTER `commentary`',
    'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ── 4) Tell Laravel these migrations are applied (so artisan migrate
--      doesn't try to run them again later) ────────────────────────────
INSERT IGNORE INTO `migrations` (`migration`, `batch`)
SELECT '2026_04_26_000006_add_presence_and_chat',
       COALESCE((SELECT MAX(batch) FROM (SELECT batch FROM migrations) m), 0) + 1
WHERE NOT EXISTS (
    SELECT 1 FROM (SELECT migration FROM migrations) m
    WHERE m.migration = '2026_04_26_000006_add_presence_and_chat'
);

INSERT IGNORE INTO `migrations` (`migration`, `batch`)
SELECT '2026_04_26_000007_create_student_analyses',
       COALESCE((SELECT MAX(batch) FROM (SELECT batch FROM migrations) m), 0) + 1
WHERE NOT EXISTS (
    SELECT 1 FROM (SELECT migration FROM migrations) m
    WHERE m.migration = '2026_04_26_000007_create_student_analyses'
);

INSERT IGNORE INTO `migrations` (`migration`, `batch`)
SELECT '2026_04_26_000008_add_tree_to_student_analyses',
       COALESCE((SELECT MAX(batch) FROM (SELECT batch FROM migrations) m), 0) + 1
WHERE NOT EXISTS (
    SELECT 1 FROM (SELECT migration FROM migrations) m
    WHERE m.migration = '2026_04_26_000008_add_tree_to_student_analyses'
);
