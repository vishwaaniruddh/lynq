<?php
/**
 * Run Inventory Dispatch and Receive Flow Migration
 * Creates tables for multi-directional material flow between ADV, Contractors, and Engineers
 */

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Load configuration
require_once __DIR__ . '/config/database.php';

// Load migration classes
require_once __DIR__ . '/migrations/Migration.php';
require_once __DIR__ . '/migrations/2024_12_30_100000_create_inventory_dispatch_receive_tables.php';

echo "=== Running Inventory Dispatch and Receive Flow Migration ===\n\n";

try {
    $migration = new CreateInventoryDispatchReceiveTables();
    $migration->up();
    
    echo "✓ Migration completed successfully!\n\n";
    echo "Created tables:\n";
    echo "  - inventory_counters\n";
    echo "  - pending_receives\n";
    echo "  - pending_receive_items\n";
    echo "  - discrepancies\n";
    echo "  - dispatch_chain\n";
    echo "  - inventory_notifications\n";
    echo "\nModified tables:\n";
    echo "  - dispatches (added sender_type, sender_id, receive_status columns)\n";
    
} catch (Exception $e) {
    echo "✗ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
