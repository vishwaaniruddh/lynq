<?php
/**
 * Run Menu Visibility Property Tests
 * 
 * **Feature: adv-crm-users-module, Property 10: Permission-Based Menu Visibility**
 * **Validates: Requirements 6.1, 6.3, 6.4, 6.5**
 */

require_once __DIR__ . '/MenuVisibilityTest.php';

echo "========================================\n";
echo "Menu Visibility Property Tests\n";
echo "========================================\n\n";

$test = new MenuVisibilityTest();
$success = $test->runAllTests();

echo "\n========================================\n";
if ($success) {
    echo "All menu visibility tests PASSED!\n";
} else {
    echo "Some menu visibility tests FAILED!\n";
}
echo "========================================\n";

exit($success ? 0 : 1);
