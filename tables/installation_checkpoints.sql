-- Database Table Export for `installation_checkpoints`
-- Generated: 2026-06-25 07:00:57
-- DB Name: if0_40845939_clarity_db

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `installation_checkpoints`;

CREATE TABLE `installation_checkpoints` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `installation_id` int(11) NOT NULL COMMENT 'Reference to installations table',
  `section` varchar(50) NOT NULL COMMENT 'Section identifier (router_fixed, router_status, adaptor, etc.)',
  `contractor_status` enum('pending','approved','rejected') DEFAULT 'pending' COMMENT 'Contractor review status',
  `contractor_reviewer_id` int(11) DEFAULT NULL COMMENT 'Contractor reviewer user ID',
  `contractor_reviewed_at` timestamp NULL DEFAULT NULL COMMENT 'When contractor reviewed',
  `adv_status` enum('pending','approved','rejected') DEFAULT 'pending' COMMENT 'ADV review status',
  `adv_reviewer_id` int(11) DEFAULT NULL COMMENT 'ADV reviewer user ID',
  `adv_reviewed_at` timestamp NULL DEFAULT NULL COMMENT 'When ADV reviewed',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_installation_section` (`installation_id`,`section`),
  KEY `idx_installation` (`installation_id`),
  KEY `idx_section` (`section`),
  KEY `idx_contractor_status` (`contractor_status`),
  KEY `idx_adv_status` (`adv_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- No data found in `installation_checkpoints`

SET FOREIGN_KEY_CHECKS = 1;
