<?php
/**
 * Runner for Site Creation Property Tests
 * **Feature: site-management-delegation, Property 1: Site creation preserves all input data**
 * **Feature: site-management-delegation, Property 2: Site uniqueness within LHO**
 * **Validates: Requirements 1.1, 1.4, 1.5**
 */

require_once __DIR__ . '/SiteCreationPropertyTest.php';

echo "Starting Site Creation Property Tests...\n\n";

$test = new SiteCreationPropertyTest();
$passed = $test->runTests();

echo "\n";
if ($passed) {
    echo "=== ALL SITE CREATION PROPERTY TESTS PASSED ===\n";
    exit(0);
} else {
    echo "=== SOME SITE CREATION PROPERTY TESTS FAILED ===\n";
    exit(1);
}
