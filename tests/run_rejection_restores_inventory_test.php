<?php
/**
 * Runner for Rejection Restores Sender Inventory Property Test
 * 
 * **Feature: inventory-dispatch-receive-flow, Property 5: Rejection Restores Sender Inventory**
 * **Validates: Requirements 4.1, 4.2, 7.6**
 */

require_once __DIR__ . '/RejectionRestoresSenderInventoryTest.php';

echo "Starting Rejection Restores Sender Inventory Property Test...\n";
echo "============================================================\n\n";

$test = new RejectionRestoresSenderInventoryTest();
$passed = $test->runTests();

echo "\n============================================================\n";
if ($passed) {
    echo "All property tests PASSED!\n";
    exit(0);
} else {
    echo "Some property tests FAILED!\n";
    exit(1);
}
