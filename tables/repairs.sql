-- Database Table Export for `repairs`
-- Generated: 2026-06-25 07:00:57
-- DB Name: if0_40845939_clarity_db

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `repairs`;

CREATE TABLE `repairs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `asset_id` int(11) NOT NULL,
  `repair_vendor` varchar(150) DEFAULT NULL,
  `estimated_cost` decimal(10,2) DEFAULT NULL,
  `actual_cost` decimal(10,2) DEFAULT NULL,
  `send_date` date NOT NULL,
  `expected_return_date` date DEFAULT NULL,
  `actual_return_date` date DEFAULT NULL,
  `status` enum('pending','in_progress','completed','cancelled') DEFAULT 'pending',
  `repair_notes` text DEFAULT NULL COMMENT 'Notes about the repair work',
  `diagnosis` text DEFAULT NULL COMMENT 'Initial diagnosis of the issue',
  `resolution` text DEFAULT NULL COMMENT 'Description of repair work done',
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `created_by` (`created_by`),
  KEY `updated_by` (`updated_by`),
  KEY `idx_asset_id` (`asset_id`),
  KEY `idx_status` (`status`),
  KEY `idx_send_date` (`send_date`),
  KEY `idx_expected_return_date` (`expected_return_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- No data found in `repairs`

SET FOREIGN_KEY_CHECKS = 1;
