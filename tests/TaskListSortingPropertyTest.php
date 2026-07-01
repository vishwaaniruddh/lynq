<?php
/**
 * Property Test: Task List Sorting
 * **Feature: task-checklist, Property 6: Task list sorting**
 * **Validates: Requirements 2.4**
 * 
 * Property: *For any* user with multiple tasks, the task list should be 
 * ordered by created_at in descending order (newest first).
 */

require_once __DIR__ . '/PropertyTestBase.php';
require_once __DIR__ . '/../repositories/TaskRepository.php';

class TaskListSortingPropertyTest extends PropertyTestBase {
    private $taskRepository;
    private $testUserId;
    private $createdTaskIds = [];
    
    public function __construct() {
        parent::__construct();
        $this->taskRepository = new TaskRepository();
    }
    
    /**
     * Set up test user
     */
    private function setupTestUser(): int {
        $username = 'test_task_sort_' . $this->generateRandomString(8);
        $email = "{$username}@test.com";
        
        $sql = "INSERT INTO users (username, email, password_hash, first_name, last_name, role_id, status, company_id, created_at) 
                VALUES (?, ?, ?, 'Test', 'User', 1, 1, 1, NOW())";
        $stmt = $this->executeQuery($sql, [$username, $email, password_hash('test123', PASSWORD_DEFAULT)], 'sss');
        $userId = $this->db->insert_id;
        $stmt->close();
        
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
        
        // Delete test user
        if ($this->testUserId) {
            $sql = "DELETE FROM users WHERE id = ?";
            $stmt = $this->executeQuery($sql, [$this->testUserId], 'i');
            $stmt->close();
            $this->testUserId = null;
        }
    }

    
    /**
     * Create a task with a specific created_at timestamp
     */
    private function createTaskWithTimestamp(int $userId, string $title, string $createdAt): int {
        $sql = "INSERT INTO tasks (user_id, title, description, is_completed, completed_at, created_at, updated_at) 
                VALUES (?, ?, NULL, 0, NULL, ?, ?)";
        $stmt = $this->executeQuery($sql, [$userId, $title, $createdAt, $createdAt], 'isss');
        $taskId = $this->db->insert_id;
        $stmt->close();
        
        $this->createdTaskIds[] = $taskId;
        return $taskId;
    }
    
    /**
     * Property Test: Tasks are sorted by created_at DESC (newest first)
     */
    public function testTaskListSorting(): bool {
        return $this->runPropertyTest(
            'Task List Sorting - created_at DESC (newest first)',
            function() {
                // Setup: Create test user
                $this->testUserId = $this->setupTestUser();
                
                // Generate random number of tasks (2-10)
                $taskCount = $this->generateRandomInt(2, 10);
                
                // Create tasks with random timestamps
                $baseTime = strtotime('2025-01-01 00:00:00');
                for ($i = 0; $i < $taskCount; $i++) {
                    // Generate random timestamp within a year
                    $randomOffset = $this->generateRandomInt(0, 365 * 24 * 60 * 60);
                    $timestamp = date('Y-m-d H:i:s', $baseTime + $randomOffset);
                    
                    $this->createTaskWithTimestamp(
                        $this->testUserId,
                        'Task ' . ($i + 1),
                        $timestamp
                    );
                }
                
                // Retrieve tasks using repository
                $tasks = $this->taskRepository->findByUserId($this->testUserId);
                
                // Verify: Tasks should be sorted by created_at DESC (newest first)
                $previousTimestamp = null;
                foreach ($tasks as $index => $task) {
                    $currentTimestamp = strtotime($task['created_at']);
                    
                    if ($previousTimestamp !== null) {
                        if ($currentTimestamp > $previousTimestamp) {
                            $this->cleanupTestData();
                            return [
                                'success' => false,
                                'message' => "Tasks not sorted correctly: task at index $index has timestamp " . 
                                           $task['created_at'] . " which is after previous timestamp",
                                'data' => [
                                    'task_count' => $taskCount,
                                    'failed_at_index' => $index,
                                    'current_timestamp' => $task['created_at'],
                                    'previous_timestamp' => date('Y-m-d H:i:s', $previousTimestamp)
                                ]
                            ];
                        }
                    }
                    
                    $previousTimestamp = $currentTimestamp;
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
        echo "=== Task List Sorting Property Tests ===\n\n";
        
        $allPassed = true;
        
        if (!$this->testTaskListSorting()) {
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
