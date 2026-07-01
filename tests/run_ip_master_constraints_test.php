<?php
/**
 * Runner for IP_Master Constraints Property Tests
 * 
 * **Feature: ip-configuration-management, Property 4: Configured IP Edit Prevention**
 * **Feature: ip-configuration-management, Property 5: IP Deletion Constraint**
 * **Validates: Requirements 1.4, 1.5**
 */

require_once __DIR__ . '/IPMasterConstraintsTest.php';

echo "========================================\n";
echo "IP_Master Constraints Property Tests\n";
echo "========================================\n";
echo "Property 4: Configured IP Edit Prevention\n";
echo "Property 5: IP Deletion Constraint\n";
echo "Validates: Requirements 1.4, 1.5\n";
echo "========================================\n\n";

$test = new IPMasterConstraintsTest();
$results = $test->runAllTests();

$allPassed = !in_array(false, $results, true);

echo "\n========================================\n";
if ($allPassed) {
    echo "RESULT: ALL TESTS PASSED\n";
} else {
    echo "RESULT: SOME TESTS FAILED\n";
}
echo "========================================\n";

exit($allPassed ? 0 : 1);
