<?php
/**
 * Runner for Site Model Validation Property Tests
 * **Feature: site-management-delegation, Property 14: Coordinate validation**
 * **Feature: site-management-delegation, Property 15: Required field validation**
 * **Validates: Requirements 7.1, 7.2, 7.3**
 */

require_once __DIR__ . '/../config/autoload.php';
require_once __DIR__ . '/SiteModelValidationTest.php';

echo "========================================\n";
echo "Site Model Validation Property Tests\n";
echo "========================================\n\n";

$test = new SiteModelValidationTest();
$passed = $test->runTests();

echo "\n========================================\n";
if ($passed) {
    echo "All tests PASSED!\n";
    exit(0);
} else {
    echo "Some tests FAILED!\n";
    exit(1);
}
