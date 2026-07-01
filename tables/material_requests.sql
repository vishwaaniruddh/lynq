-- Database Table Export for `material_requests`
-- Generated: 2026-06-25 07:00:57
-- DB Name: if0_40845939_clarity_db

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `material_requests`;

CREATE TABLE `material_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `site_id` int(11) NOT NULL,
  `material_master_id` int(11) NOT NULL,
  `status` enum('requested','approved','rejected','dispatched','received') DEFAULT 'requested',
  `company_id` int(11) NOT NULL COMMENT 'Company isolation',
  `requested_by` int(11) NOT NULL COMMENT 'User who created the request',
  `requested_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `approved_by` int(11) DEFAULT NULL COMMENT 'User who approved',
  `approved_at` timestamp NULL DEFAULT NULL,
  `rejected_by` int(11) DEFAULT NULL COMMENT 'User who rejected',
  `rejected_at` timestamp NULL DEFAULT NULL,
  `dispatched_at` timestamp NULL DEFAULT NULL,
  `received_at` timestamp NULL DEFAULT NULL,
  `received_by` int(11) DEFAULT NULL COMMENT 'Engineer who confirmed receipt',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_site_id` (`site_id`),
  KEY `idx_status` (`status`),
  KEY `idx_company_id` (`company_id`),
  KEY `idx_material_master_id` (`material_master_id`),
  KEY `idx_requested_by` (`requested_by`),
  KEY `idx_site_status` (`site_id`,`status`),
  KEY `idx_company_status` (`company_id`,`status`),
  KEY `approved_by` (`approved_by`),
  KEY `received_by` (`received_by`),
  KEY `idx_rejected_by` (`rejected_by`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `material_requests`
INSERT INTO `material_requests` (`id`, `site_id`, `material_master_id`, `status`, `company_id`, `requested_by`, `requested_at`, `approved_by`, `approved_at`, `rejected_by`, `rejected_at`, `dispatched_at`, `received_at`, `received_by`, `notes`, `created_at`, `updated_at`) VALUES ('3', '1', '1', 'dispatched', '1', '2326', '2026-01-04 00:09:05', '2326', '2026-01-04 00:09:24', NULL, NULL, '2026-01-05 03:37:05', NULL, NULL, NULL, '2026-01-04 00:09:05', '2026-01-05 03:37:05');
INSERT INTO `material_requests` (`id`, `site_id`, `material_master_id`, `status`, `company_id`, `requested_by`, `requested_at`, `approved_by`, `approved_at`, `rejected_by`, `rejected_at`, `dispatched_at`, `received_at`, `received_by`, `notes`, `created_at`, `updated_at`) VALUES ('4', '7', '1', 'approved', '1', '2326', '2026-01-05 12:41:15', '2326', '2026-01-05 12:41:27', NULL, NULL, NULL, NULL, NULL, NULL, '2026-01-05 12:41:15', '2026-01-05 12:41:27');

SET FOREIGN_KEY_CHECKS = 1;
