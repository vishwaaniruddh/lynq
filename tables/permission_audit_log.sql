-- Database Table Export for `permission_audit_log`
-- Generated: 2026-06-25 07:00:57
-- DB Name: if0_40845939_clarity_db

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `permission_audit_log`;

CREATE TABLE `permission_audit_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) DEFAULT NULL,
  `permission_id` int(11) DEFAULT NULL,
  `action` varchar(50) NOT NULL,
  `details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`details`)),
  `performed_by` int(11) DEFAULT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `performed_by` (`performed_by`),
  KEY `idx_company_id` (`company_id`),
  KEY `idx_permission_id` (`permission_id`),
  KEY `idx_action` (`action`),
  KEY `idx_timestamp` (`timestamp`)
) ENGINE=InnoDB AUTO_INCREMENT=1027 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `permission_audit_log`
INSERT INTO `permission_audit_log` (`id`, `company_id`, `permission_id`, `action`, `details`, `performed_by`, `timestamp`) VALUES ('1', '1', '5', '', NULL, '2326', '2025-12-29 15:11:43');

SET FOREIGN_KEY_CHECKS = 1;
