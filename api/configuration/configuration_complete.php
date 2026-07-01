<?php
/**
 * Configuration Complete API Endpoint
 * POST /api/configuration/configuration_complete.php - Complete a configuration session
 * 
 * POST Body (JSON):
 * - lock_id: Lock/Session ID to complete (required)
 * - notes: Optional notes about the configuration
 * 
 * Response:
 * - binding_id: Created binding ID
 * - router_serial_number: Router that was configured
 * - ip_master: Object with IP details
 * - configured_by: User ID who completed the configuration
 * - configured_at: Configuration timestamp
 * - notes: Configuration notes
 * 
 * **Validates: Requirements 5.1, 5.2**
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
    $notes = isset($input['notes']) ? trim($input['notes']) : null;
    
    // Complete configuration
    $configurationService = new ConfigurationService();
    $result = $configurationService->completeConfiguration(
        $lockId,
        $user['id'],
        $notes
    );
    
    // Log API access
    $authMiddleware->logApiAccess($user['id'], '/api/configuration/configuration_complete', 'POST', [
        'lock_id' => $lockId,
        'has_notes' => !empty($notes)
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
                $statusCode = 410; // Gone (session expired)
                break;
            case 'UNAUTHORIZED':
                $statusCode = 403; // Forbidden
                break;
            case 'BINDING_ERROR':
            case 'COMPLETION_ERROR':
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
    error_log("Configuration Complete API Error: " . $e->getMessage());
    ApiResponse::serverError('Failed to complete configuration session');
}
