<?php
/**
 * Runner for Courier ADV-Only Access Control Property Tests
 * **Feature: crm-sidebar-restructure, Property 6: Courier ADV-Only Access Control**
 * **Validates: Requirements 2.5, 5.1, 5.4**
 */

require_once __DIR__ . '/CourierAdvOnlyAccessTest.php';

echo "========================================\n";
echo "Courier ADV-Only Access Control Tests\n";
echo "========================================\n\n";

$test = new CourierAdvOnlyAccessTest();

try {
    $passed = $test->runTests();
    
    echo "\n========================================\n";
    if ($passed) {
        echo "All Courier ADV-Only Access tests PASSED!\n";
        exit(0);
    } else {
        echo "Some Courier ADV-Only Access tests FAILED!\n";
        exit(1);
    }
} catch (Exception $e) {
    echo "\nTest execution error: " . $e->getMessage() . "\n";
    exit(1);
} finally {
    // Cleanup test data
    $test->cleanupTestData();
}
