<?php
/**
 * Tasks API - List User Tasks
 * GET /api/tasks/list.php
 * 
 * Returns all tasks for the authenticated user sorted by created_at DESC
 * 
 * Response: { success: bool, data: array of tasks }
 * 
 * **Feature: task-checklist, Property 5: Task list field completeness**
 * **Validates: Requirements 2.1, 2.2, 2.3, 2.4**
 */

require_once __DIR__ . '/../../config/autoload.php';
require_once __DIR__ . '/../ApiResponse.php';
require_once __DIR__ . '/../../middleware/ApiAuthMiddleware.php';
require_once __DIR__ . '/../../services/TaskService.php';

// Handle CORS
ApiResponse::setCorsHeaders();
ApiResponse::handlePreflight();

// Only allow GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    ApiResponse::methodNotAllowed(['GET']);
}

try {
    $authMiddleware = new ApiAuthMiddleware();
    
    // Check rate limiting
    $authMiddleware->checkRateLimit();
    
    // Require authentication
    $currentUser = $authMiddleware->requireAuth();
    
    $taskService = new TaskService();
    
    // Get user's tasks (Requirement 2.1 - only tasks belonging to user)
    $result = $taskService->getUserTasks($currentUser['id']);
    
    if (!$result['success']) {
        ApiResponse::serverError($result['message']);
    }
    
    // Log API access
    $authMiddleware->logApiAccess($currentUser['id'], '/api/tasks/list', 'GET', []);
    
    // Return tasks with required fields (Requirement 2.2)
    // Empty array is valid (Requirement 2.3)
    ApiResponse::success($result['data'], 'Tasks retrieved successfully');
    
} catch (Exception $e) {
    error_log("Tasks API Error: " . $e->getMessage());
    ApiResponse::serverError('Failed to retrieve tasks');
}
