<?php
/**
 * Permissions API - Get User Permissions
 * GET /api/permissions/user.php?id={user_id}
 * 
 * Gets all permissions for a specific user
 * 
 * Query Parameters:
 * - id: User ID (required)
 * 
 * Response: { success: bool, data: { permissions: {} } }
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
    
    // Get user ID from query
    $userId = isset($_GET['id']) ? (int)$_GET['id'] : null;
    
    if (!$userId) {
        ApiResponse::validationError(['id' => 'User ID is required']);
    }
    
    // Check if user can view this user's permissions
    $userModel = new User();
    $targetUser = $userModel->findWithRelations($userId);
    
    if (!$targetUser) {
        ApiResponse::notFound('User not found');
    }
    
    // ADV users can view any user's permissions
    // Contractors can only view their own company's users
    if ($currentUser['company_type'] !== 'ADV') {
        if ((int)$currentUser['company_id'] !== (int)$targetUser['company_id']) {
            ApiResponse::forbidden('Cannot view permissions for users outside your company');
        }
    }
    
    $permissionEngine = new PermissionEngine();
    $permissions = $permissionEngine->getUserPermissions($userId);
    
    // Log API access
    $authMiddleware->logApiAccess($currentUser['id'], '/api/permissions/user', 'GET', ['user_id' => $userId]);
    
    ApiResponse::success([
        'user_id' => $userId,
        'permissions' => $permissions
    ], 'User permissions retrieved successfully');
    
} catch (Exception $e) {
    error_log("Permissions API Error: " . $e->getMessage());
    ApiResponse::serverError('Failed to retrieve user permissions');
}
