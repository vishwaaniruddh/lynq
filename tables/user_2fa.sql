-- Database Table Export for `user_2fa`
-- Generated: 2026-06-25 07:00:57
-- DB Name: if0_40845939_clarity_db

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `user_2fa`;

CREATE TABLE `user_2fa` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `secret_key` varchar(255) DEFAULT NULL,
  `is_enabled` tinyint(1) DEFAULT 0,
  `backup_codes` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`backup_codes`)),
  `last_used_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- No data found in `user_2fa`

SET FOREIGN_KEY_CHECKS = 1;
