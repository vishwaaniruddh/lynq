-- Database Table Export for `installation_section_remarks`
-- Generated: 2026-06-25 07:00:57
-- DB Name: if0_40845939_clarity_db

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `installation_section_remarks`;

CREATE TABLE `installation_section_remarks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `installation_id` int(11) NOT NULL COMMENT 'Reference to installations table',
  `section` varchar(50) NOT NULL COMMENT 'Section identifier',
  `reviewer_id` int(11) NOT NULL COMMENT 'User who performed the review',
  `reviewer_level` enum('contractor','adv') NOT NULL COMMENT 'Level of reviewer',
  `review_type` enum('approval','rejection') NOT NULL COMMENT 'Type of review action',
  `remark` text DEFAULT NULL COMMENT 'Review remarks (required for rejections, min 10 chars)',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_installation` (`installation_id`),
  KEY `idx_section` (`section`),
  KEY `idx_reviewer` (`reviewer_id`),
  KEY `idx_reviewer_level` (`reviewer_level`),
  KEY `idx_review_type` (`review_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- No data found in `installation_section_remarks`

SET FOREIGN_KEY_CHECKS = 1;
