<?php
/**
 * Test Runner for Bulk Site Import Property Tests
 * **Feature: site-management-delegation, Property 3: Bulk import processes all valid rows**
 * **Validates: Requirements 1.2, 1.3**
 */

require_once __DIR__ . '/BulkSiteImportPropertyTest.php';

echo "Starting Bulk Site Import Property Tests...\n";
echo str_repeat("=", 60) . "\n\n";

$test = new BulkSiteImportPropertyTest();
$passed = $test->runTests();

echo "\n" . str_repeat("=", 60) . "\n";
echo $passed ? "ALL TESTS PASSED\n" : "SOME TESTS FAILED\n";
echo str_repeat("=", 60) . "\n";

exit($passed ? 0 : 1);
