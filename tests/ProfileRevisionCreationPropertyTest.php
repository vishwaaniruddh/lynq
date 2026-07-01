<?php
/**
 * Property Test: Revision Creation on Update
 * **Feature: user-profile-enhancement, Property 7: Revision Creation on Update**
 * **Validates: Requirements 8.1, 8.4**
 * 
 * Property: *For any* profile update that changes at least one field, the system SHALL 
 * create a revision record containing the changed field names, old values, new values, 
 * and timestamp.
 */

require_once __DIR__ . '/PropertyTestBase.php';
require_once __DIR__ . '/../services/ProfileService.php';
require_once __DIR__ . '/../repositories/ProfileRevisionRepository.php';

class ProfileRevisionCreationPropertyTest extends PropertyTestBase {
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
        $username = 'test_revision_' . $this->generateRandomString(8);
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
     * Generate random profile update data
     */
    private function generateRandomProfileUpdate(): array {
        $fields = ['first_name', 'last_name', 'contact_number', 'address', 'bio'];
        $numFields = $this->generateRandomInt(1, count($fields));
        
        // Shuffle and pick random fields
        shuffle($fields);
        $selectedFields = array_slice($fields, 0, $numFields);
        
        $data = [];
        foreach ($selectedFields as $field) {
            switch ($field) {
                case 'first_name':
                case 'last_name':
                    $data[$field] = $this->generateRandomString($this->generateRandomInt(1, 50));
                    break;
                case 'contact_number':
                    $data[$field] = '+' . $this->generateRandomInt(1000000, 9999999999);
                    break;
                case 'address':
                    $data[$field] = $this->generateRandomString($this->generateRandomInt(10, 100));
                    break;
                case 'bio':
                    $data[$field] = $this->generateRandomString($this->generateRandomInt(10, 200));
                    break;
            }
        }
        
        return $data;
    }
    
    /**
     * Property Test: Revision is created on profile update
     */
    public function testRevisionCreatedOnUpdate(): bool {
        return $this->runPropertyTest(
            'Revision is created when profile is updated',
            function() {
                // Setup: Create test user
                $userId = $this->setupTestUser();
                
                // Get initial revision count
                $initialCount = $this->revisionRepository->countByUserId($userId);
                
                // Generate random profile update
                $updateData = $this->generateRandomProfileUpdate();
                
                // Update profile
                $updateResult = $this->profileService->updateProfile($userId, $updateData);
                
                if (!$updateResult['success']) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => "Failed to update profile: " . ($updateResult['message'] ?? 'Unknown error'),
                        'data' => $updateData
                    ];
                }
                
                // Get new revision count
                $newCount = $this->revisionRepository->countByUserId($userId);
                
                // Verify revision was created
                if ($newCount !== $initialCount + 1) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => "Revision was not created",
                        'data' => [
                            'initial_count' => $initialCount,
                            'new_count' => $newCount,
                            'expected_count' => $initialCount + 1
                        ]
                    ];
                }
                
                // Get the latest revision
                $revisions = $this->revisionRepository->findByUserId($userId);
                $latestRevision = $revisions[0];
                
                // Verify revision contains changed fields
                $changedFields = $latestRevision['changed_fields'];
                $expectedFields = array_keys($updateData);
                
                sort($changedFields);
                sort($expectedFields);
                
                if ($changedFields !== $expectedFields) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => "Revision changed_fields mismatch",
                        'data' => [
                            'expected_fields' => $expectedFields,
                            'actual_fields' => $changedFields
                        ]
                    ];
                }
                
                // Verify revision contains new values
                $newValues = $latestRevision['new_values'];
                foreach ($updateData as $field => $value) {
                    if (!isset($newValues[$field]) || $newValues[$field] !== $value) {
                        $this->cleanupTestData();
                        return [
                            'success' => false,
                            'message' => "Revision new_values mismatch for field: $field",
                            'data' => [
                                'field' => $field,
                                'expected' => $value,
                                'actual' => $newValues[$field] ?? null
                            ]
                        ];
                    }
                }
                
                // Verify revision has timestamp
                if (empty($latestRevision['created_at'])) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => "Revision missing timestamp",
                        'data' => $latestRevision
                    ];
                }
                
                // Cleanup
                $this->cleanupTestData();
                
                return ['success' => true];
            }
        );
    }
    
    /**
     * Property Test: No revision created when no changes
     */
    public function testNoRevisionWhenNoChanges(): bool {
        return $this->runPropertyTest(
            'No revision created when profile data unchanged',
            function() {
                // Setup: Create test user
                $userId = $this->setupTestUser();
                
                // Set initial profile data
                $initialData = [
                    'first_name' => 'TestFirst',
                    'last_name' => 'TestLast'
                ];
                $this->profileService->updateProfile($userId, $initialData);
                
                // Get revision count after initial update
                $countAfterInitial = $this->revisionRepository->countByUserId($userId);
                
                // Update with same data (no changes)
                $this->profileService->updateProfile($userId, $initialData);
                
                // Get revision count after "no change" update
                $countAfterNoChange = $this->revisionRepository->countByUserId($userId);
                
                // Verify no new revision was created
                if ($countAfterNoChange !== $countAfterInitial) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => "Revision was created when no changes were made",
                        'data' => [
                            'count_after_initial' => $countAfterInitial,
                            'count_after_no_change' => $countAfterNoChange
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
        echo "=== Profile Revision Creation Property Tests ===\n\n";
        
        $allPassed = true;
        
        if (!$this->testRevisionCreatedOnUpdate()) {
            $allPassed = false;
        }
        
        if (!$this->testNoRevisionWhenNoChanges()) {
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
