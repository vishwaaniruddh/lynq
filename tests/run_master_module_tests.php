<?php
/**
 * Master Module Property Test Runner
 * Runs property-based tests for the CRM Master Modules
 * 
 * **Feature: crm-master-modules**
 */

require_once __DIR__ . '/../config/autoload.php';
require_once 'MasterModuleCrudRoundTripTest.php';
require_once 'SoftDeleteStatusChangeTest.php';

function runMasterModulePropertyTests() {
    echo "CRM Master Modules - Property-Based Tests\n";
    echo "==========================================\n\n";
    
    $allPassed = true;
    
    // Run CRUD Round-Trip Consistency Tests
    echo "Starting CRUD Round-Trip Consistency tests...\n";
    $crudTest = new MasterModuleCrudRoundTripTest();
    
    try {
        $crudTestPassed = $crudTest->runTests();
        echo "CRUD Round-Trip test result: " . ($crudTestPassed ? 'PASSED' : 'FAILED') . "\n";
        $allPassed = $allPassed && $crudTestPassed;
    } catch (Exception $e) {
        echo "CRUD Round-Trip test error: " . $e->getMessage() . "\n";
        $allPassed = false;
    }
    
    // Clean up test data
    try {
        echo "\nCleaning up test data...\n";
        $crudTest->cleanupTestData();
        echo "Cleanup completed.\n";
    } catch (Exception $e) {
        echo "Cleanup error: " . $e->getMessage() . "\n";
    }
    
    // Run Soft Delete Status Change Tests
    echo "\nStarting Soft Delete Status Change tests...\n";
    $softDeleteTest = new SoftDeleteStatusChangeTest();
    
    try {
        $softDeleteTestPassed = $softDeleteTest->runTests();
        echo "Soft Delete test result: " . ($softDeleteTestPassed ? 'PASSED' : 'FAILED') . "\n";
        $allPassed = $allPassed && $softDeleteTestPassed;
    } catch (Exception $e) {
        echo "Soft Delete test error: " . $e->getMessage() . "\n";
        $allPassed = false;
    }
    
    // Clean up test data
    try {
        echo "\nCleaning up test data...\n";
        $softDeleteTest->cleanupTestData();
        echo "Cleanup completed.\n";
    } catch (Exception $e) {
        echo "Cleanup error: " . $e->getMessage() . "\n";
    }
    
    echo "\n==========================================\n";
    if ($allPassed) {
        echo "✓ All master module property tests PASSED\n";
        return 0;
    } else {
        echo "✗ Some master module property tests FAILED\n";
        return 1;
    }
}

// Run tests if this file is executed directly
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    $exitCode = runMasterModulePropertyTests();
    exit($exitCode);
}
