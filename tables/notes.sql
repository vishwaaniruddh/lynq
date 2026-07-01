-- Database Table Export for `notes`
-- Generated: 2026-06-25 07:00:57
-- DB Name: if0_40845939_clarity_db

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `notes`;

CREATE TABLE `notes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL DEFAULT '',
  `content` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_updated_at` (`updated_at`)
) ENGINE=InnoDB AUTO_INCREMENT=11467 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `notes`
INSERT INTO `notes` (`id`, `user_id`, `title`, `content`, `created_at`, `updated_at`) VALUES ('11464', '2326', 'some', '', '2026-01-06 06:00:31', '2026-01-06 06:00:31');
INSERT INTO `notes` (`id`, `user_id`, `title`, `content`, `created_at`, `updated_at`) VALUES ('11466', '2326', 'something', '', '2026-01-06 06:00:38', '2026-01-06 06:00:38');

SET FOREIGN_KEY_CHECKS = 1;
