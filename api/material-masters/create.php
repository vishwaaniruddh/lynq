<?php
/**
 * Material Masters API - Create Material Master
 * POST /api/material-masters/create.php
 * 
 * Creates a new material master with items in a transaction
 * ADV users only
 * 
 * Request Body (JSON):
 * {
 *   "name": "string (required, max 100 chars)",
 *   "description": "string (optional, max 500 chars)",
 *   "status": "string (optional, default: active)",
 *   "items": [
 *     { "product_id": int, "quantity": int }
 *   ] (required, at least one item)
 * }
 * 
 * Response: { success: bool, data: { material_master: {} } }
 * 
 * **Validates: Requirements 1.4, 9.2**
 */

require_once __DIR__ . '/../../config/autoload.php';
require_once __DIR__ . '/../ApiResponse.php';
require_once __DIR__ . '/../../middleware/ApiAuthMiddleware.php';
require_once __DIR__ . '/../../services/MaterialMasterService.php';

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
    
    // Require ADV user access - Material Masters are ADV only
    $currentUser = $authMiddleware->requireAdvUser();
    
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        ApiResponse::validationError(['body' => 'Invalid JSON body']);
    }
    
    $materialMasterService = new MaterialMasterService();
    
    // Create material master using service (handles validation)
    $result = $materialMasterService->create(
        $input,
        $currentUser['id'],
        $currentUser['company_id']
    );
    
    if (!$result['success']) {
        // Handle different error types
        switch ($result['code'] ?? 'UNKNOWN') {
            case 'VALIDATION_ERROR':
                ApiResponse::validationError($result['errors'] ?? [], $result['message']);
                break;
            case 'NOT_FOUND':
                ApiResponse::notFound($result['message']);
                break;
            default:
                ApiResponse::error($result['code'] ?? 'CREATE_ERROR', $result['message'], 400);
        }
    }
    
    // Log API access
    $authMiddleware->logApiAccess($currentUser['id'], '/api/material-masters/create', 'POST', [
        'material_master_id' => $result['data']['id'] ?? null,
        'name' => $input['name'] ?? null,
        'item_count' => count($input['items'] ?? [])
    ]);
    
    ApiResponse::success(
        ['material_master' => $result['data']],
        'Material Master created successfully',
        201
    );
    
} catch (Exception $e) {
    error_log("Material Masters API Error: " . $e->getMessage());
    ApiResponse::serverError('Failed to create Material Master: ' . $e->getMessage());
}
