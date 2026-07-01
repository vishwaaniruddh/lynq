-- Database Table Export for `email_configuration_audit_log`
-- Generated: 2026-06-25 07:00:57
-- DB Name: if0_40845939_clarity_db

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `email_configuration_audit_log`;

CREATE TABLE `email_configuration_audit_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `configuration_id` int(11) NOT NULL,
  `action` enum('created','updated','deleted','connection_tested') NOT NULL,
  `details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`details`)),
  `user_id` int(11) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_config_action` (`configuration_id`,`action`),
  KEY `idx_user_created` (`user_id`,`created_at`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `email_configuration_audit_log_ibfk_1` FOREIGN KEY (`configuration_id`) REFERENCES `email_configurations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `email_configuration_audit_log_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- No data found in `email_configuration_audit_log`

SET FOREIGN_KEY_CHECKS = 1;
