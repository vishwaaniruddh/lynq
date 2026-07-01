<?php
/**
 * Engineer Feasibility Check API Endpoint
 * POST /api/engineer/feasibility.php - Submit feasibility check
 * GET /api/engineer/feasibility.php?assignment_id={id} - Get feasibility check
 * POST /api/engineer/feasibility.php?action=upload - Upload images
 * 
 * POST Parameters (submit):
 * - assignment_id: Engineer assignment ID (required)
 * - no_of_atm: Number of ATMs (required)
 * - operator: Network operator (required)
 * - signal_status: Signal status (required)
 * - ups_available: UPS availability (required)
 * - earthing: Earthing status (required)
 * - ... (other feasibility fields)
 * 
 * POST Parameters (upload):
 * - action: 'upload' (required)
 * - feasibility_id: Feasibility check ID (required)
 * - category: Image category (required)
 * - file: Image file (required)
 * 
 * **Validates: Requirements 4.4, 6.3**
 */

require_once __DIR__ . '/../../config/autoload.php';
require_once __DIR__ . '/../ApiResponse.php';
require_once __DIR__ . '/../../middleware/ApiAuthMiddleware.php';
require_once __DIR__ . '/../../services/FeasibilityService.php';
require_once __DIR__ . '/../../services/ImageUploadService.php';
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
    
    $feasibilityService = new FeasibilityService();
    $imageUploadService = new ImageUploadService();
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        handleGetRequest($feasibilityService, $authMiddleware, $user);
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Check if this is an upload request
        $action = $_GET['action'] ?? $_POST['action'] ?? '';
        if ($action === 'upload') {
            handleUploadRequest($imageUploadService, $feasibilityService, $authMiddleware, $user);
        } else {
            handlePostRequest($feasibilityService, $authMiddleware, $user);
        }
    } else {
        ApiResponse::methodNotAllowed(['GET', 'POST']);
    }
    
} catch (Exception $e) {
    error_log("Feasibility API Error: " . $e->getMessage());
    ApiResponse::serverError('Failed to process request');
}

/**
 * Handle GET request - Get feasibility check for an assignment
 * Requirements: 4.4
 */
function handleGetRequest($feasibilityService, $authMiddleware, $user) {
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
    
    // Get feasibility check
    $feasibility = $feasibilityService->getFeasibilityByAssignment($assignmentId);
    
    // Get master site info
    $siteInfo = $feasibilityService->getMasterSiteInfo($assignmentId);
    
    // Get feasibility status
    $status = $feasibilityService->getFeasibilityStatus($assignmentId);
    
    $authMiddleware->logApiAccess($user['id'], '/api/engineer/feasibility', 'GET', [
        'assignment_id' => $assignmentId
    ]);
    
    ApiResponse::success([
        'assignment_id' => $assignmentId,
        'feasibility_status' => $status,
        'site_info' => $siteInfo,
        'feasibility' => $feasibility
    ], $feasibility ? 'Feasibility check retrieved successfully' : 'No feasibility check found');
}

/**
 * Handle POST request - Submit feasibility check
 * Requirements: 4.4
 */
function handlePostRequest($feasibilityService, $authMiddleware, $user) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input)) {
        $input = $_POST;
    }
    
    // Validate assignment_id
    if (!isset($input['assignment_id']) || (int)$input['assignment_id'] <= 0) {
        ApiResponse::validationError(
            ['assignment_id' => ['Assignment ID is required']],
            'Validation failed'
        );
    }
    
    $assignmentId = (int)$input['assignment_id'];
    
    // Verify engineer has access to this assignment
    $siteAccessService = new SiteAccessService();
    $accessResult = $siteAccessService->validateEngineerAssignmentAccess($user['id'], $assignmentId);
    if (!$accessResult['success']) {
        ApiResponse::forbidden($accessResult['message']);
    }
    
    // Remove assignment_id from data as it's handled separately
    unset($input['assignment_id']);
    
    // Create feasibility check (Requirement 4.4)
    $result = $feasibilityService->createFeasibilityCheck($assignmentId, $input, $user['id']);
    
    $authMiddleware->logApiAccess($user['id'], '/api/engineer/feasibility', 'POST', [
        'assignment_id' => $assignmentId,
        'action' => 'submit'
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

/**
 * Handle image upload request
 * Requirements: 6.3
 */
function handleUploadRequest($imageUploadService, $feasibilityService, $authMiddleware, $user) {
    // Validate required fields
    $errors = [];
    
    if (!isset($_POST['feasibility_id']) || (int)$_POST['feasibility_id'] <= 0) {
        $errors['feasibility_id'] = ['Feasibility ID is required'];
    }
    
    if (!isset($_POST['category']) || trim($_POST['category']) === '') {
        $errors['category'] = ['Image category is required'];
    }
    
    if (!isset($_FILES['file']) || $_FILES['file']['error'] === UPLOAD_ERR_NO_FILE) {
        $errors['file'] = ['Image file is required'];
    }
    
    if (!empty($errors)) {
        ApiResponse::validationError($errors, 'Validation failed');
    }
    
    $feasibilityId = (int)$_POST['feasibility_id'];
    $category = trim($_POST['category']);
    
    // Verify feasibility check exists and user has access
    $feasibility = $feasibilityService->getFeasibilityCheck($feasibilityId);
    if (!$feasibility) {
        ApiResponse::notFound('Feasibility check not found');
    }
    
    // Verify engineer has access to this assignment
    $siteAccessService = new SiteAccessService();
    $accessResult = $siteAccessService->validateEngineerAssignmentAccess($user['id'], $feasibility['assignment_id']);
    if (!$accessResult['success']) {
        ApiResponse::forbidden($accessResult['message']);
    }
    
    // Valid categories for image upload
    $validCategories = [
        'backroom_network_snap',
        'router_antenna_snap',
        'antenna_routing_snap',
        'ups_available_snap',
        'no_of_ups_snap',
        'ups_working_snap',
        'power_socket_availability_snap',
        'earthing_snap',
        'power_fluctuation_snap',
        'remarks_snap'
    ];
    
    if (!in_array($category, $validCategories)) {
        ApiResponse::validationError(
            ['category' => ['Invalid image category. Valid categories: ' . implode(', ', $validCategories)]],
            'Validation failed'
        );
    }
    
    // Upload image (Requirement 6.3)
    $result = $imageUploadService->uploadImage($_FILES['file'], $category, $feasibilityId);
    
    if ($result['success']) {
        // Update feasibility record with image path
        updateFeasibilityImagePath($feasibilityId, $category, $result['data']['path']);
        
        $authMiddleware->logApiAccess($user['id'], '/api/engineer/feasibility', 'POST', [
            'action' => 'upload',
            'feasibility_id' => $feasibilityId,
            'category' => $category
        ]);
        
        ApiResponse::success($result['data'], $result['message']);
    } else {
        switch ($result['code'] ?? 'ERROR') {
            case 'VALIDATION_ERROR':
                ApiResponse::validationError($result['errors'] ?? [], $result['message']);
                break;
            default:
                ApiResponse::error($result['code'] ?? 'ERROR', $result['message'], 400);
        }
    }
}

/**
 * Update feasibility record with image path
 * 
 * @param int $feasibilityId Feasibility check ID
 * @param string $category Image category (column name)
 * @param string $path Image file path
 */
function updateFeasibilityImagePath($feasibilityId, $category, $path) {
    $db = DatabaseConfig::getInstance();
    
    // Sanitize category to prevent SQL injection (only allow valid column names)
    $validColumns = [
        'backroom_network_snap',
        'router_antenna_snap',
        'antenna_routing_snap',
        'ups_available_snap',
        'no_of_ups_snap',
        'ups_working_snap',
        'power_socket_availability_snap',
        'earthing_snap',
        'power_fluctuation_snap',
        'remarks_snap'
    ];
    
    if (!in_array($category, $validColumns)) {
        return false;
    }
    
    $sql = "UPDATE `feasibility_checks` SET `{$category}` = ?, `updated_at` = NOW() WHERE `id` = ?";
    $stmt = $db->executeQuery($sql, [$path, $feasibilityId], 'si');
    $affectedRows = $stmt->affected_rows;
    $stmt->close();
    
    return $affectedRows > 0;
}
