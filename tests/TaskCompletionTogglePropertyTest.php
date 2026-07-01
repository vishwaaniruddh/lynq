<?php
/**
 * Property Test: Completion Toggle Round-Trip
 * **Feature: task-checklist, Property 7: Completion toggle round-trip**
 * **Validates: Requirements 3.1, 3.2**
 * 
 * Property: *For any* task, toggling completion twice should return the task 
 * to its original completion state (incomplete → complete → incomplete).
 */

require_once __DIR__ . '/PropertyTestBase.php';
require_once __DIR__ . '/../repositories/TaskRepository.php';

class TaskCompletionTogglePropertyTest extends PropertyTestBase {
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
        $username = 'test_task_tg_' . $this->generateRandomString(8);
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
     * Property Test: Toggle completion twice returns to original state
     */
    public function testCompletionToggleRoundTrip(): bool {
        return $this->runPropertyTest(
            'Completion Toggle Round-Trip',
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
                
                // Get initial state
                $initialTask = $this->taskRepository->findByIdAndUserId($taskId, $userId);
                $initialIsCompleted = (int)$initialTask['is_completed'];
                $initialCompletedAt = $initialTask['completed_at'];
                
                // First toggle
                try {
                    $this->taskRepository->toggleCompletion($taskId, $userId);
                } catch (Exception $e) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => "Failed first toggle: " . $e->getMessage(),
                        'data' => []
                    ];
                }
                
                // Verify first toggle changed state
                $afterFirstToggle = $this->taskRepository->findByIdAndUserId($taskId, $userId);
                $firstToggleIsCompleted = (int)$afterFirstToggle['is_completed'];
                
                if ($firstToggleIsCompleted === $initialIsCompleted) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => "First toggle did not change completion state",
                        'data' => [
                            'initial' => $initialIsCompleted,
                            'after_first_toggle' => $firstToggleIsCompleted
                        ]
                    ];
                }
                
                // Verify completed_at is set when marking complete (Requirement 3.1)
                if ($firstToggleIsCompleted === 1 && $afterFirstToggle['completed_at'] === null) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => "completed_at not set when marking task complete",
                        'data' => [
                            'is_completed' => $firstToggleIsCompleted,
                            'completed_at' => $afterFirstToggle['completed_at']
                        ]
                    ];
                }
                
                // Second toggle
                try {
                    $this->taskRepository->toggleCompletion($taskId, $userId);
                } catch (Exception $e) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => "Failed second toggle: " . $e->getMessage(),
                        'data' => []
                    ];
                }
                
                // Verify second toggle returned to original state
                $afterSecondToggle = $this->taskRepository->findByIdAndUserId($taskId, $userId);
                $secondToggleIsCompleted = (int)$afterSecondToggle['is_completed'];
                
                if ($secondToggleIsCompleted !== $initialIsCompleted) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => "Second toggle did not return to original state",
                        'data' => [
                            'initial' => $initialIsCompleted,
                            'after_second_toggle' => $secondToggleIsCompleted
                        ]
                    ];
                }
                
                // Verify completed_at is cleared when marking incomplete (Requirement 3.2)
                if ($secondToggleIsCompleted === 0 && $afterSecondToggle['completed_at'] !== null) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => "completed_at not cleared when marking task incomplete",
                        'data' => [
                            'is_completed' => $secondToggleIsCompleted,
                            'completed_at' => $afterSecondToggle['completed_at']
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
     * Property Test: Toggle sets completed_at timestamp correctly
     */
    public function testToggleSetsCompletedAtTimestamp(): bool {
        return $this->runPropertyTest(
            'Toggle Sets completed_at Timestamp',
            function() {
                // Setup: Create test user
                $userId = $this->setupTestUser();
                
                // Create task (starts incomplete)
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
                
                // Verify initial state
                $initialTask = $this->taskRepository->findByIdAndUserId($taskId, $userId);
                if ((int)$initialTask['is_completed'] !== 0) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => "New task should start incomplete",
                        'data' => ['is_completed' => $initialTask['is_completed']]
                    ];
                }
                
                if ($initialTask['completed_at'] !== null) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => "New task should have null completed_at",
                        'data' => ['completed_at' => $initialTask['completed_at']]
                    ];
                }
                
                // Toggle to complete
                $beforeToggle = date('Y-m-d H:i:s');
                $this->taskRepository->toggleCompletion($taskId, $userId);
                $afterToggle = date('Y-m-d H:i:s');
                
                // Verify completed_at is set and within expected range
                $completedTask = $this->taskRepository->findByIdAndUserId($taskId, $userId);
                
                if ((int)$completedTask['is_completed'] !== 1) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => "Task should be completed after toggle",
                        'data' => ['is_completed' => $completedTask['is_completed']]
                    ];
                }
                
                if ($completedTask['completed_at'] === null) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => "completed_at should be set after marking complete",
                        'data' => []
                    ];
                }
                
                // Verify timestamp is reasonable (within the toggle window)
                $completedAt = $completedTask['completed_at'];
                if ($completedAt < $beforeToggle || $completedAt > $afterToggle) {
                    // Allow 1 second tolerance for timing
                    $beforeTime = strtotime($beforeToggle) - 1;
                    $afterTime = strtotime($afterToggle) + 1;
                    $completedTime = strtotime($completedAt);
                    
                    if ($completedTime < $beforeTime || $completedTime > $afterTime) {
                        $this->cleanupTestData();
                        return [
                            'success' => false,
                            'message' => "completed_at timestamp out of expected range",
                            'data' => [
                                'before_toggle' => $beforeToggle,
                                'completed_at' => $completedAt,
                                'after_toggle' => $afterToggle
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
        echo "=== Task Completion Toggle Property Tests ===\n\n";
        
        $allPassed = true;
        
        if (!$this->testCompletionToggleRoundTrip()) {
            $allPassed = false;
        }
        
        if (!$this->testToggleSetsCompletedAtTimestamp()) {
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
