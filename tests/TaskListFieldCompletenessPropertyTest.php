<?php
/**
 * Property Test: Task List Field Completeness
 * **Feature: task-checklist, Property 5: Task list field completeness**
 * **Validates: Requirements 2.2**
 * 
 * Property: *For any* task returned in a list query, the response should include 
 * id, title, is_completed, and created_at fields.
 */

require_once __DIR__ . '/PropertyTestBase.php';
require_once __DIR__ . '/../repositories/TaskRepository.php';

class TaskListFieldCompletenessPropertyTest extends PropertyTestBase {
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
        $username = 'test_task_fc_' . $this->generateRandomString(8);
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
     * Generate random non-empty title (1-255 chars)
     */
    private function generateRandomTitle(): string {
        $length = $this->generateRandomInt(1, 100);
        return $this->generateRandomString($length);
    }
    
    /**
     * Property Test: Task list contains required fields
     */
    public function testTaskListFieldCompleteness(): bool {
        return $this->runPropertyTest(
            'Task List Field Completeness',
            function() {
                // Setup: Create test user
                $userId = $this->setupTestUser();
                
                // Create random number of tasks (1-5)
                $taskCount = $this->generateRandomInt(1, 5);
                
                for ($i = 0; $i < $taskCount; $i++) {
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
                }
                
                // Get task list
                $tasks = $this->taskRepository->findByUserId($userId);
                
                // Verify we got the expected number of tasks
                if (count($tasks) !== $taskCount) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => "Task count mismatch",
                        'data' => [
                            'expected' => $taskCount,
                            'actual' => count($tasks)
                        ]
                    ];
                }
                
                // Required fields per Requirement 2.2
                $requiredFields = ['id', 'title', 'is_completed', 'created_at'];
                
                // Check each task has required fields
                foreach ($tasks as $index => $task) {
                    foreach ($requiredFields as $field) {
                        if (!array_key_exists($field, $task)) {
                            $this->cleanupTestData();
                            return [
                                'success' => false,
                                'message' => "Missing required field in task list",
                                'data' => [
                                    'task_index' => $index,
                                    'missing_field' => $field,
                                    'available_fields' => array_keys($task)
                                ]
                            ];
                        }
                    }
                    
                    // Verify field values are not null for required fields
                    if ($task['id'] === null) {
                        $this->cleanupTestData();
                        return [
                            'success' => false,
                            'message' => "Task id is null",
                            'data' => ['task_index' => $index]
                        ];
                    }
                    
                    if ($task['title'] === null) {
                        $this->cleanupTestData();
                        return [
                            'success' => false,
                            'message' => "Task title is null",
                            'data' => ['task_index' => $index]
                        ];
                    }
                    
                    if ($task['created_at'] === null) {
                        $this->cleanupTestData();
                        return [
                            'success' => false,
                            'message' => "Task created_at is null",
                            'data' => ['task_index' => $index]
                        ];
                    }
                    
                    // is_completed should be 0 or 1
                    if (!in_array($task['is_completed'], [0, 1, '0', '1'], true)) {
                        $this->cleanupTestData();
                        return [
                            'success' => false,
                            'message' => "Task is_completed has invalid value",
                            'data' => [
                                'task_index' => $index,
                                'is_completed' => $task['is_completed']
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
        echo "=== Task List Field Completeness Property Tests ===\n\n";
        
        $allPassed = true;
        
        if (!$this->testTaskListFieldCompleteness()) {
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
