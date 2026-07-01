-- Database Table Export for `company_access_log`
-- Generated: 2026-06-25 07:00:57
-- DB Name: if0_40845939_clarity_db

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `company_access_log`;

CREATE TABLE `company_access_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `target_company_id` int(11) NOT NULL,
  `access_result` enum('GRANTED','DENIED') NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `timestamp` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_target_company_id` (`target_company_id`),
  KEY `idx_access_result` (`access_result`),
  KEY `idx_timestamp` (`timestamp`),
  CONSTRAINT `company_access_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `company_access_log_ibfk_2` FOREIGN KEY (`target_company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5771 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- No data found in `company_access_log`

SET FOREIGN_KEY_CHECKS = 1;
