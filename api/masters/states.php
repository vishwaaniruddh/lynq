<?php
/**
 * States API Endpoint
 * GET /api/masters/states.php - List states with filters and relationships
 * POST /api/masters/states.php - Create, update, delete state records
 * 
 * Query Parameters (GET):
 * - search: Search term for state name (optional)
 * - status: Filter by status (active/inactive) (optional)
 * - country_id: Filter by country (optional)
 * - zone_id: Filter by zone (optional)
 * - page: Page number (default: 1)
 * - limit: Results per page (default: 20, max: 100)
 * - id: Get single state by ID (optional)
 * - by_country: Get states by country ID (for cascading dropdowns)
 * - active_only: Set to 1 to get only active states (for dropdowns)
 * - export: Set to 1 for export mode (returns all filtered records)
 * 
 * POST Actions:
 * - action=create: Create new state (requires: name, country_id, optional: zone_id, status)
 * - action=update: Update state (requires: id, optional: name, country_id, zone_id, status)
 * - action=delete: Delete state (requires: id)
 * 
 * **Validates: Requirements 4.1, 4.2, 4.3, 4.4, 8.4**
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
        // GET: List states with filters or get single state
        handleGetRequest($locationService, $authMiddleware, $user);
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // POST: Create, update, or delete state
        handlePostRequest($locationService, $authMiddleware, $masterMiddleware, $user);
    } else {
        ApiResponse::methodNotAllowed(['GET', 'POST']);
    }
    
} catch (Exception $e) {
    error_log("States API Error: " . $e->getMessage());
    ApiResponse::serverError('Failed to process request');
}

/**
 * Handle GET request - List states or get single state
 */
function handleGetRequest($locationService, $authMiddleware, $user) {
    // Check if requesting single state
    if (isset($_GET['id']) && (int)$_GET['id'] > 0) {
        $state = $locationService->getStateById((int)$_GET['id']);
        
        if (!$state) {
            ApiResponse::notFound('State not found');
        }
        
        // Log API access
        $authMiddleware->logApiAccess($user['id'], '/api/masters/states', 'GET', [
            'action' => 'view',
            'id' => $_GET['id']
        ]);
        
        ApiResponse::success(['state' => $state], 'State retrieved successfully');
        return;
    }
    
    // Check if requesting states by country (for cascading dropdowns)
    // Supports both by_country and country_id parameters with active_only filter
    $countryIdForDropdown = null;
    if (isset($_GET['by_country']) && (int)$_GET['by_country'] > 0) {
        $countryIdForDropdown = (int)$_GET['by_country'];
    } elseif (isset($_GET['country_id']) && (int)$_GET['country_id'] > 0 && isset($_GET['active_only']) && $_GET['active_only'] == '1') {
        $countryIdForDropdown = (int)$_GET['country_id'];
    }
    
    if ($countryIdForDropdown !== null) {
        $states = $locationService->getStatesByCountry($countryIdForDropdown);
        
        // Log API access
        $authMiddleware->logApiAccess($user['id'], '/api/masters/states', 'GET', [
            'action' => 'by_country',
            'country_id' => $countryIdForDropdown
        ]);
        
        ApiResponse::success([
            'states' => $states,
            'total' => count($states)
        ], 'States retrieved successfully');
        return;
    }
    
    // Check if requesting active states only (for dropdowns) - without country filter
    if (isset($_GET['active_only']) && $_GET['active_only'] == '1') {
        $states = $locationService->getActiveStates();
        
        // Log API access
        $authMiddleware->logApiAccess($user['id'], '/api/masters/states', 'GET', [
            'action' => 'active_list'
        ]);
        
        ApiResponse::success([
            'states' => $states,
            'total' => count($states)
        ], 'Active states retrieved successfully');
        return;
    }
    
    // Get query parameters
    $search = isset($_GET['search']) ? trim($_GET['search']) : null;
    $status = isset($_GET['status']) ? $_GET['status'] : null;
    $countryId = isset($_GET['country_id']) ? (int)$_GET['country_id'] : null;
    $zoneId = isset($_GET['zone_id']) ? (int)$_GET['zone_id'] : null;
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
    
    if ($countryId !== null && $countryId > 0) {
        $filters['country_id'] = $countryId;
    }
    
    if ($zoneId !== null && $zoneId > 0) {
        $filters['zone_id'] = $zoneId;
    }
    
    // Handle export mode
    if ($export) {
        $states = $locationService->exportStates($filters);
        
        // Log API access
        $authMiddleware->logApiAccess($user['id'], '/api/masters/states', 'GET', [
            'action' => 'export',
            'filters' => $filters
        ]);
        
        ApiResponse::success([
            'states' => $states,
            'total' => count($states)
        ], 'States exported successfully');
        return;
    }
    
    // Get paginated list
    $result = $locationService->getAllStates($filters);
    
    // Log API access
    $authMiddleware->logApiAccess($user['id'], '/api/masters/states', 'GET', [
        'search' => $search,
        'status' => $status,
        'country_id' => $countryId,
        'zone_id' => $zoneId,
        'page' => $page
    ]);
    
    ApiResponse::success([
        'states' => $result['data'],
        'pagination' => [
            'page' => $result['page'],
            'limit' => $result['limit'],
            'total' => $result['total'],
            'total_pages' => $result['totalPages']
        ]
    ], 'States retrieved successfully');
}

/**
 * Handle POST request - Create, update, or delete state
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
 * Handle create state action
 */
function handleCreate($locationService, $authMiddleware, $user, $input) {
    // Validate required fields
    $errors = [];
    
    if (!isset($input['name']) || trim($input['name']) === '') {
        $errors['name'] = ['State name is required'];
    }
    
    if (!isset($input['country_id']) || (int)$input['country_id'] <= 0) {
        $errors['country_id'] = ['Country is required'];
    }
    
    if (!empty($errors)) {
        ApiResponse::validationError($errors, 'Validation failed');
    }
    
    $data = [
        'name' => $input['name'],
        'country_id' => (int)$input['country_id']
    ];
    
    if (isset($input['zone_id']) && $input['zone_id'] !== '' && $input['zone_id'] !== null) {
        $data['zone_id'] = (int)$input['zone_id'];
    }
    
    if (isset($input['status'])) {
        $data['status'] = $input['status'];
    }
    
    $result = $locationService->createState($data, $user['id']);
    
    // Log API access
    $authMiddleware->logApiAccess($user['id'], '/api/masters/states', 'POST', [
        'action' => 'create',
        'name' => $input['name'],
        'country_id' => $input['country_id']
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
 * Handle update state action
 */
function handleUpdate($locationService, $authMiddleware, $user, $input) {
    // Validate required fields
    if (!isset($input['id']) || (int)$input['id'] <= 0) {
        ApiResponse::validationError(
            ['id' => ['State ID is required']],
            'Validation failed'
        );
    }
    
    $id = (int)$input['id'];
    $data = [];
    
    if (isset($input['name'])) {
        $data['name'] = $input['name'];
    }
    
    if (isset($input['country_id'])) {
        $data['country_id'] = (int)$input['country_id'];
    }
    
    if (array_key_exists('zone_id', $input)) {
        $data['zone_id'] = $input['zone_id'];
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
    
    $result = $locationService->updateState($id, $data, $user['id']);
    
    // Log API access
    $authMiddleware->logApiAccess($user['id'], '/api/masters/states', 'POST', [
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
 * Handle delete state action
 */
function handleDelete($locationService, $authMiddleware, $user, $input) {
    // Validate required fields
    if (!isset($input['id']) || (int)$input['id'] <= 0) {
        ApiResponse::validationError(
            ['id' => ['State ID is required']],
            'Validation failed'
        );
    }
    
    $id = (int)$input['id'];
    
    $result = $locationService->deleteState($id, $user['id']);
    
    // Log API access
    $authMiddleware->logApiAccess($user['id'], '/api/masters/states', 'POST', [
        'action' => 'delete',
        'id' => $id
    ]);
    
    if ($result['success']) {
        ApiResponse::success(null, $result['message']);
    } else {
        if ($result['code'] === 'NOT_FOUND') {
            ApiResponse::notFound($result['message']);
        } elseif ($result['code'] === 'REFERENTIAL_INTEGRITY_ERROR') {
            ApiResponse::error(
                'REFERENTIAL_INTEGRITY_ERROR', 
                $result['message'], 
                409, 
                ['dependencies' => $result['dependencies'] ?? null]
            );
        } else {
            ApiResponse::serverError($result['message']);
        }
    }
}
