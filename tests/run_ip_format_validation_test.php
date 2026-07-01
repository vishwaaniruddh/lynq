<?php
/**
 * Runner for IP Format Validation Property Test
 * 
 * **Feature: ip-configuration-management, Property 1: IP Format Validation**
 * **Validates: Requirements 1.1**
 */

require_once __DIR__ . '/IPFormatValidationTest.php';

echo "========================================\n";
echo "IP Format Validation Property Test\n";
echo "========================================\n";
echo "Property 1: IP Format Validation\n";
echo "Validates: Requirements 1.1\n";
echo "========================================\n\n";

$test = new IPFormatValidationTest();
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
