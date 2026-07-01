<?php
/**
 * Property Test: User Profile Isolation
 * **Feature: user-profile-enhancement, Property 9: User Profile Isolation**
 * **Validates: Requirements 9.2**
 * 
 * Property: *For any* user attempting to access or modify another user's profile data, 
 * the system SHALL deny the request and return an authorization error.
 */

require_once __DIR__ . '/PropertyTestBase.php';
require_once __DIR__ . '/../services/ProfileService.php';

class ProfileUserIsolationPropertyTest extends PropertyTestBase {
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
        $username = 'test_isolation_' . $this->generateRandomString(8);
        $email = $username . '@test.com';
        
        $sql = "INSERT INTO users (username, email, password_hash, first_name, last_name, role_id, status, company_id, created_at) 
                VALUES (?, ?, ?, 'Test', 'User', 1, 1, 1, NOW())";
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
     * Property Test: User cannot access another user's profile
     */
    public function testCannotAccessOtherUserProfile(): bool {
        return $this->runPropertyTest(
            'User cannot access another user\'s profile',
            function() {
                // Setup: Create two test users
                $user1Id = $this->setupTestUser();
                $user2Id = $this->setupTestUser();
                
                // Verify user1 can access their own profile
                $canAccessOwn = $this->profileService->canAccessProfile($user1Id, $user1Id);
                
                if (!$canAccessOwn) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => "User cannot access their own profile",
                        'data' => [
                            'user_id' => $user1Id
                        ]
                    ];
                }
                
                // Verify user1 cannot access user2's profile
                $canAccessOther = $this->profileService->canAccessProfile($user1Id, $user2Id);
                
                if ($canAccessOther) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => "User can access another user's profile",
                        'data' => [
                            'requesting_user' => $user1Id,
                            'target_user' => $user2Id
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
     * Property Test: Access check is symmetric (neither user can access the other)
     */
    public function testAccessCheckSymmetric(): bool {
        return $this->runPropertyTest(
            'Access check is symmetric - neither user can access the other',
            function() {
                // Setup: Create two test users
                $user1Id = $this->setupTestUser();
                $user2Id = $this->setupTestUser();
                
                // Verify user1 cannot access user2
                $user1CanAccessUser2 = $this->profileService->canAccessProfile($user1Id, $user2Id);
                
                // Verify user2 cannot access user1
                $user2CanAccessUser1 = $this->profileService->canAccessProfile($user2Id, $user1Id);
                
                if ($user1CanAccessUser2 || $user2CanAccessUser1) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => "Cross-user access is allowed",
                        'data' => [
                            'user1_can_access_user2' => $user1CanAccessUser2,
                            'user2_can_access_user1' => $user2CanAccessUser1
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
     * Property Test: User can always access their own profile
     */
    public function testUserCanAccessOwnProfile(): bool {
        return $this->runPropertyTest(
            'User can always access their own profile',
            function() {
                // Setup: Create test user
                $userId = $this->setupTestUser();
                
                // Verify user can access their own profile
                $canAccess = $this->profileService->canAccessProfile($userId, $userId);
                
                if (!$canAccess) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => "User cannot access their own profile",
                        'data' => [
                            'user_id' => $userId
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
     * Run all property tests
     */
    public function runAllTests(): bool {
        echo "=== Profile User Isolation Property Tests ===\n\n";
        
        $allPassed = true;
        
        if (!$this->testCannotAccessOtherUserProfile()) {
            $allPassed = false;
        }
        
        if (!$this->testAccessCheckSymmetric()) {
            $allPassed = false;
        }
        
        if (!$this->testUserCanAccessOwnProfile()) {
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
