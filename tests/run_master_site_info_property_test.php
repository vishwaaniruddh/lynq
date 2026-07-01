<?php
/**
 * Run Master Site Information Property Test
 * **Feature: feasibility-module, Property 6: Feasibility form displays master site information**
 * **Validates: Requirements 4.2**
 */

require_once __DIR__ . '/../config/autoload.php';
require_once __DIR__ . '/MasterSiteInfoPropertyTest.php';

echo "Running Master Site Information Property Test...\n";
echo "================================================\n\n";

$test = new MasterSiteInfoPropertyTest();
$passed = $test->runTests();

echo "\n================================================\n";
if ($passed) {
    echo "All tests PASSED!\n";
    exit(0);
} else {
    echo "Some tests FAILED!\n";
    exit(1);
}
