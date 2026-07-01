<?php
/**
 * Comprehensive Test Runner for IP Configuration Management Module
 * Final Checkpoint - Task 16
 * 
 * This script runs all property-based tests for the IP Configuration Management module
 * to validate the complete implementation against all requirements.
 * 
 * Properties covered:
 * - Property 1: IP Format Validation (Requirements 1.1)
 * - Property 2: IP_Master Uniqueness (Requirements 1.2)
 * - Property 3: IP_Master Display Completeness (Requirements 1.3, 3.3)
 * - Property 4: Configured IP Edit Prevention (Requirements 1.4)
 * - Property 5: IP Deletion Constraint (Requirements 1.5)
 * - Property 6: Available Router Filtering (Requirements 2.2)
 * - Property 7: Automatic IP Assignment Validity (Requirements 3.1)
 * - Property 8: Lock Exclusivity (Requirements 4.2, 11.2)
 * - Property 9: Lock Duration Constraint (Requirements 4.1)
 * - Property 10: Lock Expiry Handling (Requirements 4.3)
 * - Property 11: Configuration Completion Binding (Requirements 4.4, 5.1)
 * - Property 12: Cancel Lock Release (Requirements 4.5)
 * - Property 14: Router-to-IP Query (Requirements 5.3)
 * - Property 15: IP-to-Router Query (Requirements 5.4)
 * - Property 16: Unbind Status Reset (Requirements 6.2)
 * - Property 17: Unbind Audit Logging (Requirements 6.3)
 * - Property 18: Dashboard Router Count Accuracy (Requirements 7.1)
 * - Property 19: Dashboard IP Count Accuracy (Requirements 7.2)
 * - Property 20: Audit Log Completeness (Requirements 9.1)
 * - Property 21: Bulk Upload Validation (Requirements 10.1)
 * - Property 22: Concurrent Lock Prevention (Requirements 11.1, 11.3)
 * - Property 23: Export Data Round-Trip (Requirements 8.4)
 */

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include test files
require_once __DIR__ . '/IPFormatValidationTest.php';
require_once __DIR__ . '/IPMasterUniquenessTest.php';
require_once __DIR__ . '/IPMasterDisplayCompletenessTest.php';
require_once __DIR__ . '/IPMasterConstraintsTest.php';
require_once __DIR__ . '/IPMasterBulkUploadTest.php';
require_once __DIR__ . '/AvailableRouterFilteringTest.php';
require_once __DIR__ . '/ConfigurationWorkflowTest.php';
require_once __DIR__ . '/LockDurationConstraintTest.php';
require_once __DIR__ . '/LockExclusivityExpiryTest.php';
require_once __DIR__ . '/RouterToIPQueryTest.php';
require_once __DIR__ . '/IPToRouterQueryTest.php';
require_once __DIR__ . '/UnbindOperationsTest.php';
require_once __DIR__ . '/ConfigurationDashboardAccuracyTest.php';
require_once __DIR__ . '/AuditLogCompletenessConfigTest.php';
require_once __DIR__ . '/ConcurrentLockPreventionTest.php';
require_once __DIR__ . '/ExportRoundTripConfigTest.php';

echo "╔══════════════════════════════════════════════════════════════════╗\n";
echo "║   IP Configuration Management Module - Final Test Suite          ║\n";
echo "║              All Properties Validation (Task 16)                 ║\n";
echo "╚══════════════════════════════════════════════════════════════════╝\n\n";

$allTestsPassed = true;
$testResults = [];
$startTime = microtime(true);

/**
 * Run a test and capture results
 */
function runPropertyTest($propertyNumber, $propertyName, $testClass, $requirements) {
    global $allTestsPassed, $testResults;
    
    echo "┌─────────────────────────────────────────────────────────────────┐\n";
    echo "│ Property $propertyNumber: $propertyName\n";
    echo "│ Validates: Requirements $requirements\n";
    echo "└─────────────────────────────────────────────────────────────────┘\n";
    
    try {
        $test = new $testClass();
        
        // Try different method names
        if (method_exists($test, 'runAllTests')) {
            $result = $test->runAllTests();
        } elseif (method_exists($test, 'runTests')) {
            $result = $test->runTests();
        } else {
            echo "  ⚠ No runAllTests or runTests method found\n";
            $result = false;
        }
        
        // Handle array results
        if (is_array($result)) {
            $passed = !in_array(false, $result, true);
        } else {
            $passed = (bool)$result;
        }
        
        $testResults["Property $propertyNumber: $propertyName"] = $passed;
        
        if (!$passed) {
            $allTestsPassed = false;
        }
        
        echo "  Result: " . ($passed ? "✓ PASSED" : "✗ FAILED") . "\n\n";
        
        return $passed;
    } catch (Exception $e) {
        echo "  ✗ ERROR: " . $e->getMessage() . "\n";
        echo "  File: " . $e->getFile() . ":" . $e->getLine() . "\n\n";
        $testResults["Property $propertyNumber: $propertyName"] = false;
        $allTestsPassed = false;
        return false;
    }
}

// ============================================================================
// SECTION 1: IP_MASTER MANAGEMENT TESTS (Properties 1-5)
// ============================================================================
echo "\n═══════════════════════════════════════════════════════════════════\n";
echo "  SECTION 1: IP_MASTER MANAGEMENT TESTS\n";
echo "  Properties 1-5: Format, Uniqueness, Display, Edit, Delete\n";
echo "═══════════════════════════════════════════════════════════════════\n\n";

runPropertyTest(1, 'IP Format Validation', 'IPFormatValidationTest', '1.1');
runPropertyTest(2, 'IP_Master Uniqueness', 'IPMasterUniquenessTest', '1.2');
runPropertyTest(3, 'IP_Master Display Completeness', 'IPMasterDisplayCompletenessTest', '1.3, 3.3');
runPropertyTest('4-5', 'IP Edit/Delete Constraints', 'IPMasterConstraintsTest', '1.4, 1.5');

// ============================================================================
// SECTION 2: ROUTER AND CONFIGURATION TESTS (Properties 6-7)
// ============================================================================
echo "\n═══════════════════════════════════════════════════════════════════\n";
echo "  SECTION 2: ROUTER AND CONFIGURATION TESTS\n";
echo "  Properties 6-7: Router Filtering, IP Assignment\n";
echo "═══════════════════════════════════════════════════════════════════\n\n";

runPropertyTest(6, 'Available Router Filtering', 'AvailableRouterFilteringTest', '2.2');
runPropertyTest('7,11,12', 'Configuration Workflow', 'ConfigurationWorkflowTest', '3.1, 4.4, 4.5, 5.1');

// ============================================================================
// SECTION 3: LOCK MANAGEMENT TESTS (Properties 8-10)
// ============================================================================
echo "\n═══════════════════════════════════════════════════════════════════\n";
echo "  SECTION 3: LOCK MANAGEMENT TESTS\n";
echo "  Properties 8-10: Exclusivity, Duration, Expiry\n";
echo "═══════════════════════════════════════════════════════════════════\n\n";

runPropertyTest(9, 'Lock Duration Constraint', 'LockDurationConstraintTest', '4.1');
runPropertyTest('8,10', 'Lock Exclusivity & Expiry', 'LockExclusivityExpiryTest', '4.2, 4.3, 11.2');

// ============================================================================
// SECTION 4: BINDING AND QUERY TESTS (Properties 14-17)
// ============================================================================
echo "\n═══════════════════════════════════════════════════════════════════\n";
echo "  SECTION 4: BINDING AND QUERY TESTS\n";
echo "  Properties 14-17: Router-IP Queries, Unbind Operations\n";
echo "═══════════════════════════════════════════════════════════════════\n\n";

runPropertyTest(14, 'Router-to-IP Query', 'RouterToIPQueryTest', '5.3');
runPropertyTest(15, 'IP-to-Router Query', 'IPToRouterQueryTest', '5.4');
runPropertyTest('16,17', 'Unbind Operations', 'UnbindOperationsTest', '6.2, 6.3');

// ============================================================================
// SECTION 5: DASHBOARD AND REPORTING TESTS (Properties 18-20, 23)
// ============================================================================
echo "\n═══════════════════════════════════════════════════════════════════\n";
echo "  SECTION 5: DASHBOARD AND REPORTING TESTS\n";
echo "  Properties 18-20, 23: Dashboard Accuracy, Audit, Export\n";
echo "═══════════════════════════════════════════════════════════════════\n\n";

runPropertyTest('18,19', 'Dashboard Count Accuracy', 'ConfigurationDashboardAccuracyTest', '7.1, 7.2');
runPropertyTest(20, 'Audit Log Completeness', 'AuditLogCompletenessConfigTest', '9.1');
runPropertyTest(23, 'Export Data Round-Trip', 'ExportRoundTripConfigTest', '8.4');

// ============================================================================
// SECTION 6: BULK AND CONCURRENT TESTS (Properties 21-22)
// ============================================================================
echo "\n═══════════════════════════════════════════════════════════════════\n";
echo "  SECTION 6: BULK AND CONCURRENT TESTS\n";
echo "  Properties 21-22: Bulk Upload, Concurrent Lock Prevention\n";
echo "═══════════════════════════════════════════════════════════════════\n\n";

runPropertyTest(21, 'Bulk Upload Validation', 'IPMasterBulkUploadTest', '10.1');
runPropertyTest(22, 'Concurrent Lock Prevention', 'ConcurrentLockPreventionTest', '11.1, 11.3');

// ============================================================================
// FINAL SUMMARY
// ============================================================================
$endTime = microtime(true);
$duration = round($endTime - $startTime, 2);

echo "\n╔══════════════════════════════════════════════════════════════════╗\n";
echo "║                      FINAL TEST SUMMARY                           ║\n";
echo "╚══════════════════════════════════════════════════════════════════╝\n\n";

echo "Test Results:\n";
echo "─────────────────────────────────────────────────────────────────────\n";

$passedCount = 0;
$failedCount = 0;
$failedTests = [];

foreach ($testResults as $testName => $result) {
    $status = $result ? "✓ PASSED" : "✗ FAILED";
    echo sprintf("  %-55s %s\n", $testName, $status);
    if ($result) {
        $passedCount++;
    } else {
        $failedCount++;
        $failedTests[] = $testName;
    }
}

echo "─────────────────────────────────────────────────────────────────────\n";
echo sprintf("  Total: %d tests | Passed: %d | Failed: %d\n", 
    count($testResults), $passedCount, $failedCount);
echo sprintf("  Duration: %.2f seconds\n", $duration);
echo "─────────────────────────────────────────────────────────────────────\n\n";

// Requirements Coverage Summary
echo "Requirements Coverage:\n";
echo "─────────────────────────────────────────────────────────────────────\n";
echo "  Requirement 1 (IP_Master Management): Properties 1, 2, 3, 4, 5\n";
echo "  Requirement 2 (Router Selection): Property 6\n";
echo "  Requirement 3 (Automatic IP Assignment): Property 7\n";
echo "  Requirement 4 (IP Locking): Properties 8, 9, 10, 12\n";
echo "  Requirement 5 (Configuration Binding): Properties 11, 14, 15\n";
echo "  Requirement 6 (IP Unbinding): Properties 16, 17\n";
echo "  Requirement 7 (Dashboard): Properties 18, 19\n";
echo "  Requirement 8 (Reports): Property 23\n";
echo "  Requirement 9 (Audit): Property 20\n";
echo "  Requirement 10 (Bulk Upload): Property 21\n";
echo "  Requirement 11 (Concurrent Prevention): Property 22\n";
echo "─────────────────────────────────────────────────────────────────────\n\n";

if ($allTestsPassed) {
    echo "╔══════════════════════════════════════════════════════════════════╗\n";
    echo "║  ✓ ALL TESTS PASSED - System meets all correctness properties    ║\n";
    echo "║    The IP Configuration Management Module is ready.              ║\n";
    echo "╚══════════════════════════════════════════════════════════════════╝\n";
    exit(0);
} else {
    echo "╔══════════════════════════════════════════════════════════════════╗\n";
    echo "║  ✗ SOME TESTS FAILED - Please review and fix failing tests       ║\n";
    echo "╚══════════════════════════════════════════════════════════════════╝\n\n";
    
    echo "Failed Tests:\n";
    foreach ($failedTests as $test) {
        echo "  - $test\n";
    }
    echo "\n";
    
    exit(1);
}
