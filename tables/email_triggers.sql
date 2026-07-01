-- Database Table Export for `email_triggers`
-- Generated: 2026-06-25 07:00:57
-- DB Name: if0_40845939_clarity_db

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `email_triggers`;

CREATE TABLE `email_triggers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `module_name` varchar(50) NOT NULL,
  `event_type` varchar(50) NOT NULL,
  `template_id` int(11) NOT NULL,
  `recipient_rules` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`recipient_rules`)),
  `conditions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`conditions`)),
  `is_active` tinyint(1) DEFAULT 1,
  `company_id` int(11) NOT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `created_by` (`created_by`),
  KEY `idx_module_event` (`module_name`,`event_type`),
  KEY `idx_template_active` (`template_id`,`is_active`),
  KEY `idx_company_active` (`company_id`,`is_active`),
  CONSTRAINT `email_triggers_ibfk_1` FOREIGN KEY (`template_id`) REFERENCES `email_templates` (`id`) ON DELETE CASCADE,
  CONSTRAINT `email_triggers_ibfk_2` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `email_triggers_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=389 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `email_triggers`
INSERT INTO `email_triggers` (`id`, `name`, `module_name`, `event_type`, `template_id`, `recipient_rules`, `conditions`, `is_active`, `company_id`, `created_by`, `created_at`, `updated_at`) VALUES ('304', 'Test Trigger 695d549d4f06b', 'material_request', 'material_request_received', '956', '[{\"type\":\"static\",\"emails\":[\"test@example.com\",\"test2@example.com\"]}]', NULL, '1', '1', '2326', '2026-01-06 18:29:49', '2026-01-06 18:29:49');

SET FOREIGN_KEY_CHECKS = 1;
