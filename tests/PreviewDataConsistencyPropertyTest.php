<?php
/**
 * Property Test for Preview Data Consistency
 * **Feature: email-management-system, Property 9: Preview Data Consistency**
 */

require_once __DIR__ . '/PropertyTestBase.php';
require_once __DIR__ . '/../services/EmailPreviewService.php';
require_once __DIR__ . '/../services/PlaceholderService.php';

class PreviewDataConsistencyPropertyTest extends PropertyTestBase {
    private $previewService;
    private $placeholderService;
    
    public function __construct() {
        parent::__construct();
        $this->previewService = new EmailPreviewService();
        $this->placeholderService = new PlaceholderService();
    }
    
    public function runTests() {
        echo "Starting Preview Data Consistency Property Tests\n";
        
        $allPassed = true;
        
        // Property 9: Preview Data Consistency
        $allPassed &= $this->runPropertyTest(
            "Preview Data Consistency",
            [$this, 'testPreviewDataConsistency']
        );
        
        if ($allPassed) {
            echo "All Preview Data Consistency property tests passed!\n";
            return true;
        } else {
            echo "Some Preview Data Consistency property tests failed!\n";
            return false;
        }
    }
    
    /**
     * Property 9: For any template with placeholders, the preview functionality should show 
     * sample data replacement that follows the same rules as actual email generation
     */
    public function testPreviewDataConsistency() {
        try {
            // Generate random module and template content
            $moduleName = $this->generateRandomModule();
            $templateContent = $this->generateRandomTemplateContent($moduleName);
            
            // Generate sample data
            $sampleData = $this->placeholderService->generateSampleData($moduleName);
            
            // Test 1: Preview and direct placeholder replacement should produce identical results
            $previewResult = $this->previewService->generateContentPreview(
                $templateContent['subject'],
                $templateContent['body_text'],
                $templateContent['body_html'],
                $moduleName,
                $sampleData
            );
            
            $directSubject = $this->placeholderService->replacePlaceholders($templateContent['subject'], $sampleData, $moduleName);
            $directBodyText = $templateContent['body_text'] ? 
                $this->placeholderService->replacePlaceholders($templateContent['body_text'], $sampleData, $moduleName) : null;
            $directBodyHtml = $templateContent['body_html'] ? 
                $this->placeholderService->replacePlaceholders($templateContent['body_html'], $sampleData, $moduleName) : null;
            
            // Verify preview results match direct replacement
            $this->assert($previewResult['subject'] === $directSubject,
                "Preview subject should match direct replacement. Preview: '{$previewResult['subject']}', Direct: '{$directSubject}'");
            
            if ($templateContent['body_text']) {
                $this->assert($previewResult['body_text'] === $directBodyText,
                    "Preview body_text should match direct replacement");
            }
            
            if ($templateContent['body_html']) {
                $this->assert($previewResult['body_html'] === $directBodyHtml,
                    "Preview body_html should match direct replacement");
            }
            
            // Test 2: Preview should use the same data that was provided
            $this->assert($previewResult['data_used'] === $sampleData,
                "Preview should use the exact same data that was provided");
            
            // Test 3: Preview validation should match direct validation
            $directSubjectValidation = $this->placeholderService->validatePlaceholders($templateContent['subject'], $moduleName);
            $previewValidationErrors = $previewResult['validation_errors'];
            
            $this->assert(count($previewValidationErrors) === count($directSubjectValidation['errors']),
                "Preview validation errors should match direct validation errors");
            
            // Test 4: Preview placeholders found should match what's actually in the template
            $allContent = $templateContent['subject'];
            if ($templateContent['body_text']) {
                $allContent .= ' ' . $templateContent['body_text'];
            }
            if ($templateContent['body_html']) {
                $allContent .= ' ' . $templateContent['body_html'];
            }
            
            $directValidation = $this->placeholderService->validatePlaceholders($allContent, $moduleName);
            $previewPlaceholders = $previewResult['placeholders_found'];
            
            // Sort arrays for comparison
            sort($previewPlaceholders);
            sort($directValidation['placeholders']);
            
            $this->assert($previewPlaceholders === $directValidation['placeholders'],
                "Preview placeholders should match direct validation placeholders. Preview: " . 
                implode(',', $previewPlaceholders) . ", Direct: " . implode(',', $directValidation['placeholders']));
            
            // Test 5: Multiple previews with same data should produce identical results
            $secondPreview = $this->previewService->generateContentPreview(
                $templateContent['subject'],
                $templateContent['body_text'],
                $templateContent['body_html'],
                $moduleName,
                $sampleData
            );
            
            $this->assert($previewResult['subject'] === $secondPreview['subject'],
                "Multiple previews with same data should produce identical subjects");
            
            $this->assert($previewResult['body_text'] === $secondPreview['body_text'],
                "Multiple previews with same data should produce identical body_text");
            
            $this->assert($previewResult['body_html'] === $secondPreview['body_html'],
                "Multiple previews with same data should produce identical body_html");
            
            // Test 6: Preview statistics should be consistent with actual content
            $stats = $this->previewService->getPreviewStatistics($previewResult);
            
            $this->assert($stats['has_text_body'] === !empty($previewResult['body_text']),
                "Statistics should correctly reflect presence of text body");
            
            $this->assert($stats['has_html_body'] === !empty($previewResult['body_html']),
                "Statistics should correctly reflect presence of HTML body");
            
            $this->assert($stats['subject_length'] === strlen($previewResult['subject']),
                "Statistics should correctly reflect subject length");
            
            $this->assert($stats['is_valid'] === empty($previewResult['validation_errors']),
                "Statistics should correctly reflect validation status");
            
            // Test 7: Nested placeholder consistency
            if ($this->hasNestedPlaceholders($previewResult['placeholders_found'])) {
                $this->verifyNestedPlaceholderConsistency($templateContent, $previewResult, $sampleData, $moduleName);
            }
            
            return [
                'success' => true,
                'data' => [
                    'module' => $moduleName,
                    'template' => $templateContent,
                    'preview_subject' => $previewResult['subject'],
                    'direct_subject' => $directSubject,
                    'placeholders_found' => $previewResult['placeholders_found'],
                    'validation_errors' => $previewResult['validation_errors']
                ]
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => [
                    'module' => $moduleName ?? 'unknown',
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
     * Generate random template content with valid placeholders
     */
    private function generateRandomTemplateContent($moduleName) {
        $availablePlaceholders = array_keys($this->placeholderService->getModulePlaceholders($moduleName));
        
        // Select random subset of placeholders (2-4 placeholders)
        $numPlaceholders = rand(2, min(4, count($availablePlaceholders)));
        $selectedPlaceholders = array_rand(array_flip($availablePlaceholders), $numPlaceholders);
        
        // Ensure it's an array
        if (!is_array($selectedPlaceholders)) {
            $selectedPlaceholders = [$selectedPlaceholders];
        }
        
        // Generate subject with placeholders
        $subjectPlaceholders = array_slice($selectedPlaceholders, 0, 2);
        $subject = "Notification: " . $this->generateRandomString(5);
        foreach ($subjectPlaceholders as $placeholder) {
            $subject .= " {" . $placeholder . "}";
        }
        
        // Generate body text (50% chance)
        $bodyText = null;
        if (rand(0, 1)) {
            $bodyText = "Dear User,\n\nThis is regarding: ";
            foreach ($selectedPlaceholders as $placeholder) {
                $bodyText .= "{" . $placeholder . "} ";
            }
            $bodyText .= "\n\nBest regards.";
        }
        
        // Generate body HTML (30% chance)
        $bodyHtml = null;
        if (rand(0, 2) === 0) {
            $bodyHtml = "<p>HTML notification:</p><ul>";
            foreach ($selectedPlaceholders as $placeholder) {
                $bodyHtml .= "<li>{" . $placeholder . "}</li>";
            }
            $bodyHtml .= "</ul>";
        }
        
        return [
            'subject' => $subject,
            'body_text' => $bodyText,
            'body_html' => $bodyHtml
        ];
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
     * Verify nested placeholder consistency
     */
    private function verifyNestedPlaceholderConsistency($templateContent, $previewResult, $sampleData, $moduleName) {
        // Find nested placeholders in the template
        $allContent = $templateContent['subject'];
        if ($templateContent['body_text']) {
            $allContent .= ' ' . $templateContent['body_text'];
        }
        if ($templateContent['body_html']) {
            $allContent .= ' ' . $templateContent['body_html'];
        }
        
        preg_match_all('/\{([^}]+\.+[^}]+)\}/', $allContent, $matches);
        $nestedPlaceholders = $matches[1];
        
        foreach ($nestedPlaceholders as $nestedPlaceholder) {
            // Get expected value from sample data
            $expectedValue = $this->getNestedValue($sampleData, $nestedPlaceholder);
            
            if ($expectedValue !== null && is_scalar($expectedValue)) {
                // Check if the value appears in preview results
                $allPreviewContent = $previewResult['subject'];
                if ($previewResult['body_text']) {
                    $allPreviewContent .= ' ' . $previewResult['body_text'];
                }
                if ($previewResult['body_html']) {
                    $allPreviewContent .= ' ' . $previewResult['body_html'];
                }
                
                $this->assert(strpos($allPreviewContent, (string)$expectedValue) !== false,
                    "Preview should contain nested value '$expectedValue' for placeholder '$nestedPlaceholder'");
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
    $test = new PreviewDataConsistencyPropertyTest();
    $success = $test->runTests();
    exit($success ? 0 : 1);
}