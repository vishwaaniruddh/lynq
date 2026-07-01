<?php
/**
 * Material Requests API - Get Material Request Detail
 * GET /api/material-requests/detail.php?id={id}
 * 
 * Returns detailed information about a material request including site info and items
 * Access is role-based: ADV (all), Contractor (delegated sites), Engineer (assigned sites)
 * 
 * Query Parameters:
 * - id: Material Request ID (required)
 * 
 * Response: { success: bool, data: { material_request: {} } }
 * 
 * **Validates: Requirements 4.3, 4.4, 6.2**
 */

require_once __DIR__ . '/../../config/autoload.php';
require_once __DIR__ . '/../ApiResponse.php';
require_once __DIR__ . '/../../middleware/ApiAuthMiddleware.php';
require_once __DIR__ . '/../../services/MaterialRequestService.php';

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
    
    // Require authentication
    $currentUser = $authMiddleware->requireAuth();
    
    // Validate ID parameter
    if (!isset($_GET['id']) || !is_numeric($_GET['id']) || (int)$_GET['id'] <= 0) {
        ApiResponse::validationError(['id' => 'Valid Material Request ID is required']);
    }
    
    $requestId = (int)$_GET['id'];
    
    // Determine user role
    $companyType = strtoupper($currentUser['company_type'] ?? '');
    $role = 'engineer'; // Default to most restrictive
    
    if ($companyType === 'ADV') {
        $role = MaterialRequestService::ROLE_ADV;
    } elseif ($companyType === 'CONTRACTOR') {
        $role = MaterialRequestService::ROLE_CONTRACTOR;
    } else {
        $role = MaterialRequestService::ROLE_ENGINEER;
    }
    
    $materialRequestService = new MaterialRequestService();
    
    // Get material request detail with authorization check
    $materialRequest = $materialRequestService->getDetail(
        $requestId,
        $currentUser['id'],
        $role,
        $currentUser['company_id']
    );
    
    if (!$materialRequest) {
        ApiResponse::notFound('Material Request not found or access denied');
    }
    
    // Log API access
    $authMiddleware->logApiAccess($currentUser['id'], '/api/material-requests/detail', 'GET', [
        'material_request_id' => $requestId,
        'role' => $role
    ]);
    
    ApiResponse::success(
        ['material_request' => $materialRequest],
        'Material Request retrieved successfully'
    );
    
} catch (Exception $e) {
    error_log("Material Requests API Error: " . $e->getMessage());
    ApiResponse::serverError('Failed to retrieve Material Request');
}
