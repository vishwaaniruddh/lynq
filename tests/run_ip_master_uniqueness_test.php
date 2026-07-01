<?php
/**
 * Runner for IP_Master Uniqueness Property Test
 * 
 * **Feature: ip-configuration-management, Property 2: IP_Master Uniqueness**
 * **Validates: Requirements 1.2**
 */

require_once __DIR__ . '/IPMasterUniquenessTest.php';

echo "========================================\n";
echo "IP_Master Uniqueness Property Test\n";
echo "========================================\n";
echo "Property 2: IP_Master Uniqueness\n";
echo "Validates: Requirements 1.2\n";
echo "========================================\n\n";

$test = new IPMasterUniquenessTest();
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
