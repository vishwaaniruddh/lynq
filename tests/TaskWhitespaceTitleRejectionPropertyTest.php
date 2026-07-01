<?php
/**
 * Property Test: Whitespace Title Rejection
 * **Feature: task-checklist, Property 2: Whitespace title rejection**
 * **Validates: Requirements 1.2, 5.1**
 * 
 * Property: *For any* string composed entirely of whitespace characters (including empty string), 
 * attempting to create or update a task with that title should be rejected with a validation error.
 */

require_once __DIR__ . '/PropertyTestBase.php';
require_once __DIR__ . '/../services/TaskService.php';

class TaskWhitespaceTitleRejectionPropertyTest extends PropertyTestBase {
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
    private function setupTestUser(): int {
        $username = 'test_ws_title_' . $this->generateRandomString(8);
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
     * Generate random whitespace-only string
     */
    private function generateWhitespaceString(): string {
        $whitespaceChars = [' ', "\t", "\n", "\r", "\v", "\f"];
        $length = $this->generateRandomInt(0, 20);
        
        $result = '';
        for ($i = 0; $i < $length; $i++) {
            $result .= $this->generateRandomChoice($whitespaceChars);
        }
        
        return $result;
    }
    
    /**
     * Property Test: Create task with whitespace title should be rejected
     */
    public function testCreateWithWhitespaceTitle(): bool {
        return $this->runPropertyTest(
            'Create task with whitespace-only title should be rejected',
            function() {
                // Setup: Create test user
                $userId = $this->setupTestUser();
                
                // Generate whitespace-only title
                $whitespaceTitle = $this->generateWhitespaceString();
                
                // Attempt to create task with whitespace title
                $result = $this->taskService->createTask($userId, $whitespaceTitle, 'Some description');
                
                // Verify creation was rejected
                if ($result['success'] === true) {
                    // Track task for cleanup if it was created
                    if (isset($result['data']['id'])) {
                        $this->createdTaskIds[] = $result['data']['id'];
                    }
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => "Task creation should have been rejected for whitespace title",
                        'data' => [
                            'title' => json_encode($whitespaceTitle),
                            'title_length' => strlen($whitespaceTitle)
                        ]
                    ];
                }
                
                // Verify error code is VALIDATION_ERROR
                if ($result['code'] !== 'VALIDATION_ERROR') {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => "Expected VALIDATION_ERROR code",
                        'data' => [
                            'expected_code' => 'VALIDATION_ERROR',
                            'actual_code' => $result['code'] ?? 'none'
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
     * Property Test: Update task with whitespace title should be rejected
     */
    public function testUpdateWithWhitespaceTitle(): bool {
        return $this->runPropertyTest(
            'Update task with whitespace-only title should be rejected',
            function() {
                // Setup: Create test user
                $userId = $this->setupTestUser();
                
                // Create a valid task first
                $validTitle = 'Valid Task ' . $this->generateRandomString(8);
                $createResult = $this->taskService->createTask($userId, $validTitle, 'Description');
                
                if (!$createResult['success']) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => "Failed to create initial task for update test",
                        'data' => $createResult
                    ];
                }
                
                $taskId = $createResult['data']['id'];
                $this->createdTaskIds[] = $taskId;
                
                // Generate whitespace-only title for update
                $whitespaceTitle = $this->generateWhitespaceString();
                
                // Attempt to update task with whitespace title
                $updateResult = $this->taskService->updateTask($taskId, $userId, $whitespaceTitle, 'Updated description');
                
                // Verify update was rejected
                if ($updateResult['success'] === true) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => "Task update should have been rejected for whitespace title",
                        'data' => [
                            'title' => json_encode($whitespaceTitle),
                            'title_length' => strlen($whitespaceTitle)
                        ]
                    ];
                }
                
                // Verify error code is VALIDATION_ERROR
                if ($updateResult['code'] !== 'VALIDATION_ERROR') {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => "Expected VALIDATION_ERROR code",
                        'data' => [
                            'expected_code' => 'VALIDATION_ERROR',
                            'actual_code' => $updateResult['code'] ?? 'none'
                        ]
                    ];
                }
                
                // Verify original task title is unchanged
                $taskResult = $this->taskService->getTask($taskId, $userId);
                if ($taskResult['data']['title'] !== $validTitle) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => "Original task title should be unchanged after rejected update",
                        'data' => [
                            'expected_title' => $validTitle,
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
     * Run all property tests
     */
    public function runAllTests(): bool {
        echo "=== Task Whitespace Title Rejection Property Tests ===\n\n";
        
        $allPassed = true;
        
        if (!$this->testCreateWithWhitespaceTitle()) {
            $allPassed = false;
        }
        
        if (!$this->testUpdateWithWhitespaceTitle()) {
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
