<?php
/**
 * Tasks API - Create Task
 * POST /api/tasks/create.php
 * 
 * Creates a new task for the authenticated user
 * 
 * Request Body (JSON):
 * - title: Task title (required, non-empty, max 255 chars)
 * - description: Task description (optional)
 * 
 * Response: { success: bool, data: created task with ID }
 * 
 * **Feature: task-checklist, Property 1: Task data round-trip**
 * **Validates: Requirements 1.1, 1.2, 1.3, 1.4**
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
    
    // Get title (required) and description (optional)
    $title = isset($input['title']) ? $input['title'] : '';
    $description = isset($input['description']) ? $input['description'] : null;
    
    $taskService = new TaskService();
    
    // Create task associated with current user (Requirement 1.1)
    // Service validates title is not empty/whitespace (Requirement 1.2)
    // Service sets is_completed=0 and created_at (Requirement 1.3)
    // Service stores description if provided (Requirement 1.4)
    $result = $taskService->createTask($currentUser['id'], $title, $description);
    
    if (!$result['success']) {
        if ($result['code'] === 'VALIDATION_ERROR') {
            ApiResponse::validationError(['title' => $result['message']]);
        }
        ApiResponse::serverError($result['message']);
    }
    
    // Log API access
    $authMiddleware->logApiAccess($currentUser['id'], '/api/tasks/create', 'POST', [
        'title_length' => strlen($title),
        'has_description' => !empty($description)
    ]);
    
    ApiResponse::success($result['data'], 'Task created successfully');
    
} catch (Exception $e) {
    error_log("Tasks API Error: " . $e->getMessage());
    ApiResponse::serverError('Failed to create task');
}
