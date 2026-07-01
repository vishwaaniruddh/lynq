<?php
/**
 * Couriers API Endpoint
 * GET /api/masters/couriers.php - List couriers with pagination, search, filters
 * POST /api/masters/couriers.php - Create, update, delete courier records
 * 
 * Query Parameters (GET):
 * - search: Search term for courier name (optional)
 * - status: Filter by status (0=inactive, 1=active) (optional)
 * - page: Page number (default: 1)
 * - limit: Results per page (default: 20, max: 100)
 * - export: Set to 1 for export mode (returns all filtered records)
 * 
 * POST Actions:
 * - action=create: Create new courier (requires: name, optional: status)
 * - action=update: Update courier (requires: id, optional: name, status)
 * - action=delete: Delete courier (requires: id)
 * 
 * **Validates: Requirements 2.1, 2.2, 2.3, 2.4, 5.3**
 */

require_once __DIR__ . '/../../config/autoload.php';
require_once __DIR__ . '/../ApiResponse.php';
require_once __DIR__ . '/../../middleware/ApiAuthMiddleware.php';
require_once __DIR__ . '/../../middleware/MasterModuleMiddleware.php';
require_once __DIR__ . '/../../services/CourierService.php';

// Handle CORS
ApiResponse::setCorsHeaders();
ApiResponse::handlePreflight();

try {
    $authMiddleware = new ApiAuthMiddleware();
    $masterMiddleware = new MasterModuleMiddleware();
    
    // Check rate limiting
    $authMiddleware->checkRateLimit();
    
    // Require ADV user access for all courier module operations
    $user = $authMiddleware->requireAdvUser();
    
    $courierService = new CourierService();
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Check view permission for GET requests
        if (!$masterMiddleware->hasPermission('couriers', 'view', $user['id'])) {
            ApiResponse::forbidden('You do not have permission to view courier records');
        }
        // GET: List couriers with filters
        handleGetRequest($courierService, $authMiddleware, $user);
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // POST: Create, update, or delete courier
        handlePostRequest($courierService, $authMiddleware, $masterMiddleware, $user);
    } else {
        ApiResponse::methodNotAllowed(['GET', 'POST']);
    }
    
} catch (Exception $e) {
    error_log("Couriers API Error: " . $e->getMessage());
    ApiResponse::serverError('Failed to process request');
}

/**
 * Handle GET request - List couriers
 */
function handleGetRequest($courierService, $authMiddleware, $user) {
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
        $filters['status'] = (int)$status;
    }
    
    // Handle export mode
    if ($export) {
        $couriers = $courierService->export($filters);
        
        // Log API access
        $authMiddleware->logApiAccess($user['id'], '/api/masters/couriers', 'GET', [
            'action' => 'export',
            'filters' => $filters
        ]);
        
        ApiResponse::success([
            'couriers' => $couriers,
            'total' => count($couriers)
        ], 'Couriers exported successfully');
        return;
    }
    
    // Get paginated list
    $result = $courierService->getAll($filters);
    
    // Log API access
    $authMiddleware->logApiAccess($user['id'], '/api/masters/couriers', 'GET', [
        'search' => $search,
        'status' => $status,
        'page' => $page
    ]);
    
    ApiResponse::success([
        'couriers' => $result['data'],
        'pagination' => [
            'page' => $result['page'],
            'limit' => $result['limit'],
            'total' => $result['total'],
            'total_pages' => $result['totalPages']
        ]
    ], 'Couriers retrieved successfully');
}

/**
 * Handle POST request - Create, update, or delete courier
 */
function handlePostRequest($courierService, $authMiddleware, $masterMiddleware, $user) {
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
            if (!$masterMiddleware->hasPermission('couriers', 'create', $user['id'])) {
                ApiResponse::forbidden('You do not have permission to create courier records');
            }
            handleCreate($courierService, $authMiddleware, $user, $input);
            break;
            
        case 'update':
            // Check edit permission
            if (!$masterMiddleware->hasPermission('couriers', 'edit', $user['id'])) {
                ApiResponse::forbidden('You do not have permission to edit courier records');
            }
            handleUpdate($courierService, $authMiddleware, $user, $input);
            break;
            
        case 'delete':
            // Check delete permission
            if (!$masterMiddleware->hasPermission('couriers', 'delete', $user['id'])) {
                ApiResponse::forbidden('You do not have permission to delete courier records');
            }
            handleDelete($courierService, $authMiddleware, $user, $input);
            break;
            
        default:
            ApiResponse::validationError(
                ['action' => ['Invalid or missing action. Valid actions: create, update, delete']],
                'Invalid action'
            );
    }
}

/**
 * Handle create courier action
 */
function handleCreate($courierService, $authMiddleware, $user, $input) {
    // Validate required fields
    if (!isset($input['name']) || trim($input['name']) === '') {
        ApiResponse::validationError(
            ['name' => ['Courier name is required']],
            'Validation failed'
        );
    }
    
    $data = [
        'name' => $input['name']
    ];
    
    if (isset($input['status'])) {
        $data['status'] = $input['status'];
    }
    
    $result = $courierService->create($data, $user['id']);
    
    // Log API access
    $authMiddleware->logApiAccess($user['id'], '/api/masters/couriers', 'POST', [
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
 * Handle update courier action
 */
function handleUpdate($courierService, $authMiddleware, $user, $input) {
    // Validate required fields
    if (!isset($input['id']) || (int)$input['id'] <= 0) {
        ApiResponse::validationError(
            ['id' => ['Courier ID is required']],
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
    
    $result = $courierService->update($id, $data, $user['id']);
    
    // Log API access
    $authMiddleware->logApiAccess($user['id'], '/api/masters/couriers', 'POST', [
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
 * Handle delete courier action
 */
function handleDelete($courierService, $authMiddleware, $user, $input) {
    // Validate required fields
    if (!isset($input['id']) || (int)$input['id'] <= 0) {
        ApiResponse::validationError(
            ['id' => ['Courier ID is required']],
            'Validation failed'
        );
    }
    
    $id = (int)$input['id'];
    
    $result = $courierService->delete($id, $user['id']);
    
    // Log API access
    $authMiddleware->logApiAccess($user['id'], '/api/masters/couriers', 'POST', [
        'action' => 'delete',
        'id' => $id
    ]);
    
    if ($result['success']) {
        ApiResponse::success(null, $result['message']);
    } else {
        if ($result['code'] === 'NOT_FOUND') {
            ApiResponse::notFound($result['message']);
        } else {
            ApiResponse::serverError($result['message']);
        }
    }
}
