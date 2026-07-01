<?php
/**
 * Test Runner for Engineer Receipt Authorization Property Test
 * **Feature: material-request-module, Property 8: Engineer Receipt Authorization**
 * **Validates: Requirements 7.3, 7.4**
 * 
 * Run: php tests/run_engineer_receipt_authorization_property_test.php
 */

require_once __DIR__ . '/EngineerReceiptAuthorizationPropertyTest.php';

echo "Starting Engineer Receipt Authorization Property Tests...\n";
echo "=========================================================\n\n";

$test = new EngineerReceiptAuthorizationPropertyTest();
$passed = $test->runTests();

echo "\n=========================================================\n";
if ($passed) {
    echo "All Engineer Receipt Authorization Property Tests PASSED!\n";
    exit(0);
} else {
    echo "Some Engineer Receipt Authorization Property Tests FAILED!\n";
    exit(1);
}
