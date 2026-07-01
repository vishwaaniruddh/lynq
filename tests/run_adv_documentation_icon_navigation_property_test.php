<?php
/**
 * Runner for ADV Documentation Icon Navigation Property Test
 * **Feature: adv-user-documentation, Property 2: Icon Navigation Target**
 * **Validates: Requirements 1.2**
 */

require_once __DIR__ . '/../config/autoload.php';
require_once __DIR__ . '/AdvDocumentationIconNavigationPropertyTest.php';

echo "Starting ADV Documentation Icon Navigation Property Tests...\n\n";

$test = new AdvDocumentationIconNavigationPropertyTest();

try {
    $passed = $test->runTests();
    
    echo "\n" . str_repeat("=", 60) . "\n";
    if ($passed) {
        echo "ALL TESTS PASSED\n";
        exit(0);
    } else {
        echo "SOME TESTS FAILED\n";
        exit(1);
    }
} catch (Exception $e) {
    echo "Test execution error: " . $e->getMessage() . "\n";
    exit(1);
} finally {
    $test->cleanupTestData();
}
