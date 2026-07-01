-- Database Table Export for `products`
-- Generated: 2026-06-25 07:00:57
-- DB Name: if0_40845939_clarity_db

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `products`;

CREATE TABLE `products` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `unit_of_measure` varchar(50) NOT NULL,
  `inventory_type` enum('INTERNAL','SITE') NOT NULL,
  `is_serializable` tinyint(1) DEFAULT 0,
  `is_repairable` tinyint(1) DEFAULT 0,
  `low_stock_threshold` int(11) DEFAULT 0,
  `status` enum('active','inactive') DEFAULT 'active',
  `description` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `created_by` (`created_by`),
  KEY `updated_by` (`updated_by`),
  KEY `idx_category_id` (`category_id`),
  KEY `idx_inventory_type` (`inventory_type`),
  KEY `idx_is_serializable` (`is_serializable`),
  KEY `idx_is_repairable` (`is_repairable`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=2037 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `products`
INSERT INTO `products` (`id`, `name`, `category_id`, `unit_of_measure`, `inventory_type`, `is_serializable`, `is_repairable`, `low_stock_threshold`, `status`, `description`, `created_by`, `updated_by`, `created_at`, `updated_at`) VALUES ('1', 'Router', '1', 'pcs', 'SITE', '1', '1', '20', 'active', 'Routers', '2326', NULL, '2026-01-03 22:04:54', '2026-01-03 22:04:54');
INSERT INTO `products` (`id`, `name`, `category_id`, `unit_of_measure`, `inventory_type`, `is_serializable`, `is_repairable`, `low_stock_threshold`, `status`, `description`, `created_by`, `updated_by`, `created_at`, `updated_at`) VALUES ('2', 'Airtel Sim Cards', '2', 'pcs', 'SITE', '1', '0', '100', 'active', 'sim card', '2326', NULL, '2026-01-03 22:05:33', '2026-01-03 22:05:33');
INSERT INTO `products` (`id`, `name`, `category_id`, `unit_of_measure`, `inventory_type`, `is_serializable`, `is_repairable`, `low_stock_threshold`, `status`, `description`, `created_by`, `updated_by`, `created_at`, `updated_at`) VALUES ('3', 'Jio Sim Cards', '2', 'pcs', 'SITE', '1', '0', '100', 'active', '', '2326', NULL, '2026-01-03 22:05:59', '2026-01-03 22:05:59');
INSERT INTO `products` (`id`, `name`, `category_id`, `unit_of_measure`, `inventory_type`, `is_serializable`, `is_repairable`, `low_stock_threshold`, `status`, `description`, `created_by`, `updated_by`, `created_at`, `updated_at`) VALUES ('4', '6U Rack with One Additional Tray', '3', 'pcs', 'SITE', '0', '0', '10', 'active', '', '2326', NULL, '2026-01-03 22:09:50', '2026-01-03 22:09:50');
INSERT INTO `products` (`id`, `name`, `category_id`, `unit_of_measure`, `inventory_type`, `is_serializable`, `is_repairable`, `low_stock_threshold`, `status`, `description`, `created_by`, `updated_by`, `created_at`, `updated_at`) VALUES ('5', '1m Patch Cord', '5', 'm', 'SITE', '0', '0', '100', 'active', '', '2326', NULL, '2026-01-03 22:14:00', '2026-01-03 22:14:00');
INSERT INTO `products` (`id`, `name`, `category_id`, `unit_of_measure`, `inventory_type`, `is_serializable`, `is_repairable`, `low_stock_threshold`, `status`, `description`, `created_by`, `updated_by`, `created_at`, `updated_at`) VALUES ('846', 'Test Product xWOLHm7G', NULL, 'unit', 'INTERNAL', '0', '0', '10', 'active', NULL, NULL, NULL, '2026-01-04 01:15:37', '2026-01-04 01:15:37');

SET FOREIGN_KEY_CHECKS = 1;
