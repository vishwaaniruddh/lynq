<?php
/**
 * Run approval workflow migrations
 */

require_once __DIR__ . '/../config/autoload.php';

$db = DatabaseConfig::getInstance();

echo "Running Approval Workflow Migrations...\n";
echo "==========================================\n\n";

// 1. Create feasibility_reviews table
echo "Creating feasibility_reviews table...\n";
$checkTable = $db->getResults("SHOW TABLES LIKE 'feasibility_reviews'");
if (empty($checkTable)) {
    $sql = "CREATE TABLE IF NOT EXISTS `feasibility_reviews` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `feasibility_id` INT NOT NULL COMMENT 'Reference to feasibility_checks',
        `reviewer_id` INT NOT NULL COMMENT 'User ID of the reviewer',
        `reviewer_role` ENUM('contractor_admin', 'contractor_manager', 'adv') NOT NULL COMMENT 'Role of the reviewer',
        `review_type` ENUM('approval', 'rejection') NOT NULL COMMENT 'Type of review action',
        `rejection_type` ENUM('overall', 'section_specific') NULL COMMENT 'Type of rejection (null if approval)',
        `rejected_sections` JSON NULL COMMENT 'Array of section names that were rejected',
        `reason` TEXT NULL COMMENT 'Required for rejections, min 10 characters',
        `comments` TEXT NULL COMMENT 'Optional comments for approvals',
        `reviewed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'When the review was submitted',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        
        INDEX `idx_feasibility` (`feasibility_id`),
        INDEX `idx_reviewer` (`reviewer_id`),
        INDEX `idx_review_type` (`review_type`),
        INDEX `idx_reviewed_at` (`reviewed_at`),
        
        FOREIGN KEY (`feasibility_id`) REFERENCES `feasibility_checks`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`reviewer_id`) REFERENCES `users`(`id`) ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    try {
        $db->getConnection()->query($sql);
        echo "✓ feasibility_reviews table created\n";
    } catch (Exception $e) {
        echo "✗ Error: " . $e->getMessage() . "\n";
    }
} else {
    echo "✓ feasibility_reviews table already exists\n";
}

// 2. Add approval_status column to feasibility_checks
echo "\nAdding approval_status column to feasibility_checks...\n";
$checkColumn = $db->getResults("SHOW COLUMNS FROM feasibility_checks LIKE 'approval_status'");
if (empty($checkColumn)) {
    $sql = "ALTER TABLE `feasibility_checks` 
            ADD COLUMN `approval_status` ENUM(
                'pending_contractor_review',
                'contractor_approved',
                'contractor_rejected',
                'adv_approved',
                'adv_rejected'
            ) DEFAULT 'pending_contractor_review' AFTER `status`";
    
    try {
        $db->getConnection()->query($sql);
        echo "✓ approval_status column added to feasibility_checks\n";
        
        // Add index
        $db->getConnection()->query("ALTER TABLE `feasibility_checks` ADD INDEX `idx_approval_status` (`approval_status`)");
        echo "✓ approval_status index added\n";
    } catch (Exception $e) {
        echo "✗ Error: " . $e->getMessage() . "\n";
    }
} else {
    echo "✓ approval_status column already exists\n";
}

// 3. Update feasibility_status enum in engineer_assignments
echo "\nUpdating feasibility_status enum in engineer_assignments...\n";
$checkEnum = $db->getResults("SHOW COLUMNS FROM engineer_assignments LIKE 'feasibility_status'");
if (!empty($checkEnum)) {
    $currentType = $checkEnum[0]['Type'];
    if (strpos($currentType, 'contractor_approved') === false) {
        $sql = "ALTER TABLE `engineer_assignments` 
                MODIFY COLUMN `feasibility_status` ENUM(
                    'pending_eta',
                    'eta_submitted',
                    'ada_submitted',
                    'feasibility_completed',
                    'pending_contractor_review',
                    'contractor_approved',
                    'contractor_rejected',
                    'adv_approved',
                    'adv_rejected'
                ) DEFAULT 'pending_eta'";
        
        try {
            $db->getConnection()->query($sql);
            echo "✓ feasibility_status enum updated\n";
        } catch (Exception $e) {
            echo "✗ Error: " . $e->getMessage() . "\n";
        }
    } else {
        echo "✓ feasibility_status enum already updated\n";
    }
} else {
    echo "✗ feasibility_status column not found\n";
}

echo "\n==========================================\n";
echo "Approval workflow migrations completed!\n";
