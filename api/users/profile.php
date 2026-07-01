<?php
/**
 * User Profile API
 * GET /api/users/profile.php - Get current user's profile
 * PUT /api/users/profile.php - Update current user's profile
 * 
 * Requirements:
 * - GET: 1.1, 1.2, 2.1, 3.1, 4.1, 5.1, 6.1, 7.1
 * - PUT: 2.2, 2.3, 3.2, 4.2, 4.3, 5.2, 7.2, 7.3, 8.1, 9.3
 * 
 * **Feature: user-profile-enhancement**
 */

require_once __DIR__ . '/../../config/autoload.php';
require_once __DIR__ . '/../ApiResponse.php';
require_once __DIR__ . '/../../middleware/ApiAuthMiddleware.php';
require_once __DIR__ . '/../../services/ProfileService.php';

// Handle CORS
ApiResponse::setCorsHeaders();
ApiResponse::handlePreflight();

// Only allow GET and PUT
if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'PUT'])) {
    ApiResponse::methodNotAllowed(['GET', 'PUT']);
}

try {
    $authMiddleware = new ApiAuthMiddleware();
    
    // Check rate limiting
    $authMiddleware->checkRateLimit();
    
    // Require authentication (no specific permission needed for own profile)
    $currentUser = $authMiddleware->requireAuth();
    
    $profileService = new ProfileService();
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // GET: Retrieve current user's profile
        // Requirements: 1.1, 1.2, 2.1, 3.1, 4.1, 5.1, 6.1, 7.1
        
        $result = $profileService->getProfile($currentUser['id']);
        
        if (!$result['success']) {
            if ($result['code'] === 'NOT_FOUND') {
                ApiResponse::notFound($result['message']);
            }
            ApiResponse::serverError($result['message']);
        }
        
        // Log API access
        $authMiddleware->logApiAccess($currentUser['id'], '/api/users/profile', 'GET');
        
        ApiResponse::success($result['data'], 'Profile retrieved successfully');
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'PUT') {
        // PUT: Update current user's profile
        // Requirements: 2.2, 2.3, 3.2, 4.2, 4.3, 5.2, 7.2, 7.3, 8.1, 9.3
        
        // Get JSON input
        $input = json_decode(file_get_contents('php://input'), true);
        
        if ($input === null) {
            ApiResponse::validationError(['body' => 'Invalid JSON input']);
        }
        
        // Update profile
        $result = $profileService->updateProfile($currentUser['id'], $input);
        
        if (!$result['success']) {
            if ($result['code'] === 'VALIDATION_ERROR') {
                ApiResponse::validationError($result['fields'] ?? [], $result['message']);
            }
            if ($result['code'] === 'NOT_FOUND') {
                ApiResponse::notFound($result['message']);
            }
            ApiResponse::serverError($result['message']);
        }
        
        // Log API access
        $authMiddleware->logApiAccess($currentUser['id'], '/api/users/profile', 'PUT', array_keys($input));
        
        ApiResponse::success($result['data'], $result['message'] ?? 'Profile updated successfully');
    }
    
} catch (Exception $e) {
    error_log("Profile API Error: " . $e->getMessage());
    ApiResponse::serverError('Failed to process profile request');
}
