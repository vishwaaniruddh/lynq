<?php
/**
 * Engineer Site Detail API Endpoint
 * GET /api/engineer/site.php?id={assignment_id} - Get site details for an assignment
 * 
 * Query Parameters (GET):
 * - id: Assignment ID (required)
 * 
 * **Validates: Requirements 6.2, 6.3**
 */

require_once __DIR__ . '/../../config/autoload.php';
require_once __DIR__ . '/../ApiResponse.php';
require_once __DIR__ . '/../../middleware/ApiAuthMiddleware.php';
require_once __DIR__ . '/../../services/EngineerAssignmentService.php';
require_once __DIR__ . '/../../services/SiteAccessService.php';

// Handle CORS
ApiResponse::setCorsHeaders();
ApiResponse::handlePreflight();

try {
    $authMiddleware = new ApiAuthMiddleware();
    
    // Check rate limiting
    $authMiddleware->checkRateLimit();
    
    // Require authenticated user
    $user = $authMiddleware->requireAuth();
    
    // Verify user is an engineer (contractor user)
    if (!isEngineerUser($user['id'])) {
        ApiResponse::forbidden('Access denied. Engineer users only.');
    }
    
    $assignmentService = new EngineerAssignmentService();
    $siteAccessService = new SiteAccessService();
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        handleGetRequest($assignmentService, $siteAccessService, $authMiddleware, $user);
    } else {
        ApiResponse::methodNotAllowed(['GET']);
    }
    
} catch (Exception $e) {
    error_log("Engineer Site API Error: " . $e->getMessage());
    ApiResponse::serverError('Failed to process request');
}

/**
 * Handle GET request - Get site details
 */
function handleGetRequest($assignmentService, $siteAccessService, $authMiddleware, $user) {
    if (!isset($_GET['id']) || (int)$_GET['id'] <= 0) {
        ApiResponse::validationError(
            ['id' => ['Assignment ID is required']],
            'Validation failed'
        );
    }
    
    $assignmentId = (int)$_GET['id'];
    
    // Verify engineer has access to this assignment (Requirement 6.3)
    $accessResult = $siteAccessService->validateEngineerAssignmentAccess($user['id'], $assignmentId);
    if (!$accessResult['success']) {
        ApiResponse::forbidden($accessResult['message']);
    }
    
    // Get assignment with full details (Requirement 6.2)
    $assignment = $assignmentService->getAssignment($assignmentId);
    
    if (!$assignment) {
        ApiResponse::notFound('Assignment not found');
    }
    
    // Get assignment history for this site
    $history = $assignmentService->getAssignmentHistory($assignment['site_id']);
    
    $authMiddleware->logApiAccess($user['id'], '/api/engineer/site', 'GET', [
        'assignment_id' => $assignmentId
    ]);
    
    ApiResponse::success([
        'assignment' => $assignment,
        'history' => $history
    ], 'Site details retrieved successfully');
}
