<?php
/**
 * Runner for LHO Manager Export Inclusion Property Test
 * **Feature: lho-manager-assignment, Property 4: Export Manager Inclusion**
 * **Validates: Requirements 2.4**
 */

require_once __DIR__ . '/LhoManagerExportInclusionPropertyTest.php';

echo "Running LHO Manager Export Inclusion Property Tests...\n";
echo str_repeat("=", 60) . "\n\n";

$test = new LhoManagerExportInclusionPropertyTest();
$result = $test->runTests();

echo "\n" . str_repeat("=", 60) . "\n";
echo $result ? "ALL TESTS PASSED\n" : "SOME TESTS FAILED\n";
echo str_repeat("=", 60) . "\n";

exit($result ? 0 : 1);
