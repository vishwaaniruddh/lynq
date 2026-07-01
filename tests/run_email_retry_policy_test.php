<?php
/**
 * Test runner for Email Retry Policy Property Test
 */

require_once 'EmailRetryPolicyPropertyTest.php';

try {
    $test = new EmailRetryPolicyPropertyTest();
    $result = $test->runTests();
    
    if ($result) {
        echo "\n✓ All email retry policy property tests passed!\n";
        exit(0);
    } else {
        echo "\n✗ Some email retry policy property tests failed!\n";
        exit(1);
    }
} catch (Exception $e) {
    echo "\n✗ Test execution failed: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}