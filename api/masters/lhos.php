<?php
/**
 * LHOs (Local Head Office) API Endpoint
 * GET /api/masters/lhos.php - List LHOs with filters
 * POST /api/masters/lhos.php - Create, update, delete LHO records
 * 
 * Query Parameters (GET):
 * - search: Search term for LHO name and manager names (optional)
 * - status: Filter by status (active/inactive) (optional)
 * - manager_id: Filter by assigned manager user ID (optional)
 * - include_managers: Set to 1 to include manager data (optional)
 * - page: Page number (default: 1)
 * - limit: Results per page (default: 20, max: 100)
 * - id: Get single LHO by ID (optional)
 * - active_only: Set to 1 to get only active LHOs (for dropdowns)
 * - export: Set to 1 for export mode (returns all filtered records with managers)
 * 
 * POST Actions:
 * - action=create: Create new LHO (requires: lho_name, optional: status, manager_ids)
 * - action=update: Update LHO (requires: id, optional: lho_name, status, manager_ids)
 * - action=delete: Delete LHO (requires: id)
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
        if (!$masterMiddleware->hasPermission('lhos', 'view', $user['id'])) {
            ApiResponse::forbidden('You do not have permission to view LHO records');
        }
        handleGetRequest($locationService, $authMiddleware, $user);
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        handlePostRequest($locationService, $authMiddleware, $masterMiddleware, $user);
    } else {
        ApiResponse::methodNotAllowed(['GET', 'POST']);
    }
    
} catch (Exception $e) {
    error_log("LHOs API Error: " . $e->getMessage());
    ApiResponse::serverError('Failed to process request');
}

/**
 * Handle GET request - List LHOs or get single LHO
 */
function handleGetRequest($locationService, $authMiddleware, $user) {
    // Check if managers should be included
    $includeManagers = isset($_GET['include_managers']) && $_GET['include_managers'] == '1';
    
    // Check if requesting single LHO
    if (isset($_GET['id']) && (int)$_GET['id'] > 0) {
        // Use getLhoWithManagers if managers are requested
        if ($includeManagers) {
            $lho = $locationService->getLhoWithManagers((int)$_GET['id']);
        } else {
            $lho = $locationService->getLhoById((int)$_GET['id']);
        }
        
        if (!$lho) {
            ApiResponse::notFound('LHO not found');
        }
        
        $authMiddleware->logApiAccess($user['id'], '/api/masters/lhos', 'GET', [
            'action' => 'view',
            'id' => $_GET['id'],
            'include_managers' => $includeManagers
        ]);
        
        ApiResponse::success(['lho' => $lho], 'LHO retrieved successfully');
        return;
    }
    
    // Check if requesting active LHOs only (for dropdowns)
    if (isset($_GET['active_only']) && $_GET['active_only'] == '1') {
        $lhos = $locationService->getActiveLhos();
        
        $authMiddleware->logApiAccess($user['id'], '/api/masters/lhos', 'GET', [
            'action' => 'active_list'
        ]);
        
        ApiResponse::success([
            'lhos' => $lhos,
            'total' => count($lhos)
        ], 'Active LHOs retrieved successfully');
        return;
    }
    
    // Get query parameters
    $search = isset($_GET['search']) ? trim($_GET['search']) : null;
    $status = $_GET['status'] ?? null;
    $managerId = isset($_GET['manager_id']) ? (int)$_GET['manager_id'] : null;
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
    
    if ($managerId !== null && $managerId > 0) {
        $filters['manager_id'] = $managerId;
    }
    
    // Handle export mode
    if ($export) {
        $lhos = $locationService->exportLhosWithManagers($filters);
        
        $authMiddleware->logApiAccess($user['id'], '/api/masters/lhos', 'GET', [
            'action' => 'export',
            'filters' => $filters
        ]);
        
        ApiResponse::success([
            'lhos' => $lhos,
            'total' => count($lhos)
        ], 'LHOs exported successfully');
        return;
    }
    
    // Get paginated list with managers if requested
    if ($includeManagers || $managerId > 0) {
        $result = $locationService->getAllLhosWithManagers($filters);
    } else {
        $result = $locationService->getAllLhos($filters);
    }
    
    $authMiddleware->logApiAccess($user['id'], '/api/masters/lhos', 'GET', [
        'search' => $search,
        'status' => $status,
        'manager_id' => $managerId,
        'include_managers' => $includeManagers,
        'page' => $page
    ]);
    
    ApiResponse::success([
        'lhos' => $result['data'],
        'pagination' => [
            'page' => $result['page'],
            'limit' => $result['limit'],
            'total' => $result['total'],
            'total_pages' => $result['totalPages']
        ]
    ], 'LHOs retrieved successfully');
}

/**
 * Handle POST request - Create, update, or delete LHO
 */
function handlePostRequest($locationService, $authMiddleware, $masterMiddleware, $user) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input)) {
        $input = $_POST;
    }
    
    $action = $input['action'] ?? '';
    
    switch ($action) {
        case 'create':
            if (!$masterMiddleware->hasPermission('lhos', 'create', $user['id'])) {
                ApiResponse::forbidden('You do not have permission to create LHO records');
            }
            handleCreate($locationService, $authMiddleware, $user, $input);
            break;
            
        case 'update':
            if (!$masterMiddleware->hasPermission('lhos', 'edit', $user['id'])) {
                ApiResponse::forbidden('You do not have permission to edit LHO records');
            }
            handleUpdate($locationService, $authMiddleware, $user, $input);
            break;
            
        case 'delete':
            if (!$masterMiddleware->hasPermission('lhos', 'delete', $user['id'])) {
                ApiResponse::forbidden('You do not have permission to delete LHO records');
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
 * Handle create LHO action
 */
function handleCreate($locationService, $authMiddleware, $user, $input) {
    $errors = [];
    
    if (!isset($input['lho_name']) || trim($input['lho_name']) === '') {
        $errors['lho_name'] = ['LHO name is required'];
    }
    
    if (!empty($errors)) {
        ApiResponse::validationError($errors, 'Validation failed');
    }
    
    $data = [
        'lho_name' => $input['lho_name']
    ];
    
    if (isset($input['status'])) {
        $data['status'] = $input['status'];
    }
    
    $result = $locationService->createLho($data, $user['id']);
    
    $authMiddleware->logApiAccess($user['id'], '/api/masters/lhos', 'POST', [
        'action' => 'create',
        'lho_name' => $input['lho_name']
    ]);
    
    if ($result['success']) {
        $lhoId = $result['data']['id'];
        
        // Handle manager_ids if provided
        if (isset($input['manager_ids']) && is_array($input['manager_ids'])) {
            $managerResult = $locationService->syncLhoManagers($lhoId, $input['manager_ids'], $user['id']);
            
            if (!$managerResult['success']) {
                // LHO was created but manager sync failed - return partial success
                ApiResponse::success([
                    'lho' => $result['data']['lho'],
                    'manager_sync_error' => $managerResult['message'],
                    'manager_errors' => $managerResult['errors'] ?? []
                ], 'LHO created but manager assignment failed', 201);
                return;
            }
            
            // Get updated LHO with managers
            $lhoWithManagers = $locationService->getLhoWithManagers($lhoId);
            ApiResponse::success(['lho' => $lhoWithManagers], $result['message'], 201);
            return;
        }
        
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
 * Handle update LHO action
 */
function handleUpdate($locationService, $authMiddleware, $user, $input) {
    if (!isset($input['id']) || (int)$input['id'] <= 0) {
        ApiResponse::validationError(
            ['id' => ['LHO ID is required']],
            'Validation failed'
        );
    }
    
    $id = (int)$input['id'];
    $data = [];
    
    if (isset($input['lho_name'])) {
        $data['lho_name'] = $input['lho_name'];
    }
    
    if (isset($input['status'])) {
        $data['status'] = $input['status'];
    }
    
    // Check if we have LHO data to update or just manager_ids
    $hasLhoData = !empty($data);
    $hasManagerIds = isset($input['manager_ids']) && is_array($input['manager_ids']);
    
    if (!$hasLhoData && !$hasManagerIds) {
        ApiResponse::validationError(
            ['data' => ['No data provided for update']],
            'Validation failed'
        );
    }
    
    $result = ['success' => true, 'message' => 'LHO updated successfully'];
    
    // Update LHO data if provided
    if ($hasLhoData) {
        $result = $locationService->updateLho($id, $data, $user['id']);
        
        if (!$result['success']) {
            if ($result['code'] === 'NOT_FOUND') {
                ApiResponse::notFound($result['message']);
            } elseif ($result['code'] === 'DUPLICATE_ERROR') {
                ApiResponse::error('DUPLICATE_ERROR', $result['message'], 409, $result['errors'] ?? null);
            } else {
                ApiResponse::validationError($result['errors'] ?? [], $result['message']);
            }
            return;
        }
    }
    
    $authMiddleware->logApiAccess($user['id'], '/api/masters/lhos', 'POST', [
        'action' => 'update',
        'id' => $id,
        'changes' => array_keys($data),
        'has_manager_ids' => $hasManagerIds
    ]);
    
    // Handle manager_ids if provided
    if ($hasManagerIds) {
        $managerResult = $locationService->syncLhoManagers($id, $input['manager_ids'], $user['id']);
        
        if (!$managerResult['success']) {
            if ($managerResult['code'] === 'NOT_FOUND') {
                ApiResponse::notFound($managerResult['message']);
            }
            
            // LHO was updated but manager sync failed - return partial success
            $lho = $locationService->getLhoWithManagers($id);
            ApiResponse::success([
                'lho' => $lho,
                'manager_sync_error' => $managerResult['message'],
                'manager_errors' => $managerResult['errors'] ?? []
            ], 'LHO updated but manager assignment failed');
            return;
        }
    }
    
    // Get updated LHO with managers
    $lhoWithManagers = $locationService->getLhoWithManagers($id);
    ApiResponse::success(['lho' => $lhoWithManagers], $result['message']);
}

/**
 * Handle delete LHO action
 */
function handleDelete($locationService, $authMiddleware, $user, $input) {
    if (!isset($input['id']) || (int)$input['id'] <= 0) {
        ApiResponse::validationError(
            ['id' => ['LHO ID is required']],
            'Validation failed'
        );
    }
    
    $id = (int)$input['id'];
    
    $result = $locationService->deleteLho($id, $user['id']);
    
    $authMiddleware->logApiAccess($user['id'], '/api/masters/lhos', 'POST', [
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
