<?php
/**
 * Test Runner for Permission-Based Button Visibility Property Test
 * **Feature: crm-master-modules, Property 11: Permission-Based Button Visibility**
 * **Validates: Requirements 8.3**
 */

require_once __DIR__ . '/PermissionBasedButtonVisibilityTest.php';

echo "========================================\n";
echo "Permission-Based Button Visibility Test\n";
echo "========================================\n\n";

$test = new PermissionBasedButtonVisibilityTest();

try {
    $passed = $test->runTests();
    
    echo "\n========================================\n";
    if ($passed) {
        echo "✓ All property tests PASSED\n";
        exit(0);
    } else {
        echo "✗ Some property tests FAILED\n";
        exit(1);
    }
} catch (Exception $e) {
    echo "\n✗ Test execution failed: " . $e->getMessage() . "\n";
    exit(1);
} finally {
    // Cleanup test data
    $test->cleanupTestData();
}
