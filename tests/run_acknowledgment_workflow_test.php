<?php
/**
 * Run Acknowledgment Workflow Integrity Property Test
 * 
 * **Feature: adv-crm-inventory-module, Property 14: Acknowledgment Workflow Integrity**
 * **Validates: Requirements 14.1, 14.2**
 */

require_once __DIR__ . '/AcknowledgmentWorkflowIntegrityTest.php';

echo "Starting Acknowledgment Workflow Integrity Property Test...\n\n";

$test = new AcknowledgmentWorkflowIntegrityTest();
$result = $test->runTests();

echo "\n";
if ($result) {
    echo "=== ALL TESTS PASSED ===\n";
    exit(0);
} else {
    echo "=== SOME TESTS FAILED ===\n";
    exit(1);
}
