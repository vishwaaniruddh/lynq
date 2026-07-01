<?php
/**
 * Runner for Installation Image Upload Property Tests
 * **Feature: installation-module, Property 15: Image upload validation**
 * **Validates: Requirements 6.4, 13.4**
 */

require_once __DIR__ . '/InstallationImageUploadPropertyTest.php';

echo "========================================\n";
echo "Installation Image Upload Property Tests\n";
echo "========================================\n\n";

$test = new InstallationImageUploadPropertyTest();
$passed = $test->runTests();

echo "\n========================================\n";
if ($passed) {
    echo "All Installation Image Upload property tests PASSED\n";
    exit(0);
} else {
    echo "Some Installation Image Upload property tests FAILED\n";
    exit(1);
}
