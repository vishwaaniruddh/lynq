<?php
/**
 * Task Service
 * Handles business logic for personal user tasks
 * 
 * Requirements: 1.1, 1.2, 2.1, 3.1, 3.2, 3.3, 4.1, 4.2, 5.1, 5.3, 6.2
 * - 1.1: Create new tasks associated with user
 * - 1.2: Reject empty/whitespace-only titles
 * - 2.1: Return only tasks belonging to user
 * - 3.1, 3.2: Toggle completion status with timestamp
 * - 3.3: Only task owner can toggle completion
 * - 4.1: Permanently delete tasks
 * - 4.2: Only task owner can delete
 * - 5.1: Update task title with validation
 * - 5.3: Only task owner can edit
 * - 6.2: Deny access to other users' tasks
 */

require_once __DIR__ . '/../config/autoload.php';
require_once __DIR__ . '/../repositories/TaskRepository.php';

class TaskService {
    private $db;
    private $taskRepository;
    
    public function __construct() {
        $this->db = DatabaseConfig::getInstance();
        $this->taskRepository = new TaskRepository();
    }
    
    /**
     * Get all tasks for a user sorted by created_at DESC
     * Requirement 2.1
     * 
     * @param int $userId User ID
     * @return array Result with success status and tasks data
     */
    public function getUserTasks(int $userId): array {
        try {
            $tasks = $this->taskRepository->findByUserId($userId);
            
            return [
                'success' => true,
                'data' => $tasks
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to retrieve tasks: ' . $e->getMessage(),
                'code' => 'FETCH_ERROR'
            ];
        }
    }
    
    /**
     * Get a single task by ID with user authorization
     * Requirement 6.2
     * 
     * @param int $taskId Task ID
     * @param int $userId User ID (for authorization)
     * @return array Result with success status and task data
     */
    public function getTask(int $taskId, int $userId): array {
        try {
            $task = $this->taskRepository->findByIdAndUserId($taskId, $userId);
            
            if ($task === null) {
                return [
                    'success' => false,
                    'message' => 'Task not found or access denied',
                    'code' => 'NOT_FOUND'
                ];
            }
            
            return [
                'success' => true,
                'data' => $task
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to retrieve task: ' . $e->getMessage(),
                'code' => 'FETCH_ERROR'
            ];
        }
    }
    
    /**
     * Validate task title
     * Requirement 1.2, 5.1
     * 
     * @param string $title Title to validate
     * @return array Validation result with success status
     */
    private function validateTitle(string $title): array {
        // Check for empty or whitespace-only title
        // Use comprehensive whitespace mask including form feed (\f)
        $whitespaceChars = " \t\n\r\0\x0B\f";
        if (trim($title, $whitespaceChars) === '') {
            return [
                'valid' => false,
                'message' => 'Title cannot be empty or contain only whitespace',
                'code' => 'VALIDATION_ERROR'
            ];
        }
        
        // Check title length
        if (strlen($title) > 255) {
            return [
                'valid' => false,
                'message' => 'Title must be 255 characters or less',
                'code' => 'VALIDATION_ERROR'
            ];
        }
        
        return ['valid' => true];
    }
    
    /**
     * Create a new task
     * Requirement 1.1, 1.2
     * 
     * @param int $userId User ID
     * @param string $title Task title
     * @param string|null $description Optional task description
     * @return array Result with success status and created task
     */
    public function createTask(int $userId, string $title, ?string $description = null): array {
        try {
            // Validate title
            $validation = $this->validateTitle($title);
            if (!$validation['valid']) {
                return [
                    'success' => false,
                    'message' => $validation['message'],
                    'code' => $validation['code']
                ];
            }
            
            $taskId = $this->taskRepository->createTask([
                'user_id' => $userId,
                'title' => $title,
                'description' => $description
            ]);
            
            // Retrieve the created task
            $task = $this->taskRepository->findByIdAndUserId($taskId, $userId);
            
            return [
                'success' => true,
                'message' => 'Task created successfully',
                'data' => $task
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to create task: ' . $e->getMessage(),
                'code' => 'CREATE_ERROR'
            ];
        }
    }
    
    /**
     * Update an existing task
     * Requirement 5.1, 5.3
     * 
     * @param int $taskId Task ID
     * @param int $userId User ID (for authorization)
     * @param string $title New title
     * @param string|null $description New description
     * @return array Result with success status and updated task
     */
    public function updateTask(int $taskId, int $userId, string $title, ?string $description = null): array {
        try {
            // Validate title
            $validation = $this->validateTitle($title);
            if (!$validation['valid']) {
                return [
                    'success' => false,
                    'message' => $validation['message'],
                    'code' => $validation['code']
                ];
            }
            
            // Check if task exists and belongs to user
            $existingTask = $this->taskRepository->findByIdAndUserId($taskId, $userId);
            if ($existingTask === null) {
                return [
                    'success' => false,
                    'message' => 'Task not found or access denied',
                    'code' => 'NOT_FOUND'
                ];
            }
            
            // Update the task
            $this->taskRepository->updateTask($taskId, $userId, [
                'title' => $title,
                'description' => $description
            ]);
            
            // Retrieve the updated task
            $task = $this->taskRepository->findByIdAndUserId($taskId, $userId);
            
            return [
                'success' => true,
                'message' => 'Task updated successfully',
                'data' => $task
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to update task: ' . $e->getMessage(),
                'code' => 'UPDATE_ERROR'
            ];
        }
    }
    
    /**
     * Delete a task
     * Requirement 4.1, 4.2
     * 
     * @param int $taskId Task ID
     * @param int $userId User ID (for authorization)
     * @return array Result with success status
     */
    public function deleteTask(int $taskId, int $userId): array {
        try {
            // Check if task exists and belongs to user
            $existingTask = $this->taskRepository->findByIdAndUserId($taskId, $userId);
            if ($existingTask === null) {
                return [
                    'success' => false,
                    'message' => 'Task not found or access denied',
                    'code' => 'NOT_FOUND'
                ];
            }
            
            // Delete the task
            $deleted = $this->taskRepository->deleteTask($taskId, $userId);
            
            if (!$deleted) {
                return [
                    'success' => false,
                    'message' => 'Failed to delete task',
                    'code' => 'DELETE_ERROR'
                ];
            }
            
            return [
                'success' => true,
                'message' => 'Task deleted successfully'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to delete task: ' . $e->getMessage(),
                'code' => 'DELETE_ERROR'
            ];
        }
    }
    
    /**
     * Toggle task completion status
     * Requirement 3.1, 3.2, 3.3
     * 
     * @param int $taskId Task ID
     * @param int $userId User ID (for authorization)
     * @return array Result with success status and updated task
     */
    public function toggleTaskCompletion(int $taskId, int $userId): array {
        try {
            // Check if task exists and belongs to user
            $existingTask = $this->taskRepository->findByIdAndUserId($taskId, $userId);
            if ($existingTask === null) {
                return [
                    'success' => false,
                    'message' => 'Task not found or access denied',
                    'code' => 'NOT_FOUND'
                ];
            }
            
            // Toggle completion
            $this->taskRepository->toggleCompletion($taskId, $userId);
            
            // Retrieve the updated task
            $task = $this->taskRepository->findByIdAndUserId($taskId, $userId);
            
            return [
                'success' => true,
                'message' => 'Task completion status toggled successfully',
                'data' => $task
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to toggle task completion: ' . $e->getMessage(),
                'code' => 'TOGGLE_ERROR'
            ];
        }
    }
    
    /**
     * Get task count for a user
     * 
     * @param int $userId User ID
     * @return int Number of tasks
     */
    public function getTaskCount(int $userId): int {
        return $this->taskRepository->countByUserId($userId);
    }
}
