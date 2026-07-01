<?php
/**
 * Property Test: Update Timestamp Recording
 * **Feature: user-profile-enhancement, Property 10: Update Timestamp Recording**
 * **Validates: Requirements 9.3**
 * 
 * Property: *For any* profile update, the system SHALL update the updated_at timestamp 
 * to reflect the time of the change.
 */

require_once __DIR__ . '/PropertyTestBase.php';
require_once __DIR__ . '/../services/ProfileService.php';

class ProfileUpdateTimestampPropertyTest extends PropertyTestBase {
    private $profileService;
    private $testUserIds = [];
    
    public function __construct() {
        parent::__construct();
        $this->profileService = new ProfileService();
    }
    
    /**
     * Set up test user
     */
    private function setupTestUser(): int {
        $username = 'test_timestamp_' . $this->generateRandomString(8);
        $email = $username . '@test.com';
        
        $sql = "INSERT INTO users (username, email, password_hash, first_name, last_name, role_id, status, company_id, created_at) 
                VALUES (?, ?, ?, 'Initial', 'User', 1, 1, 1, NOW())";
        $stmt = $this->executeQuery($sql, [$username, $email, password_hash('test123', PASSWORD_DEFAULT)], 'sss');
        $userId = $this->db->insert_id;
        $stmt->close();
        
        $this->testUserIds[] = $userId;
        return $userId;
    }
    
    /**
     * Clean up test data
     */
    protected function cleanupTestData(): void {
        if (!empty($this->testUserIds)) {
            $ids = implode(',', array_map('intval', $this->testUserIds));
            // Revisions will be deleted by CASCADE
            $sql = "DELETE FROM users WHERE id IN ($ids)";
            $this->db->query($sql);
            $this->testUserIds = [];
        }
    }
    
    /**
     * Property Test: Updated_at timestamp is set on profile update
     */
    public function testUpdatedAtTimestampSet(): bool {
        return $this->runPropertyTest(
            'Updated_at timestamp is set on profile update',
            function() {
                // Setup: Create test user
                $userId = $this->setupTestUser();
                
                // Get initial profile (updated_at should be null or old)
                $initialProfile = $this->profileService->getProfile($userId);
                
                if (!$initialProfile['success']) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => "Failed to get initial profile",
                        'data' => $initialProfile
                    ];
                }
                
                $initialUpdatedAt = $initialProfile['data']['updated_at'];
                
                // Record time before update
                $beforeUpdate = time();
                
                // Update profile
                $updateData = [
                    'first_name' => 'Updated_' . $this->generateRandomString(5)
                ];
                $updateResult = $this->profileService->updateProfile($userId, $updateData);
                
                // Record time after update
                $afterUpdate = time();
                
                if (!$updateResult['success']) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => "Failed to update profile",
                        'data' => $updateResult
                    ];
                }
                
                // Get updated profile
                $updatedProfile = $this->profileService->getProfile($userId);
                
                if (!$updatedProfile['success']) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => "Failed to get updated profile",
                        'data' => $updatedProfile
                    ];
                }
                
                $newUpdatedAt = $updatedProfile['data']['updated_at'];
                
                // Verify updated_at is set
                if (empty($newUpdatedAt)) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => "Updated_at timestamp not set after update",
                        'data' => [
                            'initial_updated_at' => $initialUpdatedAt,
                            'new_updated_at' => $newUpdatedAt
                        ]
                    ];
                }
                
                // Verify updated_at is within the expected time range
                $updatedAtTimestamp = strtotime($newUpdatedAt);
                
                if ($updatedAtTimestamp < $beforeUpdate || $updatedAtTimestamp > $afterUpdate + 1) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => "Updated_at timestamp not within expected range",
                        'data' => [
                            'updated_at' => $newUpdatedAt,
                            'updated_at_timestamp' => $updatedAtTimestamp,
                            'before_update' => $beforeUpdate,
                            'after_update' => $afterUpdate
                        ]
                    ];
                }
                
                // Cleanup
                $this->cleanupTestData();
                
                return ['success' => true];
            }
        );
    }
    
    /**
     * Property Test: Updated_at changes on each update
     */
    public function testUpdatedAtChangesOnEachUpdate(): bool {
        return $this->runPropertyTest(
            'Updated_at changes on each profile update',
            function() {
                // Setup: Create test user
                $userId = $this->setupTestUser();
                
                // First update
                $this->profileService->updateProfile($userId, ['first_name' => 'First']);
                $firstProfile = $this->profileService->getProfile($userId);
                $firstUpdatedAt = $firstProfile['data']['updated_at'];
                
                // Wait to ensure different timestamp
                sleep(1);
                
                // Second update
                $this->profileService->updateProfile($userId, ['first_name' => 'Second']);
                $secondProfile = $this->profileService->getProfile($userId);
                $secondUpdatedAt = $secondProfile['data']['updated_at'];
                
                // Verify timestamps are different
                if ($firstUpdatedAt === $secondUpdatedAt) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => "Updated_at did not change between updates",
                        'data' => [
                            'first_updated_at' => $firstUpdatedAt,
                            'second_updated_at' => $secondUpdatedAt
                        ]
                    ];
                }
                
                // Verify second timestamp is later
                if (strtotime($secondUpdatedAt) <= strtotime($firstUpdatedAt)) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => "Second updated_at is not later than first",
                        'data' => [
                            'first_updated_at' => $firstUpdatedAt,
                            'second_updated_at' => $secondUpdatedAt
                        ]
                    ];
                }
                
                // Cleanup
                $this->cleanupTestData();
                
                return ['success' => true];
            },
            10 // Reduced iterations due to 1-second delays
        );
    }
    
    /**
     * Run all property tests
     */
    public function runAllTests(): bool {
        echo "=== Profile Update Timestamp Property Tests ===\n\n";
        
        $allPassed = true;
        
        if (!$this->testUpdatedAtTimestampSet()) {
            $allPassed = false;
        }
        
        if (!$this->testUpdatedAtChangesOnEachUpdate()) {
            $allPassed = false;
        }
        
        echo "\n";
        if ($allPassed) {
            echo "All property tests PASSED!\n";
        } else {
            echo "Some property tests FAILED!\n";
        }
        
        return $allPassed;
    }
}
