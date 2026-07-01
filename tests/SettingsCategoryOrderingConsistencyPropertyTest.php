<?php
/**
 * Property Test for Settings Category Ordering Consistency
 * **Feature: system-settings-module, Property 6: Category ordering consistency**
 * **Validates: Requirements 2.2**
 */

require_once 'PropertyTestBase.php';
require_once __DIR__ . '/../models/SystemSetting.php';

class SettingsCategoryOrderingConsistencyPropertyTest extends PropertyTestBase {
    private $systemSetting;
    private $testSettingIds = [];
    
    public function __construct() {
        parent::__construct();
        $this->systemSetting = new SystemSetting();
    }
    
    /**
     * Test that settings within categories are consistently ordered
     */
    public function testCategoryOrderingConsistency() {
        return $this->runPropertyTest(
            'Category ordering consistency',
            [$this, 'propertyCategoryOrderingConsistency']
        );
    }
    
    /**
     * Property: For any settings category containing multiple parameters, 
     * when displayed, the settings should appear in a consistent logical order (alphabetical by setting key)
     */
    public function propertyCategoryOrderingConsistency() {
        // Generate random test settings within the same category to test ordering
        $testCategory = 'TestCategory_' . $this->generateRandomString(6);
        $testSettings = [];
        
        // Create 3-8 random settings in the same category with different keys
        $settingCount = $this->generateRandomInt(3, 8);
        $settingKeys = [];
        
        // Generate unique setting keys
        for ($i = 0; $i < $settingCount; $i++) {
            do {
                $key = 'test_setting_' . $this->generateRandomString(8);
            } while (in_array($key, $settingKeys));
            $settingKeys[] = $key;
        }
        
        // Create settings in random order
        shuffle($settingKeys);
        
        foreach ($settingKeys as $settingKey) {
            $settingData = [
                'category' => $testCategory,
                'setting_key' => $settingKey,
                'setting_value' => $this->generateRandomString(10),
                'default_value' => $this->generateRandomString(10),
                'data_type' => $this->generateRandomChoice(['string', 'integer', 'boolean', 'json']),
                'description' => 'Test setting for ordering test',
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
        
        // Verify that our test category exists and has the expected settings
        if (!isset($groupedSettings[$testCategory])) {
            $this->cleanupTestData();
            return [
                'success' => false,
                'message' => "Test category '{$testCategory}' not found in grouped settings",
                'data' => ['available_categories' => array_keys($groupedSettings)]
            ];
        }
        
        $categorySettings = $groupedSettings[$testCategory];
        
        // Verify all test settings are present
        if (count($categorySettings) < count($testSettings)) {
            $this->cleanupTestData();
            return [
                'success' => false,
                'message' => "Expected {count($testSettings)} settings in category, found " . count($categorySettings),
                'data' => ['expected' => count($testSettings), 'actual' => count($categorySettings)]
            ];
        }
        
        // Extract just our test settings from the category
        $ourTestSettings = [];
        foreach ($categorySettings as $setting) {
            if (in_array($setting['setting_key'], $settingKeys)) {
                $ourTestSettings[] = $setting;
            }
        }
        
        // Verify ordering consistency - settings should be ordered alphabetically by setting_key
        $expectedOrder = $settingKeys;
        sort($expectedOrder); // Sort alphabetically
        
        $actualOrder = [];
        foreach ($ourTestSettings as $setting) {
            $actualOrder[] = $setting['setting_key'];
        }
        
        if ($actualOrder !== $expectedOrder) {
            $this->cleanupTestData();
            return [
                'success' => false,
                'message' => "Settings in category '{$testCategory}' are not ordered alphabetically by setting_key",
                'data' => [
                    'expected_order' => $expectedOrder,
                    'actual_order' => $actualOrder,
                    'category' => $testCategory
                ]
            ];
        }
        
        // Test multiple calls to ensure consistency
        for ($i = 0; $i < 3; $i++) {
            try {
                $groupedSettingsRecheck = $this->systemSetting->getAllGroupedByCategory();
                $categorySettingsRecheck = $groupedSettingsRecheck[$testCategory];
                
                $recheckOrder = [];
                foreach ($categorySettingsRecheck as $setting) {
                    if (in_array($setting['setting_key'], $settingKeys)) {
                        $recheckOrder[] = $setting['setting_key'];
                    }
                }
                
                if ($recheckOrder !== $expectedOrder) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => "Settings ordering is inconsistent across multiple calls (call " . ($i + 1) . ")",
                        'data' => [
                            'expected_order' => $expectedOrder,
                            'recheck_order' => $recheckOrder,
                            'call_number' => $i + 1
                        ]
                    ];
                }
            } catch (Exception $e) {
                $this->cleanupTestData();
                return [
                    'success' => false,
                    'message' => 'Failed to recheck grouped settings: ' . $e->getMessage()
                ];
            }
        }
        
        $this->cleanupTestData();
        
        return [
            'success' => true,
            'message' => 'Category ordering consistency verified',
            'data' => [
                'test_category' => $testCategory,
                'settings_count' => count($ourTestSettings),
                'expected_order' => $expectedOrder,
                'verified_consistent' => true
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
        echo "Running Settings Category Ordering Consistency Property Tests\n";
        echo "============================================================\n";
        
        $results = [];
        $results['category_ordering_consistency'] = $this->testCategoryOrderingConsistency();
        
        $passed = array_sum($results);
        $total = count($results);
        
        echo "\nResults: $passed/$total tests passed\n";
        
        if ($passed === $total) {
            echo "✓ All settings category ordering consistency property tests passed!\n";
            return true;
        } else {
            echo "✗ Some settings category ordering consistency property tests failed!\n";
            return false;
        }
    }
}