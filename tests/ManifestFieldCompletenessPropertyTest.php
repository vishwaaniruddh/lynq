<?php
/**
 * Property Test for Manifest Field Completeness
 * **Feature: clarity-pwa-conversion, Property 6: Manifest Field Completeness**
 * **Validates: Requirements 2.5**
 */

require_once 'PropertyTestBase.php';

class ManifestFieldCompletenessPropertyTest extends PropertyTestBase {
    
    private $webManifestPath;
    private $requiredFields = [
        'name',
        'short_name', 
        'start_url',
        'display',
        'icons'
    ];
    
    public function __construct() {
        parent::__construct();
        $this->webManifestPath = __DIR__ . '/../app.webmanifest';
    }
    
    public function runTests(): bool {
        echo "=== Manifest Field Completeness Property Tests ===\n\n";
        
        $allPassed = true;
        
        // Property 6: Manifest Field Completeness
        $allPassed &= $this->runPropertyTest(
            "Property 6: All required manifest fields are present",
            [$this, 'testRequiredFieldsPresent']
        );
        
        $allPassed &= $this->runPropertyTest(
            "Property 6: Required fields have valid values",
            [$this, 'testRequiredFieldsValid']
        );
        
        $allPassed &= $this->runPropertyTest(
            "Property 6: Manifest is valid JSON",
            [$this, 'testManifestValidJson']
        );
        
        return $allPassed;
    }
    
    /**
     * Property 6: All required manifest fields are present
     * **Feature: clarity-pwa-conversion, Property 6: Manifest Field Completeness**
     * **Validates: Requirements 2.5**
     */
    public function testRequiredFieldsPresent(): array {
        try {
            $this->assert(
                file_exists($this->webManifestPath),
                "Web manifest file should exist: app.webmanifest"
            );
            
            $manifestContent = file_get_contents($this->webManifestPath);
            $manifest = json_decode($manifestContent, true);
            
            $this->assert(
                $manifest !== null,
                "Web manifest should be valid JSON"
            );
            
            // Test a random subset of required fields each iteration
            $testFields = $this->generateRandomSubset($this->requiredFields, rand(3, count($this->requiredFields)));
            
            foreach ($testFields as $field) {
                $this->assert(
                    isset($manifest[$field]),
                    "Required field '{$field}' should be present in manifest"
                );
                
                $this->assert(
                    !empty($manifest[$field]) || $field === 'icons',
                    "Required field '{$field}' should not be empty"
                );
            }
            
            return [
                'success' => true,
                'data' => ['tested_fields' => $testFields]
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => ['tested_fields' => $testFields ?? []]
            ];
        }
    }
    
    /**
     * Property 6: Required fields have valid values
     * **Feature: clarity-pwa-conversion, Property 6: Manifest Field Completeness**
     * **Validates: Requirements 2.5**
     */
    public function testRequiredFieldsValid(): array {
        try {
            $manifestContent = file_get_contents($this->webManifestPath);
            $manifest = json_decode($manifestContent, true);
            
            // Test a random required field each iteration
            $testField = $this->generateRandomChoice($this->requiredFields);
            
            switch ($testField) {
                case 'name':
                    $this->assert(
                        is_string($manifest['name']) && strlen($manifest['name']) > 0,
                        "Name field should be a non-empty string"
                    );
                    break;
                    
                case 'short_name':
                    $this->assert(
                        is_string($manifest['short_name']) && strlen($manifest['short_name']) > 0,
                        "Short name field should be a non-empty string"
                    );
                    break;
                    
                case 'start_url':
                    $this->assert(
                        is_string($manifest['start_url']) && strlen($manifest['start_url']) > 0,
                        "Start URL field should be a non-empty string"
                    );
                    
                    // Should be a valid URL path
                    $this->assert(
                        $manifest['start_url'][0] === '/' || filter_var($manifest['start_url'], FILTER_VALIDATE_URL),
                        "Start URL should be a valid URL or path"
                    );
                    break;
                    
                case 'display':
                    $validDisplayModes = ['fullscreen', 'standalone', 'minimal-ui', 'browser'];
                    $this->assert(
                        in_array($manifest['display'], $validDisplayModes),
                        "Display mode should be valid: " . $manifest['display']
                    );
                    break;
                    
                case 'icons':
                    $this->assert(
                        is_array($manifest['icons']) && count($manifest['icons']) > 0,
                        "Icons field should be a non-empty array"
                    );
                    
                    // Test a random icon entry
                    $randomIcon = $this->generateRandomChoice($manifest['icons']);
                    
                    $this->assert(
                        isset($randomIcon['src']) && is_string($randomIcon['src']),
                        "Icon should have valid src field"
                    );
                    
                    $this->assert(
                        isset($randomIcon['sizes']) && is_string($randomIcon['sizes']),
                        "Icon should have valid sizes field"
                    );
                    
                    $this->assert(
                        isset($randomIcon['type']) && is_string($randomIcon['type']),
                        "Icon should have valid type field"
                    );
                    break;
            }
            
            return [
                'success' => true,
                'data' => [
                    'tested_field' => $testField,
                    'field_value' => $manifest[$testField] ?? null
                ]
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => ['tested_field' => $testField ?? null]
            ];
        }
    }
    
    /**
     * Property 6: Manifest is valid JSON
     * **Feature: clarity-pwa-conversion, Property 6: Manifest Field Completeness**
     * **Validates: Requirements 2.5**
     */
    public function testManifestValidJson(): array {
        try {
            $this->assert(
                file_exists($this->webManifestPath),
                "Web manifest file should exist"
            );
            
            $manifestContent = file_get_contents($this->webManifestPath);
            
            $this->assert(
                $manifestContent !== false,
                "Should be able to read manifest file"
            );
            
            $this->assert(
                !empty($manifestContent),
                "Manifest file should not be empty"
            );
            
            $manifest = json_decode($manifestContent, true);
            $jsonError = json_last_error();
            
            $this->assert(
                $jsonError === JSON_ERROR_NONE,
                "Manifest should be valid JSON. Error: " . json_last_error_msg()
            );
            
            $this->assert(
                is_array($manifest),
                "Manifest should decode to an array/object"
            );
            
            // Test that it can be re-encoded
            $reEncoded = json_encode($manifest);
            
            $this->assert(
                $reEncoded !== false,
                "Manifest should be re-encodable to JSON"
            );
            
            return [
                'success' => true,
                'data' => [
                    'file_size' => strlen($manifestContent),
                    'field_count' => count($manifest)
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