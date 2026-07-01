<?php
/**
 * Runner script for Concurrent Lock Prevention Property Test
 * 
 * **Feature: ip-configuration-management, Property 22: Concurrent Lock Prevention**
 * **Validates: Requirements 11.1, 11.3**
 */

// Change to the tests directory
chdir(__DIR__);

// Include the test file
require_once __DIR__ . '/ConcurrentLockPreventionTest.php';

echo "===========================================\n";
echo "Concurrent Lock Prevention Property Tests\n";
echo "===========================================\n";
echo "Property 22: Concurrent Lock Prevention\n";
echo "Validates: Requirements 11.1, 11.3\n";
echo "===========================================\n\n";

try {
    $test = new ConcurrentLockPreventionTest();
    $results = $test->runAllTests();
    
    $allPassed = !in_array(false, $results, true);
    
    echo "\n===========================================\n";
    if ($allPassed) {
        echo "RESULT: ALL TESTS PASSED\n";
    } else {
        echo "RESULT: SOME TESTS FAILED\n";
    }
    echo "===========================================\n";
    
    exit($allPassed ? 0 : 1);
    
} catch (Exception $e) {
    echo "\nFATAL ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
