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
require_once __DIR__ . '/../../services/ETAService.php';
require_once __DIR__ . '/../../services/ADAService.php';

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
    $assignmentId = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_GET['assignment_id']) ? (int)$_GET['assignment_id'] : 0);
    
    if ($assignmentId <= 0 && isset($_GET['site_id'])) {
        $siteId = (int)$_GET['site_id'];
        $db = DatabaseConfig::getInstance();
        $assignRow = $db->getResults(
            "SELECT id FROM engineer_assignments WHERE site_id = ? AND engineer_id = ? ORDER BY id DESC LIMIT 1",
            [$siteId, $user['id']],
            'ii'
        );
        if (!empty($assignRow)) {
            $assignmentId = (int)$assignRow[0]['id'];
        }
    }
    
    if ($assignmentId <= 0) {
        ApiResponse::validationError(
            ['id' => ['Assignment ID or Site ID is required']],
            'Validation failed'
        );
    }
    
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
    
    // Attach ETA & ADA data
    $etaService = new ETAService();
    $adaService = new ADAService();
    $eta = $etaService->getETA($assignmentId);
    $ada = $adaService->getADA($assignmentId);
    
    if ($eta) {
        $assignment['eta'] = $eta['eta_datetime'];
        $assignment['eta_datetime'] = $eta['eta_datetime'];
        $assignment['eta_remarks'] = $eta['remarks'] ?? null;
    }
    
    if ($ada) {
        $assignment['ada_datetime'] = $ada['ada_datetime'];
        $assignment['ada_latitude'] = $ada['latitude'];
        $assignment['ada_longitude'] = $ada['longitude'];
    }
    
    // Get assignment history for this site
    $history = $assignmentService->getAssignmentHistory($assignment['site_id']);
    
    $authMiddleware->logApiAccess($user['id'], '/api/engineer/site', 'GET', [
        'assignment_id' => $assignmentId
    ]);
    
    ApiResponse::success([
        'assignment' => $assignment,
        'site' => $assignment,
        'history' => $history
    ], 'Site details retrieved successfully');
}
