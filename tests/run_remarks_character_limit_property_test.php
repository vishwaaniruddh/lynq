<?php
/**
 * Run Remarks Character Limit Property Test
 * **Feature: feasibility-module, Property 12: Remarks character limit**
 * **Validates: Requirements 7.2**
 */

require_once __DIR__ . '/../config/autoload.php';
require_once __DIR__ . '/RemarksCharacterLimitPropertyTest.php';

echo "Running Remarks Character Limit Property Test...\n";
echo "================================================\n\n";

$test = new RemarksCharacterLimitPropertyTest();
$passed = $test->runTests();

echo "\n================================================\n";
if ($passed) {
    echo "All tests PASSED!\n";
    exit(0);
} else {
    echo "Some tests FAILED!\n";
    exit(1);
}
