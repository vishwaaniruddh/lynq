-- Add shipping fields to dispatches table
-- Run this SQL to add the new columns for courier, POD, contact details, and attachments

-- Check if columns exist before adding (MySQL 8.0+)
-- For older MySQL versions, you may need to run these individually and handle errors

ALTER TABLE `dispatches` 
    ADD COLUMN IF NOT EXISTS `courier_id` INT NULL AFTER `notes`,
    ADD COLUMN IF NOT EXISTS `pod_number` VARCHAR(100) NULL AFTER `courier_id`,
    ADD COLUMN IF NOT EXISTS `contact_person_name` VARCHAR(255) NULL AFTER `pod_number`,
    ADD COLUMN IF NOT EXISTS `contact_person_phone` VARCHAR(50) NULL AFTER `contact_person_name`,
    ADD COLUMN IF NOT EXISTS `lr_copy_path` VARCHAR(500) NULL AFTER `contact_person_phone`,
    ADD COLUMN IF NOT EXISTS `pod_receipt_path` VARCHAR(500) NULL AFTER `lr_copy_path`;

-- Add indexes (ignore if already exists)
CREATE INDEX IF NOT EXISTS `idx_courier_id` ON `dispatches` (`courier_id`);
CREATE INDEX IF NOT EXISTS `idx_pod_number` ON `dispatches` (`pod_number`);

-- Alternative for MySQL versions that don't support IF NOT EXISTS for columns:
-- Run each statement separately and ignore errors for existing columns:
--
-- ALTER TABLE `dispatches` ADD COLUMN `courier_id` INT NULL;
-- ALTER TABLE `dispatches` ADD COLUMN `pod_number` VARCHAR(100) NULL;
-- ALTER TABLE `dispatches` ADD COLUMN `contact_person_name` VARCHAR(255) NULL;
-- ALTER TABLE `dispatches` ADD COLUMN `contact_person_phone` VARCHAR(50) NULL;
-- ALTER TABLE `dispatches` ADD COLUMN `lr_copy_path` VARCHAR(500) NULL;
-- ALTER TABLE `dispatches` ADD COLUMN `pod_receipt_path` VARCHAR(500) NULL;
