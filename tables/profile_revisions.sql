-- Database Table Export for `profile_revisions`
-- Generated: 2026-06-25 07:00:57
-- DB Name: if0_40845939_clarity_db

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `profile_revisions`;

CREATE TABLE `profile_revisions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `changed_fields` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`changed_fields`)),
  `old_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`old_values`)),
  `new_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`new_values`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=3837 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `profile_revisions`
INSERT INTO `profile_revisions` (`id`, `user_id`, `changed_fields`, `old_values`, `new_values`, `created_at`) VALUES ('3833', '2326', '[\"contact_number\",\"date_of_birth\",\"sex\"]', '{\"contact_number\":null,\"date_of_birth\":null,\"sex\":null}', '{\"contact_number\":\"7021889883\",\"date_of_birth\":\"1993-12-04\",\"sex\":\"male\"}', '2026-01-05 02:26:48');
INSERT INTO `profile_revisions` (`id`, `user_id`, `changed_fields`, `old_values`, `new_values`, `created_at`) VALUES ('3834', '2326', '[\"profile_picture\"]', '{\"profile_picture\":null}', '{\"profile_picture\":\"uploads\\/profiles\\/profile_2326_1767580156.jpg\"}', '2026-01-05 02:29:16');
INSERT INTO `profile_revisions` (`id`, `user_id`, `changed_fields`, `old_values`, `new_values`, `created_at`) VALUES ('3835', '2326', '[\"first_name\"]', '{\"first_name\":\"Aniruddh\"}', '{\"first_name\":\"Ani\"}', '2026-01-05 18:01:14');
INSERT INTO `profile_revisions` (`id`, `user_id`, `changed_fields`, `old_values`, `new_values`, `created_at`) VALUES ('3836', '2326', '[\"first_name\"]', '{\"first_name\":\"Ani\"}', '{\"first_name\":\"Aniruddh\"}', '2026-01-05 18:01:22');

SET FOREIGN_KEY_CHECKS = 1;
