-- Database Table Export for `settings_audit`
-- Generated: 2026-06-25 07:00:57
-- DB Name: if0_40845939_clarity_db

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `settings_audit`;

CREATE TABLE `settings_audit` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `old_value` text DEFAULT NULL,
  `new_value` text DEFAULT NULL,
  `action` enum('CREATE','UPDATE','DELETE','RESET') NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `session_id` varchar(128) DEFAULT NULL,
  `request_method` varchar(10) DEFAULT NULL,
  `request_uri` text DEFAULT NULL,
  `integrity_hash` varchar(64) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_setting_timestamp` (`setting_id`,`timestamp`),
  KEY `idx_user_timestamp` (`user_id`,`timestamp`),
  KEY `idx_action` (`action`),
  KEY `idx_integrity` (`integrity_hash`)
) ENGINE=InnoDB AUTO_INCREMENT=3325 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `settings_audit`
INSERT INTO `settings_audit` (`id`, `setting_id`, `user_id`, `old_value`, `new_value`, `action`, `timestamp`, `ip_address`, `user_agent`, `session_id`, `request_method`, `request_uri`, `integrity_hash`) VALUES ('3320', '1', '2326', 'old_test_value', 'new_test_value', 'UPDATE', '2026-01-06 10:57:39', '192.168.1.100', 'Test User Agent', 'no-session', 'CLI', 'cli-command', '4225198a60e4d7d26b500dfca5a849846db00626be80e5a9d51c5f2ea635d1df');
INSERT INTO `settings_audit` (`id`, `setting_id`, `user_id`, `old_value`, `new_value`, `action`, `timestamp`, `ip_address`, `user_agent`, `session_id`, `request_method`, `request_uri`, `integrity_hash`) VALUES ('3321', '2', '2326', 'UTC', 'Asia/Kolkata', 'UPDATE', '2026-01-06 12:23:03', '192.168.18.12', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:146.0) Gecko/20100101 Firefox/146.0', 'vlhvcudf9ur5hg22eulpuv2psi', 'PUT', '/api/settings/update.php', 'f47608f1d0ad646d0852afa440a86933aeeaacf087898c6399a345eec86fc034');
INSERT INTO `settings_audit` (`id`, `setting_id`, `user_id`, `old_value`, `new_value`, `action`, `timestamp`, `ip_address`, `user_agent`, `session_id`, `request_method`, `request_uri`, `integrity_hash`) VALUES ('3322', '2234', '2326', 'initial_value', '', 'UPDATE', '2026-01-06 12:25:01', '192.168.18.12', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:146.0) Gecko/20100101 Firefox/146.0', 'vlhvcudf9ur5hg22eulpuv2psi', 'PUT', '/api/settings/update.php', '9803621e214c1858de3a9240804cdc4989b4ac3183faa0214415e476aeccb24d');
INSERT INTO `settings_audit` (`id`, `setting_id`, `user_id`, `old_value`, `new_value`, `action`, `timestamp`, `ip_address`, `user_agent`, `session_id`, `request_method`, `request_uri`, `integrity_hash`) VALUES ('3323', '2234', '2326', '', 'initial_value', 'UPDATE', '2026-01-06 12:25:10', '192.168.18.12', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:146.0) Gecko/20100101 Firefox/146.0', 'vlhvcudf9ur5hg22eulpuv2psi', 'PUT', '/api/settings/update.php', '604058adbaaf63da29d4b9619bc1574e1b74fbe3da09d0de86aae433822b984e');
INSERT INTO `settings_audit` (`id`, `setting_id`, `user_id`, `old_value`, `new_value`, `action`, `timestamp`, `ip_address`, `user_agent`, `session_id`, `request_method`, `request_uri`, `integrity_hash`) VALUES ('3324', '1', '2326', 'ADV CRM System', 'ADVANTAGESB', 'UPDATE', '2026-01-06 12:31:22', '192.168.18.12', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:146.0) Gecko/20100101 Firefox/146.0', 'vlhvcudf9ur5hg22eulpuv2psi', 'PUT', '/api/settings/update.php', '2d9dfae00497f18a57504d54ed4cf3989b460c40bc62858922424f96e887fd3f');

SET FOREIGN_KEY_CHECKS = 1;
