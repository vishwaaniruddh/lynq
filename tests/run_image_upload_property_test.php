<?php
/**
 * Runner for Image Upload Property Tests
 * **Feature: feasibility-module, Property 10**
 * **Validates: Requirements 6.2**
 */

require_once __DIR__ . '/ImageUploadPropertyTest.php';

echo "========================================\n";
echo "Image Upload Property Tests\n";
echo "========================================\n\n";

$test = new ImageUploadPropertyTest();
$passed = $test->runTests();

echo "\n========================================\n";
if ($passed) {
    echo "All Image Upload property tests PASSED\n";
    exit(0);
} else {
    echo "Some Image Upload property tests FAILED\n";
    exit(1);
}
