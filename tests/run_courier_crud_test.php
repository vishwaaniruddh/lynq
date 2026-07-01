<?php
/**
 * Run Courier CRUD Round-Trip Property Tests
 * **Feature: crm-sidebar-restructure, Property 4: Courier CRUD Round-Trip Consistency**
 * **Validates: Requirements 2.2, 2.3**
 */

require_once __DIR__ . '/../config/autoload.php';
require_once __DIR__ . '/CourierCrudRoundTripTest.php';

echo "========================================\n";
echo "Courier CRUD Round-Trip Property Tests\n";
echo "========================================\n\n";

$test = new CourierCrudRoundTripTest();

try {
    $passed = $test->runTests();
    
    echo "\n========================================\n";
    if ($passed) {
        echo "All Courier CRUD tests PASSED!\n";
        exit(0);
    } else {
        echo "Some Courier CRUD tests FAILED!\n";
        exit(1);
    }
} catch (Exception $e) {
    echo "\nTest execution failed: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
} finally {
    // Ensure cleanup runs
    $test->cleanupTestData();
}
