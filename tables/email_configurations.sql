-- Database Table Export for `email_configurations`
-- Generated: 2026-06-25 07:00:57
-- DB Name: if0_40845939_clarity_db

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `email_configurations`;

CREATE TABLE `email_configurations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `type` enum('smtp','imap') NOT NULL,
  `host` varchar(255) NOT NULL,
  `port` int(11) NOT NULL,
  `username` varchar(255) NOT NULL,
  `password_encrypted` text NOT NULL,
  `encryption` enum('none','ssl','tls') DEFAULT 'tls',
  `is_default` tinyint(1) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `company_id` int(11) NOT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `created_by` (`created_by`),
  KEY `idx_company_type` (`company_id`,`type`),
  KEY `idx_active_default` (`is_active`,`is_default`),
  CONSTRAINT `email_configurations_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `email_configurations_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1224 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `email_configurations`
INSERT INTO `email_configurations` (`id`, `name`, `type`, `host`, `port`, `username`, `password_encrypted`, `encryption`, `is_default`, `is_active`, `company_id`, `created_by`, `created_at`, `updated_at`) VALUES ('303', 'test', 'smtp', 'test.com', '587', 'test@test.com', '7862C+XtgDxKR+7YImQ1vjdkR1FoRXZ3VlFlbnJmUVFDeXdpdFE9PQ==', 'tls', '0', '1', '1', '2326', '2026-01-06 17:24:36', '2026-01-06 17:24:36');

SET FOREIGN_KEY_CHECKS = 1;
