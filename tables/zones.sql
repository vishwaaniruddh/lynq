-- Database Table Export for `zones`
-- Generated: 2026-06-25 07:00:57
-- DB Name: if0_40845939_clarity_db

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `zones`;

CREATE TABLE `zones` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_zone_name` (`name`),
  KEY `idx_status` (`status`),
  KEY `created_by` (`created_by`),
  KEY `updated_by` (`updated_by`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `zones`
INSERT INTO `zones` (`id`, `name`, `status`, `created_at`, `updated_at`, `created_by`, `updated_by`) VALUES ('1', 'North', 'active', '2025-11-01 13:17:44', '2025-11-03 00:42:10', NULL, NULL);
INSERT INTO `zones` (`id`, `name`, `status`, `created_at`, `updated_at`, `created_by`, `updated_by`) VALUES ('2', 'South', 'active', '2025-11-01 13:17:44', '2025-11-03 00:42:12', NULL, NULL);
INSERT INTO `zones` (`id`, `name`, `status`, `created_at`, `updated_at`, `created_by`, `updated_by`) VALUES ('3', 'East', 'active', '2025-11-01 13:17:44', '2025-11-03 00:42:20', NULL, NULL);
INSERT INTO `zones` (`id`, `name`, `status`, `created_at`, `updated_at`, `created_by`, `updated_by`) VALUES ('4', 'West', 'active', '2025-11-01 13:17:44', '2025-11-03 00:42:26', NULL, NULL);
INSERT INTO `zones` (`id`, `name`, `status`, `created_at`, `updated_at`, `created_by`, `updated_by`) VALUES ('5', 'Central', 'active', '2025-11-01 13:17:44', '2025-11-03 00:42:32', NULL, NULL);
INSERT INTO `zones` (`id`, `name`, `status`, `created_at`, `updated_at`, `created_by`, `updated_by`) VALUES ('6', 'Northeast', 'active', '2025-11-01 13:17:44', '2025-11-03 00:42:34', NULL, NULL);

SET FOREIGN_KEY_CHECKS = 1;
