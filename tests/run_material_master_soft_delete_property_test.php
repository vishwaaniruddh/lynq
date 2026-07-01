<?php
/**
 * Runner for Material Master Soft-Delete Property Test
 * **Feature: material-request-module, Property 3: Material Master Soft-Delete Exclusion**
 * **Validates: Requirements 1.6, 9.4**
 */

require_once __DIR__ . '/MaterialMasterSoftDeletePropertyTest.php';

echo "Starting Material Master Soft-Delete Property Tests...\n\n";

$test = new MaterialMasterSoftDeletePropertyTest();
$passed = $test->runTests();

echo "\n";
if ($passed) {
    echo "All Material Master Soft-Delete property tests PASSED!\n";
    exit(0);
} else {
    echo "Some Material Master Soft-Delete property tests FAILED!\n";
    exit(1);
}
