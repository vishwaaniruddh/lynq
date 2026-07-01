-- Database Table Export for `ip_master`
-- Generated: 2026-06-25 07:00:57
-- DB Name: if0_40845939_clarity_db

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `ip_master`;

CREATE TABLE `ip_master` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `network_ip` varchar(15) NOT NULL COMMENT 'Network IP address',
  `router_ip` varchar(15) NOT NULL COMMENT 'Router IP address',
  `site_ip` varchar(15) NOT NULL COMMENT 'Site IP address',
  `subnet_mask` varchar(15) NOT NULL COMMENT 'Subnet mask',
  `status` enum('available','locked','configured') DEFAULT 'available' COMMENT 'Current status of IP combination',
  `created_by` int(11) DEFAULT NULL COMMENT 'User ID who created this record',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_ip_combination` (`network_ip`,`router_ip`,`site_ip`,`subnet_mask`),
  KEY `idx_status` (`status`),
  KEY `created_by` (`created_by`)
) ENGINE=InnoDB AUTO_INCREMENT=8523 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='IP Master table storing unique IP address combinations for router configuration';

-- Dumping data for table `ip_master`
INSERT INTO `ip_master` (`id`, `network_ip`, `router_ip`, `site_ip`, `subnet_mask`, `status`, `created_by`, `created_at`, `updated_at`) VALUES ('1736', '53.227.113.59', '42.57.205.207', '130.195.57.91', '255.255.255.0', 'configured', '2326', '2025-12-30 09:05:50', '2026-01-05 02:23:33');
INSERT INTO `ip_master` (`id`, `network_ip`, `router_ip`, `site_ip`, `subnet_mask`, `status`, `created_by`, `created_at`, `updated_at`) VALUES ('1737', '107.204.184.31', '242.65.1.70', '247.111.18.13', '255.255.255.0', 'configured', '2326', '2025-12-30 09:05:50', '2026-01-15 06:58:49');
INSERT INTO `ip_master` (`id`, `network_ip`, `router_ip`, `site_ip`, `subnet_mask`, `status`, `created_by`, `created_at`, `updated_at`) VALUES ('1738', '232.73.47.21', '215.247.214.143', '50.138.72.81', '255.255.255.0', 'available', '2326', '2025-12-30 09:05:50', '2025-12-31 15:02:35');
INSERT INTO `ip_master` (`id`, `network_ip`, `router_ip`, `site_ip`, `subnet_mask`, `status`, `created_by`, `created_at`, `updated_at`) VALUES ('1739', '186.157.68.180', '198.74.247.81', '187.51.220.35', '255.255.255.0', 'available', '2326', '2025-12-30 09:05:50', '2025-12-30 09:05:50');
INSERT INTO `ip_master` (`id`, `network_ip`, `router_ip`, `site_ip`, `subnet_mask`, `status`, `created_by`, `created_at`, `updated_at`) VALUES ('1740', '197.221.192.231', '238.59.98.26', '44.208.222.42', '255.255.255.0', 'available', '2326', '2025-12-30 09:05:50', '2025-12-30 09:05:50');
INSERT INTO `ip_master` (`id`, `network_ip`, `router_ip`, `site_ip`, `subnet_mask`, `status`, `created_by`, `created_at`, `updated_at`) VALUES ('1741', '72.199.94.144', '85.122.136.195', '137.96.76.226', '255.255.255.0', 'available', '2326', '2025-12-30 09:05:50', '2025-12-30 09:05:50');
INSERT INTO `ip_master` (`id`, `network_ip`, `router_ip`, `site_ip`, `subnet_mask`, `status`, `created_by`, `created_at`, `updated_at`) VALUES ('1742', '204.196.34.194', '100.139.158.242', '211.60.42.117', '255.255.255.0', 'available', '2326', '2025-12-30 09:05:50', '2025-12-30 09:05:50');
INSERT INTO `ip_master` (`id`, `network_ip`, `router_ip`, `site_ip`, `subnet_mask`, `status`, `created_by`, `created_at`, `updated_at`) VALUES ('1743', '173.32.153.167', '76.50.67.13', '47.214.212.154', '255.255.255.0', 'available', '2326', '2025-12-30 09:05:50', '2025-12-30 09:05:50');
INSERT INTO `ip_master` (`id`, `network_ip`, `router_ip`, `site_ip`, `subnet_mask`, `status`, `created_by`, `created_at`, `updated_at`) VALUES ('1744', '161.242.254.193', '20.186.192.13', '171.124.3.21', '255.255.255.0', 'available', '2326', '2025-12-30 09:05:50', '2025-12-30 09:05:50');
INSERT INTO `ip_master` (`id`, `network_ip`, `router_ip`, `site_ip`, `subnet_mask`, `status`, `created_by`, `created_at`, `updated_at`) VALUES ('1745', '128.22.179.171', '204.134.119.184', '92.177.240.44', '255.255.255.0', 'available', '2326', '2025-12-30 09:05:50', '2025-12-30 09:05:50');
INSERT INTO `ip_master` (`id`, `network_ip`, `router_ip`, `site_ip`, `subnet_mask`, `status`, `created_by`, `created_at`, `updated_at`) VALUES ('1746', '181.192.134.239', '150.239.224.30', '237.194.178.64', '255.255.255.0', 'available', '2326', '2025-12-30 09:05:50', '2025-12-30 09:05:50');
INSERT INTO `ip_master` (`id`, `network_ip`, `router_ip`, `site_ip`, `subnet_mask`, `status`, `created_by`, `created_at`, `updated_at`) VALUES ('1747', '224.41.204.127', '10.142.24.63', '72.188.16.100', '255.255.255.0', 'available', '2326', '2025-12-30 09:05:50', '2025-12-30 09:05:50');
INSERT INTO `ip_master` (`id`, `network_ip`, `router_ip`, `site_ip`, `subnet_mask`, `status`, `created_by`, `created_at`, `updated_at`) VALUES ('1748', '97.46.56.83', '91.100.126.200', '130.77.93.173', '255.255.255.0', 'available', '2326', '2025-12-30 09:05:50', '2025-12-30 09:05:50');
INSERT INTO `ip_master` (`id`, `network_ip`, `router_ip`, `site_ip`, `subnet_mask`, `status`, `created_by`, `created_at`, `updated_at`) VALUES ('1749', '16.208.145.218', '119.30.6.73', '162.59.70.93', '255.255.255.0', 'available', '2326', '2025-12-30 09:05:50', '2025-12-30 09:05:50');
INSERT INTO `ip_master` (`id`, `network_ip`, `router_ip`, `site_ip`, `subnet_mask`, `status`, `created_by`, `created_at`, `updated_at`) VALUES ('1750', '181.165.114.130', '124.235.23.122', '39.67.18.118', '255.255.255.0', 'available', '2326', '2025-12-30 09:05:50', '2025-12-30 09:05:50');
INSERT INTO `ip_master` (`id`, `network_ip`, `router_ip`, `site_ip`, `subnet_mask`, `status`, `created_by`, `created_at`, `updated_at`) VALUES ('1751', '202.204.26.178', '52.155.226.47', '101.240.8.181', '255.255.255.0', 'available', '2326', '2025-12-30 09:05:50', '2025-12-30 09:05:50');
INSERT INTO `ip_master` (`id`, `network_ip`, `router_ip`, `site_ip`, `subnet_mask`, `status`, `created_by`, `created_at`, `updated_at`) VALUES ('1752', '109.148.99.131', '142.105.221.188', '203.28.134.170', '255.255.255.0', 'available', '2326', '2025-12-30 09:05:50', '2025-12-30 09:05:50');
INSERT INTO `ip_master` (`id`, `network_ip`, `router_ip`, `site_ip`, `subnet_mask`, `status`, `created_by`, `created_at`, `updated_at`) VALUES ('1753', '167.28.187.79', '37.27.139.126', '105.59.49.14', '255.255.255.0', 'available', '2326', '2025-12-30 09:05:50', '2025-12-30 09:05:50');
INSERT INTO `ip_master` (`id`, `network_ip`, `router_ip`, `site_ip`, `subnet_mask`, `status`, `created_by`, `created_at`, `updated_at`) VALUES ('1754', '241.74.3.212', '201.108.62.164', '136.26.3.11', '255.255.255.0', 'available', '2326', '2025-12-30 09:05:50', '2025-12-30 09:05:50');
INSERT INTO `ip_master` (`id`, `network_ip`, `router_ip`, `site_ip`, `subnet_mask`, `status`, `created_by`, `created_at`, `updated_at`) VALUES ('1755', '131.140.105.37', '233.147.184.191', '217.4.102.51', '255.255.255.0', 'available', '2326', '2025-12-30 09:05:50', '2025-12-30 09:05:50');
INSERT INTO `ip_master` (`id`, `network_ip`, `router_ip`, `site_ip`, `subnet_mask`, `status`, `created_by`, `created_at`, `updated_at`) VALUES ('1876', '159.181.151.0', '52.184.62.211', '165.226.141.99', '255.255.255.0', 'available', '2326', '2025-12-30 09:07:49', '2025-12-30 09:07:49');

SET FOREIGN_KEY_CHECKS = 1;
