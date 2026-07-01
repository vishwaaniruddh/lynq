-- Database Table Export for `feasibility_reviews`
-- Generated: 2026-06-25 07:00:57
-- DB Name: if0_40845939_clarity_db

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `feasibility_reviews`;

CREATE TABLE `feasibility_reviews` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `feasibility_id` int(11) NOT NULL COMMENT 'Reference to feasibility_checks',
  `reviewer_id` int(11) NOT NULL COMMENT 'User ID of the reviewer',
  `reviewer_role` enum('contractor_admin','contractor_manager','adv') NOT NULL COMMENT 'Role of the reviewer',
  `review_type` enum('approval','rejection') NOT NULL COMMENT 'Type of review action',
  `rejection_type` enum('overall','section_specific') DEFAULT NULL COMMENT 'Type of rejection (null if approval)',
  `rejected_sections` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Array of section names that were rejected' CHECK (json_valid(`rejected_sections`)),
  `reason` text DEFAULT NULL COMMENT 'Required for rejections, min 10 characters',
  `comments` text DEFAULT NULL COMMENT 'Optional comments for approvals',
  `reviewed_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'When the review was submitted',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_feasibility` (`feasibility_id`),
  KEY `idx_reviewer` (`reviewer_id`),
  KEY `idx_review_type` (`review_type`),
  KEY `idx_reviewed_at` (`reviewed_at`),
  CONSTRAINT `feasibility_reviews_ibfk_1` FOREIGN KEY (`feasibility_id`) REFERENCES `feasibility_checks` (`id`) ON DELETE CASCADE,
  CONSTRAINT `feasibility_reviews_ibfk_2` FOREIGN KEY (`reviewer_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- No data found in `feasibility_reviews`

SET FOREIGN_KEY_CHECKS = 1;
