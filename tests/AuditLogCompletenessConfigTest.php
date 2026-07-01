<?php
/**
 * Property Test: Audit Log Completeness for Configuration Module
 * 
 * **Feature: ip-configuration-management, Property 20: Audit Log Completeness**
 * **Validates: Requirements 9.1**
 * 
 * Property 20: For any configuration action (lock, unlock, configure, unbind), 
 * an audit log entry SHALL be created with user ID, action type, timestamp, 
 * router serial, and IP_Master ID.
 */

require_once __DIR__ . '/PropertyTestBase.php';
require_once __DIR__ . '/../models/ConfigurationAuditLog.php';
require_once __DIR__ . '/../models/IPMaster.php';
require_once __DIR__ . '/../repositories/ConfigurationAuditLogRepository.php';
require_once __DIR__ . '/../repositories/IPMasterRepository.php';

class AuditLogCompletenessConfigTest extends PropertyTestBase {
    
    private $auditLogRepository;
    private $ipMasterRepository;
    private $createdAuditLogIds = [];
    private $createdIPMasterIds = [];
    private $testUserId = null;
    
    public function __construct() {
        parent::__construct();
        $this->auditLogRepository = new ConfigurationAuditLogRepository();
        $this->ipMasterRepository = new IPMasterRepository();
        $this->testUserId = $this->getValidUserId();
    }
    
    /**
     * Get a valid user ID from the database for testing
     */
    protected function getValidUserId(): ?int {
        $sql = "SELECT id FROM users LIMIT 1";
        $result = $this->getResults($sql, [], '');
        return !empty($result) ? (int)$result[0]['id'] : null;
    }
    
    /**
     * Generate a valid IPv4 address
     */
    protected function generateValidIP(): string {
        $octets = [];
        for ($i = 0; $i < 4; $i++) {
            $octets[] = rand(0, 255);
        }
        return implode('.', $octets);
    }
    
    /**
     * Generate a random router serial number
     */
    protected function generateRouterSerial(): string {
        return 'RTR-AUDIT-' . $this->generateRandomString(8) . '-' . rand(1000, 9999);
    }
    
    /**
     * Generate random action type
     */
    protected function generateRandomActionType(): string {
        $actionTypes = ConfigurationAuditLog::getActionTypes();
        return $actionTypes[array_rand($actionTypes)];
    }
    
    /**
     * Generate random details array
     */
    protected function generateRandomDetails(): array {
        $details = [];
        
        // Add random number of detail fields
        $fieldCount = rand(1, 5);
        for ($i = 0; $i < $fieldCount; $i++) {
            $key = 'field_' . $this->generateRandomString(5);
            $details[$key] = $this->generateRandomString(10);
        }
        
        return $details;
    }
    
    /**
     * Create a test IP_Master record
     */
    protected function createTestIPMaster(): ?int {
        try {
            $data = [
                'network_ip' => $this->generateValidIP(),
                'router_ip' => $this->generateValidIP(),
                'site_ip' => $this->generateValidIP(),
                'subnet_mask' => '255.255.255.0',
                'status' => IPMaster::STATUS_AVAILABLE
            ];
            
            if ($this->testUserId !== null) {
                $data['created_by'] = $this->testUserId;
            }
            
            $id = $this->ipMasterRepository->createIPMaster($data);
            $this->createdIPMasterIds[] = $id;
            return $id;
        } catch (Exception $e) {
            error_log("Failed to create test IP_Master: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Clean up test data
     */
    protected function cleanupTestData(): void {
        // Delete test audit logs
        foreach ($this->createdAuditLogIds as $auditId) {
            try {
                $sql = "DELETE FROM `configuration_audit_log` WHERE `id` = ?";
                $stmt = $this->db->prepare($sql);
                $stmt->bind_param('i', $auditId);
                $stmt->execute();
                $stmt->close();
            } catch (Exception $e) {
                // Ignore cleanup errors
            }
        }
        
        // Delete test IP_Masters
        foreach ($this->createdIPMasterIds as $ipMasterId) {
            try {
                // First delete any audit logs referencing this IP
                $sql = "DELETE FROM `configuration_audit_log` WHERE `ip_master_id` = ?";
                $stmt = $this->db->prepare($sql);
                $stmt->bind_param('i', $ipMasterId);
                $stmt->execute();
                $stmt->close();
                
                $sql = "DELETE FROM `ip_master` WHERE `id` = ?";
                $stmt = $this->db->prepare($sql);
                $stmt->bind_param('i', $ipMasterId);
                $stmt->execute();
                $stmt->close();
            } catch (Exception $e) {
                // Ignore cleanup errors
            }
        }
        
        $this->createdAuditLogIds = [];
        $this->createdIPMasterIds = [];
    }

    
    /**
     * Property Test 20: Audit Log Completeness
     * 
     * For any configuration action (lock, unlock, configure, unbind), 
     * an audit log entry SHALL be created with user ID, action type, 
     * timestamp, router serial, and IP_Master ID.
     * 
     * **Feature: ip-configuration-management, Property 20: Audit Log Completeness**
     * **Validates: Requirements 9.1**
     */
    public function testAuditLogCompleteness(): bool {
        echo "\n=== Property Test 20: Audit Log Completeness ===\n";
        
        if ($this->testUserId === null) {
            echo "Skipping: No valid user ID found in database\n";
            return true;
        }
        
        return $this->runPropertyTest(
            'Audit log entries contain all required fields (user ID, action type, timestamp, router serial, IP_Master ID)',
            function() {
                // Create a test IP_Master
                $ipMasterId = $this->createTestIPMaster();
                if (!$ipMasterId) {
                    return [
                        'success' => false,
                        'message' => 'Failed to create test IP_Master'
                    ];
                }
                
                // Generate random test data
                $routerSerial = $this->generateRouterSerial();
                $actionType = $this->generateRandomActionType();
                $details = $this->generateRandomDetails();
                
                // Record timestamp before logging
                $timestampBefore = date('Y-m-d H:i:s');
                
                // Log the action
                try {
                    $auditLogId = $this->auditLogRepository->logAction(
                        $actionType,
                        $this->testUserId,
                        $routerSerial,
                        $ipMasterId,
                        $details
                    );
                    $this->createdAuditLogIds[] = $auditLogId;
                } catch (Exception $e) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => 'Failed to log action: ' . $e->getMessage()
                    ];
                }
                
                // Record timestamp after logging
                $timestampAfter = date('Y-m-d H:i:s');
                
                // Retrieve the audit log entry
                $auditEntry = $this->auditLogRepository->findById($auditLogId);
                
                // Property check: Audit log entry should exist
                if ($auditEntry === null) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => 'Audit log entry should exist after logging',
                        'data' => ['audit_log_id' => $auditLogId]
                    ];
                }
                
                // Property check: User ID should be recorded correctly
                if ((int)$auditEntry['user_id'] !== $this->testUserId) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => 'Audit log should record the correct user ID',
                        'data' => [
                            'expected' => $this->testUserId,
                            'actual' => $auditEntry['user_id']
                        ]
                    ];
                }
                
                // Property check: Action type should be recorded correctly
                if ($auditEntry['action_type'] !== $actionType) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => 'Audit log should record the correct action type',
                        'data' => [
                            'expected' => $actionType,
                            'actual' => $auditEntry['action_type']
                        ]
                    ];
                }
                
                // Property check: Router serial number should be recorded correctly
                if ($auditEntry['router_serial_number'] !== $routerSerial) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => 'Audit log should record the correct router serial number',
                        'data' => [
                            'expected' => $routerSerial,
                            'actual' => $auditEntry['router_serial_number']
                        ]
                    ];
                }
                
                // Property check: IP_Master ID should be recorded correctly
                if ((int)$auditEntry['ip_master_id'] !== $ipMasterId) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => 'Audit log should record the correct IP_Master ID',
                        'data' => [
                            'expected' => $ipMasterId,
                            'actual' => $auditEntry['ip_master_id']
                        ]
                    ];
                }
                
                // Property check: Timestamp should be present and within operation window
                if (empty($auditEntry['created_at'])) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => 'Audit log should have a timestamp'
                    ];
                }
                
                // Allow 2 second tolerance for timing differences
                $beforeTime = strtotime($timestampBefore) - 2;
                $afterTime = strtotime($timestampAfter) + 2;
                $auditTime = strtotime($auditEntry['created_at']);
                
                if ($auditTime < $beforeTime || $auditTime > $afterTime) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => 'Audit log timestamp should be within operation window',
                        'data' => [
                            'before' => $timestampBefore,
                            'after' => $timestampAfter,
                            'audit' => $auditEntry['created_at']
                        ]
                    ];
                }
                
                // Property check: Details should be stored and retrievable
                if (!empty($details)) {
                    $storedDetails = $auditEntry['details_decoded'];
                    foreach ($details as $key => $value) {
                        if (!isset($storedDetails[$key]) || $storedDetails[$key] !== $value) {
                            $this->cleanupTestData();
                            return [
                                'success' => false,
                                'message' => 'Audit log should preserve all details',
                                'data' => [
                                    'expected_key' => $key,
                                    'expected_value' => $value,
                                    'stored_details' => $storedDetails
                                ]
                            ];
                        }
                    }
                }
                
                // Clean up
                $this->cleanupTestData();
                
                return ['success' => true];
            },
            100 // Run 100 iterations as per testing strategy
        );
    }
    
    /**
     * Property Test: All action types can be logged
     * 
     * For any valid action type, the system should successfully create an audit log entry.
     */
    public function testAllActionTypesCanBeLogged(): bool {
        echo "\n=== Property Test: All Action Types Can Be Logged ===\n";
        
        if ($this->testUserId === null) {
            echo "Skipping: No valid user ID found in database\n";
            return true;
        }
        
        return $this->runPropertyTest(
            'All valid action types can be logged successfully',
            function() {
                // Test each action type
                $actionTypes = ConfigurationAuditLog::getActionTypes();
                $actionType = $actionTypes[array_rand($actionTypes)];
                
                // Create a test IP_Master for actions that need it
                $ipMasterId = null;
                $routerSerial = null;
                
                // Some actions require IP_Master ID and router serial
                $actionsWithRouter = [
                    ConfigurationAuditLog::ACTION_LOCK_ACQUIRED,
                    ConfigurationAuditLog::ACTION_LOCK_RELEASED,
                    ConfigurationAuditLog::ACTION_LOCK_EXPIRED,
                    ConfigurationAuditLog::ACTION_CONFIGURED,
                    ConfigurationAuditLog::ACTION_UNBOUND
                ];
                
                $actionsWithIP = [
                    ConfigurationAuditLog::ACTION_LOCK_ACQUIRED,
                    ConfigurationAuditLog::ACTION_LOCK_RELEASED,
                    ConfigurationAuditLog::ACTION_LOCK_EXPIRED,
                    ConfigurationAuditLog::ACTION_CONFIGURED,
                    ConfigurationAuditLog::ACTION_UNBOUND,
                    ConfigurationAuditLog::ACTION_IP_CREATED,
                    ConfigurationAuditLog::ACTION_IP_UPDATED,
                    ConfigurationAuditLog::ACTION_IP_DELETED
                ];
                
                if (in_array($actionType, $actionsWithIP)) {
                    $ipMasterId = $this->createTestIPMaster();
                    if (!$ipMasterId) {
                        return [
                            'success' => false,
                            'message' => 'Failed to create test IP_Master'
                        ];
                    }
                }
                
                if (in_array($actionType, $actionsWithRouter)) {
                    $routerSerial = $this->generateRouterSerial();
                }
                
                // Log the action
                try {
                    $auditLogId = $this->auditLogRepository->logAction(
                        $actionType,
                        $this->testUserId,
                        $routerSerial,
                        $ipMasterId,
                        ['test' => 'data']
                    );
                    $this->createdAuditLogIds[] = $auditLogId;
                } catch (Exception $e) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => "Failed to log action type '$actionType': " . $e->getMessage()
                    ];
                }
                
                // Verify the entry was created
                $auditEntry = $this->auditLogRepository->findById($auditLogId);
                if ($auditEntry === null) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => "Audit log entry not found for action type '$actionType'"
                    ];
                }
                
                // Verify action type was stored correctly
                if ($auditEntry['action_type'] !== $actionType) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => 'Action type mismatch',
                        'data' => [
                            'expected' => $actionType,
                            'actual' => $auditEntry['action_type']
                        ]
                    ];
                }
                
                // Clean up
                $this->cleanupTestData();
                
                return ['success' => true];
            },
            50 // Run 50 iterations to cover all action types multiple times
        );
    }
    
    /**
     * Property Test: Invalid action types are rejected
     * 
     * For any invalid action type, the system should reject the log attempt.
     */
    public function testInvalidActionTypesRejected(): bool {
        echo "\n=== Property Test: Invalid Action Types Rejected ===\n";
        
        if ($this->testUserId === null) {
            echo "Skipping: No valid user ID found in database\n";
            return true;
        }
        
        return $this->runPropertyTest(
            'Invalid action types are rejected',
            function() {
                // Generate an invalid action type
                $invalidActionType = 'invalid_' . $this->generateRandomString(10);
                
                // Attempt to log with invalid action type
                try {
                    $auditLogId = $this->auditLogRepository->logAction(
                        $invalidActionType,
                        $this->testUserId,
                        null,
                        null,
                        null
                    );
                    
                    // If we get here, the invalid action type was accepted (which is wrong)
                    $this->createdAuditLogIds[] = $auditLogId;
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => 'Invalid action type should be rejected',
                        'data' => ['invalid_action_type' => $invalidActionType]
                    ];
                } catch (Exception $e) {
                    // Expected behavior - invalid action type should throw exception
                    return ['success' => true];
                }
            },
            20 // Run 20 iterations
        );
    }
    
    /**
     * Property Test: Audit log history filtering works correctly
     * 
     * For any filter criteria, the returned results should match the filter.
     */
    public function testAuditLogHistoryFiltering(): bool {
        echo "\n=== Property Test: Audit Log History Filtering ===\n";
        
        if ($this->testUserId === null) {
            echo "Skipping: No valid user ID found in database\n";
            return true;
        }
        
        return $this->runPropertyTest(
            'Audit log history filtering returns correct results',
            function() {
                // Create a test IP_Master
                $ipMasterId = $this->createTestIPMaster();
                if (!$ipMasterId) {
                    return [
                        'success' => false,
                        'message' => 'Failed to create test IP_Master'
                    ];
                }
                
                // Generate unique test data
                $routerSerial = $this->generateRouterSerial();
                $actionType = ConfigurationAuditLog::ACTION_CONFIGURED;
                
                // Log the action
                try {
                    $auditLogId = $this->auditLogRepository->logAction(
                        $actionType,
                        $this->testUserId,
                        $routerSerial,
                        $ipMasterId,
                        ['test_filter' => 'value']
                    );
                    $this->createdAuditLogIds[] = $auditLogId;
                } catch (Exception $e) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => 'Failed to log action: ' . $e->getMessage()
                    ];
                }
                
                // Test filtering by action type
                $result = $this->auditLogRepository->getHistory([
                    'action_type' => $actionType,
                    'limit' => 100
                ]);
                
                $found = false;
                foreach ($result['data'] as $entry) {
                    if ((int)$entry['id'] === $auditLogId) {
                        $found = true;
                        break;
                    }
                }
                
                if (!$found) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => 'Filtering by action type should return the logged entry'
                    ];
                }
                
                // Test filtering by IP_Master ID
                $result = $this->auditLogRepository->getHistory([
                    'ip_master_id' => $ipMasterId,
                    'limit' => 100
                ]);
                
                $found = false;
                foreach ($result['data'] as $entry) {
                    if ((int)$entry['id'] === $auditLogId) {
                        $found = true;
                        break;
                    }
                }
                
                if (!$found) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => 'Filtering by IP_Master ID should return the logged entry'
                    ];
                }
                
                // Test filtering by router serial number
                $result = $this->auditLogRepository->getHistory([
                    'router_serial_number' => $routerSerial,
                    'limit' => 100
                ]);
                
                $found = false;
                foreach ($result['data'] as $entry) {
                    if ((int)$entry['id'] === $auditLogId) {
                        $found = true;
                        break;
                    }
                }
                
                if (!$found) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => 'Filtering by router serial number should return the logged entry'
                    ];
                }
                
                // Clean up
                $this->cleanupTestData();
                
                return ['success' => true];
            },
            30 // Run 30 iterations
        );
    }
    
    /**
     * Run all property tests
     */
    public function runAllTests(): bool {
        $allPassed = true;
        
        $allPassed = $this->testAuditLogCompleteness() && $allPassed;
        $allPassed = $this->testAllActionTypesCanBeLogged() && $allPassed;
        $allPassed = $this->testInvalidActionTypesRejected() && $allPassed;
        $allPassed = $this->testAuditLogHistoryFiltering() && $allPassed;
        
        echo "\n" . ($allPassed ? "All tests passed!" : "Some tests failed!") . "\n";
        
        return $allPassed;
    }
}
