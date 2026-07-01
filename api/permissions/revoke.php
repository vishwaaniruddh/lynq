<?php
/**
 * Permissions API - Revoke Permission from Company
 * DELETE /api/permissions/revoke.php?company_id={id}&permission={name}
 * 
 * Revokes a delegated permission from a contractor company (ADV only)
 * 
 * Query Parameters:
 * - company_id: Company ID (required)
 * - permission: Permission name (required)
 * 
 * Response: { success: bool, message: string }
 * 
 * **Validates: Requirements 7.1, 7.2, 7.3**
 */

require_once __DIR__ . '/../../config/autoload.php';
require_once __DIR__ . '/../ApiResponse.php';
require_once __DIR__ . '/../../middleware/ApiAuthMiddleware.php';

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
    
    // Require ADV user
    $currentUser = $authMiddleware->requireAdvUser();
    
    // Get query parameters
    $companyId = isset($_GET['company_id']) ? (int)$_GET['company_id'] : null;
    $permissionName = isset($_GET['permission']) ? trim($_GET['permission']) : null;
    
    // Validate required parameters
    $errors = [];
    if (!$companyId) {
        $errors['company_id'] = 'Company ID is required';
    }
    if (!$permissionName) {
        $errors['permission'] = 'Permission name is required';
    }
    
    if (!empty($errors)) {
        ApiResponse::validationError($errors);
    }
    
    $permissionEngine = new PermissionEngine();
    
    // Revoke permission
    $result = $permissionEngine->revokePermission($companyId, $permissionName, $currentUser['id']);
    
    if (!$result) {
        ApiResponse::notFound('Permission delegation not found');
    }
    
    // Log API access
    $authMiddleware->logApiAccess($currentUser['id'], '/api/permissions/revoke', 'DELETE', [
        'company_id' => $companyId,
        'permission' => $permissionName
    ]);
    
    ApiResponse::success(null, 'Permission revoked successfully');
    
} catch (Exception $e) {
    $message = $e->getMessage();
    
    if (strpos($message, 'does not exist') !== false) {
        ApiResponse::notFound($message);
    } elseif (strpos($message, 'Only ADV') !== false) {
        ApiResponse::forbidden($message);
    } else {
        error_log("Permissions API Error: " . $message);
        ApiResponse::serverError('Failed to revoke permission');
    }
}
