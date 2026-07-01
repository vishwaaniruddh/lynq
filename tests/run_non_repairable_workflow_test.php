<?php
/**
 * Runner for Non-Repairable Item Workflow Property Test
 * **Feature: adv-crm-inventory-module, Property 8: Non-Repairable Item Workflow**
 * **Validates: Requirements 7.4**
 */

require_once __DIR__ . '/NonRepairableItemWorkflowTest.php';

echo "Starting Non-Repairable Item Workflow Property Test...\n";
echo "======================================================\n\n";

$test = new NonRepairableItemWorkflowTest();
$success = $test->runTests();

echo "\n======================================================\n";
if ($success) {
    echo "All tests PASSED!\n";
} else {
    echo "Some tests FAILED!\n";
}

exit($success ? 0 : 1);
