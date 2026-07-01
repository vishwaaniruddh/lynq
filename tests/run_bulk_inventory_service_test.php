<?php
/**
 * Test Runner for BulkInventoryService Unit Tests
 * 
 * Requirements: 4.1, 4.2, 4.4
 */

require_once __DIR__ . '/BulkInventoryServiceTest.php';

echo "========================================\n";
echo "BulkInventoryService Unit Tests\n";
echo "========================================\n\n";

$test = new BulkInventoryServiceTest();
$passed = $test->runTests();

echo "\n========================================\n";
if ($passed) {
    echo "RESULT: ALL TESTS PASSED\n";
    exit(0);
} else {
    echo "RESULT: SOME TESTS FAILED\n";
    exit(1);
}
