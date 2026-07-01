<?php
/**
 * Security Feature Test Runner
 * Runs all security-related tests
 */

require_once __DIR__ . '/SecurityFeatureTest.php';

echo "========================================\n";
echo "Running Security Feature Tests\n";
echo "========================================\n\n";

$test = new SecurityFeatureTest();
$success = $test->runAllTests();

echo "\n========================================\n";
if ($success) {
    echo "All security tests PASSED!\n";
} else {
    echo "Some security tests FAILED!\n";
}
echo "========================================\n";

// Clean up test data
$test->cleanupTestData();

exit($success ? 0 : 1);
