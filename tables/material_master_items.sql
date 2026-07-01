-- Database Table Export for `material_master_items`
-- Generated: 2026-06-25 07:00:57
-- DB Name: if0_40845939_clarity_db

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `material_master_items`;

CREATE TABLE `material_master_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `material_master_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL COMMENT 'Required quantity for this product',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_master_product` (`material_master_id`,`product_id`),
  KEY `idx_material_master_id` (`material_master_id`),
  KEY `idx_product_id` (`product_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `material_master_items`
INSERT INTO `material_master_items` (`id`, `material_master_id`, `product_id`, `quantity`, `created_at`) VALUES ('1', '1', '1', '1', '2026-01-03 22:15:33');
INSERT INTO `material_master_items` (`id`, `material_master_id`, `product_id`, `quantity`, `created_at`) VALUES ('2', '1', '3', '1', '2026-01-03 22:15:33');
INSERT INTO `material_master_items` (`id`, `material_master_id`, `product_id`, `quantity`, `created_at`) VALUES ('3', '1', '2', '1', '2026-01-03 22:15:33');
INSERT INTO `material_master_items` (`id`, `material_master_id`, `product_id`, `quantity`, `created_at`) VALUES ('4', '1', '5', '2', '2026-01-03 22:15:33');
INSERT INTO `material_master_items` (`id`, `material_master_id`, `product_id`, `quantity`, `created_at`) VALUES ('5', '1', '4', '1', '2026-01-03 22:15:33');

SET FOREIGN_KEY_CHECKS = 1;
