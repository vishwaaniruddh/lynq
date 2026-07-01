<?php
/**
 * Comprehensive Test Runner for ADV CRM Users Module
 * Final Integration and Testing Checkpoint
 * 
 * This script runs all property-based tests, unit tests, and integration tests
 * to validate the complete implementation against all requirements.
 */

require_once __DIR__ . '/../config/autoload.php';

// Include all test files
require_once 'PropertyTestBase.php';
require_once 'DatabaseSchemaIntegrityTest.php';
require_once 'AuthenticationSecurityTest.php';
require_once 'SessionExpirationTest.php';
require_once 'InputValidationTest.php';
require_once 'PermissionDelegationTest.php';
require_once 'PermissionRevocationTest.php';
require_once 'ConsistentPermissionCheckingTest.php';
require_once 'AuditTrailCompletenessTest.php';
require_once 'CompanyIsolationTest.php';
require_once 'ContractorScopeRestrictionTest.php';
require_once 'CrossCompanyAccessPreventionTest.php';
require_once 'AdvUserCompanyAssignmentTest.php';
require_once 'RoleAssignmentTypeRestrictionsTest.php';
require_once 'AdvOnlyFunctionAccessControlTest.php';
require_once 'MenuVisibilityTest.php';
require_once 'UserCrudTest.php';
require_once 'UserManagementUIIntegrationTest.php';
require_once 'ApiIntegrationTest.php';
require_once 'PermissionDelegationIntegrationTest.php';
require_once 'SecurityFeatureTest.php';

// Start session for testing
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

echo "╔══════════════════════════════════════════════════════════════════╗\n";
echo "║     ADV CRM Users Module - Final Integration Test Suite          ║\n";
echo "║                    All Requirements Validation                    ║\n";
echo "╚══════════════════════════════════════════════════════════════════╝\n\n";

$allTestsPassed = true;
$testResults = [];
$startTime = microtime(true);


/**
 * Run a test and capture results
 */
function runTest($testName, $testClass, $methodName = 'runTests') {
    global $allTestsPassed, $testResults;
    
    echo "┌─────────────────────────────────────────────────────────────────┐\n";
    echo "│ Running: $testName\n";
    echo "└─────────────────────────────────────────────────────────────────┘\n";
    
    try {
        $test = new $testClass();
        
        // Check if the method exists
        if (method_exists($test, $methodName)) {
            $result = $test->$methodName();
        } elseif (method_exists($test, 'runAllTests')) {
            $result = $test->runAllTests();
        } else {
            echo "  ⚠ No runTests or runAllTests method found\n";
            $result = false;
        }
        
        $testResults[$testName] = $result;
        
        if (!$result) {
            $allTestsPassed = false;
        }
        
        echo "  Result: " . ($result ? "✓ PASSED" : "✗ FAILED") . "\n\n";
        
        // Cleanup if method exists and is public
        if (method_exists($test, 'cleanupTestData')) {
            $reflection = new ReflectionMethod($test, 'cleanupTestData');
            if ($reflection->isPublic()) {
                $test->cleanupTestData();
            }
        }
        
        return $result;
    } catch (Exception $e) {
        echo "  ✗ ERROR: " . $e->getMessage() . "\n";
        echo "  Stack trace: " . $e->getTraceAsString() . "\n\n";
        $testResults[$testName] = false;
        $allTestsPassed = false;
        return false;
    }
}

// ============================================================================
// SECTION 1: DATABASE AND SCHEMA TESTS
// ============================================================================
echo "\n═══════════════════════════════════════════════════════════════════\n";
echo "  SECTION 1: DATABASE SCHEMA INTEGRITY TESTS\n";
echo "  Property 15: Referential Integrity Maintenance\n";
echo "═══════════════════════════════════════════════════════════════════\n\n";

runTest('Database Schema Integrity (Property 15)', 'DatabaseSchemaIntegrityTest');

// ============================================================================
// SECTION 2: AUTHENTICATION AND SECURITY TESTS
// ============================================================================
echo "\n═══════════════════════════════════════════════════════════════════\n";
echo "  SECTION 2: AUTHENTICATION AND SECURITY TESTS\n";
echo "  Properties 8, 9, 12: Authentication, Session, Input Validation\n";
echo "═══════════════════════════════════════════════════════════════════\n\n";

runTest('Authentication Security (Property 8)', 'AuthenticationSecurityTest', 'runAllTests');
runTest('Session Expiration (Property 9)', 'SessionExpirationTest', 'runAllTests');
runTest('Input Validation (Property 12)', 'InputValidationTest', 'runAllTests');

// ============================================================================
// SECTION 3: PERMISSION ENGINE TESTS
// ============================================================================
echo "\n═══════════════════════════════════════════════════════════════════\n";
echo "  SECTION 3: PERMISSION ENGINE TESTS\n";
echo "  Properties 6, 7, 11, 14: Delegation, Revocation, Checking, Audit\n";
echo "═══════════════════════════════════════════════════════════════════\n\n";

runTest('Permission Delegation (Property 6)', 'PermissionDelegationTest');
runTest('Permission Revocation (Property 7)', 'PermissionRevocationTest');
runTest('Consistent Permission Checking (Property 11)', 'ConsistentPermissionCheckingTest');
runTest('Audit Trail Completeness (Property 14)', 'AuditTrailCompletenessTest');


// ============================================================================
// SECTION 4: COMPANY ISOLATION TESTS
// ============================================================================
echo "\n═══════════════════════════════════════════════════════════════════\n";
echo "  SECTION 4: COMPANY ISOLATION AND MULTI-TENANCY TESTS\n";
echo "  Properties 2, 4, 13: Isolation, Contractor Scope, Cross-Company\n";
echo "═══════════════════════════════════════════════════════════════════\n\n";

runTest('Company Isolation (Property 2)', 'CompanyIsolationTest');
runTest('Contractor Scope Restriction (Property 4)', 'ContractorScopeRestrictionTest');
runTest('Cross-Company Access Prevention (Property 13)', 'CrossCompanyAccessPreventionTest');

// ============================================================================
// SECTION 5: USER MANAGEMENT TESTS
// ============================================================================
echo "\n═══════════════════════════════════════════════════════════════════\n";
echo "  SECTION 5: USER MANAGEMENT TESTS\n";
echo "  Properties 1, 3, 5: ADV Assignment, Role Restrictions, ADV-Only\n";
echo "═══════════════════════════════════════════════════════════════════\n\n";

runTest('ADV User Company Assignment (Property 1)', 'AdvUserCompanyAssignmentTest');
runTest('Role Assignment Type Restrictions (Property 3)', 'RoleAssignmentTypeRestrictionsTest');
runTest('ADV-Only Function Access Control (Property 5)', 'AdvOnlyFunctionAccessControlTest');

// ============================================================================
// SECTION 6: MENU AND UI TESTS
// ============================================================================
echo "\n═══════════════════════════════════════════════════════════════════\n";
echo "  SECTION 6: MENU VISIBILITY AND UI TESTS\n";
echo "  Property 10: Permission-Based Menu Visibility\n";
echo "═══════════════════════════════════════════════════════════════════\n\n";

runTest('Menu Visibility (Property 10)', 'MenuVisibilityTest', 'runAllTests');

// ============================================================================
// SECTION 7: UNIT AND INTEGRATION TESTS
// ============================================================================
echo "\n═══════════════════════════════════════════════════════════════════\n";
echo "  SECTION 7: UNIT AND INTEGRATION TESTS\n";
echo "  User CRUD, UI Integration, API Integration\n";
echo "═══════════════════════════════════════════════════════════════════\n\n";

runTest('User CRUD Operations', 'UserCrudTest');
runTest('User Management UI Integration', 'UserManagementUIIntegrationTest');
runTest('API Integration', 'ApiIntegrationTest');
runTest('Permission Delegation Integration', 'PermissionDelegationIntegrationTest');

// ============================================================================
// SECTION 8: SECURITY FEATURE TESTS
// ============================================================================
echo "\n═══════════════════════════════════════════════════════════════════\n";
echo "  SECTION 8: ADVANCED SECURITY FEATURE TESTS\n";
echo "  Password Policy, Account Lockout, Security Events\n";
echo "═══════════════════════════════════════════════════════════════════\n\n";

runTest('Security Features', 'SecurityFeatureTest', 'runAllTests');

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

foreach ($testResults as $testName => $result) {
    $status = $result ? "✓ PASSED" : "✗ FAILED";
    echo sprintf("  %-50s %s\n", $testName, $status);
    if ($result) {
        $passedCount++;
    } else {
        $failedCount++;
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
echo "  Requirement 1 (ADV User Management): Properties 1, 2, 3\n";
echo "  Requirement 2 (Contractor Management): Properties 4, 5\n";
echo "  Requirement 3 (Company Isolation): Properties 2, 13\n";
echo "  Requirement 4 (Permission Delegation): Properties 6, 7, 14\n";
echo "  Requirement 5 (Authentication): Properties 8, 9\n";
echo "  Requirement 6 (Menu Visibility): Property 10\n";
echo "  Requirement 7 (Permission Checking): Property 11\n";
echo "  Requirement 8 (Data Validation): Properties 12, 15\n";
echo "─────────────────────────────────────────────────────────────────────\n\n";

if ($allTestsPassed) {
    echo "╔══════════════════════════════════════════════════════════════════╗\n";
    echo "║  ✓ ALL TESTS PASSED - System meets all correctness properties    ║\n";
    echo "║    The ADV CRM Users Module is ready for deployment.             ║\n";
    echo "╚══════════════════════════════════════════════════════════════════╝\n";
    exit(0);
} else {
    echo "╔══════════════════════════════════════════════════════════════════╗\n";
    echo "║  ✗ SOME TESTS FAILED - Please review and fix failing tests       ║\n";
    echo "║    The system does not meet all correctness properties.          ║\n";
    echo "╚══════════════════════════════════════════════════════════════════╝\n";
    exit(1);
}
