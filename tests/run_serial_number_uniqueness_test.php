<?php
/**
 * Run Serial Number Global Uniqueness Property Tests
 * **Feature: adv-crm-inventory-module, Property 2: Serial Number Global Uniqueness**
 * **Validates: Requirements 3.3**
 */

require_once __DIR__ . '/SerialNumberUniquenessTest.php';

echo "========================================\n";
echo "Serial Number Uniqueness Property Tests\n";
echo "========================================\n\n";

$test = new SerialNumberUniquenessTest();
$passed = $test->runTests();

echo "\n========================================\n";
if ($passed) {
    echo "All tests PASSED!\n";
    exit(0);
} else {
    echo "Some tests FAILED!\n";
    exit(1);
}
