<?php
/**
 * Run Audit Log Completeness Property Test
 * 
 * **Feature: adv-crm-inventory-module, Property 10: Audit Log Completeness**
 * **Validates: Requirements 12.1**
 */

require_once __DIR__ . '/AuditLogCompletenessTest.php';

echo "Starting Audit Log Completeness Property Test...\n\n";

$test = new AuditLogCompletenessTest();
$result = $test->runTests();

echo "\n";
if ($result) {
    echo "=== ALL TESTS PASSED ===\n";
    exit(0);
} else {
    echo "=== SOME TESTS FAILED ===\n";
    exit(1);
}
