<?php
/**
 * Test runner for Recipient Validation Property Test
 */

require_once 'RecipientValidationPropertyTest.php';

try {
    $test = new RecipientValidationPropertyTest();
    $result = $test->runTests();
    
    if ($result) {
        echo "\n✓ All recipient validation property tests passed!\n";
        exit(0);
    } else {
        echo "\n✗ Some recipient validation property tests failed!\n";
        exit(1);
    }
} catch (Exception $e) {
    echo "\n✗ Test execution failed: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}