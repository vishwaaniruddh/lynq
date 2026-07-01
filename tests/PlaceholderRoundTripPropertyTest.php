<?php
/**
 * Property Test for Placeholder Round-Trip Consistency
 * **Feature: email-management-system, Property 6: Placeholder Round-Trip Consistency**
 */

require_once __DIR__ . '/PropertyTestBase.php';
require_once __DIR__ . '/../services/PlaceholderService.php';

class PlaceholderRoundTripPropertyTest extends PropertyTestBase {
    private $placeholderService;
    
    public function __construct() {
        parent::__construct();
        $this->placeholderService = new PlaceholderService();
    }
    
    public function runTests() {
        echo "Starting Placeholder Round-Trip Consistency Property Tests\n";
        
        $allPassed = true;
        
        // Property 6: Placeholder Round-Trip Consistency
        $allPassed &= $this->runPropertyTest(
            "Placeholder Round-Trip Consistency",
            [$this, 'testPlaceholderRoundTripConsistency']
        );
        
        if ($allPassed) {
            echo "All Placeholder Round-Trip property tests passed!\n";
            return true;
        } else {
            echo "Some Placeholder Round-Trip property tests failed!\n";
            return false;
        }
    }
    
    /**
     * Property 6: For any email template with valid placeholders, generating an email should 
     * replace all placeholders with actual system data, and the resulting email should contain 
     * no unreplaced placeholder syntax
     */
    public function testPlaceholderRoundTripConsistency() {
        try {
            // Generate random module and template content
            $moduleName = $this->generateRandomModule();
            $templateContent = $this->generateTemplateWithValidPlaceholders($moduleName);
            $sampleData = $this->placeholderService->generateSampleData($moduleName);
            
            // Step 1: Validate that the template has valid placeholders
            $validation = $this->placeholderService->validatePlaceholders($templateContent, $moduleName);
            $this->assert($validation['valid'], "Generated template should have valid placeholders: " . implode(', ', $validation['errors']));
            
            // Step 2: Replace placeholders with actual data
            $processedContent = $this->placeholderService->replacePlaceholders($templateContent, $sampleData, $moduleName);
            
            // Step 3: Verify no unreplaced placeholder syntax remains
            $this->assert($this->hasNoUnreplacedPlaceholders($processedContent), 
                "Processed content should not contain unreplaced placeholders. Content: " . substr($processedContent, 0, 200));
            
            // Step 4: Verify that all valid placeholders were actually replaced
            $originalPlaceholders = $validation['placeholders'];
            foreach ($originalPlaceholders as $placeholder) {
                $placeholderSyntax = '{' . $placeholder . '}';
                $this->assert(strpos($processedContent, $placeholderSyntax) === false, 
                    "Placeholder '$placeholderSyntax' should be replaced in processed content");
            }
            
            // Step 5: Verify that replaced content contains expected data
            $this->verifyReplacedContentContainsData($processedContent, $sampleData, $originalPlaceholders);
            
            // Step 6: Test with nested placeholders
            if ($this->hasNestedPlaceholders($originalPlaceholders)) {
                $this->verifyNestedPlaceholderReplacement($templateContent, $processedContent, $sampleData);
            }
            
            return [
                'success' => true,
                'data' => [
                    'module' => $moduleName,
                    'template' => $templateContent,
                    'processed' => $processedContent,
                    'placeholders' => $originalPlaceholders
                ]
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => [
                    'module' => $moduleName ?? 'unknown',
                    'template' => $templateContent ?? 'unknown',
                    'error_trace' => $e->getTraceAsString()
                ]
            ];
        }
    }
    
    /**
     * Generate a random module name
     */
    private function generateRandomModule() {
        $modules = [
            PlaceholderService::MODULE_USERS,
            PlaceholderService::MODULE_SITES,
            PlaceholderService::MODULE_FEASIBILITY,
            PlaceholderService::MODULE_INSTALLATION,
            PlaceholderService::MODULE_MATERIAL_REQUESTS,
            PlaceholderService::MODULE_DISPATCHES,
            PlaceholderService::MODULE_INVENTORY,
            PlaceholderService::MODULE_CONFIGURATION,
            PlaceholderService::MODULE_NOTES
        ];
        
        return $this->generateRandomChoice($modules);
    }
    
    /**
     * Generate template content with valid placeholders for the given module
     */
    private function generateTemplateWithValidPlaceholders($moduleName) {
        $availablePlaceholders = array_keys($this->placeholderService->getModulePlaceholders($moduleName));
        
        // Select random subset of placeholders (1-5 placeholders)
        $numPlaceholders = rand(1, min(5, count($availablePlaceholders)));
        $selectedPlaceholders = array_rand(array_flip($availablePlaceholders), $numPlaceholders);
        
        // Ensure it's an array even if only one placeholder is selected
        if (!is_array($selectedPlaceholders)) {
            $selectedPlaceholders = [$selectedPlaceholders];
        }
        
        // Generate template content with only valid placeholders for this module
        $templateParts = [];
        
        // Use a greeting with a placeholder that exists in this module
        if (in_array('user_name', $availablePlaceholders)) {
            $templateParts[] = "Dear {user_name},";
        } else {
            $templateParts[] = "Dear User,";
        }
        
        $templateParts[] = "";
        $templateParts[] = "This is a notification regarding " . $this->generateRandomString(10) . ".";
        $templateParts[] = "";
        
        // Add random placeholders in the content
        foreach ($selectedPlaceholders as $placeholder) {
            $templateParts[] = "Information: {" . $placeholder . "}";
        }
        
        $templateParts[] = "";
        
        // Use current_date and current_time if available, otherwise use static text
        if (in_array('current_date', $availablePlaceholders) && in_array('current_time', $availablePlaceholders)) {
            $templateParts[] = "Generated on {current_date} at {current_time}.";
        } else {
            $templateParts[] = "Generated automatically.";
        }
        
        $templateParts[] = "";
        $templateParts[] = "Best regards,";
        
        // Use company_name if available for this module
        if (in_array('company_name', $availablePlaceholders)) {
            $templateParts[] = "{company_name}";
        } else {
            $templateParts[] = "System Administrator";
        }
        
        return implode("\n", $templateParts);
    }
    
    /**
     * Check if content has no unreplaced placeholders
     */
    private function hasNoUnreplacedPlaceholders($content) {
        // Look for any remaining {placeholder} syntax
        return !preg_match('/\{[^}]+\}/', $content);
    }
    
    /**
     * Verify that replaced content contains expected data
     */
    private function verifyReplacedContentContainsData($processedContent, $sampleData, $originalPlaceholders) {
        foreach ($originalPlaceholders as $placeholder) {
            // Skip nested placeholders for now (they're tested separately)
            if (strpos($placeholder, '.') !== false) {
                continue;
            }
            
            // Check if the placeholder value exists in sample data
            if (isset($sampleData[$placeholder])) {
                $expectedValue = $sampleData[$placeholder];
                if (is_scalar($expectedValue) && !empty($expectedValue)) {
                    $this->assert(strpos($processedContent, (string)$expectedValue) !== false,
                        "Processed content should contain value '$expectedValue' for placeholder '$placeholder'");
                }
            }
        }
    }
    
    /**
     * Check if placeholders include nested ones
     */
    private function hasNestedPlaceholders($placeholders) {
        foreach ($placeholders as $placeholder) {
            if (strpos($placeholder, '.') !== false) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Verify nested placeholder replacement
     */
    private function verifyNestedPlaceholderReplacement($originalContent, $processedContent, $sampleData) {
        // Find nested placeholders in original content
        preg_match_all('/\{([^}]+\.+[^}]+)\}/', $originalContent, $matches);
        $nestedPlaceholders = $matches[1];
        
        foreach ($nestedPlaceholders as $nestedPlaceholder) {
            $placeholderSyntax = '{' . $nestedPlaceholder . '}';
            
            // Verify the nested placeholder was replaced
            $this->assert(strpos($processedContent, $placeholderSyntax) === false,
                "Nested placeholder '$placeholderSyntax' should be replaced");
            
            // Try to get the nested value from sample data
            $nestedValue = $this->getNestedValue($sampleData, $nestedPlaceholder);
            if ($nestedValue !== null && is_scalar($nestedValue) && !empty($nestedValue)) {
                $this->assert(strpos($processedContent, (string)$nestedValue) !== false,
                    "Processed content should contain nested value '$nestedValue' for placeholder '$nestedPlaceholder'");
            }
        }
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
    $test = new PlaceholderRoundTripPropertyTest();
    $success = $test->runTests();
    exit($success ? 0 : 1);
}