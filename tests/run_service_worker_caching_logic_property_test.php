<?php
/**
 * Test runner for Service Worker Caching Logic Property Test
 */

require_once 'ServiceWorkerCachingLogicPropertyTest.php';

try {
    $test = new ServiceWorkerCachingLogicPropertyTest();
    $result = $test->runTests();
    
    if ($result) {
        echo "\n✅ All Service Worker Caching Logic Property Tests PASSED\n";
        exit(0);
    } else {
        echo "\n❌ Some Service Worker Caching Logic Property Tests FAILED\n";
        exit(1);
    }
} catch (Exception $e) {
    echo "\n💥 Test execution failed: " . $e->getMessage() . "\n";
    exit(1);
}