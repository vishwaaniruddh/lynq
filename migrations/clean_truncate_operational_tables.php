<?php
require_once __DIR__ . '/../config/autoload.php';

$tablesToTruncate = [
    // Site & Project Operations
    'sites',
    'site_delegations',
    'delegation_history',
    'engineer_assignments',
    
    // Feasibility Workflow
    'feasibility_checks',
    'feasibility_ada',
    'feasibility_eta',
    'feasibility_reviews',
    
    // Installation Workflow
    'installations',
    'installation_checkpoints',
    'installation_material_receipts',
    'installation_notifications',
    'installation_section_remarks',
    
    // Inventory & Material Management
    'material_requests',
    'material_request_items',
    'dispatches',
    'dispatch_items',
    'dispatch_chain',
    'pending_receives',
    'pending_receive_items',
    'stock',
    'stock_alerts',
    'stock_thresholds',
    'transfers',
    'transfer_items',
    'assets',
    'repairs',
    'discrepancies',
    'router_ip_bindings',
    'inventory_counters',
    'inventory_notifications',
    'inventory_audit_log',
    
    // Tasks & Notes
    'tasks',
    'notes',
    
    // Logs, Audits, Sessions, Temp Data
    'api_access_log',
    'bulk_upload_logs',
    'company_access_log',
    'configuration_audit_log',
    'email_logs',
    'email_queue',
    'email_configuration_audit_log',
    'ip_locks',
    'login_attempts',
    'password_history',
    'permission_audit_log',
    'profile_revisions',
    'push_subscriptions',
    'refresh_tokens',
    'security_events',
    'settings_audit',
    'token_blacklist',
    'user_audit_log',
    'user_sessions'
];

$db = DatabaseConfig::getInstance();
$conn = $db->getConnection();

echo "Starting transactional and operational table truncation...\n";
$conn->query("SET FOREIGN_KEY_CHECKS = 0");

$truncated = [];
$errors = [];

foreach ($tablesToTruncate as $table) {
    // Check if table exists
    $check = $conn->query("SHOW TABLES LIKE '{$table}'");
    if ($check && $check->num_rows > 0) {
        if ($conn->query("TRUNCATE TABLE `{$table}`")) {
            $truncated[] = $table;
            echo "✔ Truncated: {$table}\n";
        } else {
            $errors[] = "Error truncating {$table}: " . $conn->error;
            echo "✖ Error: {$table} - " . $conn->error . "\n";
        }
    } else {
        echo "ℹ Skipped (not found): {$table}\n";
    }
}

$conn->query("SET FOREIGN_KEY_CHECKS = 1");

echo "\n============================================\n";
echo "Summary: " . count($truncated) . " tables truncated successfully.\n";
if (!empty($errors)) {
    echo "Errors: " . count($errors) . "\n";
}
echo "Master tables, Users, Roles, Permissions, and Custom Form definitions are safely PRESERVED.\n";
