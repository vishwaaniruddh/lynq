<?php
/**
 * Test Runner for ADV Documentation Access Control Property Test
 * **Feature: adv-user-documentation, Property 1: ADV-Only Icon Visibility**
 * **Validates: Requirements 1.1, 1.3**
 */

require_once __DIR__ . '/AdvDocumentationAccessControlPropertyTest.php';

echo "========================================\n";
echo "ADV Documentation Access Control Tests\n";
echo "========================================\n\n";

$test = new AdvDocumentationAccessControlPropertyTest();
$result = $test->runTests();

echo "\n========================================\n";
if ($result) {
    echo "All tests PASSED!\n";
} else {
    echo "Some tests FAILED!\n";
}
echo "========================================\n";

// Cleanup test data
$test->cleanupTestData();

exit($result ? 0 : 1);
