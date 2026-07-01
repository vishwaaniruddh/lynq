-- Database Table Export for `product_categories`
-- Generated: 2026-06-25 07:00:57
-- DB Name: if0_40845939_clarity_db

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `product_categories`;

CREATE TABLE `product_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `created_by` (`created_by`),
  KEY `updated_by` (`updated_by`),
  KEY `idx_parent_id` (`parent_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `product_categories`
INSERT INTO `product_categories` (`id`, `name`, `description`, `parent_id`, `status`, `created_by`, `updated_by`, `created_at`, `updated_at`) VALUES ('1', 'Electronics', '', NULL, 'active', NULL, NULL, '2026-01-03 10:01:20', '2026-01-03 10:01:20');
INSERT INTO `product_categories` (`id`, `name`, `description`, `parent_id`, `status`, `created_by`, `updated_by`, `created_at`, `updated_at`) VALUES ('2', 'SIM Cards', '', NULL, 'active', NULL, NULL, '2026-01-03 15:51:43', '2026-01-03 15:51:43');
INSERT INTO `product_categories` (`id`, `name`, `description`, `parent_id`, `status`, `created_by`, `updated_by`, `created_at`, `updated_at`) VALUES ('3', 'Carpentary', '', NULL, 'active', NULL, NULL, '2026-01-03 15:52:13', '2026-01-03 15:52:13');
INSERT INTO `product_categories` (`id`, `name`, `description`, `parent_id`, `status`, `created_by`, `updated_by`, `created_at`, `updated_at`) VALUES ('4', 'Housekeeping', '', NULL, 'active', NULL, NULL, '2026-01-03 15:52:25', '2026-01-03 15:52:25');
INSERT INTO `product_categories` (`id`, `name`, `description`, `parent_id`, `status`, `created_by`, `updated_by`, `created_at`, `updated_at`) VALUES ('5', 'Cable', '', '1', 'active', NULL, NULL, '2026-01-03 22:10:27', '2026-01-03 22:10:27');

SET FOREIGN_KEY_CHECKS = 1;
