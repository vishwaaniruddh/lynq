<?php
/**
 * Runner for IP_Master Display Completeness Property Test
 * 
 * **Feature: ip-configuration-management, Property 3: IP_Master Display Completeness**
 * **Validates: Requirements 1.3, 3.3**
 */

require_once __DIR__ . '/IPMasterDisplayCompletenessTest.php';

echo "===========================================\n";
echo "IP_Master Display Completeness Property Test\n";
echo "===========================================\n";
echo "Property 3: For any IP_Master record returned by the system,\n";
echo "the response SHALL include all four IP addresses and the current status.\n";
echo "===========================================\n\n";

$test = new IPMasterDisplayCompletenessTest();
$results = $test->runAllTests();

$allPassed = !in_array(false, $results, true);

echo "\n===========================================\n";
if ($allPassed) {
    echo "RESULT: ALL TESTS PASSED\n";
} else {
    echo "RESULT: SOME TESTS FAILED\n";
}
echo "===========================================\n";

exit($allPassed ? 0 : 1);
