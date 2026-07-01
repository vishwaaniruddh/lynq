<?php
/**
 * Cleanup Test Data Script
 * 
 * Removes all test data from the database including:
 * - Users with 'test' in username, email, first_name, or last_name
 * - Related data in all dependent tables
 * 
 * CAUTION: This script permanently deletes data. Use with care!
 * 
 * Usage: php cleanup_test_data.php [--dry-run] [--confirm]
 *   --dry-run  : Show what would be deleted without actually deleting
 *   --confirm  : Skip confirmation prompt
 */

// Check if running from CLI
if (php_sapi_name() !== 'cli') {
    die("This script must be run from the command line.\n");
}

require_once __DIR__ . '/../config/autoload.php';

// Parse command line arguments
$dryRun = in_array('--dry-run', $argv);
$skipConfirm = in_array('--confirm', $argv);

echo "===========================================\n";
echo "  TEST DATA CLEANUP SCRIPT\n";
echo "===========================================\n\n";

if ($dryRun) {
    echo "*** DRY RUN MODE - No data will be deleted ***\n\n";
}

$db = DatabaseConfig::getInstance();

// Find test users
$testUsers = $db->getResults("
    SELECT id, username, email, first_name, last_name 
    FROM users 
    WHERE username LIKE '%test%' 
       OR email LIKE '%test%' 
       OR first_name LIKE '%test%' 
       OR last_name LIKE '%test%'
    ORDER BY id
");

echo "Found " . count($testUsers) . " test user(s):\n";
foreach ($testUsers as $user) {
    echo "  - ID: {$user['id']}, Username: {$user['username']}, Email: {$user['email']}, Name: {$user['first_name']} {$user['last_name']}\n";
}
echo "\n";

if (empty($testUsers)) {
    echo "No test users found. Nothing to clean up.\n";
    exit(0);
}

$testUserIds = array_column($testUsers, 'id');
$testUserIdsStr = implode(',', $testUserIds);

// Define tables and their user reference columns
$tablesToClean = [
    // Feasibility related
    ['table' => 'feasibility_reviews', 'column' => 'reviewer_id', 'description' => 'Feasibility Reviews'],
    ['table' => 'feasibility_checks', 'column' => 'created_by', 'description' => 'Feasibility Checks'],
    ['table' => 'feasibility_ada', 'column' => 'submitted_by', 'description' => 'Feasibility ADA'],
    ['table' => 'feasibility_eta', 'column' => 'submitted_by', 'description' => 'Feasibility ETA'],
    ['table' => 'engineer_assignments', 'column' => 'engineer_id', 'description' => 'Engineer Assignments'],
    ['table' => 'engineer_assignments', 'column' => 'assigned_by', 'description' => 'Engineer Assignments (assigned_by)'],
    
    // Site related
    ['table' => 'site_delegations', 'column' => 'delegated_by', 'description' => 'Site Delegations'],
    ['table' => 'sites', 'column' => 'created_by', 'description' => 'Sites'],
    
    // Inventory related
    ['table' => 'inventory_transactions', 'column' => 'performed_by', 'description' => 'Inventory Transactions'],
    ['table' => 'asset_assignments', 'column' => 'assigned_by', 'description' => 'Asset Assignments'],
    ['table' => 'asset_assignments', 'column' => 'assigned_to', 'description' => 'Asset Assignments (assigned_to)'],
    
    // Audit logs
    ['table' => 'configuration_audit_logs', 'column' => 'user_id', 'description' => 'Configuration Audit Logs'],
    ['table' => 'audit_logs', 'column' => 'user_id', 'description' => 'Audit Logs'],
    
    // Session related
    ['table' => 'user_sessions', 'column' => 'user_id', 'description' => 'User Sessions'],
    ['table' => 'password_resets', 'column' => 'user_id', 'description' => 'Password Resets'],
];

// Count records to be deleted
echo "Records to be deleted:\n";
echo str_repeat("-", 60) . "\n";

$totalRecords = 0;
$recordCounts = [];

foreach ($tablesToClean as $tableInfo) {
    $table = $tableInfo['table'];
    $column = $tableInfo['column'];
    $description = $tableInfo['description'];
    
    // Check if table exists using direct query (SHOW TABLES doesn't support prepared statements)
    try {
        $tableCheck = $db->getConnection()->query("SHOW TABLES LIKE '{$table}'");
        if ($tableCheck->num_rows === 0) {
            continue;
        }
        
        // Check if column exists
        $columnCheck = $db->getConnection()->query("SHOW COLUMNS FROM `{$table}` LIKE '{$column}'");
        if ($columnCheck->num_rows === 0) {
            continue;
        }
        
        $count = $db->getResults("SELECT COUNT(*) as cnt FROM `{$table}` WHERE `{$column}` IN ({$testUserIdsStr})")[0]['cnt'] ?? 0;
        
        if ($count > 0) {
            echo sprintf("  %-40s %d records\n", $description . " ({$table}.{$column}):", $count);
            $totalRecords += $count;
            $recordCounts[] = ['table' => $table, 'column' => $column, 'count' => $count];
        }
    } catch (Exception $e) {
        // Table or column doesn't exist, skip
        continue;
    }
}

echo str_repeat("-", 60) . "\n";
echo sprintf("  %-40s %d records\n", "Test Users:", count($testUsers));
$totalRecords += count($testUsers);
echo sprintf("  %-40s %d records\n", "TOTAL:", $totalRecords);
echo "\n";

if ($dryRun) {
    echo "Dry run complete. No data was deleted.\n";
    echo "Run without --dry-run to actually delete the data.\n";
    exit(0);
}

// Confirmation
if (!$skipConfirm) {
    echo "WARNING: This will permanently delete {$totalRecords} records!\n";
    echo "Type 'DELETE' to confirm: ";
    $handle = fopen("php://stdin", "r");
    $line = trim(fgets($handle));
    fclose($handle);
    
    if ($line !== 'DELETE') {
        echo "Aborted.\n";
        exit(1);
    }
}

echo "\nDeleting records...\n";

// Delete in reverse order (dependent tables first)
$deletedCounts = [];

foreach (array_reverse($tablesToClean) as $tableInfo) {
    $table = $tableInfo['table'];
    $column = $tableInfo['column'];
    
    // Check if table and column exist using direct query
    try {
        $tableCheck = $db->getConnection()->query("SHOW TABLES LIKE '{$table}'");
        if ($tableCheck->num_rows === 0) continue;
        
        $columnCheck = $db->getConnection()->query("SHOW COLUMNS FROM `{$table}` LIKE '{$column}'");
        if ($columnCheck->num_rows === 0) continue;
        
        $sql = "DELETE FROM `{$table}` WHERE `{$column}` IN ({$testUserIdsStr})";
        $db->getConnection()->query($sql);
        $affected = $db->getConnection()->affected_rows;
        if ($affected > 0) {
            echo "  Deleted {$affected} records from {$table}\n";
            $deletedCounts[$table] = ($deletedCounts[$table] ?? 0) + $affected;
        }
    } catch (Exception $e) {
        echo "  Error deleting from {$table}: " . $e->getMessage() . "\n";
    }
}

// Finally delete the test users
try {
    $sql = "DELETE FROM users WHERE id IN ({$testUserIdsStr})";
    $db->getConnection()->query($sql);
    $affected = $db->getConnection()->affected_rows;
    echo "  Deleted {$affected} test users\n";
} catch (Exception $e) {
    echo "  Error deleting users: " . $e->getMessage() . "\n";
}

echo "\n===========================================\n";
echo "  CLEANUP COMPLETE\n";
echo "===========================================\n";
