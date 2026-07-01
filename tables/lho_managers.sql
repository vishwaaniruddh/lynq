-- Database Table Export for `lho_managers`
-- Generated: 2026-06-25 07:00:57
-- DB Name: if0_40845939_clarity_db

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `lho_managers`;

CREATE TABLE `lho_managers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `lho_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_lho_user` (`lho_id`,`user_id`),
  KEY `idx_lho_id` (`lho_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_created_by` (`created_by`)
) ENGINE=InnoDB AUTO_INCREMENT=3598 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `lho_managers`
INSERT INTO `lho_managers` (`id`, `lho_id`, `user_id`, `created_at`, `created_by`) VALUES ('3592', '3', '22032', '2026-01-05 02:22:13', '2326');
INSERT INTO `lho_managers` (`id`, `lho_id`, `user_id`, `created_at`, `created_by`) VALUES ('3593', '3', '2326', '2026-01-05 02:22:13', '2326');
INSERT INTO `lho_managers` (`id`, `lho_id`, `user_id`, `created_at`, `created_by`) VALUES ('3594', '3', '28268', '2026-01-05 02:22:13', '2326');
INSERT INTO `lho_managers` (`id`, `lho_id`, `user_id`, `created_at`, `created_by`) VALUES ('3595', '3', '28364', '2026-01-05 02:22:13', '2326');
INSERT INTO `lho_managers` (`id`, `lho_id`, `user_id`, `created_at`, `created_by`) VALUES ('3596', '3', '28332', '2026-01-05 02:22:13', '2326');
INSERT INTO `lho_managers` (`id`, `lho_id`, `user_id`, `created_at`, `created_by`) VALUES ('3597', '3', '28300', '2026-01-05 02:22:13', '2326');

SET FOREIGN_KEY_CHECKS = 1;
