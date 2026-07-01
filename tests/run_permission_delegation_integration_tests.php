<?php
/**
 * Runner for Permission Delegation Integration Tests
 * 
 * Usage: php run_permission_delegation_integration_tests.php
 */

require_once __DIR__ . '/../config/autoload.php';
require_once __DIR__ . '/PermissionDelegationIntegrationTest.php';

echo "========================================\n";
echo "Permission Delegation Integration Tests\n";
echo "========================================\n\n";

$test = new PermissionDelegationIntegrationTest();
$result = $test->runTests();

echo "\n========================================\n";
if ($result) {
    echo "All tests PASSED!\n";
} else {
    echo "Some tests FAILED!\n";
}
echo "========================================\n";

exit($result ? 0 : 1);
