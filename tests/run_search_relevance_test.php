<?php
/**
 * Run Search Result Relevance Property Test
 * 
 * **Feature: crm-master-modules, Property 8: Search Result Relevance**
 * **Validates: Requirements 10.1**
 */

require_once __DIR__ . '/SearchResultRelevanceTest.php';

echo "===========================================\n";
echo "Search Result Relevance Property Test\n";
echo "===========================================\n\n";

$test = new SearchResultRelevanceTest();
$success = $test->runAllTests();

echo "\n===========================================\n";
if ($success) {
    echo "ALL TESTS PASSED\n";
} else {
    echo "SOME TESTS FAILED\n";
}
echo "===========================================\n";

exit($success ? 0 : 1);
