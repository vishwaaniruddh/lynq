<?php
/**
 * Run Email Configuration Audit Migration
 */

require_once 'config/database.php';
require_once 'migrations/2026_01_06_161000_create_email_configuration_audit_table.php';

try {
    echo "Starting Email Configuration Audit Migration...\n";
    
    $migration = new CreateEmailConfigurationAuditTable();
    $migration->up();
    
    echo "Email Configuration Audit Migration completed successfully!\n";
    
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}