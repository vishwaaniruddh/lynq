<?php
/**
 * Runner for Pending Receive Creation Property Test
 * **Feature: dispatch-workflow-fixes, Property 5: Pending Receive Creation on Dispatch**
 * **Validates: Requirements 3.1, 3.2**
 */

require_once __DIR__ . '/PendingReceiveCreationPropertyTest.php';

echo "Starting Pending Receive Creation Property Tests...\n";
echo "================================================\n\n";

$test = new PendingReceiveCreationPropertyTest();
$success = $test->runTests();

echo "\n================================================\n";
if ($success) {
    echo "All tests PASSED!\n";
    exit(0);
} else {
    echo "Some tests FAILED!\n";
    exit(1);
}
