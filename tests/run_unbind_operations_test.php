<?php
/**
 * Runner script for Unbind Operations Property Tests
 * 
 * **Feature: ip-configuration-management, Property 16: Unbind Status Reset**
 * **Feature: ip-configuration-management, Property 17: Unbind Audit Logging**
 * **Validates: Requirements 6.2, 6.3**
 * 
 * Usage: php run_unbind_operations_test.php
 */

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Change to the tests directory
chdir(__DIR__);

echo "===========================================\n";
echo "Unbind Operations Property Tests\n";
echo "===========================================\n";
echo "Property 16: Unbind Status Reset\n";
echo "Property 17: Unbind Audit Logging\n";
echo "Validates: Requirements 6.2, 6.3\n";
echo "===========================================\n\n";

require_once __DIR__ . '/UnbindOperationsTest.php';

try {
    $test = new UnbindOperationsTest();
    $result = $test->runAllTests();
    
    echo "\n===========================================\n";
    if ($result) {
        echo "RESULT: ALL TESTS PASSED\n";
    } else {
        echo "RESULT: SOME TESTS FAILED\n";
    }
    echo "===========================================\n";
    
    exit($result ? 0 : 1);
} catch (Exception $e) {
    echo "\nFATAL ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
