-- Database Table Export for `email_logs`
-- Generated: 2026-06-25 07:00:57
-- DB Name: if0_40845939_clarity_db

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `email_logs`;

CREATE TABLE `email_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `queue_id` int(11) DEFAULT NULL,
  `to_email` varchar(255) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `status` enum('sent','failed','bounced') NOT NULL,
  `delivery_status` varchar(100) DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  `template_id` int(11) DEFAULT NULL,
  `trigger_id` int(11) DEFAULT NULL,
  `company_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `sent_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `queue_id` (`queue_id`),
  KEY `trigger_id` (`trigger_id`),
  KEY `user_id` (`user_id`),
  KEY `idx_company_status` (`company_id`,`status`),
  KEY `idx_sent_at` (`sent_at`),
  KEY `idx_template_trigger` (`template_id`,`trigger_id`),
  KEY `idx_email_logs_audit` (`company_id`,`sent_at`,`status`),
  CONSTRAINT `email_logs_ibfk_1` FOREIGN KEY (`queue_id`) REFERENCES `email_queue` (`id`) ON DELETE SET NULL,
  CONSTRAINT `email_logs_ibfk_2` FOREIGN KEY (`template_id`) REFERENCES `email_templates` (`id`) ON DELETE SET NULL,
  CONSTRAINT `email_logs_ibfk_3` FOREIGN KEY (`trigger_id`) REFERENCES `email_triggers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `email_logs_ibfk_4` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `email_logs_ibfk_5` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- No data found in `email_logs`

SET FOREIGN_KEY_CHECKS = 1;
