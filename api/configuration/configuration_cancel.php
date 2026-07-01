<?php
/**
 * Configuration Cancel API Endpoint
 * POST /api/configuration/configuration_cancel.php - Cancel a configuration session
 * 
 * POST Body (JSON):
 * - lock_id: Lock/Session ID to cancel (required)
 * 
 * Response:
 * - lock_id: Cancelled lock ID
 * - router_serial_number: Router that was being configured
 * - ip_master_id: IP_Master that was released
 * 
 * **Validates: Requirements 4.5**
 */

require_once __DIR__ . '/../../config/autoload.php';
require_once __DIR__ . '/../ApiResponse.php';
require_once __DIR__ . '/../../middleware/ApiAuthMiddleware.php';
require_once __DIR__ . '/../../services/ConfigurationService.php';

// Handle CORS
ApiResponse::setCorsHeaders();
ApiResponse::handlePreflight();

try {
    $authMiddleware = new ApiAuthMiddleware();
    
    // Check rate limiting
    $authMiddleware->checkRateLimit();
    
    // Require ADV user access for configuration operations
    $user = $authMiddleware->requireAdvUser();
    
    // Only POST method is allowed
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        ApiResponse::methodNotAllowed(['POST']);
    }
    
    // Get POST data
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Fallback to form data if JSON is empty
    if (empty($input)) {
        $input = $_POST;
    }
    
    // Validate required fields
    if (!isset($input['lock_id']) || !is_numeric($input['lock_id'])) {
        ApiResponse::validationError(
            ['lock_id' => ['Lock ID is required and must be numeric']],
            'Validation failed'
        );
    }

    
    $lockId = (int)$input['lock_id'];
    $forceUnlock = isset($input['force']) && ($input['force'] === true || $input['force'] === 'true' || $input['force'] === 1 || $input['force'] === '1');
    
    // Cancel configuration
    $configurationService = new ConfigurationService();
    
    // If force unlock is requested, use admin force cancel
    if ($forceUnlock) {
        $result = $configurationService->forceCancelConfiguration(
            $lockId,
            $user['id']
        );
    } else {
        $result = $configurationService->cancelConfiguration(
            $lockId,
            $user['id']
        );
    }
    
    // Log API access
    $authMiddleware->logApiAccess($user['id'], '/api/configuration/configuration_cancel', 'POST', [
        'lock_id' => $lockId
    ]);
    
    if ($result['success']) {
        ApiResponse::success($result['data'], $result['message']);
    } else {
        // Map error codes to appropriate HTTP status codes
        $statusCode = 400;
        switch ($result['code'] ?? 'ERROR') {
            case 'SESSION_NOT_FOUND':
            case 'NOT_FOUND':
                $statusCode = 404; // Not Found
                break;
            case 'SESSION_INACTIVE':
                $statusCode = 410; // Gone (session already inactive)
                break;
            case 'UNAUTHORIZED':
                $statusCode = 403; // Forbidden
                break;
            case 'RELEASE_ERROR':
                $statusCode = 500; // Server Error
                break;
            default:
                $statusCode = 400;
        }
        
        ApiResponse::error(
            $result['code'] ?? 'ERROR',
            $result['message'],
            $statusCode,
            $result['data'] ?? null
        );
    }
    
} catch (Exception $e) {
    error_log("Configuration Cancel API Error: " . $e->getMessage());
    ApiResponse::serverError('Failed to cancel configuration session');
}
