<?php
/**
 * Runner for Review Workflow Property Tests
 * 
 * Tests Properties 17, 18, 19, 20, 21, 23, 24, 25, 28
 * Validates Requirements: 10.1, 10.2, 10.3, 10.4, 10.5, 10.6, 10.7, 11.3, 11.4, 11.5, 11.6, 12.4
 */

require_once __DIR__ . '/ReviewWorkflowPropertyTest.php';

echo "========================================\n";
echo "Review Workflow Property Tests\n";
echo "========================================\n\n";

try {
    $test = new ReviewWorkflowPropertyTest();
    $passed = $test->runTests();
    
    echo "\n========================================\n";
    if ($passed) {
        echo "All Review Workflow Property Tests PASSED\n";
        exit(0);
    } else {
        echo "Some Review Workflow Property Tests FAILED\n";
        exit(1);
    }
} catch (Exception $e) {
    echo "Error running tests: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
