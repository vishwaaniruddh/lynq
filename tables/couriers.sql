-- Database Table Export for `couriers`
-- Generated: 2026-06-25 07:00:57
-- DB Name: if0_40845939_clarity_db

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `couriers`;

CREATE TABLE `couriers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `status` tinyint(1) DEFAULT 1 COMMENT '0=inactive, 1=active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_courier_name` (`name`),
  KEY `idx_status` (`status`),
  KEY `created_by` (`created_by`),
  KEY `updated_by` (`updated_by`),
  CONSTRAINT `couriers_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `couriers_ibfk_2` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `couriers`
INSERT INTO `couriers` (`id`, `name`, `status`, `created_at`, `updated_at`, `created_by`, `updated_by`) VALUES ('1', 'Aramex', '1', '2025-12-29 21:02:13', '2025-12-29 21:02:13', '2326', NULL);
INSERT INTO `couriers` (`id`, `name`, `status`, `created_at`, `updated_at`, `created_by`, `updated_by`) VALUES ('2', 'Bluedart', '1', '2025-12-29 21:02:21', '2025-12-29 21:02:21', '2326', NULL);
INSERT INTO `couriers` (`id`, `name`, `status`, `created_at`, `updated_at`, `created_by`, `updated_by`) VALUES ('3', 'Delhivery', '1', '2025-12-29 21:02:26', '2025-12-29 21:02:26', '2326', NULL);
INSERT INTO `couriers` (`id`, `name`, `status`, `created_at`, `updated_at`, `created_by`, `updated_by`) VALUES ('4', 'DTDC', '1', '2025-12-29 21:02:32', '2025-12-29 21:02:32', '2326', NULL);
INSERT INTO `couriers` (`id`, `name`, `status`, `created_at`, `updated_at`, `created_by`, `updated_by`) VALUES ('5', 'EcomExpress', '1', '2025-12-29 21:02:41', '2025-12-29 21:02:41', '2326', NULL);
INSERT INTO `couriers` (`id`, `name`, `status`, `created_at`, `updated_at`, `created_by`, `updated_by`) VALUES ('6', 'FedEx', '1', '2025-12-29 21:02:50', '2025-12-29 21:02:50', '2326', NULL);
INSERT INTO `couriers` (`id`, `name`, `status`, `created_at`, `updated_at`, `created_by`, `updated_by`) VALUES ('7', 'Gati', '1', '2025-12-29 21:03:01', '2025-12-29 21:03:01', '2326', NULL);
INSERT INTO `couriers` (`id`, `name`, `status`, `created_at`, `updated_at`, `created_by`, `updated_by`) VALUES ('8', 'India Post', '1', '2025-12-29 21:03:09', '2025-12-29 21:03:09', '2326', NULL);
INSERT INTO `couriers` (`id`, `name`, `status`, `created_at`, `updated_at`, `created_by`, `updated_by`) VALUES ('9', 'Nandan', '1', '2025-12-29 21:03:17', '2025-12-29 21:03:17', '2326', NULL);
INSERT INTO `couriers` (`id`, `name`, `status`, `created_at`, `updated_at`, `created_by`, `updated_by`) VALUES ('10', 'Shadowfax', '1', '2025-12-29 21:03:25', '2025-12-29 21:03:25', '2326', NULL);
INSERT INTO `couriers` (`id`, `name`, `status`, `created_at`, `updated_at`, `created_by`, `updated_by`) VALUES ('11', 'Trackon', '1', '2025-12-29 21:03:32', '2025-12-29 21:03:32', '2326', NULL);
INSERT INTO `couriers` (`id`, `name`, `status`, `created_at`, `updated_at`, `created_by`, `updated_by`) VALUES ('12', 'XpressBees', '1', '2025-12-29 21:03:39', '2025-12-29 21:03:39', '2326', NULL);

SET FOREIGN_KEY_CHECKS = 1;
