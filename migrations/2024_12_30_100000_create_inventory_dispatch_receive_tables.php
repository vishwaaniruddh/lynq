<?php
/**
 * Create Inventory Dispatch and Receive Flow Tables Migration
 * Creates tables for multi-directional material flow between ADV, Contractors, and Engineers
 * 
 * Requirements: 1.2, 1.3, 2.1, 2.2, 2.3, 2.5, 3.5, 4.5, 5.3, 6.2, 6.3, 7.1, 7.2, 7.3, 9.1, 9.2, 9.3, 10.1, 10.2, 10.3, 10.4, 11.1, 11.2, 11.3
 */

require_once __DIR__ . '/Migration.php';

class CreateInventoryDispatchReceiveTables extends Migration {
    
    public function up() {
        // Create tables in order of dependencies
        $this->createInventoryCountersTable();
        $this->createPendingReceivesTable();
        $this->createPendingReceiveItemsTable();
        $this->createDiscrepanciesTable();
        $this->createDispatchChainTable();
        $this->createInventoryNotificationsTable();
        $this->alterDispatchesTable();
    }
    
    public function down() {
        // Drop in reverse order of creation
        $this->removeDispatchesTableColumns();
        $this->execute("DROP TABLE IF EXISTS `inventory_notifications`");
        $this->execute("DROP TABLE IF EXISTS `dispatch_chain`");
        $this->execute("DROP TABLE IF EXISTS `discrepancies`");
        $this->execute("DROP TABLE IF EXISTS `pending_receive_items`");
        $this->execute("DROP TABLE IF EXISTS `pending_receives`");
        $this->execute("DROP TABLE IF EXISTS `inventory_counters`");
    }
    
    /**
     * Task 1.1: Create inventory_counters table
     * Tracks real-time inventory at each level (warehouse, company, user)
     * Requirements: 7.1, 7.2, 7.3
     */
    private function createInventoryCountersTable() {
        if ($this->tableExists('inventory_counters')) {
            return;
        }
        
        $sql = "CREATE TABLE `inventory_counters` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `entity_type` ENUM('warehouse', 'company', 'user') NOT NULL,
            `entity_id` INT NOT NULL,
            `product_id` INT NOT NULL,
            `quantity` INT DEFAULT 0,
            `pending_out` INT DEFAULT 0 COMMENT 'Quantity in pending outgoing dispatches',
            `pending_in` INT DEFAULT 0 COMMENT 'Quantity in pending incoming receives',
            `last_updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE RESTRICT,
            UNIQUE KEY `unique_counter` (`entity_type`, `entity_id`, `product_id`),
            INDEX `idx_entity` (`entity_type`, `entity_id`),
            INDEX `idx_product` (`product_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->execute($sql);
    }
    
    /**
     * Task 1.2: Create pending_receives table
     * Tracks dispatches awaiting acceptance
     * Requirements: 2.1, 2.2, 2.3
     */
    private function createPendingReceivesTable() {
        if ($this->tableExists('pending_receives')) {
            return;
        }
        
        $sql = "CREATE TABLE `pending_receives` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `dispatch_id` INT NOT NULL,
            `recipient_type` ENUM('warehouse', 'company', 'user') NOT NULL,
            `recipient_id` INT NOT NULL,
            `status` ENUM('pending', 'accepted', 'rejected', 'partial') DEFAULT 'pending',
            `rejection_reason` TEXT,
            `accepted_at` TIMESTAMP NULL,
            `accepted_by` INT,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (`dispatch_id`) REFERENCES `dispatches`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`accepted_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
            INDEX `idx_recipient` (`recipient_type`, `recipient_id`),
            INDEX `idx_status` (`status`),
            INDEX `idx_dispatch` (`dispatch_id`),
            INDEX `idx_created_at` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->execute($sql);
    }
    
    /**
     * Task 1.3: Create pending_receive_items table
     * For partial acceptance tracking
     * Requirements: 10.1, 10.2
     */
    private function createPendingReceiveItemsTable() {
        if ($this->tableExists('pending_receive_items')) {
            return;
        }
        
        $sql = "CREATE TABLE `pending_receive_items` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `pending_receive_id` INT NOT NULL,
            `dispatch_item_id` INT NOT NULL,
            `expected_quantity` INT NOT NULL,
            `received_quantity` INT DEFAULT 0,
            `status` ENUM('pending', 'accepted', 'rejected', 'partial') DEFAULT 'pending',
            `notes` TEXT,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (`pending_receive_id`) REFERENCES `pending_receives`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`dispatch_item_id`) REFERENCES `dispatch_items`(`id`) ON DELETE CASCADE,
            INDEX `idx_pending_receive` (`pending_receive_id`),
            INDEX `idx_dispatch_item` (`dispatch_item_id`),
            INDEX `idx_status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->execute($sql);
    }
    
    /**
     * Task 1.4: Create discrepancies table
     * Tracks differences between dispatched and received quantities
     * Requirements: 10.3, 10.4
     */
    private function createDiscrepanciesTable() {
        if ($this->tableExists('discrepancies')) {
            return;
        }
        
        $sql = "CREATE TABLE `discrepancies` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `dispatch_id` INT NOT NULL,
            `pending_receive_id` INT NOT NULL,
            `product_id` INT NOT NULL,
            `asset_id` INT NULL,
            `expected_quantity` INT NOT NULL,
            `received_quantity` INT NOT NULL,
            `discrepancy_type` ENUM('shortage', 'damage', 'wrong_item', 'excess') NOT NULL,
            `notes` TEXT,
            `status` ENUM('open', 'resolved', 'written_off') DEFAULT 'open',
            `resolved_at` TIMESTAMP NULL,
            `resolved_by` INT,
            `resolution_notes` TEXT,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (`dispatch_id`) REFERENCES `dispatches`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`pending_receive_id`) REFERENCES `pending_receives`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE RESTRICT,
            FOREIGN KEY (`asset_id`) REFERENCES `assets`(`id`) ON DELETE SET NULL,
            FOREIGN KEY (`resolved_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
            INDEX `idx_status` (`status`),
            INDEX `idx_dispatch` (`dispatch_id`),
            INDEX `idx_pending_receive` (`pending_receive_id`),
            INDEX `idx_product` (`product_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->execute($sql);
    }
    
    /**
     * Task 1.5: Create dispatch_chain table
     * Tracks complete item journey for traceability
     * Requirements: 9.1, 9.2, 9.3
     */
    private function createDispatchChainTable() {
        if ($this->tableExists('dispatch_chain')) {
            return;
        }
        
        $sql = "CREATE TABLE `dispatch_chain` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `asset_id` INT NULL COMMENT 'For serializable items',
            `product_id` INT NOT NULL,
            `dispatch_id` INT NOT NULL,
            `sequence_number` INT NOT NULL COMMENT 'Order in the chain for this item',
            `from_entity_type` ENUM('warehouse', 'company', 'user') NOT NULL,
            `from_entity_id` INT NOT NULL,
            `to_entity_type` ENUM('warehouse', 'company', 'user') NOT NULL,
            `to_entity_id` INT NOT NULL,
            `quantity` INT DEFAULT 1,
            `dispatch_date` TIMESTAMP NOT NULL,
            `acceptance_date` TIMESTAMP NULL,
            `status` ENUM('dispatched', 'accepted', 'rejected') NOT NULL DEFAULT 'dispatched',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`asset_id`) REFERENCES `assets`(`id`) ON DELETE SET NULL,
            FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE RESTRICT,
            FOREIGN KEY (`dispatch_id`) REFERENCES `dispatches`(`id`) ON DELETE CASCADE,
            INDEX `idx_asset` (`asset_id`),
            INDEX `idx_product_entity` (`product_id`, `to_entity_type`, `to_entity_id`),
            INDEX `idx_dispatch` (`dispatch_id`),
            INDEX `idx_from_entity` (`from_entity_type`, `from_entity_id`),
            INDEX `idx_to_entity` (`to_entity_type`, `to_entity_id`),
            INDEX `idx_sequence` (`asset_id`, `sequence_number`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->execute($sql);
    }
    
    /**
     * Task 1.6: Create inventory_notifications table
     * For dispatch/receive notifications
     * Requirements: 11.1, 11.2, 11.3
     */
    private function createInventoryNotificationsTable() {
        if ($this->tableExists('inventory_notifications')) {
            return;
        }
        
        $sql = "CREATE TABLE `inventory_notifications` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NOT NULL,
            `notification_type` ENUM('pending_receive', 'accepted', 'rejected', 'overdue', 'discrepancy') NOT NULL,
            `dispatch_id` INT,
            `pending_receive_id` INT,
            `title` VARCHAR(255) NOT NULL,
            `message` TEXT,
            `is_read` BOOLEAN DEFAULT FALSE,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`dispatch_id`) REFERENCES `dispatches`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`pending_receive_id`) REFERENCES `pending_receives`(`id`) ON DELETE CASCADE,
            INDEX `idx_user_unread` (`user_id`, `is_read`),
            INDEX `idx_notification_type` (`notification_type`),
            INDEX `idx_created_at` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->execute($sql);
    }
    
    /**
     * Task 1.7: Add columns to existing dispatches table
     * Requirements: 5.3, 6.2, 6.3
     */
    private function alterDispatchesTable() {
        // Add sender_type column if not exists
        if (!$this->columnExists('dispatches', 'sender_type')) {
            $sql = "ALTER TABLE `dispatches` ADD COLUMN `sender_type` ENUM('warehouse', 'company', 'user') DEFAULT 'warehouse' AFTER `dispatch_number`";
            $this->execute($sql);
        }
        
        // Add sender_id column if not exists
        if (!$this->columnExists('dispatches', 'sender_id')) {
            $sql = "ALTER TABLE `dispatches` ADD COLUMN `sender_id` INT AFTER `sender_type`";
            $this->execute($sql);
        }
        
        // Add receive_status column if not exists
        if (!$this->columnExists('dispatches', 'receive_status')) {
            $sql = "ALTER TABLE `dispatches` ADD COLUMN `receive_status` ENUM('pending', 'accepted', 'rejected', 'partial') DEFAULT 'pending' AFTER `acknowledgment_status`";
            $this->execute($sql);
            
            // Add index for receive_status
            $sql = "ALTER TABLE `dispatches` ADD INDEX `idx_receive_status` (`receive_status`)";
            $this->execute($sql);
        }
    }
    
    /**
     * Remove added columns from dispatches table (for rollback)
     */
    private function removeDispatchesTableColumns() {
        if ($this->columnExists('dispatches', 'receive_status')) {
            $this->execute("ALTER TABLE `dispatches` DROP INDEX `idx_receive_status`");
            $this->execute("ALTER TABLE `dispatches` DROP COLUMN `receive_status`");
        }
        
        if ($this->columnExists('dispatches', 'sender_id')) {
            $this->execute("ALTER TABLE `dispatches` DROP COLUMN `sender_id`");
        }
        
        if ($this->columnExists('dispatches', 'sender_type')) {
            $this->execute("ALTER TABLE `dispatches` DROP COLUMN `sender_type`");
        }
    }
}
