<?php
/**
 * Property Tests: Lock Exclusivity and Expiry
 * 
 * **Feature: ip-configuration-management, Property 8: Lock Exclusivity**
 * **Feature: ip-configuration-management, Property 10: Lock Expiry Handling**
 * **Validates: Requirements 4.2, 4.3, 11.2**
 * 
 * Property 8: For any IP_Master with an active lock, the IP SHALL be excluded 
 * from the available IP list for all other users.
 * 
 * Property 10: For any IP lock that exceeds 20 minutes without completion, 
 * the system SHALL automatically release the lock and set IP status back to 'available'.
 */

require_once __DIR__ . '/PropertyTestBase.php';
require_once __DIR__ . '/../models/IPLock.php';
require_once __DIR__ . '/../models/IPMaster.php';
require_once __DIR__ . '/../repositories/IPLockRepository.php';
require_once __DIR__ . '/../repositories/IPMasterRepository.php';
require_once __DIR__ . '/../services/LockService.php';

class LockExclusivityExpiryTest extends PropertyTestBase {
    
    private $lockRepository;
    private $ipMasterRepository;
    private $lockService;
    private $createdIPMasterIds = [];
    private $createdLockIds = [];
    private $testUserId = null; // Will be set from database
    
    public function __construct() {
        parent::__construct();
        $this->lockRepository = new IPLockRepository();
        $this->ipMasterRepository = new IPMasterRepository();
        $this->lockService = new LockService();
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
     * Get database time with offset (to handle timezone differences)
     * 
     * @param int $offsetSeconds Offset in seconds (positive for future, negative for past)
     * @return string Database timestamp
     */
    protected function getDBTimeWithOffset(int $offsetSeconds): string {
        $sql = "SELECT DATE_ADD(NOW(), INTERVAL ? SECOND) as time_value";
        $result = $this->getResults($sql, [$offsetSeconds], 'i');
        return $result[0]['time_value'];
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
        return 'RTR-' . $this->generateRandomString(8) . '-' . rand(1000, 9999);
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
            
            // Only add created_by if we have a valid user ID
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
     * Create a test lock directly in the database (for testing expiry)
     * Uses database time to avoid timezone issues
     */
    protected function createTestLockWithExpiry(int $ipMasterId, string $routerSerial, int $userId, string $expiresAt): ?int {
        try {
            $conn = $this->db;
            $status = IPLock::STATUS_ACTIVE;
            
            // Use database NOW() for locked_at to avoid timezone issues
            $sql = "INSERT INTO `ip_locks` 
                    (`ip_master_id`, `router_serial_number`, `locked_by`, `locked_at`, `expires_at`, `status`) 
                    VALUES (?, ?, ?, NOW(), ?, ?)";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('isiss', $ipMasterId, $routerSerial, $userId, $expiresAt, $status);
            $stmt->execute();
            $lockId = $conn->insert_id;
            $stmt->close();
            
            // Update IP_Master status to locked
            $sql = "UPDATE `ip_master` SET `status` = ? WHERE `id` = ?";
            $stmt = $conn->prepare($sql);
            $lockedStatus = IPMaster::STATUS_LOCKED;
            $stmt->bind_param('si', $lockedStatus, $ipMasterId);
            $stmt->execute();
            $stmt->close();
            
            $this->createdLockIds[] = $lockId;
            return $lockId;
        } catch (Exception $e) {
            error_log("Failed to create test lock: " . $e->getMessage());
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
     * Property Test 8: Lock Exclusivity
     * 
     * For any IP_Master with an active lock, the IP SHALL be excluded 
     * from the available IP list for all other users.
     */
    public function testLockExclusivity(): bool {
        echo "\n=== Property Test 8: Lock Exclusivity ===\n";
        
        // Skip if no valid user ID
        if ($this->testUserId === null) {
            echo "Skipping: No valid user ID found in database\n";
            return true;
        }
        
        return $this->runPropertyTest(
            'Locked IPs are excluded from available list',
            function() {
                // Create a test IP_Master
                $ipMasterId = $this->createTestIPMaster();
                if (!$ipMasterId) {
                    return [
                        'success' => false,
                        'message' => 'Failed to create test IP_Master'
                    ];
                }
                
                // Verify IP is in available list before locking
                $availableBefore = $this->lockService->getAvailableIPs();
                $foundBefore = false;
                foreach ($availableBefore as $ip) {
                    if ((int)$ip['id'] === $ipMasterId) {
                        $foundBefore = true;
                        break;
                    }
                }
                
                if (!$foundBefore) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => 'IP_Master not found in available list before locking',
                        'data' => ['ip_master_id' => $ipMasterId]
                    ];
                }
                
                // Acquire a lock on the IP
                $routerSerial = $this->generateRouterSerial();
                $lockResult = $this->lockService->acquireLock($ipMasterId, $routerSerial, $this->testUserId);
                
                if (!$lockResult['success']) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => 'Failed to acquire lock: ' . $lockResult['message'],
                        'data' => ['ip_master_id' => $ipMasterId]
                    ];
                }
                
                $this->createdLockIds[] = $lockResult['data']['id'];
                
                // Verify IP is NOT in available list after locking
                $availableAfter = $this->lockService->getAvailableIPs();
                $foundAfter = false;
                foreach ($availableAfter as $ip) {
                    if ((int)$ip['id'] === $ipMasterId) {
                        $foundAfter = true;
                        break;
                    }
                }
                
                // Clean up
                $this->lockService->releaseLock($lockResult['data']['id']);
                $this->cleanupTestData();
                
                if ($foundAfter) {
                    return [
                        'success' => false,
                        'message' => 'Locked IP was still found in available list',
                        'data' => ['ip_master_id' => $ipMasterId]
                    ];
                }
                
                return ['success' => true];
            },
            20 // Reduced iterations due to database operations
        );
    }
    
    /**
     * Property Test: Second lock attempt on same IP fails
     * 
     * For any IP_Master with an active lock, attempting to acquire 
     * another lock should fail.
     */
    public function testSecondLockAttemptFails(): bool {
        echo "\n=== Property Test: Second Lock Attempt Fails ===\n";
        
        // Skip if no valid user ID
        if ($this->testUserId === null) {
            echo "Skipping: No valid user ID found in database\n";
            return true;
        }
        
        return $this->runPropertyTest(
            'Second lock attempt on same IP fails',
            function() {
                // Create a test IP_Master
                $ipMasterId = $this->createTestIPMaster();
                if (!$ipMasterId) {
                    return [
                        'success' => false,
                        'message' => 'Failed to create test IP_Master'
                    ];
                }
                
                // Acquire first lock
                $routerSerial1 = $this->generateRouterSerial();
                $lockResult1 = $this->lockService->acquireLock($ipMasterId, $routerSerial1, $this->testUserId);
                
                if (!$lockResult1['success']) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => 'Failed to acquire first lock: ' . $lockResult1['message']
                    ];
                }
                
                $this->createdLockIds[] = $lockResult1['data']['id'];
                
                // Attempt second lock (should fail)
                $routerSerial2 = $this->generateRouterSerial();
                $lockResult2 = $this->lockService->acquireLock($ipMasterId, $routerSerial2, $this->testUserId + 1);
                
                // Clean up
                $this->lockService->releaseLock($lockResult1['data']['id']);
                $this->cleanupTestData();
                
                if ($lockResult2['success']) {
                    return [
                        'success' => false,
                        'message' => 'Second lock attempt should have failed but succeeded',
                        'data' => ['ip_master_id' => $ipMasterId]
                    ];
                }
                
                return ['success' => true];
            },
            20
        );
    }
    
    /**
     * Property Test 10: Lock Expiry Handling
     * 
     * For any IP lock that exceeds 20 minutes without completion, 
     * the system SHALL automatically release the lock and set IP status back to 'available'.
     */
    public function testLockExpiryHandling(): bool {
        echo "\n=== Property Test 10: Lock Expiry Handling ===\n";
        
        // Skip if no valid user ID
        if ($this->testUserId === null) {
            echo "Skipping: No valid user ID found in database\n";
            return true;
        }
        
        return $this->runPropertyTest(
            'Expired locks are automatically released',
            function() {
                // Create a test IP_Master
                $ipMasterId = $this->createTestIPMaster();
                if (!$ipMasterId) {
                    return [
                        'success' => false,
                        'message' => 'Failed to create test IP_Master'
                    ];
                }
                
                // Create a lock that has already expired (expires_at in the past)
                $routerSerial = $this->generateRouterSerial();
                // Use database time to avoid timezone issues
                $expiredTime = $this->getDBTimeWithOffset(-rand(60, 3600)); // 1 minute to 1 hour ago
                
                $lockId = $this->createTestLockWithExpiry($ipMasterId, $routerSerial, $this->testUserId, $expiredTime);
                if (!$lockId) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => 'Failed to create expired test lock'
                    ];
                }
                
                // Verify IP_Master status is 'locked' before expiry processing
                $ipMasterBefore = $this->ipMasterRepository->findById($ipMasterId);
                if ($ipMasterBefore['status'] !== IPMaster::STATUS_LOCKED) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => 'IP_Master status should be locked before expiry processing',
                        'data' => ['status' => $ipMasterBefore['status']]
                    ];
                }
                
                // Run expiry processing
                $expiredCount = $this->lockService->expireTimedOutLocks();
                
                // Verify lock status changed to 'expired'
                $lock = $this->lockRepository->findById($lockId);
                if ($lock['status'] !== IPLock::STATUS_EXPIRED) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => 'Lock status should be expired after processing',
                        'data' => ['lock_status' => $lock['status']]
                    ];
                }
                
                // Verify IP_Master status changed back to 'available'
                $ipMasterAfter = $this->ipMasterRepository->findById($ipMasterId);
                if ($ipMasterAfter['status'] !== IPMaster::STATUS_AVAILABLE) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => 'IP_Master status should be available after lock expiry',
                        'data' => ['status' => $ipMasterAfter['status']]
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
     * Property Test: Non-expired locks are not released
     * 
     * For any IP lock that has not exceeded 20 minutes, 
     * the expiry process should not release it.
     */
    public function testNonExpiredLocksNotReleased(): bool {
        echo "\n=== Property Test: Non-Expired Locks Not Released ===\n";
        
        // Skip if no valid user ID
        if ($this->testUserId === null) {
            echo "Skipping: No valid user ID found in database\n";
            return true;
        }
        
        return $this->runPropertyTest(
            'Non-expired locks are not released by expiry process',
            function() {
                // Create a test IP_Master
                $ipMasterId = $this->createTestIPMaster();
                if (!$ipMasterId) {
                    return [
                        'success' => false,
                        'message' => 'Failed to create test IP_Master'
                    ];
                }
                
                // Create a lock that has NOT expired (expires_at in the future)
                $routerSerial = $this->generateRouterSerial();
                // Use database time to avoid timezone issues
                $futureTime = $this->getDBTimeWithOffset(rand(60, 1200)); // 1 to 20 minutes in future
                
                $lockId = $this->createTestLockWithExpiry($ipMasterId, $routerSerial, $this->testUserId, $futureTime);
                if (!$lockId) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => 'Failed to create test lock'
                    ];
                }
                
                // Run expiry processing
                $this->lockService->expireTimedOutLocks();
                
                // Verify lock status is still 'active'
                $lock = $this->lockRepository->findById($lockId);
                if ($lock['status'] !== IPLock::STATUS_ACTIVE) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => 'Non-expired lock should still be active',
                        'data' => ['lock_status' => $lock['status'], 'expires_at' => $futureTime]
                    ];
                }
                
                // Verify IP_Master status is still 'locked'
                $ipMaster = $this->ipMasterRepository->findById($ipMasterId);
                if ($ipMaster['status'] !== IPMaster::STATUS_LOCKED) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => 'IP_Master should still be locked',
                        'data' => ['status' => $ipMaster['status']]
                    ];
                }
                
                // Clean up - manually release the lock
                $this->lockService->releaseLock($lockId);
                $this->cleanupTestData();
                
                return ['success' => true];
            },
            20
        );
    }
    
    /**
     * Property Test: Released lock makes IP available again
     * 
     * For any released lock, the IP should become available again.
     */
    public function testReleasedLockMakesIPAvailable(): bool {
        echo "\n=== Property Test: Released Lock Makes IP Available ===\n";
        
        // Skip if no valid user ID
        if ($this->testUserId === null) {
            echo "Skipping: No valid user ID found in database\n";
            return true;
        }
        
        return $this->runPropertyTest(
            'Released lock makes IP available again',
            function() {
                // Create a test IP_Master
                $ipMasterId = $this->createTestIPMaster();
                if (!$ipMasterId) {
                    return [
                        'success' => false,
                        'message' => 'Failed to create test IP_Master'
                    ];
                }
                
                // Acquire a lock
                $routerSerial = $this->generateRouterSerial();
                $lockResult = $this->lockService->acquireLock($ipMasterId, $routerSerial, $this->testUserId);
                
                if (!$lockResult['success']) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => 'Failed to acquire lock: ' . $lockResult['message']
                    ];
                }
                
                $lockId = $lockResult['data']['id'];
                $this->createdLockIds[] = $lockId;
                
                // Verify IP is not available while locked
                if ($this->lockService->isIPAvailable($ipMasterId)) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => 'IP should not be available while locked'
                    ];
                }
                
                // Release the lock
                $releaseResult = $this->lockService->releaseLock($lockId);
                
                if (!$releaseResult['success']) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => 'Failed to release lock: ' . $releaseResult['message']
                    ];
                }
                
                // Verify IP is available again
                if (!$this->lockService->isIPAvailable($ipMasterId)) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => 'IP should be available after lock release'
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
    public function runAllTests(): array {
        $results = [];
        
        $results['lock_exclusivity'] = $this->testLockExclusivity();
        $results['second_lock_fails'] = $this->testSecondLockAttemptFails();
        $results['lock_expiry_handling'] = $this->testLockExpiryHandling();
        $results['non_expired_not_released'] = $this->testNonExpiredLocksNotReleased();
        $results['released_lock_available'] = $this->testReleasedLockMakesIPAvailable();
        
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
    $test = new LockExclusivityExpiryTest();
    $results = $test->runAllTests();
    exit(in_array(false, $results, true) ? 1 : 0);
}
