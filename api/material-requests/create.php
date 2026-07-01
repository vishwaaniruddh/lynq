<?php
/**
 * Material Requests API - Create Material Request
 * POST /api/material-requests/create.php
 * 
 * Creates a new material request for a site using a Material Master template
 * ADV users only
 * 
 * Request Body (JSON):
 * {
 *   "site_id": int (required),
 *   "material_master_id": int (required),
 *   "notes": "string (optional)"
 * }
 * 
 * Response: { success: bool, data: { material_request: {} } }
 * 
 * **Validates: Requirements 3.3, 3.5, 9.6**
 */

require_once __DIR__ . '/../../config/autoload.php';
require_once __DIR__ . '/../ApiResponse.php';
require_once __DIR__ . '/../../middleware/ApiAuthMiddleware.php';
require_once __DIR__ . '/../../services/MaterialRequestService.php';

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
    
    // Require ADV user access - Material Request creation is ADV only
    $currentUser = $authMiddleware->requireAdvUser();
    
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        ApiResponse::validationError(['body' => 'Invalid JSON body']);
    }
    
    // Validate required fields
    $errors = [];
    
    if (empty($input['site_id'])) {
        $errors['site_id'] = 'Site ID is required';
    } elseif (!is_numeric($input['site_id']) || (int)$input['site_id'] <= 0) {
        $errors['site_id'] = 'Site ID must be a positive integer';
    }
    
    if (empty($input['material_master_id'])) {
        $errors['material_master_id'] = 'Material Master ID is required';
    } elseif (!is_numeric($input['material_master_id']) || (int)$input['material_master_id'] <= 0) {
        $errors['material_master_id'] = 'Material Master ID must be a positive integer';
    }
    
    // Validate notes if provided
    if (isset($input['notes']) && strlen($input['notes']) > 1000) {
        $errors['notes'] = 'Notes must not exceed 1000 characters';
    }
    
    if (!empty($errors)) {
        ApiResponse::validationError($errors);
    }
    
    $materialRequestService = new MaterialRequestService();
    
    // Create material request using service (handles validation and duplicate check)
    $result = $materialRequestService->create(
        (int)$input['site_id'],
        (int)$input['material_master_id'],
        $currentUser['id'],
        $currentUser['company_id'],
        $input['notes'] ?? null
    );
    
    if (!$result['success']) {
        // Handle different error types
        switch ($result['code'] ?? 'UNKNOWN') {
            case 'SITE_NOT_FOUND':
                ApiResponse::notFound($result['message']);
                break;
            case 'MASTER_NOT_FOUND':
                ApiResponse::notFound($result['message']);
                break;
            case 'MASTER_INACTIVE':
                ApiResponse::error('MASTER_INACTIVE', $result['message'], 400);
                break;
            case 'DUPLICATE_REQUEST':
                ApiResponse::error('DUPLICATE_REQUEST', $result['message'], 409);
                break;
            default:
                ApiResponse::error($result['code'] ?? 'CREATE_ERROR', $result['message'], 400);
        }
    }
    
    // Log API access
    $authMiddleware->logApiAccess($currentUser['id'], '/api/material-requests/create', 'POST', [
        'material_request_id' => $result['data']['id'] ?? null,
        'site_id' => $input['site_id'],
        'material_master_id' => $input['material_master_id']
    ]);
    
    ApiResponse::success(
        ['material_request' => $result['data']],
        'Material Request created successfully',
        201
    );
    
} catch (Exception $e) {
    error_log("Material Requests API Error: " . $e->getMessage());
    ApiResponse::serverError('Failed to create Material Request: ' . $e->getMessage());
}
