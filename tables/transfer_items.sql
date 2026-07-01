-- Database Table Export for `transfer_items`
-- Generated: 2026-06-25 07:00:57
-- DB Name: if0_40845939_clarity_db

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `transfer_items`;

CREATE TABLE `transfer_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `transfer_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `asset_id` int(11) DEFAULT NULL COMMENT 'For serializable items, references specific asset',
  `quantity` int(11) DEFAULT 1 COMMENT 'For non-serializable items, quantity transferred',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_transfer_id` (`transfer_id`),
  KEY `idx_product_id` (`product_id`),
  KEY `idx_asset_id` (`asset_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1190 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- No data found in `transfer_items`

SET FOREIGN_KEY_CHECKS = 1;
