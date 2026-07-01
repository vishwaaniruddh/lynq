<?php
/**
 * Cities API Endpoint
 * GET /api/masters/cities.php - List cities with filters and relationships
 * POST /api/masters/cities.php - Create, update, delete city records
 * 
 * Query Parameters (GET):
 * - search: Search term for city name (optional)
 * - status: Filter by status (active/inactive) (optional)
 * - country_id: Filter by country (optional)
 * - state_id: Filter by state (optional)
 * - zone_id: Filter by zone (optional)
 * - page: Page number (default: 1)
 * - limit: Results per page (default: 20, max: 100)
 * - id: Get single city by ID (optional)
 * - by_state: Get cities by state ID (for cascading dropdowns)
 * - active_only: Set to 1 to get only active cities (for dropdowns)
 * - export: Set to 1 for export mode (returns all filtered records)
 * 
 * POST Actions:
 * - action=create: Create new city (requires: name, state_id, optional: zone_id, status)
 * - action=update: Update city (requires: id, optional: name, state_id, zone_id, status)
 * - action=delete: Delete city (requires: id) - soft delete
 * 
 * **Validates: Requirements 6.1, 6.2, 6.3, 6.4, 6.5, 8.4**
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
        // GET: List cities with filters or get single city
        handleGetRequest($locationService, $authMiddleware, $user);
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // POST: Create, update, or delete city
        handlePostRequest($locationService, $authMiddleware, $masterMiddleware, $user);
    } else {
        ApiResponse::methodNotAllowed(['GET', 'POST']);
    }
    
} catch (Exception $e) {
    error_log("Cities API Error: " . $e->getMessage());
    ApiResponse::serverError('Failed to process request');
}

/**
 * Handle GET request - List cities or get single city
 */
function handleGetRequest($locationService, $authMiddleware, $user) {
    // Check if requesting single city
    if (isset($_GET['id']) && (int)$_GET['id'] > 0) {
        $city = $locationService->getCityById((int)$_GET['id']);
        
        if (!$city) {
            ApiResponse::notFound('City not found');
        }
        
        // Log API access
        $authMiddleware->logApiAccess($user['id'], '/api/masters/cities', 'GET', [
            'action' => 'view',
            'id' => $_GET['id']
        ]);
        
        ApiResponse::success(['city' => $city], 'City retrieved successfully');
        return;
    }
    
    // Check if requesting cities by state (for cascading dropdowns)
    // Supports both by_state and state_id parameters with active_only filter
    $stateIdForDropdown = null;
    if (isset($_GET['by_state']) && (int)$_GET['by_state'] > 0) {
        $stateIdForDropdown = (int)$_GET['by_state'];
    } elseif (isset($_GET['state_id']) && (int)$_GET['state_id'] > 0 && isset($_GET['active_only']) && $_GET['active_only'] == '1') {
        $stateIdForDropdown = (int)$_GET['state_id'];
    }
    
    if ($stateIdForDropdown !== null) {
        $cities = $locationService->getCitiesByState($stateIdForDropdown);
        
        // Log API access
        $authMiddleware->logApiAccess($user['id'], '/api/masters/cities', 'GET', [
            'action' => 'by_state',
            'state_id' => $stateIdForDropdown
        ]);
        
        ApiResponse::success([
            'cities' => $cities,
            'total' => count($cities)
        ], 'Cities retrieved successfully');
        return;
    }
    
    // Check if requesting active cities only (for dropdowns) - without state filter
    if (isset($_GET['active_only']) && $_GET['active_only'] == '1') {
        $cities = $locationService->getActiveCities();
        
        // Log API access
        $authMiddleware->logApiAccess($user['id'], '/api/masters/cities', 'GET', [
            'action' => 'active_list'
        ]);
        
        ApiResponse::success([
            'cities' => $cities,
            'total' => count($cities)
        ], 'Active cities retrieved successfully');
        return;
    }
    
    // Get query parameters
    $search = isset($_GET['search']) ? trim($_GET['search']) : null;
    $status = isset($_GET['status']) ? $_GET['status'] : null;
    $countryId = isset($_GET['country_id']) ? (int)$_GET['country_id'] : null;
    $stateId = isset($_GET['state_id']) ? (int)$_GET['state_id'] : null;
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
    
    if ($stateId !== null && $stateId > 0) {
        $filters['state_id'] = $stateId;
    }
    
    if ($zoneId !== null && $zoneId > 0) {
        $filters['zone_id'] = $zoneId;
    }
    
    // Handle export mode
    if ($export) {
        $cities = $locationService->exportCities($filters);
        
        // Log API access
        $authMiddleware->logApiAccess($user['id'], '/api/masters/cities', 'GET', [
            'action' => 'export',
            'filters' => $filters
        ]);
        
        ApiResponse::success([
            'cities' => $cities,
            'total' => count($cities)
        ], 'Cities exported successfully');
        return;
    }
    
    // Get paginated list
    $result = $locationService->getAllCities($filters);
    
    // Log API access
    $authMiddleware->logApiAccess($user['id'], '/api/masters/cities', 'GET', [
        'search' => $search,
        'status' => $status,
        'country_id' => $countryId,
        'state_id' => $stateId,
        'zone_id' => $zoneId,
        'page' => $page
    ]);
    
    ApiResponse::success([
        'cities' => $result['data'],
        'pagination' => [
            'page' => $result['page'],
            'limit' => $result['limit'],
            'total' => $result['total'],
            'total_pages' => $result['totalPages']
        ]
    ], 'Cities retrieved successfully');
}

/**
 * Handle POST request - Create, update, or delete city
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
 * Handle create city action
 */
function handleCreate($locationService, $authMiddleware, $user, $input) {
    // Validate required fields
    $errors = [];
    
    if (!isset($input['name']) || trim($input['name']) === '') {
        $errors['name'] = ['City name is required'];
    }
    
    if (!isset($input['state_id']) || (int)$input['state_id'] <= 0) {
        $errors['state_id'] = ['State is required'];
    }
    
    if (!empty($errors)) {
        ApiResponse::validationError($errors, 'Validation failed');
    }
    
    $data = [
        'name' => $input['name'],
        'state_id' => (int)$input['state_id']
    ];
    
    if (isset($input['zone_id']) && $input['zone_id'] !== '' && $input['zone_id'] !== null) {
        $data['zone_id'] = (int)$input['zone_id'];
    }
    
    if (isset($input['status'])) {
        $data['status'] = $input['status'];
    }
    
    $result = $locationService->createCity($data, $user['id']);
    
    // Log API access
    $authMiddleware->logApiAccess($user['id'], '/api/masters/cities', 'POST', [
        'action' => 'create',
        'name' => $input['name'],
        'state_id' => $input['state_id']
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
 * Handle update city action
 */
function handleUpdate($locationService, $authMiddleware, $user, $input) {
    // Validate required fields
    if (!isset($input['id']) || (int)$input['id'] <= 0) {
        ApiResponse::validationError(
            ['id' => ['City ID is required']],
            'Validation failed'
        );
    }
    
    $id = (int)$input['id'];
    $data = [];
    
    if (isset($input['name'])) {
        $data['name'] = $input['name'];
    }
    
    if (isset($input['state_id'])) {
        $data['state_id'] = (int)$input['state_id'];
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
    
    $result = $locationService->updateCity($id, $data, $user['id']);
    
    // Log API access
    $authMiddleware->logApiAccess($user['id'], '/api/masters/cities', 'POST', [
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
 * Handle delete city action (soft delete)
 */
function handleDelete($locationService, $authMiddleware, $user, $input) {
    // Validate required fields
    if (!isset($input['id']) || (int)$input['id'] <= 0) {
        ApiResponse::validationError(
            ['id' => ['City ID is required']],
            'Validation failed'
        );
    }
    
    $id = (int)$input['id'];
    
    $result = $locationService->deleteCity($id, $user['id']);
    
    // Log API access
    $authMiddleware->logApiAccess($user['id'], '/api/masters/cities', 'POST', [
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
