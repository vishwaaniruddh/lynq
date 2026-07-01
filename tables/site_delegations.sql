-- Database Table Export for `site_delegations`
-- Generated: 2026-06-25 07:00:57
-- DB Name: if0_40845939_clarity_db

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `site_delegations`;

CREATE TABLE `site_delegations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `site_id` int(11) NOT NULL,
  `contractor_id` int(11) NOT NULL COMMENT 'Company ID of contractor',
  `delegated_by` int(11) NOT NULL COMMENT 'User ID who delegated',
  `delegated_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('pending','accepted','rejected') DEFAULT 'pending',
  `rejection_notes` text DEFAULT NULL,
  `responded_by` int(11) DEFAULT NULL,
  `responded_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_site` (`site_id`),
  KEY `idx_contractor` (`contractor_id`),
  KEY `idx_status` (`status`),
  KEY `idx_delegated_at` (`delegated_at`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `site_delegations`
INSERT INTO `site_delegations` (`id`, `site_id`, `contractor_id`, `delegated_by`, `delegated_at`, `status`, `rejection_notes`, `responded_by`, `responded_at`, `created_at`, `updated_at`) VALUES ('8', '1', '2', '2326', '2026-01-05 04:26:34', 'pending', NULL, NULL, NULL, '2026-01-05 04:26:34', NULL);

SET FOREIGN_KEY_CHECKS = 1;
