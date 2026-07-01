<?php
/**
 * Banks API Endpoint
 * GET /api/masters/banks.php - List banks with pagination, search, filters
 * POST /api/masters/banks.php - Create, update, delete bank records
 * 
 * Query Parameters (GET):
 * - search: Search term for bank name (optional)
 * - status: Filter by status (0=inactive, 1=active) (optional)
 * - page: Page number (default: 1)
 * - limit: Results per page (default: 20, max: 100)
 * - export: Set to 1 for export mode (returns all filtered records)
 * 
 * POST Actions:
 * - action=create: Create new bank (requires: name, optional: status)
 * - action=update: Update bank (requires: id, optional: name, status)
 * - action=delete: Delete bank (requires: id)
 * 
 * **Validates: Requirements 1.1, 1.2, 1.3, 1.4, 8.4**
 */

require_once __DIR__ . '/../../config/autoload.php';
require_once __DIR__ . '/../ApiResponse.php';
require_once __DIR__ . '/../../middleware/ApiAuthMiddleware.php';
require_once __DIR__ . '/../../middleware/MasterModuleMiddleware.php';
require_once __DIR__ . '/../../services/BankService.php';

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
    
    $bankService = new BankService();
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Check view permission for GET requests
        if (!$masterMiddleware->hasPermission('banks', 'view', $user['id'])) {
            ApiResponse::forbidden('You do not have permission to view bank records');
        }
        // GET: List banks with filters
        handleGetRequest($bankService, $authMiddleware, $user);
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // POST: Create, update, or delete bank
        handlePostRequest($bankService, $authMiddleware, $masterMiddleware, $user);
    } else {
        ApiResponse::methodNotAllowed(['GET', 'POST']);
    }
    
} catch (Exception $e) {
    error_log("Banks API Error: " . $e->getMessage());
    ApiResponse::serverError('Failed to process request');
}

/**
 * Handle GET request - List banks
 */
function handleGetRequest($bankService, $authMiddleware, $user) {
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
        $banks = $bankService->export($filters);
        
        // Log API access
        $authMiddleware->logApiAccess($user['id'], '/api/masters/banks', 'GET', [
            'action' => 'export',
            'filters' => $filters
        ]);
        
        ApiResponse::success([
            'banks' => $banks,
            'total' => count($banks)
        ], 'Banks exported successfully');
        return;
    }
    
    // Get paginated list
    $result = $bankService->getAll($filters);
    
    // Log API access
    $authMiddleware->logApiAccess($user['id'], '/api/masters/banks', 'GET', [
        'search' => $search,
        'status' => $status,
        'page' => $page
    ]);
    
    ApiResponse::success([
        'banks' => $result['data'],
        'pagination' => [
            'page' => $result['page'],
            'limit' => $result['limit'],
            'total' => $result['total'],
            'total_pages' => $result['totalPages']
        ]
    ], 'Banks retrieved successfully');
}

/**
 * Handle POST request - Create, update, or delete bank
 */
function handlePostRequest($bankService, $authMiddleware, $masterMiddleware, $user) {
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
            if (!$masterMiddleware->hasPermission('banks', 'create', $user['id'])) {
                ApiResponse::forbidden('You do not have permission to create bank records');
            }
            handleCreate($bankService, $authMiddleware, $user, $input);
            break;
            
        case 'update':
            // Check edit permission
            if (!$masterMiddleware->hasPermission('banks', 'edit', $user['id'])) {
                ApiResponse::forbidden('You do not have permission to edit bank records');
            }
            handleUpdate($bankService, $authMiddleware, $user, $input);
            break;
            
        case 'delete':
            // Check delete permission
            if (!$masterMiddleware->hasPermission('banks', 'delete', $user['id'])) {
                ApiResponse::forbidden('You do not have permission to delete bank records');
            }
            handleDelete($bankService, $authMiddleware, $user, $input);
            break;
            
        default:
            ApiResponse::validationError(
                ['action' => ['Invalid or missing action. Valid actions: create, update, delete']],
                'Invalid action'
            );
    }
}

/**
 * Handle create bank action
 */
function handleCreate($bankService, $authMiddleware, $user, $input) {
    // Validate required fields
    if (!isset($input['name']) || trim($input['name']) === '') {
        ApiResponse::validationError(
            ['name' => ['Bank name is required']],
            'Validation failed'
        );
    }
    
    $data = [
        'name' => $input['name']
    ];
    
    if (isset($input['status'])) {
        $data['status'] = $input['status'];
    }
    
    $result = $bankService->create($data, $user['id']);
    
    // Log API access
    $authMiddleware->logApiAccess($user['id'], '/api/masters/banks', 'POST', [
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
 * Handle update bank action
 */
function handleUpdate($bankService, $authMiddleware, $user, $input) {
    // Validate required fields
    if (!isset($input['id']) || (int)$input['id'] <= 0) {
        ApiResponse::validationError(
            ['id' => ['Bank ID is required']],
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
    
    $result = $bankService->update($id, $data, $user['id']);
    
    // Log API access
    $authMiddleware->logApiAccess($user['id'], '/api/masters/banks', 'POST', [
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
 * Handle delete bank action
 */
function handleDelete($bankService, $authMiddleware, $user, $input) {
    // Validate required fields
    if (!isset($input['id']) || (int)$input['id'] <= 0) {
        ApiResponse::validationError(
            ['id' => ['Bank ID is required']],
            'Validation failed'
        );
    }
    
    $id = (int)$input['id'];
    
    $result = $bankService->delete($id, $user['id']);
    
    // Log API access
    $authMiddleware->logApiAccess($user['id'], '/api/masters/banks', 'POST', [
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
