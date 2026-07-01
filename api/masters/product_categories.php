<?php
/**
 * Product Categories API Endpoint
 * GET /api/masters/product_categories.php - List categories with pagination, search, filters
 * POST /api/masters/product_categories.php - Create, update, delete category records
 * 
 * Query Parameters (GET):
 * - search: Search term for category name (optional)
 * - status: Filter by status (active/inactive) (optional)
 * - page: Page number (default: 1)
 * - limit: Results per page (default: 20, max: 100)
 * - export: Set to 1 for export mode (returns all filtered records)
 * 
 * POST Actions:
 * - action=create: Create new category (requires: name, optional: description, parent_id, status)
 * - action=update: Update category (requires: id, optional: name, description, parent_id, status)
 * - action=delete: Delete category (requires: id)
 */

require_once __DIR__ . '/../../config/autoload.php';
require_once __DIR__ . '/../ApiResponse.php';
require_once __DIR__ . '/../../middleware/ApiAuthMiddleware.php';
require_once __DIR__ . '/../../middleware/MasterModuleMiddleware.php';
require_once __DIR__ . '/../../services/ProductCategoryService.php';

// Handle CORS
ApiResponse::setCorsHeaders();
ApiResponse::handlePreflight();

try {
    $authMiddleware = new ApiAuthMiddleware();
    $masterMiddleware = new MasterModuleMiddleware();
    
    $authMiddleware->checkRateLimit();
    $user = $authMiddleware->requireAdvUser();
    
    $categoryService = new ProductCategoryService();
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        if (!$masterMiddleware->hasPermission('product_categories', 'view', $user['id'])) {
            ApiResponse::forbidden('You do not have permission to view product categories');
        }
        handleGetRequest($categoryService, $authMiddleware, $user);
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        handlePostRequest($categoryService, $authMiddleware, $masterMiddleware, $user);
    } else {
        ApiResponse::methodNotAllowed(['GET', 'POST']);
    }
    
} catch (Exception $e) {
    error_log("Product Categories API Error: " . $e->getMessage());
    ApiResponse::serverError('Failed to process request');
}

function handleGetRequest($categoryService, $authMiddleware, $user) {
    $search = isset($_GET['search']) ? trim($_GET['search']) : null;
    $status = isset($_GET['status']) ? $_GET['status'] : null;
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = min(100, max(1, (int)($_GET['limit'] ?? 20)));
    $export = isset($_GET['export']) && $_GET['export'] == '1';
    
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
    
    if ($export) {
        $categories = $categoryService->export($filters);
        
        $authMiddleware->logApiAccess($user['id'], '/api/masters/product_categories', 'GET', [
            'action' => 'export',
            'filters' => $filters
        ]);
        
        ApiResponse::success([
            'categories' => $categories,
            'total' => count($categories)
        ], 'Categories exported successfully');
        return;
    }
    
    $result = $categoryService->getAll($filters);
    
    $authMiddleware->logApiAccess($user['id'], '/api/masters/product_categories', 'GET', [
        'search' => $search,
        'status' => $status,
        'page' => $page
    ]);
    
    ApiResponse::success([
        'categories' => $result['data'],
        'pagination' => [
            'page' => $result['page'],
            'limit' => $result['limit'],
            'total' => $result['total'],
            'total_pages' => $result['totalPages']
        ]
    ], 'Categories retrieved successfully');
}

function handlePostRequest($categoryService, $authMiddleware, $masterMiddleware, $user) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input)) {
        $input = $_POST;
    }
    
    $action = $input['action'] ?? '';
    
    switch ($action) {
        case 'create':
            if (!$masterMiddleware->hasPermission('product_categories', 'create', $user['id'])) {
                ApiResponse::forbidden('You do not have permission to create product categories');
            }
            handleCreate($categoryService, $authMiddleware, $user, $input);
            break;
            
        case 'update':
            if (!$masterMiddleware->hasPermission('product_categories', 'edit', $user['id'])) {
                ApiResponse::forbidden('You do not have permission to edit product categories');
            }
            handleUpdate($categoryService, $authMiddleware, $user, $input);
            break;
            
        case 'delete':
            if (!$masterMiddleware->hasPermission('product_categories', 'delete', $user['id'])) {
                ApiResponse::forbidden('You do not have permission to delete product categories');
            }
            handleDelete($categoryService, $authMiddleware, $user, $input);
            break;
            
        default:
            ApiResponse::validationError(
                ['action' => ['Invalid or missing action. Valid actions: create, update, delete']],
                'Invalid action'
            );
    }
}

function handleCreate($categoryService, $authMiddleware, $user, $input) {
    if (!isset($input['name']) || trim($input['name']) === '') {
        ApiResponse::validationError(
            ['name' => ['Category name is required']],
            'Validation failed'
        );
    }
    
    $data = [
        'name' => $input['name'],
        'description' => $input['description'] ?? null,
        'parent_id' => $input['parent_id'] ?? null,
        'status' => $input['status'] ?? 'active'
    ];
    
    $result = $categoryService->create($data, $user['id']);
    
    $authMiddleware->logApiAccess($user['id'], '/api/masters/product_categories', 'POST', [
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

function handleUpdate($categoryService, $authMiddleware, $user, $input) {
    if (!isset($input['id']) || (int)$input['id'] <= 0) {
        ApiResponse::validationError(
            ['id' => ['Category ID is required']],
            'Validation failed'
        );
    }
    
    $id = (int)$input['id'];
    $data = [];
    
    if (isset($input['name'])) $data['name'] = $input['name'];
    if (isset($input['description'])) $data['description'] = $input['description'];
    if (array_key_exists('parent_id', $input)) $data['parent_id'] = $input['parent_id'];
    if (isset($input['status'])) $data['status'] = $input['status'];
    
    if (empty($data)) {
        ApiResponse::validationError(
            ['data' => ['No data provided for update']],
            'Validation failed'
        );
    }
    
    $result = $categoryService->update($id, $data, $user['id']);
    
    $authMiddleware->logApiAccess($user['id'], '/api/masters/product_categories', 'POST', [
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

function handleDelete($categoryService, $authMiddleware, $user, $input) {
    if (!isset($input['id']) || (int)$input['id'] <= 0) {
        ApiResponse::validationError(
            ['id' => ['Category ID is required']],
            'Validation failed'
        );
    }
    
    $id = (int)$input['id'];
    
    $result = $categoryService->delete($id, $user['id']);
    
    $authMiddleware->logApiAccess($user['id'], '/api/masters/product_categories', 'POST', [
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
