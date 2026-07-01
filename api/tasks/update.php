<?php
/**
 * Tasks API - Update Task
 * PUT /api/tasks/update.php
 * 
 * Updates an existing task with user ownership verification
 * 
 * Request Body (JSON):
 * - id: Task ID (required)
 * - title: Task title (required, non-empty, max 255 chars)
 * - description: Task description (optional)
 * 
 * Response: { success: bool, data: updated task }
 * 
 * **Feature: task-checklist, Property 10: Update timestamp modification**
 * **Validates: Requirements 5.1, 5.2, 5.3, 5.4**
 */

require_once __DIR__ . '/../../config/autoload.php';
require_once __DIR__ . '/../ApiResponse.php';
require_once __DIR__ . '/../../middleware/ApiAuthMiddleware.php';
require_once __DIR__ . '/../../services/TaskService.php';

// Handle CORS
ApiResponse::setCorsHeaders();
ApiResponse::handlePreflight();

// Only allow PUT
if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    ApiResponse::methodNotAllowed(['PUT']);
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
    $title = isset($input['title']) ? $input['title'] : '';
    $description = isset($input['description']) ? $input['description'] : null;
    
    $taskService = new TaskService();
    
    // Update task with user ownership verification (Requirement 5.3)
    // Service validates title is not empty (Requirement 5.1)
    // Service saves description changes (Requirement 5.2)
    // Service updates updated_at timestamp (Requirement 5.4)
    $result = $taskService->updateTask($taskId, $currentUser['id'], $title, $description);
    
    if (!$result['success']) {
        if ($result['code'] === 'NOT_FOUND') {
            // Return 403 for access denied (task belongs to different user)
            ApiResponse::forbidden('Task not found or access denied');
        }
        if ($result['code'] === 'VALIDATION_ERROR') {
            ApiResponse::validationError(['title' => $result['message']]);
        }
        ApiResponse::serverError($result['message']);
    }
    
    // Log API access
    $authMiddleware->logApiAccess($currentUser['id'], '/api/tasks/update', 'PUT', [
        'id' => $taskId,
        'title_length' => strlen($title),
        'has_description' => !empty($description)
    ]);
    
    ApiResponse::success($result['data'], 'Task updated successfully');
    
} catch (Exception $e) {
    error_log("Tasks API Error: " . $e->getMessage());
    ApiResponse::serverError('Failed to update task');
}
