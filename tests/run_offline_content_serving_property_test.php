<?php
/**
 * Test runner for Offline Content Serving Property Test
 */

require_once 'OfflineContentServingPropertyTest.php';

try {
    $test = new OfflineContentServingPropertyTest();
    $result = $test->runTests();
    
    if ($result) {
        echo "\n✅ All Offline Content Serving Property Tests PASSED\n";
        exit(0);
    } else {
        echo "\n❌ Some Offline Content Serving Property Tests FAILED\n";
        exit(1);
    }
} catch (Exception $e) {
    echo "\n💥 Test execution failed: " . $e->getMessage() . "\n";
    exit(1);
}