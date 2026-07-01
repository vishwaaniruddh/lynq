-- Database Table Export for `installation_material_receipts`
-- Generated: 2026-06-25 07:00:57
-- DB Name: if0_40845939_clarity_db

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `installation_material_receipts`;

CREATE TABLE `installation_material_receipts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `installation_id` int(11) NOT NULL COMMENT 'Reference to installations table',
  `confirmed_by` int(11) NOT NULL COMMENT 'Engineer who confirmed material receipt',
  `confirmed_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'When materials were confirmed received',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_installation_receipt` (`installation_id`),
  KEY `idx_installation` (`installation_id`),
  KEY `idx_confirmed_by` (`confirmed_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- No data found in `installation_material_receipts`

SET FOREIGN_KEY_CHECKS = 1;
