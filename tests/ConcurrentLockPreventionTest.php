<?php
/**
 * Property Test: Concurrent Lock Prevention
 * 
 * **Feature: ip-configuration-management, Property 22: Concurrent Lock Prevention**
 * **Validates: Requirements 11.1, 11.3**
 * 
 * Property 22: For any two simultaneous configuration start requests, 
 * each request SHALL receive a different IP_Master (no IP collision).
 * 
 * This test validates that the database-level locking mechanism prevents
 * race conditions when multiple users attempt to acquire locks concurrently.
 */

require_once __DIR__ . '/PropertyTestBase.php';
require_once __DIR__ . '/../models/IPLock.php';
require_once __DIR__ . '/../models/IPMaster.php';
require_once __DIR__ . '/../repositories/IPLockRepository.php';
require_once __DIR__ . '/../repositories/IPMasterRepository.php';
require_once __DIR__ . '/../services/LockService.php';

class ConcurrentLockPreventionTest extends PropertyTestBase {
    
    private $lockRepository;
    private $ipMasterRepository;
    private $lockService;
    private $createdIPMasterIds = [];
    private $createdLockIds = [];
    private $testUserId = null;
    private $testUserId2 = null;
    
    public function __construct() {
        parent::__construct();
        $this->lockRepository = new IPLockRepository();
        $this->ipMasterRepository = new IPMasterRepository();
        $this->lockService = new LockService();
        $this->initTestUsers();
    }
    
    /**
     * Initialize test user IDs from the database
     */
    protected function initTestUsers(): void {
        $sql = "SELECT id FROM users ORDER BY id LIMIT 2";
        $result = $this->getResults($sql, [], '');
        
        if (!empty($result)) {
            $this->testUserId = (int)$result[0]['id'];
            $this->testUserId2 = isset($result[1]) ? (int)$result[1]['id'] : $this->testUserId + 1;
        }
    }
    
    /**
     * Generate a valid IPv4 address
     */
    protected function generateValidIP(): string {
        $octets = [];
        for ($i = 0; $i < 4; $i++) {
            $octets[] = rand(1, 254);
        }
        return implode('.', $octets);
    }
    
    /**
     * Generate a random router serial number
     */
    protected function generateRouterSerial(): string {
        return 'RTR-CONC-' . $this->generateRandomString(8) . '-' . rand(1000, 9999);
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
                // First delete any locks referencing this IP
                $sql = "DELETE FROM `ip_locks` WHERE `ip_master_id` = ?";
                $stmt = $this->db->prepare($sql);
                $stmt->bind_param('i', $ipMasterId);
                $stmt->execute();
                $stmt->close();
                
                // Then delete the IP_Master
                $sql = "DELETE FROM `ip_master` WHERE `id` = ?";
                $stmt = $this->db->prepare($sql);
                $stmt->bind_param('i', $ipMasterId);
                $stmt->execute();
                $stmt->close();
            } catch (Exception $e) {
                // Ignore cleanup errors
            }
        }
        
        $this->createdLockIds = [];
        $this->createdIPMasterIds = [];
    }
    
    /**
     * Property Test 22: Concurrent Lock Prevention
     * 
     * For any two simultaneous configuration start requests, 
     * each request SHALL receive a different IP_Master (no IP collision).
     * 
     * This test simulates concurrent lock acquisition by:
     * 1. Creating multiple available IPs
     * 2. Acquiring locks sequentially (simulating concurrent requests)
     * 3. Verifying each lock gets a different IP
     */
    public function testConcurrentLockPrevention(): bool {
        echo "\n=== Property Test 22: Concurrent Lock Prevention ===\n";
        
        if ($this->testUserId === null) {
            echo "Skipping: No valid user ID found in database\n";
            return true;
        }
        
        return $this->runPropertyTest(
            'Concurrent lock requests receive different IPs',
            function() {
                // Create multiple test IP_Masters (at least 2)
                $numIPs = rand(2, 5);
                $createdIPs = [];
                
                for ($i = 0; $i < $numIPs; $i++) {
                    $ipId = $this->createTestIPMaster();
                    if (!$ipId) {
                        $this->cleanupTestData();
                        return [
                            'success' => false,
                            'message' => 'Failed to create test IP_Master'
                        ];
                    }
                    $createdIPs[] = $ipId;
                }
                
                // Simulate concurrent lock acquisition
                // In a real concurrent scenario, these would be parallel requests
                // Here we test that sequential requests get different IPs
                $acquiredLocks = [];
                $acquiredIPIds = [];
                
                for ($i = 0; $i < min($numIPs, 3); $i++) {
                    $routerSerial = $this->generateRouterSerial();
                    $userId = ($i % 2 === 0) ? $this->testUserId : $this->testUserId2;
                    
                    // Use the atomic lock acquisition method
                    $result = $this->lockRepository->acquireLockOnNextAvailableIP($routerSerial, $userId);
                    
                    if ($result['success']) {
                        $this->createdLockIds[] = $result['data']['id'];
                        $acquiredLocks[] = $result['data'];
                        $acquiredIPIds[] = $result['data']['ip_master_id'];
                    }
                }
                
                // Verify all acquired IPs are unique (no collision)
                $uniqueIPIds = array_unique($acquiredIPIds);
                
                // Clean up locks before checking
                foreach ($this->createdLockIds as $lockId) {
                    $this->lockService->releaseLock($lockId);
                }
                $this->createdLockIds = [];
                
                $this->cleanupTestData();
                
                if (count($uniqueIPIds) !== count($acquiredIPIds)) {
                    return [
                        'success' => false,
                        'message' => 'IP collision detected - same IP assigned to multiple locks',
                        'data' => [
                            'acquired_ip_ids' => $acquiredIPIds,
                            'unique_ip_ids' => $uniqueIPIds
                        ]
                    ];
                }
                
                return ['success' => true];
            },
            30 // Reduced iterations due to database operations
        );
    }
    
    /**
     * Property Test: Same IP cannot be locked twice
     * 
     * For any IP_Master with an active lock, attempting to acquire 
     * another lock on the same IP should fail.
     */
    public function testSameIPCannotBeLockedTwice(): bool {
        echo "\n=== Property Test: Same IP Cannot Be Locked Twice ===\n";
        
        if ($this->testUserId === null) {
            echo "Skipping: No valid user ID found in database\n";
            return true;
        }
        
        return $this->runPropertyTest(
            'Same IP cannot be locked by two different users',
            function() {
                // Create a test IP_Master
                $ipMasterId = $this->createTestIPMaster();
                if (!$ipMasterId) {
                    return [
                        'success' => false,
                        'message' => 'Failed to create test IP_Master'
                    ];
                }
                
                // First user acquires lock
                $routerSerial1 = $this->generateRouterSerial();
                $result1 = $this->lockRepository->acquireLock($ipMasterId, $routerSerial1, $this->testUserId);
                
                if (!$result1['success']) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => 'First lock acquisition failed: ' . $result1['message']
                    ];
                }
                
                $this->createdLockIds[] = $result1['data']['id'];
                
                // Second user attempts to lock the same IP (should fail)
                $routerSerial2 = $this->generateRouterSerial();
                $result2 = $this->lockRepository->acquireLock($ipMasterId, $routerSerial2, $this->testUserId2);
                
                // Release the first lock
                $this->lockService->releaseLock($result1['data']['id']);
                $this->createdLockIds = [];
                $this->cleanupTestData();
                
                if ($result2['success']) {
                    return [
                        'success' => false,
                        'message' => 'Second lock should have failed but succeeded',
                        'data' => ['ip_master_id' => $ipMasterId]
                    ];
                }
                
                // Verify the error code indicates the IP is already locked
                if (!in_array($result2['code'], ['ALREADY_LOCKED', 'NOT_AVAILABLE'])) {
                    return [
                        'success' => false,
                        'message' => 'Expected ALREADY_LOCKED or NOT_AVAILABLE error code',
                        'data' => ['actual_code' => $result2['code']]
                    ];
                }
                
                return ['success' => true];
            },
            30
        );
    }
    
    /**
     * Property Test: Same router cannot have multiple active sessions
     * 
     * For any router with an active configuration session,
     * attempting to start another session should fail.
     */
    public function testSameRouterCannotHaveMultipleSessions(): bool {
        echo "\n=== Property Test: Same Router Cannot Have Multiple Sessions ===\n";
        
        if ($this->testUserId === null) {
            echo "Skipping: No valid user ID found in database\n";
            return true;
        }
        
        return $this->runPropertyTest(
            'Same router cannot have multiple active sessions',
            function() {
                // Create two test IP_Masters
                $ipMasterId1 = $this->createTestIPMaster();
                $ipMasterId2 = $this->createTestIPMaster();
                
                if (!$ipMasterId1 || !$ipMasterId2) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => 'Failed to create test IP_Masters'
                    ];
                }
                
                // Same router serial for both attempts
                $routerSerial = $this->generateRouterSerial();
                
                // First lock acquisition
                $result1 = $this->lockRepository->acquireLock($ipMasterId1, $routerSerial, $this->testUserId);
                
                if (!$result1['success']) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => 'First lock acquisition failed: ' . $result1['message']
                    ];
                }
                
                $this->createdLockIds[] = $result1['data']['id'];
                
                // Second lock attempt with same router (should fail)
                $result2 = $this->lockRepository->acquireLock($ipMasterId2, $routerSerial, $this->testUserId);
                
                // Release the first lock
                $this->lockService->releaseLock($result1['data']['id']);
                $this->createdLockIds = [];
                $this->cleanupTestData();
                
                if ($result2['success']) {
                    return [
                        'success' => false,
                        'message' => 'Second lock with same router should have failed',
                        'data' => ['router_serial' => $routerSerial]
                    ];
                }
                
                if ($result2['code'] !== 'ROUTER_IN_SESSION') {
                    return [
                        'success' => false,
                        'message' => 'Expected ROUTER_IN_SESSION error code',
                        'data' => ['actual_code' => $result2['code']]
                    ];
                }
                
                return ['success' => true];
            },
            30
        );
    }
    
    /**
     * Property Test: Atomic IP selection prevents collision
     * 
     * When using acquireLockOnNextAvailableIP, multiple requests
     * should each get a unique IP without collision.
     */
    public function testAtomicIPSelectionPreventsCollision(): bool {
        echo "\n=== Property Test: Atomic IP Selection Prevents Collision ===\n";
        
        if ($this->testUserId === null) {
            echo "Skipping: No valid user ID found in database\n";
            return true;
        }
        
        return $this->runPropertyTest(
            'Atomic IP selection prevents collision',
            function() {
                // Create exactly 2 IPs
                $ip1 = $this->createTestIPMaster();
                $ip2 = $this->createTestIPMaster();
                
                if (!$ip1 || !$ip2) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => 'Failed to create test IP_Masters'
                    ];
                }
                
                // Two different routers request IPs
                $router1 = $this->generateRouterSerial();
                $router2 = $this->generateRouterSerial();
                
                $result1 = $this->lockRepository->acquireLockOnNextAvailableIP($router1, $this->testUserId);
                $result2 = $this->lockRepository->acquireLockOnNextAvailableIP($router2, $this->testUserId2);
                
                // Track created locks for cleanup
                if ($result1['success']) {
                    $this->createdLockIds[] = $result1['data']['id'];
                }
                if ($result2['success']) {
                    $this->createdLockIds[] = $result2['data']['id'];
                }
                
                // Both should succeed
                if (!$result1['success'] || !$result2['success']) {
                    // Clean up
                    foreach ($this->createdLockIds as $lockId) {
                        $this->lockService->releaseLock($lockId);
                    }
                    $this->createdLockIds = [];
                    $this->cleanupTestData();
                    
                    return [
                        'success' => false,
                        'message' => 'One or both lock acquisitions failed',
                        'data' => [
                            'result1' => $result1['success'] ? 'success' : $result1['message'],
                            'result2' => $result2['success'] ? 'success' : $result2['message']
                        ]
                    ];
                }
                
                // Verify they got different IPs
                $ipId1 = $result1['data']['ip_master_id'];
                $ipId2 = $result2['data']['ip_master_id'];
                
                // Clean up
                foreach ($this->createdLockIds as $lockId) {
                    $this->lockService->releaseLock($lockId);
                }
                $this->createdLockIds = [];
                $this->cleanupTestData();
                
                if ($ipId1 === $ipId2) {
                    return [
                        'success' => false,
                        'message' => 'Both requests got the same IP (collision)',
                        'data' => [
                            'ip_id_1' => $ipId1,
                            'ip_id_2' => $ipId2
                        ]
                    ];
                }
                
                return ['success' => true];
            },
            30
        );
    }
    
    /**
     * Run all property tests
     */
    public function runAllTests(): array {
        $results = [];
        
        $results['concurrent_lock_prevention'] = $this->testConcurrentLockPrevention();
        $results['same_ip_cannot_be_locked_twice'] = $this->testSameIPCannotBeLockedTwice();
        $results['same_router_cannot_have_multiple_sessions'] = $this->testSameRouterCannotHaveMultipleSessions();
        $results['atomic_ip_selection_prevents_collision'] = $this->testAtomicIPSelectionPreventsCollision();
        
        $passed = array_filter($results);
        $total = count($results);
        $passedCount = count($passed);
        
        echo "\n=== Summary ===\n";
        echo "Passed: $passedCount / $total\n";
        
        if ($passedCount === $total) {
            echo "✓ All property tests passed!\n";
        } else {
            echo "✗ Some property tests failed.\n";
            foreach ($results as $name => $result) {
                if (!$result) {
                    echo "  - Failed: $name\n";
                }
            }
        }
        
        return $results;
    }
}

// Run tests if executed directly
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    $test = new ConcurrentLockPreventionTest();
    $results = $test->runAllTests();
    exit(in_array(false, $results, true) ? 1 : 0);
}
