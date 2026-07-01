-- Database Table Export for `email_queue`
-- Generated: 2026-06-25 07:00:57
-- DB Name: if0_40845939_clarity_db

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `email_queue`;

CREATE TABLE `email_queue` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `to_email` varchar(255) NOT NULL,
  `cc_email` text DEFAULT NULL,
  `bcc_email` text DEFAULT NULL,
  `subject` varchar(255) NOT NULL,
  `body_text` text DEFAULT NULL,
  `body_html` text DEFAULT NULL,
  `template_id` int(11) DEFAULT NULL,
  `trigger_id` int(11) DEFAULT NULL,
  `priority` enum('low','normal','high') DEFAULT 'normal',
  `status` enum('pending','processing','sent','failed') DEFAULT 'pending',
  `attempts` int(11) DEFAULT 0,
  `max_attempts` int(11) DEFAULT 3,
  `error_message` text DEFAULT NULL,
  `scheduled_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `sent_at` timestamp NULL DEFAULT NULL,
  `company_id` int(11) NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `template_id` (`template_id`),
  KEY `trigger_id` (`trigger_id`),
  KEY `created_by` (`created_by`),
  KEY `idx_status_priority` (`status`,`priority`),
  KEY `idx_scheduled_status` (`scheduled_at`,`status`),
  KEY `idx_company_status` (`company_id`,`status`),
  KEY `idx_email_queue_processing` (`status`,`attempts`,`scheduled_at`),
  CONSTRAINT `email_queue_ibfk_1` FOREIGN KEY (`template_id`) REFERENCES `email_templates` (`id`) ON DELETE SET NULL,
  CONSTRAINT `email_queue_ibfk_2` FOREIGN KEY (`trigger_id`) REFERENCES `email_triggers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `email_queue_ibfk_3` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `email_queue_ibfk_4` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=151 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- No data found in `email_queue`

SET FOREIGN_KEY_CHECKS = 1;
