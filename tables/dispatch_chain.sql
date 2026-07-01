-- Database Table Export for `dispatch_chain`
-- Generated: 2026-06-25 07:00:57
-- DB Name: if0_40845939_clarity_db

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `dispatch_chain`;

CREATE TABLE `dispatch_chain` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `asset_id` int(11) DEFAULT NULL COMMENT 'For serializable items',
  `product_id` int(11) NOT NULL,
  `dispatch_id` int(11) NOT NULL,
  `sequence_number` int(11) NOT NULL COMMENT 'Order in the chain for this item',
  `from_entity_type` enum('warehouse','company','user') NOT NULL,
  `from_entity_id` int(11) NOT NULL,
  `to_entity_type` enum('warehouse','company','user') NOT NULL,
  `to_entity_id` int(11) NOT NULL,
  `quantity` int(11) DEFAULT 1,
  `dispatch_date` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `acceptance_date` timestamp NULL DEFAULT NULL,
  `status` enum('dispatched','accepted','rejected') NOT NULL DEFAULT 'dispatched',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_asset` (`asset_id`),
  KEY `idx_product_entity` (`product_id`,`to_entity_type`,`to_entity_id`),
  KEY `idx_dispatch` (`dispatch_id`),
  KEY `idx_from_entity` (`from_entity_type`,`from_entity_id`),
  KEY `idx_to_entity` (`to_entity_type`,`to_entity_id`),
  KEY `idx_sequence` (`asset_id`,`sequence_number`),
  CONSTRAINT `dispatch_chain_ibfk_1` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`id`) ON DELETE SET NULL,
  CONSTRAINT `dispatch_chain_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  CONSTRAINT `dispatch_chain_ibfk_3` FOREIGN KEY (`dispatch_id`) REFERENCES `dispatches` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1631 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `dispatch_chain`
INSERT INTO `dispatch_chain` (`id`, `asset_id`, `product_id`, `dispatch_id`, `sequence_number`, `from_entity_type`, `from_entity_id`, `to_entity_type`, `to_entity_id`, `quantity`, `dispatch_date`, `acceptance_date`, `status`, `created_at`) VALUES ('1621', NULL, '5', '1584', '1', 'warehouse', '2', 'company', '2', '2', '2026-01-04 18:30:00', NULL, 'dispatched', '2026-01-05 03:37:05');
INSERT INTO `dispatch_chain` (`id`, `asset_id`, `product_id`, `dispatch_id`, `sequence_number`, `from_entity_type`, `from_entity_id`, `to_entity_type`, `to_entity_id`, `quantity`, `dispatch_date`, `acceptance_date`, `status`, `created_at`) VALUES ('1622', NULL, '4', '1584', '1', 'warehouse', '2', 'company', '2', '1', '2026-01-04 18:30:00', NULL, 'dispatched', '2026-01-05 03:37:05');
INSERT INTO `dispatch_chain` (`id`, `asset_id`, `product_id`, `dispatch_id`, `sequence_number`, `from_entity_type`, `from_entity_id`, `to_entity_type`, `to_entity_id`, `quantity`, `dispatch_date`, `acceptance_date`, `status`, `created_at`) VALUES ('1623', '102', '2', '1584', '1', 'warehouse', '2', 'company', '2', '1', '2026-01-04 18:30:00', NULL, 'dispatched', '2026-01-05 03:37:05');
INSERT INTO `dispatch_chain` (`id`, `asset_id`, `product_id`, `dispatch_id`, `sequence_number`, `from_entity_type`, `from_entity_id`, `to_entity_type`, `to_entity_id`, `quantity`, `dispatch_date`, `acceptance_date`, `status`, `created_at`) VALUES ('1624', '113', '3', '1584', '1', 'warehouse', '2', 'company', '2', '1', '2026-01-04 18:30:00', NULL, 'dispatched', '2026-01-05 03:37:05');
INSERT INTO `dispatch_chain` (`id`, `asset_id`, `product_id`, `dispatch_id`, `sequence_number`, `from_entity_type`, `from_entity_id`, `to_entity_type`, `to_entity_id`, `quantity`, `dispatch_date`, `acceptance_date`, `status`, `created_at`) VALUES ('1625', '92', '1', '1584', '1', 'warehouse', '2', 'company', '2', '1', '2026-01-04 18:30:00', NULL, 'dispatched', '2026-01-05 03:37:05');
INSERT INTO `dispatch_chain` (`id`, `asset_id`, `product_id`, `dispatch_id`, `sequence_number`, `from_entity_type`, `from_entity_id`, `to_entity_type`, `to_entity_id`, `quantity`, `dispatch_date`, `acceptance_date`, `status`, `created_at`) VALUES ('1626', NULL, '5', '1584', '2', 'warehouse', '2', 'company', '2', '2', '2026-01-04 18:30:00', '2026-01-05 03:39:10', 'accepted', '2026-01-05 03:39:10');
INSERT INTO `dispatch_chain` (`id`, `asset_id`, `product_id`, `dispatch_id`, `sequence_number`, `from_entity_type`, `from_entity_id`, `to_entity_type`, `to_entity_id`, `quantity`, `dispatch_date`, `acceptance_date`, `status`, `created_at`) VALUES ('1627', NULL, '4', '1584', '2', 'warehouse', '2', 'company', '2', '1', '2026-01-04 18:30:00', '2026-01-05 03:39:10', 'accepted', '2026-01-05 03:39:10');
INSERT INTO `dispatch_chain` (`id`, `asset_id`, `product_id`, `dispatch_id`, `sequence_number`, `from_entity_type`, `from_entity_id`, `to_entity_type`, `to_entity_id`, `quantity`, `dispatch_date`, `acceptance_date`, `status`, `created_at`) VALUES ('1628', '102', '2', '1584', '2', 'warehouse', '2', 'company', '2', '1', '2026-01-04 18:30:00', '2026-01-05 03:39:10', 'accepted', '2026-01-05 03:39:10');
INSERT INTO `dispatch_chain` (`id`, `asset_id`, `product_id`, `dispatch_id`, `sequence_number`, `from_entity_type`, `from_entity_id`, `to_entity_type`, `to_entity_id`, `quantity`, `dispatch_date`, `acceptance_date`, `status`, `created_at`) VALUES ('1629', '113', '3', '1584', '2', 'warehouse', '2', 'company', '2', '1', '2026-01-04 18:30:00', '2026-01-05 03:39:10', 'accepted', '2026-01-05 03:39:10');
INSERT INTO `dispatch_chain` (`id`, `asset_id`, `product_id`, `dispatch_id`, `sequence_number`, `from_entity_type`, `from_entity_id`, `to_entity_type`, `to_entity_id`, `quantity`, `dispatch_date`, `acceptance_date`, `status`, `created_at`) VALUES ('1630', '92', '1', '1584', '2', 'warehouse', '2', 'company', '2', '1', '2026-01-04 18:30:00', '2026-01-05 03:39:10', 'accepted', '2026-01-05 03:39:10');

SET FOREIGN_KEY_CHECKS = 1;
