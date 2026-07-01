<?php
/**
 * Add Shipping Fields to Dispatches Table Migration
 * Adds courier, POD, contact details, and attachment fields to dispatches table
 * 
 * New fields:
 * - courier_id: Reference to couriers table
 * - pod_number: POD (Proof of Delivery) number
 * - contact_person_name: Contact person name at destination
 * - contact_person_phone: Contact person phone number
 * - lr_copy_path: Path to LR (Lorry Receipt) copy attachment
 * - pod_receipt_path: Path to POD receipt attachment
 */

require_once __DIR__ . '/Migration.php';

class AddDispatchShippingFields extends Migration {
    
    public function up() {
        // Add new columns to dispatches table
        $sql = "ALTER TABLE `dispatches` 
            ADD COLUMN `courier_id` INT NULL AFTER `notes`,
            ADD COLUMN `pod_number` VARCHAR(100) NULL AFTER `courier_id`,
            ADD COLUMN `contact_person_name` VARCHAR(255) NULL AFTER `pod_number`,
            ADD COLUMN `contact_person_phone` VARCHAR(50) NULL AFTER `contact_person_name`,
            ADD COLUMN `lr_copy_path` VARCHAR(500) NULL AFTER `contact_person_phone`,
            ADD COLUMN `pod_receipt_path` VARCHAR(500) NULL AFTER `lr_copy_path`,
            ADD INDEX `idx_courier_id` (`courier_id`),
            ADD INDEX `idx_pod_number` (`pod_number`)";
        
        $this->execute($sql);
        
        // Add foreign key for courier_id (optional - depends on couriers table structure)
        // Uncomment if couriers table exists with proper structure
        // $this->execute("ALTER TABLE `dispatches` ADD FOREIGN KEY (`courier_id`) REFERENCES `couriers`(`id`) ON DELETE SET NULL");
    }
    
    public function down() {
        $sql = "ALTER TABLE `dispatches` 
            DROP INDEX IF EXISTS `idx_courier_id`,
            DROP INDEX IF EXISTS `idx_pod_number`,
            DROP COLUMN IF EXISTS `courier_id`,
            DROP COLUMN IF EXISTS `pod_number`,
            DROP COLUMN IF EXISTS `contact_person_name`,
            DROP COLUMN IF EXISTS `contact_person_phone`,
            DROP COLUMN IF EXISTS `lr_copy_path`,
            DROP COLUMN IF EXISTS `pod_receipt_path`";
        
        $this->execute($sql);
    }
}
