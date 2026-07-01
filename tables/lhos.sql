-- Database Table Export for `lhos`
-- Generated: 2026-06-25 07:00:57
-- DB Name: if0_40845939_clarity_db

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `lhos`;

CREATE TABLE `lhos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `lho_name` varchar(255) NOT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_lho_name` (`lho_name`),
  KEY `idx_status` (`status`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=208 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `lhos`
INSERT INTO `lhos` (`id`, `lho_name`, `status`, `created_at`, `updated_at`, `created_by`, `updated_by`) VALUES ('1', 'Mumbai', 'active', '2025-12-29 21:31:29', '2026-01-04 18:08:11', '2326', '2326');
INSERT INTO `lhos` (`id`, `lho_name`, `status`, `created_at`, `updated_at`, `created_by`, `updated_by`) VALUES ('2', 'Pune', 'active', '2025-12-29 21:38:43', '2025-12-29 21:38:43', '2326', NULL);
INSERT INTO `lhos` (`id`, `lho_name`, `status`, `created_at`, `updated_at`, `created_by`, `updated_by`) VALUES ('3', 'Delhi', 'active', '2025-12-29 21:38:59', '2026-01-05 02:22:13', '2326', '2326');

SET FOREIGN_KEY_CHECKS = 1;
