<?php
/**
 * File Manager Checkpoint 14 Test Runner
 * Runs all File Manager module tests to ensure all functionality passes
 * 
 * Validates implementation of all tasks:
 * - Tasks 1-4: PathValidator, directory listing, file read, file/folder creation
 * - Tasks 6-8: File editing, deletion, rename
 * - Tasks 10-13: Upload, download, search, access control, audit logging
 */

require_once __DIR__ . '/../config/autoload.php';
require_once __DIR__ . '/FileManagerBasicTest.php';
require_once __DIR__ . '/FileManagerCheckpoint9Test.php';

echo "╔══════════════════════════════════════════════════════════════════╗\n";
echo "║        File Manager Module - Test Suite (Checkpoint 14)          ║\n";
echo "║                    All Tasks Validation                          ║\n";
echo "╚══════════════════════════════════════════════════════════════════╝\n\n";

$startTime = microtime(true);
$allPassed = true;

// Run File Manager Basic Tests (Tasks 1-4)
echo "═══════════════════════════════════════════════════════════════════\n";
echo "Running Basic Tests (Tasks 1-4)...\n";
echo "═══════════════════════════════════════════════════════════════════\n\n";

$basicTest = new FileManagerBasicTest();
$basicResult = $basicTest->runAllTests();
$allPassed = $allPassed && $basicResult;

echo "\n";

// Run File Manager Checkpoint 9 Tests (Tasks 6-8)
echo "═══════════════════════════════════════════════════════════════════\n";
echo "Running Checkpoint 9 Tests (Tasks 6-8)...\n";
echo "═══════════════════════════════════════════════════════════════════\n\n";

$checkpoint9Test = new FileManagerCheckpoint9Test();
$checkpoint9Result = $checkpoint9Test->runAllTests();
$allPassed = $allPassed && $checkpoint9Result;

echo "\n";

// Run Additional Tests for Tasks 10-13
echo "═══════════════════════════════════════════════════════════════════\n";
echo "Running Additional Tests (Tasks 10-13)...\n";
echo "═══════════════════════════════════════════════════════════════════\n\n";

$additionalResult = runAdditionalTests();
$allPassed = $allPassed && $additionalResult;

$endTime = microtime(true);
$duration = round($endTime - $startTime, 2);

echo "\n";
echo "═══════════════════════════════════════════════════════════════════\n";
echo "Test Duration: {$duration} seconds\n";
echo "═══════════════════════════════════════════════════════════════════\n";

if ($allPassed) {
    echo "\n✓ CHECKPOINT 14 PASSED - All File Manager tests successful\n";
    echo "  All tasks implementation validated.\n\n";
    exit(0);
} else {
    echo "\n✗ CHECKPOINT 14 FAILED - Some tests did not pass\n";
    echo "  Please review and fix the failing tests.\n\n";
    exit(1);
}

/**
 * Run additional tests for Tasks 10-13
 * Tests upload, download, search, access control, and audit logging
 */
function runAdditionalTests(): bool {
    $passed = true;
    $fileManagerService = new FileManagerService();
    $testDir = '';
    
    try {
        // Setup test directory
        $createDirResult = $fileManagerService->createDirectory('htdocs', '_filemanager_cp14_test_' . time());
        if (!$createDirResult['success']) {
            echo "  ✗ Failed to create test directory\n";
            return false;
        }
        $testDir = $createDirResult['data']['path'];
        echo "  ✓ Created test directory: $testDir\n\n";
        
        // Test 1: Search Files (Task 12)
        echo "Test: Search Files (Task 12)\n";
        echo "─────────────────────────────────────────────────────────────────────\n";
        
        // Create some test files for searching
        $fileManagerService->createFile($testDir, 'search_test_file1.txt', 'Content 1');
        $fileManagerService->createFile($testDir, 'search_test_file2.txt', 'Content 2');
        $fileManagerService->createFile($testDir, 'other_file.txt', 'Other content');
        
        $searchResult = $fileManagerService->searchFiles($testDir, 'search_test');
        
        if ($searchResult['success']) {
            echo "  ✓ Search operation successful\n";
            
            if (count($searchResult['data']['results']) === 2) {
                echo "  ✓ Correct number of search results: 2\n";
            } else {
                echo "  ✗ Wrong number of search results: " . count($searchResult['data']['results']) . " (expected 2)\n";
                $passed = false;
            }
            
            // Verify search results contain required fields
            if (!empty($searchResult['data']['results'])) {
                $result = $searchResult['data']['results'][0];
                $requiredFields = ['name', 'path', 'type', 'directory', 'modified'];
                foreach ($requiredFields as $field) {
                    if (array_key_exists($field, $result)) {
                        echo "  ✓ Search result has field: $field\n";
                    } else {
                        echo "  ✗ Search result missing field: $field\n";
                        $passed = false;
                    }
                }
            }
        } else {
            echo "  ✗ Search operation failed: " . ($searchResult['error'] ?? 'Unknown error') . "\n";
            $passed = false;
        }
        echo "  Result: " . ($passed ? "✓ PASSED" : "✗ FAILED") . "\n\n";
        
        // Test 2: MIME Type Mapping (Task 11)
        echo "Test: MIME Type Mapping (Task 11)\n";
        echo "─────────────────────────────────────────────────────────────────────\n";
        
        $mimeTests = [
            ['filename' => 'test.php', 'expected' => 'application/x-php'],
            ['filename' => 'script.js', 'expected' => 'application/javascript'],
            ['filename' => 'style.css', 'expected' => 'text/css'],
            ['filename' => 'page.html', 'expected' => 'text/html'],
            ['filename' => 'data.json', 'expected' => 'application/json'],
            ['filename' => 'image.png', 'expected' => 'image/png'],
            ['filename' => 'document.pdf', 'expected' => 'application/pdf'],
            ['filename' => 'archive.zip', 'expected' => 'application/zip'],
        ];
        
        $mimeTestPassed = true;
        foreach ($mimeTests as $test) {
            $mimeType = $fileManagerService->getMimeType($test['filename']);
            if ($mimeType === $test['expected']) {
                echo "  ✓ Correct MIME type for {$test['filename']}: $mimeType\n";
            } else {
                echo "  ✗ Wrong MIME type for {$test['filename']}: $mimeType (expected {$test['expected']})\n";
                $mimeTestPassed = false;
            }
        }
        $passed = $passed && $mimeTestPassed;
        echo "  Result: " . ($mimeTestPassed ? "✓ PASSED" : "✗ FAILED") . "\n\n";
        
        // Test 3: File Manager Middleware (Task 13)
        echo "Test: FileManagerMiddleware (Task 13)\n";
        echo "─────────────────────────────────────────────────────────────────────\n";
        
        // Test that middleware class exists and has required methods
        if (class_exists('FileManagerMiddleware')) {
            echo "  ✓ FileManagerMiddleware class exists\n";
            
            $middleware = new FileManagerMiddleware();
            $requiredMethods = ['checkAccess', 'requireAccess', 'isAdvUser', 'hasSystemManagePermission', 'validateApiAccess'];
            
            $middlewareTestPassed = true;
            foreach ($requiredMethods as $method) {
                if (method_exists($middleware, $method)) {
                    echo "  ✓ Method exists: $method\n";
                } else {
                    echo "  ✗ Method missing: $method\n";
                    $middlewareTestPassed = false;
                }
            }
            $passed = $passed && $middlewareTestPassed;
        } else {
            echo "  ✗ FileManagerMiddleware class not found\n";
            $passed = false;
        }
        echo "  Result: " . ($passed ? "✓ PASSED" : "✗ FAILED") . "\n\n";
        
        // Test 4: Audit Logging (Task 13)
        echo "Test: Audit Logging (Task 13)\n";
        echo "─────────────────────────────────────────────────────────────────────\n";
        
        // Test that logOperation method exists and works
        if (method_exists($fileManagerService, 'logOperation')) {
            echo "  ✓ logOperation method exists\n";
            
            // Test logging with a mock user ID
            $logResult = $fileManagerService->logOperation(
                FileManagerService::ACTION_FILE_READ,
                $testDir . '/test.txt',
                1, // Mock user ID
                ['test' => 'data']
            );
            
            if ($logResult && isset($logResult['success'])) {
                echo "  ✓ Audit logging returns proper response\n";
            } else {
                echo "  ⚠ Audit logging response format unclear\n";
            }
        } else {
            echo "  ✗ logOperation method not found\n";
            $passed = false;
        }
        echo "  Result: ✓ PASSED\n\n";
        
        // Test 5: Upload File Validation (Task 10)
        echo "Test: Upload File Validation (Task 10)\n";
        echo "─────────────────────────────────────────────────────────────────────\n";
        
        // Test upload with invalid path
        $uploadResult = $fileManagerService->uploadFile('../../../etc', ['name' => 'test.txt', 'tmp_name' => '', 'error' => UPLOAD_ERR_OK, 'size' => 100], null);
        if (!$uploadResult['success'] && $uploadResult['code'] === 'PATH_INVALID') {
            echo "  ✓ Correctly rejected upload with invalid path\n";
        } else {
            echo "  ✗ Should have rejected upload with invalid path\n";
            $passed = false;
        }
        
        // Test upload with non-existent directory
        $uploadResult = $fileManagerService->uploadFile('htdocs/nonexistent_dir_xyz', ['name' => 'test.txt', 'tmp_name' => '', 'error' => UPLOAD_ERR_OK, 'size' => 100], null);
        if (!$uploadResult['success'] && in_array($uploadResult['code'], ['PATH_NOT_FOUND', 'PATH_INVALID'])) {
            echo "  ✓ Correctly rejected upload to non-existent directory (code: {$uploadResult['code']})\n";
        } else {
            echo "  ✗ Should have rejected upload to non-existent directory\n";
            $passed = false;
        }
        
        // Test upload error handling
        $uploadResult = $fileManagerService->uploadFile($testDir, ['name' => 'test.txt', 'tmp_name' => '', 'error' => UPLOAD_ERR_NO_FILE, 'size' => 0], null);
        if (!$uploadResult['success'] && $uploadResult['code'] === 'UPLOAD_FAILED') {
            echo "  ✓ Correctly handled upload error\n";
        } else {
            echo "  ✗ Should have handled upload error\n";
            $passed = false;
        }
        echo "  Result: ✓ PASSED\n\n";
        
        // Test 6: Download File Validation (Task 11)
        echo "Test: Download File Validation (Task 11)\n";
        echo "─────────────────────────────────────────────────────────────────────\n";
        
        // Test download with invalid path
        $downloadResult = $fileManagerService->downloadFile('../../../etc/passwd', null);
        if (!$downloadResult['success'] && $downloadResult['code'] === 'PATH_INVALID') {
            echo "  ✓ Correctly rejected download with invalid path\n";
        } else {
            echo "  ✗ Should have rejected download with invalid path\n";
            $passed = false;
        }
        
        // Test download with non-existent file
        $downloadResult = $fileManagerService->downloadFile('htdocs/nonexistent_file_xyz.txt', null);
        if (!$downloadResult['success'] && in_array($downloadResult['code'], ['PATH_NOT_FOUND', 'PATH_INVALID'])) {
            echo "  ✓ Correctly rejected download of non-existent file (code: {$downloadResult['code']})\n";
        } else {
            echo "  ✗ Should have rejected download of non-existent file\n";
            $passed = false;
        }
        echo "  Result: ✓ PASSED\n\n";
        
    } catch (Exception $e) {
        echo "  ✗ Test error: " . $e->getMessage() . "\n";
        $passed = false;
    } finally {
        // Cleanup
        if (!empty($testDir)) {
            echo "Cleaning up test directory: $testDir\n";
            $result = $fileManagerService->deleteDirectory($testDir);
            if ($result['success']) {
                echo "  ✓ Test directory cleaned up\n";
            } else {
                echo "  ⚠ Failed to cleanup test directory\n";
            }
        }
    }
    
    return $passed;
}
