<?php
/**
 * Test Runner for Service Layer Property Tests
 * 
 * Runs all property tests for the CRM Master Modules Service Layer:
 * - Property 4: Uniqueness Constraint Enforcement
 * - Property 5: Referential Integrity Prevention
 * - Property 6: Zone Deletion Cascade
 * - Property 9: Audit Field Maintenance
 * - Property 12: Required Field Validation
 */

echo "========================================\n";
echo "CRM Master Modules - Service Layer Tests\n";
echo "========================================\n\n";

// Include test files
require_once __DIR__ . '/UniquenessConstraintEnforcementTest.php';
require_once __DIR__ . '/ReferentialIntegrityPreventionTest.php';
require_once __DIR__ . '/ZoneDeletionCascadeTest.php';
require_once __DIR__ . '/AuditFieldMaintenanceTest.php';
require_once __DIR__ . '/RequiredFieldValidationTest.php';

$allPassed = true;
$testResults = [];

// Run Uniqueness Constraint Enforcement Test (Property 4)
echo "\n--- Property 4: Uniqueness Constraint Enforcement ---\n";
$test1 = new UniquenessConstraintEnforcementTest();
$result1 = $test1->runTests();
$test1->cleanupTestData();
$testResults['Property 4: Uniqueness Constraint Enforcement'] = $result1;
$allPassed = $allPassed && $result1;

// Run Referential Integrity Prevention Test (Property 5)
echo "\n--- Property 5: Referential Integrity Prevention ---\n";
$test2 = new ReferentialIntegrityPreventionTest();
$result2 = $test2->runTests();
$test2->cleanupTestData();
$testResults['Property 5: Referential Integrity Prevention'] = $result2;
$allPassed = $allPassed && $result2;

// Run Zone Deletion Cascade Test (Property 6)
echo "\n--- Property 6: Zone Deletion Cascade ---\n";
$test3 = new ZoneDeletionCascadeTest();
$result3 = $test3->runTests();
$test3->cleanupTestData();
$testResults['Property 6: Zone Deletion Cascade'] = $result3;
$allPassed = $allPassed && $result3;

// Run Audit Field Maintenance Test (Property 9)
echo "\n--- Property 9: Audit Field Maintenance ---\n";
$test4 = new AuditFieldMaintenanceTest();
$result4 = $test4->runTests();
$test4->cleanupTestData();
$testResults['Property 9: Audit Field Maintenance'] = $result4;
$allPassed = $allPassed && $result4;

// Run Required Field Validation Test (Property 12)
echo "\n--- Property 12: Required Field Validation ---\n";
$test5 = new RequiredFieldValidationTest();
$result5 = $test5->runTests();
$test5->cleanupTestData();
$testResults['Property 12: Required Field Validation'] = $result5;
$allPassed = $allPassed && $result5;

// Summary
echo "\n========================================\n";
echo "Test Summary\n";
echo "========================================\n";

foreach ($testResults as $testName => $passed) {
    $status = $passed ? '✓ PASSED' : '✗ FAILED';
    echo "$status: $testName\n";
}

echo "\n";
if ($allPassed) {
    echo "All service layer property tests passed!\n";
    exit(0);
} else {
    echo "Some service layer property tests failed.\n";
    exit(1);
}
