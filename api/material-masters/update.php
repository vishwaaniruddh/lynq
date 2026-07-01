<?php
/**
 * Material Masters API - Update Material Master
 * PUT /api/material-masters/update.php?id={id}
 * 
 * Updates an existing material master and replaces items
 * ADV users only
 * 
 * Query Parameters:
 * - id: Material Master ID (required)
 * 
 * Request Body (JSON):
 * {
 *   "name": "string (optional, max 100 chars)",
 *   "description": "string (optional, max 500 chars)",
 *   "status": "string (optional, active/inactive)",
 *   "items": [
 *     { "product_id": int, "quantity": int }
 *   ] (optional, replaces all items if provided)
 * }
 * 
 * Response: { success: bool, data: { material_master: {} } }
 * 
 * **Validates: Requirements 1.5, 9.3**
 */

require_once __DIR__ . '/../../config/autoload.php';
require_once __DIR__ . '/../ApiResponse.php';
require_once __DIR__ . '/../../middleware/ApiAuthMiddleware.php';
require_once __DIR__ . '/../../services/MaterialMasterService.php';

// Handle CORS
ApiResponse::setCorsHeaders();
ApiResponse::handlePreflight();

// Only allow PUT
if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    ApiResponse::methodNotAllowed(['PUT']);
}

try {
    $authMiddleware = new ApiAuthMiddleware();
    
    // Check rate limiting
    $authMiddleware->checkRateLimit();
    
    // Require ADV user access - Material Masters are ADV only
    $currentUser = $authMiddleware->requireAdvUser();
    
    // Get material master ID from query
    $masterId = isset($_GET['id']) ? (int)$_GET['id'] : null;
    
    if (!$masterId) {
        ApiResponse::validationError(['id' => 'Material Master ID is required']);
    }
    
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        ApiResponse::validationError(['body' => 'Invalid JSON body']);
    }
    
    // Check if there's anything to update
    if (empty($input)) {
        ApiResponse::validationError(['body' => 'No valid fields to update']);
    }
    
    $materialMasterService = new MaterialMasterService();
    
    // Update material master using service (handles validation)
    $result = $materialMasterService->update($masterId, $input, $currentUser['company_id']);
    
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
                ApiResponse::error($result['code'] ?? 'UPDATE_ERROR', $result['message'], 400);
        }
    }
    
    // Log API access
    $authMiddleware->logApiAccess($currentUser['id'], '/api/material-masters/update', 'PUT', [
        'material_master_id' => $masterId,
        'fields_updated' => array_keys($input)
    ]);
    
    ApiResponse::success(
        ['material_master' => $result['data']],
        'Material Master updated successfully'
    );
    
} catch (Exception $e) {
    error_log("Material Masters API Error: " . $e->getMessage());
    ApiResponse::serverError('Failed to update Material Master: ' . $e->getMessage());
}
