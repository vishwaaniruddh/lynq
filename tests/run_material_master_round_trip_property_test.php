<?php
/**
 * Runner for Material Master Round-Trip Property Test
 * **Feature: material-request-module, Property 1: Material Master Round-Trip Persistence**
 * **Validates: Requirements 1.4, 9.2**
 */

require_once __DIR__ . '/MaterialMasterRoundTripPropertyTest.php';

echo "Starting Material Master Round-Trip Property Tests...\n\n";

$test = new MaterialMasterRoundTripPropertyTest();
$passed = $test->runTests();

echo "\n";
if ($passed) {
    echo "All Material Master Round-Trip property tests PASSED!\n";
    exit(0);
} else {
    echo "Some Material Master Round-Trip property tests FAILED!\n";
    exit(1);
}
