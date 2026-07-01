<?php
/**
 * Runner for All Feasibility Module Property Tests
 * **Feature: feasibility-module**
 * **Validates: Requirements 2.2, 2.3, 2.4, 2.5, 3.4, 3.5, 4.3, 4.4, 6.2**
 */

require_once __DIR__ . '/ETAPropertyTest.php';
require_once __DIR__ . '/ADAPropertyTest.php';
require_once __DIR__ . '/FeasibilityCheckPropertyTest.php';
require_once __DIR__ . '/ImageUploadPropertyTest.php';

echo "========================================\n";
echo "All Feasibility Module Property Tests\n";
echo "========================================\n\n";

$allPassed = true;

// Run ETA tests
echo "--- ETA Property Tests ---\n";
$etaTest = new ETAPropertyTest();
$allPassed &= $etaTest->runTests();
echo "\n";

// Run ADA tests
echo "--- ADA Property Tests ---\n";
$adaTest = new ADAPropertyTest();
$allPassed &= $adaTest->runTests();
echo "\n";

// Run Feasibility Check tests
echo "--- Feasibility Check Property Tests ---\n";
$feasibilityTest = new FeasibilityCheckPropertyTest();
$allPassed &= $feasibilityTest->runTests();
echo "\n";

// Run Image Upload tests
echo "--- Image Upload Property Tests ---\n";
$imageTest = new ImageUploadPropertyTest();
$allPassed &= $imageTest->runTests();
echo "\n";

echo "========================================\n";
if ($allPassed) {
    echo "ALL FEASIBILITY MODULE PROPERTY TESTS PASSED\n";
    exit(0);
} else {
    echo "SOME FEASIBILITY MODULE PROPERTY TESTS FAILED\n";
    exit(1);
}
