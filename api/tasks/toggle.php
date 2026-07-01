<?php
/**
 * Tasks API - Toggle Task Completion
 * POST /api/tasks/toggle.php
 * 
 * Toggles the completion status of a task with user ownership verification
 * 
 * Request Body (JSON):
 * - id: Task ID (required)
 * 
 * Response: { success: bool, data: updated task with new completion status }
 * 
 * **Feature: task-checklist, Property 7: Completion toggle round-trip**
 * **Validates: Requirements 3.1, 3.2, 3.3**
 */

require_once __DIR__ . '/../../config/autoload.php';
require_once __DIR__ . '/../ApiResponse.php';
require_once __DIR__ . '/../../middleware/ApiAuthMiddleware.php';
require_once __DIR__ . '/../../services/TaskService.php';

// Handle CORS
ApiResponse::setCorsHeaders();
ApiResponse::handlePreflight();

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ApiResponse::methodNotAllowed(['POST']);
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
    
    // Toggle task completion with user ownership verification (Requirement 3.3)
    // Service updates is_completed and completed_at (Requirements 3.1, 3.2)
    $result = $taskService->toggleTaskCompletion($taskId, $currentUser['id']);
    
    if (!$result['success']) {
        if ($result['code'] === 'NOT_FOUND') {
            // Return 403 for access denied (task belongs to different user)
            ApiResponse::forbidden('Task not found or access denied');
        }
        ApiResponse::serverError($result['message']);
    }
    
    // Log API access
    $authMiddleware->logApiAccess($currentUser['id'], '/api/tasks/toggle', 'POST', [
        'id' => $taskId,
        'new_status' => $result['data']['is_completed']
    ]);
    
    ApiResponse::success($result['data'], 'Task completion status toggled successfully');
    
} catch (Exception $e) {
    error_log("Tasks API Error: " . $e->getMessage());
    ApiResponse::serverError('Failed to toggle task completion');
}
