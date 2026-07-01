<?php
/**
 * Property Test for Settings Batch Validation Atomicity
 * **Feature: system-settings-module, Property 9: Batch validation atomicity**
 * **Validates: Requirements 4.4**
 */

require_once 'PropertyTestBase.php';
require_once __DIR__ . '/../services/SettingsService.php';
require_once __DIR__ . '/../models/SystemSetting.php';
require_once __DIR__ . '/../models/SettingsAudit.php';

class SettingsBatchValidationAtomicityPropertyTest extends PropertyTestBase {
    private $settingsService;
    private $systemSetting;
    private $settingsAudit;
    private $testSettingIds = [];
    private $testAuditIds = [];
    private $testUserId;
    
    public function __construct() {
        parent::__construct();
        $this->settingsService = new SettingsService();
        $this->systemSetting = new SystemSetting();
        $this->settingsAudit = new SettingsAudit();
        
        // Create a test user for operations
        $this->createTestUser();
    }
    
    /**
     * Test that batch validation atomicity is maintained
     */
    public function testBatchValidationAtomicity() {
        return $this->runPropertyTest(
            'Batch validation atomicity',
            [$this, 'propertyBatchValidationAtomicity']
        );
    }
    
    /**
     * Property: For any collection of setting changes submitted simultaneously, 
     * either all changes should be validated and saved, or none should be saved if any validation fails
     */
    public function propertyBatchValidationAtomicity() {
        // Create multiple test settings with different validation rules
        $testSettings = [];
        $settingCount = $this->generateRandomInt(3, 8);
        
        for ($i = 0; $i < $settingCount; $i++) {
            $settingKey = 'test_batch_setting_' . $this->generateRandomString(8);
            $dataType = $this->generateRandomChoice(['string', 'integer', 'boolean']);
            
            // Create validation rules based on data type
            $validationRules = [];
            if ($dataType === 'string') {
                $validationRules = [
                    'required' => true, // Make it required so empty string fails
                    'min_length' => 2,
                    'max_length' => 20,
                    'pattern' => '^[a-zA-Z0-9_-]+$'
                ];
            } elseif ($dataType === 'integer') {
                $validationRules = [
                    'required' => true,
                    'min_value' => 1,
                    'max_value' => 100
                ];
            } elseif ($dataType === 'boolean') {
                $validationRules = [
                    'required' => true
                ];
            }
            
            $settingData = [
                'category' => $this->generateRandomChoice(['General', 'Security', 'Email']),
                'setting_key' => $settingKey,
                'setting_value' => $this->generateValidValue($dataType, $validationRules),
                'default_value' => $this->generateValidValue($dataType, $validationRules),
                'data_type' => $dataType,
                'description' => 'Test setting for batch validation atomicity test',
                'validation_rules' => json_encode($validationRules),
                'is_required' => true // Make all test settings required
            ];
            
            try {
                $created = $this->systemSetting->create($settingData);
                $this->testSettingIds[] = $created['id'];
                $testSettings[] = [
                    'key' => $settingKey,
                    'id' => $created['id'],
                    'data_type' => $dataType,
                    'validation_rules' => $validationRules,
                    'initial_value' => $created['setting_value']
                ];
            } catch (Exception $e) {
                $this->cleanupTestData();
                return [
                    'success' => false,
                    'message' => 'Failed to create test setting: ' . $e->getMessage(),
                    'data' => $settingData
                ];
            }
        }
        
        // Test Case 1: All valid changes should succeed atomically
        $validChanges = [];
        foreach ($testSettings as $setting) {
            $validChanges[$setting['key']] = $this->generateValidValue($setting['data_type'], $setting['validation_rules']);
        }
        
        try {
            $result = $this->settingsService->updateMultipleSettings($validChanges, $this->testUserId);
            
            if (!$result['valid'] || !$result['success']) {
                $this->cleanupTestData();
                return [
                    'success' => false,
                    'message' => 'Valid batch update failed when it should have succeeded',
                    'data' => ['result' => $result, 'changes' => $validChanges]
                ];
            }
            
            // Verify all settings were actually updated
            foreach ($testSettings as $setting) {
                $updatedSetting = $this->systemSetting->findByKey($setting['key']);
                $expectedValue = $validChanges[$setting['key']];
                $actualValue = $updatedSetting['setting_value'];
                
                // Handle data type conversions for comparison
                if ($setting['data_type'] === 'boolean') {
                    // Normalize boolean values for comparison
                    $expectedNormalized = in_array($expectedValue, ['true', '1']) ? '1' : '0';
                    $actualNormalized = in_array($actualValue, ['true', '1']) ? '1' : '0';
                    if ($expectedNormalized !== $actualNormalized) {
                        $this->cleanupTestData();
                        return [
                            'success' => false,
                            'message' => 'Boolean setting was not updated correctly in valid batch operation',
                            'data' => [
                                'setting_key' => $setting['key'],
                                'expected_value' => $expectedValue,
                                'actual_value' => $actualValue,
                                'expected_normalized' => $expectedNormalized,
                                'actual_normalized' => $actualNormalized
                            ]
                        ];
                    }
                } elseif ($actualValue !== $expectedValue) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => 'Setting was not updated in valid batch operation',
                        'data' => [
                            'setting_key' => $setting['key'],
                            'expected_value' => $expectedValue,
                            'actual_value' => $actualValue,
                            'data_type' => $setting['data_type']
                        ]
                    ];
                }
            }
            
            // Verify audit entries were created for all settings
            $auditCountAfterValid = $this->getTotalAuditCountForSettings($testSettings);
            if ($auditCountAfterValid < count($testSettings)) {
                $this->cleanupTestData();
                return [
                    'success' => false,
                    'message' => 'Not all audit entries were created for valid batch update',
                    'data' => [
                        'expected_audit_count' => count($testSettings),
                        'actual_audit_count' => $auditCountAfterValid
                    ]
                ];
            }
            
        } catch (Exception $e) {
            $this->cleanupTestData();
            return [
                'success' => false,
                'message' => 'Exception during valid batch update: ' . $e->getMessage(),
                'data' => ['exception_trace' => $e->getTraceAsString()]
            ];
        }
        
        // Test Case 2: Mixed valid/invalid changes should fail atomically (no changes applied)
        $mixedChanges = [];
        $validChangeCount = 0;
        $invalidChangeCount = 0;
        
        foreach ($testSettings as $i => $setting) {
            if ($i % 2 === 0) {
                // Valid change
                $mixedChanges[$setting['key']] = $this->generateValidValue($setting['data_type'], $setting['validation_rules']);
                $validChangeCount++;
            } else {
                // Invalid change
                $mixedChanges[$setting['key']] = $this->generateInvalidValue($setting['data_type'], $setting['validation_rules']);
                $invalidChangeCount++;
            }
        }
        
        // Store current values before attempting mixed update
        $valuesBefore = [];
        foreach ($testSettings as $setting) {
            $currentSetting = $this->systemSetting->findByKey($setting['key']);
            $valuesBefore[$setting['key']] = $currentSetting['setting_value'];
        }
        
        $auditCountBeforeMixed = $this->getTotalAuditCountForSettings($testSettings);
        
        try {
            $result = $this->settingsService->updateMultipleSettings($mixedChanges, $this->testUserId);
            
            // Mixed update should fail validation
            if ($result['valid'] || (isset($result['success']) && $result['success'])) {
                $this->cleanupTestData();
                return [
                    'success' => false,
                    'message' => 'Mixed valid/invalid batch update succeeded when it should have failed',
                    'data' => ['result' => $result, 'changes' => $mixedChanges]
                ];
            }
            
            // Verify NO settings were changed (atomicity)
            foreach ($testSettings as $setting) {
                $currentSetting = $this->systemSetting->findByKey($setting['key']);
                if ($currentSetting['setting_value'] !== $valuesBefore[$setting['key']]) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => 'Setting was modified during failed batch operation (atomicity violated)',
                        'data' => [
                            'setting_key' => $setting['key'],
                            'value_before' => $valuesBefore[$setting['key']],
                            'value_after' => $currentSetting['setting_value']
                        ]
                    ];
                }
            }
            
            // Verify NO audit entries were created for failed batch
            $auditCountAfterMixed = $this->getTotalAuditCountForSettings($testSettings);
            if ($auditCountAfterMixed !== $auditCountBeforeMixed) {
                $this->cleanupTestData();
                return [
                    'success' => false,
                    'message' => 'Audit entries were created for failed batch operation (atomicity violated)',
                    'data' => [
                        'audit_count_before' => $auditCountBeforeMixed,
                        'audit_count_after' => $auditCountAfterMixed
                    ]
                ];
            }
            
        } catch (Exception $e) {
            $this->cleanupTestData();
            return [
                'success' => false,
                'message' => 'Exception during mixed batch update: ' . $e->getMessage(),
                'data' => ['exception_trace' => $e->getTraceAsString()]
            ];
        }
        
        // Test Case 3: All invalid changes should fail without any changes
        $invalidChanges = [];
        foreach ($testSettings as $setting) {
            $invalidChanges[$setting['key']] = $this->generateInvalidValue($setting['data_type'], $setting['validation_rules']);
        }
        
        $valuesBeforeInvalid = [];
        foreach ($testSettings as $setting) {
            $currentSetting = $this->systemSetting->findByKey($setting['key']);
            $valuesBeforeInvalid[$setting['key']] = $currentSetting['setting_value'];
        }
        
        $auditCountBeforeInvalid = $this->getTotalAuditCountForSettings($testSettings);
        
        try {
            $result = $this->settingsService->updateMultipleSettings($invalidChanges, $this->testUserId);
            
            // All invalid update should fail validation
            if ($result['valid'] || (isset($result['success']) && $result['success'])) {
                $this->cleanupTestData();
                return [
                    'success' => false,
                    'message' => 'All invalid batch update succeeded when it should have failed',
                    'data' => ['result' => $result, 'changes' => $invalidChanges]
                ];
            }
            
            // Verify NO settings were changed
            foreach ($testSettings as $setting) {
                $currentSetting = $this->systemSetting->findByKey($setting['key']);
                if ($currentSetting['setting_value'] !== $valuesBeforeInvalid[$setting['key']]) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => 'Setting was modified during all-invalid batch operation',
                        'data' => [
                            'setting_key' => $setting['key'],
                            'value_before' => $valuesBeforeInvalid[$setting['key']],
                            'value_after' => $currentSetting['setting_value']
                        ]
                    ];
                }
            }
            
            // Verify NO audit entries were created
            $auditCountAfterInvalid = $this->getTotalAuditCountForSettings($testSettings);
            if ($auditCountAfterInvalid !== $auditCountBeforeInvalid) {
                $this->cleanupTestData();
                return [
                    'success' => false,
                    'message' => 'Audit entries were created for all-invalid batch operation',
                    'data' => [
                        'audit_count_before' => $auditCountBeforeInvalid,
                        'audit_count_after' => $auditCountAfterInvalid
                    ]
                ];
            }
            
        } catch (Exception $e) {
            $this->cleanupTestData();
            return [
                'success' => false,
                'message' => 'Exception during all-invalid batch update: ' . $e->getMessage(),
                'data' => ['exception_trace' => $e->getTraceAsString()]
            ];
        }
        
        $this->cleanupTestData();
        
        return [
            'success' => true,
            'message' => 'Batch validation atomicity verified for all test cases',
            'data' => [
                'settings_tested' => count($testSettings),
                'valid_changes_count' => $validChangeCount,
                'invalid_changes_count' => $invalidChangeCount,
                'test_cases_passed' => 3
            ]
        ];
    }
    
    /**
     * Generate a valid value for the given data type and validation rules
     */
    private function generateValidValue($dataType, $validationRules) {
        switch ($dataType) {
            case 'string':
                $length = $this->generateRandomInt(
                    $validationRules['min_length'] ?? 1,
                    $validationRules['max_length'] ?? 10
                );
                return $this->generateRandomString($length, 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789_-');
                
            case 'integer':
                return (string)$this->generateRandomInt(
                    $validationRules['min_value'] ?? 1,
                    $validationRules['max_value'] ?? 100
                );
                
            case 'boolean':
                return $this->generateRandomChoice(['true', 'false', '1', '0']);
                
            default:
                return $this->generateRandomString(8);
        }
    }
    
    /**
     * Generate an invalid value for the given data type and validation rules
     */
    private function generateInvalidValue($dataType, $validationRules) {
        switch ($dataType) {
            case 'string':
                // Generate string that violates constraints
                if (isset($validationRules['min_length']) && $validationRules['min_length'] > 0) {
                    return ''; // Too short (empty string)
                } elseif (isset($validationRules['max_length'])) {
                    return str_repeat('x', $validationRules['max_length'] + 5); // Too long
                } elseif (isset($validationRules['pattern'])) {
                    return 'invalid@#$%^&*()'; // Invalid characters that won't match pattern
                }
                return str_repeat('x', 100); // Default: very long string
                
            case 'integer':
                // For integers, we need to provide non-numeric strings or out-of-range values
                if (isset($validationRules['min_value'])) {
                    return (string)($validationRules['min_value'] - 10); // Too small
                } elseif (isset($validationRules['max_value'])) {
                    return (string)($validationRules['max_value'] + 10); // Too large
                }
                return 'not_a_number_at_all'; // Not an integer
                
            case 'boolean':
                // For boolean, provide a value that can't be converted to boolean
                // Note: filter_var is permissive, so we need something that will fail validation
                return 'definitely_not_a_boolean_value_123'; // Invalid boolean value
                
            default:
                return str_repeat('x', 1000); // Default: very long string
        }
    }
    
    /**
     * Get total audit count for all test settings
     */
    private function getTotalAuditCountForSettings($testSettings) {
        $totalCount = 0;
        foreach ($testSettings as $setting) {
            $sql = "SELECT COUNT(*) as count FROM settings_audit WHERE setting_id = ?";
            $result = $this->getResults($sql, [$setting['id']], 'i');
            $totalCount += (int)($result[0]['count'] ?? 0);
        }
        return $totalCount;
    }
    
    /**
     * Create a test user for operations
     */
    private function createTestUser() {
        // Try to find an existing ADV user first
        $sql = "SELECT u.id FROM users u 
                JOIN companies c ON u.company_id = c.id 
                WHERE c.type = 'ADV' AND u.status = 1 
                LIMIT 1";
        $result = $this->getResults($sql);
        
        if (!empty($result)) {
            $this->testUserId = (int)$result[0]['id'];
            return;
        }
        
        // If no ADV user exists, create a minimal test user
        // First, ensure we have an ADV company
        $companySql = "SELECT id FROM companies WHERE type = 'ADV' LIMIT 1";
        $companyResult = $this->getResults($companySql);
        
        if (empty($companyResult)) {
            // Create a test ADV company
            $companyInsertSql = "INSERT INTO companies (name, type, status) VALUES (?, ?, ?)";
            $stmt = $this->executeQuery($companyInsertSql, ['Test ADV Company', 'ADV', 1], 'ssi');
            $companyId = $this->db->insert_id;
            $stmt->close();
        } else {
            $companyId = (int)$companyResult[0]['id'];
        }
        
        // Create test user
        $userInsertSql = "INSERT INTO users (username, email, password_hash, company_id, status) VALUES (?, ?, ?, ?, ?)";
        $testUsername = 'test_batch_user_' . $this->generateRandomString(6);
        $testEmail = $testUsername . '@test.com';
        $passwordHash = password_hash('testpassword', PASSWORD_DEFAULT);
        
        $stmt = $this->executeQuery($userInsertSql, [
            $testUsername, 
            $testEmail, 
            $passwordHash, 
            $companyId, 
            1
        ], 'sssii');
        
        $this->testUserId = (int)$this->db->insert_id;
        $stmt->close();
    }
    
    /**
     * Clean up test data
     */
    protected function cleanupTestData() {
        // Clean up audit entries
        foreach ($this->testAuditIds as $id) {
            try {
                $sql = "DELETE FROM settings_audit WHERE id = ?";
                $stmt = $this->executeQuery($sql, [$id], 'i');
                $stmt->close();
            } catch (Exception $e) {
                // Ignore cleanup errors
            }
        }
        
        // Clean up settings
        foreach ($this->testSettingIds as $id) {
            try {
                $this->systemSetting->delete($id);
            } catch (Exception $e) {
                // Ignore cleanup errors
            }
        }
        
        $this->testSettingIds = [];
        $this->testAuditIds = [];
    }
    
    /**
     * Run all property tests
     */
    public function runAllTests() {
        echo "Running Settings Batch Validation Atomicity Property Tests\n";
        echo "==========================================================\n";
        
        $results = [];
        $results['batch_validation_atomicity'] = $this->testBatchValidationAtomicity();
        
        $passed = array_sum($results);
        $total = count($results);
        
        echo "\nResults: $passed/$total tests passed\n";
        
        if ($passed === $total) {
            echo "✓ All settings batch validation atomicity property tests passed!\n";
            return true;
        } else {
            echo "✗ Some settings batch validation atomicity property tests failed!\n";
            return false;
        }
    }
}