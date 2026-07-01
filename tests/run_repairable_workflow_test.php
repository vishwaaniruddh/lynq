<?php
/**
 * Runner for Repairable Item Workflow Property Test
 * **Feature: adv-crm-inventory-module, Property 7: Repairable Item Workflow**
 * **Validates: Requirements 7.1**
 */

require_once __DIR__ . '/RepairableItemWorkflowTest.php';

echo "Starting Repairable Item Workflow Property Test...\n";
echo "==================================================\n\n";

$test = new RepairableItemWorkflowTest();
$success = $test->runTests();

echo "\n==================================================\n";
if ($success) {
    echo "All tests PASSED!\n";
} else {
    echo "Some tests FAILED!\n";
}

exit($success ? 0 : 1);
