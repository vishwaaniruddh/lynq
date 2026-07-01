<?php
/**
 * Runner for Delegation Property Tests
 * **Feature: site-management-delegation, Property 4: Delegation creates proper audit trail**
 * **Feature: site-management-delegation, Property 5: No duplicate active delegations**
 * **Feature: site-management-delegation, Property 6: Delegation filtering returns correct results**
 * **Feature: site-management-delegation, Property 9: Rejection requires notes**
 * **Validates: Requirements 2.1, 2.2, 2.4, 3.1, 3.2, 4.3**
 */

require_once __DIR__ . '/DelegationPropertyTest.php';

echo "Starting Delegation Property Tests...\n\n";

$test = new DelegationPropertyTest();
$passed = $test->runTests();

echo "\n";
if ($passed) {
    echo "=== ALL DELEGATION PROPERTY TESTS PASSED ===\n";
    exit(0);
} else {
    echo "=== SOME DELEGATION PROPERTY TESTS FAILED ===\n";
    exit(1);
}
