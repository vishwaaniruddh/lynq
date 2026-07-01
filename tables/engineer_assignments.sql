-- Database Table Export for `engineer_assignments`
-- Generated: 2026-06-25 07:00:57
-- DB Name: if0_40845939_clarity_db

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `engineer_assignments`;

CREATE TABLE `engineer_assignments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `site_id` int(11) NOT NULL,
  `delegation_id` int(11) NOT NULL COMMENT 'Reference to accepted delegation',
  `engineer_id` int(11) NOT NULL COMMENT 'User ID of engineer',
  `assigned_by` int(11) NOT NULL COMMENT 'User ID who assigned',
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('assigned','in_progress','completed') DEFAULT 'assigned',
  `feasibility_status` enum('pending_eta','eta_submitted','ada_submitted','feasibility_completed','pending_contractor_review','contractor_approved','contractor_rejected','adv_approved','adv_rejected') DEFAULT 'pending_eta' COMMENT 'Feasibility workflow status including approval workflow',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_site` (`site_id`),
  KEY `idx_delegation` (`delegation_id`),
  KEY `idx_engineer` (`engineer_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- No data found in `engineer_assignments`

SET FOREIGN_KEY_CHECKS = 1;
