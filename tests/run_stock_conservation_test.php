<?php
/**
 * Run Stock Conservation During Transfer Property Test
 * 
 * **Feature: adv-crm-inventory-module, Property 3: Stock Conservation During Transfer**
 * **Validates: Requirements 5.4**
 */

require_once __DIR__ . '/StockConservationTransferTest.php';

echo "Starting Stock Conservation During Transfer Property Test...\n\n";

$test = new StockConservationTransferTest();
$result = $test->runTests();

echo "\n";
if ($result) {
    echo "=== ALL TESTS PASSED ===\n";
    exit(0);
} else {
    echo "=== SOME TESTS FAILED ===\n";
    exit(1);
}
