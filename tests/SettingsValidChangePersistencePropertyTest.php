<?php
/**
 * Property Test for Valid Change Persistence
 * **Feature: system-settings-module, Property 3: Valid change persistence**
 * **Validates: Requirements 1.3, 4.3**
 */

require_once 'PropertyTestBase.php';
require_once __DIR__ . '/../repositories/SettingsRepository.php';
require_once __DIR__ . '/../models/SystemSetting.php';

class SettingsValidChangePersistencePropertyTest extends PropertyTestBase {
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
     * Test that valid configuration changes are immediately persisted and retrievable
     */
    public function testValidChangePersistence() {
        return $this->runPropertyTest(
            'Valid change persistence',
            [$this, 'propertyValidChangePersistence']
        );
    }
    
    /**
     * Property: For any valid configuration change, when submitted by an authorized user, 
     * the new value should be immediately persisted to the database and retrievable
     */
    public function propertyValidChangePersistence() {
        // Generate a random test setting
        $settingKey = 'test_persistence_' . $this->generateRandomString(8);
        $dataType = $this->generateRandomChoice(['string', 'integer', 'boolean', 'json']);
        
        // Create initial setting
        $initialValue = $this->generateValidValueForType($dataType);
        $defaultValue = $this->generateValidValueForType($dataType);
        
        $settingData = [
            'category' => 'Test',
            'setting_key' => $settingKey,
            'setting_value' => $initialValue,
            'default_value' => $defaultValue,
            'data_type' => $dataType,
            'description' => 'Test setting for persistence validation',
            'validation_rules' => $this->generateValidationRulesForType($dataType),
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
        
        // Generate a new valid value for the setting
        $newValue = $this->generateValidValueForType($dataType);
        
        // Ensure the new value is different from the initial value
        $attempts = 0;
        while ($newValue === $initialValue && $attempts < 10) {
            $newValue = $this->generateValidValueForType($dataType);
            $attempts++;
        }
        
        // Update the setting using the repository
        try {
            $updateResult = $this->settingsRepository->updateSetting($settingKey, $newValue, $this->testUserId);
            
            if (!$updateResult) {
                $this->cleanupTestData();
                return [
                    'success' => false,
                    'message' => 'updateSetting returned false for valid change',
                    'data' => [
                        'setting_key' => $settingKey,
                        'old_value' => $initialValue,
                        'new_value' => $newValue,
                        'data_type' => $dataType
                    ]
                ];
            }
        } catch (Exception $e) {
            $this->cleanupTestData();
            return [
                'success' => false,
                'message' => 'updateSetting threw exception for valid change: ' . $e->getMessage(),
                'data' => [
                    'setting_key' => $settingKey,
                    'old_value' => $initialValue,
                    'new_value' => $newValue,
                    'data_type' => $dataType
                ]
            ];
        }
        
        // Immediately retrieve the setting to verify persistence
        try {
            $retrievedSetting = $this->settingsRepository->findByKey($settingKey);
            
            if (!$retrievedSetting) {
                $this->cleanupTestData();
                return [
                    'success' => false,
                    'message' => 'Setting not found after update',
                    'data' => ['setting_key' => $settingKey]
                ];
            }
            
            // Verify the value was persisted correctly
            if ($retrievedSetting['setting_value'] !== $newValue) {
                $this->cleanupTestData();
                return [
                    'success' => false,
                    'message' => 'Retrieved value does not match the updated value',
                    'data' => [
                        'setting_key' => $settingKey,
                        'expected_value' => $newValue,
                        'actual_value' => $retrievedSetting['setting_value'],
                        'data_type' => $dataType
                    ]
                ];
            }
            
            // Verify the updated_at timestamp was updated (allow for same second)
            $originalTime = strtotime($created['updated_at']);
            $currentTime = strtotime($retrievedSetting['updated_at']);
            
            if ($currentTime < $originalTime) {
                $this->cleanupTestData();
                return [
                    'success' => false,
                    'message' => 'updated_at timestamp went backwards',
                    'data' => [
                        'setting_key' => $settingKey,
                        'original_timestamp' => $created['updated_at'],
                        'current_timestamp' => $retrievedSetting['updated_at']
                    ]
                ];
            }
            
        } catch (Exception $e) {
            $this->cleanupTestData();
            return [
                'success' => false,
                'message' => 'Failed to retrieve setting after update: ' . $e->getMessage(),
                'data' => ['setting_key' => $settingKey]
            ];
        }
        
        // Test multiple consecutive updates to ensure persistence works consistently
        for ($i = 0; $i < 3; $i++) {
            $anotherNewValue = $this->generateValidValueForType($dataType);
            
            try {
                $this->settingsRepository->updateSetting($settingKey, $anotherNewValue, $this->testUserId);
                $retrievedAgain = $this->settingsRepository->findByKey($settingKey);
                
                if ($retrievedAgain['setting_value'] !== $anotherNewValue) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => "Multiple update test failed at iteration $i",
                        'data' => [
                            'setting_key' => $settingKey,
                            'expected_value' => $anotherNewValue,
                            'actual_value' => $retrievedAgain['setting_value'],
                            'iteration' => $i
                        ]
                    ];
                }
            } catch (Exception $e) {
                $this->cleanupTestData();
                return [
                    'success' => false,
                    'message' => "Multiple update test threw exception at iteration $i: " . $e->getMessage(),
                    'data' => ['iteration' => $i]
                ];
            }
        }
        
        $this->cleanupTestData();
        
        return [
            'success' => true,
            'message' => 'Valid change persistence verified',
            'data' => [
                'setting_key' => $settingKey,
                'data_type' => $dataType,
                'initial_value' => $initialValue,
                'final_value' => $anotherNewValue ?? $newValue,
                'updates_tested' => 4
            ]
        ];
    }
    
    /**
     * Generate a valid value for the given data type
     */
    private function generateValidValueForType($dataType) {
        switch ($dataType) {
            case 'integer':
                return (string)$this->generateRandomInt(1, 1000);
            case 'boolean':
                return $this->generateRandomChoice(['true', 'false', '1', '0']);
            case 'json':
                $jsonData = [
                    'key' => $this->generateRandomString(5),
                    'value' => $this->generateRandomInt(1, 100),
                    'enabled' => $this->generateRandomBool()
                ];
                return json_encode($jsonData);
            case 'string':
            default:
                return $this->generateRandomString($this->generateRandomInt(5, 50));
        }
    }
    
    /**
     * Generate validation rules for the given data type
     */
    private function generateValidationRulesForType($dataType) {
        switch ($dataType) {
            case 'integer':
                return json_encode([
                    'min_value' => 1,
                    'max_value' => 10000
                ]);
            case 'string':
                return json_encode([
                    'min_length' => 1,
                    'max_length' => 255
                ]);
            case 'boolean':
            case 'json':
            default:
                return '{}';
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
        echo "Running Settings Valid Change Persistence Property Tests\n";
        echo "======================================================\n";
        
        $results = [];
        $results['valid_change_persistence'] = $this->testValidChangePersistence();
        
        $passed = array_sum($results);
        $total = count($results);
        
        echo "\nResults: $passed/$total tests passed\n";
        
        if ($passed === $total) {
            echo "✓ All valid change persistence property tests passed!\n";
            return true;
        } else {
            echo "✗ Some valid change persistence property tests failed!\n";
            return false;
        }
    }
}

// Run the tests if this file is executed directly
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    $test = new SettingsValidChangePersistencePropertyTest();
    $success = $test->runAllTests();
    exit($success ? 0 : 1);
}