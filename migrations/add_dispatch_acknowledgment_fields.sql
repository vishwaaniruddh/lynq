-- Migration: Add acknowledgment fields to dispatches table
-- Run this SQL to add the new columns for material acknowledgment with proof

ALTER TABLE `dispatches` 
ADD COLUMN IF NOT EXISTS `acknowledgment_notes` TEXT NULL AFTER `acknowledged_by`,
ADD COLUMN IF NOT EXISTS `acknowledgment_condition` ENUM('good', 'minor_damage', 'damaged', 'missing') NULL DEFAULT 'good' AFTER `acknowledgment_notes`,
ADD COLUMN IF NOT EXISTS `acknowledgment_proof` JSON NULL AFTER `acknowledgment_condition`;

-- If your MySQL version doesn't support IF NOT EXISTS for columns, use this instead:
-- ALTER TABLE `dispatches` ADD COLUMN `acknowledgment_notes` TEXT NULL;
-- ALTER TABLE `dispatches` ADD COLUMN `acknowledgment_condition` ENUM('good', 'minor_damage', 'damaged', 'missing') NULL DEFAULT 'good';
-- ALTER TABLE `dispatches` ADD COLUMN `acknowledgment_proof` JSON NULL;
