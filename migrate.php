<?php
/**
 * Migration Runner Script
 * Run this script to execute database migrations
 */

require_once __DIR__ . '/config/autoload.php';

try {
    echo "Starting ADV CRM Users Module Migrations...\n";
    echo "==========================================\n\n";
    
    $runner = new MigrationRunner();
    $runner->runMigrations();
    
    echo "\n==========================================\n";
    echo "Migrations completed successfully!\n";
    
} catch (Exception $e) {
    echo "\nMigration failed: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}