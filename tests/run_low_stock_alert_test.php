<?php
/**
 * Run Low Stock Alert Generation Property Test
 * 
 * **Feature: adv-crm-inventory-module, Property 12: Low Stock Alert Generation**
 * **Validates: Requirements 13.1**
 */

require_once __DIR__ . '/LowStockAlertGenerationTest.php';

echo "Starting Low Stock Alert Generation Property Test...\n\n";

$test = new LowStockAlertGenerationTest();
$result = $test->runTests();

echo "\n";
if ($result) {
    echo "=== ALL TESTS PASSED ===\n";
    exit(0);
} else {
    echo "=== SOME TESTS FAILED ===\n";
    exit(1);
}
