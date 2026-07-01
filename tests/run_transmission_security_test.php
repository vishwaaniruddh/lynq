<?php
/**
 * Test runner for Transmission Security Property Test
 */

require_once 'TransmissionSecurityPropertyTest.php';

try {
    $test = new TransmissionSecurityPropertyTest();
    $result = $test->runTests();
    
    if ($result) {
        echo "\n✓ All transmission security property tests passed!\n";
        exit(0);
    } else {
        echo "\n✗ Some transmission security property tests failed!\n";
        exit(1);
    }
} catch (Exception $e) {
    echo "\n✗ Test execution failed: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}