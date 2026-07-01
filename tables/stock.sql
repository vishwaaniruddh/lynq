-- Database Table Export for `stock`
-- Generated: 2026-06-25 07:00:57
-- DB Name: if0_40845939_clarity_db

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `stock`;

CREATE TABLE `stock` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `warehouse_id` int(11) NOT NULL,
  `quantity` int(11) DEFAULT 0,
  `reserved_quantity` int(11) DEFAULT 0,
  `updated_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_product_warehouse` (`product_id`,`warehouse_id`),
  KEY `updated_by` (`updated_by`),
  KEY `idx_product_id` (`product_id`),
  KEY `idx_warehouse_id` (`warehouse_id`)
) ENGINE=InnoDB AUTO_INCREMENT=789 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `stock`
INSERT INTO `stock` (`id`, `product_id`, `warehouse_id`, `quantity`, `reserved_quantity`, `updated_by`, `created_at`, `updated_at`) VALUES ('5', '4', '1', '120', '0', NULL, '2026-01-04 00:04:28', '2026-01-04 00:04:41');
INSERT INTO `stock` (`id`, `product_id`, `warehouse_id`, `quantity`, `reserved_quantity`, `updated_by`, `created_at`, `updated_at`) VALUES ('6', '4', '2', '83', '0', '2326', '2026-01-04 00:04:28', '2026-01-05 03:37:05');
INSERT INTO `stock` (`id`, `product_id`, `warehouse_id`, `quantity`, `reserved_quantity`, `updated_by`, `created_at`, `updated_at`) VALUES ('7', '5', '1', '98', '0', NULL, '2026-01-04 00:04:28', '2026-01-04 00:04:41');
INSERT INTO `stock` (`id`, `product_id`, `warehouse_id`, `quantity`, `reserved_quantity`, `updated_by`, `created_at`, `updated_at`) VALUES ('8', '5', '2', '180', '0', '2326', '2026-01-04 00:04:28', '2026-01-05 03:37:05');

SET FOREIGN_KEY_CHECKS = 1;
