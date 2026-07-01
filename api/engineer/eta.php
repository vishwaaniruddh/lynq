<?php
/**
 * Engineer ETA API Endpoint
 * POST /api/engineer/eta.php - Submit or update ETA
 * GET /api/engineer/eta.php?assignment_id={id} - Get current ETA
 * GET /api/engineer/eta.php?assignment_id={id}&history=1 - Get ETA history
 * 
 * POST Parameters:
 * - assignment_id: Engineer assignment ID (required)
 * - eta_datetime: ETA date and time in Y-m-d H:i:s format (required)
 * 
 * **Validates: Requirements 2.1, 2.2, 2.5**
 */

require_once __DIR__ . '/../../config/autoload.php';
require_once __DIR__ . '/../ApiResponse.php';
require_once __DIR__ . '/../../middleware/ApiAuthMiddleware.php';
require_once __DIR__ . '/../../services/ETAService.php';
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
    
    $etaService = new ETAService();
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        handleGetRequest($etaService, $authMiddleware, $user);
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        handlePostRequest($etaService, $authMiddleware, $user);
    } else {
        ApiResponse::methodNotAllowed(['GET', 'POST']);
    }
    
} catch (Exception $e) {
    error_log("ETA API Error: " . $e->getMessage());
    ApiResponse::serverError('Failed to process request');
}

/**
 * Handle GET request - Get current ETA or ETA history
 * Requirements: 2.2, 2.5
 */
function handleGetRequest($etaService, $authMiddleware, $user) {
    if (!isset($_GET['assignment_id']) || (int)$_GET['assignment_id'] <= 0) {
        ApiResponse::validationError(
            ['assignment_id' => ['Assignment ID is required']],
            'Validation failed'
        );
    }
    
    $assignmentId = (int)$_GET['assignment_id'];
    $getHistory = isset($_GET['history']) && $_GET['history'] == '1';
    
    // Verify engineer has access to this assignment
    $siteAccessService = new SiteAccessService();
    $accessResult = $siteAccessService->validateEngineerAssignmentAccess($user['id'], $assignmentId);
    if (!$accessResult['success']) {
        ApiResponse::forbidden($accessResult['message']);
    }
    
    if ($getHistory) {
        // Get ETA history (Requirement 2.5)
        $history = $etaService->getETAHistory($assignmentId);
        
        $authMiddleware->logApiAccess($user['id'], '/api/engineer/eta', 'GET', [
            'assignment_id' => $assignmentId,
            'action' => 'history'
        ]);
        
        ApiResponse::success([
            'assignment_id' => $assignmentId,
            'history' => $history,
            'count' => count($history)
        ], 'ETA history retrieved successfully');
    } else {
        // Get current ETA (Requirement 2.2)
        $eta = $etaService->getETA($assignmentId);
        
        $authMiddleware->logApiAccess($user['id'], '/api/engineer/eta', 'GET', [
            'assignment_id' => $assignmentId,
            'action' => 'current'
        ]);
        
        if ($eta) {
            ApiResponse::success([
                'assignment_id' => $assignmentId,
                'eta' => $eta
            ], 'ETA retrieved successfully');
        } else {
            ApiResponse::success([
                'assignment_id' => $assignmentId,
                'eta' => null
            ], 'No ETA found for this assignment');
        }
    }
}

/**
 * Handle POST request - Submit or update ETA
 * Requirements: 2.1, 2.2
 */
function handlePostRequest($etaService, $authMiddleware, $user) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input)) {
        $input = $_POST;
    }
    
    // Validate required fields
    $errors = [];
    
    if (!isset($input['assignment_id']) || (int)$input['assignment_id'] <= 0) {
        $errors['assignment_id'] = ['Assignment ID is required'];
    }
    
    if (!isset($input['eta_datetime']) || trim($input['eta_datetime']) === '') {
        $errors['eta_datetime'] = ['ETA date and time is required'];
    }
    
    if (!empty($errors)) {
        ApiResponse::validationError($errors, 'Validation failed');
    }
    
    $assignmentId = (int)$input['assignment_id'];
    $etaDateTime = trim($input['eta_datetime']);
    
    // Verify engineer has access to this assignment
    $siteAccessService = new SiteAccessService();
    $accessResult = $siteAccessService->validateEngineerAssignmentAccess($user['id'], $assignmentId);
    if (!$accessResult['success']) {
        ApiResponse::forbidden($accessResult['message']);
    }
    
    // Check if ETA already exists - if so, update; otherwise, submit new
    $existingETA = $etaService->getETA($assignmentId);
    
    if ($existingETA) {
        // Update existing ETA (Requirement 2.5 - maintain history)
        $result = $etaService->updateETA($assignmentId, $etaDateTime, $user['id']);
    } else {
        // Submit new ETA (Requirement 2.2)
        $result = $etaService->submitETA($assignmentId, $etaDateTime, $user['id']);
    }
    
    $authMiddleware->logApiAccess($user['id'], '/api/engineer/eta', 'POST', [
        'assignment_id' => $assignmentId,
        'eta_datetime' => $etaDateTime,
        'action' => $existingETA ? 'update' : 'submit'
    ]);
    
    if ($result['success']) {
        ApiResponse::success($result['data'], $result['message']);
    } else {
        switch ($result['code'] ?? 'ERROR') {
            case 'VALIDATION_ERROR':
                ApiResponse::validationError($result['errors'] ?? [], $result['message']);
                break;
            case 'NOT_FOUND':
                ApiResponse::notFound($result['message']);
                break;
            case 'UNAUTHORIZED':
                ApiResponse::forbidden($result['message']);
                break;
            default:
                ApiResponse::error($result['code'] ?? 'ERROR', $result['message'], 400);
        }
    }
}
