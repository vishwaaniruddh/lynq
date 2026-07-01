<?php
/**
 * Runner for ADV-Only Function Access Control Tests
 * **Feature: adv-crm-users-module, Property 5: ADV-Only Function Access Control**
 * **Validates: Requirements 2.4**
 */

require_once __DIR__ . '/AdvOnlyFunctionAccessControlTest.php';

echo "========================================\n";
echo "ADV-Only Function Access Control Tests\n";
echo "========================================\n\n";

$test = new AdvOnlyFunctionAccessControlTest();
$result = $test->runTests();

echo "\n========================================\n";
if ($result) {
    echo "All tests PASSED!\n";
} else {
    echo "Some tests FAILED!\n";
}
echo "========================================\n";

exit($result ? 0 : 1);
