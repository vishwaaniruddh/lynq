<?php
/**
 * Runner for Site Serialization Property Tests
 * **Feature: site-management-delegation, Property 16: Site serialization round-trip**
 * **Validates: Requirements 7.4, 7.5**
 */

require_once __DIR__ . '/SiteSerializationPropertyTest.php';

echo "Starting Site Serialization Property Tests...\n";
echo "============================================\n\n";

$test = new SiteSerializationPropertyTest();
$result = $test->runTests();

echo "\n============================================\n";
if ($result) {
    echo "All property tests PASSED!\n";
    exit(0);
} else {
    echo "Some property tests FAILED!\n";
    exit(1);
}
