<?php
/**
 * Settings API - Get Settings by Categories
 * GET /api/settings/categories
 * 
 * Returns all system settings grouped by category with proper ordering
 * Includes current and default values for each setting
 * 
 * Response: { success: bool, data: { categories: { [category]: [settings] } } }
 * 
 * **Validates: Requirements 1.1, 2.1, 2.2, 3.1, 6.1, 6.2**
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
    
    // Require authentication and system.manage permission with session re-verification
    $user = $authMiddleware->requirePermission('system.manage', true);
    
    $settingsService = new SettingsService();
    
    // Get all settings grouped by category
    $settingsByCategory = $settingsService->getSettingsByCategory();
    
    // Log API access
    $authMiddleware->logApiAccess($user['id'], '/api/settings/categories', 'GET', []);
    
    ApiResponse::success([
        'categories' => $settingsByCategory
    ], 'Settings categories retrieved successfully');
    
} catch (Exception $e) {
    error_log("Settings Categories API Error: " . $e->getMessage());
    
    // Check if it's a permission error
    if (strpos($e->getMessage(), 'permission') !== false || strpos($e->getMessage(), 'Authentication') !== false) {
        // Error already handled by middleware
        exit;
    }
    
    ApiResponse::serverError('Failed to retrieve settings categories');
}