<?php
/**
 * Runner for Courier Name Uniqueness Property Test
 * **Feature: crm-sidebar-restructure, Property 7: Courier Name Uniqueness**
 * **Validates: Requirements 2.2**
 */

require_once __DIR__ . '/CourierNameUniquenessTest.php';

echo "Starting Courier Name Uniqueness Property Tests...\n\n";

$test = new CourierNameUniquenessTest();
$passed = $test->runTests();

echo "\n";
if ($passed) {
    echo "=== ALL COURIER NAME UNIQUENESS TESTS PASSED ===\n";
    exit(0);
} else {
    echo "=== SOME COURIER NAME UNIQUENESS TESTS FAILED ===\n";
    exit(1);
}
