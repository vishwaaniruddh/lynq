-- Database Table Export for `router_ip_bindings`
-- Generated: 2026-06-25 07:00:57
-- DB Name: if0_40845939_clarity_db

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `router_ip_bindings`;

CREATE TABLE `router_ip_bindings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `router_serial_number` varchar(100) NOT NULL COMMENT 'Serial number of the configured router',
  `ip_master_id` int(11) NOT NULL COMMENT 'Reference to ip_master table',
  `configured_by` int(11) NOT NULL COMMENT 'User ID who performed the configuration',
  `configured_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'When the configuration was completed',
  `notes` text DEFAULT NULL COMMENT 'Optional notes about the configuration',
  `status` enum('active','unbound') DEFAULT 'active' COMMENT 'Binding status',
  `unbound_by` int(11) DEFAULT NULL COMMENT 'User ID who unbound the IP',
  `unbound_at` timestamp NULL DEFAULT NULL COMMENT 'When the IP was unbound',
  `unbind_reason` text DEFAULT NULL COMMENT 'Reason for unbinding',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_active_router` (`router_serial_number`,`status`),
  KEY `configured_by` (`configured_by`),
  KEY `unbound_by` (`unbound_by`),
  KEY `idx_router_serial` (`router_serial_number`),
  KEY `idx_status` (`status`),
  KEY `idx_ip_master` (`ip_master_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Router IP Bindings table for permanent router-to-IP associations';

-- Dumping data for table `router_ip_bindings`
INSERT INTO `router_ip_bindings` (`id`, `router_serial_number`, `ip_master_id`, `configured_by`, `configured_at`, `notes`, `status`, `unbound_by`, `unbound_at`, `unbind_reason`) VALUES ('1', 'ROUT-1-0001-8bbb', '1736', '2326', '2026-01-05 02:23:33', NULL, 'active', NULL, NULL, NULL);
INSERT INTO `router_ip_bindings` (`id`, `router_serial_number`, `ip_master_id`, `configured_by`, `configured_at`, `notes`, `status`, `unbound_by`, `unbound_at`, `unbind_reason`) VALUES ('2', 'ROUT-2-0003-f799', '1737', '2326', '2026-01-15 06:58:49', NULL, 'active', NULL, NULL, NULL);

SET FOREIGN_KEY_CHECKS = 1;
