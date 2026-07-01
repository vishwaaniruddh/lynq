-- Database Table Export for `installation_notifications`
-- Generated: 2026-06-25 07:00:57
-- DB Name: if0_40845939_clarity_db

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `installation_notifications`;

CREATE TABLE `installation_notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL COMMENT 'User to notify',
  `notification_type` enum('installation_initiated','section_rejected','adv_rejected','contractor_rejected','adv_approved','contractor_approved') NOT NULL COMMENT 'Type of notification',
  `installation_id` int(11) NOT NULL COMMENT 'Reference to installations table',
  `site_id` int(11) DEFAULT NULL COMMENT 'Reference to sites table',
  `section` varchar(50) DEFAULT NULL COMMENT 'Section identifier (for rejection notifications)',
  `title` varchar(255) NOT NULL COMMENT 'Notification title',
  `message` text DEFAULT NULL COMMENT 'Notification message',
  `is_read` tinyint(1) DEFAULT 0 COMMENT 'Whether notification has been read',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_unread` (`user_id`,`is_read`),
  KEY `idx_notification_type` (`notification_type`),
  KEY `idx_installation` (`installation_id`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- No data found in `installation_notifications`

SET FOREIGN_KEY_CHECKS = 1;
