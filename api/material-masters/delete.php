<?php
/**
 * Material Masters API - Delete Material Master
 * DELETE /api/material-masters/delete.php?id={id}
 * 
 * Soft deletes a material master (sets deleted_at timestamp)
 * ADV users only
 * 
 * Query Parameters:
 * - id: Material Master ID (required)
 * 
 * Response: { success: bool, message: string }
 * 
 * **Validates: Requirements 1.6, 9.4**
 */

require_once __DIR__ . '/../../config/autoload.php';
require_once __DIR__ . '/../ApiResponse.php';
require_once __DIR__ . '/../../middleware/ApiAuthMiddleware.php';
require_once __DIR__ . '/../../services/MaterialMasterService.php';

// Handle CORS
ApiResponse::setCorsHeaders();
ApiResponse::handlePreflight();

// Only allow DELETE
if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    ApiResponse::methodNotAllowed(['DELETE']);
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
    
    $materialMasterService = new MaterialMasterService();
    
    // Delete material master using service (handles validation)
    $result = $materialMasterService->delete($masterId, $currentUser['company_id']);
    
    if (!$result['success']) {
        // Handle different error types
        switch ($result['code'] ?? 'UNKNOWN') {
            case 'NOT_FOUND':
                ApiResponse::notFound($result['message']);
                break;
            default:
                ApiResponse::error($result['code'] ?? 'DELETE_ERROR', $result['message'], 400);
        }
    }
    
    // Log API access
    $authMiddleware->logApiAccess($currentUser['id'], '/api/material-masters/delete', 'DELETE', [
        'material_master_id' => $masterId
    ]);
    
    ApiResponse::success(null, 'Material Master deleted successfully');
    
} catch (Exception $e) {
    error_log("Material Masters API Error: " . $e->getMessage());
    ApiResponse::serverError('Failed to delete Material Master: ' . $e->getMessage());
}
