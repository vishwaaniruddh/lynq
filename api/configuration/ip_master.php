<?php
/**
 * IP Master API Endpoint
 * GET /api/configuration/ip_master.php - List IP_Master records with pagination, search, filters
 * POST /api/configuration/ip_master.php - Create new IP_Master record
 * 
 * Query Parameters (GET):
 * - search: Search term for IP addresses (optional)
 * - status: Filter by status (available, locked, configured) (optional)
 * - page: Page number (default: 1)
 * - limit: Results per page (default: 20, max: 100)
 * - export: Set to 1 for export mode (returns all filtered records)
 * 
 * POST Body (JSON):
 * - network_ip: Network IP address (required)
 * - router_ip: Router IP address (required)
 * - site_ip: Site IP address (required)
 * - subnet_mask: Subnet mask (required)
 * 
 * **Validates: Requirements 1.1, 1.3**
 */

require_once __DIR__ . '/../../config/autoload.php';
require_once __DIR__ . '/../ApiResponse.php';
require_once __DIR__ . '/../../middleware/ApiAuthMiddleware.php';
require_once __DIR__ . '/../../services/IPMasterService.php';

// Handle CORS
ApiResponse::setCorsHeaders();
ApiResponse::handlePreflight();

try {
    $authMiddleware = new ApiAuthMiddleware();
    
    // Check rate limiting
    $authMiddleware->checkRateLimit();
    
    // Require ADV user access for all IP configuration operations
    $user = $authMiddleware->requireAdvUser();
    
    $ipMasterService = new IPMasterService();
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // GET: List IP_Master records with filters
        handleGetRequest($ipMasterService, $authMiddleware, $user);
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // POST: Create new IP_Master record
        handlePostRequest($ipMasterService, $authMiddleware, $user);
    } else {
        ApiResponse::methodNotAllowed(['GET', 'POST']);
    }
    
} catch (Exception $e) {
    error_log("IP Master API Error: " . $e->getMessage());
    ApiResponse::serverError('Failed to process request');
}

/**
 * Handle GET request - List IP_Master records
 * 
 * Requirements: 1.3 - Display IP combinations with status
 */
function handleGetRequest($ipMasterService, $authMiddleware, $user) {
    // Get query parameters
    $search = isset($_GET['search']) ? trim($_GET['search']) : null;
    $status = isset($_GET['status']) ? trim($_GET['status']) : null;
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = min(100, max(1, (int)($_GET['limit'] ?? 20)));
    $export = isset($_GET['export']) && $_GET['export'] == '1';
    
    // Validate status if provided
    if ($status !== null && $status !== '') {
        $validStatuses = ['available', 'locked', 'configured'];
        if (!in_array($status, $validStatuses)) {
            ApiResponse::validationError(
                ['status' => ['Invalid status. Valid values: available, locked, configured']],
                'Invalid status filter'
            );
        }
    }
    
    // Build filters
    $filters = [
        'page' => $page,
        'limit' => $limit
    ];
    
    if ($search !== null && $search !== '') {
        $filters['search'] = $search;
    }
    
    if ($status !== null && $status !== '') {
        $filters['status'] = $status;
    }
    
    // Handle export mode
    if ($export) {
        $ipMasters = $ipMasterService->export($filters);
        
        // Log API access
        $authMiddleware->logApiAccess($user['id'], '/api/configuration/ip_master', 'GET', [
            'action' => 'export',
            'filters' => $filters
        ]);
        
        ApiResponse::success([
            'ip_masters' => $ipMasters,
            'total' => count($ipMasters)
        ], 'IP_Master records exported successfully');
        return;
    }
    
    // Get paginated list
    $result = $ipMasterService->getAll($filters);
    
    // Log API access
    $authMiddleware->logApiAccess($user['id'], '/api/configuration/ip_master', 'GET', [
        'search' => $search,
        'status' => $status,
        'page' => $page
    ]);
    
    // Format response to include all required fields per Requirements 1.3
    $formattedData = array_map(function($ip) {
        return [
            'id' => (int)$ip['id'],
            'network_ip' => $ip['network_ip'],
            'router_ip' => $ip['router_ip'],
            'site_ip' => $ip['site_ip'],
            'subnet_mask' => $ip['subnet_mask'],
            'status' => $ip['status'],
            'created_by' => isset($ip['created_by']) ? (int)$ip['created_by'] : null,
            'created_at' => $ip['created_at'] ?? null,
            'updated_at' => $ip['updated_at'] ?? null
        ];
    }, $result['data']);
    
    ApiResponse::success([
        'ip_masters' => $formattedData,
        'pagination' => [
            'page' => $result['page'],
            'limit' => $result['limit'],
            'total' => $result['total'],
            'total_pages' => $result['totalPages']
        ]
    ], 'IP_Master records retrieved successfully');
}

/**
 * Handle POST request - Create new IP_Master record
 * 
 * Requirements: 1.1 - Create IP_Master with validation
 */
function handlePostRequest($ipMasterService, $authMiddleware, $user) {
    // Get POST data
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Fallback to form data if JSON is empty
    if (empty($input)) {
        $input = $_POST;
    }
    
    // Validate required fields
    $requiredFields = ['network_ip', 'router_ip', 'site_ip', 'subnet_mask'];
    $errors = [];
    
    foreach ($requiredFields as $field) {
        if (!isset($input[$field]) || trim($input[$field]) === '') {
            $errors[$field] = [ucfirst(str_replace('_', ' ', $field)) . ' is required'];
        }
    }
    
    if (!empty($errors)) {
        ApiResponse::validationError($errors, 'Validation failed');
    }
    
    // Prepare data
    $data = [
        'network_ip' => trim($input['network_ip']),
        'router_ip' => trim($input['router_ip']),
        'site_ip' => trim($input['site_ip']),
        'subnet_mask' => trim($input['subnet_mask'])
    ];
    
    // Create IP_Master
    $result = $ipMasterService->create($data, $user['id']);
    
    // Log API access
    $authMiddleware->logApiAccess($user['id'], '/api/configuration/ip_master', 'POST', [
        'action' => 'create',
        'network_ip' => $data['network_ip'],
        'router_ip' => $data['router_ip'],
        'site_ip' => $data['site_ip'],
        'subnet_mask' => $data['subnet_mask']
    ]);
    
    if ($result['success']) {
        // Format response to include all required fields
        $ipMaster = $result['data'];
        $formattedData = [
            'id' => (int)$ipMaster['id'],
            'network_ip' => $ipMaster['network_ip'],
            'router_ip' => $ipMaster['router_ip'],
            'site_ip' => $ipMaster['site_ip'],
            'subnet_mask' => $ipMaster['subnet_mask'],
            'status' => $ipMaster['status'],
            'created_by' => isset($ipMaster['created_by']) ? (int)$ipMaster['created_by'] : null,
            'created_at' => $ipMaster['created_at'] ?? null,
            'updated_at' => $ipMaster['updated_at'] ?? null
        ];
        
        ApiResponse::success($formattedData, $result['message'], 201);
    } else {
        if ($result['code'] === 'DUPLICATE_ERROR') {
            ApiResponse::error('DUPLICATE_ERROR', $result['message'], 409, $result['errors'] ?? null);
        } else {
            ApiResponse::validationError($result['errors'] ?? [], $result['message']);
        }
    }
}
