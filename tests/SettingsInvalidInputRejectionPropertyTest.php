<?php
/**
 * Property Test for Invalid Input Rejection
 * **Feature: system-settings-module, Property 4: Invalid input rejection**
 * **Validates: Requirements 1.4, 4.2**
 */

require_once 'PropertyTestBase.php';
require_once __DIR__ . '/../repositories/SettingsRepository.php';
require_once __DIR__ . '/../models/SystemSetting.php';

class SettingsInvalidInputRejectionPropertyTest extends PropertyTestBase {
    private $settingsRepository;
    private $systemSetting;
    private $testSettingIds = [];
    private $testUserId = 2326; // Using existing admin user
    
    public function __construct() {
        parent::__construct();
        $this->settingsRepository = new SettingsRepository();
        $this->systemSetting = new SystemSetting();
    }
    
    /**
     * Test that invalid configuration values are rejected and return appropriate error messages
     */
    public function testInvalidInputRejection() {
        return $this->runPropertyTest(
            'Invalid input rejection',
            [$this, 'propertyInvalidInputRejection']
        );
    }
    
    /**
     * Property: For any invalid configuration value, when submitted, 
     * the system should reject the change and return appropriate error messages without modifying the stored value
     */
    public function propertyInvalidInputRejection() {
        // Generate a random test setting with validation rules
        $settingKey = 'test_invalid_' . $this->generateRandomString(8);
        $dataType = $this->generateRandomChoice(['string', 'integer', 'boolean', 'json']);
        
        // Create initial setting with validation rules
        $initialValue = $this->generateValidValueForType($dataType);
        $validationRules = $this->generateStrictValidationRulesForType($dataType);
        
        $settingData = [
            'category' => 'Test',
            'setting_key' => $settingKey,
            'setting_value' => $initialValue,
            'default_value' => $initialValue,
            'data_type' => $dataType,
            'description' => 'Test setting for invalid input rejection',
            'validation_rules' => $validationRules,
            'is_required' => true
        ];
        
        try {
            $created = $this->systemSetting->create($settingData);
            $this->testSettingIds[] = $created['id'];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to create test setting: ' . $e->getMessage(),
                'data' => $settingData
            ];
        }
        
        // Generate multiple types of invalid values and test each
        $invalidValues = $this->generateInvalidValuesForType($dataType, $validationRules);
        
        foreach ($invalidValues as $invalidValue) {
            // Store the original value before attempting update
            $originalSetting = $this->settingsRepository->findByKey($settingKey);
            $originalValue = $originalSetting['setting_value'];
            
            // Attempt to update with invalid value - should throw exception
            $exceptionThrown = false;
            $exceptionMessage = '';
            
            try {
                $this->settingsRepository->updateSetting($settingKey, $invalidValue['value'], $this->testUserId);
            } catch (Exception $e) {
                $exceptionThrown = true;
                $exceptionMessage = $e->getMessage();
            }
            
            // Verify that an exception was thrown
            if (!$exceptionThrown) {
                $this->cleanupTestData();
                return [
                    'success' => false,
                    'message' => 'Invalid value was accepted without throwing exception',
                    'data' => [
                        'setting_key' => $settingKey,
                        'invalid_value' => $invalidValue['value'],
                        'invalid_type' => $invalidValue['type'],
                        'data_type' => $dataType
                    ]
                ];
            }
            
            // Verify that the exception message is appropriate
            if (empty($exceptionMessage)) {
                $this->cleanupTestData();
                return [
                    'success' => false,
                    'message' => 'Exception was thrown but message was empty',
                    'data' => [
                        'setting_key' => $settingKey,
                        'invalid_value' => $invalidValue['value'],
                        'invalid_type' => $invalidValue['type']
                    ]
                ];
            }
            
            // Verify that the stored value was not modified
            $currentSetting = $this->settingsRepository->findByKey($settingKey);
            if ($currentSetting['setting_value'] !== $originalValue) {
                $this->cleanupTestData();
                return [
                    'success' => false,
                    'message' => 'Stored value was modified despite validation failure',
                    'data' => [
                        'setting_key' => $settingKey,
                        'original_value' => $originalValue,
                        'current_value' => $currentSetting['setting_value'],
                        'invalid_value_attempted' => $invalidValue['value'],
                        'invalid_type' => $invalidValue['type']
                    ]
                ];
            }
            
            // Verify that the updated_at timestamp was not changed
            if ($currentSetting['updated_at'] !== $originalSetting['updated_at']) {
                $this->cleanupTestData();
                return [
                    'success' => false,
                    'message' => 'updated_at timestamp was modified despite validation failure',
                    'data' => [
                        'setting_key' => $settingKey,
                        'original_timestamp' => $originalSetting['updated_at'],
                        'current_timestamp' => $currentSetting['updated_at'],
                        'invalid_value_attempted' => $invalidValue['value']
                    ]
                ];
            }
        }
        
        // Test empty/null values for required settings
        $emptyValues = [null, '', '   ', "\t\n"];
        
        foreach ($emptyValues as $emptyValue) {
            $originalSetting = $this->settingsRepository->findByKey($settingKey);
            $exceptionThrown = false;
            
            try {
                $this->settingsRepository->updateSetting($settingKey, $emptyValue, $this->testUserId);
            } catch (Exception $e) {
                $exceptionThrown = true;
                // Verify the exception mentions the required field
                if (strpos(strtolower($e->getMessage()), 'required') === false) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => 'Exception for required field did not mention "required"',
                        'data' => [
                            'setting_key' => $settingKey,
                            'exception_message' => $e->getMessage(),
                            'empty_value' => $emptyValue
                        ]
                    ];
                }
            }
            
            if (!$exceptionThrown) {
                $this->cleanupTestData();
                return [
                    'success' => false,
                    'message' => 'Empty value was accepted for required setting',
                    'data' => [
                        'setting_key' => $settingKey,
                        'empty_value' => $emptyValue
                    ]
                ];
            }
            
            // Verify value wasn't changed
            $currentSetting = $this->settingsRepository->findByKey($settingKey);
            if ($currentSetting['setting_value'] !== $originalSetting['setting_value']) {
                $this->cleanupTestData();
                return [
                    'success' => false,
                    'message' => 'Value was changed despite empty value rejection',
                    'data' => [
                        'setting_key' => $settingKey,
                        'original_value' => $originalSetting['setting_value'],
                        'current_value' => $currentSetting['setting_value'],
                        'empty_value_attempted' => $emptyValue
                    ]
                ];
            }
        }
        
        $this->cleanupTestData();
        
        return [
            'success' => true,
            'message' => 'Invalid input rejection verified',
            'data' => [
                'setting_key' => $settingKey,
                'data_type' => $dataType,
                'invalid_values_tested' => count($invalidValues),
                'empty_values_tested' => count($emptyValues),
                'total_rejections_tested' => count($invalidValues) + count($emptyValues)
            ]
        ];
    }
    
    /**
     * Generate a valid value for the given data type
     */
    private function generateValidValueForType($dataType) {
        switch ($dataType) {
            case 'integer':
                return '50'; // Within typical validation range
            case 'boolean':
                return 'true';
            case 'json':
                return '{"valid": true, "test": "data"}';
            case 'string':
            default:
                return 'ValidTestString';
        }
    }
    
    /**
     * Generate strict validation rules for testing
     */
    private function generateStrictValidationRulesForType($dataType) {
        switch ($dataType) {
            case 'integer':
                return json_encode([
                    'min_value' => 10,
                    'max_value' => 100
                ]);
            case 'string':
                return json_encode([
                    'min_length' => 5,
                    'max_length' => 20,
                    'pattern' => '^[a-zA-Z0-9]+$'
                ]);
            case 'boolean':
            case 'json':
            default:
                return '{}';
        }
    }
    
    /**
     * Generate various invalid values for the given data type
     */
    private function generateInvalidValuesForType($dataType, $validationRules) {
        $rules = json_decode($validationRules, true) ?: [];
        $invalidValues = [];
        
        switch ($dataType) {
            case 'integer':
                // Non-numeric values
                $invalidValues[] = ['value' => 'not_a_number', 'type' => 'non_numeric'];
                $invalidValues[] = ['value' => '12.5', 'type' => 'decimal'];
                $invalidValues[] = ['value' => 'abc123', 'type' => 'alphanumeric'];
                
                // Out of range values
                if (isset($rules['min_value'])) {
                    $invalidValues[] = ['value' => (string)($rules['min_value'] - 1), 'type' => 'below_minimum'];
                }
                if (isset($rules['max_value'])) {
                    $invalidValues[] = ['value' => (string)($rules['max_value'] + 1), 'type' => 'above_maximum'];
                }
                break;
                
            case 'boolean':
                $invalidValues[] = ['value' => 'maybe', 'type' => 'invalid_boolean'];
                $invalidValues[] = ['value' => '2', 'type' => 'invalid_numeric_boolean'];
                $invalidValues[] = ['value' => 'TRUE', 'type' => 'wrong_case'];
                $invalidValues[] = ['value' => 'FALSE', 'type' => 'wrong_case'];
                break;
                
            case 'json':
                $invalidValues[] = ['value' => '{invalid json}', 'type' => 'malformed_json'];
                $invalidValues[] = ['value' => '{"unclosed": true', 'type' => 'unclosed_json'];
                $invalidValues[] = ['value' => 'not json at all', 'type' => 'not_json'];
                break;
                
            case 'string':
                // Length violations
                if (isset($rules['min_length'])) {
                    $invalidValues[] = ['value' => str_repeat('a', $rules['min_length'] - 1), 'type' => 'too_short'];
                }
                if (isset($rules['max_length'])) {
                    $invalidValues[] = ['value' => str_repeat('a', $rules['max_length'] + 1), 'type' => 'too_long'];
                }
                
                // Pattern violations
                if (isset($rules['pattern'])) {
                    $invalidValues[] = ['value' => 'invalid-chars!@#', 'type' => 'pattern_violation'];
                    $invalidValues[] = ['value' => 'spaces not allowed', 'type' => 'pattern_violation_spaces'];
                }
                break;
        }
        
        return $invalidValues;
    }
    
    /**
     * Clean up test data
     */
    protected function cleanupTestData() {
        foreach ($this->testSettingIds as $id) {
            try {
                $this->systemSetting->delete($id);
            } catch (Exception $e) {
                // Ignore cleanup errors
            }
        }
        $this->testSettingIds = [];
    }
    
    /**
     * Run all property tests
     */
    public function runAllTests() {
        echo "Running Settings Invalid Input Rejection Property Tests\n";
        echo "=====================================================\n";
        
        $results = [];
        $results['invalid_input_rejection'] = $this->testInvalidInputRejection();
        
        $passed = array_sum($results);
        $total = count($results);
        
        echo "\nResults: $passed/$total tests passed\n";
        
        if ($passed === $total) {
            echo "✓ All invalid input rejection property tests passed!\n";
            return true;
        } else {
            echo "✗ Some invalid input rejection property tests failed!\n";
            return false;
        }
    }
}

// Run the tests if this file is executed directly
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    $test = new SettingsInvalidInputRejectionPropertyTest();
    $success = $test->runAllTests();
    exit($success ? 0 : 1);
}