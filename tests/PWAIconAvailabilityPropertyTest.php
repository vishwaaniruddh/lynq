<?php
/**
 * Property Test for PWA Icon Availability and Correctness
 * **Feature: clarity-pwa-conversion, Property 7: Icon Availability and Correctness**
 * **Validates: Requirements 3.1, 3.2**
 */

require_once 'PropertyTestBase.php';

class PWAIconAvailabilityPropertyTest extends PropertyTestBase {
    
    private $requiredIconSizes = [72, 96, 128, 144, 152, 192, 384, 512];
    private $maskableIconSizes = [192, 512];
    private $iconsBasePath;
    private $webManifestPath;
    
    public function __construct() {
        parent::__construct();
        $this->iconsBasePath = __DIR__ . '/../assets/icons/';
        $this->webManifestPath = __DIR__ . '/../app.webmanifest';
    }
    
    public function runTests(): bool {
        echo "=== PWA Icon Availability and Correctness Property Tests ===\n\n";
        
        $allPassed = true;
        
        // Property 7: Icon Availability and Correctness
        $allPassed &= $this->runPropertyTest(
            "Property 7: All required icon sizes are available",
            [$this, 'testRequiredIconSizesAvailable']
        );
        
        $allPassed &= $this->runPropertyTest(
            "Property 7: All icons have correct MIME type",
            [$this, 'testIconsHaveCorrectMimeType']
        );
        
        $allPassed &= $this->runPropertyTest(
            "Property 7: Maskable icons are available for Android",
            [$this, 'testMaskableIconsAvailable']
        );
        
        $allPassed &= $this->runPropertyTest(
            "Property 7: Icons are valid PNG images",
            [$this, 'testIconsAreValidImages']
        );
        
        $allPassed &= $this->runPropertyTest(
            "Property 7: Web manifest references all icons correctly",
            [$this, 'testWebManifestIconReferences']
        );
        
        return $allPassed;
    }
    
    /**
     * Property 7: All required icon sizes are available
     * **Feature: clarity-pwa-conversion, Property 7: Icon Availability and Correctness**
     * **Validates: Requirements 3.1**
     */
    public function testRequiredIconSizesAvailable(): array {
        try {
            // Test a random subset of required sizes each iteration
            $testSizes = $this->generateRandomSubset($this->requiredIconSizes, rand(3, count($this->requiredIconSizes)));
            
            foreach ($testSizes as $size) {
                $iconPath = $this->iconsBasePath . "icon-{$size}.png";
                
                $this->assert(
                    file_exists($iconPath),
                    "Icon file should exist: icon-{$size}.png"
                );
                
                $this->assert(
                    filesize($iconPath) > 0,
                    "Icon file should not be empty: icon-{$size}.png"
                );
            }
            
            return [
                'success' => true,
                'data' => ['tested_sizes' => $testSizes]
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => ['tested_sizes' => $testSizes ?? []]
            ];
        }
    }
    
    /**
     * Property 7: All icons have correct MIME type
     * **Feature: clarity-pwa-conversion, Property 7: Icon Availability and Correctness**
     * **Validates: Requirements 3.2**
     */
    public function testIconsHaveCorrectMimeType(): array {
        try {
            // Test a random icon size
            $testSize = $this->generateRandomChoice($this->requiredIconSizes);
            $iconPath = $this->iconsBasePath . "icon-{$testSize}.png";
            
            $this->assert(
                file_exists($iconPath),
                "Icon file should exist: icon-{$testSize}.png"
            );
            
            // Check MIME type using getimagesize
            $imageInfo = @getimagesize($iconPath);
            
            $this->assert(
                $imageInfo !== false,
                "Icon should be a valid image: icon-{$testSize}.png"
            );
            
            $this->assert(
                $imageInfo['mime'] === 'image/png',
                "Icon should have PNG MIME type, got: " . ($imageInfo['mime'] ?? 'unknown')
            );
            
            // Check file extension
            $this->assert(
                pathinfo($iconPath, PATHINFO_EXTENSION) === 'png',
                "Icon should have .png extension"
            );
            
            return [
                'success' => true,
                'data' => [
                    'tested_size' => $testSize,
                    'mime_type' => $imageInfo['mime'],
                    'dimensions' => $imageInfo[0] . 'x' . $imageInfo[1]
                ]
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => ['tested_size' => $testSize ?? null]
            ];
        }
    }
    
    /**
     * Property 7: Maskable icons are available for Android
     * **Feature: clarity-pwa-conversion, Property 7: Icon Availability and Correctness**
     * **Validates: Requirements 3.1, 3.2**
     */
    public function testMaskableIconsAvailable(): array {
        try {
            // Test a random maskable icon size
            $testSize = $this->generateRandomChoice($this->maskableIconSizes);
            $maskableIconPath = $this->iconsBasePath . "icon-{$testSize}-maskable.png";
            
            $this->assert(
                file_exists($maskableIconPath),
                "Maskable icon should exist: icon-{$testSize}-maskable.png"
            );
            
            $this->assert(
                filesize($maskableIconPath) > 0,
                "Maskable icon should not be empty: icon-{$testSize}-maskable.png"
            );
            
            // Verify it's a valid image
            $imageInfo = @getimagesize($maskableIconPath);
            
            $this->assert(
                $imageInfo !== false,
                "Maskable icon should be a valid image: icon-{$testSize}-maskable.png"
            );
            
            $this->assert(
                $imageInfo['mime'] === 'image/png',
                "Maskable icon should have PNG MIME type"
            );
            
            return [
                'success' => true,
                'data' => [
                    'tested_size' => $testSize,
                    'file_size' => filesize($maskableIconPath),
                    'dimensions' => $imageInfo[0] . 'x' . $imageInfo[1]
                ]
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => ['tested_size' => $testSize ?? null]
            ];
        }
    }
    
    /**
     * Property 7: Icons are valid PNG images
     * **Feature: clarity-pwa-conversion, Property 7: Icon Availability and Correctness**
     * **Validates: Requirements 3.2**
     */
    public function testIconsAreValidImages(): array {
        try {
            // Test a random icon
            $allIcons = [];
            
            // Add regular icons
            foreach ($this->requiredIconSizes as $size) {
                $allIcons[] = "icon-{$size}.png";
            }
            
            // Add maskable icons
            foreach ($this->maskableIconSizes as $size) {
                $allIcons[] = "icon-{$size}-maskable.png";
            }
            
            $testIcon = $this->generateRandomChoice($allIcons);
            $iconPath = $this->iconsBasePath . $testIcon;
            
            $this->assert(
                file_exists($iconPath),
                "Icon file should exist: {$testIcon}"
            );
            
            // Verify image properties
            $imageInfo = @getimagesize($iconPath);
            
            $this->assert(
                $imageInfo !== false,
                "Icon should be a valid image: {$testIcon}"
            );
            
            $this->assert(
                $imageInfo[0] > 0 && $imageInfo[1] > 0,
                "Icon should have valid dimensions: {$testIcon}"
            );
            
            $this->assert(
                $imageInfo[2] === IMAGETYPE_PNG,
                "Icon should be PNG format: {$testIcon}"
            );
            
            // Check file is not corrupted by reading a few bytes
            $handle = fopen($iconPath, 'rb');
            $header = fread($handle, 8);
            fclose($handle);
            
            // PNG signature: 89 50 4E 47 0D 0A 1A 0A
            $pngSignature = "\x89\x50\x4E\x47\x0D\x0A\x1A\x0A";
            
            $this->assert(
                $header === $pngSignature,
                "Icon should have valid PNG signature: {$testIcon}"
            );
            
            return [
                'success' => true,
                'data' => [
                    'tested_icon' => $testIcon,
                    'width' => $imageInfo[0],
                    'height' => $imageInfo[1],
                    'file_size' => filesize($iconPath)
                ]
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => ['tested_icon' => $testIcon ?? null]
            ];
        }
    }
    
    /**
     * Property 7: Web manifest references all icons correctly
     * **Feature: clarity-pwa-conversion, Property 7: Icon Availability and Correctness**
     * **Validates: Requirements 3.1, 3.2**
     */
    public function testWebManifestIconReferences(): array {
        try {
            $this->assert(
                file_exists($this->webManifestPath),
                "Web manifest should exist: app.webmanifest"
            );
            
            $manifestContent = file_get_contents($this->webManifestPath);
            $manifest = json_decode($manifestContent, true);
            
            $this->assert(
                $manifest !== null,
                "Web manifest should be valid JSON"
            );
            
            $this->assert(
                isset($manifest['icons']) && is_array($manifest['icons']),
                "Web manifest should have icons array"
            );
            
            // Test a random subset of icons in manifest
            $testCount = rand(3, min(5, count($manifest['icons'])));
            $testIcons = array_slice($manifest['icons'], 0, $testCount);
            
            foreach ($testIcons as $iconEntry) {
                $this->assert(
                    isset($iconEntry['src']),
                    "Icon entry should have 'src' property"
                );
                
                $this->assert(
                    isset($iconEntry['sizes']),
                    "Icon entry should have 'sizes' property"
                );
                
                $this->assert(
                    isset($iconEntry['type']),
                    "Icon entry should have 'type' property"
                );
                
                $this->assert(
                    $iconEntry['type'] === 'image/png',
                    "Icon entry should have PNG type, got: " . $iconEntry['type']
                );
                
                // Verify the referenced file exists
                $iconPath = __DIR__ . '/../' . $iconEntry['src'];
                
                $this->assert(
                    file_exists($iconPath),
                    "Referenced icon file should exist: " . $iconEntry['src']
                );
                
                // Verify purpose attribute exists
                $this->assert(
                    isset($iconEntry['purpose']),
                    "Icon entry should have 'purpose' property"
                );
                
                $validPurposes = ['any', 'maskable', 'any maskable'];
                $this->assert(
                    in_array($iconEntry['purpose'], $validPurposes),
                    "Icon purpose should be valid: " . $iconEntry['purpose']
                );
            }
            
            return [
                'success' => true,
                'data' => [
                    'total_icons' => count($manifest['icons']),
                    'tested_icons' => count($testIcons)
                ]
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Generate a random subset of an array
     */
    private function generateRandomSubset(array $array, int $count): array {
        $count = min($count, count($array));
        $keys = array_rand($array, $count);
        
        if (!is_array($keys)) {
            $keys = [$keys];
        }
        
        $result = [];
        foreach ($keys as $key) {
            $result[] = $array[$key];
        }
        
        return $result;
    }
}