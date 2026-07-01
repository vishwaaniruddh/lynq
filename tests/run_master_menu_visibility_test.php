<?php
/**
 * Test Runner for Master Data Menu Visibility Property Test
 * 
 * **Feature: crm-master-modules, Property 15: Menu Visibility for ADV Users**
 * **Validates: Requirements 7.1**
 */

require_once __DIR__ . '/../config/autoload.php';
require_once __DIR__ . '/MasterDataMenuVisibilityTest.php';

echo "===========================================\n";
echo "Master Data Menu Visibility Property Test\n";
echo "===========================================\n";
echo "**Feature: crm-master-modules, Property 15: Menu Visibility for ADV Users**\n";
echo "**Validates: Requirements 7.1**\n";
echo "===========================================\n\n";

$test = new MasterDataMenuVisibilityTest();
$success = $test->runAllTests();

echo "\n===========================================\n";
if ($success) {
    echo "ALL TESTS PASSED!\n";
} else {
    echo "SOME TESTS FAILED!\n";
}
echo "===========================================\n";

exit($success ? 0 : 1);
