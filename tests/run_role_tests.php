<?php
/**
 * Run Role Management Property Tests
 * 
 * This script runs all property-based tests related to role management
 * including role assignment type restrictions.
 */

echo "========================================\n";
echo "Running Role Management Property Tests\n";
echo "========================================\n\n";

$results = [];

// Test 1: Role Assignment Type Restrictions
echo "Test 1: Role Assignment Type Restrictions\n";
echo "----------------------------------------\n";
require_once __DIR__ . '/RoleAssignmentTypeRestrictionsTest.php';
$test1 = new RoleAssignmentTypeRestrictionsTest();
$results['RoleAssignmentTypeRestrictions'] = $test1->runTests();
echo "\n";

// Summary
echo "========================================\n";
echo "Test Summary\n";
echo "========================================\n";

$passed = 0;
$failed = 0;

foreach ($results as $testName => $result) {
    $status = $result ? '✓ PASSED' : '✗ FAILED';
    echo "$testName: $status\n";
    if ($result) {
        $passed++;
    } else {
        $failed++;
    }
}

echo "\nTotal: $passed passed, $failed failed\n";

exit($failed > 0 ? 1 : 0);
