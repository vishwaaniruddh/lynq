-- Database Table Export for `roles`
-- Generated: 2026-06-25 07:00:57
-- DB Name: if0_40845939_clarity_db

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `roles`;

CREATE TABLE `roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `level` int(11) NOT NULL DEFAULT 1,
  `company_type` enum('ADV','CONTRACTOR','BOTH') NOT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_name_company_type` (`name`,`company_type`),
  KEY `idx_company_type` (`company_type`),
  KEY `idx_level` (`level`)
) ENGINE=InnoDB AUTO_INCREMENT=2960 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `roles`
INSERT INTO `roles` (`id`, `name`, `level`, `company_type`, `description`, `is_active`, `created_at`, `updated_at`) VALUES ('1', 'Super Admin', '10', 'ADV', 'Full system administrator with all permissions', '1', '2025-12-27 09:45:18', '2026-01-01 12:17:13');
INSERT INTO `roles` (`id`, `name`, `level`, `company_type`, `description`, `is_active`, `created_at`, `updated_at`) VALUES ('2', 'ADV Admin', '8', 'ADV', 'ADV administrator with delegation capabilities', '1', '2025-12-27 09:45:18', '2025-12-27 09:45:18');
INSERT INTO `roles` (`id`, `name`, `level`, `company_type`, `description`, `is_active`, `created_at`, `updated_at`) VALUES ('3', 'ADV Manager', '6', 'ADV', 'ADV manager with limited administrative access', '1', '2025-12-27 09:45:18', '2025-12-30 14:54:35');
INSERT INTO `roles` (`id`, `name`, `level`, `company_type`, `description`, `is_active`, `created_at`, `updated_at`) VALUES ('4', 'ADV User', '4', 'ADV', 'Standard ADV user', '1', '2025-12-27 09:45:18', '2025-12-27 09:45:18');
INSERT INTO `roles` (`id`, `name`, `level`, `company_type`, `description`, `is_active`, `created_at`, `updated_at`) VALUES ('5', 'Contractor Admin', '7', 'CONTRACTOR', 'Contractor administrator', '1', '2025-12-27 09:45:18', '2026-01-01 12:17:33');
INSERT INTO `roles` (`id`, `name`, `level`, `company_type`, `description`, `is_active`, `created_at`, `updated_at`) VALUES ('6', 'Contractor Manager', '5', 'CONTRACTOR', 'Contractor manager', '1', '2025-12-27 09:45:18', '2025-12-29 22:09:49');
INSERT INTO `roles` (`id`, `name`, `level`, `company_type`, `description`, `is_active`, `created_at`, `updated_at`) VALUES ('7', 'Contractor User', '3', 'CONTRACTOR', 'Standard contractor user', '1', '2025-12-27 09:45:18', '2025-12-27 09:45:18');
INSERT INTO `roles` (`id`, `name`, `level`, `company_type`, `description`, `is_active`, `created_at`, `updated_at`) VALUES ('8', 'Engineer', '2', 'CONTRACTOR', 'Field engineer', '1', '2025-12-27 09:45:18', '2025-12-29 22:44:03');
INSERT INTO `roles` (`id`, `name`, `level`, `company_type`, `description`, `is_active`, `created_at`, `updated_at`) VALUES ('9', 'Viewer', '1', 'BOTH', 'Read-only access', '1', '2025-12-27 09:45:18', '2025-12-27 09:45:18');

SET FOREIGN_KEY_CHECKS = 1;
