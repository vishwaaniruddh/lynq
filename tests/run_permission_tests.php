<?php
/**
 * Test Runner for Permission Engine Property Tests
 */

require_once __DIR__ . '/../config/autoload.php';

// Include all permission test classes
require_once 'PermissionDelegationTest.php';
require_once 'PermissionRevocationTest.php';
require_once 'ConsistentPermissionCheckingTest.php';
require_once 'AuditTrailCompletenessTest.php';

echo "=== ADV CRM Permission Engine Property Tests ===\n";
echo "Running comprehensive property-based tests for permission system...\n\n";

$allTestsPassed = true;
$testResults = [];

// Test 1: Permission Delegation Verification
echo "1. Running Permission Delegation Tests...\n";
$delegationTest = new PermissionDelegationTest();
$delegationResult = $delegationTest->runTests();
$testResults['delegation'] = $delegationResult;
if (!$delegationResult) {
    $allTestsPassed = false;
}
echo "\n";

// Test 2: Permission Revocation Immediate Effect
echo "2. Running Permission Revocation Tests...\n";
$revocationTest = new PermissionRevocationTest();
$revocationResult = $revocationTest->runTests();
$testResults['revocation'] = $revocationResult;
if (!$revocationResult) {
    $allTestsPassed = false;
}
echo "\n";

// Test 3: Consistent Permission Checking
echo "3. Running Consistent Permission Checking Tests...\n";
$consistencyTest = new ConsistentPermissionCheckingTest();
$consistencyResult = $consistencyTest->runTests();
$testResults['consistency'] = $consistencyResult;
if (!$consistencyResult) {
    $allTestsPassed = false;
}
echo "\n";

// Test 4: Audit Trail Completeness
echo "4. Running Audit Trail Completeness Tests...\n";
$auditTest = new AuditTrailCompletenessTest();
$auditResult = $auditTest->runTests();
$testResults['audit'] = $auditResult;
if (!$auditResult) {
    $allTestsPassed = false;
}
echo "\n";

// Summary
echo "=== Test Summary ===\n";
echo "Permission Delegation Verification: " . ($testResults['delegation'] ? "PASSED" : "FAILED") . "\n";
echo "Permission Revocation Immediate Effect: " . ($testResults['revocation'] ? "PASSED" : "FAILED") . "\n";
echo "Consistent Permission Checking: " . ($testResults['consistency'] ? "PASSED" : "FAILED") . "\n";
echo "Audit Trail Completeness: " . ($testResults['audit'] ? "PASSED" : "FAILED") . "\n";
echo "\n";

if ($allTestsPassed) {
    echo "✓ All permission engine property tests PASSED!\n";
    echo "The permission system meets all specified correctness properties.\n";
} else {
    echo "✗ Some permission engine property tests FAILED!\n";
    echo "Please review the failing tests and fix the implementation.\n";
}

exit($allTestsPassed ? 0 : 1);