<?php
/**
 * Test Runner for Offline State Indication Property Test
 * **Feature: clarity-pwa-conversion, Property 5: Offline State Indication**
 * **Validates: Requirements 1.5**
 */

require_once __DIR__ . '/OfflineStateIndicationPropertyTest.php';

echo "Running Offline State Indication Property Tests...\n";
echo "=================================================\n\n";

$test = new OfflineStateIndicationPropertyTest();
$result = $test->runTests();

if ($result) {
    echo "\n✅ All Offline State Indication Property Tests PASSED\n";
    exit(0);
} else {
    echo "\n❌ Some Offline State Indication Property Tests FAILED\n";
    exit(1);
}