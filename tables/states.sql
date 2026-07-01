-- Database Table Export for `states`
-- Generated: 2026-06-25 07:00:57
-- DB Name: if0_40845939_clarity_db

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `states`;

CREATE TABLE `states` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `country_id` int(11) NOT NULL,
  `zone_id` int(11) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_state_country` (`name`,`country_id`),
  KEY `idx_country` (`country_id`),
  KEY `idx_zone` (`zone_id`),
  KEY `idx_status` (`status`),
  KEY `created_by` (`created_by`),
  KEY `updated_by` (`updated_by`)
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `states`
INSERT INTO `states` (`id`, `name`, `country_id`, `zone_id`, `status`, `created_at`, `updated_at`, `created_by`, `updated_by`) VALUES ('1', 'Himachal Pradesh', '1', '1', 'active', '2025-11-03 00:46:25', '2025-11-03 00:46:25', NULL, NULL);
INSERT INTO `states` (`id`, `name`, `country_id`, `zone_id`, `status`, `created_at`, `updated_at`, `created_by`, `updated_by`) VALUES ('2', 'Punjab', '1', '1', 'active', '2025-11-03 00:46:25', '2025-11-03 00:46:25', NULL, NULL);
INSERT INTO `states` (`id`, `name`, `country_id`, `zone_id`, `status`, `created_at`, `updated_at`, `created_by`, `updated_by`) VALUES ('3', 'Haryana', '1', '1', 'active', '2025-11-03 00:46:25', '2025-11-03 00:46:25', NULL, NULL);
INSERT INTO `states` (`id`, `name`, `country_id`, `zone_id`, `status`, `created_at`, `updated_at`, `created_by`, `updated_by`) VALUES ('4', 'Rajasthan', '1', '1', 'active', '2025-11-03 00:46:25', '2025-11-03 00:46:25', NULL, NULL);
INSERT INTO `states` (`id`, `name`, `country_id`, `zone_id`, `status`, `created_at`, `updated_at`, `created_by`, `updated_by`) VALUES ('5', 'Uttar Pradesh', '1', '1', 'active', '2025-11-03 00:46:25', '2025-11-03 00:46:25', NULL, NULL);
INSERT INTO `states` (`id`, `name`, `country_id`, `zone_id`, `status`, `created_at`, `updated_at`, `created_by`, `updated_by`) VALUES ('6', 'Uttarakhand', '1', '1', 'active', '2025-11-03 00:46:25', '2025-11-03 00:46:25', NULL, NULL);
INSERT INTO `states` (`id`, `name`, `country_id`, `zone_id`, `status`, `created_at`, `updated_at`, `created_by`, `updated_by`) VALUES ('7', 'Jammu and Kashmir', '1', '1', 'active', '2025-11-03 00:46:25', '2025-11-03 00:46:25', NULL, NULL);
INSERT INTO `states` (`id`, `name`, `country_id`, `zone_id`, `status`, `created_at`, `updated_at`, `created_by`, `updated_by`) VALUES ('8', 'Ladakh', '1', '1', 'active', '2025-11-03 00:46:25', '2025-11-03 00:46:25', NULL, NULL);
INSERT INTO `states` (`id`, `name`, `country_id`, `zone_id`, `status`, `created_at`, `updated_at`, `created_by`, `updated_by`) VALUES ('9', 'Chandigarh', '1', '1', 'active', '2025-11-03 00:46:25', '2025-11-03 00:46:25', NULL, NULL);
INSERT INTO `states` (`id`, `name`, `country_id`, `zone_id`, `status`, `created_at`, `updated_at`, `created_by`, `updated_by`) VALUES ('10', 'Delhi', '1', '1', 'active', '2025-11-03 00:46:25', '2025-11-03 00:46:25', NULL, NULL);
INSERT INTO `states` (`id`, `name`, `country_id`, `zone_id`, `status`, `created_at`, `updated_at`, `created_by`, `updated_by`) VALUES ('11', 'Andhra Pradesh', '1', '2', 'active', '2025-11-03 00:46:25', '2025-11-03 00:46:25', NULL, NULL);
INSERT INTO `states` (`id`, `name`, `country_id`, `zone_id`, `status`, `created_at`, `updated_at`, `created_by`, `updated_by`) VALUES ('12', 'Karnataka', '1', '2', 'active', '2025-11-03 00:46:25', '2025-11-03 00:46:25', NULL, NULL);
INSERT INTO `states` (`id`, `name`, `country_id`, `zone_id`, `status`, `created_at`, `updated_at`, `created_by`, `updated_by`) VALUES ('13', 'Kerala', '1', '2', 'active', '2025-11-03 00:46:25', '2025-11-03 00:46:25', NULL, NULL);
INSERT INTO `states` (`id`, `name`, `country_id`, `zone_id`, `status`, `created_at`, `updated_at`, `created_by`, `updated_by`) VALUES ('14', 'Tamil Nadu', '1', '2', 'active', '2025-11-03 00:46:25', '2025-11-03 00:46:25', NULL, NULL);
INSERT INTO `states` (`id`, `name`, `country_id`, `zone_id`, `status`, `created_at`, `updated_at`, `created_by`, `updated_by`) VALUES ('15', 'Telangana', '1', '2', 'active', '2025-11-03 00:46:25', '2025-11-03 00:46:25', NULL, NULL);
INSERT INTO `states` (`id`, `name`, `country_id`, `zone_id`, `status`, `created_at`, `updated_at`, `created_by`, `updated_by`) VALUES ('16', 'Puducherry', '1', '2', 'active', '2025-11-03 00:46:25', '2025-11-03 00:46:25', NULL, NULL);
INSERT INTO `states` (`id`, `name`, `country_id`, `zone_id`, `status`, `created_at`, `updated_at`, `created_by`, `updated_by`) VALUES ('17', 'Lakshadweep', '1', '2', 'active', '2025-11-03 00:46:25', '2025-11-03 00:46:25', NULL, NULL);
INSERT INTO `states` (`id`, `name`, `country_id`, `zone_id`, `status`, `created_at`, `updated_at`, `created_by`, `updated_by`) VALUES ('18', 'Andaman and Nicobar Islands', '1', '2', 'active', '2025-11-03 00:46:25', '2025-11-03 00:46:25', NULL, NULL);
INSERT INTO `states` (`id`, `name`, `country_id`, `zone_id`, `status`, `created_at`, `updated_at`, `created_by`, `updated_by`) VALUES ('19', 'Bihar', '1', '3', 'active', '2025-11-03 00:46:25', '2025-11-03 00:46:25', NULL, NULL);
INSERT INTO `states` (`id`, `name`, `country_id`, `zone_id`, `status`, `created_at`, `updated_at`, `created_by`, `updated_by`) VALUES ('20', 'Jharkhand', '1', '3', 'active', '2025-11-03 00:46:25', '2025-11-03 00:46:25', NULL, NULL);
INSERT INTO `states` (`id`, `name`, `country_id`, `zone_id`, `status`, `created_at`, `updated_at`, `created_by`, `updated_by`) VALUES ('21', 'Odisha', '1', '3', 'active', '2025-11-03 00:46:25', '2025-11-03 00:46:25', NULL, NULL);
INSERT INTO `states` (`id`, `name`, `country_id`, `zone_id`, `status`, `created_at`, `updated_at`, `created_by`, `updated_by`) VALUES ('22', 'West Bengal', '1', '3', 'active', '2025-11-03 00:46:25', '2025-11-03 00:46:25', NULL, NULL);
INSERT INTO `states` (`id`, `name`, `country_id`, `zone_id`, `status`, `created_at`, `updated_at`, `created_by`, `updated_by`) VALUES ('23', 'Goa', '1', '4', 'active', '2025-11-03 00:46:25', '2025-11-03 00:46:25', NULL, NULL);
INSERT INTO `states` (`id`, `name`, `country_id`, `zone_id`, `status`, `created_at`, `updated_at`, `created_by`, `updated_by`) VALUES ('24', 'Gujarat', '1', '4', 'active', '2025-11-03 00:46:25', '2025-11-03 00:46:25', NULL, NULL);
INSERT INTO `states` (`id`, `name`, `country_id`, `zone_id`, `status`, `created_at`, `updated_at`, `created_by`, `updated_by`) VALUES ('25', 'Maharashtra', '1', '4', 'active', '2025-11-03 00:46:25', '2025-11-03 00:46:25', NULL, NULL);
INSERT INTO `states` (`id`, `name`, `country_id`, `zone_id`, `status`, `created_at`, `updated_at`, `created_by`, `updated_by`) VALUES ('26', 'Dadra and Nagar Haveli and Daman and Diu', '1', '4', 'active', '2025-11-03 00:46:25', '2025-11-03 00:46:25', NULL, NULL);
INSERT INTO `states` (`id`, `name`, `country_id`, `zone_id`, `status`, `created_at`, `updated_at`, `created_by`, `updated_by`) VALUES ('27', 'Madhya Pradesh', '1', '5', 'active', '2025-11-03 00:46:25', '2025-11-03 00:46:25', NULL, NULL);

SET FOREIGN_KEY_CHECKS = 1;
