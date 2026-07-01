<?php
/**
 * Property Test: User Isolation
 * **Feature: task-checklist, Property 4: User isolation**
 * **Validates: Requirements 2.1, 6.1**
 * 
 * Property: *For any* two distinct users A and B, when user A creates tasks, 
 * user B's task list query should not include any of user A's tasks.
 */

require_once __DIR__ . '/PropertyTestBase.php';
require_once __DIR__ . '/../services/TaskService.php';

class TaskUserIsolationPropertyTest extends PropertyTestBase {
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
    private function setupTestUser(string $prefix = 'test_iso'): int {
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
     * Property Test: User A's tasks should not appear in User B's task list
     */
    public function testUserIsolation(): bool {
        return $this->runPropertyTest(
            'User A tasks should not appear in User B task list',
            function() {
                // Setup: Create two distinct test users
                $userA = $this->setupTestUser('test_iso_a');
                $userB = $this->setupTestUser('test_iso_b');
                
                // Generate random number of tasks for User A (1-5)
                $numTasks = $this->generateRandomInt(1, 5);
                $userATaskIds = [];
                
                for ($i = 0; $i < $numTasks; $i++) {
                    $title = $this->generateRandomTitle();
                    $result = $this->taskService->createTask($userA, $title, 'Description ' . $i);
                    
                    if (!$result['success']) {
                        $this->cleanupTestData();
                        return [
                            'success' => false,
                            'message' => "Failed to create task for User A",
                            'data' => $result
                        ];
                    }
                    
                    $userATaskIds[] = $result['data']['id'];
                    $this->createdTaskIds[] = $result['data']['id'];
                }
                
                // Get User B's task list
                $userBTasksResult = $this->taskService->getUserTasks($userB);
                
                if (!$userBTasksResult['success']) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => "Failed to get User B's tasks",
                        'data' => $userBTasksResult
                    ];
                }
                
                $userBTasks = $userBTasksResult['data'];
                
                // Extract task IDs from User B's list
                $userBTaskIds = array_map(function($task) {
                    return $task['id'];
                }, $userBTasks);
                
                // Verify none of User A's tasks appear in User B's list
                $intersection = array_intersect($userATaskIds, $userBTaskIds);
                
                if (!empty($intersection)) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => "User A's tasks appeared in User B's task list",
                        'data' => [
                            'user_a_task_ids' => $userATaskIds,
                            'user_b_task_ids' => $userBTaskIds,
                            'leaked_task_ids' => array_values($intersection)
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
     * Property Test: User A's tasks should only appear in User A's task list
     */
    public function testUserTasksOnlyInOwnList(): bool {
        return $this->runPropertyTest(
            'User A tasks should appear in User A task list',
            function() {
                // Setup: Create test user
                $userA = $this->setupTestUser('test_own');
                
                // Generate random number of tasks (1-5)
                $numTasks = $this->generateRandomInt(1, 5);
                $createdTaskIds = [];
                
                for ($i = 0; $i < $numTasks; $i++) {
                    $title = $this->generateRandomTitle();
                    $result = $this->taskService->createTask($userA, $title, 'Description ' . $i);
                    
                    if (!$result['success']) {
                        $this->cleanupTestData();
                        return [
                            'success' => false,
                            'message' => "Failed to create task",
                            'data' => $result
                        ];
                    }
                    
                    $createdTaskIds[] = $result['data']['id'];
                    $this->createdTaskIds[] = $result['data']['id'];
                }
                
                // Get User A's task list
                $userATasksResult = $this->taskService->getUserTasks($userA);
                
                if (!$userATasksResult['success']) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => "Failed to get User A's tasks",
                        'data' => $userATasksResult
                    ];
                }
                
                $userATasks = $userATasksResult['data'];
                
                // Extract task IDs from User A's list
                $userATaskIds = array_map(function($task) {
                    return $task['id'];
                }, $userATasks);
                
                // Verify all created tasks appear in User A's list
                foreach ($createdTaskIds as $taskId) {
                    if (!in_array($taskId, $userATaskIds)) {
                        $this->cleanupTestData();
                        return [
                            'success' => false,
                            'message' => "Created task not found in user's task list",
                            'data' => [
                                'missing_task_id' => $taskId,
                                'user_task_ids' => $userATaskIds
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
     * Run all property tests
     */
    public function runAllTests(): bool {
        echo "=== Task User Isolation Property Tests ===\n\n";
        
        $allPassed = true;
        
        if (!$this->testUserIsolation()) {
            $allPassed = false;
        }
        
        if (!$this->testUserTasksOnlyInOwnList()) {
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
