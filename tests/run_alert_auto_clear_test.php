<?php
/**
 * Run Alert Auto-Clear on Replenishment Property Test
 * 
 * **Feature: adv-crm-inventory-module, Property 13: Alert Auto-Clear on Replenishment**
 * **Validates: Requirements 13.4**
 */

require_once __DIR__ . '/AlertAutoClearReplenishmentTest.php';

echo "Starting Alert Auto-Clear on Replenishment Property Test...\n\n";

$test = new AlertAutoClearReplenishmentTest();
$result = $test->runTests();

echo "\n";
if ($result) {
    echo "=== ALL TESTS PASSED ===\n";
    exit(0);
} else {
    echo "=== SOME TESTS FAILED ===\n";
    exit(1);
}
