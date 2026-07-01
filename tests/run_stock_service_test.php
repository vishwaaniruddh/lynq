<?php
/**
 * Runner for StockService Unit Tests
 * 
 * Requirements: 3.1, 3.2, 5.2
 */

require_once __DIR__ . '/StockServiceTest.php';

echo "========================================\n";
echo "StockService Unit Tests\n";
echo "========================================\n\n";

$test = new StockServiceTest();
$passed = $test->runTests();

echo "\n========================================\n";
if ($passed) {
    echo "All unit tests PASSED\n";
    exit(0);
} else {
    echo "Some unit tests FAILED\n";
    exit(1);
}
