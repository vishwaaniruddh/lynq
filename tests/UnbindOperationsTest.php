<?php
/**
 * Property Tests: Unbind Operations
 * 
 * **Feature: ip-configuration-management, Property 16: Unbind Status Reset**
 * **Feature: ip-configuration-management, Property 17: Unbind Audit Logging**
 * **Validates: Requirements 6.2, 6.3**
 * 
 * Property 16: For any unbind operation, the IP_Master status SHALL change 
 * from 'configured' to 'available'.
 * 
 * Property 17: For any unbind operation, an audit log entry SHALL be created 
 * with timestamp, user ID, and unbind reason.
 */

require_once __DIR__ . '/PropertyTestBase.php';
require_once __DIR__ . '/../models/IPLock.php';
require_once __DIR__ . '/../models/IPMaster.php';
require_once __DIR__ . '/../models/RouterIPBinding.php';
require_once __DIR__ . '/../models/ConfigurationAuditLog.php';
require_once __DIR__ . '/../repositories/IPLockRepository.php';
require_once __DIR__ . '/../repositories/IPMasterRepository.php';
require_once __DIR__ . '/../repositories/RouterIPBindingRepository.php';
require_once __DIR__ . '/../services/ConfigurationService.php';
require_once __DIR__ . '/../services/BindingService.php';
require_once __DIR__ . '/../services/LockService.php';

class UnbindOperationsTest extends PropertyTestBase {
    
    private $configurationService;
    private $bindingService;
    private $lockService;
    private $lockRepository;
    private $ipMasterRepository;
    private $bindingRepository;
    private $auditLog;
    private $createdIPMasterIds = [];
    private $createdLockIds = [];
    private $createdBindingIds = [];
    private $createdAuditLogIds = [];
    private $testUserId = null;
    
    public function __construct() {
        parent::__construct();
        $this->configurationService = new ConfigurationService();
        $this->bindingService = new BindingService();
        $this->lockService = new LockService();
        $this->lockRepository = new IPLockRepository();
        $this->ipMasterRepository = new IPMasterRepository();
        $this->bindingRepository = new RouterIPBindingRepository();
        $this->auditLog = new ConfigurationAuditLog();
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
        return 'RTR-UNBIND-' . $this->generateRandomString(8) . '-' . rand(1000, 9999);
    }
    
    /**
     * Generate a random unbind reason
     */
    protected function generateUnbindReason(): string {
        $reasons = [
            'Router replacement',
            'IP reassignment required',
            'Configuration error correction',
            'Hardware failure',
            'Network restructuring',
            'Maintenance operation',
            'Customer request',
            'Testing purposes'
        ];
        return $reasons[array_rand($reasons)] . ' - ' . $this->generateRandomString(6);
    }

    
    /**
     * Create a test IP_Master record with available status
     */
    protected function createTestIPMaster(string $status = IPMaster::STATUS_AVAILABLE): ?int {
        try {
            $data = [
                'network_ip' => $this->generateValidIP(),
                'router_ip' => $this->generateValidIP(),
                'site_ip' => $this->generateValidIP(),
                'subnet_mask' => '255.255.255.0',
                'status' => $status
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
     * Create a configured binding for testing unbind operations
     * 
     * @return array|null Array with binding_id, ip_master_id, router_serial or null on failure
     */
    protected function createConfiguredBinding(): ?array {
        // Create a test IP_Master
        $ipMasterId = $this->createTestIPMaster(IPMaster::STATUS_AVAILABLE);
        if (!$ipMasterId) {
            return null;
        }
        
        // Generate a random router serial
        $routerSerial = $this->generateRouterSerial();
        
        // Start configuration
        $startResult = $this->configurationService->startConfiguration(
            $routerSerial,
            $this->testUserId,
            $ipMasterId
        );
        
        if (!$startResult['success']) {
            return null;
        }
        
        $lockId = $startResult['data']['lock_id'];
        $this->createdLockIds[] = $lockId;
        
        // Complete configuration to create binding
        $completeResult = $this->configurationService->completeConfiguration(
            $lockId,
            $this->testUserId,
            'Test configuration for unbind testing'
        );
        
        if (!$completeResult['success']) {
            return null;
        }
        
        $bindingId = $completeResult['data']['binding_id'];
        $this->createdBindingIds[] = $bindingId;
        
        return [
            'binding_id' => $bindingId,
            'ip_master_id' => $ipMasterId,
            'router_serial' => $routerSerial
        ];
    }
    
    /**
     * Get the latest audit log entry for a specific action type and IP_Master
     */
    protected function getLatestAuditLogEntry(int $ipMasterId, string $actionType): ?array {
        $sql = "SELECT * FROM configuration_audit_log 
                WHERE ip_master_id = ? AND action_type = ? 
                ORDER BY created_at DESC LIMIT 1";
        $result = $this->getResults($sql, [$ipMasterId, $actionType], 'is');
        return !empty($result) ? $result[0] : null;
    }
    
    /**
     * Clean up test data
     */
    protected function cleanupTestData(): void {
        // Delete test audit logs first
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
        
        // Delete test bindings (due to foreign key constraints)
        foreach ($this->createdBindingIds as $bindingId) {
            try {
                $sql = "DELETE FROM `router_ip_bindings` WHERE `id` = ?";
                $stmt = $this->db->prepare($sql);
                $stmt->bind_param('i', $bindingId);
                $stmt->execute();
                $stmt->close();
            } catch (Exception $e) {
                // Ignore cleanup errors
            }
        }
        
        // Delete test locks
        foreach ($this->createdLockIds as $lockId) {
            try {
                $sql = "DELETE FROM `ip_locks` WHERE `id` = ?";
                $stmt = $this->db->prepare($sql);
                $stmt->bind_param('i', $lockId);
                $stmt->execute();
                $stmt->close();
            } catch (Exception $e) {
                // Ignore cleanup errors
            }
        }
        
        // Delete audit logs for test IP_Masters
        foreach ($this->createdIPMasterIds as $ipMasterId) {
            try {
                $sql = "DELETE FROM `configuration_audit_log` WHERE `ip_master_id` = ?";
                $stmt = $this->db->prepare($sql);
                $stmt->bind_param('i', $ipMasterId);
                $stmt->execute();
                $stmt->close();
            } catch (Exception $e) {
                // Ignore cleanup errors
            }
        }
        
        // Delete test IP_Masters
        foreach ($this->createdIPMasterIds as $ipMasterId) {
            try {
                // First reset status to available to allow deletion
                $sql = "UPDATE `ip_master` SET `status` = 'available' WHERE `id` = ?";
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
        $this->createdBindingIds = [];
        $this->createdLockIds = [];
        $this->createdIPMasterIds = [];
    }

    
    /**
     * Property Test 16: Unbind Status Reset
     * 
     * For any unbind operation, the IP_Master status SHALL change 
     * from 'configured' to 'available'.
     * 
     * **Feature: ip-configuration-management, Property 16: Unbind Status Reset**
     * **Validates: Requirements 6.2**
     */
    public function testUnbindStatusReset(): bool {
        echo "\n=== Property Test 16: Unbind Status Reset ===\n";
        
        if ($this->testUserId === null) {
            echo "Skipping: No valid user ID found in database\n";
            return true;
        }
        
        return $this->runPropertyTest(
            'Unbind operation resets IP_Master status from configured to available',
            function() {
                // Create a configured binding
                $bindingData = $this->createConfiguredBinding();
                if (!$bindingData) {
                    return [
                        'success' => false,
                        'message' => 'Failed to create configured binding for testing'
                    ];
                }
                
                $bindingId = $bindingData['binding_id'];
                $ipMasterId = $bindingData['ip_master_id'];
                
                // Verify IP_Master is in 'configured' status before unbind
                $ipMasterBefore = $this->ipMasterRepository->findById($ipMasterId);
                if ($ipMasterBefore['status'] !== IPMaster::STATUS_CONFIGURED) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => 'IP_Master should be in configured status before unbind',
                        'data' => ['status' => $ipMasterBefore['status']]
                    ];
                }
                
                // Generate a random unbind reason
                $unbindReason = $this->generateUnbindReason();
                
                // Perform unbind operation
                $unbindResult = $this->bindingService->unbind(
                    $bindingId,
                    $this->testUserId,
                    $unbindReason
                );
                
                if (!$unbindResult['success']) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => 'Unbind operation failed: ' . $unbindResult['message']
                    ];
                }
                
                // Property check: IP_Master status should now be 'available'
                $ipMasterAfter = $this->ipMasterRepository->findById($ipMasterId);
                if ($ipMasterAfter['status'] !== IPMaster::STATUS_AVAILABLE) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => 'IP_Master status should be available after unbind',
                        'data' => [
                            'expected' => IPMaster::STATUS_AVAILABLE,
                            'actual' => $ipMasterAfter['status']
                        ]
                    ];
                }
                
                // Property check: Binding status should be 'unbound'
                $bindingAfter = $this->bindingRepository->findById($bindingId);
                if ($bindingAfter['status'] !== RouterIPBinding::STATUS_UNBOUND) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => 'Binding status should be unbound after unbind',
                        'data' => [
                            'expected' => RouterIPBinding::STATUS_UNBOUND,
                            'actual' => $bindingAfter['status']
                        ]
                    ];
                }
                
                // Clean up
                $this->cleanupTestData();
                
                return ['success' => true];
            },
            20 // Run 20 iterations
        );
    }
    
    /**
     * Property Test 17: Unbind Audit Logging
     * 
     * For any unbind operation, an audit log entry SHALL be created 
     * with timestamp, user ID, and unbind reason.
     * 
     * **Feature: ip-configuration-management, Property 17: Unbind Audit Logging**
     * **Validates: Requirements 6.3**
     */
    public function testUnbindAuditLogging(): bool {
        echo "\n=== Property Test 17: Unbind Audit Logging ===\n";
        
        if ($this->testUserId === null) {
            echo "Skipping: No valid user ID found in database\n";
            return true;
        }
        
        return $this->runPropertyTest(
            'Unbind operation creates audit log entry with timestamp, user ID, and reason',
            function() {
                // Create a configured binding
                $bindingData = $this->createConfiguredBinding();
                if (!$bindingData) {
                    return [
                        'success' => false,
                        'message' => 'Failed to create configured binding for testing'
                    ];
                }
                
                $bindingId = $bindingData['binding_id'];
                $ipMasterId = $bindingData['ip_master_id'];
                $routerSerial = $bindingData['router_serial'];
                
                // Generate a random unbind reason
                $unbindReason = $this->generateUnbindReason();
                
                // Record timestamp before unbind
                $timestampBefore = date('Y-m-d H:i:s');
                
                // Perform unbind operation
                $unbindResult = $this->bindingService->unbind(
                    $bindingId,
                    $this->testUserId,
                    $unbindReason
                );
                
                // Record timestamp after unbind
                $timestampAfter = date('Y-m-d H:i:s');
                
                if (!$unbindResult['success']) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => 'Unbind operation failed: ' . $unbindResult['message']
                    ];
                }
                
                // Get the audit log entry for this unbind operation
                $auditEntry = $this->getLatestAuditLogEntry($ipMasterId, ConfigurationAuditLog::ACTION_UNBOUND);
                
                // Property check: Audit log entry should exist
                if ($auditEntry === null) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => 'Audit log entry should be created for unbind operation',
                        'data' => ['ip_master_id' => $ipMasterId]
                    ];
                }
                
                // Property check: User ID should be recorded
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
                
                // Property check: Router serial number should be recorded
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
                
                // Property check: IP_Master ID should be recorded
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
                
                // Property check: Timestamp should be within the operation window
                $auditTimestamp = $auditEntry['created_at'];
                if ($auditTimestamp < $timestampBefore || $auditTimestamp > $timestampAfter) {
                    // Allow 1 second tolerance for timing differences
                    $beforeTime = strtotime($timestampBefore) - 1;
                    $afterTime = strtotime($timestampAfter) + 1;
                    $auditTime = strtotime($auditTimestamp);
                    
                    if ($auditTime < $beforeTime || $auditTime > $afterTime) {
                        $this->cleanupTestData();
                        return [
                            'success' => false,
                            'message' => 'Audit log timestamp should be within operation window',
                            'data' => [
                                'before' => $timestampBefore,
                                'after' => $timestampAfter,
                                'audit' => $auditTimestamp
                            ]
                        ];
                    }
                }
                
                // Property check: Unbind reason should be recorded in details
                $details = json_decode($auditEntry['details'], true);
                if (!isset($details['unbind_reason']) || $details['unbind_reason'] !== $unbindReason) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => 'Audit log should record the unbind reason',
                        'data' => [
                            'expected_reason' => $unbindReason,
                            'actual_details' => $details
                        ]
                    ];
                }
                
                // Clean up
                $this->cleanupTestData();
                
                return ['success' => true];
            },
            20 // Run 20 iterations
        );
    }

    
    /**
     * Property Test: Unbind requires valid reason
     * 
     * For any unbind operation, a non-empty reason must be provided.
     */
    public function testUnbindRequiresReason(): bool {
        echo "\n=== Property Test: Unbind Requires Reason ===\n";
        
        if ($this->testUserId === null) {
            echo "Skipping: No valid user ID found in database\n";
            return true;
        }
        
        return $this->runPropertyTest(
            'Unbind operation requires a non-empty reason',
            function() {
                // Create a configured binding
                $bindingData = $this->createConfiguredBinding();
                if (!$bindingData) {
                    return [
                        'success' => false,
                        'message' => 'Failed to create configured binding for testing'
                    ];
                }
                
                $bindingId = $bindingData['binding_id'];
                $ipMasterId = $bindingData['ip_master_id'];
                
                // Try to unbind with empty reason
                $unbindResult = $this->bindingService->unbind(
                    $bindingId,
                    $this->testUserId,
                    ''
                );
                
                // Property check: Unbind should fail with empty reason
                if ($unbindResult['success']) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => 'Unbind should fail when reason is empty'
                    ];
                }
                
                // Property check: IP_Master status should remain 'configured'
                $ipMaster = $this->ipMasterRepository->findById($ipMasterId);
                if ($ipMaster['status'] !== IPMaster::STATUS_CONFIGURED) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => 'IP_Master status should remain configured when unbind fails',
                        'data' => ['status' => $ipMaster['status']]
                    ];
                }
                
                // Try to unbind with whitespace-only reason
                $unbindResult2 = $this->bindingService->unbind(
                    $bindingId,
                    $this->testUserId,
                    '   '
                );
                
                // Property check: Unbind should fail with whitespace-only reason
                if ($unbindResult2['success']) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => 'Unbind should fail when reason is whitespace-only'
                    ];
                }
                
                // Clean up
                $this->cleanupTestData();
                
                return ['success' => true];
            },
            10 // Run 10 iterations
        );
    }
    
    /**
     * Property Test: Unbind only works on active bindings
     * 
     * For any unbind operation, the binding must be in 'active' status.
     */
    public function testUnbindOnlyActiveBindings(): bool {
        echo "\n=== Property Test: Unbind Only Active Bindings ===\n";
        
        if ($this->testUserId === null) {
            echo "Skipping: No valid user ID found in database\n";
            return true;
        }
        
        return $this->runPropertyTest(
            'Unbind operation only works on active bindings',
            function() {
                // Create a configured binding
                $bindingData = $this->createConfiguredBinding();
                if (!$bindingData) {
                    return [
                        'success' => false,
                        'message' => 'Failed to create configured binding for testing'
                    ];
                }
                
                $bindingId = $bindingData['binding_id'];
                $unbindReason = $this->generateUnbindReason();
                
                // First unbind should succeed
                $unbindResult1 = $this->bindingService->unbind(
                    $bindingId,
                    $this->testUserId,
                    $unbindReason
                );
                
                if (!$unbindResult1['success']) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => 'First unbind should succeed: ' . $unbindResult1['message']
                    ];
                }
                
                // Second unbind on same binding should fail (already unbound)
                $unbindResult2 = $this->bindingService->unbind(
                    $bindingId,
                    $this->testUserId,
                    'Second unbind attempt'
                );
                
                // Property check: Second unbind should fail
                if ($unbindResult2['success']) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => 'Unbind should fail on already unbound binding'
                    ];
                }
                
                // Property check: Error code should indicate not active
                if ($unbindResult2['code'] !== 'NOT_ACTIVE' && $unbindResult2['code'] !== 'NOT_FOUND') {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => 'Error code should indicate binding is not active',
                        'data' => ['code' => $unbindResult2['code']]
                    ];
                }
                
                // Clean up
                $this->cleanupTestData();
                
                return ['success' => true];
            },
            10 // Run 10 iterations
        );
    }
    
    /**
     * Property Test: Unbind records unbound_by and unbound_at
     * 
     * For any unbind operation, the binding record should be updated with
     * unbound_by user ID and unbound_at timestamp.
     */
    public function testUnbindRecordsMetadata(): bool {
        echo "\n=== Property Test: Unbind Records Metadata ===\n";
        
        if ($this->testUserId === null) {
            echo "Skipping: No valid user ID found in database\n";
            return true;
        }
        
        return $this->runPropertyTest(
            'Unbind operation records unbound_by and unbound_at in binding',
            function() {
                // Create a configured binding
                $bindingData = $this->createConfiguredBinding();
                if (!$bindingData) {
                    return [
                        'success' => false,
                        'message' => 'Failed to create configured binding for testing'
                    ];
                }
                
                $bindingId = $bindingData['binding_id'];
                $unbindReason = $this->generateUnbindReason();
                
                // Record timestamp before unbind
                $timestampBefore = date('Y-m-d H:i:s');
                
                // Perform unbind
                $unbindResult = $this->bindingService->unbind(
                    $bindingId,
                    $this->testUserId,
                    $unbindReason
                );
                
                // Record timestamp after unbind
                $timestampAfter = date('Y-m-d H:i:s');
                
                if (!$unbindResult['success']) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => 'Unbind operation failed: ' . $unbindResult['message']
                    ];
                }
                
                // Get the updated binding
                $binding = $this->bindingRepository->findById($bindingId);
                
                // Property check: unbound_by should be set to the user ID
                if ((int)$binding['unbound_by'] !== $this->testUserId) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => 'Binding should record unbound_by user ID',
                        'data' => [
                            'expected' => $this->testUserId,
                            'actual' => $binding['unbound_by']
                        ]
                    ];
                }
                
                // Property check: unbound_at should be set
                if (empty($binding['unbound_at'])) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => 'Binding should record unbound_at timestamp'
                    ];
                }
                
                // Property check: unbind_reason should be recorded
                if ($binding['unbind_reason'] !== $unbindReason) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => 'Binding should record unbind_reason',
                        'data' => [
                            'expected' => $unbindReason,
                            'actual' => $binding['unbind_reason']
                        ]
                    ];
                }
                
                // Clean up
                $this->cleanupTestData();
                
                return ['success' => true];
            },
            15 // Run 15 iterations
        );
    }
    
    /**
     * Property Test: IP becomes available for rebinding after unbind
     * 
     * For any unbind operation, the IP_Master should become available
     * for new configuration bindings.
     */
    public function testIPAvailableAfterUnbind(): bool {
        echo "\n=== Property Test: IP Available After Unbind ===\n";
        
        if ($this->testUserId === null) {
            echo "Skipping: No valid user ID found in database\n";
            return true;
        }
        
        return $this->runPropertyTest(
            'IP_Master becomes available for rebinding after unbind',
            function() {
                // Create a configured binding
                $bindingData = $this->createConfiguredBinding();
                if (!$bindingData) {
                    return [
                        'success' => false,
                        'message' => 'Failed to create configured binding for testing'
                    ];
                }
                
                $bindingId = $bindingData['binding_id'];
                $ipMasterId = $bindingData['ip_master_id'];
                $unbindReason = $this->generateUnbindReason();
                
                // Perform unbind
                $unbindResult = $this->bindingService->unbind(
                    $bindingId,
                    $this->testUserId,
                    $unbindReason
                );
                
                if (!$unbindResult['success']) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => 'Unbind operation failed: ' . $unbindResult['message']
                    ];
                }
                
                // Property check: IP should appear in available IPs list
                $availableIPs = $this->ipMasterRepository->getAvailable();
                $foundInAvailable = false;
                foreach ($availableIPs as $ip) {
                    if ((int)$ip['id'] === $ipMasterId) {
                        $foundInAvailable = true;
                        break;
                    }
                }
                
                if (!$foundInAvailable) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => 'IP_Master should appear in available IPs after unbind',
                        'data' => ['ip_master_id' => $ipMasterId]
                    ];
                }
                
                // Property check: IP should be rebindable (can start new configuration)
                $newRouterSerial = $this->generateRouterSerial();
                $startResult = $this->configurationService->startConfiguration(
                    $newRouterSerial,
                    $this->testUserId,
                    $ipMasterId
                );
                
                if (!$startResult['success']) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => 'IP_Master should be rebindable after unbind',
                        'data' => ['error' => $startResult['message']]
                    ];
                }
                
                // Track the new lock for cleanup
                $this->createdLockIds[] = $startResult['data']['lock_id'];
                
                // Clean up (cancel the new configuration)
                $this->configurationService->cancelConfiguration(
                    $startResult['data']['lock_id'],
                    $this->testUserId
                );
                
                $this->cleanupTestData();
                
                return ['success' => true];
            },
            10 // Run 10 iterations
        );
    }
    
    /**
     * Run all property tests
     */
    public function runAllTests(): bool {
        $allPassed = true;
        
        $allPassed = $this->testUnbindStatusReset() && $allPassed;
        $allPassed = $this->testUnbindAuditLogging() && $allPassed;
        $allPassed = $this->testUnbindRequiresReason() && $allPassed;
        $allPassed = $this->testUnbindOnlyActiveBindings() && $allPassed;
        $allPassed = $this->testUnbindRecordsMetadata() && $allPassed;
        $allPassed = $this->testIPAvailableAfterUnbind() && $allPassed;
        
        echo "\n" . ($allPassed ? "All property tests passed!" : "Some property tests failed!") . "\n";
        
        return $allPassed;
    }
}

// Run tests if executed directly
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    $test = new UnbindOperationsTest();
    $result = $test->runAllTests();
    exit($result ? 0 : 1);
}
