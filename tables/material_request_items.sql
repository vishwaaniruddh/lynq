-- Database Table Export for `material_request_items`
-- Generated: 2026-06-25 07:00:57
-- DB Name: if0_40845939_clarity_db

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `material_request_items`;

CREATE TABLE `material_request_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `material_request_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity_requested` int(11) NOT NULL COMMENT 'Quantity requested from material master',
  `quantity_dispatched` int(11) DEFAULT 0 COMMENT 'Quantity actually dispatched',
  `quantity_received` int(11) DEFAULT 0 COMMENT 'Quantity confirmed received',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_request_product` (`material_request_id`,`product_id`),
  KEY `idx_material_request_id` (`material_request_id`),
  KEY `idx_product_id` (`product_id`)
) ENGINE=InnoDB AUTO_INCREMENT=29 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `material_request_items`
INSERT INTO `material_request_items` (`id`, `material_request_id`, `product_id`, `quantity_requested`, `quantity_dispatched`, `quantity_received`, `created_at`) VALUES ('15', '3', '1', '1', '1', '0', '2026-01-04 00:09:05');
INSERT INTO `material_request_items` (`id`, `material_request_id`, `product_id`, `quantity_requested`, `quantity_dispatched`, `quantity_received`, `created_at`) VALUES ('16', '3', '3', '1', '1', '0', '2026-01-04 00:09:05');
INSERT INTO `material_request_items` (`id`, `material_request_id`, `product_id`, `quantity_requested`, `quantity_dispatched`, `quantity_received`, `created_at`) VALUES ('17', '3', '2', '1', '1', '0', '2026-01-04 00:09:05');
INSERT INTO `material_request_items` (`id`, `material_request_id`, `product_id`, `quantity_requested`, `quantity_dispatched`, `quantity_received`, `created_at`) VALUES ('18', '3', '5', '2', '2', '0', '2026-01-04 00:09:05');
INSERT INTO `material_request_items` (`id`, `material_request_id`, `product_id`, `quantity_requested`, `quantity_dispatched`, `quantity_received`, `created_at`) VALUES ('19', '3', '4', '1', '1', '0', '2026-01-04 00:09:05');
INSERT INTO `material_request_items` (`id`, `material_request_id`, `product_id`, `quantity_requested`, `quantity_dispatched`, `quantity_received`, `created_at`) VALUES ('22', '4', '1', '1', '0', '0', '2026-01-05 12:41:15');
INSERT INTO `material_request_items` (`id`, `material_request_id`, `product_id`, `quantity_requested`, `quantity_dispatched`, `quantity_received`, `created_at`) VALUES ('23', '4', '3', '1', '0', '0', '2026-01-05 12:41:15');
INSERT INTO `material_request_items` (`id`, `material_request_id`, `product_id`, `quantity_requested`, `quantity_dispatched`, `quantity_received`, `created_at`) VALUES ('24', '4', '2', '1', '0', '0', '2026-01-05 12:41:15');
INSERT INTO `material_request_items` (`id`, `material_request_id`, `product_id`, `quantity_requested`, `quantity_dispatched`, `quantity_received`, `created_at`) VALUES ('25', '4', '5', '2', '0', '0', '2026-01-05 12:41:15');
INSERT INTO `material_request_items` (`id`, `material_request_id`, `product_id`, `quantity_requested`, `quantity_dispatched`, `quantity_received`, `created_at`) VALUES ('26', '4', '4', '1', '0', '0', '2026-01-05 12:41:15');

SET FOREIGN_KEY_CHECKS = 1;
