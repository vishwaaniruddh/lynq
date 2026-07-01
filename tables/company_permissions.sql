-- Database Table Export for `company_permissions`
-- Generated: 2026-06-25 07:00:57
-- DB Name: if0_40845939_clarity_db

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `company_permissions`;

CREATE TABLE `company_permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `permission_id` int(11) NOT NULL,
  `granted_by` int(11) NOT NULL,
  `granted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `revoked_at` timestamp NULL DEFAULT NULL,
  `revoked_by` int(11) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `permission_id` (`permission_id`),
  KEY `granted_by` (`granted_by`),
  KEY `revoked_by` (`revoked_by`),
  KEY `idx_company_permission` (`company_id`,`permission_id`),
  KEY `idx_active` (`is_active`),
  CONSTRAINT `company_permissions_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `company_permissions_ibfk_2` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `company_permissions_ibfk_3` FOREIGN KEY (`granted_by`) REFERENCES `users` (`id`),
  CONSTRAINT `company_permissions_ibfk_4` FOREIGN KEY (`revoked_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=616 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- No data found in `company_permissions`

SET FOREIGN_KEY_CHECKS = 1;
