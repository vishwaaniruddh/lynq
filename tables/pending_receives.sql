-- Database Table Export for `pending_receives`
-- Generated: 2026-06-25 07:00:57
-- DB Name: if0_40845939_clarity_db

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `pending_receives`;

CREATE TABLE `pending_receives` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `dispatch_id` int(11) NOT NULL,
  `recipient_type` enum('warehouse','company','user') NOT NULL,
  `recipient_id` int(11) NOT NULL,
  `status` enum('pending','accepted','rejected','partial') DEFAULT 'pending',
  `rejection_reason` text DEFAULT NULL,
  `accepted_at` timestamp NULL DEFAULT NULL,
  `accepted_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `accepted_by` (`accepted_by`),
  KEY `idx_recipient` (`recipient_type`,`recipient_id`),
  KEY `idx_status` (`status`),
  KEY `idx_dispatch` (`dispatch_id`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=1505 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `pending_receives`
INSERT INTO `pending_receives` (`id`, `dispatch_id`, `recipient_type`, `recipient_id`, `status`, `rejection_reason`, `accepted_at`, `accepted_by`, `created_at`, `updated_at`) VALUES ('1504', '1584', 'company', '2', 'accepted', NULL, '2026-01-05 03:39:10', '19330', '2026-01-05 03:37:05', '2026-01-05 03:39:10');

SET FOREIGN_KEY_CHECKS = 1;
