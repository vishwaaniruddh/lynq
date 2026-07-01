<?php
/**
 * Run Push Notifications Migration
 * Creates the push_subscriptions table for PWA push notifications
 */

require_once 'config/autoload.php';
require_once 'migrations/MigrationRunner.php';

try {
    echo "Running Push Notifications Migration...\n";
    
    $runner = new MigrationRunner();
    
    // Run all pending migrations (which will include our new one)
    $runner->runMigrations();
    
    echo "\nPush notifications migration completed successfully!\n";
    
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}