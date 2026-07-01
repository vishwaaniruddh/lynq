-- Database Table Export for `pending_receive_items`
-- Generated: 2026-06-25 07:00:57
-- DB Name: if0_40845939_clarity_db

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `pending_receive_items`;

CREATE TABLE `pending_receive_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `pending_receive_id` int(11) NOT NULL,
  `dispatch_item_id` int(11) NOT NULL,
  `expected_quantity` int(11) NOT NULL,
  `received_quantity` int(11) DEFAULT 0,
  `status` enum('pending','accepted','rejected','partial') DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_pending_receive` (`pending_receive_id`),
  KEY `idx_dispatch_item` (`dispatch_item_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=1516 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `pending_receive_items`
INSERT INTO `pending_receive_items` (`id`, `pending_receive_id`, `dispatch_item_id`, `expected_quantity`, `received_quantity`, `status`, `notes`, `created_at`, `updated_at`) VALUES ('1511', '1504', '1511', '2', '2', 'accepted', NULL, '2026-01-05 03:37:05', '2026-01-05 03:39:10');
INSERT INTO `pending_receive_items` (`id`, `pending_receive_id`, `dispatch_item_id`, `expected_quantity`, `received_quantity`, `status`, `notes`, `created_at`, `updated_at`) VALUES ('1512', '1504', '1512', '1', '1', 'accepted', NULL, '2026-01-05 03:37:05', '2026-01-05 03:39:10');
INSERT INTO `pending_receive_items` (`id`, `pending_receive_id`, `dispatch_item_id`, `expected_quantity`, `received_quantity`, `status`, `notes`, `created_at`, `updated_at`) VALUES ('1513', '1504', '1513', '1', '1', 'accepted', NULL, '2026-01-05 03:37:05', '2026-01-05 03:39:10');
INSERT INTO `pending_receive_items` (`id`, `pending_receive_id`, `dispatch_item_id`, `expected_quantity`, `received_quantity`, `status`, `notes`, `created_at`, `updated_at`) VALUES ('1514', '1504', '1514', '1', '1', 'accepted', NULL, '2026-01-05 03:37:05', '2026-01-05 03:39:10');
INSERT INTO `pending_receive_items` (`id`, `pending_receive_id`, `dispatch_item_id`, `expected_quantity`, `received_quantity`, `status`, `notes`, `created_at`, `updated_at`) VALUES ('1515', '1504', '1515', '1', '1', 'accepted', NULL, '2026-01-05 03:37:05', '2026-01-05 03:39:10');

SET FOREIGN_KEY_CHECKS = 1;
