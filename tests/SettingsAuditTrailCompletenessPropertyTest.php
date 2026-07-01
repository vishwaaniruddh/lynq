<?php
/**
 * Property Test for Settings Audit Trail Completeness
 * **Feature: system-settings-module, Property 5: Audit trail completeness**
 * **Validates: Requirements 1.5, 5.1**
 */

require_once 'PropertyTestBase.php';
require_once __DIR__ . '/../services/SettingsService.php';
require_once __DIR__ . '/../models/SystemSetting.php';
require_once __DIR__ . '/../models/SettingsAudit.php';

class SettingsAuditTrailCompletenessPropertyTest extends PropertyTestBase {
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
        
        // Create a test user for audit logging
        $this->createTestUser();
    }
    
    /**
     * Test that audit trail completeness is maintained for all setting changes
     */
    public function testAuditTrailCompleteness() {
        return $this->runPropertyTest(
            'Audit trail completeness',
            [$this, 'propertyAuditTrailCompleteness']
        );
    }
    
    /**
     * Property: For any configuration change, when saved, an audit log entry should be created 
     * containing the user ID, timestamp, old value, and new value
     */
    public function propertyAuditTrailCompleteness() {
        // Create a test setting with random data
        $settingKey = 'test_audit_setting_' . $this->generateRandomString(8);
        $initialValue = $this->generateRandomString(10);
        $newValue = $this->generateRandomString(10);
        
        $settingData = [
            'category' => $this->generateRandomChoice(['General', 'Security', 'Email', 'Backup']),
            'setting_key' => $settingKey,
            'setting_value' => $initialValue,
            'default_value' => $this->generateRandomString(8),
            'data_type' => 'string',
            'description' => 'Test setting for audit trail completeness test',
            'validation_rules' => json_encode(['max_length' => 50]),
            'is_required' => false
        ];
        
        try {
            // Create the test setting
            $created = $this->systemSetting->create($settingData);
            $this->testSettingIds[] = $created['id'];
            $settingId = $created['id'];
            
            // Get audit count before update
            $auditCountBefore = $this->getAuditCountForSetting($settingId);
            
            // Update the setting using the service (which should create audit entry)
            $updateResult = $this->settingsService->updateSetting($settingKey, $newValue, $this->testUserId);
            
            if (!$updateResult) {
                return [
                    'success' => false,
                    'message' => 'Failed to update setting',
                    'data' => ['setting_key' => $settingKey, 'new_value' => $newValue]
                ];
            }
            
            // Get audit count after update
            $auditCountAfter = $this->getAuditCountForSetting($settingId);
            
            // Verify audit entry was created
            if ($auditCountAfter !== $auditCountBefore + 1) {
                return [
                    'success' => false,
                    'message' => 'Audit entry was not created for setting update',
                    'data' => [
                        'setting_key' => $settingKey,
                        'audit_count_before' => $auditCountBefore,
                        'audit_count_after' => $auditCountAfter
                    ]
                ];
            }
            
            // Get the most recent audit entry for this setting
            $auditEntries = $this->getAuditEntriesForSetting($settingId, 1);
            
            if (empty($auditEntries)) {
                return [
                    'success' => false,
                    'message' => 'No audit entries found for setting',
                    'data' => ['setting_id' => $settingId]
                ];
            }
            
            $auditEntry = $auditEntries[0];
            $this->testAuditIds[] = $auditEntry['id'];
            
            // Verify audit entry completeness - all required fields must be present
            $requiredFields = ['user_id', 'timestamp', 'old_value', 'new_value', 'action'];
            foreach ($requiredFields as $field) {
                if (!isset($auditEntry[$field]) || $auditEntry[$field] === null) {
                    return [
                        'success' => false,
                        'message' => "Audit entry missing required field: {$field}",
                        'data' => ['audit_entry' => $auditEntry, 'missing_field' => $field]
                    ];
                }
            }
            
            // Verify audit entry values are correct
            if ((int)$auditEntry['user_id'] !== $this->testUserId) {
                return [
                    'success' => false,
                    'message' => 'Audit entry has incorrect user_id',
                    'data' => [
                        'expected_user_id' => $this->testUserId,
                        'actual_user_id' => $auditEntry['user_id']
                    ]
                ];
            }
            
            if ($auditEntry['old_value'] !== $initialValue) {
                return [
                    'success' => false,
                    'message' => 'Audit entry has incorrect old_value',
                    'data' => [
                        'expected_old_value' => $initialValue,
                        'actual_old_value' => $auditEntry['old_value']
                    ]
                ];
            }
            
            if ($auditEntry['new_value'] !== $newValue) {
                return [
                    'success' => false,
                    'message' => 'Audit entry has incorrect new_value',
                    'data' => [
                        'expected_new_value' => $newValue,
                        'actual_new_value' => $auditEntry['new_value']
                    ]
                ];
            }
            
            if ($auditEntry['action'] !== SettingsAudit::ACTION_UPDATE) {
                return [
                    'success' => false,
                    'message' => 'Audit entry has incorrect action',
                    'data' => [
                        'expected_action' => SettingsAudit::ACTION_UPDATE,
                        'actual_action' => $auditEntry['action']
                    ]
                ];
            }
            
            // Verify timestamp is recent (within last minute)
            $auditTime = strtotime($auditEntry['timestamp']);
            $currentTime = time();
            if ($currentTime - $auditTime > 60) {
                return [
                    'success' => false,
                    'message' => 'Audit entry timestamp is not recent',
                    'data' => [
                        'audit_timestamp' => $auditEntry['timestamp'],
                        'time_difference' => $currentTime - $auditTime
                    ]
                ];
            }
            
            // Test reset operation audit trail
            $resetResult = $this->settingsService->resetSetting($settingKey, $this->testUserId, true);
            
            if (!$resetResult['success']) {
                return [
                    'success' => false,
                    'message' => 'Failed to reset setting',
                    'data' => ['reset_result' => $resetResult]
                ];
            }
            
            // Verify reset audit entry was created
            $auditCountAfterReset = $this->getAuditCountForSetting($settingId);
            
            if ($auditCountAfterReset !== $auditCountAfter + 1) {
                return [
                    'success' => false,
                    'message' => 'Audit entry was not created for setting reset',
                    'data' => [
                        'audit_count_after_update' => $auditCountAfter,
                        'audit_count_after_reset' => $auditCountAfterReset
                    ]
                ];
            }
            
            // Get the reset audit entry
            $resetAuditEntries = $this->getAuditEntriesForSetting($settingId, 1);
            $resetAuditEntry = $resetAuditEntries[0];
            $this->testAuditIds[] = $resetAuditEntry['id'];
            
            // Verify reset audit entry has correct action
            if ($resetAuditEntry['action'] !== SettingsAudit::ACTION_RESET) {
                return [
                    'success' => false,
                    'message' => 'Reset audit entry has incorrect action',
                    'data' => [
                        'expected_action' => SettingsAudit::ACTION_RESET,
                        'actual_action' => $resetAuditEntry['action']
                    ]
                ];
            }
            
            return [
                'success' => true,
                'message' => 'Audit trail completeness verified for both update and reset operations',
                'data' => [
                    'setting_key' => $settingKey,
                    'update_audit_id' => $auditEntry['id'],
                    'reset_audit_id' => $resetAuditEntry['id'],
                    'total_audit_entries' => $auditCountAfterReset
                ]
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Exception during audit trail test: ' . $e->getMessage(),
                'data' => ['exception_trace' => $e->getTraceAsString()]
            ];
        } finally {
            $this->cleanupTestData();
        }
    }
    
    /**
     * Get audit count for a specific setting
     */
    private function getAuditCountForSetting($settingId) {
        $sql = "SELECT COUNT(*) as count FROM settings_audit WHERE setting_id = ?";
        $result = $this->getResults($sql, [$settingId], 'i');
        return (int)($result[0]['count'] ?? 0);
    }
    
    /**
     * Get audit entries for a specific setting
     */
    private function getAuditEntriesForSetting($settingId, $limit = 10) {
        $sql = "SELECT * FROM settings_audit WHERE setting_id = ? ORDER BY timestamp DESC LIMIT ?";
        return $this->getResults($sql, [$settingId, $limit], 'ii');
    }
    
    /**
     * Create a test user for audit logging
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
        $testUsername = 'test_audit_user_' . $this->generateRandomString(6);
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
        echo "Running Settings Audit Trail Completeness Property Tests\n";
        echo "========================================================\n";
        
        $results = [];
        $results['audit_trail_completeness'] = $this->testAuditTrailCompleteness();
        
        $passed = array_sum($results);
        $total = count($results);
        
        echo "\nResults: $passed/$total tests passed\n";
        
        if ($passed === $total) {
            echo "✓ All settings audit trail completeness property tests passed!\n";
            return true;
        } else {
            echo "✗ Some settings audit trail completeness property tests failed!\n";
            return false;
        }
    }
}