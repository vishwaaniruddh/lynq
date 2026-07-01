<?php
/**
 * Run Profile Enhancement Migrations
 * Executes migrations for the User Profile Enhancement module
 * 
 * Requirements: 2.3, 3.2, 4.3, 5.2, 6.4, 7.3, 8.1
 */

require_once __DIR__ . '/config/database.php';

echo "Running User Profile Enhancement Migrations...\n\n";

$migrations = [
    '2026_01_05_100000_add_profile_fields_to_users.php' => 'AddProfileFieldsToUsers',
    '2026_01_05_100001_create_profile_revisions_table.php' => 'CreateProfileRevisionsTable',
];

$success = true;

foreach ($migrations as $file => $class) {
    echo "Running migration: $file\n";
    
    try {
        require_once __DIR__ . '/migrations/' . $file;
        
        $migration = new $class();
        $migration->up();
        
        echo "  ✓ Migration completed successfully\n\n";
    } catch (Exception $e) {
        echo "  ✗ Migration failed: " . $e->getMessage() . "\n";
        $success = false;
        break;
    }
}

echo "\n";
if ($success) {
    echo "User Profile Enhancement migrations completed successfully!\n";
} else {
    echo "Migration process stopped due to errors.\n";
}
