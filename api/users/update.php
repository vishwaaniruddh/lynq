<?php
/**
 * Users API - Update User
 * PUT /api/users/update.php?id={id}
 * 
 * Updates an existing user with validation and company isolation
 * 
 * Query Parameters:
 * - id: User ID (required)
 * 
 * Request Body (JSON):
 * {
 *   "username": "string (optional)",
 *   "email": "string (optional)",
 *   "password": "string (optional)",
 *   "first_name": "string (optional)",
 *   "last_name": "string (optional)",
 *   "company_id": "int (optional, ADV only)",
 *   "role_id": "int (optional)",
 *   "status": "int (optional)"
 * }
 * 
 * Response: { success: bool, data: { user: {} } }
 * 
 * **Validates: Requirements 7.1, 7.2, 7.3, 8.1, 8.4**
 */

require_once __DIR__ . '/../../config/autoload.php';
require_once __DIR__ . '/../ApiResponse.php';
require_once __DIR__ . '/../../middleware/ApiAuthMiddleware.php';

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
    
    // Require authentication and users.update permission
    $currentUser = $authMiddleware->requirePermission('users.update');
    
    // Get user ID from query
    $userId = isset($_GET['id']) ? (int)$_GET['id'] : null;
    
    if (!$userId) {
        ApiResponse::validationError(['id' => 'User ID is required']);
    }
    
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        ApiResponse::validationError(['body' => 'Invalid JSON body']);
    }
    
    // Validate email format if provided
    if (isset($input['email']) && !filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
        ApiResponse::validationError(['email' => 'Invalid email format']);
    }
    
    // Validate password strength if provided
    if (isset($input['password']) && !empty($input['password'])) {
        $passwordErrors = ValidationUtils::validatePasswordStrength($input['password']);
        if (!empty($passwordErrors)) {
            ApiResponse::validationError(['password' => $passwordErrors]);
        }
    }
    
    // Build update data with sanitization
    $updateData = [];
    
    if (isset($input['username'])) {
        $updateData['username'] = ValidationUtils::sanitizeInput($input['username'], 'string');
    }
    if (isset($input['email'])) {
        $updateData['email'] = ValidationUtils::sanitizeInput($input['email'], 'email');
    }
    if (isset($input['password']) && !empty($input['password'])) {
        $updateData['password'] = $input['password'];
    }
    if (isset($input['first_name'])) {
        $updateData['first_name'] = ValidationUtils::sanitizeInput($input['first_name'], 'string');
    }
    if (isset($input['last_name'])) {
        $updateData['last_name'] = ValidationUtils::sanitizeInput($input['last_name'], 'string');
    }
    if (isset($input['role_id'])) {
        $updateData['role_id'] = (int)$input['role_id'];
    }
    if (isset($input['status'])) {
        $updateData['status'] = (int)$input['status'];
    }
    
    // Company change is ADV-only
    if (isset($input['company_id'])) {
        if ($currentUser['company_type'] !== 'ADV') {
            ApiResponse::forbidden('Only ADV users can change user company');
        }
        $updateData['company_id'] = (int)$input['company_id'];
    }
    
    if (empty($updateData)) {
        ApiResponse::validationError(['body' => 'No valid fields to update']);
    }
    
    $userService = new UserService();
    
    // Update user
    $user = $userService->updateUser($userId, $updateData, $currentUser['id']);
    
    // Sanitize response
    unset($user['password_hash']);
    unset($user['failed_login_attempts']);
    unset($user['locked_until']);
    
    // Log API access
    $authMiddleware->logApiAccess($currentUser['id'], '/api/users/update', 'PUT', [
        'user_id' => $userId,
        'fields' => array_keys($updateData)
    ]);
    
    ApiResponse::success(['user' => $user], 'User updated successfully');
    
} catch (InvalidArgumentException $e) {
    ApiResponse::validationError(['error' => $e->getMessage()]);
} catch (CompanyAccessDeniedException $e) {
    ApiResponse::forbidden($e->getMessage());
} catch (Exception $e) {
    error_log("Users API Error: " . $e->getMessage());
    ApiResponse::serverError('Failed to update user');
}
