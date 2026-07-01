<?php
/**
 * Property Test: IP-to-Router Query
 * 
 * **Feature: ip-configuration-management, Property 15: IP-to-Router Query**
 * **Validates: Requirements 5.4**
 * 
 * Property 15: For any configured IP_Master query, the response SHALL include 
 * the bound router serial number.
 */

require_once __DIR__ . '/PropertyTestBase.php';
require_once __DIR__ . '/../models/IPLock.php';
require_once __DIR__ . '/../models/IPMaster.php';
require_once __DIR__ . '/../models/RouterIPBinding.php';
require_once __DIR__ . '/../repositories/IPLockRepository.php';
require_once __DIR__ . '/../repositories/IPMasterRepository.php';
require_once __DIR__ . '/../repositories/RouterIPBindingRepository.php';
require_once __DIR__ . '/../services/ConfigurationService.php';
require_once __DIR__ . '/../services/LockService.php';

class IPToRouterQueryTest extends PropertyTestBase {
    
    private $configurationService;
    private $lockService;
    private $lockRepository;
    private $ipMasterRepository;
    private $bindingRepository;
    private $bindingModel;
    private $createdIPMasterIds = [];
    private $createdLockIds = [];
    private $createdBindingIds = [];
    private $testUserId = null;
    
    public function __construct() {
        parent::__construct();
        $this->configurationService = new ConfigurationService();
        $this->lockService = new LockService();
        $this->lockRepository = new IPLockRepository();
        $this->ipMasterRepository = new IPMasterRepository();
        $this->bindingRepository = new RouterIPBindingRepository();
        $this->bindingModel = new RouterIPBinding();
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
        return 'RTR-TEST-' . $this->generateRandomString(8) . '-' . rand(1000, 9999);
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
     * Clean up test data
     */
    protected function cleanupTestData(): void {
        // Delete test bindings first (due to foreign key constraints)
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
        
        $this->createdBindingIds = [];
        $this->createdLockIds = [];
        $this->createdIPMasterIds = [];
    }
    
    /**
     * Property Test 15: IP-to-Router Query
     * 
     * For any configured IP_Master query, the response SHALL include 
     * the bound router serial number.
     * 
     * **Feature: ip-configuration-management, Property 15: IP-to-Router Query**
     * **Validates: Requirements 5.4**
     */
    public function testIPToRouterQuery(): bool {
        echo "\n=== Property Test 15: IP-to-Router Query ===\n";
        
        if ($this->testUserId === null) {
            echo "Skipping: No valid user ID found in database\n";
            return true;
        }
        
        return $this->runPropertyTest(
            'Configured IP_Master query returns bound router serial number',
            function() {
                // Create a test IP_Master
                $ipMasterId = $this->createTestIPMaster(IPMaster::STATUS_AVAILABLE);
                if (!$ipMasterId) {
                    return [
                        'success' => false,
                        'message' => 'Failed to create test IP_Master'
                    ];
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
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => 'Failed to start configuration: ' . $startResult['message']
                    ];
                }
                
                $lockId = $startResult['data']['lock_id'];
                $this->createdLockIds[] = $lockId;
                
                // Complete configuration to create binding
                $completeResult = $this->configurationService->completeConfiguration(
                    $lockId,
                    $this->testUserId,
                    'Test configuration notes'
                );
                
                if (!$completeResult['success']) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => 'Failed to complete configuration: ' . $completeResult['message']
                    ];
                }
                
                $this->createdBindingIds[] = $completeResult['data']['binding_id'];
                
                // Query IP_Master binding using repository
                $binding = $this->bindingRepository->getByIPMaster($ipMasterId);
                
                // Property check: Binding should not be null
                if ($binding === null) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => 'IP_Master binding query returned null',
                        'data' => ['ip_master_id' => $ipMasterId]
                    ];
                }
                
                // Property check: router_serial_number should be present
                if (!isset($binding['router_serial_number']) || empty($binding['router_serial_number'])) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => 'Router serial number should be present in binding',
                        'data' => ['binding' => $binding]
                    ];
                }
                
                // Property check: router_serial_number should match
                if ($binding['router_serial_number'] !== $routerSerial) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => 'Router serial number should match',
                        'data' => [
                            'expected' => $routerSerial,
                            'actual' => $binding['router_serial_number']
                        ]
                    ];
                }
                
                // Property check: ip_master_id should match
                if ((int)$binding['ip_master_id'] !== $ipMasterId) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => 'IP_Master ID should match',
                        'data' => [
                            'expected' => $ipMasterId,
                            'actual' => $binding['ip_master_id']
                        ]
                    ];
                }
                
                // Property check: status should be active
                if ($binding['status'] !== RouterIPBinding::STATUS_ACTIVE) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => 'Binding status should be active',
                        'data' => ['status' => $binding['status']]
                    ];
                }
                
                // Clean up
                $this->cleanupTestData();
                
                return ['success' => true];
            },
            20
        );
    }
    
    /**
     * Property Test: Unbound IP_Master returns null binding
     * 
     * For any IP_Master that is not bound, the query should return null.
     */
    public function testUnboundIPMasterQuery(): bool {
        echo "\n=== Property Test: Unbound IP_Master Query ===\n";
        
        if ($this->testUserId === null) {
            echo "Skipping: No valid user ID found in database\n";
            return true;
        }
        
        return $this->runPropertyTest(
            'Unbound IP_Master query returns null',
            function() {
                // Create a test IP_Master (not bound)
                $ipMasterId = $this->createTestIPMaster(IPMaster::STATUS_AVAILABLE);
                if (!$ipMasterId) {
                    return [
                        'success' => false,
                        'message' => 'Failed to create test IP_Master'
                    ];
                }
                
                // Query IP_Master binding (should be null since not bound)
                $binding = $this->bindingRepository->getByIPMaster($ipMasterId);
                
                // Property check: Binding should be null for unbound IP
                if ($binding !== null) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => 'Binding should be null for unbound IP_Master',
                        'data' => ['binding' => $binding]
                    ];
                }
                
                // Clean up
                $this->cleanupTestData();
                
                return ['success' => true];
            },
            20
        );
    }
    
    /**
     * Property Test: IP_Master binding includes all required fields
     * 
     * For any configured IP_Master, the binding should include all required fields.
     */
    public function testIPMasterBindingCompleteness(): bool {
        echo "\n=== Property Test: IP_Master Binding Completeness ===\n";
        
        if ($this->testUserId === null) {
            echo "Skipping: No valid user ID found in database\n";
            return true;
        }
        
        return $this->runPropertyTest(
            'IP_Master binding includes all required fields',
            function() {
                // Create a test IP_Master
                $ipMasterId = $this->createTestIPMaster(IPMaster::STATUS_AVAILABLE);
                if (!$ipMasterId) {
                    return [
                        'success' => false,
                        'message' => 'Failed to create test IP_Master'
                    ];
                }
                
                // Generate a random router serial
                $routerSerial = $this->generateRouterSerial();
                
                // Start and complete configuration
                $startResult = $this->configurationService->startConfiguration(
                    $routerSerial,
                    $this->testUserId,
                    $ipMasterId
                );
                
                if (!$startResult['success']) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => 'Failed to start configuration: ' . $startResult['message']
                    ];
                }
                
                $lockId = $startResult['data']['lock_id'];
                $this->createdLockIds[] = $lockId;
                
                $completeResult = $this->configurationService->completeConfiguration(
                    $lockId,
                    $this->testUserId,
                    'Test notes'
                );
                
                if (!$completeResult['success']) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => 'Failed to complete configuration: ' . $completeResult['message']
                    ];
                }
                
                $this->createdBindingIds[] = $completeResult['data']['binding_id'];
                
                // Query IP_Master binding
                $binding = $this->bindingRepository->getByIPMaster($ipMasterId);
                
                // Property check: All required fields should be present
                $requiredFields = [
                    'id', 'router_serial_number', 'ip_master_id', 
                    'configured_by', 'configured_at', 'status',
                    'network_ip', 'router_ip', 'site_ip', 'subnet_mask'
                ];
                
                foreach ($requiredFields as $field) {
                    if (!isset($binding[$field])) {
                        $this->cleanupTestData();
                        return [
                            'success' => false,
                            'message' => "Required field '$field' should be present in binding",
                            'data' => ['binding' => $binding]
                        ];
                    }
                }
                
                // Clean up
                $this->cleanupTestData();
                
                return ['success' => true];
            },
            20
        );
    }
    
    /**
     * Run all property tests
     */
    public function runAllTests(): bool {
        $allPassed = true;
        
        $allPassed = $this->testIPToRouterQuery() && $allPassed;
        $allPassed = $this->testUnboundIPMasterQuery() && $allPassed;
        $allPassed = $this->testIPMasterBindingCompleteness() && $allPassed;
        
        echo "\n" . ($allPassed ? "All property tests passed!" : "Some property tests failed!") . "\n";
        
        return $allPassed;
    }
}

// Run tests if executed directly
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    $test = new IPToRouterQueryTest();
    $result = $test->runAllTests();
    exit($result ? 0 : 1);
}
