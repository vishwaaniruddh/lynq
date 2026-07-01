<?php
/**
 * Property Test: Available Router Filtering
 * 
 * **Feature: ip-configuration-management, Property 6: Available Router Filtering**
 * **Validates: Requirements 2.2**
 * 
 * Property 6: For any router list returned for configuration, the list SHALL exclude 
 * all routers that have an active configuration session (locked) or are already configured.
 */

require_once __DIR__ . '/PropertyTestBase.php';
require_once __DIR__ . '/../models/IPLock.php';
require_once __DIR__ . '/../models/IPMaster.php';
require_once __DIR__ . '/../models/RouterIPBinding.php';
require_once __DIR__ . '/../repositories/IPLockRepository.php';
require_once __DIR__ . '/../repositories/IPMasterRepository.php';
require_once __DIR__ . '/../services/ConfigurationService.php';
require_once __DIR__ . '/../services/LockService.php';

class AvailableRouterFilteringTest extends PropertyTestBase {
    
    private $configurationService;
    private $lockService;
    private $lockRepository;
    private $ipMasterRepository;
    private $bindingModel;
    private $createdIPMasterIds = [];
    private $createdLockIds = [];
    private $createdBindingIds = [];
    private $createdAssetIds = [];
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
     * Create a test asset (router) in the assets table
     * Uses the actual assets table structure with product_id and status enum
     */
    protected function createTestAsset(string $serialNumber, string $status = 'in_stock'): ?int {
        try {
            // First, get a valid product_id from the products table
            $productId = $this->getValidProductId();
            if (!$productId) {
                error_log("No valid product found for test asset creation");
                return null;
            }
            
            $sql = "INSERT INTO assets (serial_number, product_id, status, created_at) VALUES (?, ?, ?, NOW())";
            $stmt = $this->db->prepare($sql);
            $stmt->bind_param('sis', $serialNumber, $productId, $status);
            $stmt->execute();
            $id = $this->db->insert_id;
            $stmt->close();
            $this->createdAssetIds[] = $id;
            return $id;
        } catch (Exception $e) {
            error_log("Failed to create test asset: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get a valid product ID from the database for testing
     */
    protected function getValidProductId(): ?int {
        $sql = "SELECT id FROM products LIMIT 1";
        $result = $this->getResults($sql, [], '');
        return !empty($result) ? (int)$result[0]['id'] : null;
    }
    
    /**
     * Get available routers using the same logic as the API
     * Uses the actual assets table structure with status 'in_stock'
     */
    protected function getAvailableRouters(): array {
        // Get routers that are in active configuration sessions
        $activeLocks = $this->lockRepository->getActiveLocks();
        $lockedRouterSerials = array_column($activeLocks, 'router_serial_number');
        
        // Get routers that are already configured
        $activeBindings = $this->bindingModel->getActiveBindingsWithDetails();
        $configuredRouterSerials = array_column($activeBindings, 'router_serial_number');
        
        // Combine excluded serials
        $excludedSerials = array_unique(array_merge($lockedRouterSerials, $configuredRouterSerials));
        
        // Query inventory for available routers (status = 'in_stock')
        $sql = "SELECT DISTINCT serial_number, status, product_id
                FROM assets 
                WHERE serial_number IS NOT NULL 
                AND serial_number != ''
                AND status = 'in_stock'";
        
        $params = [];
        $types = '';
        
        if (!empty($excludedSerials)) {
            $placeholders = implode(',', array_fill(0, count($excludedSerials), '?'));
            $sql .= " AND serial_number NOT IN ($placeholders)";
            $params = $excludedSerials;
            $types = str_repeat('s', count($excludedSerials));
        }
        
        $sql .= " ORDER BY serial_number ASC";
        
        try {
            $results = DatabaseConfig::getInstance()->getResults($sql, $params, $types);
            return $results ?: [];
        } catch (Exception $e) {
            return [];
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
        
        // Delete test assets
        foreach ($this->createdAssetIds as $assetId) {
            try {
                $sql = "DELETE FROM `assets` WHERE `id` = ?";
                $stmt = $this->db->prepare($sql);
                $stmt->bind_param('i', $assetId);
                $stmt->execute();
                $stmt->close();
            } catch (Exception $e) {
                // Ignore cleanup errors
            }
        }
        
        $this->createdBindingIds = [];
        $this->createdLockIds = [];
        $this->createdIPMasterIds = [];
        $this->createdAssetIds = [];
    }
    
    /**
     * Property Test 6: Available Router Filtering
     * 
     * For any router list returned for configuration, the list SHALL exclude 
     * all routers that have an active configuration session (locked) or are already configured.
     * 
     * **Feature: ip-configuration-management, Property 6: Available Router Filtering**
     * **Validates: Requirements 2.2**
     */
    public function testAvailableRouterFiltering(): bool {
        echo "\n=== Property Test 6: Available Router Filtering ===\n";
        
        if ($this->testUserId === null) {
            echo "Skipping: No valid user ID found in database\n";
            return true;
        }
        
        return $this->runPropertyTest(
            'Available router list excludes locked and configured routers',
            function() {
                // Create test IP_Master for locking
                $ipMasterId = $this->createTestIPMaster(IPMaster::STATUS_AVAILABLE);
                if (!$ipMasterId) {
                    return [
                        'success' => false,
                        'message' => 'Failed to create test IP_Master'
                    ];
                }
                
                // Create test routers
                $availableSerial = $this->generateRouterSerial();
                $lockedSerial = $this->generateRouterSerial();
                $configuredSerial = $this->generateRouterSerial();
                
                // Create assets in the database (status 'in_stock' for available)
                $this->createTestAsset($availableSerial, 'in_stock');
                $this->createTestAsset($lockedSerial, 'in_stock');
                $this->createTestAsset($configuredSerial, 'in_stock');
                
                // Start configuration for the "locked" router (creates a lock)
                $startResult = $this->configurationService->startConfiguration(
                    $lockedSerial,
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
                
                // Create another IP_Master for the configured router
                $ipMasterId2 = $this->createTestIPMaster(IPMaster::STATUS_AVAILABLE);
                if (!$ipMasterId2) {
                    $this->configurationService->cancelConfiguration($lockId, $this->testUserId);
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => 'Failed to create second test IP_Master'
                    ];
                }
                
                // Create a binding for the "configured" router
                $bindingData = [
                    'router_serial_number' => $configuredSerial,
                    'ip_master_id' => $ipMasterId2,
                    'configured_by' => $this->testUserId,
                    'configured_at' => date('Y-m-d H:i:s'),
                    'notes' => 'Test binding',
                    'status' => RouterIPBinding::STATUS_ACTIVE
                ];
                
                $binding = $this->bindingModel->create($bindingData);
                if (!$binding) {
                    $this->configurationService->cancelConfiguration($lockId, $this->testUserId);
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => 'Failed to create test binding'
                    ];
                }
                $this->createdBindingIds[] = $binding['id'];
                
                // Update IP_Master status to configured
                $sql = "UPDATE `ip_master` SET `status` = ? WHERE `id` = ?";
                $stmt = $this->db->prepare($sql);
                $configuredStatus = IPMaster::STATUS_CONFIGURED;
                $stmt->bind_param('si', $configuredStatus, $ipMasterId2);
                $stmt->execute();
                $stmt->close();
                
                // Get available routers
                $availableRouters = $this->getAvailableRouters();
                $availableSerials = array_column($availableRouters, 'serial_number');
                
                // Property check: Locked router should NOT be in available list
                if (in_array($lockedSerial, $availableSerials)) {
                    $this->configurationService->cancelConfiguration($lockId, $this->testUserId);
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => 'Locked router should not be in available list',
                        'data' => [
                            'locked_serial' => $lockedSerial,
                            'available_serials' => $availableSerials
                        ]
                    ];
                }
                
                // Property check: Configured router should NOT be in available list
                if (in_array($configuredSerial, $availableSerials)) {
                    $this->configurationService->cancelConfiguration($lockId, $this->testUserId);
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => 'Configured router should not be in available list',
                        'data' => [
                            'configured_serial' => $configuredSerial,
                            'available_serials' => $availableSerials
                        ]
                    ];
                }
                
                // Property check: Available router SHOULD be in available list
                if (!in_array($availableSerial, $availableSerials)) {
                    $this->configurationService->cancelConfiguration($lockId, $this->testUserId);
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => 'Available router should be in available list',
                        'data' => [
                            'available_serial' => $availableSerial,
                            'available_serials' => $availableSerials
                        ]
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
     * Property Test: Locked routers are excluded from available list
     * 
     * For any router with an active lock, it SHALL NOT appear in the available router list.
     */
    public function testLockedRoutersExcluded(): bool {
        echo "\n=== Property Test: Locked Routers Excluded ===\n";
        
        if ($this->testUserId === null) {
            echo "Skipping: No valid user ID found in database\n";
            return true;
        }
        
        return $this->runPropertyTest(
            'Locked routers are excluded from available list',
            function() {
                // Create test IP_Master
                $ipMasterId = $this->createTestIPMaster(IPMaster::STATUS_AVAILABLE);
                if (!$ipMasterId) {
                    return [
                        'success' => false,
                        'message' => 'Failed to create test IP_Master'
                    ];
                }
                
                // Create a test router (status 'in_stock' for available)
                $routerSerial = $this->generateRouterSerial();
                $this->createTestAsset($routerSerial, 'in_stock');
                
                // Verify router is in available list before locking
                $availableBefore = $this->getAvailableRouters();
                $serialsBefore = array_column($availableBefore, 'serial_number');
                
                if (!in_array($routerSerial, $serialsBefore)) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => 'Router should be available before locking',
                        'data' => ['serial' => $routerSerial]
                    ];
                }
                
                // Start configuration (creates lock)
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
                
                // Verify router is NOT in available list after locking
                $availableAfter = $this->getAvailableRouters();
                $serialsAfter = array_column($availableAfter, 'serial_number');
                
                if (in_array($routerSerial, $serialsAfter)) {
                    $this->configurationService->cancelConfiguration($lockId, $this->testUserId);
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => 'Locked router should not be in available list',
                        'data' => ['serial' => $routerSerial]
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
     * Property Test: Configured routers are excluded from available list
     * 
     * For any router with an active binding, it SHALL NOT appear in the available router list.
     */
    public function testConfiguredRoutersExcluded(): bool {
        echo "\n=== Property Test: Configured Routers Excluded ===\n";
        
        if ($this->testUserId === null) {
            echo "Skipping: No valid user ID found in database\n";
            return true;
        }
        
        return $this->runPropertyTest(
            'Configured routers are excluded from available list',
            function() {
                // Create test IP_Master
                $ipMasterId = $this->createTestIPMaster(IPMaster::STATUS_AVAILABLE);
                if (!$ipMasterId) {
                    return [
                        'success' => false,
                        'message' => 'Failed to create test IP_Master'
                    ];
                }
                
                // Create a test router (status 'in_stock' for available)
                $routerSerial = $this->generateRouterSerial();
                $this->createTestAsset($routerSerial, 'in_stock');
                
                // Verify router is in available list before configuration
                $availableBefore = $this->getAvailableRouters();
                $serialsBefore = array_column($availableBefore, 'serial_number');
                
                if (!in_array($routerSerial, $serialsBefore)) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => 'Router should be available before configuration',
                        'data' => ['serial' => $routerSerial]
                    ];
                }
                
                // Create a binding (simulating completed configuration)
                $bindingData = [
                    'router_serial_number' => $routerSerial,
                    'ip_master_id' => $ipMasterId,
                    'configured_by' => $this->testUserId,
                    'configured_at' => date('Y-m-d H:i:s'),
                    'notes' => 'Test binding',
                    'status' => RouterIPBinding::STATUS_ACTIVE
                ];
                
                $binding = $this->bindingModel->create($bindingData);
                if (!$binding) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => 'Failed to create test binding'
                    ];
                }
                $this->createdBindingIds[] = $binding['id'];
                
                // Update IP_Master status to configured
                $sql = "UPDATE `ip_master` SET `status` = ? WHERE `id` = ?";
                $stmt = $this->db->prepare($sql);
                $configuredStatus = IPMaster::STATUS_CONFIGURED;
                $stmt->bind_param('si', $configuredStatus, $ipMasterId);
                $stmt->execute();
                $stmt->close();
                
                // Verify router is NOT in available list after configuration
                $availableAfter = $this->getAvailableRouters();
                $serialsAfter = array_column($availableAfter, 'serial_number');
                
                if (in_array($routerSerial, $serialsAfter)) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => 'Configured router should not be in available list',
                        'data' => ['serial' => $routerSerial]
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
     * Run all property tests
     */
    public function runAllTests(): bool {
        $allPassed = true;
        
        $allPassed = $this->testAvailableRouterFiltering() && $allPassed;
        $allPassed = $this->testLockedRoutersExcluded() && $allPassed;
        $allPassed = $this->testConfiguredRoutersExcluded() && $allPassed;
        
        echo "\n" . ($allPassed ? "All property tests passed!" : "Some property tests failed!") . "\n";
        
        return $allPassed;
    }
}

// Run tests if executed directly
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    $test = new AvailableRouterFilteringTest();
    $result = $test->runAllTests();
    exit($result ? 0 : 1);
}
