<?php
/**
 * Property Test for Installation Image Upload Operations
 * **Feature: installation-module, Property 15: Image upload validation**
 * **Validates: Requirements 6.4, 13.4**
 */

require_once 'PropertyTestBase.php';
require_once __DIR__ . '/../services/InstallationImageService.php';

class InstallationImageUploadPropertyTest extends PropertyTestBase {
    
    private $installationImageService;
    private $testDir;
    private $createdFiles = [];
    
    public function __construct() {
        parent::__construct();
        $this->installationImageService = new InstallationImageService();
        $this->testDir = __DIR__ . '/test_installation_images/';
        
        // Create test directory
        if (!is_dir($this->testDir)) {
            mkdir($this->testDir, 0755, true);
        }
    }
    
    /**
     * Create a test image file
     * Uses minimal valid image data without requiring GD library
     */
    private function createTestImage(string $type = 'jpeg', int $width = 100, int $height = 100): string {
        $filename = $this->testDir . 'test_' . uniqid() . '.' . ($type === 'jpeg' ? 'jpg' : $type);
        
        if ($type === 'jpeg') {
            // Minimal valid JPEG (1x1 pixel red image)
            $jpegData = base64_decode(
                '/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRof' .
                'Hh0aHBwgJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPC4zNDL/2wBDAQkJCQwLDBgNDRgyIRwh' .
                'MjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjL/wAAR' .
                'CAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAn/xAAUEAEAAAAAAAAAAAAAAAAA' .
                'AAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMB' .
                'AAIRAxEAPwCwAB//2Q=='
            );
            file_put_contents($filename, $jpegData);
        } elseif ($type === 'png') {
            // Minimal valid PNG (1x1 pixel transparent image)
            $pngData = base64_decode(
                'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='
            );
            file_put_contents($filename, $pngData);
        }
        
        $this->createdFiles[] = $filename;
        
        return $filename;
    }
    
    /**
     * Create a test file with invalid content
     */
    private function createInvalidFile(string $extension): string {
        $filename = $this->testDir . 'invalid_' . uniqid() . '.' . $extension;
        file_put_contents($filename, 'This is not an image file content');
        $this->createdFiles[] = $filename;
        return $filename;
    }
    
    /**
     * Create a large test file
     * Creates a file larger than the specified size
     */
    private function createLargeFile(int $sizeInMB): string {
        $filename = $this->testDir . 'large_' . uniqid() . '.jpg';
        
        // Start with a valid JPEG header
        $jpegData = base64_decode(
            '/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRof' .
            'Hh0aHBwgJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPC4zNDL/2wBDAQkJCQwLDBgNDRgyIRwh' .
            'MjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjL/wAAR' .
            'CAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAn/xAAUEAEAAAAAAAAAAAAAAAAA' .
            'AAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMB' .
            'AAIRAxEAPwCwAB//2Q=='
        );
        file_put_contents($filename, $jpegData);
        
        // Append data to make it larger
        $handle = fopen($filename, 'a');
        $bytesToAdd = ($sizeInMB * 1024 * 1024) - filesize($filename);
        if ($bytesToAdd > 0) {
            // Write in chunks to avoid memory issues
            $chunkSize = 1024 * 1024; // 1MB chunks
            while ($bytesToAdd > 0) {
                $writeSize = min($chunkSize, $bytesToAdd);
                fwrite($handle, str_repeat("\0", $writeSize));
                $bytesToAdd -= $writeSize;
            }
        }
        fclose($handle);
        
        $this->createdFiles[] = $filename;
        return $filename;
    }
    
    /**
     * Simulate a file upload array
     */
    private function simulateFileUpload(string $filePath, ?string $overrideName = null): array {
        $filename = $overrideName ?? basename($filePath);
        return [
            'name' => $filename,
            'type' => mime_content_type($filePath),
            'tmp_name' => $filePath,
            'error' => UPLOAD_ERR_OK,
            'size' => filesize($filePath)
        ];
    }
    
    public function runTests(): bool {
        echo "=== Installation Image Upload Property Tests ===\n\n";
        
        $allPassed = true;
        
        // Property 15: Image upload validation
        $allPassed &= $this->runPropertyTest(
            "Property 15: Valid JPEG images are accepted for installation",
            [$this, 'testValidJPEGImagesAccepted']
        );
        
        $allPassed &= $this->runPropertyTest(
            "Property 15: Valid PNG images are accepted for installation",
            [$this, 'testValidPNGImagesAccepted']
        );
        
        $allPassed &= $this->runPropertyTest(
            "Property 15: Invalid file types are rejected for installation",
            [$this, 'testInvalidFileTypesRejected']
        );
        
        $allPassed &= $this->runPropertyTest(
            "Property 15: Files exceeding 5MB are rejected for installation",
            [$this, 'testLargeFilesRejected'],
            10 // Fewer iterations for large file tests
        );
        
        // Cleanup
        $this->cleanupTestData();
        
        return $allPassed;
    }
    
    /**
     * Property 15: Valid JPEG images are accepted
     * **Feature: installation-module, Property 15: Image upload validation**
     * **Validates: Requirements 6.4, 13.4**
     */
    public function testValidJPEGImagesAccepted(): array {
        try {
            // Create a valid JPEG image
            $imagePath = $this->createTestImage('jpeg', rand(50, 500), rand(50, 500));
            $file = $this->simulateFileUpload($imagePath);
            
            // Validate the image
            $result = $this->installationImageService->validateImage($file);
            
            $this->assert(
                $result['isValid'],
                "Valid JPEG image should be accepted: " . ($result['message'] ?? '')
            );
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Property 15: Valid PNG images are accepted
     * **Feature: installation-module, Property 15: Image upload validation**
     * **Validates: Requirements 6.4, 13.4**
     */
    public function testValidPNGImagesAccepted(): array {
        try {
            // Create a valid PNG image
            $imagePath = $this->createTestImage('png', rand(50, 500), rand(50, 500));
            $file = $this->simulateFileUpload($imagePath);
            
            // Validate the image
            $result = $this->installationImageService->validateImage($file);
            
            $this->assert(
                $result['isValid'],
                "Valid PNG image should be accepted: " . ($result['message'] ?? '')
            );
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Property 15: Invalid file types are rejected
     * **Feature: installation-module, Property 15: Image upload validation**
     * **Validates: Requirements 6.4, 13.4**
     */
    public function testInvalidFileTypesRejected(): array {
        try {
            // Create a file with invalid extension
            $invalidExtensions = ['txt', 'pdf', 'doc', 'exe', 'gif', 'bmp', 'webp'];
            $extension = $this->generateRandomChoice($invalidExtensions);
            
            $filePath = $this->createInvalidFile($extension);
            $file = $this->simulateFileUpload($filePath);
            
            // Validate the image
            $result = $this->installationImageService->validateImage($file);
            
            $this->assert(
                !$result['isValid'],
                "Invalid file type ({$extension}) should be rejected"
            );
            
            // Verify error code
            $hasCorrectError = false;
            if (!empty($result['errors'])) {
                foreach ($result['errors'] as $error) {
                    if ($error['code'] === 'INVALID_FILE_TYPE' || 
                        $error['code'] === 'INVALID_MIME_TYPE' ||
                        $error['code'] === 'INVALID_IMAGE') {
                        $hasCorrectError = true;
                        break;
                    }
                }
            }
            
            $this->assert(
                $hasCorrectError,
                "Error should indicate invalid file type"
            );
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Property 15: Files exceeding 5MB are rejected
     * **Feature: installation-module, Property 15: Image upload validation**
     * **Validates: Requirements 6.4, 13.4**
     */
    public function testLargeFilesRejected(): array {
        try {
            // Create a file larger than 5MB
            $sizeInMB = rand(6, 10);
            $filePath = $this->createLargeFile($sizeInMB);
            
            // Verify file was created with correct size
            clearstatcache(true, $filePath);
            $actualSize = filesize($filePath);
            $expectedMinSize = 5 * 1024 * 1024; // 5MB
            
            $this->assert(
                $actualSize > $expectedMinSize,
                "Test file should be larger than 5MB. Actual: " . ($actualSize / 1024 / 1024) . "MB"
            );
            
            $file = $this->simulateFileUpload($filePath);
            
            // Validate the image
            $result = $this->installationImageService->validateImage($file);
            
            $this->assert(
                !$result['isValid'],
                "File exceeding 5MB ({$sizeInMB}MB) should be rejected"
            );
            
            // Verify error code
            $hasCorrectError = false;
            if (!empty($result['errors'])) {
                foreach ($result['errors'] as $error) {
                    if ($error['code'] === 'FILE_TOO_LARGE') {
                        $hasCorrectError = true;
                        break;
                    }
                }
            }
            
            $this->assert(
                $hasCorrectError,
                "Error should indicate file is too large"
            );
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Clean up test data
     */
    protected function cleanupTestData(): void {
        // Delete created test files
        foreach ($this->createdFiles as $file) {
            if (file_exists($file)) {
                @unlink($file);
            }
        }
        $this->createdFiles = [];
        
        // Remove test directory if empty
        if (is_dir($this->testDir)) {
            $files = scandir($this->testDir);
            if (count($files) <= 2) { // Only . and ..
                @rmdir($this->testDir);
            }
        }
    }
}
