<?php
/**
 * Runner for Manifest Property Completeness Property Test
 */

require_once __DIR__ . '/ManifestPropertyCompletenessPropertyTest.php';

echo "Starting Manifest Property Completeness Property Test...\n\n";

$test = new ManifestPropertyCompletenessPropertyTest();
$result = $test->runTests();

if ($result) {
    echo "\n✓ All Manifest Property Completeness property tests passed!\n";
    exit(0);
} else {
    echo "\n✗ Some Manifest Property Completeness property tests failed!\n";
    exit(1);
}