<?php
/**
 * Router Available API Endpoint
 * GET /api/configuration/router_available.php - List available routers for configuration
 * 
 * Returns routers from inventory that are:
 * - Not currently in an active configuration session (locked)
 * - Not already configured with an IP
 * 
 * Query Parameters:
 * - search: Optional search term for serial number filtering
 * - limit: Optional limit for results (default: 100)
 * 
 * Response:
 * - routers: Array of available routers with serial_number, model, status
 * - total: Total count of available routers
 * 
 * **Validates: Requirements 2.1, 2.2**
 */

require_once __DIR__ . '/../../config/autoload.php';
require_once __DIR__ . '/../ApiResponse.php';
require_once __DIR__ . '/../../middleware/ApiAuthMiddleware.php';
require_once __DIR__ . '/../../services/ConfigurationService.php';
require_once __DIR__ . '/../../repositories/IPLockRepository.php';
require_once __DIR__ . '/../../models/RouterIPBinding.php';

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
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
    $limit = max(1, min(500, $limit)); // Clamp between 1 and 500
    
    // Get routers that are in active configuration sessions
    $lockRepository = new IPLockRepository();
    $activeLocks = $lockRepository->getActiveLocks();
    $lockedRouterSerials = array_column($activeLocks, 'router_serial_number');
    
    // Get routers that are already configured
    $bindingModel = new RouterIPBinding();
    $activeBindings = $bindingModel->getActiveBindingsWithDetails();
    $configuredRouterSerials = array_column($activeBindings, 'router_serial_number');
    
    // Combine excluded serials
    $excludedSerials = array_unique(array_merge($lockedRouterSerials, $configuredRouterSerials));
    
    // Query inventory for available routers
    // This queries the assets table for routers not in active session or configured
    // Assets with status 'in_stock' are available for configuration
    $db = DatabaseConfig::getInstance();
    
    $sql = "SELECT DISTINCT a.serial_number, a.status, a.product_id, p.name as product_name, a.created_at
            FROM assets a
            LEFT JOIN products p ON a.product_id = p.id
            WHERE a.serial_number IS NOT NULL 
            AND a.serial_number != ''
            AND a.status = 'in_stock'";
    
    $params = [];
    $types = '';
    
    // Exclude locked and configured routers
    if (!empty($excludedSerials)) {
        $placeholders = implode(',', array_fill(0, count($excludedSerials), '?'));
        $sql .= " AND a.serial_number NOT IN ($placeholders)";
        $params = $excludedSerials;
        $types = str_repeat('s', count($excludedSerials));
    }
    
    // Apply search filter if provided
    if (!empty($search)) {
        $sql .= " AND a.serial_number LIKE ?";
        $params[] = '%' . $search . '%';
        $types .= 's';
    }
    
    $sql .= " ORDER BY a.serial_number ASC LIMIT ?";
    $params[] = $limit;
    $types .= 'i';
    
    $routers = $db->getResults($sql, $params, $types);
    
    // If assets table doesn't exist or is empty, return empty array
    if ($routers === false) {
        $routers = [];
    }
    
    // Format response
    $formattedRouters = array_map(function($router) {
        return [
            'serial_number' => $router['serial_number'],
            'product_name' => $router['product_name'] ?? null,
            'product_id' => $router['product_id'] ?? null,
            'status' => $router['status'],
            'available_for_configuration' => true
        ];
    }, $routers);
    
    // Log API access
    $authMiddleware->logApiAccess($user['id'], '/api/configuration/router_available', 'GET', [
        'search' => $search,
        'limit' => $limit
    ]);
    
    ApiResponse::success([
        'routers' => $formattedRouters,
        'total' => count($formattedRouters),
        'excluded' => [
            'locked_count' => count($lockedRouterSerials),
            'configured_count' => count($configuredRouterSerials)
        ]
    ], 'Available routers retrieved successfully');
    
} catch (Exception $e) {
    error_log("Router Available API Error: " . $e->getMessage());
    ApiResponse::serverError('Failed to retrieve available routers');
}
