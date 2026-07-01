<?php
/**
 * Property Test: Installation Form Required Field Validation
 * 
 * **Feature: installation-module, Property 11: Installation form required field validation**
 * **Validates: Requirements 5.3**
 * 
 * Property: For any installation form submission with any required field empty,
 * the submission should be rejected with a specific validation error identifying the missing field.
 */

require_once __DIR__ . '/../config/autoload.php';
require_once __DIR__ . '/../services/InstallationService.php';
require_once __DIR__ . '/../models/Installation.php';

class InstallationValidationPropertyTest {
    private $testResults = [];
    private $iterations = 100;
    private $installationService;
    
    // Required fields for installation submission
    private $requiredFields = [
        'vendor_name',
        'engineer_name',
        'engineer_number',
        'router_serial',
        'router_make',
        'router_model',
        'router_fixed',
        'router_status',
        'adaptor_installed',
        'adaptor_status',
        'lan_cable_installed',
        'lan_cable_status',
        'antenna_installed',
        'antenna_status',
        'gps_installed',
        'gps_status',
        'wifi_installed',
        'wifi_status',
        'airtel_sim_installed',
        'airtel_sim_status',
        'vodafone_sim_installed',
        'vodafone_sim_status',
        'jio_sim_installed',
        'jio_sim_status',
        'signature_image'
    ];
    
    public function __construct() {
        $this->installationService = new InstallationService();
    }
    
    /**
     * Run all property tests
     */
    public function runTests(): bool {
        echo "\n=== Installation Validation Property Tests ===\n";
        echo "**Feature: installation-module, Property 11: Installation form required field validation**\n";
        echo "**Validates: Requirements 5.3**\n\n";
        
        // Property tests
        $this->runPropertyTest(
            'Missing required field is rejected with specific error',
            [$this, 'testMissingRequiredFieldRejected']
        );
        
        $this->runPropertyTest(
            'Empty string required field is rejected',
            [$this, 'testEmptyStringRequiredFieldRejected']
        );
        
        $this->runPropertyTest(
            'Complete data passes validation',
            [$this, 'testCompleteDataPassesValidation']
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
     * Property Test: Missing required field is rejected with specific error
     */
    private function testMissingRequiredFieldRejected(): array {
        // Generate complete data
        $data = $this->generateCompleteInstallationData();
        
        // Pick a random required field to remove
        $fieldToRemove = $this->requiredFields[array_rand($this->requiredFields)];
        unset($data[$fieldToRemove]);
        
        // Validate
        $result = $this->installationService->validateInstallationData($data);
        
        // Should be invalid
        if ($result['isValid']) {
            return [
                'success' => false,
                'message' => "Validation should fail when '$fieldToRemove' is missing"
            ];
        }
        
        // Should have error for the missing field
        $hasFieldError = false;
        foreach ($result['errors'] as $error) {
            if ($error['field'] === $fieldToRemove) {
                $hasFieldError = true;
                break;
            }
        }
        
        if (!$hasFieldError) {
            return [
                'success' => false,
                'message' => "Error should identify missing field '$fieldToRemove'"
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * Property Test: Empty string required field is rejected
     */
    private function testEmptyStringRequiredFieldRejected(): array {
        // Generate complete data
        $data = $this->generateCompleteInstallationData();
        
        // Pick a random required field to set to empty string
        $fieldToEmpty = $this->requiredFields[array_rand($this->requiredFields)];
        $data[$fieldToEmpty] = '';
        
        // Validate
        $result = $this->installationService->validateInstallationData($data);
        
        // Should be invalid
        if ($result['isValid']) {
            return [
                'success' => false,
                'message' => "Validation should fail when '$fieldToEmpty' is empty string"
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * Property Test: Complete data passes validation
     */
    private function testCompleteDataPassesValidation(): array {
        // Generate complete data
        $data = $this->generateCompleteInstallationData();
        
        // Validate
        $result = $this->installationService->validateInstallationData($data);
        
        // Should be valid
        if (!$result['isValid']) {
            $errorFields = array_map(function($e) { return $e['field']; }, $result['errors']);
            return [
                'success' => false,
                'message' => "Complete data should pass validation. Errors: " . implode(', ', $errorFields)
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
     * Generate complete installation data with all required fields
     */
    private function generateCompleteInstallationData(): array {
        return [
            // Vendor/Engineer Information
            'vendor_name' => 'Vendor-' . $this->generateRandomString(8),
            'engineer_name' => 'Engineer-' . $this->generateRandomString(8),
            'engineer_number' => '9' . rand(100000000, 999999999),
            
            // Router Section
            'router_serial' => 'RSN-' . $this->generateRandomString(12),
            'router_make' => 'Make-' . $this->generateRandomString(6),
            'router_model' => 'Model-' . $this->generateRandomString(6),
            'router_fixed' => Installation::YES,
            'router_status' => Installation::WORKING,
            
            // Adaptor Section
            'adaptor_installed' => Installation::YES,
            'adaptor_status' => Installation::WORKING,
            
            // LAN Cable Section
            'lan_cable_installed' => Installation::YES,
            'lan_cable_status' => Installation::WORKING,
            
            // Antenna Section
            'antenna_installed' => Installation::YES,
            'antenna_status' => Installation::WORKING,
            
            // GPS Section
            'gps_installed' => Installation::YES,
            'gps_status' => Installation::WORKING,
            
            // WiFi Section
            'wifi_installed' => Installation::YES,
            'wifi_status' => Installation::WORKING,
            
            // Airtel SIM Section
            'airtel_sim_installed' => Installation::YES,
            'airtel_sim_status' => Installation::WORKING,
            
            // Vodafone SIM Section
            'vodafone_sim_installed' => Installation::YES,
            'vodafone_sim_status' => Installation::WORKING,
            
            // JIO SIM Section
            'jio_sim_installed' => Installation::YES,
            'jio_sim_status' => Installation::WORKING,
            
            // Verification Section
            'signature_image' => 'uploads/signature_' . $this->generateRandomString(8) . '.png'
        ];
    }
}

// Run tests if executed directly
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    $test = new InstallationValidationPropertyTest();
    $success = $test->runTests();
    exit($success ? 0 : 1);
}
