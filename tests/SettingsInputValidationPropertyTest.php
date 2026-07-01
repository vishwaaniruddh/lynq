<?php
/**
 * Property Test for Settings Input Validation Enforcement
 * **Feature: system-settings-module, Property 2: Input validation enforcement**
 * **Validates: Requirements 1.2, 4.1**
 */

require_once 'PropertyTestBase.php';
require_once __DIR__ . '/../models/SystemSetting.php';

class SettingsInputValidationPropertyTest extends PropertyTestBase {
    private $systemSetting;
    private $testSettingIds = [];
    
    public function __construct() {
        parent::__construct();
        $this->systemSetting = new SystemSetting();
    }
    
    /**
     * Test that input validation is consistently enforced
     */
    public function testInputValidationEnforcement() {
        return $this->runPropertyTest(
            'Input validation enforcement',
            [$this, 'propertyInputValidationEnforcement']
        );
    }
    
    /**
     * Property: For any setting key and value pair, when validation is performed, 
     * the result should match the validation rules defined for that setting's data type and constraints
     */
    public function propertyInputValidationEnforcement() {
        // Create a test setting with specific validation rules
        $dataType = $this->generateRandomChoice(['string', 'integer', 'boolean', 'json']);
        $validationRules = $this->generateValidationRules($dataType);
        
        $settingData = [
            'category' => 'Test',
            'setting_key' => 'test_validation_' . $this->generateRandomString(8),
            'setting_value' => null,
            'default_value' => $this->generateValidValueForStorage($dataType, $validationRules),
            'data_type' => $dataType,
            'description' => 'Test setting for validation test',
            'validation_rules' => json_encode($validationRules),
            'is_required' => $this->generateRandomBool()
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
        
        // Test with valid value
        $validValue = $this->generateValidValue($dataType, $validationRules);
        $validationResult = $this->systemSetting->validateValue($validValue, $validationRules, $dataType);
        
        if (!$validationResult['valid']) {
            $this->cleanupTestData();
            return [
                'success' => false,
                'message' => 'Valid value was rejected by validation',
                'data' => [
                    'value' => $validValue,
                    'data_type' => $dataType,
                    'validation_rules' => $validationRules,
                    'errors' => $validationResult['errors']
                ]
            ];
        }
        
        // Test with invalid value
        $invalidValue = $this->generateInvalidValue($dataType, $validationRules);
        if ($invalidValue !== null) { // null means we couldn't generate an invalid value for this rule set
            $invalidValidationResult = $this->systemSetting->validateValue($invalidValue, $validationRules, $dataType);
            
            if ($invalidValidationResult['valid']) {
                $this->cleanupTestData();
                return [
                    'success' => false,
                    'message' => 'Invalid value was accepted by validation',
                    'data' => [
                        'value' => $invalidValue,
                        'data_type' => $dataType,
                        'validation_rules' => $validationRules
                    ]
                ];
            }
        }
        
        // Test data type casting
        try {
            $castedValue = $this->systemSetting->castValue($validValue, $dataType);
            $expectedType = $this->getExpectedPhpType($dataType);
            
            if ($expectedType && gettype($castedValue) !== $expectedType && $castedValue !== null) {
                $this->cleanupTestData();
                return [
                    'success' => false,
                    'message' => 'Value was not cast to expected type',
                    'data' => [
                        'value' => $validValue,
                        'casted_value' => $castedValue,
                        'expected_type' => $expectedType,
                        'actual_type' => gettype($castedValue),
                        'data_type' => $dataType
                    ]
                ];
            }
        } catch (Exception $e) {
            $this->cleanupTestData();
            return [
                'success' => false,
                'message' => 'Type casting failed: ' . $e->getMessage(),
                'data' => [
                    'value' => $validValue,
                    'data_type' => $dataType
                ]
            ];
        }
        
        $this->cleanupTestData();
        
        return [
            'success' => true,
            'message' => 'Input validation enforcement verified',
            'data' => [
                'data_type' => $dataType,
                'validation_rules' => $validationRules,
                'valid_value' => $validValue,
                'invalid_value' => $invalidValue
            ]
        ];
    }
    
    /**
     * Generate validation rules for a given data type
     */
    private function generateValidationRules($dataType) {
        $rules = [];
        
        // Sometimes make it required
        if ($this->generateRandomBool()) {
            $rules['required'] = true;
        }
        
        // Decide whether to use allowed_values or other constraints (not both)
        $useAllowedValues = $this->generateRandomBool();
        
        if ($useAllowedValues && $dataType !== 'json') {
            // Use allowed values instead of other constraints
            switch ($dataType) {
                case 'string':
                    $rules['allowed_values'] = ['option1', 'option2', 'option3'];
                    break;
                case 'integer':
                    $rules['allowed_values'] = [1, 2, 3, 5, 10];
                    break;
                case 'boolean':
                    $rules['allowed_values'] = [true, false];
                    break;
            }
        } else {
            // Use other constraint types
            switch ($dataType) {
                case 'string':
                    if ($this->generateRandomBool()) {
                        $rules['min_length'] = $this->generateRandomInt(1, 5);
                    }
                    if ($this->generateRandomBool()) {
                        $rules['max_length'] = $this->generateRandomInt(10, 50);
                    }
                    if ($this->generateRandomBool()) {
                        $rules['pattern'] = '^[a-zA-Z0-9_-]+$';
                    }
                    break;
                    
                case 'integer':
                    if ($this->generateRandomBool()) {
                        $rules['min_value'] = $this->generateRandomInt(1, 10);
                    }
                    if ($this->generateRandomBool()) {
                        $rules['max_value'] = $this->generateRandomInt(50, 100);
                    }
                    break;
                    
                case 'boolean':
                    // Boolean doesn't need additional rules
                    break;
                    
                case 'json':
                    if ($this->generateRandomBool()) {
                        $rules['schema'] = ['type' => 'object'];
                    }
                    break;
            }
        }
        
        return $rules;
    }
    
    /**
     * Generate a valid value for storage (string format)
     */
    private function generateValidValueForStorage($dataType, $rules) {
        $value = $this->generateValidValue($dataType, $rules);
        
        if ($dataType === 'json' && is_array($value)) {
            return json_encode($value);
        }
        
        return (string) $value;
    }
    
    /**
     * Generate a valid value for the given data type and rules
     */
    private function generateValidValue($dataType, $rules) {
        if (isset($rules['allowed_values'])) {
            return $this->generateRandomChoice($rules['allowed_values']);
        }
        
        switch ($dataType) {
            case 'string':
                $length = 10;
                if (isset($rules['min_length'])) {
                    $length = max($length, $rules['min_length']);
                }
                if (isset($rules['max_length'])) {
                    $length = min($length, $rules['max_length']);
                }
                
                $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789_-';
                if (isset($rules['pattern']) && $rules['pattern'] === '^[a-zA-Z0-9_-]+$') {
                    return $this->generateRandomString($length, $chars);
                }
                return $this->generateRandomString($length);
                
            case 'integer':
                $min = isset($rules['min_value']) ? $rules['min_value'] : 1;
                $max = isset($rules['max_value']) ? $rules['max_value'] : 100;
                return $this->generateRandomInt($min, $max);
                
            case 'boolean':
                return $this->generateRandomBool();
                
            case 'json':
                return ['key' => 'value', 'number' => 42];
                
            default:
                return 'default_value';
        }
    }
    
    /**
     * Generate an invalid value for the given data type and rules
     */
    private function generateInvalidValue($dataType, $rules) {
        // If required is true, return null/empty to test required validation
        if (isset($rules['required']) && $rules['required']) {
            return $dataType === 'string' ? '' : null;
        }
        
        if (isset($rules['allowed_values'])) {
            // Return a value not in allowed values
            switch ($dataType) {
                case 'string':
                    return 'invalid_option';
                case 'integer':
                    return 999;
                case 'boolean':
                    return null; // Can't really have invalid boolean
            }
        }
        
        switch ($dataType) {
            case 'string':
                if (isset($rules['min_length']) && $rules['min_length'] > 1) {
                    return $this->generateRandomString($rules['min_length'] - 1);
                }
                if (isset($rules['max_length'])) {
                    return $this->generateRandomString($rules['max_length'] + 5);
                }
                if (isset($rules['pattern']) && $rules['pattern'] === '^[a-zA-Z0-9_-]+$') {
                    return 'invalid@#$%';
                }
                break;
                
            case 'integer':
                if (isset($rules['min_value'])) {
                    return $rules['min_value'] - 1;
                }
                if (isset($rules['max_value'])) {
                    return $rules['max_value'] + 1;
                }
                break;
                
            case 'json':
                return 'invalid json string';
                
            case 'boolean':
                // Hard to make invalid boolean, return null
                return null;
        }
        
        return null; // Couldn't generate invalid value
    }
    
    /**
     * Get expected PHP type for data type
     */
    private function getExpectedPhpType($dataType) {
        switch ($dataType) {
            case 'string':
                return 'string';
            case 'integer':
                return 'integer';
            case 'boolean':
                return 'boolean';
            case 'json':
                return 'array';
            default:
                return null;
        }
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
        echo "Running Settings Input Validation Property Tests\n";
        echo "===============================================\n";
        
        $results = [];
        $results['input_validation_enforcement'] = $this->testInputValidationEnforcement();
        
        $passed = array_sum($results);
        $total = count($results);
        
        echo "\nResults: $passed/$total tests passed\n";
        
        if ($passed === $total) {
            echo "✓ All settings input validation property tests passed!\n";
            return true;
        } else {
            echo "✗ Some settings input validation property tests failed!\n";
            return false;
        }
    }
}