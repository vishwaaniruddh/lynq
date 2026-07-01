<?php
/**
 * Run Push Notification Property Tests
 * Executes all property-based tests for push notification functionality
 */

require_once __DIR__ . '/PushNotificationSendingPropertyTest.php';
require_once __DIR__ . '/PushMessageDisplayPropertyTest.php';
require_once __DIR__ . '/NotificationClickNavigationPropertyTest.php';

echo "Running Push Notification Property Tests...\n";
echo "==========================================\n\n";

$tests = [
    'PushNotificationSendingPropertyTest',
    'PushMessageDisplayPropertyTest', 
    'NotificationClickNavigationPropertyTest'
];

$totalTests = 0;
$passedTests = 0;
$failedTests = 0;

foreach ($tests as $testClass) {
    echo "Running $testClass...\n";
    
    try {
        $test = new $testClass();
        $methods = get_class_methods($test);
        
        foreach ($methods as $method) {
            if (strpos($method, 'test') === 0 && $method !== 'testPropertyBase') {
                echo "  - $method: ";
                $totalTests++;
                
                try {
                    if (method_exists($test, 'setUp')) {
                        $test->setUp();
                    }
                    $test->$method();
                    if (method_exists($test, 'tearDown')) {
                        $test->tearDown();
                    }
                    echo "PASS\n";
                    $passedTests++;
                } catch (Exception $e) {
                    echo "FAIL - " . $e->getMessage() . "\n";
                    $failedTests++;
                } catch (AssertionError $e) {
                    echo "FAIL - " . $e->getMessage() . "\n";
                    $failedTests++;
                }
            }
        }
        
    } catch (Exception $e) {
        echo "ERROR: Failed to run $testClass - " . $e->getMessage() . "\n";
        $failedTests++;
    }
    
    echo "\n";
}

echo "==========================================\n";
echo "Test Results:\n";
echo "Total Tests: $totalTests\n";
echo "Passed: $passedTests\n";
echo "Failed: $failedTests\n";

if ($failedTests > 0) {
    echo "\nSome tests failed. Please review the output above.\n";
    exit(1);
} else {
    echo "\nAll push notification property tests passed!\n";
    exit(0);
}