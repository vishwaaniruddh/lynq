<?php
/**
 * Runner for Courier Soft Delete Property Test
 * **Feature: crm-sidebar-restructure, Property 5: Courier Soft Delete Status Change**
 * **Validates: Requirements 2.4**
 */

require_once __DIR__ . '/CourierSoftDeleteTest.php';

echo "Starting Courier Soft Delete Property Tests...\n\n";

$test = new CourierSoftDeleteTest();
$passed = $test->runTests();

echo "\n";
if ($passed) {
    echo "=== ALL COURIER SOFT DELETE TESTS PASSED ===\n";
    exit(0);
} else {
    echo "=== SOME COURIER SOFT DELETE TESTS FAILED ===\n";
    exit(1);
}
