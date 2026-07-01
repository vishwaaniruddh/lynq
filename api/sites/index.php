<?php
/**
 * Sites API Endpoint
 * GET /api/sites/index.php - List sites with pagination, search, filters
 * POST /api/sites/index.php - Create, update, delete site records
 * 
 * Query Parameters (GET):
 * - search: Search term for site name, address, city (optional)
 * - status: Filter by status (active, inactive) (optional)
 * - lho: Filter by LHO (optional)
 * - page: Page number (default: 1)
 * - limit: Results per page (default: 20, max: 100)
 * - export: Set to 1 for export mode (returns all filtered records)
 * 
 * POST Actions:
 * - action=create: Create new site (requires: site_name, lho, city, state, country)
 * - action=update: Update site (requires: id)
 * - action=delete: Delete site (requires: id)
 * 
 * **Validates: Requirements 1.1, 1.4, 1.5, 7.1, 7.2, 7.3**
 */

// Prevent PHP errors from outputting HTML and corrupting JSON responses
ini_set('display_errors', '0');
error_reporting(E_ALL);
ob_start();

require_once __DIR__ . '/../../config/autoload.php';
require_once __DIR__ . '/../ApiResponse.php';
require_once __DIR__ . '/../../middleware/ApiAuthMiddleware.php';
require_once __DIR__ . '/../../services/SiteService.php';

// Discard any output generated during includes (e.g., PHP warnings)
ob_end_clean();

// Handle CORS
ApiResponse::setCorsHeaders();
ApiResponse::handlePreflight();

try {
    $authMiddleware = new ApiAuthMiddleware();
    
    // Check rate limiting
    $authMiddleware->checkRateLimit();
    
    // Require ADV user access for site management
    $user = $authMiddleware->requireAdvUser();
    
    $siteService = new SiteService();
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        handleGetRequest($siteService, $authMiddleware, $user);
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        handlePostRequest($siteService, $authMiddleware, $user);
    } else {
        ApiResponse::methodNotAllowed(['GET', 'POST']);
    }
    
} catch (Exception $e) {
    error_log("Sites API Error: " . $e->getMessage());
    ApiResponse::serverError('Failed to process request');
}

/**
 * Handle GET request - List sites
 */
function handleGetRequest($siteService, $authMiddleware, $user) {
    $search = isset($_GET['search']) ? trim($_GET['search']) : null;
    $status = isset($_GET['status']) ? $_GET['status'] : null;
    $lho = isset($_GET['lho']) ? trim($_GET['lho']) : null;
    $delegation = isset($_GET['delegation']) ? $_GET['delegation'] : null;
    $material = isset($_GET['material']) ? $_GET['material'] : null;
    $installation = isset($_GET['installation']) ? $_GET['installation'] : null;
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = min(100, max(1, (int)($_GET['limit'] ?? 20)));
    $orderBy = $_GET['orderBy'] ?? 'site_name';
    $orderDir = $_GET['orderDir'] ?? 'ASC';
    $export = isset($_GET['export']) && $_GET['export'] == '1';
    
    $filters = [
        'page' => $page,
        'limit' => $limit,
        'orderBy' => $orderBy,
        'orderDir' => $orderDir
    ];
    
    if ($search !== null && $search !== '') {
        $filters['search'] = $search;
    }
    
    if ($status !== null && $status !== '') {
        $filters['status'] = $status;
    }
    
    if ($lho !== null && $lho !== '') {
        $filters['lho'] = $lho;
    }
    
    if ($delegation !== null && $delegation !== '') {
        $filters['delegation'] = $delegation;
    }
    
    if ($material !== null && $material !== '') {
        $filters['material'] = $material;
    }
    
    if ($installation !== null && $installation !== '') {
        $filters['installation'] = $installation;
    }
    
    // Handle export mode
    if ($export) {
        $sites = $siteService->exportSites($user['company_id'], $filters);
        
        $authMiddleware->logApiAccess($user['id'], '/api/sites', 'GET', [
            'action' => 'export',
            'filters' => $filters
        ]);
        
        ApiResponse::success([
            'sites' => $sites,
            'total' => count($sites)
        ], 'Sites exported successfully');
        return;
    }
    
    // Get paginated list
    $result = $siteService->getSitesByCompany($user['company_id'], $filters);
    
    // Get distinct LHOs for filter dropdown
    $lhos = $siteService->getDistinctLHOs($user['company_id']);
    
    // Get site counts by status
    $counts = $siteService->getSiteCountsByStatus($user['company_id']);
    
    $authMiddleware->logApiAccess($user['id'], '/api/sites', 'GET', [
        'search' => $search,
        'status' => $status,
        'lho' => $lho,
        'delegation' => $delegation,
        'material' => $material,
        'installation' => $installation,
        'page' => $page
    ]);
    
    ApiResponse::success([
        'sites' => $result['data'],
        'pagination' => [
            'page' => $result['page'],
            'limit' => $result['limit'],
            'total' => $result['total'],
            'total_pages' => $result['totalPages']
        ],
        'lhos' => $lhos,
        'counts' => $counts
    ], 'Sites retrieved successfully');
}

/**
 * Handle POST request - Create, update, or delete site
 */
function handlePostRequest($siteService, $authMiddleware, $user) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input)) {
        $input = $_POST;
    }
    
    $action = $input['action'] ?? '';
    
    switch ($action) {
        case 'create':
            handleCreate($siteService, $authMiddleware, $user, $input);
            break;
            
        case 'update':
            handleUpdate($siteService, $authMiddleware, $user, $input);
            break;
            
        case 'delete':
            handleDelete($siteService, $authMiddleware, $user, $input);
            break;
            
        case 'get_lhos':
            handleGetLHOs($siteService, $user);
            break;
            
        case 'get_undelegated':
            handleGetUndelegated($siteService, $user);
            break;
            
        default:
            ApiResponse::validationError(
                ['action' => ['Invalid or missing action. Valid actions: create, update, delete, get_lhos, get_undelegated']],
                'Invalid action'
            );
    }
}

/**
 * Handle create site action
 */
function handleCreate($siteService, $authMiddleware, $user, $input) {
    $data = [
        'site_name' => $input['site_name'] ?? '',
        'lho' => $input['lho'] ?? '',
        'bank_name' => $input['bank_name'] ?? null,
        'customer_name' => $input['customer_name'] ?? null,
        'city' => $input['city'] ?? '',
        'state' => $input['state'] ?? '',
        'country' => $input['country'] ?? '',
        'zone' => $input['zone'] ?? null,
        'address' => $input['address'] ?? null,
        'latitude' => isset($input['latitude']) && $input['latitude'] !== '' ? (float)$input['latitude'] : null,
        'longitude' => isset($input['longitude']) && $input['longitude'] !== '' ? (float)$input['longitude'] : null,
        'company_id' => $user['company_id'],
        'status' => $input['status'] ?? 'active'
    ];
    
    $result = $siteService->createSite($data, $user['id']);
    
    $authMiddleware->logApiAccess($user['id'], '/api/sites', 'POST', [
        'action' => 'create',
        'site_name' => $data['site_name']
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
 * Handle update site action
 */
function handleUpdate($siteService, $authMiddleware, $user, $input) {
    if (!isset($input['id']) || (int)$input['id'] <= 0) {
        ApiResponse::validationError(
            ['id' => ['Site ID is required']],
            'Validation failed'
        );
    }
    
    $id = (int)$input['id'];
    $data = [];
    
    $allowedFields = ['site_name', 'lho', 'bank_name', 'customer_name', 'city', 'state', 'country', 'zone', 'address', 'latitude', 'longitude', 'status'];
    
    foreach ($allowedFields as $field) {
        if (isset($input[$field])) {
            if (in_array($field, ['latitude', 'longitude'])) {
                $data[$field] = $input[$field] !== '' ? (float)$input[$field] : null;
            } else {
                $data[$field] = $input[$field];
            }
        }
    }
    
    if (empty($data)) {
        ApiResponse::validationError(
            ['data' => ['No data provided for update']],
            'Validation failed'
        );
    }
    
    $result = $siteService->updateSite($id, $data, $user['id']);
    
    $authMiddleware->logApiAccess($user['id'], '/api/sites', 'POST', [
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
 * Handle delete site action
 */
function handleDelete($siteService, $authMiddleware, $user, $input) {
    if (!isset($input['id']) || (int)$input['id'] <= 0) {
        ApiResponse::validationError(
            ['id' => ['Site ID is required']],
            'Validation failed'
        );
    }
    
    $id = (int)$input['id'];
    
    $result = $siteService->deleteSite($id, $user['id']);
    
    $authMiddleware->logApiAccess($user['id'], '/api/sites', 'POST', [
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

/**
 * Handle get LHOs action
 */
function handleGetLHOs($siteService, $user) {
    $lhos = $siteService->getDistinctLHOs($user['company_id']);
    ApiResponse::success(['lhos' => $lhos], 'LHOs retrieved successfully');
}

/**
 * Handle get undelegated sites action
 */
function handleGetUndelegated($siteService, $user) {
    $sites = $siteService->getUndelegatedSites($user['company_id']);
    ApiResponse::success(['sites' => $sites], 'Undelegated sites retrieved successfully');
}
