<?php
/**
 * User Profile Revisions API
 * GET /api/users/profile-revisions.php - Get profile revision history
 * 
 * Returns user's revision history sorted by date DESC
 * 
 * Requirements: 8.3, 8.4
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

// Only allow GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    ApiResponse::methodNotAllowed(['GET']);
}

try {
    $authMiddleware = new ApiAuthMiddleware();
    
    // Check rate limiting
    $authMiddleware->checkRateLimit();
    
    // Require authentication (no specific permission needed for own profile)
    $currentUser = $authMiddleware->requireAuth();
    
    $profileService = new ProfileService();
    
    // Get revision history for current user
    // Requirement 8.3: Return revisions sorted by date DESC
    // Requirement 8.4: Show changed fields, old values, new values, and timestamp
    $result = $profileService->getRevisionHistory($currentUser['id']);
    
    if (!$result['success']) {
        ApiResponse::serverError($result['message']);
    }
    
    // Log API access
    $authMiddleware->logApiAccess($currentUser['id'], '/api/users/profile-revisions', 'GET');
    
    ApiResponse::success([
        'revisions' => $result['data'],
        'total' => count($result['data'])
    ], 'Revision history retrieved successfully');
    
} catch (Exception $e) {
    error_log("Profile Revisions API Error: " . $e->getMessage());
    ApiResponse::serverError('Failed to retrieve revision history');
}
