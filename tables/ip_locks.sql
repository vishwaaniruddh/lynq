-- Database Table Export for `ip_locks`
-- Generated: 2026-06-25 07:00:57
-- DB Name: if0_40845939_clarity_db

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `ip_locks`;

CREATE TABLE `ip_locks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ip_master_id` int(11) NOT NULL COMMENT 'Reference to ip_master table',
  `router_serial_number` varchar(100) NOT NULL COMMENT 'Serial number of router being configured',
  `locked_by` int(11) NOT NULL COMMENT 'User ID who acquired the lock',
  `locked_at` datetime DEFAULT current_timestamp() COMMENT 'When the lock was acquired',
  `expires_at` datetime NOT NULL COMMENT 'When the lock expires (locked_at + 20 minutes)',
  `status` enum('active','released','expired') DEFAULT 'active' COMMENT 'Current lock status',
  `released_at` datetime DEFAULT NULL COMMENT 'When the lock was released (if applicable)',
  PRIMARY KEY (`id`),
  KEY `locked_by` (`locked_by`),
  KEY `idx_status_expires` (`status`,`expires_at`),
  KEY `idx_ip_master_status` (`ip_master_id`,`status`),
  KEY `idx_router_serial` (`router_serial_number`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='IP Locks table for temporary locking during 20-minute configuration process';

-- Dumping data for table `ip_locks`
INSERT INTO `ip_locks` (`id`, `ip_master_id`, `router_serial_number`, `locked_by`, `locked_at`, `expires_at`, `status`, `released_at`) VALUES ('1', '1736', 'ROUT-1-0001-8bbb', '2326', '2026-01-05 07:53:23', '2026-01-05 08:13:23', 'released', '2026-01-05 07:53:33');
INSERT INTO `ip_locks` (`id`, `ip_master_id`, `router_serial_number`, `locked_by`, `locked_at`, `expires_at`, `status`, `released_at`) VALUES ('2', '1737', 'ROUT-2-0003-f799', '2326', '2026-01-15 12:28:42', '2026-01-15 12:48:42', 'released', '2026-01-15 12:28:49');

SET FOREIGN_KEY_CHECKS = 1;
