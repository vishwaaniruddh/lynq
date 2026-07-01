<?php
/**
 * ADV Users List API Endpoint
 * GET /api/users/adv-list.php - List active ADV users for manager dropdown
 * 
 * Returns active ADV users for use in manager selection dropdowns.
 * Requires ADV user authentication.
 * 
 * Requirements: 1.1
 */

require_once __DIR__ . '/../../config/autoload.php';
require_once __DIR__ . '/../ApiResponse.php';
require_once __DIR__ . '/../../middleware/ApiAuthMiddleware.php';
require_once __DIR__ . '/../../services/LocationService.php';

// Handle CORS
ApiResponse::setCorsHeaders();
ApiResponse::handlePreflight();

try {
    $authMiddleware = new ApiAuthMiddleware();
    
    // Check rate limiting
    $authMiddleware->checkRateLimit();
    
    // Require ADV user access
    $user = $authMiddleware->requireAdvUser();
    
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        ApiResponse::methodNotAllowed(['GET']);
    }
    
    $locationService = new LocationService();
    
    // Get active ADV users for dropdown
    $advUsers = $locationService->getActiveAdvUsers();
    
    // Log API access
    $authMiddleware->logApiAccess($user['id'], '/api/users/adv-list', 'GET', [
        'action' => 'list_adv_users'
    ]);
    
    ApiResponse::success([
        'users' => $advUsers,
        'total' => count($advUsers)
    ], 'ADV users retrieved successfully');
    
} catch (Exception $e) {
    error_log("ADV Users List API Error: " . $e->getMessage());
    ApiResponse::serverError('Failed to retrieve ADV users');
}
