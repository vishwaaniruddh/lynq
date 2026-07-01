<?php
/**
 * Runner for ADV-Only Access Control Property Test
 * **Feature: crm-master-modules, Property 3: ADV-Only Access Control**
 * **Validates: Requirements 1.5, 2.6, 7.2, 8.1, 8.2**
 */

require_once __DIR__ . '/AdvOnlyAccessControlTest.php';

echo "========================================\n";
echo "ADV-Only Access Control Property Test\n";
echo "========================================\n\n";

$test = new AdvOnlyAccessControlTest();

try {
    $passed = $test->runTests();
    
    echo "\n========================================\n";
    if ($passed) {
        echo "✓ All ADV-Only Access Control tests PASSED\n";
        $exitCode = 0;
    } else {
        echo "✗ Some ADV-Only Access Control tests FAILED\n";
        $exitCode = 1;
    }
    echo "========================================\n";
    
} catch (Exception $e) {
    echo "\n========================================\n";
    echo "✗ Test execution failed: " . $e->getMessage() . "\n";
    echo "========================================\n";
    $exitCode = 1;
} finally {
    // Clean up test data
    echo "\nCleaning up test data...\n";
    $test->cleanupTestData();
}

exit($exitCode);
