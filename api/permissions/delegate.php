<?php
/**
 * Permissions API - Delegate Permission to Company
 * POST /api/permissions/delegate.php
 * 
 * Delegates a permission to a contractor company (ADV only)
 * 
 * Request Body (JSON):
 * {
 *   "company_id": "int (required)",
 *   "permission": "string (required)"
 * }
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

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ApiResponse::methodNotAllowed(['POST']);
}

try {
    $authMiddleware = new ApiAuthMiddleware();
    
    // Check rate limiting
    $authMiddleware->checkRateLimit();
    
    // Require ADV user
    $currentUser = $authMiddleware->requireAdvUser();
    
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        ApiResponse::validationError(['body' => 'Invalid JSON body']);
    }
    
    // Validate required fields
    $errors = [];
    if (!isset($input['company_id']) || !is_numeric($input['company_id'])) {
        $errors['company_id'] = 'Company ID is required';
    }
    if (!isset($input['permission']) || trim($input['permission']) === '') {
        $errors['permission'] = 'Permission name is required';
    }
    
    if (!empty($errors)) {
        ApiResponse::validationError($errors);
    }
    
    $companyId = (int)$input['company_id'];
    $permissionName = trim($input['permission']);
    
    $permissionEngine = new PermissionEngine();
    
    // Validate permission format
    if (!$permissionEngine->validatePermissionFormat($permissionName)) {
        ApiResponse::validationError(['permission' => 'Invalid permission format. Use module.action format.']);
    }
    
    // Delegate permission
    $result = $permissionEngine->delegatePermission($companyId, $permissionName, $currentUser['id']);
    
    // Log API access
    $authMiddleware->logApiAccess($currentUser['id'], '/api/permissions/delegate', 'POST', [
        'company_id' => $companyId,
        'permission' => $permissionName
    ]);
    
    ApiResponse::success(null, 'Permission delegated successfully', 201);
    
} catch (Exception $e) {
    $message = $e->getMessage();
    
    if (strpos($message, 'does not exist') !== false) {
        ApiResponse::notFound($message);
    } elseif (strpos($message, 'Only ADV') !== false || strpos($message, 'contractor') !== false) {
        ApiResponse::forbidden($message);
    } else {
        error_log("Permissions API Error: " . $message);
        ApiResponse::serverError('Failed to delegate permission');
    }
}
