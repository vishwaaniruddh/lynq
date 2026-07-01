<?php
/**
 * Runner for Installation ADA Property Tests
 * 
 * **Feature: installation-module, Property 6: ADA submission updates status to pending_materials**
 * **Validates: Requirements 3.5**
 */

require_once __DIR__ . '/InstallationADAPropertyTest.php';

echo "Running Installation ADA Property Tests...\n";
echo "==========================================\n";

$test = new InstallationADAPropertyTest();
$success = $test->runTests();

echo "\n==========================================\n";
echo $success ? "All tests passed!\n" : "Some tests failed!\n";

exit($success ? 0 : 1);
