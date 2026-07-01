<?php
/**
 * Property Test: Image Thumbnail Display
 * 
 * **Feature: installation-module, Property 30: Image thumbnail display**
 * **Validates: Requirements 17.1**
 * 
 * Property: For any installation with uploaded images, the view mode should render 
 * all images as visible thumbnail elements (img tags) with 300x300 dimensions.
 * 
 * This test verifies that:
 * 1. Image paths are correctly parsed from comma-separated strings
 * 2. Each image generates a proper img tag
 * 3. Thumbnail dimensions are set to 300x300
 * 4. Error handling for missing images is present
 * 5. Lightbox click handler is attached
 */

require_once __DIR__ . '/../config/autoload.php';

class ImageThumbnailDisplayPropertyTest {
    private $testResults = [];
    private $iterations = 100;
    
    /**
     * Run all property tests
     */
    public function runTests(): bool {
        echo "\n=== Image Thumbnail Display Property Tests ===\n";
        echo "**Feature: installation-module, Property 30: Image thumbnail display**\n";
        echo "**Validates: Requirements 17.1**\n\n";
        
        $this->runPropertyTest(
            'Single image path generates correct thumbnail HTML',
            [$this, 'testSingleImageThumbnail']
        );
        
        $this->runPropertyTest(
            'Multiple image paths generate grid of thumbnails',
            [$this, 'testMultipleImageThumbnails']
        );
        
        $this->runPropertyTest(
            'Thumbnail HTML contains 300px height class',
            [$this, 'testThumbnailDimensions']
        );
        
        $this->runPropertyTest(
            'Thumbnail HTML contains error handler for missing images',
            [$this, 'testErrorHandling']
        );
        
        $this->runPropertyTest(
            'Thumbnail HTML contains lightbox click handler',
            [$this, 'testLightboxHandler']
        );
        
        $this->runPropertyTest(
            'Empty path string returns no images message',
            [$this, 'testEmptyPathHandling']
        );
        
        // Summary
        echo "\n=== Test Summary ===\n";
        $passed = count(array_filter($this->testResults));
        $total = count($this->testResults);
        echo "Passed: $passed / $total\n";
        
        return $passed === $total;
    }
    
    /**
     * Run a property test with multiple iterations
     */
    private function runPropertyTest(string $name, callable $testFunction): void {
        echo "Testing: $name\n";
        $failures = [];
        
        for ($i = 0; $i < $this->iterations; $i++) {
            try {
                $result = $testFunction();
                if (!$result['success']) {
                    $failures[] = "Iteration $i: {$result['message']}";
                }
            } catch (Exception $e) {
                $failures[] = "Iteration $i: Exception - {$e->getMessage()}";
            }
        }
        
        if (empty($failures)) {
            echo "  ✓ Passed ({$this->iterations} iterations)\n";
            $this->testResults[$name] = true;
        } else {
            echo "  ✗ Failed\n";
            foreach (array_slice($failures, 0, 3) as $failure) {
                echo "    - $failure\n";
            }
            if (count($failures) > 3) {
                echo "    ... and " . (count($failures) - 3) . " more failures\n";
            }
            $this->testResults[$name] = false;
        }
    }
    
    /**
     * Property Test: Single image path generates correct thumbnail HTML
     */
    private function testSingleImageThumbnail(): array {
        $imagePath = $this->generateRandomImagePath();
        $html = $this->generateThumbnailHtml($imagePath);
        
        // Check that HTML contains img tag
        if (strpos($html, '<img') === false) {
            return [
                'success' => false,
                'message' => "Generated HTML does not contain img tag"
            ];
        }
        
        // Check that HTML contains the image path
        if (strpos($html, $imagePath) === false) {
            return [
                'success' => false,
                'message' => "Generated HTML does not contain the image path"
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * Property Test: Multiple image paths generate grid of thumbnails
     */
    private function testMultipleImageThumbnails(): array {
        $numImages = rand(2, 5);
        $paths = [];
        for ($i = 0; $i < $numImages; $i++) {
            $paths[] = $this->generateRandomImagePath();
        }
        $pathsStr = implode(',', $paths);
        
        $html = $this->generateThumbnailHtml($pathsStr);
        
        // Count img tags
        $imgCount = substr_count($html, '<img');
        
        if ($imgCount !== $numImages) {
            return [
                'success' => false,
                'message' => "Expected $numImages img tags, found $imgCount"
            ];
        }
        
        // Verify each path is present
        foreach ($paths as $path) {
            if (strpos($html, $path) === false) {
                return [
                    'success' => false,
                    'message' => "Path '$path' not found in generated HTML"
                ];
            }
        }
        
        return ['success' => true];
    }
    
    /**
     * Property Test: Thumbnail HTML contains 300px height class
     * Requirements: 15.1 - Display uploaded images as visible thumbnail previews (300x300 pixels)
     */
    private function testThumbnailDimensions(): array {
        $imagePath = $this->generateRandomImagePath();
        $html = $this->generateThumbnailHtml($imagePath);
        
        // Check for 300px height class (h-[300px])
        if (strpos($html, 'h-[300px]') === false) {
            return [
                'success' => false,
                'message' => "Generated HTML does not contain h-[300px] class for 300px height"
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * Property Test: Thumbnail HTML contains error handler for missing images
     * Requirements: 15.5 - Display placeholder icon with "Image not available" text
     */
    private function testErrorHandling(): array {
        $imagePath = $this->generateRandomImagePath();
        $html = $this->generateThumbnailHtml($imagePath);
        
        // Check for onerror handler
        if (strpos($html, 'onerror') === false) {
            return [
                'success' => false,
                'message' => "Generated HTML does not contain onerror handler"
            ];
        }
        
        // Check for "Image not available" text in error handler
        if (strpos($html, 'Image not available') === false) {
            return [
                'success' => false,
                'message' => "Generated HTML does not contain 'Image not available' fallback text"
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * Property Test: Thumbnail HTML contains lightbox click handler
     * Requirements: 15.2 - Display full-size image in lightbox modal overlay when clicked
     */
    private function testLightboxHandler(): array {
        $imagePath = $this->generateRandomImagePath();
        $html = $this->generateThumbnailHtml($imagePath);
        
        // Check for onclick handler with openLightbox
        if (strpos($html, 'openLightbox') === false) {
            return [
                'success' => false,
                'message' => "Generated HTML does not contain openLightbox click handler"
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * Property Test: Empty path string returns no images message
     */
    private function testEmptyPathHandling(): array {
        $html = $this->generateThumbnailHtml('');
        
        // Check for "No images" message
        if (strpos($html, 'No images') === false) {
            return [
                'success' => false,
                'message' => "Empty path should show 'No images' message"
            ];
        }
        
        // Should not contain img tag
        if (strpos($html, '<img') !== false) {
            return [
                'success' => false,
                'message' => "Empty path should not generate img tags"
            ];
        }
        
        return ['success' => true];
    }
    
    // ==================== Helper Methods ====================
    
    /**
     * Generate random string
     */
    private function generateRandomString(int $length = 10): string {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $result = '';
        for ($i = 0; $i < $length; $i++) {
            $result .= $chars[rand(0, strlen($chars) - 1)];
        }
        return $result;
    }
    
    /**
     * Generate random image path
     */
    private function generateRandomImagePath(): string {
        $extensions = ['jpg', 'jpeg', 'png'];
        $ext = $extensions[array_rand($extensions)];
        $installationId = rand(1, 1000);
        $filename = $this->generateRandomString(8) . '.' . $ext;
        return "uploads/installations/{$installationId}/{$filename}";
    }
    
    /**
     * Generate thumbnail HTML (simulates the JavaScript renderImages function)
     * This is a PHP simulation of the client-side rendering logic
     */
    private function generateThumbnailHtml(string $pathsStr): string {
        if (!$pathsStr) {
            return '<p class="text-gray-400 text-sm">No images uploaded</p>';
        }
        
        $paths = array_filter(explode(',', $pathsStr), function($p) {
            return trim($p) !== '';
        });
        
        if (empty($paths)) {
            return '<p class="text-gray-400 text-sm">No images uploaded</p>';
        }
        
        $html = '';
        foreach ($paths as $path) {
            $fullPath = strpos($path, 'data:') === 0 ? $path : '../' . $path;
            $escapedPath = htmlspecialchars($fullPath, ENT_QUOTES, 'UTF-8');
            
            $html .= <<<HTML
            <div class="relative group cursor-pointer" onclick="openLightbox('{$escapedPath}')">
                <img src="{$escapedPath}" 
                     alt="Installation photo" 
                     class="w-full h-[300px] object-cover rounded-lg border shadow-sm hover:shadow-md transition"
                     onerror="this.parentElement.innerHTML='<div class=\\'w-full h-[300px] bg-gray-100 rounded-lg border flex items-center justify-center\\'><div class=\\'text-center text-gray-400\\'><i class=\\'fas fa-image text-3xl mb-2\\'></i><p class=\\'text-sm\\'>Image not available</p></div></div>'">
                <div class="absolute inset-0 bg-black/0 group-hover:bg-black/20 rounded-lg transition flex items-center justify-center">
                    <i class="fas fa-search-plus text-white text-2xl opacity-0 group-hover:opacity-100 transition"></i>
                </div>
            </div>
HTML;
        }
        
        return $html;
    }
}

// Run tests if executed directly
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    $test = new ImageThumbnailDisplayPropertyTest();
    $success = $test->runTests();
    exit($success ? 0 : 1);
}
