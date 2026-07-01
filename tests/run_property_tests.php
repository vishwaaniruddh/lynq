<?php
/**
 * Property Test Runner
 * Runs all property-based tests for the ADV CRM Users Module
 */

require_once __DIR__ . '/../config/autoload.php';
require_once 'DatabaseSchemaIntegrityTest.php';

function runAllPropertyTests() {
    echo "ADV CRM Users Module - Property-Based Tests\n";
    echo "==========================================\n\n";
    
    $allPassed = true;
    
    // Run Database Schema Integrity Tests
    echo "Starting schema integrity tests...\n";
    $schemaTest = new DatabaseSchemaIntegrityTest();
    $schemaTestPassed = $schemaTest->runTests();
    echo "Schema test result: " . ($schemaTestPassed ? 'PASSED' : 'FAILED') . "\n";
    $allPassed = $allPassed && $schemaTestPassed;
    
    // Clean up test data
    try {
        echo "Cleaning up test data...\n";
        $schemaTest->cleanupTestData();
        echo "Cleanup completed.\n";
    } catch (Exception $e) {
        echo "Cleanup error: " . $e->getMessage() . "\n";
    }
    
    echo "\n==========================================\n";
    echo "Debug: allPassed = " . ($allPassed ? 'true' : 'false') . "\n";
    if ($allPassed) {
        echo "✓ All property tests PASSED\n";
        return 0;
    } else {
        echo "✗ Some property tests FAILED\n";
        return 1;
    }
}

// Run tests if this file is executed directly
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    $exitCode = runAllPropertyTests();
    exit($exitCode);
}