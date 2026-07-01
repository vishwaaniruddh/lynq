<?php
/**
 * Test Runner for Authentication System Property Tests
 * Runs all property-based tests for the authentication system
 */

require_once __DIR__ . '/../config/autoload.php';
require_once 'AuthenticationSecurityTest.php';
require_once 'SessionExpirationTest.php';
require_once 'InputValidationTest.php';

// Start session for testing
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

echo "=== ADV CRM Authentication System Property Tests ===\n";
echo "Running property-based tests for authentication security...\n\n";

$allTestsPassed = true;

try {
    // Test 1: Authentication Security
    echo "1. Authentication Security Tests\n";
    echo "================================\n";
    $authTest = new AuthenticationSecurityTest();
    $authResult = $authTest->runAllTests();
    $allTestsPassed = $allTestsPassed && $authResult;
    echo "\n";
    
    // Test 2: Session Expiration
    echo "2. Session Expiration Tests\n";
    echo "===========================\n";
    $sessionTest = new SessionExpirationTest();
    $sessionResult = $sessionTest->runAllTests();
    $allTestsPassed = $allTestsPassed && $sessionResult;
    echo "\n";
    
    // Test 3: Input Validation
    echo "3. Input Validation Tests\n";
    echo "=========================\n";
    $inputTest = new InputValidationTest();
    $inputResult = $inputTest->runAllTests();
    $allTestsPassed = $allTestsPassed && $inputResult;
    echo "\n";
    
} catch (Exception $e) {
    echo "ERROR: Exception during test execution: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
    $allTestsPassed = false;
}

// Final results
echo "=== FINAL RESULTS ===\n";
if ($allTestsPassed) {
    echo "✓ ALL AUTHENTICATION PROPERTY TESTS PASSED!\n";
    echo "The authentication system meets all specified correctness properties.\n";
    exit(0);
} else {
    echo "✗ SOME AUTHENTICATION PROPERTY TESTS FAILED!\n";
    echo "The authentication system does not meet all specified correctness properties.\n";
    echo "Please review the failing tests and fix the implementation.\n";
    exit(1);
}