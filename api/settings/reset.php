<?php
/**
 * Settings API - Reset Setting to Default
 * POST /api/settings/reset.php
 * 
 * Resets a specific setting to its default value with confirmation workflow
 * 
 * Request Body: { key: string, confirmed?: boolean }
 * 
 * Response: { success: bool, data: { setting?: {}, requires_confirmation?: bool }, message: string }
 * 
 * **Validates: Requirements 3.3, 1.5**
 */

require_once __DIR__ . '/../../config/autoload.php';
require_once __DIR__ . '/../ApiResponse.php';
require_once __DIR__ . '/../../middleware/ApiAuthMiddleware.php';
require_once __DIR__ . '/../../middleware/CSRFMiddleware.php';

// Handle CORS
ApiResponse::setCorsHeaders();
ApiResponse::handlePreflight();

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ApiResponse::methodNotAllowed(['POST']);
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
    $confirmed = isset($input['confirmed']) ? (bool)$input['confirmed'] : false;
    
    if (empty($key)) {
        ApiResponse::validationError(['key' => 'Setting key is required'], 'Missing required parameter');
    }
    
    $settingsService = new SettingsService();
    $systemSettingModel = new SystemSetting();
    
    try {
        // Attempt to reset the setting
        $result = $settingsService->resetSetting($key, $user['id'], $confirmed);
        
        if ($result['requires_confirmation']) {
            // Return confirmation requirement
            ApiResponse::success([
                'requires_confirmation' => true,
                'current_value' => $result['current_value'],
                'default_value' => $result['default_value']
            ], $result['message']);
        } else if ($result['success']) {
            // Get the reset setting to return
            $resetSetting = $systemSettingModel->findByKey($key);
            
            // Log API access
            $authMiddleware->logApiAccess($user['id'], '/api/settings/reset', 'POST', [
                'key' => $key,
                'confirmed' => $confirmed
            ]);
            
            ApiResponse::success([
                'setting' => $resetSetting
            ], $result['message']);
        } else {
            ApiResponse::serverError($result['message']);
        }
        
    } catch (InvalidArgumentException $e) {
        // Handle validation errors
        if (strpos($e->getMessage(), 'not found') !== false) {
            ApiResponse::notFound($e->getMessage());
        } else {
            ApiResponse::validationError(['key' => $e->getMessage()], 'Validation failed');
        }
    }
    
} catch (Exception $e) {
    error_log("Settings Reset API Error: " . $e->getMessage());
    
    // Check if it's a permission error
    if (strpos($e->getMessage(), 'permission') !== false || strpos($e->getMessage(), 'Authentication') !== false) {
        // Error already handled by middleware
        exit;
    }
    
    ApiResponse::serverError('Failed to reset setting');
}