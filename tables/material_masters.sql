-- Database Table Export for `material_masters`
-- Generated: 2026-06-25 07:00:57
-- DB Name: if0_40845939_clarity_db

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `material_masters`;

CREATE TABLE `material_masters` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` varchar(500) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `company_id` int(11) NOT NULL COMMENT 'Company isolation',
  `created_by` int(11) NOT NULL COMMENT 'User who created',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL COMMENT 'Soft delete timestamp',
  PRIMARY KEY (`id`),
  KEY `idx_company_id` (`company_id`),
  KEY `idx_status` (`status`),
  KEY `idx_deleted_at` (`deleted_at`),
  KEY `idx_company_status` (`company_id`,`status`,`deleted_at`),
  KEY `created_by` (`created_by`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `material_masters`
INSERT INTO `material_masters` (`id`, `name`, `description`, `status`, `company_id`, `created_by`, `created_at`, `updated_at`, `deleted_at`) VALUES ('1', 'SBI Material Sheet', 'SBI Material Sheet', 'active', '1', '2326', '2026-01-03 22:15:33', '2026-01-03 22:15:33', NULL);

SET FOREIGN_KEY_CHECKS = 1;
