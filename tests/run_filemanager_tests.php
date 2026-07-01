<?php
/**
 * File Manager Test Runner
 * Runs all File Manager module tests for Checkpoint 5
 * 
 * Validates implementation of tasks 1-4:
 * - Task 1: PathValidator utility class
 * - Task 2: Directory listing and navigation
 * - Task 3: File read and view operations
 * - Task 4: File and folder creation
 */

require_once __DIR__ . '/../config/autoload.php';
require_once __DIR__ . '/FileManagerBasicTest.php';

echo "╔══════════════════════════════════════════════════════════════════╗\n";
echo "║        File Manager Module - Test Suite (Checkpoint 5)           ║\n";
echo "║                    Tasks 1-4 Validation                          ║\n";
echo "╚══════════════════════════════════════════════════════════════════╝\n\n";

$startTime = microtime(true);

// Run File Manager Basic Tests
$test = new FileManagerBasicTest();
$result = $test->runAllTests();

$endTime = microtime(true);
$duration = round($endTime - $startTime, 2);

echo "\n";
echo "═══════════════════════════════════════════════════════════════════\n";
echo "Test Duration: {$duration} seconds\n";
echo "═══════════════════════════════════════════════════════════════════\n";

if ($result) {
    echo "\n✓ CHECKPOINT 5 PASSED - All File Manager tests successful\n";
    echo "  Tasks 1-4 implementation validated.\n\n";
    exit(0);
} else {
    echo "\n✗ CHECKPOINT 5 FAILED - Some tests did not pass\n";
    echo "  Please review and fix the failing tests.\n\n";
    exit(1);
}
