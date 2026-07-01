-- Database Table Export for `inventory_notifications`
-- Generated: 2026-06-25 07:00:57
-- DB Name: if0_40845939_clarity_db

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `inventory_notifications`;

CREATE TABLE `inventory_notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `notification_type` enum('pending_receive','accepted','rejected','overdue','discrepancy') NOT NULL,
  `dispatch_id` int(11) DEFAULT NULL,
  `pending_receive_id` int(11) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `message` text DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `dispatch_id` (`dispatch_id`),
  KEY `pending_receive_id` (`pending_receive_id`),
  KEY `idx_user_unread` (`user_id`,`is_read`),
  KEY `idx_notification_type` (`notification_type`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=1009 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `inventory_notifications`
INSERT INTO `inventory_notifications` (`id`, `user_id`, `notification_type`, `dispatch_id`, `pending_receive_id`, `title`, `message`, `is_read`, `created_at`) VALUES ('1', '19330', 'pending_receive', '1', '1', 'New Materials Pending', 'You have new materials pending from Mumbai Main Warehouse. Please review and accept or reject.', '0', '2026-01-03 22:22:26');
INSERT INTO `inventory_notifications` (`id`, `user_id`, `notification_type`, `dispatch_id`, `pending_receive_id`, `title`, `message`, `is_read`, `created_at`) VALUES ('2', '19330', 'pending_receive', '2', '2', 'New Materials Pending', 'You have new materials pending from Delhi Warehouse. Please review and accept or reject.', '0', '2026-01-03 22:22:26');
INSERT INTO `inventory_notifications` (`id`, `user_id`, `notification_type`, `dispatch_id`, `pending_receive_id`, `title`, `message`, `is_read`, `created_at`) VALUES ('3', '2326', 'accepted', '1', '1', 'Dispatch Accepted', 'Your dispatch has been accepted by Cleared Secured Services.', '1', '2026-01-03 22:23:04');
INSERT INTO `inventory_notifications` (`id`, `user_id`, `notification_type`, `dispatch_id`, `pending_receive_id`, `title`, `message`, `is_read`, `created_at`) VALUES ('4', '2326', 'accepted', '2', '2', 'Dispatch Accepted', 'Your dispatch has been accepted by Cleared Secured Services.', '1', '2026-01-03 22:23:04');
INSERT INTO `inventory_notifications` (`id`, `user_id`, `notification_type`, `dispatch_id`, `pending_receive_id`, `title`, `message`, `is_read`, `created_at`) VALUES ('5', '19330', 'pending_receive', '3', '3', 'New Materials Pending', 'You have new materials pending from Delhi Warehouse. Please review and accept or reject.', '1', '2026-01-03 23:51:33');
INSERT INTO `inventory_notifications` (`id`, `user_id`, `notification_type`, `dispatch_id`, `pending_receive_id`, `title`, `message`, `is_read`, `created_at`) VALUES ('6', '2326', 'accepted', '3', '3', 'Dispatch Accepted', 'Your dispatch has been accepted by Cleared Secured Services.', '1', '2026-01-03 23:54:14');
INSERT INTO `inventory_notifications` (`id`, `user_id`, `notification_type`, `dispatch_id`, `pending_receive_id`, `title`, `message`, `is_read`, `created_at`) VALUES ('1007', '19330', 'pending_receive', '1584', '1504', 'New Materials Pending', 'You have new materials pending from Delhi Warehouse. Please review and accept or reject.', '1', '2026-01-05 03:37:05');
INSERT INTO `inventory_notifications` (`id`, `user_id`, `notification_type`, `dispatch_id`, `pending_receive_id`, `title`, `message`, `is_read`, `created_at`) VALUES ('1008', '2326', 'accepted', '1584', '1504', 'Dispatch Accepted', 'Your dispatch has been accepted by Cleared Secured Services.', '1', '2026-01-05 03:39:10');

SET FOREIGN_KEY_CHECKS = 1;
