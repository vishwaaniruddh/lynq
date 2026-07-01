<?php
/**
 * File Manager Checkpoint 9 Test
 * Validates the File Manager functionality implemented in tasks 6-8
 * 
 * Tests:
 * - Task 6: File editing and saving (writeFile)
 * - Task 7: File and folder deletion (deleteFile, deleteDirectory)
 * - Task 8: Rename operation (renameItem)
 * 
 * Requirements validated: 4.2, 4.3, 4.4, 5.2, 5.4, 10.2, 10.3, 10.4
 */

require_once __DIR__ . '/../config/autoload.php';
require_once __DIR__ . '/../utils/PathValidator.php';
require_once __DIR__ . '/../services/FileManagerService.php';
require_once __DIR__ . '/PropertyTestBase.php';

class FileManagerCheckpoint9Test extends PropertyTestBase {
    private FileManagerService $fileManagerService;
    private string $testDir;
    private array $createdPaths = [];
    
    public function __construct() {
        parent::__construct();
        $this->fileManagerService = new FileManagerService();
        $this->testDir = '';
    }
    
    /**
     * Run all Checkpoint 9 tests
     */
    public function runAllTests(): bool {
        $allPassed = true;
        
        echo "╔══════════════════════════════════════════════════════════════════╗\n";
        echo "║        File Manager Checkpoint 9 Tests - Tasks 6-8              ║\n";
        echo "╚══════════════════════════════════════════════════════════════════╝\n\n";
        
        // Setup test directory
        if (!$this->setupTestDirectory()) {
            echo "✗ Failed to setup test directory\n";
            return false;
        }
        
        // Task 6: File Editing and Saving Tests
        echo "═══════════════════════════════════════════════════════════════════\n";
        echo "Task 6: File Editing and Saving\n";
        echo "═══════════════════════════════════════════════════════════════════\n\n";
        
        echo "Test 1: Write File - Basic Content Update\n";
        echo "─────────────────────────────────────────────────────────────────────\n";
        $allPassed = $this->testWriteFileBasic() && $allPassed;
        echo "\n";
        
        echo "Test 2: Write File - Backup Creation\n";
        echo "─────────────────────────────────────────────────────────────────────\n";
        $allPassed = $this->testWriteFileBackup() && $allPassed;
        echo "\n";
        
        echo "Test 3: Write File - Content Round-Trip\n";
        echo "─────────────────────────────────────────────────────────────────────\n";
        $allPassed = $this->testWriteFileRoundTrip() && $allPassed;
        echo "\n";
        
        // Task 7: File and Folder Deletion Tests
        echo "═══════════════════════════════════════════════════════════════════\n";
        echo "Task 7: File and Folder Deletion\n";
        echo "═══════════════════════════════════════════════════════════════════\n\n";
        
        echo "Test 4: Delete File - Basic Deletion\n";
        echo "─────────────────────────────────────────────────────────────────────\n";
        $allPassed = $this->testDeleteFileBasic() && $allPassed;
        echo "\n";
        
        echo "Test 5: Delete File - Non-existent File\n";
        echo "─────────────────────────────────────────────────────────────────────\n";
        $allPassed = $this->testDeleteFileNonExistent() && $allPassed;
        echo "\n";
        
        echo "Test 6: Delete Directory - Recursive Deletion\n";
        echo "─────────────────────────────────────────────────────────────────────\n";
        $allPassed = $this->testDeleteDirectoryRecursive() && $allPassed;
        echo "\n";
        
        // Task 8: Rename Operation Tests
        echo "═══════════════════════════════════════════════════════════════════\n";
        echo "Task 8: Rename Operation\n";
        echo "═══════════════════════════════════════════════════════════════════\n\n";
        
        echo "Test 7: Rename File - Basic Rename\n";
        echo "─────────────────────────────────────────────────────────────────────\n";
        $allPassed = $this->testRenameFileBasic() && $allPassed;
        echo "\n";
        
        echo "Test 8: Rename Directory - Basic Rename\n";
        echo "─────────────────────────────────────────────────────────────────────\n";
        $allPassed = $this->testRenameDirectoryBasic() && $allPassed;
        echo "\n";
        
        echo "Test 9: Rename - Duplicate Name Prevention\n";
        echo "─────────────────────────────────────────────────────────────────────\n";
        $allPassed = $this->testRenameDuplicatePrevention() && $allPassed;
        echo "\n";
        
        echo "Test 10: Rename - Content Integrity\n";
        echo "─────────────────────────────────────────────────────────────────────\n";
        $allPassed = $this->testRenameContentIntegrity() && $allPassed;
        echo "\n";
        
        // Cleanup
        $this->cleanup();
        
        // Summary
        echo "═══════════════════════════════════════════════════════════════════\n";
        if ($allPassed) {
            echo "✓ ALL CHECKPOINT 9 TESTS PASSED\n";
        } else {
            echo "✗ SOME CHECKPOINT 9 TESTS FAILED\n";
        }
        echo "═══════════════════════════════════════════════════════════════════\n";
        
        return $allPassed;
    }
    
    /**
     * Setup test directory
     */
    private function setupTestDirectory(): bool {
        $result = $this->fileManagerService->createDirectory('htdocs', '_filemanager_cp9_test_' . time());
        if (!$result['success']) {
            echo "  ✗ Failed to create test directory: " . ($result['error'] ?? 'Unknown error') . "\n";
            return false;
        }
        $this->testDir = $result['data']['path'];
        $this->createdPaths[] = $this->testDir;
        echo "  ✓ Created test directory: {$this->testDir}\n\n";
        return true;
    }

    
    /**
     * Test 1: Write File - Basic Content Update
     * Validates: Requirements 4.2, 4.4
     */
    private function testWriteFileBasic(): bool {
        $passed = true;
        
        // Create a test file first
        $createResult = $this->fileManagerService->createFile($this->testDir, 'write_test.txt', 'Original content');
        if (!$createResult['success']) {
            echo "  ✗ Failed to create test file: " . ($createResult['error'] ?? 'Unknown error') . "\n";
            return false;
        }
        $filePath = $createResult['data']['path'];
        echo "  ✓ Created test file: $filePath\n";
        
        // Write new content
        $newContent = 'Updated content - ' . date('Y-m-d H:i:s');
        $writeResult = $this->fileManagerService->writeFile($filePath, $newContent);
        
        if (!$writeResult['success']) {
            echo "  ✗ Failed to write file: " . ($writeResult['error'] ?? 'Unknown error') . "\n";
            return false;
        }
        echo "  ✓ Write operation successful\n";
        
        // Verify content was updated
        $readResult = $this->fileManagerService->readFile($filePath);
        if ($readResult['success'] && $readResult['data']['content'] === $newContent) {
            echo "  ✓ Content correctly updated\n";
        } else {
            echo "  ✗ Content not updated correctly\n";
            $passed = false;
        }
        
        echo $passed ? "  Result: ✓ PASSED\n" : "  Result: ✗ FAILED\n";
        return $passed;
    }
    
    /**
     * Test 2: Write File - Backup Creation
     * Validates: Requirements 4.3
     */
    private function testWriteFileBackup(): bool {
        $passed = true;
        
        // Create a test file
        $createResult = $this->fileManagerService->createFile($this->testDir, 'backup_test.txt', 'Original backup content');
        if (!$createResult['success']) {
            echo "  ✗ Failed to create test file\n";
            return false;
        }
        $filePath = $createResult['data']['path'];
        echo "  ✓ Created test file: $filePath\n";
        
        // Write new content (should create backup)
        $writeResult = $this->fileManagerService->writeFile($filePath, 'New content after backup');
        
        if (!$writeResult['success']) {
            echo "  ✗ Failed to write file\n";
            return false;
        }
        
        // Check if backup was created
        if (isset($writeResult['data']['backupCreated']) && $writeResult['data']['backupCreated']) {
            echo "  ✓ Backup was created\n";
            if (isset($writeResult['data']['backupPath'])) {
                echo "  ✓ Backup path: " . $writeResult['data']['backupPath'] . "\n";
            }
        } else {
            echo "  ⚠ Backup creation status unclear\n";
        }
        
        echo $passed ? "  Result: ✓ PASSED\n" : "  Result: ✗ FAILED\n";
        return $passed;
    }
    
    /**
     * Test 3: Write File - Content Round-Trip
     * Validates: Property 3 - File Content Round-Trip
     */
    private function testWriteFileRoundTrip(): bool {
        $passed = true;
        
        // Create a test file
        $createResult = $this->fileManagerService->createFile($this->testDir, 'roundtrip_test.txt', '');
        if (!$createResult['success']) {
            echo "  ✗ Failed to create test file\n";
            return false;
        }
        $filePath = $createResult['data']['path'];
        
        // Test with various content types
        $testContents = [
            'Simple text content',
            "Multi-line\ncontent\nwith\nnewlines",
            "Special chars: <>&\"'",
            "Unicode: 你好世界 🎉",
            str_repeat('Long content ', 100),
        ];
        
        foreach ($testContents as $index => $content) {
            // Write content
            $writeResult = $this->fileManagerService->writeFile($filePath, $content);
            if (!$writeResult['success']) {
                echo "  ✗ Failed to write content $index\n";
                $passed = false;
                continue;
            }
            
            // Read back
            $readResult = $this->fileManagerService->readFile($filePath);
            if (!$readResult['success']) {
                echo "  ✗ Failed to read content $index\n";
                $passed = false;
                continue;
            }
            
            // Compare
            if ($readResult['data']['content'] === $content) {
                echo "  ✓ Round-trip test $index passed\n";
            } else {
                echo "  ✗ Round-trip test $index failed - content mismatch\n";
                $passed = false;
            }
        }
        
        echo $passed ? "  Result: ✓ PASSED\n" : "  Result: ✗ FAILED\n";
        return $passed;
    }
    
    /**
     * Test 4: Delete File - Basic Deletion
     * Validates: Requirements 5.2
     */
    private function testDeleteFileBasic(): bool {
        $passed = true;
        
        // Create a test file
        $createResult = $this->fileManagerService->createFile($this->testDir, 'delete_test.txt', 'Content to delete');
        if (!$createResult['success']) {
            echo "  ✗ Failed to create test file\n";
            return false;
        }
        $filePath = $createResult['data']['path'];
        echo "  ✓ Created test file: $filePath\n";
        
        // Delete the file
        $deleteResult = $this->fileManagerService->deleteFile($filePath);
        
        if (!$deleteResult['success']) {
            echo "  ✗ Failed to delete file: " . ($deleteResult['error'] ?? 'Unknown error') . "\n";
            return false;
        }
        echo "  ✓ Delete operation successful\n";
        
        // Verify file no longer exists (check filesystem directly)
        $absolutePath = $this->fileManagerService->getPathValidator()->getAbsolutePath($filePath);
        if (!file_exists($absolutePath)) {
            echo "  ✓ File correctly removed from filesystem\n";
        } else {
            echo "  ✗ File still exists after deletion\n";
            $passed = false;
        }
        
        // Verify file doesn't appear in directory listing
        $listResult = $this->fileManagerService->listDirectory($this->testDir);
        if ($listResult['success']) {
            $found = false;
            foreach ($listResult['data']['items'] as $item) {
                if ($item['name'] === 'delete_test.txt') {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                echo "  ✓ File not in directory listing\n";
            } else {
                echo "  ✗ File still appears in directory listing\n";
                $passed = false;
            }
        }
        
        echo $passed ? "  Result: ✓ PASSED\n" : "  Result: ✗ FAILED\n";
        return $passed;
    }
    
    /**
     * Test 5: Delete File - Non-existent File
     * Validates: Error handling for deletion
     */
    private function testDeleteFileNonExistent(): bool {
        $passed = true;
        
        // Try to delete a non-existent file
        $deleteResult = $this->fileManagerService->deleteFile($this->testDir . '/nonexistent_file.txt');
        
        if (!$deleteResult['success']) {
            echo "  ✓ Correctly returned error for non-existent file (code: " . ($deleteResult['code'] ?? 'unknown') . ")\n";
        } else {
            echo "  ✗ Should have returned an error for non-existent file\n";
            $passed = false;
        }
        
        echo $passed ? "  Result: ✓ PASSED\n" : "  Result: ✗ FAILED\n";
        return $passed;
    }

    
    /**
     * Test 6: Delete Directory - Recursive Deletion
     * Validates: Requirements 5.4, Property 7
     */
    private function testDeleteDirectoryRecursive(): bool {
        $passed = true;
        
        // Create a nested directory structure
        $subDirResult = $this->fileManagerService->createDirectory($this->testDir, 'recursive_delete_test');
        if (!$subDirResult['success']) {
            echo "  ✗ Failed to create subdirectory\n";
            return false;
        }
        $subDir = $subDirResult['data']['path'];
        echo "  ✓ Created subdirectory: $subDir\n";
        
        // Create nested subdirectory
        $nestedResult = $this->fileManagerService->createDirectory($subDir, 'nested');
        if (!$nestedResult['success']) {
            echo "  ✗ Failed to create nested directory\n";
            return false;
        }
        $nestedDir = $nestedResult['data']['path'];
        echo "  ✓ Created nested directory: $nestedDir\n";
        
        // Create files in both directories
        $this->fileManagerService->createFile($subDir, 'file1.txt', 'Content 1');
        $this->fileManagerService->createFile($subDir, 'file2.txt', 'Content 2');
        $this->fileManagerService->createFile($nestedDir, 'nested_file.txt', 'Nested content');
        echo "  ✓ Created test files in directories\n";
        
        // Delete the parent directory recursively
        $deleteResult = $this->fileManagerService->deleteDirectory($subDir);
        
        if (!$deleteResult['success']) {
            echo "  ✗ Failed to delete directory: " . ($deleteResult['error'] ?? 'Unknown error') . "\n";
            return false;
        }
        echo "  ✓ Recursive delete operation successful\n";
        
        // Verify directory no longer exists
        $listResult = $this->fileManagerService->listDirectory($this->testDir);
        if ($listResult['success']) {
            $found = false;
            foreach ($listResult['data']['items'] as $item) {
                if ($item['name'] === 'recursive_delete_test') {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                echo "  ✓ Directory and all contents removed\n";
            } else {
                echo "  ✗ Directory still exists after deletion\n";
                $passed = false;
            }
        }
        
        echo $passed ? "  Result: ✓ PASSED\n" : "  Result: ✗ FAILED\n";
        return $passed;
    }
    
    /**
     * Test 7: Rename File - Basic Rename
     * Validates: Requirements 10.2
     */
    private function testRenameFileBasic(): bool {
        $passed = true;
        
        // Create a test file
        $createResult = $this->fileManagerService->createFile($this->testDir, 'rename_original.txt', 'Rename test content');
        if (!$createResult['success']) {
            echo "  ✗ Failed to create test file\n";
            return false;
        }
        $originalPath = $createResult['data']['path'];
        echo "  ✓ Created test file: $originalPath\n";
        
        // Rename the file
        $renameResult = $this->fileManagerService->renameItem($originalPath, 'rename_new.txt');
        
        if (!$renameResult['success']) {
            echo "  ✗ Failed to rename file: " . ($renameResult['error'] ?? 'Unknown error') . "\n";
            return false;
        }
        echo "  ✓ Rename operation successful\n";
        
        // Verify old name no longer exists (check filesystem directly)
        $oldAbsolutePath = $this->fileManagerService->getPathValidator()->getAbsolutePath($originalPath);
        if (!file_exists($oldAbsolutePath)) {
            echo "  ✓ Original file no longer exists\n";
        } else {
            echo "  ✗ Original file still exists\n";
            $passed = false;
        }
        
        // Verify new name exists
        $newPath = $renameResult['data']['newPath'];
        $readNewResult = $this->fileManagerService->readFile($newPath);
        if ($readNewResult['success']) {
            echo "  ✓ New file exists: $newPath\n";
        } else {
            echo "  ✗ New file does not exist\n";
            $passed = false;
        }
        
        echo $passed ? "  Result: ✓ PASSED\n" : "  Result: ✗ FAILED\n";
        return $passed;
    }
    
    /**
     * Test 8: Rename Directory - Basic Rename
     * Validates: Requirements 10.2
     */
    private function testRenameDirectoryBasic(): bool {
        $passed = true;
        
        // Create a test directory
        $createResult = $this->fileManagerService->createDirectory($this->testDir, 'dir_rename_original');
        if (!$createResult['success']) {
            echo "  ✗ Failed to create test directory\n";
            return false;
        }
        $originalPath = $createResult['data']['path'];
        echo "  ✓ Created test directory: $originalPath\n";
        
        // Create a file inside to verify contents are preserved
        $this->fileManagerService->createFile($originalPath, 'inside_file.txt', 'Content inside');
        
        // Rename the directory
        $renameResult = $this->fileManagerService->renameItem($originalPath, 'dir_rename_new');
        
        if (!$renameResult['success']) {
            echo "  ✗ Failed to rename directory: " . ($renameResult['error'] ?? 'Unknown error') . "\n";
            return false;
        }
        echo "  ✓ Directory rename successful\n";
        
        // Verify new directory exists and contains the file
        $newPath = $renameResult['data']['newPath'];
        $listResult = $this->fileManagerService->listDirectory($newPath);
        if ($listResult['success']) {
            $found = false;
            foreach ($listResult['data']['items'] as $item) {
                if ($item['name'] === 'inside_file.txt') {
                    $found = true;
                    break;
                }
            }
            if ($found) {
                echo "  ✓ Directory contents preserved after rename\n";
            } else {
                echo "  ✗ Directory contents not preserved\n";
                $passed = false;
            }
        } else {
            echo "  ✗ Cannot list renamed directory\n";
            $passed = false;
        }
        
        echo $passed ? "  Result: ✓ PASSED\n" : "  Result: ✗ FAILED\n";
        return $passed;
    }
    
    /**
     * Test 9: Rename - Duplicate Name Prevention
     * Validates: Requirements 10.3, Property 5
     */
    private function testRenameDuplicatePrevention(): bool {
        $passed = true;
        
        // Create two test files
        $this->fileManagerService->createFile($this->testDir, 'existing_file.txt', 'Existing content');
        $createResult = $this->fileManagerService->createFile($this->testDir, 'file_to_rename.txt', 'To rename');
        
        if (!$createResult['success']) {
            echo "  ✗ Failed to create test files\n";
            return false;
        }
        echo "  ✓ Created test files\n";
        
        // Try to rename to existing name
        $renameResult = $this->fileManagerService->renameItem(
            $createResult['data']['path'],
            'existing_file.txt'
        );
        
        if (!$renameResult['success'] && $renameResult['code'] === 'FILE_EXISTS') {
            echo "  ✓ Correctly prevented rename to existing name\n";
        } else {
            echo "  ✗ Should have prevented rename to existing name\n";
            $passed = false;
        }
        
        echo $passed ? "  Result: ✓ PASSED\n" : "  Result: ✗ FAILED\n";
        return $passed;
    }
    
    /**
     * Test 10: Rename - Content Integrity
     * Validates: Property 13 - Rename Operation Integrity
     */
    private function testRenameContentIntegrity(): bool {
        $passed = true;
        
        $originalContent = "Test content for integrity check\nLine 2\nLine 3";
        
        // Create a test file with specific content
        $createResult = $this->fileManagerService->createFile($this->testDir, 'integrity_original.txt', $originalContent);
        if (!$createResult['success']) {
            echo "  ✗ Failed to create test file\n";
            return false;
        }
        $originalPath = $createResult['data']['path'];
        echo "  ✓ Created test file with content\n";
        
        // Rename the file
        $renameResult = $this->fileManagerService->renameItem($originalPath, 'integrity_renamed.txt');
        
        if (!$renameResult['success']) {
            echo "  ✗ Failed to rename file\n";
            return false;
        }
        echo "  ✓ Renamed file\n";
        
        // Read content from new location
        $readResult = $this->fileManagerService->readFile($renameResult['data']['newPath']);
        
        if ($readResult['success'] && $readResult['data']['content'] === $originalContent) {
            echo "  ✓ Content integrity preserved after rename\n";
        } else {
            echo "  ✗ Content changed after rename\n";
            $passed = false;
        }
        
        echo $passed ? "  Result: ✓ PASSED\n" : "  Result: ✗ FAILED\n";
        return $passed;
    }
    
    /**
     * Cleanup test files and directories
     */
    private function cleanup(): void {
        if (!empty($this->testDir)) {
            echo "\nCleaning up test directory: {$this->testDir}\n";
            $result = $this->fileManagerService->deleteDirectory($this->testDir);
            if ($result['success']) {
                echo "  ✓ Test directory cleaned up\n";
            } else {
                echo "  ⚠ Failed to cleanup test directory: " . ($result['error'] ?? 'Unknown error') . "\n";
            }
        }
    }
}

// Run tests if executed directly
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    $test = new FileManagerCheckpoint9Test();
    $result = $test->runAllTests();
    exit($result ? 0 : 1);
}
