<?php
/**
 * Property Test for Nested Placeholder Resolution
 * **Feature: email-management-system, Property 8: Nested Placeholder Resolution**
 */

require_once __DIR__ . '/PropertyTestBase.php';
require_once __DIR__ . '/../services/PlaceholderService.php';

class NestedPlaceholderResolutionPropertyTest extends PropertyTestBase {
    private $placeholderService;
    
    public function __construct() {
        parent::__construct();
        $this->placeholderService = new PlaceholderService();
    }
    
    public function runTests() {
        echo "Starting Nested Placeholder Resolution Property Tests\n";
        
        $allPassed = true;
        
        // Property 8: Nested Placeholder Resolution
        $allPassed &= $this->runPropertyTest(
            "Nested Placeholder Resolution",
            [$this, 'testNestedPlaceholderResolution']
        );
        
        if ($allPassed) {
            echo "All Nested Placeholder Resolution property tests passed!\n";
            return true;
        } else {
            echo "Some Nested Placeholder Resolution property tests failed!\n";
            return false;
        }
    }
    
    /**
     * Property 8: For any nested placeholder (e.g., {site.engineer.name}), the system should 
     * resolve the complete data path and replace it with the correct nested value
     */
    public function testNestedPlaceholderResolution() {
        try {
            // Generate random module that supports nested placeholders
            $moduleName = $this->generateModuleWithNestedPlaceholders();
            $nestedPlaceholders = $this->getNestedPlaceholdersForModule($moduleName);
            
            // Skip if no nested placeholders available
            if (empty($nestedPlaceholders)) {
                return [
                    'success' => true,
                    'data' => ['message' => 'No nested placeholders available for module: ' . $moduleName]
                ];
            }
            
            // Select random nested placeholder
            $selectedPlaceholder = $this->generateRandomChoice($nestedPlaceholders);
            
            // Create template content with the nested placeholder
            $templateContent = $this->generateTemplateWithNestedPlaceholder($selectedPlaceholder);
            
            // Generate sample data with nested structure
            $sampleData = $this->placeholderService->generateSampleData($moduleName);
            
            // Verify the nested data exists in sample data
            $nestedValue = $this->getNestedValue($sampleData, $selectedPlaceholder);
            $this->assert($nestedValue !== null, 
                "Sample data should contain nested value for placeholder '$selectedPlaceholder'. Data structure: " . json_encode($sampleData));
            
            // Replace placeholders
            $processedContent = $this->placeholderService->replacePlaceholders($templateContent, $sampleData, $moduleName);
            
            // Verify the nested placeholder was replaced
            $placeholderSyntax = '{' . $selectedPlaceholder . '}';
            $this->assert(strpos($processedContent, $placeholderSyntax) === false,
                "Nested placeholder '$placeholderSyntax' should be replaced in processed content");
            
            // Verify the nested value appears in the processed content
            if (is_scalar($nestedValue) && !empty($nestedValue)) {
                $this->assert(strpos($processedContent, (string)$nestedValue) !== false,
                    "Processed content should contain nested value '$nestedValue' for placeholder '$selectedPlaceholder'");
            }
            
            // Test multiple nested placeholders in same template
            $multipleNestedTemplate = $this->generateTemplateWithMultipleNestedPlaceholders($nestedPlaceholders);
            $multipleProcessedContent = $this->placeholderService->replacePlaceholders($multipleNestedTemplate, $sampleData, $moduleName);
            
            // Verify all nested placeholders were replaced
            foreach ($nestedPlaceholders as $placeholder) {
                $syntax = '{' . $placeholder . '}';
                if (strpos($multipleNestedTemplate, $syntax) !== false) {
                    $this->assert(strpos($multipleProcessedContent, $syntax) === false,
                        "Multiple nested placeholder test: '$syntax' should be replaced");
                }
            }
            
            // Test deeply nested placeholders (if available)
            $deeplyNestedPlaceholder = $this->findDeeplyNestedPlaceholder($nestedPlaceholders);
            if ($deeplyNestedPlaceholder) {
                $deepTemplate = "Deep nested test: {" . $deeplyNestedPlaceholder . "}";
                $deepProcessed = $this->placeholderService->replacePlaceholders($deepTemplate, $sampleData, $moduleName);
                $deepSyntax = '{' . $deeplyNestedPlaceholder . '}';
                $this->assert(strpos($deepProcessed, $deepSyntax) === false,
                    "Deeply nested placeholder '$deepSyntax' should be replaced");
            }
            
            return [
                'success' => true,
                'data' => [
                    'module' => $moduleName,
                    'placeholder' => $selectedPlaceholder,
                    'nested_value' => $nestedValue,
                    'template' => $templateContent,
                    'processed' => $processedContent
                ]
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => [
                    'module' => $moduleName ?? 'unknown',
                    'placeholder' => $selectedPlaceholder ?? 'unknown',
                    'error_trace' => $e->getTraceAsString()
                ]
            ];
        }
    }
    
    /**
     * Generate a module that supports nested placeholders
     */
    private function generateModuleWithNestedPlaceholders() {
        $modulesWithNested = [
            PlaceholderService::MODULE_SITES,
            PlaceholderService::MODULE_FEASIBILITY,
            PlaceholderService::MODULE_INSTALLATION,
            PlaceholderService::MODULE_MATERIAL_REQUESTS,
            PlaceholderService::MODULE_DISPATCHES,
            PlaceholderService::MODULE_CONFIGURATION,
            PlaceholderService::MODULE_NOTES
        ];
        
        return $this->generateRandomChoice($modulesWithNested);
    }
    
    /**
     * Get nested placeholders for a module
     */
    private function getNestedPlaceholdersForModule($moduleName) {
        $allPlaceholders = array_keys($this->placeholderService->getModulePlaceholders($moduleName));
        
        // Filter for nested placeholders (containing dots)
        $nestedPlaceholders = array_filter($allPlaceholders, function($placeholder) {
            return strpos($placeholder, '.') !== false;
        });
        
        return array_values($nestedPlaceholders);
    }
    
    /**
     * Generate template content with a nested placeholder
     */
    private function generateTemplateWithNestedPlaceholder($nestedPlaceholder) {
        $templateParts = [];
        $templateParts[] = "Notification: " . $this->generateRandomString(10);
        $templateParts[] = "";
        $templateParts[] = "Nested information: {" . $nestedPlaceholder . "}";
        $templateParts[] = "";
        $templateParts[] = "End of notification.";
        
        return implode("\n", $templateParts);
    }
    
    /**
     * Generate template with multiple nested placeholders
     */
    private function generateTemplateWithMultipleNestedPlaceholders($nestedPlaceholders) {
        $templateParts = [];
        $templateParts[] = "Multi-nested test:";
        $templateParts[] = "";
        
        // Use up to 3 nested placeholders
        $count = min(3, count($nestedPlaceholders));
        $selectedPlaceholders = array_slice($nestedPlaceholders, 0, $count);
        
        foreach ($selectedPlaceholders as $placeholder) {
            $templateParts[] = "- {" . $placeholder . "}";
        }
        
        $templateParts[] = "";
        $templateParts[] = "End of multi-nested test.";
        
        return implode("\n", $templateParts);
    }
    
    /**
     * Find a deeply nested placeholder (with multiple dots)
     */
    private function findDeeplyNestedPlaceholder($nestedPlaceholders) {
        foreach ($nestedPlaceholders as $placeholder) {
            if (substr_count($placeholder, '.') >= 2) {
                return $placeholder;
            }
        }
        return null;
    }
    
    /**
     * Get nested value from data using dot notation
     */
    private function getNestedValue($data, $path) {
        $keys = explode('.', $path);
        $value = $data;
        
        foreach ($keys as $key) {
            if (is_array($value) && isset($value[$key])) {
                $value = $value[$key];
            } else {
                return null;
            }
        }
        
        return $value;
    }
}

// Run the test if called directly
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    $test = new NestedPlaceholderResolutionPropertyTest();
    $success = $test->runTests();
    exit($success ? 0 : 1);
}