-- Database Table Export for `banks`
-- Generated: 2026-06-25 07:00:57
-- DB Name: if0_40845939_clarity_db

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `banks`;

CREATE TABLE `banks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `status` tinyint(1) DEFAULT 1 COMMENT '0=inactive, 1=active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_bank_name` (`name`),
  KEY `idx_status` (`status`),
  KEY `created_by` (`created_by`),
  KEY `updated_by` (`updated_by`),
  CONSTRAINT `banks_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `banks_ibfk_2` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `banks`
INSERT INTO `banks` (`id`, `name`, `status`, `created_at`, `updated_at`, `created_by`, `updated_by`) VALUES ('1', 'HDFC Bank', '1', '2025-12-29 20:45:33', '2025-12-29 20:45:33', '2326', NULL);
INSERT INTO `banks` (`id`, `name`, `status`, `created_at`, `updated_at`, `created_by`, `updated_by`) VALUES ('2', 'SBI', '1', '2025-12-29 20:45:40', '2025-12-29 20:45:40', '2326', NULL);
INSERT INTO `banks` (`id`, `name`, `status`, `created_at`, `updated_at`, `created_by`, `updated_by`) VALUES ('3', 'Axis Bank', '1', '2025-12-29 20:45:47', '2025-12-29 20:46:24', '2326', '2326');
INSERT INTO `banks` (`id`, `name`, `status`, `created_at`, `updated_at`, `created_by`, `updated_by`) VALUES ('4', 'IDFC Bank', '1', '2025-12-29 20:45:56', '2025-12-29 20:45:56', '2326', NULL);

SET FOREIGN_KEY_CHECKS = 1;
