<?php
/**
 * Run Notes Module Migration
 * Executes the migration for the Notes Module
 * 
 * Requirements: 3.3 - Note persistence to database
 */

require_once __DIR__ . '/config/database.php';

echo "Running Notes Module Migration...\n\n";

$migrations = [
    '2026_01_04_300000_create_notes_table.php' => 'CreateNotesTable',
];

$success = true;

foreach ($migrations as $file => $class) {
    echo "Running migration: $file\n";
    
    try {
        require_once __DIR__ . '/migrations/' . $file;
        
        $migration = new $class();
        $migration->up();
        
        echo "  ✓ Migration completed successfully\n";
    } catch (Exception $e) {
        echo "  ✗ Migration failed: " . $e->getMessage() . "\n";
        $success = false;
        break;
    }
}

echo "\n";
if ($success) {
    echo "Notes Module migration completed successfully!\n";
} else {
    echo "Migration process stopped due to errors.\n";
}
