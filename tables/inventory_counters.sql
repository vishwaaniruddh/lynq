-- Database Table Export for `inventory_counters`
-- Generated: 2026-06-25 07:00:57
-- DB Name: if0_40845939_clarity_db

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `inventory_counters`;

CREATE TABLE `inventory_counters` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `entity_type` enum('warehouse','company','user') NOT NULL,
  `entity_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) DEFAULT 0,
  `pending_out` int(11) DEFAULT 0 COMMENT 'Quantity in pending outgoing dispatches',
  `pending_in` int(11) DEFAULT 0 COMMENT 'Quantity in pending incoming receives',
  `last_updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_counter` (`entity_type`,`entity_id`,`product_id`),
  KEY `idx_entity` (`entity_type`,`entity_id`),
  KEY `idx_product` (`product_id`),
  CONSTRAINT `inventory_counters_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=812 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `inventory_counters`
INSERT INTO `inventory_counters` (`id`, `entity_type`, `entity_id`, `product_id`, `quantity`, `pending_out`, `pending_in`, `last_updated_at`, `created_at`) VALUES ('106', 'warehouse', '843', '846', '118', '0', '0', '2026-01-04 01:15:37', '2026-01-04 01:15:37');
INSERT INTO `inventory_counters` (`id`, `entity_type`, `entity_id`, `product_id`, `quantity`, `pending_out`, `pending_in`, `last_updated_at`, `created_at`) VALUES ('807', 'company', '2', '5', '2', '0', '0', '2026-01-05 03:39:10', '2026-01-05 03:39:10');
INSERT INTO `inventory_counters` (`id`, `entity_type`, `entity_id`, `product_id`, `quantity`, `pending_out`, `pending_in`, `last_updated_at`, `created_at`) VALUES ('808', 'company', '2', '4', '1', '0', '0', '2026-01-05 03:39:10', '2026-01-05 03:39:10');
INSERT INTO `inventory_counters` (`id`, `entity_type`, `entity_id`, `product_id`, `quantity`, `pending_out`, `pending_in`, `last_updated_at`, `created_at`) VALUES ('809', 'company', '2', '2', '1', '0', '0', '2026-01-05 03:39:10', '2026-01-05 03:39:10');
INSERT INTO `inventory_counters` (`id`, `entity_type`, `entity_id`, `product_id`, `quantity`, `pending_out`, `pending_in`, `last_updated_at`, `created_at`) VALUES ('810', 'company', '2', '3', '1', '0', '0', '2026-01-05 03:39:10', '2026-01-05 03:39:10');
INSERT INTO `inventory_counters` (`id`, `entity_type`, `entity_id`, `product_id`, `quantity`, `pending_out`, `pending_in`, `last_updated_at`, `created_at`) VALUES ('811', 'company', '2', '1', '1', '0', '0', '2026-01-05 03:39:10', '2026-01-05 03:39:10');

SET FOREIGN_KEY_CHECKS = 1;
