<?php
/**
 * Test Runner for Configuration Workflow Property Tests
 * 
 * **Feature: ip-configuration-management, Property 7: Automatic IP Assignment Validity**
 * **Feature: ip-configuration-management, Property 11: Configuration Completion Binding**
 * **Feature: ip-configuration-management, Property 12: Cancel Lock Release**
 * **Validates: Requirements 3.1, 4.4, 4.5, 5.1**
 */

require_once __DIR__ . '/ConfigurationWorkflowTest.php';

echo "===========================================\n";
echo "Configuration Workflow Property Tests\n";
echo "===========================================\n";

$test = new ConfigurationWorkflowTest();
$results = $test->runAllTests();

echo "\n===========================================\n";
if (in_array(false, $results, true)) {
    echo "FAILED: Some tests did not pass\n";
    exit(1);
} else {
    echo "SUCCESS: All tests passed\n";
    exit(0);
}
