<?php
/**
 * Clear all dispatches, pending receives, dispatch chains, material requests,
 * feasibility checks, installations, site delegations, stocks, and assets
 * 
 * WARNING: This will delete ALL inventory data!
 */

require_once __DIR__ . '/config/database.php';

$db = DatabaseConfig::getInstance()->getConnection();

echo "Starting cleanup...\n\n";

// Disable foreign key checks temporarily
$db->query("SET FOREIGN_KEY_CHECKS = 0");

// 1. Clear dispatch chain
$result = $db->query("DELETE FROM dispatch_chain");
echo "Cleared dispatch_chain: {$db->affected_rows} rows\n";

// 2. Clear pending receive items (if exists)
$result = $db->query("SHOW TABLES LIKE 'pending_receive_items'");
if ($result->num_rows > 0) {
    $db->query("DELETE FROM pending_receive_items");
    echo "Cleared pending_receive_items: {$db->affected_rows} rows\n";
}

// 3. Clear pending receives
$result = $db->query("DELETE FROM pending_receives");
echo "Cleared pending_receives: {$db->affected_rows} rows\n";

// 4. Clear dispatch items
$result = $db->query("DELETE FROM dispatch_items");
echo "Cleared dispatch_items: {$db->affected_rows} rows\n";

// 5. Clear dispatches
$result = $db->query("DELETE FROM dispatches");
echo "Cleared dispatches: {$db->affected_rows} rows\n";

// 6. Clear inventory counters
$result = $db->query("SHOW TABLES LIKE 'inventory_counters'");
if ($result->num_rows > 0) {
    $db->query("DELETE FROM inventory_counters");
    echo "Cleared inventory_counters: {$db->affected_rows} rows\n";
}

// 7. Clear inventory audit log
$result = $db->query("SHOW TABLES LIKE 'inventory_audit_log'");
if ($result->num_rows > 0) {
    $db->query("DELETE FROM inventory_audit_log");
    echo "Cleared inventory_audit_log: {$db->affected_rows} rows\n";
}

// 8. Clear discrepancies
$result = $db->query("SHOW TABLES LIKE 'discrepancies'");
if ($result->num_rows > 0) {
    $db->query("DELETE FROM discrepancies");
    echo "Cleared discrepancies: {$db->affected_rows} rows\n";
}

// 9. Clear all assets (serializable stock)
$result = $db->query("DELETE FROM assets");
echo "Cleared assets: {$db->affected_rows} rows\n";

// 10. Clear all stock (non-serializable stock)
$result = $db->query("DELETE FROM stock");
echo "Cleared stock: {$db->affected_rows} rows\n";

// 11. Clear material request items
$result = $db->query("SHOW TABLES LIKE 'material_request_items'");
if ($result->num_rows > 0) {
    $db->query("DELETE FROM material_request_items");
    echo "Cleared material_request_items: {$db->affected_rows} rows\n";
}

// 12. Clear material requests
$result = $db->query("SHOW TABLES LIKE 'material_requests'");
if ($result->num_rows > 0) {
    $db->query("DELETE FROM material_requests");
    echo "Cleared material_requests: {$db->affected_rows} rows\n";
}

// 13. Clear feasibility check items (if exists)
$result = $db->query("SHOW TABLES LIKE 'feasibility_check_items'");
if ($result->num_rows > 0) {
    $db->query("DELETE FROM feasibility_check_items");
    echo "Cleared feasibility_check_items: {$db->affected_rows} rows\n";
}

// 14. Clear feasibility checks
$result = $db->query("SHOW TABLES LIKE 'feasibility_checks'");
if ($result->num_rows > 0) {
    $db->query("DELETE FROM feasibility_checks");
    echo "Cleared feasibility_checks: {$db->affected_rows} rows\n";
}

// 15. Clear installation items (if exists)
$result = $db->query("SHOW TABLES LIKE 'installation_items'");
if ($result->num_rows > 0) {
    $db->query("DELETE FROM installation_items");
    echo "Cleared installation_items: {$db->affected_rows} rows\n";
}

// 16. Clear installations
$result = $db->query("SHOW TABLES LIKE 'installations'");
if ($result->num_rows > 0) {
    $db->query("DELETE FROM installations");
    echo "Cleared installations: {$db->affected_rows} rows\n";
}

// 17. Clear site delegation permissions (if exists)
$result = $db->query("SHOW TABLES LIKE 'site_delegation_permissions'");
if ($result->num_rows > 0) {
    $db->query("DELETE FROM site_delegation_permissions");
    echo "Cleared site_delegation_permissions: {$db->affected_rows} rows\n";
}

// 18. Clear site delegations (cancels all delegations from ADV and contractors)
$result = $db->query("SHOW TABLES LIKE 'site_delegations'");
if ($result->num_rows > 0) {
    $db->query("DELETE FROM site_delegations");
    echo "Cleared site_delegations: {$db->affected_rows} rows\n";
}

// 19. Clear engineer assignments (if exists)
$result = $db->query("SHOW TABLES LIKE 'engineer_assignments'");
if ($result->num_rows > 0) {
    $db->query("DELETE FROM engineer_assignments");
    echo "Cleared engineer_assignments: {$db->affected_rows} rows\n";
}

// Re-enable foreign key checks
$db->query("SET FOREIGN_KEY_CHECKS = 1");

echo "\n✓ Cleanup completed successfully!\n";
echo "\nCleared:\n";
echo "- All dispatches and related data\n";
echo "- All pending receives\n";
echo "- All assets (serializable stock)\n";
echo "- All stock (non-serializable stock)\n";
echo "- All inventory counters\n";
echo "- All material requests\n";
echo "- All feasibility checks\n";
echo "- All installations\n";
echo "- All site delegations (ADV and Contractor)\n";
echo "- All engineer assignments\n";
echo "\nDatabase is now clean for fresh testing.\n";
