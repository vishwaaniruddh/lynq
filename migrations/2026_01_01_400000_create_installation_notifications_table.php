<?php
/**
 * Migration: Create installation_notifications table
 * 
 * Requirements: 1.4, 12.4, 13.5
 * - 1.4: Notify contractor when installation is initiated
 * - 12.4: Notify engineer when section is rejected
 * - 13.5: Notify contractor and engineer when ADV rejects
 */

require_once __DIR__ . '/Migration.php';

class CreateInstallationNotificationsTable extends Migration {
    
    public function up() {
        $this->createInstallationNotificationsTable();
    }
    
    public function down() {
        $this->execute("DROP TABLE IF EXISTS `installation_notifications`");
    }
    
    /**
     * Create installation_notifications table
     * For installation workflow notifications
     */
    private function createInstallationNotificationsTable() {
        if ($this->tableExists('installation_notifications')) {
            return;
        }
        
        $sql = "CREATE TABLE `installation_notifications` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NOT NULL,
            `notification_type` ENUM('installation_initiated', 'section_rejected', 'adv_rejected', 'contractor_rejected', 'adv_approved', 'contractor_approved') NOT NULL,
            `installation_id` INT NOT NULL,
            `site_id` INT,
            `section` VARCHAR(50),
            `title` VARCHAR(255) NOT NULL,
            `message` TEXT,
            `is_read` BOOLEAN DEFAULT FALSE,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`installation_id`) REFERENCES `installations`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`site_id`) REFERENCES `sites`(`id`) ON DELETE SET NULL,
            INDEX `idx_user_unread` (`user_id`, `is_read`),
            INDEX `idx_notification_type` (`notification_type`),
            INDEX `idx_installation` (`installation_id`),
            INDEX `idx_created_at` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->execute($sql);
    }
}
