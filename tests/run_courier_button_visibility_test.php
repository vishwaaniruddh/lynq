<?php
/**
 * Runner for Courier Permission-Based Button Visibility Property Tests
 * **Feature: crm-sidebar-restructure, Property 10: Permission-Based Courier Button Visibility**
 * **Validates: Requirements 5.2**
 */

require_once __DIR__ . '/CourierButtonVisibilityTest.php';

echo "========================================\n";
echo "Courier Button Visibility Tests\n";
echo "========================================\n\n";

$test = new CourierButtonVisibilityTest();

try {
    $passed = $test->runTests();
    
    echo "\n========================================\n";
    if ($passed) {
        echo "All Courier Button Visibility tests PASSED!\n";
        exit(0);
    } else {
        echo "Some Courier Button Visibility tests FAILED!\n";
        exit(1);
    }
} catch (Exception $e) {
    echo "\nTest execution error: " . $e->getMessage() . "\n";
    exit(1);
} finally {
    // Cleanup test data
    $test->cleanupTestData();
}
