<?php
/**
 * Property Test: Task Data Round-Trip
 * **Feature: task-checklist, Property 1: Task data round-trip**
 * **Validates: Requirements 1.1, 1.4, 5.2**
 * 
 * Property: *For any* valid task with a non-empty title and optional description, 
 * creating the task and then retrieving it should return the same title and description values.
 */

require_once __DIR__ . '/PropertyTestBase.php';
require_once __DIR__ . '/../repositories/TaskRepository.php';

class TaskDataRoundTripPropertyTest extends PropertyTestBase {
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
        $username = 'test_task_rt_' . $this->generateRandomString(8);
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
        $length = $this->generateRandomInt(1, 255);
        return $this->generateRandomString($length);
    }
    
    /**
     * Generate random description (0-1000 chars for testing, can be null)
     */
    private function generateRandomDescription(): ?string {
        // 30% chance of null description
        if ($this->generateRandomInt(1, 10) <= 3) {
            return null;
        }
        $length = $this->generateRandomInt(0, 1000);
        return $this->generateRandomString($length);
    }
    
    /**
     * Property Test: Create task round-trip
     */
    public function testCreateTaskRoundTrip(): bool {
        return $this->runPropertyTest(
            'Task Data Round-Trip - Create and retrieve',
            function() {
                // Setup: Create test user
                $userId = $this->setupTestUser();
                
                // Generate random title and description
                $title = $this->generateRandomTitle();
                $description = $this->generateRandomDescription();
                
                // Create task
                try {
                    $taskId = $this->taskRepository->createTask([
                        'user_id' => $userId,
                        'title' => $title,
                        'description' => $description
                    ]);
                    $this->createdTaskIds[] = $taskId;
                } catch (Exception $e) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => "Failed to create task: " . $e->getMessage(),
                        'data' => [
                            'title_length' => strlen($title),
                            'description_length' => $description !== null ? strlen($description) : 'null'
                        ]
                    ];
                }
                
                // Retrieve task
                $retrievedTask = $this->taskRepository->findByIdAndUserId($taskId, $userId);
                
                if (!$retrievedTask) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => "Failed to retrieve task",
                        'data' => ['task_id' => $taskId]
                    ];
                }
                
                // Verify title matches
                if ($retrievedTask['title'] !== $title) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => "Title mismatch after round-trip",
                        'data' => [
                            'original_title' => $title,
                            'retrieved_title' => $retrievedTask['title']
                        ]
                    ];
                }
                
                // Verify description matches
                if ($retrievedTask['description'] !== $description) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => "Description mismatch after round-trip",
                        'data' => [
                            'original_description' => $description,
                            'retrieved_description' => $retrievedTask['description']
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
     * Property Test: Update task round-trip
     */
    public function testUpdateTaskRoundTrip(): bool {
        return $this->runPropertyTest(
            'Task Data Round-Trip - Update and retrieve',
            function() {
                // Setup: Create test user
                $userId = $this->setupTestUser();
                
                // Create initial task
                $initialTitle = $this->generateRandomTitle();
                $initialDescription = $this->generateRandomDescription();
                
                try {
                    $taskId = $this->taskRepository->createTask([
                        'user_id' => $userId,
                        'title' => $initialTitle,
                        'description' => $initialDescription
                    ]);
                    $this->createdTaskIds[] = $taskId;
                } catch (Exception $e) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => "Failed to create initial task: " . $e->getMessage(),
                        'data' => []
                    ];
                }
                
                // Generate new random title and description
                $newTitle = $this->generateRandomTitle();
                $newDescription = $this->generateRandomDescription();
                
                // Update task
                try {
                    $this->taskRepository->updateTask($taskId, $userId, [
                        'title' => $newTitle,
                        'description' => $newDescription
                    ]);
                } catch (Exception $e) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => "Failed to update task: " . $e->getMessage(),
                        'data' => [
                            'task_id' => $taskId,
                            'new_title_length' => strlen($newTitle),
                            'new_description_length' => $newDescription !== null ? strlen($newDescription) : 'null'
                        ]
                    ];
                }
                
                // Retrieve updated task
                $retrievedTask = $this->taskRepository->findByIdAndUserId($taskId, $userId);
                
                if (!$retrievedTask) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => "Failed to retrieve updated task",
                        'data' => ['task_id' => $taskId]
                    ];
                }
                
                // Verify title matches new value
                if ($retrievedTask['title'] !== $newTitle) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => "Title mismatch after update round-trip",
                        'data' => [
                            'expected_title' => $newTitle,
                            'actual_title' => $retrievedTask['title']
                        ]
                    ];
                }
                
                // Verify description matches new value
                if ($retrievedTask['description'] !== $newDescription) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => "Description mismatch after update round-trip",
                        'data' => [
                            'expected_description' => $newDescription,
                            'actual_description' => $retrievedTask['description']
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
        echo "=== Task Data Round-Trip Property Tests ===\n\n";
        
        $allPassed = true;
        
        if (!$this->testCreateTaskRoundTrip()) {
            $allPassed = false;
        }
        
        if (!$this->testUpdateTaskRoundTrip()) {
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
