<?php
/**
 * Property Test for Manifest Property Completeness
 * **Feature: clarity-pwa-conversion, Property 8: Manifest Property Completeness**
 * **Validates: Requirements 3.3**
 */

require_once 'PropertyTestBase.php';

class ManifestPropertyCompletenessPropertyTest extends PropertyTestBase {
    
    private $webManifestPath;
    private $requiredProperties = [
        'name',
        'short_name',
        'start_url',
        'display',
        'icons',
        'theme_color',
        'background_color'
    ];
    
    private $recommendedProperties = [
        'description',
        'scope',
        'orientation',
        'lang',
        'categories',
        'shortcuts'
    ];
    
    public function __construct() {
        parent::__construct();
        $this->webManifestPath = __DIR__ . '/../app.webmanifest';
    }
    
    public function runTests(): bool {
        echo "=== Manifest Property Completeness Property Tests ===\n\n";
        
        $allPassed = true;
        
        // Property 8: Manifest Property Completeness
        $allPassed &= $this->runPropertyTest(
            "Property 8: All required properties are present and valid",
            [$this, 'testRequiredPropertiesComplete']
        );
        
        $allPassed &= $this->runPropertyTest(
            "Property 8: Recommended properties enhance user experience",
            [$this, 'testRecommendedPropertiesPresent']
        );
        
        $allPassed &= $this->runPropertyTest(
            "Property 8: Icon properties meet PWA standards",
            [$this, 'testIconPropertiesComplete']
        );
        
        $allPassed &= $this->runPropertyTest(
            "Property 8: Display and theme properties are valid",
            [$this, 'testDisplayThemePropertiesValid']
        );
        
        return $allPassed;
    }
    
    /**
     * Property 8: All required properties are present and valid
     * **Feature: clarity-pwa-conversion, Property 8: Manifest Property Completeness**
     * **Validates: Requirements 3.3**
     */
    public function testRequiredPropertiesComplete(): array {
        try {
            $manifestContent = file_get_contents($this->webManifestPath);
            $manifest = json_decode($manifestContent, true);
            
            // Test a random subset of required properties each iteration
            $testProperties = $this->generateRandomSubset($this->requiredProperties, rand(4, count($this->requiredProperties)));
            
            foreach ($testProperties as $property) {
                $this->assert(
                    isset($manifest[$property]),
                    "Required property '{$property}' should be present in manifest"
                );
                
                switch ($property) {
                    case 'name':
                    case 'short_name':
                        $this->assert(
                            is_string($manifest[$property]) && strlen($manifest[$property]) > 0,
                            "Property '{$property}' should be a non-empty string"
                        );
                        break;
                        
                    case 'start_url':
                        $this->assert(
                            is_string($manifest[$property]) && strlen($manifest[$property]) > 0,
                            "Start URL should be a non-empty string"
                        );
                        
                        $this->assert(
                            $manifest[$property][0] === '/' || filter_var($manifest[$property], FILTER_VALIDATE_URL),
                            "Start URL should be a valid URL or path"
                        );
                        break;
                        
                    case 'display':
                        $validDisplayModes = ['fullscreen', 'standalone', 'minimal-ui', 'browser'];
                        $this->assert(
                            in_array($manifest[$property], $validDisplayModes),
                            "Display mode should be valid: " . $manifest[$property]
                        );
                        break;
                        
                    case 'icons':
                        $this->assert(
                            is_array($manifest[$property]) && count($manifest[$property]) > 0,
                            "Icons should be a non-empty array"
                        );
                        break;
                        
                    case 'theme_color':
                    case 'background_color':
                        $this->assert(
                            is_string($manifest[$property]) && preg_match('/^#[0-9a-fA-F]{6}$/', $manifest[$property]),
                            "Color property '{$property}' should be a valid hex color"
                        );
                        break;
                }
            }
            
            return [
                'success' => true,
                'data' => ['tested_properties' => $testProperties]
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => ['tested_properties' => $testProperties ?? []]
            ];
        }
    }
    
    /**
     * Property 8: Recommended properties enhance user experience
     * **Feature: clarity-pwa-conversion, Property 8: Manifest Property Completeness**
     * **Validates: Requirements 3.3**
     */
    public function testRecommendedPropertiesPresent(): array {
        try {
            $manifestContent = file_get_contents($this->webManifestPath);
            $manifest = json_decode($manifestContent, true);
            
            // Test a random subset of recommended properties
            $testProperties = $this->generateRandomSubset($this->recommendedProperties, rand(3, count($this->recommendedProperties)));
            
            $presentCount = 0;
            $validCount = 0;
            
            foreach ($testProperties as $property) {
                if (isset($manifest[$property])) {
                    $presentCount++;
                    
                    switch ($property) {
                        case 'description':
                            if (is_string($manifest[$property]) && strlen($manifest[$property]) > 0) {
                                $validCount++;
                            }
                            break;
                            
                        case 'scope':
                            if (is_string($manifest[$property]) && strlen($manifest[$property]) > 0) {
                                $validCount++;
                            }
                            break;
                            
                        case 'orientation':
                            $validOrientations = ['any', 'natural', 'landscape', 'portrait', 'portrait-primary', 'portrait-secondary', 'landscape-primary', 'landscape-secondary'];
                            if (in_array($manifest[$property], $validOrientations)) {
                                $validCount++;
                            }
                            break;
                            
                        case 'lang':
                            if (is_string($manifest[$property]) && preg_match('/^[a-z]{2}(-[A-Z]{2})?$/', $manifest[$property])) {
                                $validCount++;
                            }
                            break;
                            
                        case 'categories':
                            if (is_array($manifest[$property]) && count($manifest[$property]) > 0) {
                                $validCount++;
                            }
                            break;
                            
                        case 'shortcuts':
                            if (is_array($manifest[$property]) && count($manifest[$property]) > 0) {
                                $validCount++;
                            }
                            break;
                    }
                }
            }
            
            // At least 50% of tested recommended properties should be present and valid
            $successRate = count($testProperties) > 0 ? ($validCount / count($testProperties)) : 0;
            
            $this->assert(
                $successRate >= 0.5,
                "At least 50% of recommended properties should be present and valid. Got: " . round($successRate * 100, 1) . "%"
            );
            
            return [
                'success' => true,
                'data' => [
                    'tested_properties' => $testProperties,
                    'present_count' => $presentCount,
                    'valid_count' => $validCount,
                    'success_rate' => round($successRate * 100, 1) . '%'
                ]
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => ['tested_properties' => $testProperties ?? []]
            ];
        }
    }
    
    /**
     * Property 8: Icon properties meet PWA standards
     * **Feature: clarity-pwa-conversion, Property 8: Manifest Property Completeness**
     * **Validates: Requirements 3.3**
     */
    public function testIconPropertiesComplete(): array {
        try {
            $manifestContent = file_get_contents($this->webManifestPath);
            $manifest = json_decode($manifestContent, true);
            
            $this->assert(
                isset($manifest['icons']) && is_array($manifest['icons']),
                "Icons array should be present"
            );
            
            // Test a random icon from the manifest
            $testIcon = $this->generateRandomChoice($manifest['icons']);
            
            // Required icon properties
            $requiredIconProps = ['src', 'sizes', 'type'];
            foreach ($requiredIconProps as $prop) {
                $this->assert(
                    isset($testIcon[$prop]),
                    "Icon should have required property: {$prop}"
                );
            }
            
            // Validate icon properties
            $this->assert(
                is_string($testIcon['src']) && strlen($testIcon['src']) > 0,
                "Icon src should be a non-empty string"
            );
            
            $this->assert(
                preg_match('/^\d+x\d+$/', $testIcon['sizes']),
                "Icon sizes should be in format 'WxH': " . $testIcon['sizes']
            );
            
            $this->assert(
                $testIcon['type'] === 'image/png',
                "Icon type should be image/png for PWA compatibility"
            );
            
            // Check for purpose property (recommended)
            if (isset($testIcon['purpose'])) {
                $validPurposes = ['any', 'maskable', 'any maskable'];
                $this->assert(
                    in_array($testIcon['purpose'], $validPurposes),
                    "Icon purpose should be valid: " . $testIcon['purpose']
                );
            }
            
            // Verify the icon file exists
            $iconPath = __DIR__ . '/../' . $testIcon['src'];
            $this->assert(
                file_exists($iconPath),
                "Referenced icon file should exist: " . $testIcon['src']
            );
            
            return [
                'success' => true,
                'data' => [
                    'tested_icon' => $testIcon,
                    'total_icons' => count($manifest['icons'])
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
     * Property 8: Display and theme properties are valid
     * **Feature: clarity-pwa-conversion, Property 8: Manifest Property Completeness**
     * **Validates: Requirements 3.3**
     */
    public function testDisplayThemePropertiesValid(): array {
        try {
            $manifestContent = file_get_contents($this->webManifestPath);
            $manifest = json_decode($manifestContent, true);
            
            // Test display property
            if (isset($manifest['display'])) {
                $validDisplayModes = ['fullscreen', 'standalone', 'minimal-ui', 'browser'];
                $this->assert(
                    in_array($manifest['display'], $validDisplayModes),
                    "Display mode should be valid: " . $manifest['display']
                );
            }
            
            // Test theme_color
            if (isset($manifest['theme_color'])) {
                $this->assert(
                    is_string($manifest['theme_color']) && preg_match('/^#[0-9a-fA-F]{6}$/', $manifest['theme_color']),
                    "Theme color should be a valid hex color: " . $manifest['theme_color']
                );
            }
            
            // Test background_color
            if (isset($manifest['background_color'])) {
                $this->assert(
                    is_string($manifest['background_color']) && preg_match('/^#[0-9a-fA-F]{6}$/', $manifest['background_color']),
                    "Background color should be a valid hex color: " . $manifest['background_color']
                );
            }
            
            // Test orientation if present
            if (isset($manifest['orientation'])) {
                $validOrientations = ['any', 'natural', 'landscape', 'portrait', 'portrait-primary', 'portrait-secondary', 'landscape-primary', 'landscape-secondary'];
                $this->assert(
                    in_array($manifest['orientation'], $validOrientations),
                    "Orientation should be valid: " . $manifest['orientation']
                );
            }
            
            // Test scope if present
            if (isset($manifest['scope'])) {
                $this->assert(
                    is_string($manifest['scope']) && strlen($manifest['scope']) > 0,
                    "Scope should be a non-empty string"
                );
                
                $this->assert(
                    $manifest['scope'][0] === '/' || filter_var($manifest['scope'], FILTER_VALIDATE_URL),
                    "Scope should be a valid URL or path"
                );
            }
            
            return [
                'success' => true,
                'data' => [
                    'display' => $manifest['display'] ?? null,
                    'theme_color' => $manifest['theme_color'] ?? null,
                    'background_color' => $manifest['background_color'] ?? null,
                    'orientation' => $manifest['orientation'] ?? null,
                    'scope' => $manifest['scope'] ?? null
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