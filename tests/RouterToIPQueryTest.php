<?php
/**
 * Property Test: Router-to-IP Query
 * 
 * **Feature: ip-configuration-management, Property 14: Router-to-IP Query**
 * **Validates: Requirements 5.3**
 * 
 * Property 14: For any configured router query, the response SHALL include 
 * the bound IP_Master details.
 */

require_once __DIR__ . '/PropertyTestBase.php';
require_once __DIR__ . '/../models/IPLock.php';
require_once __DIR__ . '/../models/IPMaster.php';
require_once __DIR__ . '/../models/RouterIPBinding.php';
require_once __DIR__ . '/../repositories/IPLockRepository.php';
require_once __DIR__ . '/../repositories/IPMasterRepository.php';
require_once __DIR__ . '/../services/ConfigurationService.php';
require_once __DIR__ . '/../services/LockService.php';

class RouterToIPQueryTest extends PropertyTestBase {
    
    private $configurationService;
    private $lockService;
    private $lockRepository;
    private $ipMasterRepository;
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
     * Property Test 14: Router-to-IP Query
     * 
     * For any configured router query, the response SHALL include 
     * the bound IP_Master details.
     * 
     * **Feature: ip-configuration-management, Property 14: Router-to-IP Query**
     * **Validates: Requirements 5.3**
     */
    public function testRouterToIPQuery(): bool {
        echo "\n=== Property Test 14: Router-to-IP Query ===\n";
        
        if ($this->testUserId === null) {
            echo "Skipping: No valid user ID found in database\n";
            return true;
        }
        
        return $this->runPropertyTest(
            'Configured router query returns bound IP_Master details',
            function() {
                // Create a test IP_Master
                $ipMasterId = $this->createTestIPMaster(IPMaster::STATUS_AVAILABLE);
                if (!$ipMasterId) {
                    return [
                        'success' => false,
                        'message' => 'Failed to create test IP_Master'
                    ];
                }
                
                // Get the IP_Master details for verification
                $ipMaster = $this->ipMasterRepository->findById($ipMasterId);
                
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
                
                // Query router configuration
                $config = $this->configurationService->getRouterConfiguration($routerSerial);
                
                // Property check: Response should not be null
                if ($config === null) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => 'Router configuration query returned null',
                        'data' => ['router_serial' => $routerSerial]
                    ];
                }
                
                // Property check: Status should be 'configured'
                if ($config['status'] !== 'configured') {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => 'Router status should be configured',
                        'data' => ['status' => $config['status']]
                    ];
                }
                
                // Property check: ip_master should be present
                if (!isset($config['ip_master']) || $config['ip_master'] === null) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => 'IP_Master details should be present in response',
                        'data' => ['config' => $config]
                    ];
                }
                
                // Property check: ip_master should have correct ID
                if ((int)$config['ip_master']['id'] !== $ipMasterId) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => 'IP_Master ID should match',
                        'data' => [
                            'expected' => $ipMasterId,
                            'actual' => $config['ip_master']['id']
                        ]
                    ];
                }
                
                // Property check: ip_master should have all required fields
                $requiredFields = ['network_ip', 'router_ip', 'site_ip', 'subnet_mask'];
                foreach ($requiredFields as $field) {
                    if (!isset($config['ip_master'][$field])) {
                        $this->cleanupTestData();
                        return [
                            'success' => false,
                            'message' => "IP_Master should have $field field",
                            'data' => ['ip_master' => $config['ip_master']]
                        ];
                    }
                }
                
                // Property check: IP values should match original
                if ($config['ip_master']['network_ip'] !== $ipMaster['network_ip']) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => 'Network IP should match',
                        'data' => [
                            'expected' => $ipMaster['network_ip'],
                            'actual' => $config['ip_master']['network_ip']
                        ]
                    ];
                }
                
                if ($config['ip_master']['router_ip'] !== $ipMaster['router_ip']) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => 'Router IP should match',
                        'data' => [
                            'expected' => $ipMaster['router_ip'],
                            'actual' => $config['ip_master']['router_ip']
                        ]
                    ];
                }
                
                if ($config['ip_master']['site_ip'] !== $ipMaster['site_ip']) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => 'Site IP should match',
                        'data' => [
                            'expected' => $ipMaster['site_ip'],
                            'actual' => $config['ip_master']['site_ip']
                        ]
                    ];
                }
                
                if ($config['ip_master']['subnet_mask'] !== $ipMaster['subnet_mask']) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => 'Subnet mask should match',
                        'data' => [
                            'expected' => $ipMaster['subnet_mask'],
                            'actual' => $config['ip_master']['subnet_mask']
                        ]
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
     * Property Test: Unconfigured router returns null IP_Master
     * 
     * For any unconfigured router query, the response SHALL have status 'unconfigured'
     * and ip_master should be null.
     */
    public function testUnconfiguredRouterQuery(): bool {
        echo "\n=== Property Test: Unconfigured Router Query ===\n";
        
        if ($this->testUserId === null) {
            echo "Skipping: No valid user ID found in database\n";
            return true;
        }
        
        return $this->runPropertyTest(
            'Unconfigured router query returns null IP_Master',
            function() {
                // Generate a random router serial that doesn't exist
                $routerSerial = $this->generateRouterSerial();
                
                // Query router configuration
                $config = $this->configurationService->getRouterConfiguration($routerSerial);
                
                // Property check: Response should not be null
                if ($config === null) {
                    return [
                        'success' => false,
                        'message' => 'Router configuration query should not return null',
                        'data' => ['router_serial' => $routerSerial]
                    ];
                }
                
                // Property check: Status should be 'unconfigured'
                if ($config['status'] !== 'unconfigured') {
                    return [
                        'success' => false,
                        'message' => 'Router status should be unconfigured',
                        'data' => ['status' => $config['status']]
                    ];
                }
                
                // Property check: ip_master should be null
                if ($config['ip_master'] !== null) {
                    return [
                        'success' => false,
                        'message' => 'IP_Master should be null for unconfigured router',
                        'data' => ['ip_master' => $config['ip_master']]
                    ];
                }
                
                return ['success' => true];
            },
            20
        );
    }
    
    /**
     * Property Test: In-progress router returns IP_Master details
     * 
     * For any router with an active lock (in-progress configuration),
     * the response SHALL include the IP_Master details being configured.
     */
    public function testInProgressRouterQuery(): bool {
        echo "\n=== Property Test: In-Progress Router Query ===\n";
        
        if ($this->testUserId === null) {
            echo "Skipping: No valid user ID found in database\n";
            return true;
        }
        
        return $this->runPropertyTest(
            'In-progress router query returns IP_Master details',
            function() {
                // Create a test IP_Master
                $ipMasterId = $this->createTestIPMaster(IPMaster::STATUS_AVAILABLE);
                if (!$ipMasterId) {
                    return [
                        'success' => false,
                        'message' => 'Failed to create test IP_Master'
                    ];
                }
                
                // Get the IP_Master details for verification
                $ipMaster = $this->ipMasterRepository->findById($ipMasterId);
                
                // Generate a random router serial
                $routerSerial = $this->generateRouterSerial();
                
                // Start configuration (creates lock, doesn't complete)
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
                
                // Query router configuration (should be in_progress)
                $config = $this->configurationService->getRouterConfiguration($routerSerial);
                
                // Property check: Response should not be null
                if ($config === null) {
                    $this->configurationService->cancelConfiguration($lockId, $this->testUserId);
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => 'Router configuration query returned null',
                        'data' => ['router_serial' => $routerSerial]
                    ];
                }
                
                // Property check: Status should be 'in_progress'
                if ($config['status'] !== 'in_progress') {
                    $this->configurationService->cancelConfiguration($lockId, $this->testUserId);
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => 'Router status should be in_progress',
                        'data' => ['status' => $config['status']]
                    ];
                }
                
                // Property check: ip_master should be present
                if (!isset($config['ip_master']) || $config['ip_master'] === null) {
                    $this->configurationService->cancelConfiguration($lockId, $this->testUserId);
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => 'IP_Master details should be present for in-progress router',
                        'data' => ['config' => $config]
                    ];
                }
                
                // Property check: ip_master should have correct ID
                if ((int)$config['ip_master']['id'] !== $ipMasterId) {
                    $this->configurationService->cancelConfiguration($lockId, $this->testUserId);
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => 'IP_Master ID should match',
                        'data' => [
                            'expected' => $ipMasterId,
                            'actual' => $config['ip_master']['id']
                        ]
                    ];
                }
                
                // Property check: lock details should be present
                if (!isset($config['lock']) || $config['lock'] === null) {
                    $this->configurationService->cancelConfiguration($lockId, $this->testUserId);
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => 'Lock details should be present for in-progress router',
                        'data' => ['config' => $config]
                    ];
                }
                
                // Clean up
                $this->configurationService->cancelConfiguration($lockId, $this->testUserId);
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
        
        $allPassed = $this->testRouterToIPQuery() && $allPassed;
        $allPassed = $this->testUnconfiguredRouterQuery() && $allPassed;
        $allPassed = $this->testInProgressRouterQuery() && $allPassed;
        
        echo "\n" . ($allPassed ? "All property tests passed!" : "Some property tests failed!") . "\n";
        
        return $allPassed;
    }
}

// Run tests if executed directly
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    $test = new RouterToIPQueryTest();
    $result = $test->runAllTests();
    exit($result ? 0 : 1);
}
