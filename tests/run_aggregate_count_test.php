<?php
/**
 * Run Aggregate Count Accuracy Property Test
 * 
 * **Feature: crm-master-modules, Property 10: Aggregate Count Accuracy**
 * **Validates: Requirements 3.1, 4.1, 5.1, 5.4, 6.1**
 */

require_once __DIR__ . '/AggregateCountAccuracyTest.php';

echo "===========================================\n";
echo "Aggregate Count Accuracy Property Test\n";
echo "===========================================\n\n";

$test = new AggregateCountAccuracyTest();
$success = $test->runAllTests();

echo "\n===========================================\n";
if ($success) {
    echo "ALL TESTS PASSED\n";
} else {
    echo "SOME TESTS FAILED\n";
}
echo "===========================================\n";

exit($success ? 0 : 1);
