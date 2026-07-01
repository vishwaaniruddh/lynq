<?php
/**
 * Users API - Get Single User
 * GET /api/users/show.php?id={id}
 * 
 * Retrieves a single user by ID with company isolation
 * 
 * Query Parameters:
 * - id: User ID (required)
 * - include_managed_lhos: Include LHOs managed by this user (optional, default: 0)
 * 
 * Response: { success: bool, data: { user: {}, managed_lhos?: [] } }
 * 
 * **Validates: Requirements 7.1, 7.2, 7.3**
 * **Validates: Requirements 3.1, 3.2, 3.3 (LHO Manager Assignment)**
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
    
    // Require authentication and users.read permission
    $currentUser = $authMiddleware->requirePermission('users.read');
    
    // Get user ID from query
    $userId = isset($_GET['id']) ? (int)$_GET['id'] : null;
    $includeManagedLhos = isset($_GET['include_managed_lhos']) && $_GET['include_managed_lhos'] == '1';
    
    if (!$userId) {
        ApiResponse::validationError(['id' => 'User ID is required']);
    }
    
    $userService = new UserService();
    
    // Get user with company isolation
    $user = $userService->getUser($userId, $currentUser['id']);
    
    if (!$user) {
        ApiResponse::notFound('User not found');
    }
    
    // Sanitize user data
    unset($user['password_hash']);
    unset($user['failed_login_attempts']);
    unset($user['locked_until']);
    
    $responseData = ['user' => $user];
    
    // Include managed LHOs if requested
    // Requirements: 3.1, 3.2, 3.3 - Display LHOs managed by user
    if ($includeManagedLhos) {
        $locationService = new LocationService();
        $managedLhos = $locationService->getLhosByManager($userId);
        $responseData['managed_lhos'] = $managedLhos;
    }
    
    // Log API access
    $authMiddleware->logApiAccess($currentUser['id'], '/api/users/show', 'GET', ['id' => $userId]);
    
    ApiResponse::success($responseData, 'User retrieved successfully');
    
} catch (Exception $e) {
    error_log("Users API Error: " . $e->getMessage());
    ApiResponse::serverError('Failed to retrieve user');
}
