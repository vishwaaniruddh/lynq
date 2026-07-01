<?php
/**
 * Property Test: New Task Defaults
 * **Feature: task-checklist, Property 3: New task defaults**
 * **Validates: Requirements 1.3**
 * 
 * Property: *For any* newly created task, the is_completed field should be 0 (false), 
 * completed_at should be NULL, and created_at should be set to a valid timestamp.
 */

require_once __DIR__ . '/PropertyTestBase.php';
require_once __DIR__ . '/../models/Task.php';

class TaskNewDefaultsPropertyTest extends PropertyTestBase {
    private $taskModel;
    private $testUserIds = [];
    private $createdTaskIds = [];
    
    public function __construct() {
        parent::__construct();
        $this->taskModel = new Task();
    }
    
    /**
     * Set up test user
     */
    private function setupTestUser(): int {
        $username = 'test_task_def_' . $this->generateRandomString(8);
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
     * Generate random non-empty title (1-255 chars)
     */
    private function generateRandomTitle(): string {
        $length = $this->generateRandomInt(1, 255);
        return $this->generateRandomString($length);
    }
    
    /**
     * Generate random description (0-500 chars for testing)
     */
    private function generateRandomDescription(): ?string {
        // 50% chance of null description
        if ($this->generateRandomBool()) {
            return null;
        }
        $length = $this->generateRandomInt(0, 500);
        return $this->generateRandomString($length);
    }
    
    /**
     * Property Test: New task has correct defaults
     */
    public function testNewTaskDefaults(): bool {
        return $this->runPropertyTest(
            'New Task Defaults - is_completed=0, completed_at=NULL, created_at set',
            function() {
                // Setup: Create test user
                $userId = $this->setupTestUser();
                
                // Generate random title and description
                $title = $this->generateRandomTitle();
                $description = $this->generateRandomDescription();
                
                // Record time before creation
                $beforeCreate = new DateTime();
                
                // Create task using model (only providing user_id, title, description)
                $taskData = [
                    'user_id' => $userId,
                    'title' => $title,
                    'description' => $description
                ];
                
                $createdTask = $this->taskModel->create($taskData);
                
                if (!$createdTask) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => "Failed to create task",
                        'data' => ['title_length' => strlen($title)]
                    ];
                }
                
                $taskId = $createdTask['id'];
                $this->createdTaskIds[] = $taskId;
                
                // Record time after creation
                $afterCreate = new DateTime();
                
                // Verify is_completed is 0 (false)
                if ((int)$createdTask['is_completed'] !== 0) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => "is_completed should be 0 for new task",
                        'data' => [
                            'expected' => 0,
                            'actual' => $createdTask['is_completed']
                        ]
                    ];
                }
                
                // Verify completed_at is NULL
                if ($createdTask['completed_at'] !== null) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => "completed_at should be NULL for new task",
                        'data' => [
                            'expected' => null,
                            'actual' => $createdTask['completed_at']
                        ]
                    ];
                }
                
                // Verify created_at is set and is a valid timestamp
                if (empty($createdTask['created_at'])) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => "created_at should be set for new task",
                        'data' => ['created_at' => $createdTask['created_at']]
                    ];
                }
                
                // Verify created_at is within reasonable time range (allow 5 second tolerance)
                $createdAt = new DateTime($createdTask['created_at']);
                $tolerance = clone $afterCreate;
                $tolerance->modify('+5 seconds');
                $beforeTolerance = clone $beforeCreate;
                $beforeTolerance->modify('-5 seconds');
                
                if ($createdAt < $beforeTolerance || $createdAt > $tolerance) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => "created_at timestamp is not within expected range",
                        'data' => [
                            'created_at' => $createdTask['created_at'],
                            'before' => $beforeCreate->format('Y-m-d H:i:s'),
                            'after' => $afterCreate->format('Y-m-d H:i:s')
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
        echo "=== Task New Defaults Property Tests ===\n\n";
        
        $allPassed = true;
        
        if (!$this->testNewTaskDefaults()) {
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
