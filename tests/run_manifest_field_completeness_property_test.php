<?php
/**
 * Runner for Manifest Field Completeness Property Test
 */

require_once __DIR__ . '/ManifestFieldCompletenessPropertyTest.php';

echo "Starting Manifest Field Completeness Property Test...\n\n";

$test = new ManifestFieldCompletenessPropertyTest();
$result = $test->runTests();

if ($result) {
    echo "\n✓ All Manifest Field Completeness property tests passed!\n";
    exit(0);
} else {
    echo "\n✗ Some Manifest Field Completeness property tests failed!\n";
    exit(1);
}