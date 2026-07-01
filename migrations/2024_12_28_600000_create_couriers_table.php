<?php
/**
 * Create Couriers Table Migration
 * Creates the couriers table for the Courier Master module
 * 
 * Requirements: 2.2 - Create new courier with valid name and persist
 */

require_once __DIR__ . '/Migration.php';

class CreateCouriersTable extends Migration {
    
    public function up() {
        $this->createCouriersTable();
        $this->insertCourierPermissions();
    }
    
    public function down() {
        // Remove courier permissions
        $this->execute("DELETE FROM `role_permissions` WHERE `permission_id` IN (SELECT `id` FROM `permissions` WHERE `module` = 'masters.couriers')");
        $this->execute("DELETE FROM `permissions` WHERE `module` = 'masters.couriers'");
        
        // Drop couriers table
        $this->execute("DROP TABLE IF EXISTS `couriers`");
    }
    
    /**
     * Create couriers table
     * Requirements: 2.2
     */
    private function createCouriersTable() {
        $sql = "CREATE TABLE IF NOT EXISTS `couriers` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(255) NOT NULL,
            `status` TINYINT(1) DEFAULT 1 COMMENT '0=inactive, 1=active',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            `created_by` INT NULL,
            `updated_by` INT NULL,
            UNIQUE KEY `unique_courier_name` (`name`),
            INDEX `idx_status` (`status`),
            FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
            FOREIGN KEY (`updated_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->execute($sql);
    }
    
    /**
     * Insert courier module permissions
     * Requirements: 5.1, 5.2, 5.3
     */
    private function insertCourierPermissions() {
        $permissions = [
            ['masters.couriers.view', 'masters.couriers', 'view', 'View courier records', 1],
            ['masters.couriers.create', 'masters.couriers', 'create', 'Create courier records', 1],
            ['masters.couriers.edit', 'masters.couriers', 'edit', 'Edit courier records', 1],
            ['masters.couriers.delete', 'masters.couriers', 'delete', 'Delete courier records', 1]
        ];
        
        foreach ($permissions as $perm) {
            $name = $this->db->real_escape_string($perm[0]);
            $module = $this->db->real_escape_string($perm[1]);
            $action = $this->db->real_escape_string($perm[2]);
            $description = $this->db->real_escape_string($perm[3]);
            $isAdvOnly = (int)$perm[4];
            
            $this->execute("INSERT IGNORE INTO `permissions` (`name`, `module`, `action`, `description`, `is_adv_only`) 
                           VALUES ('$name', '$module', '$action', '$description', $isAdvOnly)");
        }
        
        // Assign courier permissions to Super Admin role
        $this->execute("
            INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
            SELECT r.id, p.id 
            FROM `roles` r 
            CROSS JOIN `permissions` p 
            WHERE r.name = 'Super Admin' 
            AND p.module = 'masters.couriers'
        ");
        
        // Assign courier permissions to ADV Admin role
        $this->execute("
            INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
            SELECT r.id, p.id 
            FROM `roles` r 
            CROSS JOIN `permissions` p 
            WHERE r.name = 'ADV Admin' 
            AND p.module = 'masters.couriers'
        ");
    }
}
