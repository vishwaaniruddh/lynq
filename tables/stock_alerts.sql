-- Database Table Export for `stock_alerts`
-- Generated: 2026-06-25 07:00:57
-- DB Name: if0_40845939_clarity_db

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `stock_alerts`;

CREATE TABLE `stock_alerts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `warehouse_id` int(11) NOT NULL,
  `alert_type` enum('low_stock','overdue_repair') NOT NULL,
  `current_value` int(11) DEFAULT NULL COMMENT 'Current stock quantity or days overdue',
  `threshold_value` int(11) DEFAULT NULL COMMENT 'Threshold that triggered the alert',
  `status` enum('active','cleared') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `cleared_at` timestamp NULL DEFAULT NULL,
  `cleared_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `cleared_by` (`cleared_by`),
  KEY `idx_product_id` (`product_id`),
  KEY `idx_warehouse_id` (`warehouse_id`),
  KEY `idx_alert_type` (`alert_type`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- No data found in `stock_alerts`

SET FOREIGN_KEY_CHECKS = 1;
