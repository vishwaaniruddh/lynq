<?php
/**
 * Projects API Endpoint
 * GET /api/masters/projects.php - List projects with pagination, search, filters
 * POST /api/masters/projects.php - Create, update, delete (soft delete) project records
 */

require_once __DIR__ . '/../../config/autoload.php';
require_once __DIR__ . '/../ApiResponse.php';
require_once __DIR__ . '/../../middleware/ApiAuthMiddleware.php';
require_once __DIR__ . '/../../middleware/MasterModuleMiddleware.php';
require_once __DIR__ . '/../../services/ProjectService.php';

// Handle CORS
ApiResponse::setCorsHeaders();
ApiResponse::handlePreflight();

try {
    $authMiddleware = new ApiAuthMiddleware();
    $masterMiddleware = new MasterModuleMiddleware();
    
    // Check rate limiting
    $authMiddleware->checkRateLimit();
    
    // Require ADV user access
    $user = $authMiddleware->requireAdvUser();
    $projectService = new ProjectService();
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        if (!$masterMiddleware->hasPermission('projects', 'view', $user['id'])) {
            ApiResponse::forbidden('You do not have permission to view project records');
        }
        handleGetRequest($projectService, $authMiddleware, $user);
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        handlePostRequest($projectService, $authMiddleware, $masterMiddleware, $user);
    } else {
        ApiResponse::methodNotAllowed(['GET', 'POST']);
    }
} catch (Exception $e) {
    error_log("Projects API Error: " . $e->getMessage());
    ApiResponse::serverError('Failed to process request: ' . $e->getMessage());
}

/**
 * Handle GET request - List projects
 */
function handleGetRequest($projectService, $authMiddleware, $user) {
    $search = isset($_GET['search']) ? trim($_GET['search']) : null;
    $status = isset($_GET['status']) && $_GET['status'] !== '' ? (int)$_GET['status'] : null;
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = min(100, max(1, (int)($_GET['limit'] ?? 10)));
    $export = isset($_GET['export']) && $_GET['export'] == '1';
    
    $filters = [
        'page' => $page,
        'limit' => $limit
    ];
    
    if ($search !== null && $search !== '') {
        $filters['search'] = $search;
    }
    
    if ($status !== null) {
        $filters['status'] = $status;
    }
    
    if ($export) {
        $projects = $projectService->export($filters);
        ApiResponse::success([
            'projects' => $projects,
            'total' => count($projects)
        ], 'Projects exported successfully');
    }
    
    $result = $projectService->getAll($filters);
    
    ApiResponse::success([
        'projects' => $result['data'],
        'pagination' => [
            'total' => $result['total'],
            'page' => $result['page'],
            'limit' => $result['limit'],
            'total_pages' => $result['totalPages']
        ]
    ], 'Projects retrieved successfully');
}

/**
 * Handle POST request - Create, update, or delete
 */
function handlePostRequest($projectService, $authMiddleware, $masterMiddleware, $user) {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        $input = $_POST;
    }
    
    $action = $input['action'] ?? $_GET['action'] ?? null;
    
    if (!$action) {
        ApiResponse::validationError(['action' => 'Action is required (create, update, delete)']);
    }
    
    switch ($action) {
        case 'create':
            if (!$masterMiddleware->hasPermission('projects', 'create', $user['id'])) {
                ApiResponse::forbidden('You do not have permission to create project records');
            }
            
            $result = $projectService->create($input, $user['id']);
            if (!$result['success']) {
                if ($result['code'] === 'VALIDATION_ERROR') {
                    ApiResponse::validationError($result['errors'], $result['message']);
                } elseif ($result['code'] === 'DUPLICATE_ERROR') {
                    ApiResponse::conflict($result['message'], $result['errors']);
                }
                ApiResponse::badRequest($result['message']);
            }
            
            ApiResponse::created(['project' => $result['data']], 'Project created successfully');
            break;
            
        case 'update':
            if (!$masterMiddleware->hasPermission('projects', 'edit', $user['id'])) {
                ApiResponse::forbidden('You do not have permission to edit project records');
            }
            
            $id = isset($input['id']) ? (int)$input['id'] : 0;
            if ($id <= 0) {
                ApiResponse::validationError(['id' => 'Valid project ID is required']);
            }
            
            $result = $projectService->update($id, $input, $user['id']);
            if (!$result['success']) {
                if ($result['code'] === 'NOT_FOUND') {
                    ApiResponse::notFound($result['message']);
                } elseif ($result['code'] === 'VALIDATION_ERROR') {
                    ApiResponse::validationError($result['errors'], $result['message']);
                } elseif ($result['code'] === 'DUPLICATE_ERROR') {
                    ApiResponse::conflict($result['message'], $result['errors']);
                }
                ApiResponse::badRequest($result['message']);
            }
            
            ApiResponse::success(['project' => $result['data']], 'Project updated successfully');
            break;
            
        case 'delete':
            if (!$masterMiddleware->hasPermission('projects', 'delete', $user['id'])) {
                ApiResponse::forbidden('You do not have permission to delete project records');
            }
            
            $id = isset($input['id']) ? (int)$input['id'] : 0;
            if ($id <= 0) {
                ApiResponse::validationError(['id' => 'Valid project ID is required']);
            }
            
            $result = $projectService->delete($id, $user['id']);
            if (!$result['success']) {
                if ($result['code'] === 'NOT_FOUND') {
                    ApiResponse::notFound($result['message']);
                }
                ApiResponse::badRequest($result['message']);
            }
            
            ApiResponse::success(null, 'Project deleted successfully');
            break;
            
        default:
            ApiResponse::badRequest("Invalid action: {$action}");
    }
}
