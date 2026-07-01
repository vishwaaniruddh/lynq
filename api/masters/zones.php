<?php
/**
 * Zones API Endpoint
 * GET /api/masters/zones.php - List zones with child counts
 * POST /api/masters/zones.php - Create, update, delete zone records
 * 
 * Query Parameters (GET):
 * - search: Search term for zone name (optional)
 * - status: Filter by status (active/inactive) (optional)
 * - page: Page number (default: 1)
 * - limit: Results per page (default: 20, max: 100)
 * - id: Get single zone by ID with details (optional)
 * - active_only: Set to 1 to get only active zones (for dropdowns)
 * - export: Set to 1 for export mode (returns all filtered records)
 * 
 * POST Actions:
 * - action=create: Create new zone (requires: name, optional: status)
 * - action=update: Update zone (requires: id, optional: name, status)
 * - action=delete: Delete zone (requires: id) - cascades to set zone_id NULL in states/cities
 * 
 * **Validates: Requirements 5.1, 5.2, 5.3, 5.4, 5.5, 8.4**
 */

require_once __DIR__ . '/../../config/autoload.php';
require_once __DIR__ . '/../ApiResponse.php';
require_once __DIR__ . '/../../middleware/ApiAuthMiddleware.php';
require_once __DIR__ . '/../../middleware/MasterModuleMiddleware.php';
require_once __DIR__ . '/../../services/LocationService.php';

// Handle CORS
ApiResponse::setCorsHeaders();
ApiResponse::handlePreflight();

try {
    $authMiddleware = new ApiAuthMiddleware();
    $masterMiddleware = new MasterModuleMiddleware();
    
    // Check rate limiting
    $authMiddleware->checkRateLimit();
    
    // Require ADV user access for all master module operations
    $user = $authMiddleware->requireAdvUser();
    
    $locationService = new LocationService();
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Check view permission for GET requests
        if (!$masterMiddleware->hasPermission('locations', 'view', $user['id'])) {
            ApiResponse::forbidden('You do not have permission to view location records');
        }
        // GET: List zones with filters or get single zone
        handleGetRequest($locationService, $authMiddleware, $user);
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // POST: Create, update, or delete zone
        handlePostRequest($locationService, $authMiddleware, $masterMiddleware, $user);
    } else {
        ApiResponse::methodNotAllowed(['GET', 'POST']);
    }
    
} catch (Exception $e) {
    error_log("Zones API Error: " . $e->getMessage());
    ApiResponse::serverError('Failed to process request');
}

/**
 * Handle GET request - List zones or get single zone
 */
function handleGetRequest($locationService, $authMiddleware, $user) {
    // Check if requesting single zone with details
    if (isset($_GET['id']) && (int)$_GET['id'] > 0) {
        $zone = $locationService->getZoneById((int)$_GET['id']);
        
        if (!$zone) {
            ApiResponse::notFound('Zone not found');
        }
        
        // Log API access
        $authMiddleware->logApiAccess($user['id'], '/api/masters/zones', 'GET', [
            'action' => 'view',
            'id' => $_GET['id']
        ]);
        
        ApiResponse::success(['zone' => $zone], 'Zone retrieved successfully');
        return;
    }
    
    // Check if requesting active zones only (for dropdowns)
    if (isset($_GET['active_only']) && $_GET['active_only'] == '1') {
        $zones = $locationService->getActiveZones();
        
        // Log API access
        $authMiddleware->logApiAccess($user['id'], '/api/masters/zones', 'GET', [
            'action' => 'active_list'
        ]);
        
        ApiResponse::success([
            'zones' => $zones,
            'total' => count($zones)
        ], 'Active zones retrieved successfully');
        return;
    }
    
    // Get query parameters
    $search = isset($_GET['search']) ? trim($_GET['search']) : null;
    $status = isset($_GET['status']) ? $_GET['status'] : null;
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = min(100, max(1, (int)($_GET['limit'] ?? 20)));
    $export = isset($_GET['export']) && $_GET['export'] == '1';
    
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
        $zones = $locationService->exportZones($filters);
        
        // Log API access
        $authMiddleware->logApiAccess($user['id'], '/api/masters/zones', 'GET', [
            'action' => 'export',
            'filters' => $filters
        ]);
        
        ApiResponse::success([
            'zones' => $zones,
            'total' => count($zones)
        ], 'Zones exported successfully');
        return;
    }
    
    // Get paginated list
    $result = $locationService->getAllZones($filters);
    
    // Log API access
    $authMiddleware->logApiAccess($user['id'], '/api/masters/zones', 'GET', [
        'search' => $search,
        'status' => $status,
        'page' => $page
    ]);
    
    ApiResponse::success([
        'zones' => $result['data'],
        'pagination' => [
            'page' => $result['page'],
            'limit' => $result['limit'],
            'total' => $result['total'],
            'total_pages' => $result['totalPages']
        ]
    ], 'Zones retrieved successfully');
}

/**
 * Handle POST request - Create, update, or delete zone
 */
function handlePostRequest($locationService, $authMiddleware, $masterMiddleware, $user) {
    // Get POST data
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Fallback to form data if JSON is empty
    if (empty($input)) {
        $input = $_POST;
    }
    
    $action = $input['action'] ?? '';
    
    switch ($action) {
        case 'create':
            // Check create permission
            if (!$masterMiddleware->hasPermission('locations', 'create', $user['id'])) {
                ApiResponse::forbidden('You do not have permission to create location records');
            }
            handleCreate($locationService, $authMiddleware, $user, $input);
            break;
            
        case 'update':
            // Check edit permission
            if (!$masterMiddleware->hasPermission('locations', 'edit', $user['id'])) {
                ApiResponse::forbidden('You do not have permission to edit location records');
            }
            handleUpdate($locationService, $authMiddleware, $user, $input);
            break;
            
        case 'delete':
            // Check delete permission
            if (!$masterMiddleware->hasPermission('locations', 'delete', $user['id'])) {
                ApiResponse::forbidden('You do not have permission to delete location records');
            }
            handleDelete($locationService, $authMiddleware, $user, $input);
            break;
            
        default:
            ApiResponse::validationError(
                ['action' => ['Invalid or missing action. Valid actions: create, update, delete']],
                'Invalid action'
            );
    }
}

/**
 * Handle create zone action
 */
function handleCreate($locationService, $authMiddleware, $user, $input) {
    // Validate required fields
    if (!isset($input['name']) || trim($input['name']) === '') {
        ApiResponse::validationError(
            ['name' => ['Zone name is required']],
            'Validation failed'
        );
    }
    
    $data = [
        'name' => $input['name']
    ];
    
    if (isset($input['status'])) {
        $data['status'] = $input['status'];
    }
    
    $result = $locationService->createZone($data, $user['id']);
    
    // Log API access
    $authMiddleware->logApiAccess($user['id'], '/api/masters/zones', 'POST', [
        'action' => 'create',
        'name' => $input['name']
    ]);
    
    if ($result['success']) {
        ApiResponse::success($result['data'], $result['message'], 201);
    } else {
        if ($result['code'] === 'DUPLICATE_ERROR') {
            ApiResponse::error('DUPLICATE_ERROR', $result['message'], 409, $result['errors'] ?? null);
        } else {
            ApiResponse::validationError($result['errors'] ?? [], $result['message']);
        }
    }
}

/**
 * Handle update zone action
 */
function handleUpdate($locationService, $authMiddleware, $user, $input) {
    // Validate required fields
    if (!isset($input['id']) || (int)$input['id'] <= 0) {
        ApiResponse::validationError(
            ['id' => ['Zone ID is required']],
            'Validation failed'
        );
    }
    
    $id = (int)$input['id'];
    $data = [];
    
    if (isset($input['name'])) {
        $data['name'] = $input['name'];
    }
    
    if (isset($input['status'])) {
        $data['status'] = $input['status'];
    }
    
    if (empty($data)) {
        ApiResponse::validationError(
            ['data' => ['No data provided for update']],
            'Validation failed'
        );
    }
    
    $result = $locationService->updateZone($id, $data, $user['id']);
    
    // Log API access
    $authMiddleware->logApiAccess($user['id'], '/api/masters/zones', 'POST', [
        'action' => 'update',
        'id' => $id,
        'changes' => array_keys($data)
    ]);
    
    if ($result['success']) {
        ApiResponse::success($result['data'], $result['message']);
    } else {
        if ($result['code'] === 'NOT_FOUND') {
            ApiResponse::notFound($result['message']);
        } elseif ($result['code'] === 'DUPLICATE_ERROR') {
            ApiResponse::error('DUPLICATE_ERROR', $result['message'], 409, $result['errors'] ?? null);
        } else {
            ApiResponse::validationError($result['errors'] ?? [], $result['message']);
        }
    }
}

/**
 * Handle delete zone action
 * Note: Zone deletion cascades - sets zone_id to NULL in states and cities
 */
function handleDelete($locationService, $authMiddleware, $user, $input) {
    // Validate required fields
    if (!isset($input['id']) || (int)$input['id'] <= 0) {
        ApiResponse::validationError(
            ['id' => ['Zone ID is required']],
            'Validation failed'
        );
    }
    
    $id = (int)$input['id'];
    
    $result = $locationService->deleteZone($id, $user['id']);
    
    // Log API access
    $authMiddleware->logApiAccess($user['id'], '/api/masters/zones', 'POST', [
        'action' => 'delete',
        'id' => $id
    ]);
    
    if ($result['success']) {
        // Include cascade info in response
        $responseData = null;
        if (isset($result['cascade_info'])) {
            $responseData = ['cascade_info' => $result['cascade_info']];
        }
        ApiResponse::success($responseData, $result['message']);
    } else {
        if ($result['code'] === 'NOT_FOUND') {
            ApiResponse::notFound($result['message']);
        } else {
            ApiResponse::serverError($result['message']);
        }
    }
}
