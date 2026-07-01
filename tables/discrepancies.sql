-- Database Table Export for `discrepancies`
-- Generated: 2026-06-25 07:00:57
-- DB Name: if0_40845939_clarity_db

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `discrepancies`;

CREATE TABLE `discrepancies` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `dispatch_id` int(11) NOT NULL,
  `pending_receive_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `asset_id` int(11) DEFAULT NULL,
  `expected_quantity` int(11) NOT NULL,
  `received_quantity` int(11) NOT NULL,
  `discrepancy_type` enum('shortage','damage','wrong_item','excess') NOT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('open','resolved','written_off') DEFAULT 'open',
  `resolved_at` timestamp NULL DEFAULT NULL,
  `resolved_by` int(11) DEFAULT NULL,
  `resolution_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `asset_id` (`asset_id`),
  KEY `resolved_by` (`resolved_by`),
  KEY `idx_status` (`status`),
  KEY `idx_dispatch` (`dispatch_id`),
  KEY `idx_pending_receive` (`pending_receive_id`),
  KEY `idx_product` (`product_id`),
  CONSTRAINT `discrepancies_ibfk_1` FOREIGN KEY (`dispatch_id`) REFERENCES `dispatches` (`id`) ON DELETE CASCADE,
  CONSTRAINT `discrepancies_ibfk_2` FOREIGN KEY (`pending_receive_id`) REFERENCES `pending_receives` (`id`) ON DELETE CASCADE,
  CONSTRAINT `discrepancies_ibfk_3` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  CONSTRAINT `discrepancies_ibfk_4` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`id`) ON DELETE SET NULL,
  CONSTRAINT `discrepancies_ibfk_5` FOREIGN KEY (`resolved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- No data found in `discrepancies`

SET FOREIGN_KEY_CHECKS = 1;
