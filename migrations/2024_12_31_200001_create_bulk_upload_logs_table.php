<?php
/**
 * Migration: Create bulk_upload_logs table
 * 
 * Stores history of bulk upload operations with success/error data
 */

require_once __DIR__ . '/../config/autoload.php';

$db = DatabaseConfig::getInstance();

$sql = "CREATE TABLE IF NOT EXISTS `bulk_upload_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `upload_type` VARCHAR(50) NOT NULL COMMENT 'Type of upload: sites, delegations, etc.',
    `original_filename` VARCHAR(255) NOT NULL,
    `total_rows` INT NOT NULL DEFAULT 0,
    `success_count` INT NOT NULL DEFAULT 0,
    `error_count` INT NOT NULL DEFAULT 0,
    `success_file` VARCHAR(255) NULL COMMENT 'Path to success records Excel file',
    `error_file` VARCHAR(255) NULL COMMENT 'Path to error records Excel file',
    `success_data` LONGTEXT NULL COMMENT 'JSON of successful records',
    `error_data` LONGTEXT NULL COMMENT 'JSON of error records with messages',
    `uploaded_by` INT NOT NULL,
    `company_id` INT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_upload_type` (`upload_type`),
    INDEX `idx_uploaded_by` (`uploaded_by`),
    INDEX `idx_company_id` (`company_id`),
    INDEX `idx_created_at` (`created_at`),
    FOREIGN KEY (`uploaded_by`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

try {
    $db->executeQuery($sql);
    echo "Table 'bulk_upload_logs' created successfully.\n";
} catch (Exception $e) {
    echo "Error creating table: " . $e->getMessage() . "\n";
}
