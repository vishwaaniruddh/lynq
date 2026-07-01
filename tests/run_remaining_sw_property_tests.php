<?php
/**
 * Test runner for remaining Service Worker Property Tests
 */

require_once 'InitialResourceCachingPropertyTest.php';
require_once 'CachedResourceServingPropertyTest.php';
require_once 'CachingStrategyImplementationPropertyTest.php';
require_once 'AppShellCacheServingPropertyTest.php';
require_once 'CacheVersionManagementPropertyTest.php';

$tests = [
    'Initial Resource Caching' => new InitialResourceCachingPropertyTest(),
    'Cached Resource Serving' => new CachedResourceServingPropertyTest(),
    'Caching Strategy Implementation' => new CachingStrategyImplementationPropertyTest(),
    'App Shell Cache Serving' => new AppShellCacheServingPropertyTest(),
    'Cache Version Management' => new CacheVersionManagementPropertyTest()
];

$allPassed = true;

foreach ($tests as $testName => $test) {
    echo "\n=== Running $testName Tests ===\n";
    
    try {
        $result = $test->runTests();
        
        if ($result) {
            echo "\n✅ $testName Property Tests PASSED\n";
        } else {
            echo "\n❌ $testName Property Tests FAILED\n";
            $allPassed = false;
        }
    } catch (Exception $e) {
        echo "\n💥 $testName Test execution failed: " . $e->getMessage() . "\n";
        $allPassed = false;
    }
}

if ($allPassed) {
    echo "\n🎉 ALL SERVICE WORKER PROPERTY TESTS PASSED!\n";
    exit(0);
} else {
    echo "\n❌ SOME SERVICE WORKER PROPERTY TESTS FAILED\n";
    exit(1);
}