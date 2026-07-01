<?php
/**
 * File Manager Basic Test
 * Validates the core File Manager functionality implemented in tasks 1-4
 * 
 * Tests:
 * - PathValidator path sanitization and validation
 * - PathValidator traversal attack detection
 * - FileManagerService directory listing
 * - FileManagerService breadcrumb generation
 * - FileManagerService file read operations
 * - FileManagerService file/directory creation
 * 
 * Requirements validated: 1.1, 1.3, 1.4, 1.5, 2.1, 2.2, 3.2, 3.4, 3.5, 6.2, 6.5
 */

require_once __DIR__ . '/../config/autoload.php';
require_once __DIR__ . '/../utils/PathValidator.php';
require_once __DIR__ . '/../services/FileManagerService.php';
require_once __DIR__ . '/PropertyTestBase.php';

class FileManagerBasicTest extends PropertyTestBase {
    private PathValidator $pathValidator;
    private FileManagerService $fileManagerService;
    private string $testDir;
    
    public function __construct() {
        parent::__construct();
        $this->fileManagerService = new FileManagerService();
        $this->pathValidator = $this->fileManagerService->getPathValidator();
        $this->testDir = 'htdocs/_filemanager_test_' . time();
    }
    
    /**
     * Run all basic tests
     */
    public function runAllTests(): bool {
        $allPassed = true;
        
        echo "╔══════════════════════════════════════════════════════════════════╗\n";
        echo "║           File Manager Basic Tests - Checkpoint 5                ║\n";
        echo "╚══════════════════════════════════════════════════════════════════╝\n\n";
        
        // Test 1: PathValidator - Traversal Detection
        echo "Test 1: PathValidator - Traversal Attack Detection\n";
        echo "─────────────────────────────────────────────────────────────────────\n";
        $allPassed = $this->testTraversalDetection() && $allPassed;
        echo "\n";
        
        // Test 2: PathValidator - Path Sanitization
        echo "Test 2: PathValidator - Path Sanitization\n";
        echo "─────────────────────────────────────────────────────────────────────\n";
        $allPassed = $this->testPathSanitization() && $allPassed;
        echo "\n";
        
        // Test 3: PathValidator - Filename Validation
        echo "Test 3: PathValidator - Filename Validation\n";
        echo "─────────────────────────────────────────────────────────────────────\n";
        $allPassed = $this->testFilenameValidation() && $allPassed;
        echo "\n";
        
        // Test 4: FileManagerService - Directory Listing
        echo "Test 4: FileManagerService - Directory Listing\n";
        echo "─────────────────────────────────────────────────────────────────────\n";
        $allPassed = $this->testDirectoryListing() && $allPassed;
        echo "\n";
        
        // Test 5: FileManagerService - Breadcrumb Generation
        echo "Test 5: FileManagerService - Breadcrumb Generation\n";
        echo "─────────────────────────────────────────────────────────────────────\n";
        $allPassed = $this->testBreadcrumbGeneration() && $allPassed;
        echo "\n";
        
        // Test 6: FileManagerService - Syntax Highlighting Language Detection
        echo "Test 6: FileManagerService - Syntax Highlighting Language Detection\n";
        echo "─────────────────────────────────────────────────────────────────────\n";
        $allPassed = $this->testLanguageDetection() && $allPassed;
        echo "\n";
        
        // Test 7: FileManagerService - File Creation and Reading
        echo "Test 7: FileManagerService - File Creation and Reading\n";
        echo "─────────────────────────────────────────────────────────────────────\n";
        $allPassed = $this->testFileCreationAndReading() && $allPassed;
        echo "\n";
        
        // Test 8: FileManagerService - Directory Creation
        echo "Test 8: FileManagerService - Directory Creation\n";
        echo "─────────────────────────────────────────────────────────────────────\n";
        $allPassed = $this->testDirectoryCreation() && $allPassed;
        echo "\n";
        
        // Test 9: FileManagerService - Duplicate Name Prevention
        echo "Test 9: FileManagerService - Duplicate Name Prevention\n";
        echo "─────────────────────────────────────────────────────────────────────\n";
        $allPassed = $this->testDuplicateNamePrevention() && $allPassed;
        echo "\n";
        
        // Cleanup
        $this->cleanup();
        
        // Summary
        echo "═══════════════════════════════════════════════════════════════════\n";
        if ($allPassed) {
            echo "✓ ALL FILE MANAGER BASIC TESTS PASSED\n";
        } else {
            echo "✗ SOME FILE MANAGER TESTS FAILED\n";
        }
        echo "═══════════════════════════════════════════════════════════════════\n";
        
        return $allPassed;
    }

    
    /**
     * Test 1: PathValidator - Traversal Attack Detection
     * Validates: Requirements 6.2, 6.5
     */
    private function testTraversalDetection(): bool {
        $passed = true;
        
        // Test cases that should be detected as traversal attempts
        $traversalPaths = [
            '../etc/passwd',
            '..\\windows\\system32',
            'htdocs/../../../etc/passwd',
            'htdocs/..%2f..%2f..%2fetc/passwd',
            'htdocs/..%5c..%5c..%5cwindows',
            'htdocs/%2e%2e/etc/passwd',
            "htdocs/test\0.php",
            'htdocs/%252e%252e/etc/passwd',
        ];
        
        foreach ($traversalPaths as $path) {
            $hasTraversal = $this->pathValidator->hasTraversalAttempt($path);
            if ($hasTraversal) {
                echo "  ✓ Correctly detected traversal in: " . substr($path, 0, 40) . "\n";
            } else {
                echo "  ✗ Failed to detect traversal in: $path\n";
                $passed = false;
            }
        }
        
        // Test cases that should NOT be detected as traversal
        $safePaths = [
            'htdocs/test.php',
            'htdocs/folder/file.txt',
            'htdocs/my-app/config.json',
        ];
        
        foreach ($safePaths as $path) {
            $hasTraversal = $this->pathValidator->hasTraversalAttempt($path);
            if (!$hasTraversal) {
                echo "  ✓ Correctly allowed safe path: $path\n";
            } else {
                echo "  ✗ Incorrectly flagged safe path: $path\n";
                $passed = false;
            }
        }
        
        echo $passed ? "  Result: ✓ PASSED\n" : "  Result: ✗ FAILED\n";
        return $passed;
    }
    
    /**
     * Test 2: PathValidator - Path Sanitization
     * Validates: Requirements 6.5
     */
    private function testPathSanitization(): bool {
        $passed = true;
        
        $testCases = [
            ['input' => 'htdocs\\test\\file.php', 'expected_contains' => 'htdocs/test/file.php'],
            ['input' => 'htdocs//test///file.php', 'expected_contains' => 'htdocs/test/file.php'],
            ['input' => '  htdocs/test.php  ', 'expected_contains' => 'htdocs/test.php'],
        ];
        
        foreach ($testCases as $case) {
            $sanitized = $this->pathValidator->sanitize($case['input']);
            if (strpos($sanitized, $case['expected_contains']) !== false || $sanitized === $case['expected_contains']) {
                echo "  ✓ Correctly sanitized: '{$case['input']}' -> '$sanitized'\n";
            } else {
                echo "  ✗ Sanitization failed: '{$case['input']}' -> '$sanitized' (expected to contain '{$case['expected_contains']}')\n";
                $passed = false;
            }
        }
        
        echo $passed ? "  Result: ✓ PASSED\n" : "  Result: ✗ FAILED\n";
        return $passed;
    }
    
    /**
     * Test 3: PathValidator - Filename Validation
     * Validates: Requirements 3.5
     */
    private function testFilenameValidation(): bool {
        $passed = true;
        
        // Valid filenames
        $validNames = ['test.php', 'my-file.txt', 'config_v2.json', 'README.md'];
        foreach ($validNames as $name) {
            if ($this->pathValidator->validateFilename($name)) {
                echo "  ✓ Correctly validated filename: $name\n";
            } else {
                echo "  ✗ Incorrectly rejected valid filename: $name\n";
                $passed = false;
            }
        }
        
        // Invalid filenames
        $invalidNames = ['', '..', '.', 'test/file.php', 'test\\file.php', 'file<name>.txt', 'file:name.txt'];
        foreach ($invalidNames as $name) {
            if (!$this->pathValidator->validateFilename($name)) {
                echo "  ✓ Correctly rejected invalid filename: '$name'\n";
            } else {
                echo "  ✗ Incorrectly accepted invalid filename: '$name'\n";
                $passed = false;
            }
        }
        
        echo $passed ? "  Result: ✓ PASSED\n" : "  Result: ✗ FAILED\n";
        return $passed;
    }
    
    /**
     * Test 4: FileManagerService - Directory Listing
     * Validates: Requirements 1.1, 1.3, 1.4
     */
    private function testDirectoryListing(): bool {
        $passed = true;
        
        // List the htdocs directory (should exist in XAMPP)
        $result = $this->fileManagerService->listDirectory('htdocs');
        
        if (!$result['success']) {
            echo "  ✗ Failed to list htdocs directory: " . ($result['error'] ?? 'Unknown error') . "\n";
            return false;
        }
        
        echo "  ✓ Successfully listed htdocs directory\n";
        
        // Check that items have required fields
        if (!empty($result['data']['items'])) {
            $item = $result['data']['items'][0];
            $requiredFields = ['name', 'path', 'type', 'size', 'sizeFormatted', 'modified', 'modifiedFormatted', 'icon'];
            
            foreach ($requiredFields as $field) {
                if (array_key_exists($field, $item)) {
                    echo "  ✓ Item has required field: $field\n";
                } else {
                    echo "  ✗ Item missing required field: $field\n";
                    $passed = false;
                }
            }
        }
        
        // Check breadcrumbs are included
        if (isset($result['data']['breadcrumbs']) && is_array($result['data']['breadcrumbs'])) {
            echo "  ✓ Breadcrumbs included in response\n";
        } else {
            echo "  ✗ Breadcrumbs missing from response\n";
            $passed = false;
        }
        
        echo $passed ? "  Result: ✓ PASSED\n" : "  Result: ✗ FAILED\n";
        return $passed;
    }

    
    /**
     * Test 5: FileManagerService - Breadcrumb Generation
     * Validates: Requirements 1.5
     */
    private function testBreadcrumbGeneration(): bool {
        $passed = true;
        
        // Test breadcrumb generation for various paths
        $testCases = [
            ['path' => '', 'expected_count' => 1, 'first_label' => 'XAMPP'],
            ['path' => 'htdocs', 'expected_count' => 2, 'last_label' => 'htdocs'],
            ['path' => 'htdocs/clarity', 'expected_count' => 3, 'last_label' => 'clarity'],
        ];
        
        foreach ($testCases as $case) {
            $breadcrumbs = $this->fileManagerService->getBreadcrumbs($case['path']);
            
            if (count($breadcrumbs) >= $case['expected_count']) {
                echo "  ✓ Correct breadcrumb count for '{$case['path']}': " . count($breadcrumbs) . "\n";
            } else {
                echo "  ✗ Wrong breadcrumb count for '{$case['path']}': " . count($breadcrumbs) . " (expected >= {$case['expected_count']})\n";
                $passed = false;
            }
            
            // Check first breadcrumb is always XAMPP root
            if ($breadcrumbs[0]['label'] === 'XAMPP') {
                echo "  ✓ First breadcrumb is XAMPP root\n";
            } else {
                echo "  ✗ First breadcrumb should be XAMPP, got: {$breadcrumbs[0]['label']}\n";
                $passed = false;
            }
            
            // Check last breadcrumb is marked as last
            $lastBreadcrumb = end($breadcrumbs);
            if ($lastBreadcrumb['isLast'] === true) {
                echo "  ✓ Last breadcrumb is marked as last\n";
            } else {
                echo "  ✗ Last breadcrumb should be marked as last\n";
                $passed = false;
            }
        }
        
        echo $passed ? "  Result: ✓ PASSED\n" : "  Result: ✗ FAILED\n";
        return $passed;
    }
    
    /**
     * Test 6: FileManagerService - Syntax Highlighting Language Detection
     * Validates: Requirements 2.2
     */
    private function testLanguageDetection(): bool {
        $passed = true;
        
        $testCases = [
            ['filename' => 'test.php', 'expected' => 'php'],
            ['filename' => 'script.js', 'expected' => 'javascript'],
            ['filename' => 'style.css', 'expected' => 'css'],
            ['filename' => 'page.html', 'expected' => 'html'],
            ['filename' => 'data.json', 'expected' => 'json'],
            ['filename' => 'query.sql', 'expected' => 'sql'],
            ['filename' => 'config.xml', 'expected' => 'xml'],
            ['filename' => 'README.md', 'expected' => 'markdown'],
            ['filename' => 'unknown.xyz', 'expected' => 'plaintext'],
        ];
        
        foreach ($testCases as $case) {
            $language = $this->fileManagerService->getLanguageForSyntaxHighlight($case['filename']);
            if ($language === $case['expected']) {
                echo "  ✓ Correct language for {$case['filename']}: $language\n";
            } else {
                echo "  ✗ Wrong language for {$case['filename']}: $language (expected {$case['expected']})\n";
                $passed = false;
            }
        }
        
        echo $passed ? "  Result: ✓ PASSED\n" : "  Result: ✗ FAILED\n";
        return $passed;
    }
    
    /**
     * Test 7: FileManagerService - File Creation and Reading
     * Validates: Requirements 3.2, 2.1
     */
    private function testFileCreationAndReading(): bool {
        $passed = true;
        
        // First create a test directory
        $createDirResult = $this->fileManagerService->createDirectory('htdocs', '_filemanager_test_' . time());
        if (!$createDirResult['success']) {
            echo "  ✗ Failed to create test directory: " . ($createDirResult['error'] ?? 'Unknown error') . "\n";
            return false;
        }
        
        $this->testDir = $createDirResult['data']['path'];
        echo "  ✓ Created test directory: {$this->testDir}\n";
        
        // Create a test file
        $testContent = "<?php\necho 'Hello World';\n// Test file created at " . date('Y-m-d H:i:s');
        $createResult = $this->fileManagerService->createFile($this->testDir, 'test_file.php', $testContent);
        
        if (!$createResult['success']) {
            echo "  ✗ Failed to create test file: " . ($createResult['error'] ?? 'Unknown error') . "\n";
            return false;
        }
        
        echo "  ✓ Created test file: {$createResult['data']['path']}\n";
        
        // Read the file back
        $readResult = $this->fileManagerService->readFile($createResult['data']['path']);
        
        if (!$readResult['success']) {
            echo "  ✗ Failed to read test file: " . ($readResult['error'] ?? 'Unknown error') . "\n";
            return false;
        }
        
        // Verify content matches
        if ($readResult['data']['content'] === $testContent) {
            echo "  ✓ File content matches original\n";
        } else {
            echo "  ✗ File content does not match original\n";
            $passed = false;
        }
        
        // Verify metadata
        if ($readResult['data']['language'] === 'php') {
            echo "  ✓ Correct language detected: php\n";
        } else {
            echo "  ✗ Wrong language detected: {$readResult['data']['language']}\n";
            $passed = false;
        }
        
        echo $passed ? "  Result: ✓ PASSED\n" : "  Result: ✗ FAILED\n";
        return $passed;
    }
    
    /**
     * Test 8: FileManagerService - Directory Creation
     * Validates: Requirements 3.4
     */
    private function testDirectoryCreation(): bool {
        $passed = true;
        
        if (empty($this->testDir)) {
            echo "  ✗ Test directory not available\n";
            return false;
        }
        
        // Create a subdirectory
        $createResult = $this->fileManagerService->createDirectory($this->testDir, 'subdir_test');
        
        if (!$createResult['success']) {
            echo "  ✗ Failed to create subdirectory: " . ($createResult['error'] ?? 'Unknown error') . "\n";
            return false;
        }
        
        echo "  ✓ Created subdirectory: {$createResult['data']['path']}\n";
        
        // Verify it appears in directory listing
        $listResult = $this->fileManagerService->listDirectory($this->testDir);
        
        if (!$listResult['success']) {
            echo "  ✗ Failed to list test directory\n";
            return false;
        }
        
        $found = false;
        foreach ($listResult['data']['items'] as $item) {
            if ($item['name'] === 'subdir_test' && $item['type'] === 'directory') {
                $found = true;
                break;
            }
        }
        
        if ($found) {
            echo "  ✓ Subdirectory appears in listing\n";
        } else {
            echo "  ✗ Subdirectory not found in listing\n";
            $passed = false;
        }
        
        echo $passed ? "  Result: ✓ PASSED\n" : "  Result: ✗ FAILED\n";
        return $passed;
    }
    
    /**
     * Test 9: FileManagerService - Duplicate Name Prevention
     * Validates: Requirements 3.5
     */
    private function testDuplicateNamePrevention(): bool {
        $passed = true;
        
        if (empty($this->testDir)) {
            echo "  ✗ Test directory not available\n";
            return false;
        }
        
        // Try to create a file with the same name as existing file
        $duplicateResult = $this->fileManagerService->createFile($this->testDir, 'test_file.php', 'duplicate content');
        
        if (!$duplicateResult['success'] && $duplicateResult['code'] === 'FILE_EXISTS') {
            echo "  ✓ Correctly prevented duplicate file creation\n";
        } else {
            echo "  ✗ Should have prevented duplicate file creation\n";
            $passed = false;
        }
        
        // Try to create a directory with the same name as existing directory
        $duplicateDirResult = $this->fileManagerService->createDirectory($this->testDir, 'subdir_test');
        
        if (!$duplicateDirResult['success'] && $duplicateDirResult['code'] === 'FILE_EXISTS') {
            echo "  ✓ Correctly prevented duplicate directory creation\n";
        } else {
            echo "  ✗ Should have prevented duplicate directory creation\n";
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
            echo "Cleaning up test directory: {$this->testDir}\n";
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
    $test = new FileManagerBasicTest();
    $result = $test->runAllTests();
    exit($result ? 0 : 1);
}
