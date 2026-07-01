<?php
/**
 * Runner for User Management UI Integration Tests
 * **Feature: adv-crm-users-module**
 * **Validates: Requirements 1.1, 1.2, 1.3, 1.4, 2.1, 2.2**
 */

require_once __DIR__ . '/UserManagementUIIntegrationTest.php';

echo "========================================\n";
echo "User Management UI Integration Tests\n";
echo "========================================\n\n";

$test = new UserManagementUIIntegrationTest();
$result = $test->runTests();

echo "\n========================================\n";
if ($result) {
    echo "All tests PASSED!\n";
} else {
    echo "Some tests FAILED!\n";
}
echo "========================================\n";

exit($result ? 0 : 1);
