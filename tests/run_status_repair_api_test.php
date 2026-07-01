<?php
/**
 * Runner for Status and Repair API Integration Tests
 * 
 * Requirements: 6.1, 7.2
 */

require_once __DIR__ . '/StatusRepairApiTest.php';

echo "=== Running Status and Repair API Integration Tests ===\n";
echo "Requirements: 6.1, 7.2\n\n";

$test = new StatusRepairApiTest();
$success = $test->runTests();

echo "\n=== Test Summary ===\n";
echo $success ? "All tests PASSED\n" : "Some tests FAILED\n";

exit($success ? 0 : 1);
