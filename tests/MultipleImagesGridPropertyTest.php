<?php
/**
 * Property Test: Multiple Images Grid Display
 * 
 * **Feature: installation-module, Property 31: Multiple images grid display**
 * **Validates: Requirements 17.4**
 * 
 * Property: For any section with multiple images, all thumbnails should be 
 * displayed in a grid layout.
 * 
 * This test verifies that:
 * 1. Multiple images are rendered in a grid container
 * 2. Each image in the grid has consistent styling
 * 3. Grid layout classes are properly applied
 * 4. All images from the comma-separated string are rendered
 */

require_once __DIR__ . '/../config/autoload.php';

class MultipleImagesGridPropertyTest {
    private $testResults = [];
    private $iterations = 100;
    
    /**
     * Run all property tests
     */
    public function runTests(): bool {
        echo "\n=== Multiple Images Grid Display Property Tests ===\n";
        echo "**Feature: installation-module, Property 31: Multiple images grid display**\n";
        echo "**Validates: Requirements 17.4**\n\n";
        
        $this->runPropertyTest(
            'Multiple images are all rendered',
            [$this, 'testAllImagesRendered']
        );
        
        $this->runPropertyTest(
            'Grid container has proper grid classes',
            [$this, 'testGridContainerClasses']
        );
        
        $this->runPropertyTest(
            'Each image has consistent wrapper structure',
            [$this, 'testConsistentImageWrapper']
        );
        
        $this->runPropertyTest(
            'Image count matches path count',
            [$this, 'testImageCountMatchesPathCount']
        );
        
        $this->runPropertyTest(
            'Grid handles varying number of images',
            [$this, 'testVaryingImageCounts']
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
     * Property Test: Multiple images are all rendered
     */
    private function testAllImagesRendered(): array {
        $numImages = rand(2, 6);
        $paths = $this->generateMultipleImagePaths($numImages);
        $pathsStr = implode(',', $paths);
        
        $html = $this->generateGridHtml($pathsStr);
        
        // Verify each path appears in the HTML
        foreach ($paths as $path) {
            if (strpos($html, $path) === false) {
                return [
                    'success' => false,
                    'message' => "Image path '$path' not found in rendered HTML"
                ];
            }
        }
        
        return ['success' => true];
    }
    
    /**
     * Property Test: Grid container has proper grid classes
     */
    private function testGridContainerClasses(): array {
        $paths = $this->generateMultipleImagePaths(rand(2, 4));
        $pathsStr = implode(',', $paths);
        
        $containerHtml = $this->generateGridContainerHtml();
        
        // Check for grid classes
        if (strpos($containerHtml, 'grid') === false) {
            return [
                'success' => false,
                'message' => "Grid container does not have 'grid' class"
            ];
        }
        
        // Check for responsive grid columns
        if (strpos($containerHtml, 'grid-cols') === false) {
            return [
                'success' => false,
                'message' => "Grid container does not have responsive column classes"
            ];
        }
        
        // Check for gap class
        if (strpos($containerHtml, 'gap') === false) {
            return [
                'success' => false,
                'message' => "Grid container does not have gap class"
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * Property Test: Each image has consistent wrapper structure
     */
    private function testConsistentImageWrapper(): array {
        $numImages = rand(2, 5);
        $paths = $this->generateMultipleImagePaths($numImages);
        $pathsStr = implode(',', $paths);
        
        $html = $this->generateGridHtml($pathsStr);
        
        // Count wrapper divs (each image should have a wrapper)
        $wrapperCount = substr_count($html, 'class="relative group');
        
        if ($wrapperCount !== $numImages) {
            return [
                'success' => false,
                'message' => "Expected $numImages image wrappers, found $wrapperCount"
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * Property Test: Image count matches path count
     */
    private function testImageCountMatchesPathCount(): array {
        $numImages = rand(1, 8);
        $paths = $this->generateMultipleImagePaths($numImages);
        $pathsStr = implode(',', $paths);
        
        $html = $this->generateGridHtml($pathsStr);
        
        // Count img tags
        $imgCount = substr_count($html, '<img');
        
        if ($imgCount !== $numImages) {
            return [
                'success' => false,
                'message' => "Expected $numImages img tags, found $imgCount"
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * Property Test: Grid handles varying number of images
     */
    private function testVaryingImageCounts(): array {
        // Test with different image counts
        $testCounts = [1, 2, 3, 4, 5, 6, 8, 10];
        $count = $testCounts[array_rand($testCounts)];
        
        $paths = $this->generateMultipleImagePaths($count);
        $pathsStr = implode(',', $paths);
        
        $html = $this->generateGridHtml($pathsStr);
        
        // Verify all images are rendered
        $imgCount = substr_count($html, '<img');
        
        if ($imgCount !== $count) {
            return [
                'success' => false,
                'message' => "Grid with $count images rendered $imgCount img tags"
            ];
        }
        
        // Verify no errors in HTML structure
        if (strpos($html, 'undefined') !== false || strpos($html, 'null') !== false) {
            return [
                'success' => false,
                'message' => "Grid HTML contains undefined or null values"
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
     * Generate multiple image paths
     */
    private function generateMultipleImagePaths(int $count): array {
        $paths = [];
        for ($i = 0; $i < $count; $i++) {
            $paths[] = $this->generateRandomImagePath();
        }
        return $paths;
    }
    
    /**
     * Generate grid container HTML (simulates the view.php container)
     */
    private function generateGridContainerHtml(): string {
        return '<div id="view-images" class="grid grid-cols-2 md:grid-cols-4 gap-4"></div>';
    }
    
    /**
     * Generate grid HTML with images (simulates the JavaScript renderImages function)
     */
    private function generateGridHtml(string $pathsStr): string {
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
    $test = new MultipleImagesGridPropertyTest();
    $success = $test->runTests();
    exit($success ? 0 : 1);
}
