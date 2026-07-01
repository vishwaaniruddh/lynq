<?php
/**
 * Test runner for ServiceWorkerTest
 */

// Include autoloader and dependencies
require_once __DIR__ . '/../config/autoload.php';

echo "Running Service Worker Unit Tests...\n";
echo "===================================\n\n";

// Check if required files exist
$requiredFiles = [
    __DIR__ . '/PropertyTestBase.php',
    __DIR__ . '/PWATestBase.php',
    __DIR__ . '/ServiceWorkerTest.php'
];

foreach ($requiredFiles as $file) {
    if (!file_exists($file)) {
        echo "Error: Required file not found: $file\n";
        exit(1);
    }
}

// Include test files
require_once __DIR__ . '/PropertyTestBase.php';
require_once __DIR__ . '/PWATestBase.php';
require_once __DIR__ . '/ServiceWorkerTest.php';

// Check if class exists
if (!class_exists('ServiceWorkerTest')) {
    echo "Error: ServiceWorkerTest class not found\n";
    exit(1);
}

try {
    $test = new ServiceWorkerTest();
    
    // Check if setUp method exists
    if (method_exists($test, 'setUp')) {
        $test->setUp();
    }
    
    $testMethods = [
        'testCacheManagement',
        'testCacheVersioningAndCleanup',
        'testAppShellCaching',
        'testApiResponseCaching',
        'testCacheTTLExpiration',
        'testOfflineActionQueuing',
        'testOfflineActionProcessing',
        'testOfflineActionRetry',
        'testPushNotificationHandling',
        'testNotificationClickHandling',
        'testBackgroundSync',
        'testServiceWorkerMessageHandling',
        'testCacheStrategySelection',
        'testOfflineFallbackHandling',
        'testServiceWorkerInstallation',
        'testServiceWorkerActivation',
        'testFetchEventHandling',
        'testNetworkStatusDetection',
        'testCacheSizeManagement'
    ];
    
    $passed = 0;
    $failed = 0;
    $failures = [];
    
    foreach ($testMethods as $method) {
        if (!method_exists($test, $method)) {
            echo "Warning: Method $method does not exist, skipping...\n";
            continue;
        }
        
        echo "Running $method... ";
        
        try {
            $test->$method();
            echo "✓ PASSED\n";
            $passed++;
        } catch (Exception $e) {
            echo "✗ FAILED\n";
            $failed++;
            $failures[] = [
                'method' => $method,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ];
        } catch (Error $e) {
            echo "✗ ERROR\n";
            $failed++;
            $failures[] = [
                'method' => $method,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ];
        }
    }
    
    // Check if tearDown method exists
    if (method_exists($test, 'tearDown')) {
        $test->tearDown();
    }
    
    echo "\n===================================\n";
    echo "Test Results:\n";
    echo "Passed: $passed\n";
    echo "Failed: $failed\n";
    echo "Total:  " . ($passed + $failed) . "\n";
    
    if (!empty($failures)) {
        echo "\nFailure Details:\n";
        foreach ($failures as $failure) {
            echo "\n{$failure['method']}:\n";
            echo "  Error: {$failure['error']}\n";
        }
    }
    
    if ($failed === 0) {
        echo "\n🎉 All tests passed!\n";
        exit(0);
    } else {
        echo "\n❌ Some tests failed.\n";
        exit(1);
    }
    
} catch (Exception $e) {
    echo "Test setup failed: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
    exit(1);
} catch (Error $e) {
    echo "Fatal error: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
    exit(1);
}
?>