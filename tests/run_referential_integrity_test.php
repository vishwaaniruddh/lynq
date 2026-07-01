<?php
/**
 * Test Runner for Referential Integrity Prevention Property Test
 * **Feature: crm-master-modules, Property 5: Referential Integrity Prevention**
 * **Validates: Requirements 3.4, 4.4, 9.3**
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== Running Referential Integrity Prevention Property Test ===\n\n";

// Include test file
require_once __DIR__ . '/ReferentialIntegrityPreventionTest.php';

// Run the test
$test = new ReferentialIntegrityPreventionTest();
$result = $test->runTests();
$test->cleanupTestData();

echo "\n=== Test Summary ===\n";
if ($result) {
    echo "✓ All Referential Integrity Prevention tests PASSED\n";
    exit(0);
} else {
    echo "✗ Some Referential Integrity Prevention tests FAILED\n";
    exit(1);
}
