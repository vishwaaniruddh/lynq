-- Database Table Export for `email_templates`
-- Generated: 2026-06-25 07:00:57
-- DB Name: if0_40845939_clarity_db

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `email_templates`;

CREATE TABLE `email_templates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `body_text` text DEFAULT NULL,
  `body_html` text DEFAULT NULL,
  `module_name` varchar(50) NOT NULL,
  `event_type` varchar(50) NOT NULL,
  `placeholders` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`placeholders`)),
  `is_active` tinyint(1) DEFAULT 1,
  `company_id` int(11) NOT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_template` (`module_name`,`event_type`,`company_id`),
  KEY `created_by` (`created_by`),
  KEY `idx_module_event` (`module_name`,`event_type`),
  KEY `idx_company_active` (`company_id`,`is_active`),
  KEY `idx_email_templates_lookup` (`module_name`,`event_type`,`is_active`),
  CONSTRAINT `email_templates_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `email_templates_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1120 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `email_templates`
INSERT INTO `email_templates` (`id`, `name`, `subject`, `body_text`, `body_html`, `module_name`, `event_type`, `placeholders`, `is_active`, `company_id`, `created_by`, `created_at`, `updated_at`) VALUES ('956', 'Test Template 695d549d4d5e3', 'Test Subject: {site_name}', 'Test body for {user_name} at {site_name}', NULL, 'material_request', 'material_request_received', NULL, '1', '1', '2326', '2026-01-06 18:29:49', '2026-01-06 18:29:49');

SET FOREIGN_KEY_CHECKS = 1;
