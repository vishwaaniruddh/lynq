<?php
/**
 * Enhance Settings Audit Logging Migration
 * Adds comprehensive audit logging fields to settings_audit table for:
 * - Complete metadata capture (session_id, request_method, request_uri)
 * - Integrity checking with cryptographic hashes
 * - Immutable audit trail verification
 */

require_once __DIR__ . '/Migration.php';

class EnhanceSettingsAuditLogging extends Migration {
    
    public function up() {
        $this->addAuditMetadataFields();
        $this->addIntegrityHashField();
        $this->addIndexes();
    }
    
    public function down() {
        $this->execute("ALTER TABLE `settings_audit` DROP COLUMN IF EXISTS `integrity_hash`");
        $this->execute("ALTER TABLE `settings_audit` DROP COLUMN IF EXISTS `request_uri`");
        $this->execute("ALTER TABLE `settings_audit` DROP COLUMN IF EXISTS `request_method`");
        $this->execute("ALTER TABLE `settings_audit` DROP COLUMN IF EXISTS `session_id`");
        $this->execute("DROP INDEX IF EXISTS `idx_integrity` ON `settings_audit`");
    }
    
    private function addAuditMetadataFields() {
        // Check if columns already exist before adding them
        $columns = $this->getTableColumns('settings_audit');
        $existingColumns = array_column($columns, 'Field');
        
        if (!in_array('session_id', $existingColumns)) {
            $sql = "ALTER TABLE `settings_audit` 
                    ADD COLUMN `session_id` VARCHAR(128) AFTER `user_agent`";
            $this->execute($sql);
        }
        
        if (!in_array('request_method', $existingColumns)) {
            $sql = "ALTER TABLE `settings_audit` 
                    ADD COLUMN `request_method` VARCHAR(10) AFTER `session_id`";
            $this->execute($sql);
        }
        
        if (!in_array('request_uri', $existingColumns)) {
            $sql = "ALTER TABLE `settings_audit` 
                    ADD COLUMN `request_uri` TEXT AFTER `request_method`";
            $this->execute($sql);
        }
    }
    
    private function addIntegrityHashField() {
        $columns = $this->getTableColumns('settings_audit');
        $existingColumns = array_column($columns, 'Field');
        
        if (!in_array('integrity_hash', $existingColumns)) {
            $sql = "ALTER TABLE `settings_audit` 
                    ADD COLUMN `integrity_hash` VARCHAR(64) AFTER `request_uri`";
            $this->execute($sql);
        }
    }
    
    private function addIndexes() {
        // Check if index exists before creating it
        $indexes = $this->getTableIndexes('settings_audit');
        $existingIndexes = array_column($indexes, 'Key_name');
        
        if (!in_array('idx_integrity', $existingIndexes)) {
            $sql = "ALTER TABLE `settings_audit` 
                    ADD INDEX `idx_integrity` (`integrity_hash`)";
            $this->execute($sql);
        }
    }
    
    private function getTableColumns($tableName) {
        $sql = "SHOW COLUMNS FROM `{$tableName}`";
        return $this->db->query($sql)->fetch_all(MYSQLI_ASSOC);
    }
    
    private function getTableIndexes($tableName) {
        $sql = "SHOW INDEX FROM `{$tableName}`";
        return $this->db->query($sql)->fetch_all(MYSQLI_ASSOC);
    }
}