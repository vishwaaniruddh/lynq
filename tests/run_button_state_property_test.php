<?php
/**
 * Run Button State Property Test
 * **Feature: feasibility-module, Property 1: Button state reflects feasibility status**
 * **Validates: Requirements 1.2, 1.3, 1.4**
 */

require_once __DIR__ . '/../config/autoload.php';
require_once __DIR__ . '/ButtonStatePropertyTest.php';

echo "Running Button State Property Test...\n";
echo "=====================================\n\n";

$test = new ButtonStatePropertyTest();
$passed = $test->runTests();

echo "\n=====================================\n";
if ($passed) {
    echo "All tests PASSED!\n";
    exit(0);
} else {
    echo "Some tests FAILED!\n";
    exit(1);
}
