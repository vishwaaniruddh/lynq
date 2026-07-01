<?php
/**
 * Runner for Lock Duration Constraint Property Test
 * 
 * **Feature: ip-configuration-management, Property 9: Lock Duration Constraint**
 * **Validates: Requirements 4.1**
 */

require_once __DIR__ . '/LockDurationConstraintTest.php';

echo "===========================================\n";
echo "Lock Duration Constraint Property Test\n";
echo "===========================================\n";
echo "Property 9: For any IP lock created, the lock duration\n";
echo "SHALL be exactly 20 minutes from creation time.\n";
echo "Validates: Requirements 4.1\n";
echo "===========================================\n\n";

$test = new LockDurationConstraintTest();
$results = $test->runAllTests();

echo "\n===========================================\n";
if (in_array(false, $results, true)) {
    echo "RESULT: FAILED\n";
    exit(1);
} else {
    echo "RESULT: PASSED\n";
    exit(0);
}
