<?php
/**
 * Test Runner for Service Worker Update Handling Property Test
 * **Feature: clarity-pwa-conversion, Property 21: Service Worker Update Handling**
 * **Validates: Requirements 6.5**
 */

require_once __DIR__ . '/ServiceWorkerUpdateHandlingPropertyTest.php';

echo "Running Service Worker Update Handling Property Tests...\n";
echo "======================================================\n\n";

$test = new ServiceWorkerUpdateHandlingPropertyTest();
$result = $test->runTests();

if ($result) {
    echo "\n✅ All Service Worker Update Handling Property Tests PASSED\n";
    exit(0);
} else {
    echo "\n❌ Some Service Worker Update Handling Property Tests FAILED\n";
    exit(1);
}