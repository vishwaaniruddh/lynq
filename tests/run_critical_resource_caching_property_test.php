<?php
/**
 * Test runner for Critical Resource Caching Property Test
 */

require_once 'CriticalResourceCachingPropertyTest.php';

try {
    $test = new CriticalResourceCachingPropertyTest();
    $result = $test->runTests();
    
    if ($result) {
        echo "\n✅ All Critical Resource Caching Property Tests PASSED\n";
        exit(0);
    } else {
        echo "\n❌ Some Critical Resource Caching Property Tests FAILED\n";
        exit(1);
    }
} catch (Exception $e) {
    echo "\n💥 Test execution failed: " . $e->getMessage() . "\n";
    exit(1);
}