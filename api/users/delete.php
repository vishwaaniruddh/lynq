<?php
/**
 * Users API - Delete User
 * DELETE /api/users/delete.php?id={id}
 * 
 * Deletes a user with company isolation and integrity checks
 * 
 * Query Parameters:
 * - id: User ID (required)
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
    
    // Require authentication and users.delete permission
    $currentUser = $authMiddleware->requirePermission('users.delete');
    
    // Get user ID from query
    $userId = isset($_GET['id']) ? (int)$_GET['id'] : null;
    
    if (!$userId) {
        ApiResponse::validationError(['id' => 'User ID is required']);
    }
    
    $userService = new UserService();
    
    // Delete user
    $result = $userService->deleteUser($userId, $currentUser['id']);
    
    if (!$result) {
        ApiResponse::serverError('Failed to delete user');
    }
    
    // Log API access
    $authMiddleware->logApiAccess($currentUser['id'], '/api/users/delete', 'DELETE', ['user_id' => $userId]);
    
    ApiResponse::success(null, 'User deleted successfully');
    
} catch (InvalidArgumentException $e) {
    $message = $e->getMessage();
    
    if (strpos($message, 'not found') !== false) {
        ApiResponse::notFound($message);
    } elseif (strpos($message, 'own account') !== false) {
        ApiResponse::forbidden($message);
    } else {
        ApiResponse::validationError(['error' => $message]);
    }
} catch (CompanyAccessDeniedException $e) {
    ApiResponse::forbidden($e->getMessage());
} catch (Exception $e) {
    error_log("Users API Error: " . $e->getMessage());
    ApiResponse::serverError('Failed to delete user');
}
