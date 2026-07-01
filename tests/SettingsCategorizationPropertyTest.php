<?php
/**
 * Property Test for Settings Categorization Consistency
 * **Feature: system-settings-module, Property 1: Settings categorization consistency**
 * **Validates: Requirements 1.1, 2.1**
 */

require_once 'PropertyTestBase.php';
require_once __DIR__ . '/../models/SystemSetting.php';

class SettingsCategorizationPropertyTest extends PropertyTestBase {
    private $systemSetting;
    private $testSettingIds = [];
    
    public function __construct() {
        parent::__construct();
        $this->systemSetting = new SystemSetting();
    }
    
    /**
     * Test that settings are consistently categorized without duplication
     */
    public function testSettingsCategorizationConsistency() {
        return $this->runPropertyTest(
            'Settings categorization consistency',
            [$this, 'propertySettingsCategorizationConsistency']
        );
    }
    
    /**
     * Property: For any collection of system settings, when displayed, 
     * all settings should be grouped by their assigned category with no settings appearing in multiple categories
     */
    public function propertySettingsCategorizationConsistency() {
        // Generate random test settings with various categories
        $categories = ['General', 'Security', 'Email', 'Backup', 'Logging', 'Performance'];
        $testSettings = [];
        
        // Create 5-15 random settings across different categories
        $settingCount = $this->generateRandomInt(5, 15);
        
        for ($i = 0; $i < $settingCount; $i++) {
            $category = $this->generateRandomChoice($categories);
            $settingKey = 'test_setting_' . $this->generateRandomString(8);
            
            $settingData = [
                'category' => $category,
                'setting_key' => $settingKey,
                'setting_value' => $this->generateRandomString(10),
                'default_value' => $this->generateRandomString(10),
                'data_type' => $this->generateRandomChoice(['string', 'integer', 'boolean', 'json']),
                'description' => 'Test setting for categorization test',
                'validation_rules' => '{}',
                'is_required' => $this->generateRandomBool()
            ];
            
            try {
                $created = $this->systemSetting->create($settingData);
                $this->testSettingIds[] = $created['id'];
                $testSettings[] = $created;
            } catch (Exception $e) {
                return [
                    'success' => false,
                    'message' => 'Failed to create test setting: ' . $e->getMessage(),
                    'data' => $settingData
                ];
            }
        }
        
        // Get all settings grouped by category
        try {
            $groupedSettings = $this->systemSetting->getAllGroupedByCategory();
        } catch (Exception $e) {
            $this->cleanupTestData();
            return [
                'success' => false,
                'message' => 'Failed to get grouped settings: ' . $e->getMessage()
            ];
        }
        
        // Verify categorization consistency
        $allSettingKeys = [];
        $categorySettingCounts = [];
        
        foreach ($groupedSettings as $category => $settings) {
            $categorySettingCounts[$category] = count($settings);
            
            foreach ($settings as $setting) {
                // Check that setting belongs to the correct category
                if ($setting['category'] !== $category) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => "Setting '{$setting['setting_key']}' appears in category '{$category}' but belongs to '{$setting['category']}'",
                        'data' => ['setting' => $setting, 'expected_category' => $category]
                    ];
                }
                
                // Check for duplicate settings across categories
                if (in_array($setting['setting_key'], $allSettingKeys)) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => "Setting '{$setting['setting_key']}' appears in multiple categories",
                        'data' => ['duplicate_key' => $setting['setting_key']]
                    ];
                }
                
                $allSettingKeys[] = $setting['setting_key'];
            }
        }
        
        // Verify that all test settings are present and correctly categorized
        foreach ($testSettings as $testSetting) {
            $found = false;
            $expectedCategory = $testSetting['category'];
            
            if (isset($groupedSettings[$expectedCategory])) {
                foreach ($groupedSettings[$expectedCategory] as $setting) {
                    if ($setting['setting_key'] === $testSetting['setting_key']) {
                        $found = true;
                        break;
                    }
                }
            }
            
            if (!$found) {
                $this->cleanupTestData();
                return [
                    'success' => false,
                    'message' => "Test setting '{$testSetting['setting_key']}' not found in expected category '{$expectedCategory}'",
                    'data' => ['missing_setting' => $testSetting]
                ];
            }
        }
        
        // Verify settings within each category are ordered by setting_key
        foreach ($groupedSettings as $category => $settings) {
            if (count($settings) > 1) {
                $previousKey = '';
                foreach ($settings as $setting) {
                    if ($previousKey !== '' && strcmp($setting['setting_key'], $previousKey) < 0) {
                        $this->cleanupTestData();
                        return [
                            'success' => false,
                            'message' => "Settings in category '{$category}' are not ordered by setting_key",
                            'data' => ['category' => $category, 'current_key' => $setting['setting_key'], 'previous_key' => $previousKey]
                        ];
                    }
                    $previousKey = $setting['setting_key'];
                }
            }
        }
        
        $this->cleanupTestData();
        
        return [
            'success' => true,
            'message' => 'Settings categorization consistency verified',
            'data' => [
                'total_settings' => count($allSettingKeys),
                'categories_tested' => array_keys($categorySettingCounts),
                'settings_per_category' => $categorySettingCounts
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
        echo "Running Settings Categorization Property Tests\n";
        echo "==============================================\n";
        
        $results = [];
        $results['categorization_consistency'] = $this->testSettingsCategorizationConsistency();
        
        $passed = array_sum($results);
        $total = count($results);
        
        echo "\nResults: $passed/$total tests passed\n";
        
        if ($passed === $total) {
            echo "✓ All settings categorization property tests passed!\n";
            return true;
        } else {
            echo "✗ Some settings categorization property tests failed!\n";
            return false;
        }
    }
}