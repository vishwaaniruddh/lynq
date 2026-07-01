<?php
/**
 * Runner script for Access Control Property Tests
 * **Feature: site-management-delegation, Property 8: Contractor data isolation**
 * **Feature: site-management-delegation, Property 13: Engineer data isolation**
 * **Validates: Requirements 4.1, 4.5, 6.1, 6.3**
 */

require_once __DIR__ . '/AccessControlPropertyTest.php';

echo "Starting Access Control Property Tests...\n\n";

$test = new AccessControlPropertyTest();
$result = $test->runTests();

echo "\n";
if ($result) {
    echo "=== ALL TESTS PASSED ===\n";
    exit(0);
} else {
    echo "=== SOME TESTS FAILED ===\n";
    exit(1);
}
