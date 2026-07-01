<?php
/**
 * Property Test: Task Deletion Permanence
 * **Feature: task-checklist, Property 9: Task deletion permanence**
 * **Validates: Requirements 4.1, 4.3**
 * 
 * Property: *For any* task that is deleted, subsequent queries for that task 
 * should return not found.
 */

require_once __DIR__ . '/PropertyTestBase.php';
require_once __DIR__ . '/../repositories/TaskRepository.php';

class TaskDeletionPermanencePropertyTest extends PropertyTestBase {
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
        $username = 'test_task_dp_' . $this->generateRandomString(8);
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
        // Delete created tasks (if any remain)
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
     * Property Test: Deleted task cannot be retrieved
     */
    public function testDeletedTaskNotFound(): bool {
        return $this->runPropertyTest(
            'Deleted Task Not Found',
            function() {
                // Setup: Create test user
                $userId = $this->setupTestUser();
                
                // Create task
                try {
                    $taskId = $this->taskRepository->createTask([
                        'user_id' => $userId,
                        'title' => $this->generateRandomTitle(),
                        'description' => $this->generateRandomBool() ? $this->generateRandomString(50) : null
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
                
                // Verify task exists before deletion
                $taskBeforeDelete = $this->taskRepository->findByIdAndUserId($taskId, $userId);
                if ($taskBeforeDelete === null) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => "Task should exist before deletion",
                        'data' => ['task_id' => $taskId]
                    ];
                }
                
                // Delete task
                try {
                    $deleted = $this->taskRepository->deleteTask($taskId, $userId);
                    // Remove from tracking since it's deleted
                    $this->createdTaskIds = array_diff($this->createdTaskIds, [$taskId]);
                    
                    if (!$deleted) {
                        $this->cleanupTestData();
                        return [
                            'success' => false,
                            'message' => "Delete operation returned false",
                            'data' => ['task_id' => $taskId]
                        ];
                    }
                } catch (Exception $e) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => "Failed to delete task: " . $e->getMessage(),
                        'data' => ['task_id' => $taskId]
                    ];
                }
                
                // Verify task no longer exists (Requirement 4.1 - permanent removal)
                $taskAfterDelete = $this->taskRepository->findByIdAndUserId($taskId, $userId);
                if ($taskAfterDelete !== null) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => "Task should not exist after deletion",
                        'data' => [
                            'task_id' => $taskId,
                            'found_task' => $taskAfterDelete
                        ]
                    ];
                }
                
                // Verify task is not in user's task list
                $userTasks = $this->taskRepository->findByUserId($userId);
                foreach ($userTasks as $task) {
                    if ((int)$task['id'] === $taskId) {
                        $this->cleanupTestData();
                        return [
                            'success' => false,
                            'message' => "Deleted task should not appear in user's task list",
                            'data' => ['task_id' => $taskId]
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
     * Property Test: Multiple tasks - deleting one doesn't affect others
     */
    public function testDeleteOneTaskPreservesOthers(): bool {
        return $this->runPropertyTest(
            'Delete One Task Preserves Others',
            function() {
                // Setup: Create test user
                $userId = $this->setupTestUser();
                
                // Create multiple tasks (2-5)
                $taskCount = $this->generateRandomInt(2, 5);
                $taskIds = [];
                
                for ($i = 0; $i < $taskCount; $i++) {
                    try {
                        $taskId = $this->taskRepository->createTask([
                            'user_id' => $userId,
                            'title' => $this->generateRandomTitle(),
                            'description' => null
                        ]);
                        $taskIds[] = $taskId;
                        $this->createdTaskIds[] = $taskId;
                    } catch (Exception $e) {
                        $this->cleanupTestData();
                        return [
                            'success' => false,
                            'message' => "Failed to create task: " . $e->getMessage(),
                            'data' => []
                        ];
                    }
                }
                
                // Pick a random task to delete
                $deleteIndex = array_rand($taskIds);
                $taskToDelete = $taskIds[$deleteIndex];
                $remainingTaskIds = array_values(array_diff($taskIds, [$taskToDelete]));
                
                // Delete the selected task
                try {
                    $this->taskRepository->deleteTask($taskToDelete, $userId);
                    $this->createdTaskIds = array_diff($this->createdTaskIds, [$taskToDelete]);
                } catch (Exception $e) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => "Failed to delete task: " . $e->getMessage(),
                        'data' => ['task_id' => $taskToDelete]
                    ];
                }
                
                // Verify remaining tasks still exist
                foreach ($remainingTaskIds as $remainingId) {
                    $task = $this->taskRepository->findByIdAndUserId($remainingId, $userId);
                    if ($task === null) {
                        $this->cleanupTestData();
                        return [
                            'success' => false,
                            'message' => "Remaining task should still exist after deleting another",
                            'data' => [
                                'deleted_task_id' => $taskToDelete,
                                'missing_task_id' => $remainingId
                            ]
                        ];
                    }
                }
                
                // Verify user's task list has correct count
                $userTasks = $this->taskRepository->findByUserId($userId);
                if (count($userTasks) !== count($remainingTaskIds)) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => "Task count mismatch after deletion",
                        'data' => [
                            'expected' => count($remainingTaskIds),
                            'actual' => count($userTasks)
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
        echo "=== Task Deletion Permanence Property Tests ===\n\n";
        
        $allPassed = true;
        
        if (!$this->testDeletedTaskNotFound()) {
            $allPassed = false;
        }
        
        if (!$this->testDeleteOneTaskPreservesOthers()) {
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
