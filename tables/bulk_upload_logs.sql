-- Database Table Export for `bulk_upload_logs`
-- Generated: 2026-06-25 07:00:57
-- DB Name: if0_40845939_clarity_db

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `bulk_upload_logs`;

CREATE TABLE `bulk_upload_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `upload_type` varchar(50) NOT NULL COMMENT 'Type of upload: sites, delegations, etc.',
  `original_filename` varchar(255) NOT NULL,
  `total_rows` int(11) NOT NULL DEFAULT 0,
  `success_count` int(11) NOT NULL DEFAULT 0,
  `error_count` int(11) NOT NULL DEFAULT 0,
  `success_file` varchar(255) DEFAULT NULL COMMENT 'Path to success records Excel file',
  `error_file` varchar(255) DEFAULT NULL COMMENT 'Path to error records Excel file',
  `success_data` longtext DEFAULT NULL COMMENT 'JSON of successful records',
  `error_data` longtext DEFAULT NULL COMMENT 'JSON of error records with messages',
  `uploaded_by` int(11) NOT NULL,
  `company_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_upload_type` (`upload_type`),
  KEY `idx_uploaded_by` (`uploaded_by`),
  KEY `idx_company_id` (`company_id`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `bulk_upload_logs_ibfk_1` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- No data found in `bulk_upload_logs`

SET FOREIGN_KEY_CHECKS = 1;
