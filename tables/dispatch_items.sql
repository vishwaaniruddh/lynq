-- Database Table Export for `dispatch_items`
-- Generated: 2026-06-25 07:00:57
-- DB Name: if0_40845939_clarity_db

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `dispatch_items`;

CREATE TABLE `dispatch_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `dispatch_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `asset_id` int(11) DEFAULT NULL COMMENT 'For serializable items, references specific asset',
  `quantity` int(11) DEFAULT 1 COMMENT 'For non-serializable items, quantity dispatched',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_dispatch_id` (`dispatch_id`),
  KEY `idx_product_id` (`product_id`),
  KEY `idx_asset_id` (`asset_id`),
  CONSTRAINT `dispatch_items_ibfk_1` FOREIGN KEY (`dispatch_id`) REFERENCES `dispatches` (`id`) ON DELETE CASCADE,
  CONSTRAINT `dispatch_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  CONSTRAINT `dispatch_items_ibfk_3` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=1516 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `dispatch_items`
INSERT INTO `dispatch_items` (`id`, `dispatch_id`, `product_id`, `asset_id`, `quantity`, `created_at`) VALUES ('1511', '1584', '5', NULL, '2', '2026-01-05 03:37:05');
INSERT INTO `dispatch_items` (`id`, `dispatch_id`, `product_id`, `asset_id`, `quantity`, `created_at`) VALUES ('1512', '1584', '4', NULL, '1', '2026-01-05 03:37:05');
INSERT INTO `dispatch_items` (`id`, `dispatch_id`, `product_id`, `asset_id`, `quantity`, `created_at`) VALUES ('1513', '1584', '2', '102', '1', '2026-01-05 03:37:05');
INSERT INTO `dispatch_items` (`id`, `dispatch_id`, `product_id`, `asset_id`, `quantity`, `created_at`) VALUES ('1514', '1584', '3', '113', '1', '2026-01-05 03:37:05');
INSERT INTO `dispatch_items` (`id`, `dispatch_id`, `product_id`, `asset_id`, `quantity`, `created_at`) VALUES ('1515', '1584', '1', '92', '1', '2026-01-05 03:37:05');

SET FOREIGN_KEY_CHECKS = 1;
