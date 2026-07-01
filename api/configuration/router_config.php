<?php
/**
 * Router Configuration API Endpoint
 * GET /api/configuration/router_config.php - Get router configuration details
 * 
 * Returns router details with bound IP if configured, or in-progress status if locked.
 * 
 * Query Parameters:
 * - serial_number: Router serial number (required)
 * 
 * Response:
 * - router_serial_number: The queried router serial number
 * - status: 'configured', 'in_progress', or 'unconfigured'
 * - binding: Binding details if configured (id, configured_by, configured_at, notes)
 * - lock: Lock details if in progress (id, locked_by, expires_at, remaining_seconds)
 * - ip_master: IP details if configured or in progress (id, network_ip, router_ip, site_ip, subnet_mask)
 * 
 * **Validates: Requirements 2.4, 5.3**
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
    
    // Only GET method is allowed
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        ApiResponse::methodNotAllowed(['GET']);
    }
    
    // Get query parameters
    $serialNumber = isset($_GET['serial_number']) ? trim($_GET['serial_number']) : '';
    
    // Validate required parameter
    if (empty($serialNumber)) {
        ApiResponse::validationError(
            ['serial_number' => ['Router serial number is required']],
            'Validation failed'
        );
    }
    
    // Get router configuration
    $configurationService = new ConfigurationService();
    $config = $configurationService->getRouterConfiguration($serialNumber);
    
    // Log API access
    $authMiddleware->logApiAccess($user['id'], '/api/configuration/router_config', 'GET', [
        'serial_number' => $serialNumber
    ]);
    
    if ($config === null) {
        // Router not found in any state
        ApiResponse::success([
            'router_serial_number' => $serialNumber,
            'status' => 'unconfigured',
            'binding' => null,
            'lock' => null,
            'ip_master' => null
        ], 'Router configuration retrieved successfully');
    } else {
        ApiResponse::success($config, 'Router configuration retrieved successfully');
    }
    
} catch (Exception $e) {
    error_log("Router Config API Error: " . $e->getMessage());
    ApiResponse::serverError('Failed to retrieve router configuration');
}
