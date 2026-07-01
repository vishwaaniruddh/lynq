<?php
/**
 * Property Test: Update Timestamp Modification
 * **Feature: task-checklist, Property 10: Update timestamp modification**
 * **Validates: Requirements 5.4**
 * 
 * Property: *For any* task that is updated, the updated_at timestamp should be 
 * greater than or equal to the original updated_at value.
 */

require_once __DIR__ . '/PropertyTestBase.php';
require_once __DIR__ . '/../repositories/TaskRepository.php';

class TaskUpdateTimestampPropertyTest extends PropertyTestBase {
    private $taskRepository;
    private $testUserIds = [];
    private $createdTaskIds = [];
    
    public function __construct() {
        parent::__construct();
        $this->taskRepository = new TaskRepository();
    }
    
    /**
     * Set up test user
     */
    private function setupTestUser(): int {
        $username = 'test_task_ut_' . $this->generateRandomString(8);
        $email = "{$username}@test.com";
        
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
        // Delete created tasks
        if (!empty($this->createdTaskIds)) {
            $ids = implode(',', array_map('intval', $this->createdTaskIds));
            $sql = "DELETE FROM tasks WHERE id IN ($ids)";
            $this->db->query($sql);
            $this->createdTaskIds = [];
        }
        
        // Delete test users
        if (!empty($this->testUserIds)) {
            $ids = implode(',', array_map('intval', $this->testUserIds));
            $sql = "DELETE FROM users WHERE id IN ($ids)";
            $this->db->query($sql);
            $this->testUserIds = [];
        }
    }
    
    /**
     * Generate random non-empty title (1-100 chars)
     */
    private function generateRandomTitle(): string {
        $length = $this->generateRandomInt(1, 100);
        return $this->generateRandomString($length);
    }
    
    /**
     * Property Test: Update increases or maintains updated_at timestamp
     */
    public function testUpdateTimestampModification(): bool {
        return $this->runPropertyTest(
            'Update Timestamp Modification',
            function() {
                // Setup: Create test user
                $userId = $this->setupTestUser();
                
                // Create task
                try {
                    $taskId = $this->taskRepository->createTask([
                        'user_id' => $userId,
                        'title' => $this->generateRandomTitle(),
                        'description' => null
                    ]);
                    $this->createdTaskIds[] = $taskId;
                } catch (Exception $e) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => "Failed to create task: " . $e->getMessage(),
                        'data' => []
                    ];
                }
                
                // Get initial updated_at
                $initialTask = $this->taskRepository->findByIdAndUserId($taskId, $userId);
                $initialUpdatedAt = $initialTask['updated_at'];
                
                if ($initialUpdatedAt === null) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => "Initial updated_at should not be null",
                        'data' => ['task_id' => $taskId]
                    ];
                }
                
                // Small delay to ensure timestamp difference is detectable
                usleep(100000); // 100ms
                
                // Update task with new title
                try {
                    $this->taskRepository->updateTask($taskId, $userId, [
                        'title' => $this->generateRandomTitle(),
                        'description' => $this->generateRandomBool() ? $this->generateRandomString(50) : null
                    ]);
                } catch (Exception $e) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => "Failed to update task: " . $e->getMessage(),
                        'data' => ['task_id' => $taskId]
                    ];
                }
                
                // Get updated task
                $updatedTask = $this->taskRepository->findByIdAndUserId($taskId, $userId);
                $newUpdatedAt = $updatedTask['updated_at'];
                
                if ($newUpdatedAt === null) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => "Updated_at should not be null after update",
                        'data' => ['task_id' => $taskId]
                    ];
                }
                
                // Verify updated_at is >= original (Requirement 5.4)
                $initialTime = strtotime($initialUpdatedAt);
                $newTime = strtotime($newUpdatedAt);
                
                if ($newTime < $initialTime) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => "Updated_at should be >= original after update",
                        'data' => [
                            'initial_updated_at' => $initialUpdatedAt,
                            'new_updated_at' => $newUpdatedAt
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
     * Property Test: Multiple updates keep increasing timestamp
     */
    public function testMultipleUpdatesIncreaseTimestamp(): bool {
        return $this->runPropertyTest(
            'Multiple Updates Increase Timestamp',
            function() {
                // Setup: Create test user
                $userId = $this->setupTestUser();
                
                // Create task
                try {
                    $taskId = $this->taskRepository->createTask([
                        'user_id' => $userId,
                        'title' => $this->generateRandomTitle(),
                        'description' => null
                    ]);
                    $this->createdTaskIds[] = $taskId;
                } catch (Exception $e) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => "Failed to create task: " . $e->getMessage(),
                        'data' => []
                    ];
                }
                
                // Perform multiple updates (2-4)
                $updateCount = $this->generateRandomInt(2, 4);
                $previousUpdatedAt = null;
                
                for ($i = 0; $i < $updateCount; $i++) {
                    // Get current updated_at
                    $currentTask = $this->taskRepository->findByIdAndUserId($taskId, $userId);
                    $currentUpdatedAt = $currentTask['updated_at'];
                    
                    // Verify timestamp is >= previous (if not first iteration)
                    if ($previousUpdatedAt !== null) {
                        $prevTime = strtotime($previousUpdatedAt);
                        $currTime = strtotime($currentUpdatedAt);
                        
                        if ($currTime < $prevTime) {
                            $this->cleanupTestData();
                            return [
                                'success' => false,
                                'message' => "Timestamp decreased between updates",
                                'data' => [
                                    'iteration' => $i,
                                    'previous' => $previousUpdatedAt,
                                    'current' => $currentUpdatedAt
                                ]
                            ];
                        }
                    }
                    
                    $previousUpdatedAt = $currentUpdatedAt;
                    
                    // Small delay
                    usleep(100000); // 100ms
                    
                    // Update task
                    try {
                        $this->taskRepository->updateTask($taskId, $userId, [
                            'title' => $this->generateRandomTitle(),
                            'description' => null
                        ]);
                    } catch (Exception $e) {
                        $this->cleanupTestData();
                        return [
                            'success' => false,
                            'message' => "Failed to update task on iteration $i: " . $e->getMessage(),
                            'data' => ['task_id' => $taskId]
                        ];
                    }
                }
                
                // Final check
                $finalTask = $this->taskRepository->findByIdAndUserId($taskId, $userId);
                $finalUpdatedAt = $finalTask['updated_at'];
                
                $prevTime = strtotime($previousUpdatedAt);
                $finalTime = strtotime($finalUpdatedAt);
                
                if ($finalTime < $prevTime) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => "Final timestamp decreased",
                        'data' => [
                            'previous' => $previousUpdatedAt,
                            'final' => $finalUpdatedAt
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
        echo "=== Task Update Timestamp Property Tests ===\n\n";
        
        $allPassed = true;
        
        if (!$this->testUpdateTimestampModification()) {
            $allPassed = false;
        }
        
        if (!$this->testMultipleUpdatesIncreaseTimestamp()) {
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
