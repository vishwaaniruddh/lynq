<?php
/**
 * Runner for Screenshot Form Factor Coverage Property Test
 */

require_once __DIR__ . '/ScreenshotFormFactorCoveragePropertyTest.php';

echo "Starting Screenshot Form Factor Coverage Property Test...\n\n";

$test = new ScreenshotFormFactorCoveragePropertyTest();
$result = $test->runTests();

if ($result) {
    echo "\n✓ All Screenshot Form Factor Coverage property tests passed!\n";
    exit(0);
} else {
    echo "\n✗ Some Screenshot Form Factor Coverage property tests failed!\n";
    exit(1);
}