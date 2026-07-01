<?php
/**
 * Test Runner for Template Selection Consistency Property Test
 * **Feature: email-management-system, Property 13: Template Selection Consistency**
 * **Validates: Requirements 6.1**
 */

require_once __DIR__ . '/../config/autoload.php';
require_once 'TemplateSelectionConsistencyPropertyTest.php';

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Starting Template Selection Consistency Property Tests...\n";
echo "=======================================================\n\n";

try {
    $test = new TemplateSelectionConsistencyPropertyTest();
    $success = $test->runTests();
    
    if ($success) {
        echo "\n✅ All Template Selection Consistency property tests PASSED!\n";
        exit(0);
    } else {
        echo "\n❌ Some Template Selection Consistency property tests FAILED!\n";
        exit(1);
    }
} catch (Exception $e) {
    echo "\n💥 Test execution failed with error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}