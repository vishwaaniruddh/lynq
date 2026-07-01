<?php
/**
 * Run Email System Migration
 */

require_once 'config/database.php';
require_once 'migrations/2026_01_06_160000_create_email_system_tables.php';

try {
    echo "Starting Email System Migration...\n";
    
    $migration = new CreateEmailSystemTables();
    $migration->up();
    
    echo "Email System Migration completed successfully!\n";
    
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}