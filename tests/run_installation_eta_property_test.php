<?php
/**
 * Runner for Installation ETA Property Tests
 * 
 * **Feature: installation-module, Property 5: ETA submission updates status to pending_ada**
 * **Validates: Requirements 3.3**
 */

require_once __DIR__ . '/InstallationETAPropertyTest.php';

echo "Running Installation ETA Property Tests...\n";
echo "==========================================\n";

$test = new InstallationETAPropertyTest();
$success = $test->runTests();

echo "\n==========================================\n";
echo $success ? "All tests passed!\n" : "Some tests failed!\n";

exit($success ? 0 : 1);
