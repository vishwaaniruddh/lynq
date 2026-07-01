<?php
/**
 * Run Audit and Alert Service Unit Tests
 * 
 * Requirements: 12.1, 13.1, 13.4
 */

require_once __DIR__ . '/AuditAlertServiceTest.php';

echo "Starting Audit and Alert Service Unit Tests...\n\n";

$test = new AuditAlertServiceTest();
$result = $test->runTests();

echo "\n";
if ($result) {
    echo "=== ALL TESTS PASSED ===\n";
    exit(0);
} else {
    echo "=== SOME TESTS FAILED ===\n";
    exit(1);
}
