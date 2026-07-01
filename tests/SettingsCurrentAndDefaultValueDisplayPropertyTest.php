<?php
/**
 * Property Test for Settings Current and Default Value Display
 * **Feature: system-settings-module, Property 7: Current and default value display**
 * **Validates: Requirements 3.1**
 */

require_once 'PropertyTestBase.php';
require_once __DIR__ . '/../models/SystemSetting.php';

class SettingsCurrentAndDefaultValueDisplayPropertyTest extends PropertyTestBase {
    private $systemSetting;
    private $testSettingIds = [];
    
    public function __construct() {
        parent::__construct();
        $this->systemSetting = new SystemSetting();
    }
    
    /**
     * Test that settings display both current and default values
     */
    public function testCurrentAndDefaultValueDisplay() {
        return $this->runPropertyTest(
            'Current and default value display',
            [$this, 'propertyCurrentAndDefaultValueDisplay']
        );
    }
    
    /**
     * Property: For any configuration parameter, when displayed, 
     * both the current value and default value should be included in the response
     */
    public function propertyCurrentAndDefaultValueDisplay() {
        // Generate random test settings with different current and default values
        $testSettings = [];
        $settingCount = $this->generateRandomInt(3, 8);
        
        for ($i = 0; $i < $settingCount; $i++) {
            $settingKey = 'test_setting_' . $this->generateRandomString(8);
            $dataType = $this->generateRandomChoice(['string', 'integer', 'boolean', 'json']);
            
            // Generate different current and default values based on data type
            switch ($dataType) {
                case 'string':
                    $defaultValue = $this->generateRandomString(10);
                    $currentValue = $this->generateRandomString(12); // Different from default
                    break;
                case 'integer':
                    $defaultValue = (string)$this->generateRandomInt(1, 100);
                    $currentValue = (string)$this->generateRandomInt(101, 200); // Different from default
                    break;
                case 'boolean':
                    $defaultValue = 'true';
                    $currentValue = 'false'; // Different from default
                    break;
                case 'json':
                    $defaultValue = json_encode(['default' => true, 'value' => $this->generateRandomInt(1, 50)]);
                    $currentValue = json_encode(['default' => false, 'value' => $this->generateRandomInt(51, 100)]);
                    break;
            }
            
            $settingData = [
                'category' => 'TestCategory',
                'setting_key' => $settingKey,
                'setting_value' => $currentValue,
                'default_value' => $defaultValue,
                'data_type' => $dataType,
                'description' => 'Test setting for value display test',
                'validation_rules' => '{}',
                'is_required' => $this->generateRandomBool()
            ];
            
            try {
                $created = $this->systemSetting->create($settingData);
                $this->testSettingIds[] = $created['id'];
                $testSettings[] = [
                    'id' => $created['id'],
                    'key' => $settingKey,
                    'current_value' => $currentValue,
                    'default_value' => $defaultValue,
                    'data_type' => $dataType
                ];
            } catch (Exception $e) {
                return [
                    'success' => false,
                    'message' => 'Failed to create test setting: ' . $e->getMessage(),
                    'data' => $settingData
                ];
            }
        }
        
        // Test 1: Check individual setting retrieval includes both values
        foreach ($testSettings as $testSetting) {
            try {
                $retrievedSetting = $this->systemSetting->findByKey($testSetting['key']);
                
                if (!$retrievedSetting) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => "Setting with key '{$testSetting['key']}' not found",
                        'data' => ['missing_key' => $testSetting['key']]
                    ];
                }
                
                // Verify both current and default values are present
                if (!isset($retrievedSetting['setting_value'])) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => "Current value (setting_value) missing for setting '{$testSetting['key']}'",
                        'data' => ['setting' => $retrievedSetting]
                    ];
                }
                
                if (!isset($retrievedSetting['default_value'])) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => "Default value missing for setting '{$testSetting['key']}'",
                        'data' => ['setting' => $retrievedSetting]
                    ];
                }
                
                // Verify values match what we set
                if ($retrievedSetting['setting_value'] !== $testSetting['current_value']) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => "Current value mismatch for setting '{$testSetting['key']}'",
                        'data' => [
                            'expected' => $testSetting['current_value'],
                            'actual' => $retrievedSetting['setting_value']
                        ]
                    ];
                }
                
                if ($retrievedSetting['default_value'] !== $testSetting['default_value']) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => "Default value mismatch for setting '{$testSetting['key']}'",
                        'data' => [
                            'expected' => $testSetting['default_value'],
                            'actual' => $retrievedSetting['default_value']
                        ]
                    ];
                }
                
            } catch (Exception $e) {
                $this->cleanupTestData();
                return [
                    'success' => false,
                    'message' => 'Failed to retrieve setting: ' . $e->getMessage()
                ];
            }
        }
        
        // Test 2: Check grouped settings also include both values
        try {
            $groupedSettings = $this->systemSetting->getAllGroupedByCategory();
            
            if (!isset($groupedSettings['TestCategory'])) {
                $this->cleanupTestData();
                return [
                    'success' => false,
                    'message' => 'TestCategory not found in grouped settings',
                    'data' => ['available_categories' => array_keys($groupedSettings)]
                ];
            }
            
            $categorySettings = $groupedSettings['TestCategory'];
            
            foreach ($testSettings as $testSetting) {
                $found = false;
                
                foreach ($categorySettings as $setting) {
                    if ($setting['setting_key'] === $testSetting['key']) {
                        $found = true;
                        
                        // Verify both values are present in grouped display
                        if (!isset($setting['setting_value'])) {
                            $this->cleanupTestData();
                            return [
                                'success' => false,
                                'message' => "Current value missing in grouped display for '{$testSetting['key']}'",
                                'data' => ['setting' => $setting]
                            ];
                        }
                        
                        if (!isset($setting['default_value'])) {
                            $this->cleanupTestData();
                            return [
                                'success' => false,
                                'message' => "Default value missing in grouped display for '{$testSetting['key']}'",
                                'data' => ['setting' => $setting]
                            ];
                        }
                        
                        // Verify values are correct
                        if ($setting['setting_value'] !== $testSetting['current_value']) {
                            $this->cleanupTestData();
                            return [
                                'success' => false,
                                'message' => "Current value mismatch in grouped display for '{$testSetting['key']}'",
                                'data' => [
                                    'expected' => $testSetting['current_value'],
                                    'actual' => $setting['setting_value']
                                ]
                            ];
                        }
                        
                        if ($setting['default_value'] !== $testSetting['default_value']) {
                            $this->cleanupTestData();
                            return [
                                'success' => false,
                                'message' => "Default value mismatch in grouped display for '{$testSetting['key']}'",
                                'data' => [
                                    'expected' => $testSetting['default_value'],
                                    'actual' => $setting['default_value']
                                ]
                            ];
                        }
                        
                        break;
                    }
                }
                
                if (!$found) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => "Setting '{$testSetting['key']}' not found in grouped display",
                        'data' => ['missing_key' => $testSetting['key']]
                    ];
                }
            }
            
        } catch (Exception $e) {
            $this->cleanupTestData();
            return [
                'success' => false,
                'message' => 'Failed to get grouped settings: ' . $e->getMessage()
            ];
        }
        
        // Test 3: Verify that null values are handled properly
        $nullTestKey = 'test_null_setting_' . $this->generateRandomString(6);
        $nullSettingData = [
            'category' => 'TestCategory',
            'setting_key' => $nullTestKey,
            'setting_value' => null,
            'default_value' => 'default_value',
            'data_type' => 'string',
            'description' => 'Test setting with null current value',
            'validation_rules' => '{}',
            'is_required' => false
        ];
        
        try {
            $nullSetting = $this->systemSetting->create($nullSettingData);
            $this->testSettingIds[] = $nullSetting['id'];
            
            $retrievedNullSetting = $this->systemSetting->findByKey($nullTestKey);
            
            // Verify both fields exist even when current value is null
            if (!array_key_exists('setting_value', $retrievedNullSetting)) {
                $this->cleanupTestData();
                return [
                    'success' => false,
                    'message' => "setting_value field missing for null value test",
                    'data' => ['setting' => $retrievedNullSetting]
                ];
            }
            
            if (!isset($retrievedNullSetting['default_value'])) {
                $this->cleanupTestData();
                return [
                    'success' => false,
                    'message' => "default_value field missing for null value test",
                    'data' => ['setting' => $retrievedNullSetting]
                ];
            }
            
        } catch (Exception $e) {
            $this->cleanupTestData();
            return [
                'success' => false,
                'message' => 'Failed to test null value handling: ' . $e->getMessage()
            ];
        }
        
        $this->cleanupTestData();
        
        return [
            'success' => true,
            'message' => 'Current and default value display verified',
            'data' => [
                'settings_tested' => count($testSettings),
                'individual_retrieval_verified' => true,
                'grouped_display_verified' => true,
                'null_value_handling_verified' => true
            ]
        ];
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
        echo "Running Settings Current and Default Value Display Property Tests\n";
        echo "================================================================\n";
        
        $results = [];
        $results['current_and_default_value_display'] = $this->testCurrentAndDefaultValueDisplay();
        
        $passed = array_sum($results);
        $total = count($results);
        
        echo "\nResults: $passed/$total tests passed\n";
        
        if ($passed === $total) {
            echo "✓ All settings current and default value display property tests passed!\n";
            return true;
        } else {
            echo "✗ Some settings current and default value display property tests failed!\n";
            return false;
        }
    }
}