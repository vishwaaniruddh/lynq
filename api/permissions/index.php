<?php
/**
 * Permissions API - List Permissions
 * GET /api/permissions/index.php
 * 
 * Lists all available permissions, optionally grouped by module
 * 
 * Query Parameters:
 * - grouped: If "true", returns permissions grouped by module
 * 
 * Response: { success: bool, data: { permissions: [] } }
 * 
 * **Validates: Requirements 7.1, 7.2, 7.3**
 */

require_once __DIR__ . '/../../config/autoload.php';
require_once __DIR__ . '/../ApiResponse.php';
require_once __DIR__ . '/../../middleware/ApiAuthMiddleware.php';

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
    
    $permissionEngine = new PermissionEngine();
    $permissionModel = new Permission();
    
    $grouped = isset($_GET['grouped']) && $_GET['grouped'] === 'true';
    
    if ($grouped) {
        $permissions = $permissionEngine->getPermissionsGroupedByModule();
    } else {
        $permissions = $permissionModel->findAll();
    }
    
    // Log API access
    $authMiddleware->logApiAccess($currentUser['id'], '/api/permissions', 'GET', ['grouped' => $grouped]);
    
    ApiResponse::success(['permissions' => $permissions], 'Permissions retrieved successfully');
    
} catch (Exception $e) {
    error_log("Permissions API Error: " . $e->getMessage());
    ApiResponse::serverError('Failed to retrieve permissions');
}
