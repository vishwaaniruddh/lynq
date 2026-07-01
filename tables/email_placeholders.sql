-- Database Table Export for `email_placeholders`
-- Generated: 2026-06-25 07:00:57
-- DB Name: if0_40845939_clarity_db

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `email_placeholders`;

CREATE TABLE `email_placeholders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `module_name` varchar(50) NOT NULL,
  `placeholder_key` varchar(100) NOT NULL,
  `placeholder_label` varchar(100) NOT NULL,
  `data_source` varchar(100) NOT NULL,
  `data_path` varchar(255) NOT NULL,
  `data_type` enum('string','number','date','boolean') DEFAULT 'string',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_placeholder` (`module_name`,`placeholder_key`),
  KEY `idx_module_active` (`module_name`,`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- No data found in `email_placeholders`

SET FOREIGN_KEY_CHECKS = 1;
