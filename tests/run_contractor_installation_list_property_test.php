<?php
/**
 * Runner for Contractor Installation List Property Test
 * 
 * **Feature: installation-module, Property 3: Contractor installation list displays delegated sites**
 * **Validates: Requirements 2.1, 2.2**
 */

require_once __DIR__ . '/ContractorInstallationListPropertyTest.php';

echo "Starting Contractor Installation List Property Tests...\n";
echo "========================================\n";

$test = new ContractorInstallationListPropertyTest();
$success = $test->runTests();

echo "\n========================================\n";
if ($success) {
    echo "All tests passed!\n";
    exit(0);
} else {
    echo "Some tests failed!\n";
    exit(1);
}
