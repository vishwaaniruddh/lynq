<?php
/**
 * Engineer ADA API Endpoint
 * POST /api/engineer/ada.php - Submit ADA with coordinates
 * GET /api/engineer/ada.php?assignment_id={id} - Get ADA
 * 
 * POST Parameters:
 * - assignment_id: Engineer assignment ID (required)
 * - latitude: GPS latitude (required)
 * - longitude: GPS longitude (required)
 * 
 * **Validates: Requirements 3.4**
 */

require_once __DIR__ . '/../../config/autoload.php';
require_once __DIR__ . '/../ApiResponse.php';
require_once __DIR__ . '/../../middleware/ApiAuthMiddleware.php';
require_once __DIR__ . '/../../services/ADAService.php';
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
    
    $adaService = new ADAService();
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        handleGetRequest($adaService, $authMiddleware, $user);
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        handlePostRequest($adaService, $authMiddleware, $user);
    } else {
        ApiResponse::methodNotAllowed(['GET', 'POST']);
    }
    
} catch (Exception $e) {
    error_log("ADA API Error: " . $e->getMessage());
    ApiResponse::serverError('Failed to process request');
}

/**
 * Handle GET request - Get ADA for an assignment
 * Requirements: 3.4
 */
function handleGetRequest($adaService, $authMiddleware, $user) {
    if (!isset($_GET['assignment_id']) || (int)$_GET['assignment_id'] <= 0) {
        ApiResponse::validationError(
            ['assignment_id' => ['Assignment ID is required']],
            'Validation failed'
        );
    }
    
    $assignmentId = (int)$_GET['assignment_id'];
    
    // Verify engineer has access to this assignment
    $siteAccessService = new SiteAccessService();
    $accessResult = $siteAccessService->validateEngineerAssignmentAccess($user['id'], $assignmentId);
    if (!$accessResult['success']) {
        ApiResponse::forbidden($accessResult['message']);
    }
    
    // Get ADA with details
    $ada = $adaService->getADAWithDetails($assignmentId);
    
    $authMiddleware->logApiAccess($user['id'], '/api/engineer/ada', 'GET', [
        'assignment_id' => $assignmentId
    ]);
    
    if ($ada) {
        ApiResponse::success([
            'assignment_id' => $assignmentId,
            'ada' => $ada
        ], 'ADA retrieved successfully');
    } else {
        ApiResponse::success([
            'assignment_id' => $assignmentId,
            'ada' => null
        ], 'No ADA found for this assignment');
    }
}

/**
 * Handle POST request - Submit ADA with coordinates
 * Requirements: 3.4
 */
function handlePostRequest($adaService, $authMiddleware, $user) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input)) {
        $input = $_POST;
    }
    
    // Validate required fields
    $errors = [];
    
    if (!isset($input['assignment_id']) || (int)$input['assignment_id'] <= 0) {
        $errors['assignment_id'] = ['Assignment ID is required'];
    }
    
    if (!isset($input['latitude']) || $input['latitude'] === '') {
        $errors['latitude'] = ['Latitude is required'];
    } elseif (!is_numeric($input['latitude'])) {
        $errors['latitude'] = ['Latitude must be a valid number'];
    }
    
    if (!isset($input['longitude']) || $input['longitude'] === '') {
        $errors['longitude'] = ['Longitude is required'];
    } elseif (!is_numeric($input['longitude'])) {
        $errors['longitude'] = ['Longitude must be a valid number'];
    }
    
    if (!empty($errors)) {
        ApiResponse::validationError($errors, 'Validation failed');
    }
    
    $assignmentId = (int)$input['assignment_id'];
    $latitude = (float)$input['latitude'];
    $longitude = (float)$input['longitude'];
    
    // Verify engineer has access to this assignment
    $siteAccessService = new SiteAccessService();
    $accessResult = $siteAccessService->validateEngineerAssignmentAccess($user['id'], $assignmentId);
    if (!$accessResult['success']) {
        ApiResponse::forbidden($accessResult['message']);
    }
    
    // Submit ADA (Requirement 3.4)
    $result = $adaService->submitADA($assignmentId, $latitude, $longitude, $user['id']);
    
    $authMiddleware->logApiAccess($user['id'], '/api/engineer/ada', 'POST', [
        'assignment_id' => $assignmentId,
        'latitude' => $latitude,
        'longitude' => $longitude
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
            case 'PREREQUISITE_NOT_MET':
                ApiResponse::error('PREREQUISITE_NOT_MET', $result['message'], 400);
                break;
            case 'DUPLICATE_ERROR':
                ApiResponse::error('DUPLICATE_ERROR', $result['message'], 409);
                break;
            default:
                ApiResponse::error($result['code'] ?? 'ERROR', $result['message'], 400);
        }
    }
}
