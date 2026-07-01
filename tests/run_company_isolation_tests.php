<?php
/**
 * Run all Company Isolation Property Tests
 * Tests for Task 4: Implement company isolation and multi-tenancy
 */

require_once __DIR__ . '/../config/autoload.php';

echo "========================================\n";
echo "Company Isolation Property Tests\n";
echo "========================================\n\n";

$allPassed = true;

// Test 1: Company Isolation for User Queries (Property 2)
echo "Test 1: Company Isolation for User Queries\n";
echo "-------------------------------------------\n";
require_once __DIR__ . '/CompanyIsolationTest.php';
$test1 = new CompanyIsolationTest();
$result1 = $test1->runTests();
$allPassed = $allPassed && $result1;
echo "\n";

// Test 2: Contractor Company Scope Restriction (Property 4)
echo "Test 2: Contractor Company Scope Restriction\n";
echo "---------------------------------------------\n";
require_once __DIR__ . '/ContractorScopeRestrictionTest.php';
$test2 = new ContractorScopeRestrictionTest();
$result2 = $test2->runTests();
$allPassed = $allPassed && $result2;
echo "\n";

// Test 3: Cross-Company Access Prevention (Property 13)
echo "Test 3: Cross-Company Access Prevention\n";
echo "----------------------------------------\n";
require_once __DIR__ . '/CrossCompanyAccessPreventionTest.php';
$test3 = new CrossCompanyAccessPreventionTest();
$result3 = $test3->runTests();
$allPassed = $allPassed && $result3;
echo "\n";

echo "========================================\n";
echo "Summary\n";
echo "========================================\n";
echo "Property 2 (Company Isolation): " . ($result1 ? "PASSED" : "FAILED") . "\n";
echo "Property 4 (Contractor Scope): " . ($result2 ? "PASSED" : "FAILED") . "\n";
echo "Property 13 (Cross-Company Prevention): " . ($result3 ? "PASSED" : "FAILED") . "\n";
echo "\n";
echo "Overall: " . ($allPassed ? "ALL TESTS PASSED" : "SOME TESTS FAILED") . "\n";

exit($allPassed ? 0 : 1);
