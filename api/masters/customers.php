<?php
/**
 * Customers API Endpoint
 * GET /api/masters/customers.php - List customers with pagination, search, filters
 * POST /api/masters/customers.php - Create, update, delete customer records
 * 
 * Query Parameters (GET):
 * - search: Search term for customer name/email (optional)
 * - status: Filter by status (0=inactive, 1=active) (optional)
 * - page: Page number (default: 1)
 * - limit: Results per page (default: 20, max: 100)
 * - id: Get single customer by ID (optional)
 * - export: Set to 1 for export mode (returns all filtered records)
 * 
 * POST Actions:
 * - action=create: Create new customer (requires: name, email, optional: phone, address, city, state, country, postal_code, status)
 * - action=update: Update customer (requires: id, optional: name, email, phone, address, city, state, country, postal_code, status)
 * - action=delete: Delete customer (requires: id)
 * 
 * **Validates: Requirements 2.1, 2.2, 2.3, 2.4, 8.4**
 */

require_once __DIR__ . '/../../config/autoload.php';
require_once __DIR__ . '/../ApiResponse.php';
require_once __DIR__ . '/../../middleware/ApiAuthMiddleware.php';
require_once __DIR__ . '/../../middleware/MasterModuleMiddleware.php';
require_once __DIR__ . '/../../services/CustomerService.php';

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
    
    $customerService = new CustomerService();
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Check view permission for GET requests
        if (!$masterMiddleware->hasPermission('customers', 'view', $user['id'])) {
            ApiResponse::forbidden('You do not have permission to view customer records');
        }
        // GET: List customers with filters or get single customer
        handleGetRequest($customerService, $authMiddleware, $user);
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // POST: Create, update, or delete customer
        handlePostRequest($customerService, $authMiddleware, $masterMiddleware, $user);
    } else {
        ApiResponse::methodNotAllowed(['GET', 'POST']);
    }
    
} catch (Exception $e) {
    error_log("Customers API Error: " . $e->getMessage());
    ApiResponse::serverError('Failed to process request');
}

/**
 * Handle GET request - List customers or get single customer
 */
function handleGetRequest($customerService, $authMiddleware, $user) {
    // Check if requesting single customer
    if (isset($_GET['id']) && (int)$_GET['id'] > 0) {
        $customer = $customerService->getById((int)$_GET['id']);
        
        if (!$customer) {
            ApiResponse::notFound('Customer not found');
        }
        
        // Log API access
        $authMiddleware->logApiAccess($user['id'], '/api/masters/customers', 'GET', [
            'action' => 'view',
            'id' => $_GET['id']
        ]);
        
        ApiResponse::success(['customer' => $customer], 'Customer retrieved successfully');
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
        $filters['status'] = (int)$status;
    }
    
    // Handle export mode
    if ($export) {
        $customers = $customerService->export($filters);
        
        // Log API access
        $authMiddleware->logApiAccess($user['id'], '/api/masters/customers', 'GET', [
            'action' => 'export',
            'filters' => $filters
        ]);
        
        ApiResponse::success([
            'customers' => $customers,
            'total' => count($customers)
        ], 'Customers exported successfully');
        return;
    }
    
    // Get paginated list
    $result = $customerService->getAll($filters);
    
    // Log API access
    $authMiddleware->logApiAccess($user['id'], '/api/masters/customers', 'GET', [
        'search' => $search,
        'status' => $status,
        'page' => $page
    ]);
    
    ApiResponse::success([
        'customers' => $result['data'],
        'pagination' => [
            'page' => $result['page'],
            'limit' => $result['limit'],
            'total' => $result['total'],
            'total_pages' => $result['totalPages']
        ]
    ], 'Customers retrieved successfully');
}

/**
 * Handle POST request - Create, update, or delete customer
 */
function handlePostRequest($customerService, $authMiddleware, $masterMiddleware, $user) {
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
            if (!$masterMiddleware->hasPermission('customers', 'create', $user['id'])) {
                ApiResponse::forbidden('You do not have permission to create customer records');
            }
            handleCreate($customerService, $authMiddleware, $user, $input);
            break;
            
        case 'update':
            // Check edit permission
            if (!$masterMiddleware->hasPermission('customers', 'edit', $user['id'])) {
                ApiResponse::forbidden('You do not have permission to edit customer records');
            }
            handleUpdate($customerService, $authMiddleware, $user, $input);
            break;
            
        case 'delete':
            // Check delete permission
            if (!$masterMiddleware->hasPermission('customers', 'delete', $user['id'])) {
                ApiResponse::forbidden('You do not have permission to delete customer records');
            }
            handleDelete($customerService, $authMiddleware, $user, $input);
            break;
            
        default:
            ApiResponse::validationError(
                ['action' => ['Invalid or missing action. Valid actions: create, update, delete']],
                'Invalid action'
            );
    }
}

/**
 * Handle create customer action
 */
function handleCreate($customerService, $authMiddleware, $user, $input) {
    // Validate required fields
    $errors = [];
    
    if (!isset($input['name']) || trim($input['name']) === '') {
        $errors['name'] = ['Customer name is required'];
    }
    
    if (!isset($input['email']) || trim($input['email']) === '') {
        $errors['email'] = ['Customer email is required'];
    }
    
    if (!empty($errors)) {
        ApiResponse::validationError($errors, 'Validation failed');
    }
    
    $data = [
        'name' => $input['name'],
        'email' => $input['email']
    ];
    
    // Optional fields
    $optionalFields = ['phone', 'address', 'city', 'state', 'country', 'postal_code', 'status', 'country_id', 'state_id', 'city_id'];
    foreach ($optionalFields as $field) {
        if (isset($input[$field])) {
            $data[$field] = $input[$field];
        }
    }
    
    $result = $customerService->create($data, $user['id']);
    
    // Log API access
    $authMiddleware->logApiAccess($user['id'], '/api/masters/customers', 'POST', [
        'action' => 'create',
        'name' => $input['name'],
        'email' => $input['email']
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
 * Handle update customer action
 */
function handleUpdate($customerService, $authMiddleware, $user, $input) {
    // Validate required fields
    if (!isset($input['id']) || (int)$input['id'] <= 0) {
        ApiResponse::validationError(
            ['id' => ['Customer ID is required']],
            'Validation failed'
        );
    }
    
    $id = (int)$input['id'];
    $data = [];
    
    // Collect update fields
    $updateFields = ['name', 'email', 'phone', 'address', 'city', 'state', 'country', 'postal_code', 'status', 'country_id', 'state_id', 'city_id'];
    foreach ($updateFields as $field) {
        if (isset($input[$field])) {
            $data[$field] = $input[$field];
        }
    }
    
    if (empty($data)) {
        ApiResponse::validationError(
            ['data' => ['No data provided for update']],
            'Validation failed'
        );
    }
    
    $result = $customerService->update($id, $data, $user['id']);
    
    // Log API access
    $authMiddleware->logApiAccess($user['id'], '/api/masters/customers', 'POST', [
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
 * Handle delete customer action
 */
function handleDelete($customerService, $authMiddleware, $user, $input) {
    // Validate required fields
    if (!isset($input['id']) || (int)$input['id'] <= 0) {
        ApiResponse::validationError(
            ['id' => ['Customer ID is required']],
            'Validation failed'
        );
    }
    
    $id = (int)$input['id'];
    
    $result = $customerService->delete($id, $user['id']);
    
    // Log API access
    $authMiddleware->logApiAccess($user['id'], '/api/masters/customers', 'POST', [
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
