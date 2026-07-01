<?php
/**
 * ADV Clarity Management System - PWA Migration Runner
 * Runs the PWA-related database migrations
 */

require_once 'config/autoload.php';
require_once 'migrations/MigrationRunner.php';

try {
    echo "Starting PWA migration...\n";
    
    $migrationRunner = new MigrationRunner();
    
    // Run the PWA tables migration
    $migrationFile = 'migrations/2025_01_05_120000_create_pwa_tables.php';
    
    if (file_exists($migrationFile)) {
        require_once $migrationFile;
        
        $migration = new CreatePWATables();
        $migration->up();
        
        // Record migration in database
        $migrationRunner->recordMigration('2025_01_05_120000_create_pwa_tables');
        
        echo "PWA migration completed successfully!\n";
    } else {
        echo "Migration file not found: $migrationFile\n";
        exit(1);
    }
    
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
?>