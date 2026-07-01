<?php
/**
 * Runner for Lost Item Lock Property Test
 * **Feature: adv-crm-inventory-module, Property 9: Lost Item Lock**
 * **Validates: Requirements 6.3**
 */

require_once __DIR__ . '/LostItemLockTest.php';

echo "Starting Lost Item Lock Property Test...\n";
echo "========================================\n\n";

$test = new LostItemLockTest();
$success = $test->runTests();

echo "\n========================================\n";
if ($success) {
    echo "All tests PASSED!\n";
} else {
    echo "Some tests FAILED!\n";
}

exit($success ? 0 : 1);
