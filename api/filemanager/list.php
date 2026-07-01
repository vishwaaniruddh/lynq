<?php
/**
 * File Manager API - List Directory Contents
 * GET /api/filemanager/list.php
 * 
 * Returns directory contents with file/folder information
 * 
 * Query Parameters:
 * - path: Directory path relative to XAMPP root (optional, defaults to root)
 * 
 * Response: { success: bool, data: { path: string, items: [], breadcrumbs: [] } }
 * 
 * Requirements: 1.1, 6.1
 * - 1.1: Display contents of XAMPP_Root directory
 * - 6.1: Verify user has ADV company type and system.manage permission
 */

require_once __DIR__ . '/../../config/autoload.php';
require_once __DIR__ . '/../ApiResponse.php';
require_once __DIR__ . '/../../middleware/ApiAuthMiddleware.php';
require_once __DIR__ . '/../../middleware/FileManagerMiddleware.php';
require_once __DIR__ . '/../../services/FileManagerService.php';

// Handle CORS
ApiResponse::setCorsHeaders();
ApiResponse::handlePreflight();

// Only allow GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    ApiResponse::methodNotAllowed(['GET']);
}

try {
    // Authentication and rate limiting
    $authMiddleware = new ApiAuthMiddleware();
    $authMiddleware->checkRateLimit();
    
    // File Manager access check (ADV + system.manage)
    $fileManagerMiddleware = new FileManagerMiddleware();
    $user = $fileManagerMiddleware->validateApiAccess();
    
    // Get path parameter (default to root)
    $path = isset($_GET['path']) ? trim($_GET['path']) : '';
    
    // Initialize FileManagerService
    $fileManagerService = new FileManagerService();
    
    // List directory contents
    $result = $fileManagerService->listDirectory($path);
    
    if (!$result['success']) {
        ApiResponse::error(
            $result['code'] ?? 'LIST_FAILED',
            $result['error'] ?? 'Failed to list directory',
            400
        );
    }
    
    // Log API access
    $authMiddleware->logApiAccess($user['id'], '/api/filemanager/list', 'GET', [
        'path' => $path
    ]);
    
    // Log file operation for audit trail
    $fileManagerService->logOperation(
        FileManagerService::ACTION_DIR_LIST,
        $path ?: '/',
        $user['id'],
        ['action' => 'list_directory']
    );
    
    ApiResponse::success($result['data'], 'Directory contents retrieved successfully');
    
} catch (Exception $e) {
    error_log("File Manager List API Error: " . $e->getMessage());
    ApiResponse::serverError('Failed to retrieve directory contents');
}
