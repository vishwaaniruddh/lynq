<?php
/**
 * Verify Inventory Dispatch and Receive Flow Tables
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/config/database.php';

$db = DatabaseConfig::getInstance()->getConnection();

echo "=== Verifying Inventory Dispatch and Receive Flow Tables ===\n\n";

// Tables to verify
$tables = [
    'inventory_counters',
    'pending_receives',
    'pending_receive_items',
    'discrepancies',
    'dispatch_chain',
    'inventory_notifications'
];

foreach ($tables as $table) {
    $result = $db->query("SHOW TABLES LIKE '$table'");
    if ($result->num_rows > 0) {
        echo "✓ Table '$table' exists\n";
        
        // Show columns
        $columns = $db->query("SHOW COLUMNS FROM `$table`");
        echo "  Columns: ";
        $cols = [];
        while ($col = $columns->fetch_assoc()) {
            $cols[] = $col['Field'];
        }
        echo implode(', ', $cols) . "\n\n";
    } else {
        echo "✗ Table '$table' NOT FOUND\n\n";
    }
}

// Verify dispatches table modifications
echo "=== Verifying dispatches table modifications ===\n\n";
$newColumns = ['sender_type', 'sender_id', 'receive_status'];
foreach ($newColumns as $col) {
    $result = $db->query("SHOW COLUMNS FROM `dispatches` LIKE '$col'");
    if ($result->num_rows > 0) {
        $colInfo = $result->fetch_assoc();
        echo "✓ Column 'dispatches.$col' exists (Type: {$colInfo['Type']})\n";
    } else {
        echo "✗ Column 'dispatches.$col' NOT FOUND\n";
    }
}

echo "\n=== Verification Complete ===\n";
