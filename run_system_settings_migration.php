<?php
/**
 * Run System Settings Migration
 * Creates the system_settings and settings_audit tables with default data
 */

require_once 'config/database.php';
require_once 'migrations/2026_01_06_140000_create_system_settings_tables.php';

try {
    echo "Starting System Settings Migration...\n";
    
    $migration = new CreateSystemSettingsTables();
    $migration->up();
    
    echo "System Settings Migration completed successfully!\n";
    echo "Created tables:\n";
    echo "- system_settings (with default settings)\n";
    echo "- settings_audit (for change tracking)\n";
    
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}