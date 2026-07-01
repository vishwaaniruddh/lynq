<?php
/**
 * Runner for Status and Repair Service Unit Tests
 * Requirements: 6.1, 7.1, 7.4
 */

require_once __DIR__ . '/StatusRepairServiceTest.php';

echo "Starting Status and Repair Service Unit Tests...\n";
echo "=================================================\n\n";

$test = new StatusRepairServiceTest();
$success = $test->runTests();

echo "\n=================================================\n";
if ($success) {
    echo "All tests PASSED!\n";
} else {
    echo "Some tests FAILED!\n";
}

exit($success ? 0 : 1);
