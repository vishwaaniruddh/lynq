<?php
/**
 * Settings API - Update Setting
 * PUT /api/settings/update.php
 * 
 * Updates a specific setting value with validation and audit logging
 * 
 * Request Body: { key: string, value: mixed }
 * 
 * Response: { success: bool, data: { setting: {} }, message: string }
 * 
 * **Validates: Requirements 1.2, 1.3, 1.4, 1.5, 4.1, 4.2, 4.3, 5.1**
 */

require_once __DIR__ . '/../../config/autoload.php';
require_once __DIR__ . '/../ApiResponse.php';
require_once __DIR__ . '/../../middleware/ApiAuthMiddleware.php';
require_once __DIR__ . '/../../middleware/CSRFMiddleware.php';

// Handle CORS
ApiResponse::setCorsHeaders();
ApiResponse::handlePreflight();

// Only allow PUT
if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    ApiResponse::methodNotAllowed(['PUT']);
}

try {
    $authMiddleware = new ApiAuthMiddleware();
    $csrfMiddleware = new CSRFMiddleware();
    
    // Check rate limiting
    $authMiddleware->checkRateLimit();
    
    // Require authentication and system.manage permission with session re-verification
    $user = $authMiddleware->requirePermission('system.manage', true);
    
    // Validate CSRF token with enhanced error handling
    try {
        $csrfMiddleware->validateToken();
    } catch (Exception $e) {
        ApiResponse::forbidden('CSRF token validation failed: ' . $e->getMessage());
    }
    
    // Get request body
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        ApiResponse::validationError(['request' => 'Invalid JSON in request body'], 'Invalid request format');
    }
    
    // Validate required fields
    $key = isset($input['key']) ? trim($input['key']) : null;
    $value = $input['value'] ?? null;
    
    if (empty($key)) {
        ApiResponse::validationError(['key' => 'Setting key is required'], 'Missing required parameter');
    }
    
    $settingsService = new SettingsService();
    $systemSettingModel = new SystemSetting();
    
    try {
        // Update the setting
        $success = $settingsService->updateSetting($key, $value, $user['id']);
        
        if ($success) {
            // Get the updated setting to return
            $updatedSetting = $systemSettingModel->findByKey($key);
            
            // Log API access
            $authMiddleware->logApiAccess($user['id'], '/api/settings/update', 'PUT', [
                'key' => $key,
                'value_type' => gettype($value)
            ]);
            
            ApiResponse::success([
                'setting' => $updatedSetting
            ], "Setting '{$key}' updated successfully");
        } else {
            ApiResponse::serverError('Failed to update setting');
        }
        
    } catch (InvalidArgumentException $e) {
        // Handle validation errors
        if (strpos($e->getMessage(), 'not found') !== false) {
            ApiResponse::notFound($e->getMessage());
        } else {
            ApiResponse::validationError(['value' => $e->getMessage()], 'Validation failed');
        }
    }
    
} catch (Exception $e) {
    error_log("Settings Update API Error: " . $e->getMessage());
    
    // Check if it's a permission error
    if (strpos($e->getMessage(), 'permission') !== false || strpos($e->getMessage(), 'Authentication') !== false) {
        // Error already handled by middleware
        exit;
    }
    
    ApiResponse::serverError('Failed to update setting');
}