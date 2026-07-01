<?php
/**
 * Property Test: Revision List Sorting
 * **Feature: user-profile-enhancement, Property 8: Revision List Sorting**
 * **Validates: Requirements 8.3**
 * 
 * Property: *For any* user with multiple profile revisions, retrieving the revision history 
 * SHALL return revisions sorted by created_at timestamp in descending order (most recent first).
 */

require_once __DIR__ . '/PropertyTestBase.php';
require_once __DIR__ . '/../services/ProfileService.php';
require_once __DIR__ . '/../repositories/ProfileRevisionRepository.php';

class ProfileRevisionSortingPropertyTest extends PropertyTestBase {
    private $profileService;
    private $revisionRepository;
    private $testUserIds = [];
    
    public function __construct() {
        parent::__construct();
        $this->profileService = new ProfileService();
        $this->revisionRepository = new ProfileRevisionRepository();
    }
    
    /**
     * Set up test user
     */
    private function setupTestUser(): int {
        $username = 'test_rev_sort_' . $this->generateRandomString(8);
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
     * Property Test: Revisions are sorted by created_at DESC
     */
    public function testRevisionsSortedDescending(): bool {
        return $this->runPropertyTest(
            'Revisions are sorted by created_at descending',
            function() {
                // Setup: Create test user
                $userId = $this->setupTestUser();
                
                // Create multiple revisions with different data
                $numRevisions = $this->generateRandomInt(2, 5);
                
                for ($i = 0; $i < $numRevisions; $i++) {
                    $updateData = [
                        'first_name' => 'Name' . $i . '_' . $this->generateRandomString(5)
                    ];
                    $this->profileService->updateProfile($userId, $updateData);
                    
                    // Small delay to ensure different timestamps
                    usleep(10000); // 10ms
                }
                
                // Get revision history
                $result = $this->profileService->getRevisionHistory($userId);
                
                if (!$result['success']) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => "Failed to get revision history",
                        'data' => $result
                    ];
                }
                
                $revisions = $result['data'];
                
                // Verify we have the expected number of revisions
                if (count($revisions) !== $numRevisions) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => "Unexpected number of revisions",
                        'data' => [
                            'expected' => $numRevisions,
                            'actual' => count($revisions)
                        ]
                    ];
                }
                
                // Verify revisions are sorted by created_at DESC
                for ($i = 0; $i < count($revisions) - 1; $i++) {
                    $currentTimestamp = strtotime($revisions[$i]['created_at']);
                    $nextTimestamp = strtotime($revisions[$i + 1]['created_at']);
                    
                    if ($currentTimestamp < $nextTimestamp) {
                        $this->cleanupTestData();
                        return [
                            'success' => false,
                            'message' => "Revisions not sorted in descending order",
                            'data' => [
                                'position' => $i,
                                'current_timestamp' => $revisions[$i]['created_at'],
                                'next_timestamp' => $revisions[$i + 1]['created_at']
                            ]
                        ];
                    }
                }
                
                // Cleanup
                $this->cleanupTestData();
                
                return ['success' => true];
            }
        );
    }
    
    /**
     * Property Test: Most recent revision is first
     * Note: When timestamps are identical (same second), MySQL returns in insertion order
     * which may not be deterministic. This test uses 1-second delays to ensure distinct timestamps.
     */
    public function testMostRecentRevisionFirst(): bool {
        return $this->runPropertyTest(
            'Most recent revision appears first in list',
            function() {
                // Setup: Create test user
                $userId = $this->setupTestUser();
                
                // Create initial revision
                $this->profileService->updateProfile($userId, ['first_name' => 'First']);
                
                // Wait 1 second to ensure different timestamp (MySQL TIMESTAMP has second precision)
                sleep(1);
                
                // Create final revision with unique identifier
                $finalName = 'Final_' . $this->generateRandomString(8);
                $this->profileService->updateProfile($userId, ['first_name' => $finalName]);
                
                // Get revision history
                $result = $this->profileService->getRevisionHistory($userId);
                
                if (!$result['success']) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => "Failed to get revision history",
                        'data' => $result
                    ];
                }
                
                $revisions = $result['data'];
                
                // Verify the first revision in the list is the most recent one
                $firstRevision = $revisions[0];
                $newValues = $firstRevision['new_values'];
                
                if (!isset($newValues['first_name']) || $newValues['first_name'] !== $finalName) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => "Most recent revision is not first",
                        'data' => [
                            'expected_first_name' => $finalName,
                            'actual_first_name' => $newValues['first_name'] ?? null
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
        echo "=== Profile Revision Sorting Property Tests ===\n\n";
        
        $allPassed = true;
        
        if (!$this->testRevisionsSortedDescending()) {
            $allPassed = false;
        }
        
        if (!$this->testMostRecentRevisionFirst()) {
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
