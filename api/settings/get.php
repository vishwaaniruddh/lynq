<?php
/**
 * Settings API - Get Single Setting
 * GET /api/settings/get.php?key={key}
 * 
 * Retrieves a specific setting by key with current and default values
 * 
 * Query Parameters:
 * - key: Setting key (required)
 * 
 * Response: { success: bool, data: { setting: {} } }
 * 
 * **Validates: Requirements 3.1, 6.1, 6.2**
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
    
    // Get and validate key parameter
    $key = isset($_GET['key']) ? trim($_GET['key']) : null;
    
    if (empty($key)) {
        ApiResponse::validationError(['key' => 'Setting key is required'], 'Missing required parameter');
    }
    
    $settingsService = new SettingsService();
    $systemSettingModel = new SystemSetting();
    
    // Get the setting by key
    $setting = $systemSettingModel->findByKey($key);
    
    if (!$setting) {
        ApiResponse::notFound("Setting with key '{$key}' not found");
    }
    
    // Log API access
    $authMiddleware->logApiAccess($user['id'], '/api/settings/get', 'GET', ['key' => $key]);
    
    ApiResponse::success([
        'setting' => $setting
    ], 'Setting retrieved successfully');
    
} catch (Exception $e) {
    error_log("Settings Get API Error: " . $e->getMessage());
    
    // Check if it's a permission error
    if (strpos($e->getMessage(), 'permission') !== false || strpos($e->getMessage(), 'Authentication') !== false) {
        // Error already handled by middleware
        exit;
    }
    
    ApiResponse::serverError('Failed to retrieve setting');
}