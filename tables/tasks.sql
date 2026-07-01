-- Database Table Export for `tasks`
-- Generated: 2026-06-25 07:00:57
-- DB Name: if0_40845939_clarity_db

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `tasks`;

CREATE TABLE `tasks` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `is_completed` tinyint(1) NOT NULL DEFAULT 0,
  `completed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tasks_user_id` (`user_id`),
  KEY `idx_tasks_user_created` (`user_id`,`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=9935 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `tasks`
INSERT INTO `tasks` (`id`, `user_id`, `title`, `description`, `is_completed`, `completed_at`, `created_at`, `updated_at`) VALUES ('9930', '19330', 'do some feasibilty', NULL, '1', '2026-01-05 10:55:21', '2026-01-05 10:55:04', '2026-01-05 10:55:21');
INSERT INTO `tasks` (`id`, `user_id`, `title`, `description`, `is_completed`, `completed_at`, `created_at`, `updated_at`) VALUES ('9931', '19330', 'some', NULL, '0', NULL, '2026-01-05 10:55:07', '2026-01-05 10:55:07');
INSERT INTO `tasks` (`id`, `user_id`, `title`, `description`, `is_completed`, `completed_at`, `created_at`, `updated_at`) VALUES ('9932', '19330', 'some more', NULL, '0', NULL, '2026-01-05 10:55:11', '2026-01-05 10:55:11');
INSERT INTO `tasks` (`id`, `user_id`, `title`, `description`, `is_completed`, `completed_at`, `created_at`, `updated_at`) VALUES ('9933', '19330', 'something', NULL, '0', NULL, '2026-01-05 10:55:14', '2026-01-05 10:55:14');
INSERT INTO `tasks` (`id`, `user_id`, `title`, `description`, `is_completed`, `completed_at`, `created_at`, `updated_at`) VALUES ('9934', '2326', 'Some', 'this is not some this is something', '0', NULL, '2026-01-05 11:03:29', '2026-01-05 11:25:14');

SET FOREIGN_KEY_CHECKS = 1;
