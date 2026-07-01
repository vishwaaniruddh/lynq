<?php
/**
 * Run Filter Result Consistency Property Test
 * 
 * **Feature: crm-master-modules, Property 7: Filter Result Consistency**
 * **Validates: Requirements 3.5, 4.5, 6.4, 10.2**
 */

require_once __DIR__ . '/FilterResultConsistencyTest.php';

echo "===========================================\n";
echo "Filter Result Consistency Property Test\n";
echo "===========================================\n\n";

$test = new FilterResultConsistencyTest();
$success = $test->runAllTests();

echo "\n===========================================\n";
if ($success) {
    echo "ALL TESTS PASSED\n";
} else {
    echo "SOME TESTS FAILED\n";
}
echo "===========================================\n";

exit($success ? 0 : 1);
