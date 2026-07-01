<?php
/**
 * Test Runner for Event-Driven Email Queuing Property Test
 * **Feature: email-management-system, Property 11: Event-Driven Email Queuing**
 * **Validates: Requirements 4.3, 4.5, 5.2, 5.3, 5.4, 5.5**
 */

require_once __DIR__ . '/../config/autoload.php';
require_once 'EventDrivenEmailQueuingPropertyTest.php';

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Starting Event-Driven Email Queuing Property Tests...\n";
echo "========================================================\n\n";

try {
    $test = new EventDrivenEmailQueuingPropertyTest();
    $success = $test->runTests();
    
    if ($success) {
        echo "\n✅ All Event-Driven Email Queuing property tests PASSED!\n";
        exit(0);
    } else {
        echo "\n❌ Some Event-Driven Email Queuing property tests FAILED!\n";
        exit(1);
    }
} catch (Exception $e) {
    echo "\n💥 Test execution failed with error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}