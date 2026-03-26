-- ============================================================
--  GSSC SCIENCE OFFICIAL  Database Schema
--  Import this file in phpMyAdmin to set up the database.
--  MySQL 5.7+ / MariaDB 10.4+
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

--  USERS 
CREATE TABLE IF NOT EXISTS `users` (
  `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `full_name`     VARCHAR(100) NOT NULL,
  `nickname`      VARCHAR(50)  DEFAULT NULL,
  `roll_no`       VARCHAR(30)  NOT NULL,
  `email`         VARCHAR(150) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `gender`        ENUM('male','female','other') NOT NULL DEFAULT 'other',
  `avatar`        VARCHAR(255) DEFAULT NULL,
  `role`          ENUM('student','moderator','admin') NOT NULL DEFAULT 'student',
  `is_active`     TINYINT(1) NOT NULL DEFAULT 1,
  `is_approved`   TINYINT(1) NOT NULL DEFAULT 0,
  `notif_enabled` TINYINT(1) NOT NULL DEFAULT 1,
  `sound_enabled` TINYINT(1) NOT NULL DEFAULT 1,
  `last_seen_at`  DATETIME DEFAULT NULL,
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_email`   (`email`),
  UNIQUE KEY `uq_roll_no` (`roll_no`),
  INDEX `idx_role`         (`role`),
  INDEX `idx_gender`       (`gender`),
  INDEX `idx_last_seen_at` (`last_seen_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--  INVITES 
CREATE TABLE IF NOT EXISTS `invites` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `email`       VARCHAR(150) NOT NULL,
  `token`       VARCHAR(64)  NOT NULL,
  `invited_by`  INT UNSIGNED NOT NULL,
  `used`        TINYINT(1) NOT NULL DEFAULT 0,
  `expires_at`  DATETIME NOT NULL,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_token` (`token`),
  INDEX `idx_email`      (`email`),
  CONSTRAINT `fk_invites_user` FOREIGN KEY (`invited_by`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--  PASSWORD RESETS 
CREATE TABLE IF NOT EXISTS `password_resets` (
  `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `email`      VARCHAR(150) NOT NULL,
  `token`      VARCHAR(64)  NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `used`       TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_token` (`token`),
  INDEX `idx_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--  POSTS (notice board  all types) 
CREATE TABLE IF NOT EXISTS `posts` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `post_type`   ENUM('announcement','event','poll') NOT NULL DEFAULT 'announcement',
  `title`       VARCHAR(200) NOT NULL,
  `body`        TEXT DEFAULT NULL,
  `image_path`  VARCHAR(255) DEFAULT NULL,
  `priority`    ENUM('urgent','info','general') NOT NULL DEFAULT 'general',
  `is_pinned`   TINYINT(1) NOT NULL DEFAULT 0,
  `is_published`TINYINT(1) NOT NULL DEFAULT 1,
  `posted_by`   INT UNSIGNED NOT NULL,
  `event_date`  DATE DEFAULT NULL,
  `event_time`  TIME DEFAULT NULL,
  `event_type`  ENUM('exam','submission','holiday','class','other') DEFAULT NULL,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_post_type`  (`post_type`),
  INDEX `idx_is_pinned`  (`is_pinned`),
  INDEX `idx_priority`   (`priority`),
  INDEX `idx_created_at` (`created_at`),
  CONSTRAINT `fk_posts_user` FOREIGN KEY (`posted_by`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--  POST READS 
CREATE TABLE IF NOT EXISTS `post_reads` (
  `id`        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `post_id`   INT UNSIGNED NOT NULL,
  `user_id`   INT UNSIGNED NOT NULL,
  `read_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_post_user` (`post_id`,`user_id`),
  CONSTRAINT `fk_reads_post` FOREIGN KEY (`post_id`) REFERENCES `posts`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_reads_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--  POLLS 
CREATE TABLE IF NOT EXISTS `polls` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `post_id`     INT UNSIGNED NOT NULL,
  `is_anonymous`TINYINT(1) NOT NULL DEFAULT 0,
  `ends_at`     DATETIME DEFAULT NULL,
  `is_closed`   TINYINT(1) NOT NULL DEFAULT 0,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_post_id` (`post_id`),
  CONSTRAINT `fk_polls_post` FOREIGN KEY (`post_id`) REFERENCES `posts`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `poll_options` (
  `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `poll_id`       INT UNSIGNED NOT NULL,
  `option_text`   VARCHAR(200) NOT NULL,
  `display_order` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  INDEX `idx_poll_id` (`poll_id`),
  CONSTRAINT `fk_options_poll` FOREIGN KEY (`poll_id`) REFERENCES `polls`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `poll_votes` (
  `id`        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `poll_id`   INT UNSIGNED NOT NULL,
  `option_id` INT UNSIGNED NOT NULL,
  `user_id`   INT UNSIGNED NOT NULL,
  `voted_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_poll_user` (`poll_id`,`user_id`),
  CONSTRAINT `fk_votes_poll`   FOREIGN KEY (`poll_id`)   REFERENCES `polls`(`id`)        ON DELETE CASCADE,
  CONSTRAINT `fk_votes_option` FOREIGN KEY (`option_id`) REFERENCES `poll_options`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_votes_user`   FOREIGN KEY (`user_id`)   REFERENCES `users`(`id`)        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--  MESSAGES 
CREATE TABLE IF NOT EXISTS `messages` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id`     INT UNSIGNED NOT NULL,
  `body`        TEXT DEFAULT NULL,
  `type`        ENUM('text','image','file') NOT NULL DEFAULT 'text',
  `file_path`   VARCHAR(255) DEFAULT NULL,
  `file_name`   VARCHAR(255) DEFAULT NULL,
  `reply_to_id` INT UNSIGNED DEFAULT NULL,
  `is_deleted`  TINYINT(1) NOT NULL DEFAULT 0,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_created_at` (`created_at`),
  INDEX `idx_user_id`    (`user_id`),
  INDEX `idx_is_deleted` (`is_deleted`),
  CONSTRAINT `fk_msg_user`  FOREIGN KEY (`user_id`)     REFERENCES `users`(`id`)    ON DELETE CASCADE,
  CONSTRAINT `fk_msg_reply` FOREIGN KEY (`reply_to_id`) REFERENCES `messages`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--  MESSAGE REACTIONS 
CREATE TABLE IF NOT EXISTS `message_reactions` (
  `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `message_id` INT UNSIGNED NOT NULL,
  `user_id`    INT UNSIGNED NOT NULL,
  `emoji`      VARCHAR(10)  NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_msg_user_emoji` (`message_id`,`user_id`,`emoji`),
  CONSTRAINT `fk_react_msg`  FOREIGN KEY (`message_id`) REFERENCES `messages`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_react_user` FOREIGN KEY (`user_id`)    REFERENCES `users`(`id`)    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--  STORAGE FILES 
CREATE TABLE IF NOT EXISTS `storage_files` (
  `id`             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `title`          VARCHAR(200) NOT NULL,
  `description`    TEXT DEFAULT NULL,
  `file_path`      VARCHAR(255) NOT NULL,
  `file_name`      VARCHAR(255) NOT NULL,
  `file_type`      VARCHAR(20)  NOT NULL,
  `file_size`      INT UNSIGNED NOT NULL DEFAULT 0,
  `category`       ENUM('notes','syllabus','assignment','slides','result','other') NOT NULL DEFAULT 'other',
  `uploaded_by`    INT UNSIGNED NOT NULL,
  `is_approved`    TINYINT(1) NOT NULL DEFAULT 0,
  `download_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_category`   (`category`),
  INDEX `idx_is_approved`(`is_approved`),
  INDEX `idx_created_at` (`created_at`),
  CONSTRAINT `fk_storage_user` FOREIGN KEY (`uploaded_by`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--  SITE SETTINGS 
CREATE TABLE IF NOT EXISTS `site_settings` (
  `key`        VARCHAR(100) NOT NULL PRIMARY KEY,
  `value`      TEXT NOT NULL,
  `updated_by` INT UNSIGNED DEFAULT NULL,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_settings_user` FOREIGN KEY (`updated_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `site_settings` (`key`, `value`) VALUES
  ('chat_enabled',              '1'),
  ('registration_mode',         'invite'),
  ('storage_approval_required', '1'),
  ('site_name',                 'GSSC-science official'),
  ('college_name',              'Govt. Shaheed Suhrawardy College'),
  ('department',                'Science'),
  ('about_us',                  'The official platform for the Science Department of Govt. Shaheed Suhrawardy College (GSSC). Built to connect students, share notices, and collaborate academically.'),
  ('rules',                     '1. Be respectful to all members.\n2. No spam or irrelevant content.\n3. Only share academic materials in Storage.\n4. Follow college academic guidelines.\n5. Violations may result in account removal.')
ON DUPLICATE KEY UPDATE `key` = `key`;

--  ACTIVITY LOG 
CREATE TABLE IF NOT EXISTS `activity_log` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id`     INT UNSIGNED DEFAULT NULL,
  `action`      VARCHAR(100) NOT NULL,
  `target_type` VARCHAR(50)  DEFAULT NULL,
  `target_id`   INT UNSIGNED DEFAULT NULL,
  `meta`        JSON DEFAULT NULL,
  `ip_address`  VARCHAR(45)  DEFAULT NULL,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_user_id`    (`user_id`),
  INDEX `idx_action`     (`action`),
  INDEX `idx_created_at` (`created_at`),
  CONSTRAINT `fk_log_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--  RATE LIMITS 
CREATE TABLE IF NOT EXISTS `rate_limits` (
  `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `identifier`   VARCHAR(100) NOT NULL,
  `action`       VARCHAR(50)  NOT NULL,
  `attempts`     SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  `window_start` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_id_action` (`identifier`,`action`),
  INDEX `idx_window_start` (`window_start`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--  PERSISTENT LOGINS (Remember Me)
CREATE TABLE IF NOT EXISTS `persistent_logins` (
  `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id`       INT UNSIGNED NOT NULL,
  `token_hash`    VARCHAR(64)  NOT NULL,
  `expires_at`    DATETIME NOT NULL,
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_token_hash` (`token_hash`),
  INDEX `idx_expires_at` (`expires_at`),
  CONSTRAINT `fk_persist_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
--  SEED: First admin user
--  After importing, run this with your real details.
--  Generate hash at: https://bcrypt-generator.com (cost 12)
--  OR run: php -r "echo password_hash('YourPassword', PASSWORD_BCRYPT, ['cost'=>12]);"
-- ============================================================
-- INSERT INTO `users` (full_name, nickname, roll_no, email, password_hash, gender, role, is_active, is_approved)
-- VALUES ('Admin Name', 'Admin', '0000', 'admin@youremail.com', 'PASTE_HASH_HERE', 'male', 'admin', 1, 1);
