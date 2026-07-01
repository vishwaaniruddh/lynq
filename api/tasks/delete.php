<?php
/**
 * Tasks API - Delete Task
 * DELETE /api/tasks/delete.php
 * 
 * Deletes a task with user ownership verification
 * 
 * Request Body (JSON):
 * - id: Task ID (required)
 * 
 * Response: { success: bool, message: string }
 * 
 * **Feature: task-checklist, Property 9: Task deletion permanence**
 * **Validates: Requirements 4.1, 4.2, 4.3**
 */

require_once __DIR__ . '/../../config/autoload.php';
require_once __DIR__ . '/../ApiResponse.php';
require_once __DIR__ . '/../../middleware/ApiAuthMiddleware.php';
require_once __DIR__ . '/../../services/TaskService.php';

// Handle CORS
ApiResponse::setCorsHeaders();
ApiResponse::handlePreflight();

// Only allow DELETE
if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    ApiResponse::methodNotAllowed(['DELETE']);
}

try {
    $authMiddleware = new ApiAuthMiddleware();
    
    // Check rate limiting
    $authMiddleware->checkRateLimit();
    
    // Require authentication
    $currentUser = $authMiddleware->requireAuth();
    
    // Parse JSON body
    $input = json_decode(file_get_contents('php://input'), true);
    
    if ($input === null && json_last_error() !== JSON_ERROR_NONE) {
        ApiResponse::validationError(['body' => 'Invalid JSON in request body']);
    }
    
    // Validate task ID
    if (!isset($input['id']) || !is_numeric($input['id'])) {
        ApiResponse::validationError(['id' => 'Task ID is required and must be numeric']);
    }
    
    $taskId = (int)$input['id'];
    
    $taskService = new TaskService();
    
    // Delete task with user ownership verification (Requirement 4.2)
    // Service permanently removes task from database (Requirement 4.1)
    $result = $taskService->deleteTask($taskId, $currentUser['id']);
    
    if (!$result['success']) {
        if ($result['code'] === 'NOT_FOUND') {
            // Return 403 for access denied (task belongs to different user)
            ApiResponse::forbidden('Task not found or access denied');
        }
        ApiResponse::serverError($result['message']);
    }
    
    // Log API access
    $authMiddleware->logApiAccess($currentUser['id'], '/api/tasks/delete', 'DELETE', ['id' => $taskId]);
    
    // Return confirmation of successful deletion (Requirement 4.3)
    ApiResponse::success(null, 'Task deleted successfully');
    
} catch (Exception $e) {
    error_log("Tasks API Error: " . $e->getMessage());
    ApiResponse::serverError('Failed to delete task');
}
