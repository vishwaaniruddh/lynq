<?php
/**
 * Runner for Installation Delegation Property Tests
 * 
 * **Feature: installation-module, Property 2: Installation delegation creates record with correct initial status**
 * **Validates: Requirements 1.4**
 */

require_once __DIR__ . '/InstallationDelegationPropertyTest.php';

echo "===========================================\n";
echo "Installation Delegation Property Test Runner\n";
echo "===========================================\n\n";

$test = new InstallationDelegationPropertyTest();
$success = $test->runTests();

echo "\n===========================================\n";
if ($success) {
    echo "All tests PASSED!\n";
} else {
    echo "Some tests FAILED!\n";
}
echo "===========================================\n";

exit($success ? 0 : 1);
