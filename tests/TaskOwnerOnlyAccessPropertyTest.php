<?php
/**
 * Property Test: Owner-Only Access
 * **Feature: task-checklist, Property 8: Owner-only access**
 * **Validates: Requirements 3.3, 4.2, 5.3, 6.2**
 * 
 * Property: *For any* task owned by user A, when user B attempts to read, update, 
 * delete, or toggle completion, the operation should fail with an authorization error.
 */

require_once __DIR__ . '/PropertyTestBase.php';
require_once __DIR__ . '/../services/TaskService.php';

class TaskOwnerOnlyAccessPropertyTest extends PropertyTestBase {
    private $taskService;
    private $testUserIds = [];
    private $createdTaskIds = [];
    
    public function __construct() {
        parent::__construct();
        $this->taskService = new TaskService();
    }
    
    /**
     * Set up test user
     */
    private function setupTestUser(string $prefix = 'test_owner'): int {
        $username = $prefix . '_' . $this->generateRandomString(8);
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
     * Generate random non-empty title
     */
    private function generateRandomTitle(): string {
        $length = $this->generateRandomInt(5, 50);
        return 'Task_' . $this->generateRandomString($length);
    }
    
    /**
     * Property Test: Non-owner cannot read task
     */
    public function testNonOwnerCannotRead(): bool {
        return $this->runPropertyTest(
            'Non-owner cannot read task (getTask)',
            function() {
                // Setup: Create two distinct test users
                $ownerUser = $this->setupTestUser('test_owner');
                $otherUser = $this->setupTestUser('test_other');
                
                // Create a task owned by ownerUser
                $title = $this->generateRandomTitle();
                $createResult = $this->taskService->createTask($ownerUser, $title, 'Description');
                
                if (!$createResult['success']) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => "Failed to create task",
                        'data' => $createResult
                    ];
                }
                
                $taskId = $createResult['data']['id'];
                $this->createdTaskIds[] = $taskId;
                
                // Attempt to read task as otherUser
                $readResult = $this->taskService->getTask($taskId, $otherUser);
                
                // Verify read was denied
                if ($readResult['success'] === true) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => "Non-owner should not be able to read task",
                        'data' => [
                            'task_id' => $taskId,
                            'owner_id' => $ownerUser,
                            'other_user_id' => $otherUser
                        ]
                    ];
                }
                
                // Verify error code is NOT_FOUND (which indicates access denied)
                if ($readResult['code'] !== 'NOT_FOUND') {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => "Expected NOT_FOUND code for unauthorized access",
                        'data' => [
                            'expected_code' => 'NOT_FOUND',
                            'actual_code' => $readResult['code'] ?? 'none'
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
     * Property Test: Non-owner cannot update task
     */
    public function testNonOwnerCannotUpdate(): bool {
        return $this->runPropertyTest(
            'Non-owner cannot update task',
            function() {
                // Setup: Create two distinct test users
                $ownerUser = $this->setupTestUser('test_owner');
                $otherUser = $this->setupTestUser('test_other');
                
                // Create a task owned by ownerUser
                $originalTitle = $this->generateRandomTitle();
                $createResult = $this->taskService->createTask($ownerUser, $originalTitle, 'Original description');
                
                if (!$createResult['success']) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => "Failed to create task",
                        'data' => $createResult
                    ];
                }
                
                $taskId = $createResult['data']['id'];
                $this->createdTaskIds[] = $taskId;
                
                // Attempt to update task as otherUser
                $newTitle = $this->generateRandomTitle();
                $updateResult = $this->taskService->updateTask($taskId, $otherUser, $newTitle, 'New description');
                
                // Verify update was denied
                if ($updateResult['success'] === true) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => "Non-owner should not be able to update task",
                        'data' => [
                            'task_id' => $taskId,
                            'owner_id' => $ownerUser,
                            'other_user_id' => $otherUser
                        ]
                    ];
                }
                
                // Verify original task is unchanged
                $taskResult = $this->taskService->getTask($taskId, $ownerUser);
                if ($taskResult['data']['title'] !== $originalTitle) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => "Task title should be unchanged after denied update",
                        'data' => [
                            'expected_title' => $originalTitle,
                            'actual_title' => $taskResult['data']['title']
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
     * Property Test: Non-owner cannot delete task
     */
    public function testNonOwnerCannotDelete(): bool {
        return $this->runPropertyTest(
            'Non-owner cannot delete task',
            function() {
                // Setup: Create two distinct test users
                $ownerUser = $this->setupTestUser('test_owner');
                $otherUser = $this->setupTestUser('test_other');
                
                // Create a task owned by ownerUser
                $title = $this->generateRandomTitle();
                $createResult = $this->taskService->createTask($ownerUser, $title, 'Description');
                
                if (!$createResult['success']) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => "Failed to create task",
                        'data' => $createResult
                    ];
                }
                
                $taskId = $createResult['data']['id'];
                $this->createdTaskIds[] = $taskId;
                
                // Attempt to delete task as otherUser
                $deleteResult = $this->taskService->deleteTask($taskId, $otherUser);
                
                // Verify delete was denied
                if ($deleteResult['success'] === true) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => "Non-owner should not be able to delete task",
                        'data' => [
                            'task_id' => $taskId,
                            'owner_id' => $ownerUser,
                            'other_user_id' => $otherUser
                        ]
                    ];
                }
                
                // Verify task still exists for owner
                $taskResult = $this->taskService->getTask($taskId, $ownerUser);
                if (!$taskResult['success']) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => "Task should still exist after denied delete",
                        'data' => [
                            'task_id' => $taskId
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
     * Property Test: Non-owner cannot toggle task completion
     */
    public function testNonOwnerCannotToggle(): bool {
        return $this->runPropertyTest(
            'Non-owner cannot toggle task completion',
            function() {
                // Setup: Create two distinct test users
                $ownerUser = $this->setupTestUser('test_owner');
                $otherUser = $this->setupTestUser('test_other');
                
                // Create a task owned by ownerUser
                $title = $this->generateRandomTitle();
                $createResult = $this->taskService->createTask($ownerUser, $title, 'Description');
                
                if (!$createResult['success']) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => "Failed to create task",
                        'data' => $createResult
                    ];
                }
                
                $taskId = $createResult['data']['id'];
                $this->createdTaskIds[] = $taskId;
                $originalStatus = (int)$createResult['data']['is_completed'];
                
                // Attempt to toggle task as otherUser
                $toggleResult = $this->taskService->toggleTaskCompletion($taskId, $otherUser);
                
                // Verify toggle was denied
                if ($toggleResult['success'] === true) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => "Non-owner should not be able to toggle task completion",
                        'data' => [
                            'task_id' => $taskId,
                            'owner_id' => $ownerUser,
                            'other_user_id' => $otherUser
                        ]
                    ];
                }
                
                // Verify task completion status is unchanged
                $taskResult = $this->taskService->getTask($taskId, $ownerUser);
                $currentStatus = (int)$taskResult['data']['is_completed'];
                
                if ($currentStatus !== $originalStatus) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => "Task completion status should be unchanged after denied toggle",
                        'data' => [
                            'expected_status' => $originalStatus,
                            'actual_status' => $currentStatus
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
        echo "=== Task Owner-Only Access Property Tests ===\n\n";
        
        $allPassed = true;
        
        if (!$this->testNonOwnerCannotRead()) {
            $allPassed = false;
        }
        
        if (!$this->testNonOwnerCannotUpdate()) {
            $allPassed = false;
        }
        
        if (!$this->testNonOwnerCannotDelete()) {
            $allPassed = false;
        }
        
        if (!$this->testNonOwnerCannotToggle()) {
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
