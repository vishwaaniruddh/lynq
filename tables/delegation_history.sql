-- Database Table Export for `delegation_history`
-- Generated: 2026-06-25 07:00:57
-- DB Name: if0_40845939_clarity_db

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `delegation_history`;

CREATE TABLE `delegation_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `delegation_id` int(11) NOT NULL,
  `action` enum('created','accepted','rejected','reassigned') NOT NULL,
  `performed_by` int(11) NOT NULL,
  `notes` text DEFAULT NULL,
  `performed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_delegation` (`delegation_id`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `delegation_history`
INSERT INTO `delegation_history` (`id`, `delegation_id`, `action`, `performed_by`, `notes`, `performed_at`) VALUES ('1', '1', 'created', '2326', NULL, '2026-01-03 22:02:05');
INSERT INTO `delegation_history` (`id`, `delegation_id`, `action`, `performed_by`, `notes`, `performed_at`) VALUES ('2', '1', 'accepted', '19330', NULL, '2026-01-03 22:24:36');
INSERT INTO `delegation_history` (`id`, `delegation_id`, `action`, `performed_by`, `notes`, `performed_at`) VALUES ('3', '2', 'created', '2326', NULL, '2026-01-03 23:34:49');
INSERT INTO `delegation_history` (`id`, `delegation_id`, `action`, `performed_by`, `notes`, `performed_at`) VALUES ('4', '3', 'created', '2326', NULL, '2026-01-03 23:34:49');
INSERT INTO `delegation_history` (`id`, `delegation_id`, `action`, `performed_by`, `notes`, `performed_at`) VALUES ('5', '4', 'created', '2326', NULL, '2026-01-03 23:34:49');
INSERT INTO `delegation_history` (`id`, `delegation_id`, `action`, `performed_by`, `notes`, `performed_at`) VALUES ('6', '5', 'created', '2326', NULL, '2026-01-03 23:34:49');
INSERT INTO `delegation_history` (`id`, `delegation_id`, `action`, `performed_by`, `notes`, `performed_at`) VALUES ('7', '6', 'created', '2326', NULL, '2026-01-03 23:34:49');
INSERT INTO `delegation_history` (`id`, `delegation_id`, `action`, `performed_by`, `notes`, `performed_at`) VALUES ('8', '7', 'created', '2326', NULL, '2026-01-03 23:34:49');
INSERT INTO `delegation_history` (`id`, `delegation_id`, `action`, `performed_by`, `notes`, `performed_at`) VALUES ('9', '2', 'accepted', '19330', NULL, '2026-01-03 23:35:14');
INSERT INTO `delegation_history` (`id`, `delegation_id`, `action`, `performed_by`, `notes`, `performed_at`) VALUES ('10', '3', 'accepted', '19330', NULL, '2026-01-03 23:35:15');
INSERT INTO `delegation_history` (`id`, `delegation_id`, `action`, `performed_by`, `notes`, `performed_at`) VALUES ('11', '4', 'accepted', '19330', NULL, '2026-01-03 23:35:15');
INSERT INTO `delegation_history` (`id`, `delegation_id`, `action`, `performed_by`, `notes`, `performed_at`) VALUES ('12', '5', 'accepted', '19330', NULL, '2026-01-03 23:35:15');
INSERT INTO `delegation_history` (`id`, `delegation_id`, `action`, `performed_by`, `notes`, `performed_at`) VALUES ('13', '6', 'accepted', '19330', NULL, '2026-01-03 23:35:15');
INSERT INTO `delegation_history` (`id`, `delegation_id`, `action`, `performed_by`, `notes`, `performed_at`) VALUES ('14', '7', 'accepted', '19330', NULL, '2026-01-03 23:35:15');
INSERT INTO `delegation_history` (`id`, `delegation_id`, `action`, `performed_by`, `notes`, `performed_at`) VALUES ('15', '8', 'created', '2326', NULL, '2026-01-05 04:26:34');

SET FOREIGN_KEY_CHECKS = 1;
