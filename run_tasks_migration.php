<?php
/**
 * Run Tasks Module Migration
 * Executes the migration for the Task Checklist System
 * 
 * Requirements: 1.1 - Task creation and persistence
 */

require_once __DIR__ . '/config/database.php';

echo "Running Task Checklist Migration...\n\n";

$migrations = [
    '2026_01_05_200000_create_tasks_table.php' => 'CreateTasksTable',
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
    echo "Task Checklist migration completed successfully!\n";
} else {
    echo "Migration process stopped due to errors.\n";
}
