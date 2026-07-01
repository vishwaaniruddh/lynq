-- Database Table Export for `configuration_audit_log`
-- Generated: 2026-06-25 07:00:57
-- DB Name: if0_40845939_clarity_db

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `configuration_audit_log`;

CREATE TABLE `configuration_audit_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `action_type` enum('lock_acquired','lock_released','lock_expired','configured','unbound','ip_created','ip_updated','ip_deleted','bulk_upload') NOT NULL COMMENT 'Type of configuration action',
  `user_id` int(11) NOT NULL COMMENT 'User ID who performed the action',
  `router_serial_number` varchar(100) DEFAULT NULL COMMENT 'Router serial number (if applicable)',
  `ip_master_id` int(11) DEFAULT NULL COMMENT 'Reference to ip_master (if applicable)',
  `details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Additional context in JSON format' CHECK (json_valid(`details`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'When the action occurred',
  PRIMARY KEY (`id`),
  KEY `ip_master_id` (`ip_master_id`),
  KEY `idx_action_type` (`action_type`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_router` (`router_serial_number`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_action_created` (`action_type`,`created_at`),
  CONSTRAINT `configuration_audit_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `configuration_audit_log_ibfk_2` FOREIGN KEY (`ip_master_id`) REFERENCES `ip_master` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Configuration audit log for tracking all IP configuration activities';

-- Dumping data for table `configuration_audit_log`
INSERT INTO `configuration_audit_log` (`id`, `action_type`, `user_id`, `router_serial_number`, `ip_master_id`, `details`, `created_at`) VALUES ('1', 'lock_acquired', '2326', 'ROUT-1-0001-8bbb', '1736', '{\"router_serial_number\":\"ROUT-1-0001-8bbb\",\"lock_id\":1,\"expires_at\":\"2026-01-05 08:13:23\"}', '2026-01-05 02:23:23');
INSERT INTO `configuration_audit_log` (`id`, `action_type`, `user_id`, `router_serial_number`, `ip_master_id`, `details`, `created_at`) VALUES ('2', 'configured', '2326', 'ROUT-1-0001-8bbb', '1736', '{\"binding_id\":1,\"notes\":null,\"lock_id\":1}', '2026-01-05 02:23:33');
INSERT INTO `configuration_audit_log` (`id`, `action_type`, `user_id`, `router_serial_number`, `ip_master_id`, `details`, `created_at`) VALUES ('3', 'lock_acquired', '2326', 'ROUT-2-0003-f799', '1737', '{\"router_serial_number\":\"ROUT-2-0003-f799\",\"lock_id\":2,\"expires_at\":\"2026-01-15 12:48:42\"}', '2026-01-15 06:58:42');
INSERT INTO `configuration_audit_log` (`id`, `action_type`, `user_id`, `router_serial_number`, `ip_master_id`, `details`, `created_at`) VALUES ('4', 'configured', '2326', 'ROUT-2-0003-f799', '1737', '{\"binding_id\":2,\"notes\":null,\"lock_id\":2}', '2026-01-15 06:58:49');

SET FOREIGN_KEY_CHECKS = 1;
