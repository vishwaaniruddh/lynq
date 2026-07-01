<?php
/**
 * File Manager Checkpoint 9 Test Runner
 * Runs all File Manager module tests for Checkpoint 9
 * 
 * Validates implementation of tasks 6-8:
 * - Task 6: File editing and saving (writeFile)
 * - Task 7: File and folder deletion (deleteFile, deleteDirectory)
 * - Task 8: Rename operation (renameItem)
 */

require_once __DIR__ . '/../config/autoload.php';
require_once __DIR__ . '/FileManagerCheckpoint9Test.php';

echo "╔══════════════════════════════════════════════════════════════════╗\n";
echo "║        File Manager Module - Test Suite (Checkpoint 9)           ║\n";
echo "║                    Tasks 6-8 Validation                          ║\n";
echo "╚══════════════════════════════════════════════════════════════════╝\n\n";

$startTime = microtime(true);

// Run File Manager Checkpoint 9 Tests
$test = new FileManagerCheckpoint9Test();
$result = $test->runAllTests();

$endTime = microtime(true);
$duration = round($endTime - $startTime, 2);

echo "\n";
echo "═══════════════════════════════════════════════════════════════════\n";
echo "Test Duration: {$duration} seconds\n";
echo "═══════════════════════════════════════════════════════════════════\n";

if ($result) {
    echo "\n✓ CHECKPOINT 9 PASSED - All File Manager tests successful\n";
    echo "  Tasks 6-8 implementation validated.\n\n";
    exit(0);
} else {
    echo "\n✗ CHECKPOINT 9 FAILED - Some tests did not pass\n";
    echo "  Please review and fix the failing tests.\n\n";
    exit(1);
}
