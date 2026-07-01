<?php
/**
 * Runner for Status Transition Validity Property Test
 * **Feature: adv-crm-inventory-module, Property 6: Status Transition Validity**
 * **Validates: Requirements 6.1**
 */

require_once __DIR__ . '/StatusTransitionValidityTest.php';

echo "Starting Status Transition Validity Property Test...\n";
echo "================================================\n\n";

$test = new StatusTransitionValidityTest();
$success = $test->runTests();

echo "\n================================================\n";
if ($success) {
    echo "All tests PASSED!\n";
} else {
    echo "Some tests FAILED!\n";
}

exit($success ? 0 : 1);
