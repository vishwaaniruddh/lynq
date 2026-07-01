<?php
/**
 * Test Runner for Audit Log Completeness Configuration Tests
 * 
 * **Feature: ip-configuration-management, Property 20: Audit Log Completeness**
 * **Validates: Requirements 9.1**
 */

require_once __DIR__ . '/AuditLogCompletenessConfigTest.php';

echo "===========================================\n";
echo "Running Audit Log Completeness Config Tests\n";
echo "===========================================\n\n";

$test = new AuditLogCompletenessConfigTest();
$result = $test->runAllTests();

echo "\n===========================================\n";
echo "Test Result: " . ($result ? "PASSED" : "FAILED") . "\n";
echo "===========================================\n";

exit($result ? 0 : 1);
