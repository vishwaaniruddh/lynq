<?php
/**
 * Configuration Start API Endpoint
 * POST /api/configuration/configuration_start.php - Start a configuration session
 * 
 * POST Body (JSON):
 * - router_serial_number: Router serial number to configure (required)
 * - ip_master_id: Specific IP_Master ID to use (optional, auto-assigns if not provided)
 * 
 * Response:
 * - lock_id: Session/Lock ID
 * - session_id: Alias for lock_id
 * - expires_at: Lock expiration timestamp
 * - remaining_seconds: Seconds until lock expires
 * - router_serial_number: Router being configured
 * - ip_master: Object with IP details (id, network_ip, router_ip, site_ip, subnet_mask)
 * 
 * **Validates: Requirements 2.1, 3.1, 4.1**
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
    if (!isset($input['router_serial_number']) || trim($input['router_serial_number']) === '') {
        ApiResponse::validationError(
            ['router_serial_number' => ['Router serial number is required']],
            'Validation failed'
        );
    }

    
    $routerSerialNumber = trim($input['router_serial_number']);
    $specificIPMasterId = isset($input['ip_master_id']) ? (int)$input['ip_master_id'] : null;
    
    // Start configuration
    $configurationService = new ConfigurationService();
    $result = $configurationService->startConfiguration(
        $routerSerialNumber,
        $user['id'],
        $specificIPMasterId
    );
    
    // Log API access
    $authMiddleware->logApiAccess($user['id'], '/api/configuration/configuration_start', 'POST', [
        'router_serial_number' => $routerSerialNumber,
        'ip_master_id' => $specificIPMasterId
    ]);
    
    if ($result['success']) {
        ApiResponse::success($result['data'], $result['message'], 201);
    } else {
        // Map error codes to appropriate HTTP status codes
        $statusCode = 400;
        switch ($result['code'] ?? 'ERROR') {
            case 'ROUTER_IN_SESSION':
            case 'ALREADY_CONFIGURED':
            case 'ALREADY_LOCKED':
                $statusCode = 409; // Conflict
                break;
            case 'IP_NOT_FOUND':
            case 'NOT_FOUND':
                $statusCode = 404; // Not Found
                break;
            case 'NO_IP_AVAILABLE':
            case 'IP_NOT_AVAILABLE':
            case 'NOT_AVAILABLE':
                $statusCode = 422; // Unprocessable Entity
                break;
            case 'VALIDATION_ERROR':
                $statusCode = 400; // Bad Request
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
    error_log("Configuration Start API Error: " . $e->getMessage());
    ApiResponse::serverError('Failed to start configuration session');
}
