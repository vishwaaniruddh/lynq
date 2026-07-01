<?php
/**
 * Material Masters API - Get Material Master Detail
 * GET /api/material-masters/detail.php?id={id}
 * 
 * Gets a single material master with all items and product details
 * ADV users only
 * 
 * Query Parameters:
 * - id: Material Master ID (required)
 * 
 * Response: { success: bool, data: { material_master: {} } }
 * 
 * **Validates: Requirements 1.1**
 */

require_once __DIR__ . '/../../config/autoload.php';
require_once __DIR__ . '/../ApiResponse.php';
require_once __DIR__ . '/../../middleware/ApiAuthMiddleware.php';
require_once __DIR__ . '/../../services/MaterialMasterService.php';

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
    
    // Require ADV user access - Material Masters are ADV only
    $currentUser = $authMiddleware->requireAdvUser();
    
    // Get material master ID from query
    $masterId = isset($_GET['id']) ? (int)$_GET['id'] : null;
    
    if (!$masterId) {
        ApiResponse::validationError(['id' => 'Material Master ID is required']);
    }
    
    $materialMasterService = new MaterialMasterService();
    
    // Get material master with items
    $materialMaster = $materialMasterService->getById($masterId, $currentUser['company_id']);
    
    if (!$materialMaster) {
        ApiResponse::notFound('Material Master not found');
    }
    
    // Log API access
    $authMiddleware->logApiAccess($currentUser['id'], '/api/material-masters/detail', 'GET', [
        'material_master_id' => $masterId
    ]);
    
    ApiResponse::success(
        ['material_master' => $materialMaster],
        'Material Master retrieved successfully'
    );
    
} catch (Exception $e) {
    error_log("Material Masters API Error: " . $e->getMessage());
    ApiResponse::serverError('Failed to retrieve Material Master');
}
