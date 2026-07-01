<?php
/**
 * File Manager API - Read File Content
 * GET /api/filemanager/read.php
 * 
 * Returns file content with metadata for viewing
 * 
 * Query Parameters:
 * - path: File path relative to XAMPP root (required)
 * 
 * Response: { success: bool, data: { path, name, content, size, sizeFormatted, modified, modifiedFormatted, language, isTruncated, isLargeFile } }
 * 
 * Requirements: 2.1, 2.3, 6.1
 * - 2.1: Display file content in a readable format
 * - 2.3: Display file path, size, and last modified timestamp
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
    
    // Get path parameter (required)
    if (!isset($_GET['path']) || trim($_GET['path']) === '') {
        ApiResponse::error('MISSING_PATH', 'File path is required', 400);
    }
    
    $path = trim($_GET['path']);
    
    // Initialize FileManagerService
    $fileManagerService = new FileManagerService();
    
    // Read file content
    $result = $fileManagerService->readFile($path);
    
    if (!$result['success']) {
        $statusCode = 400;
        if ($result['code'] === 'PATH_NOT_FOUND') {
            $statusCode = 404;
        } elseif ($result['code'] === 'ACCESS_DENIED') {
            $statusCode = 403;
        }
        
        ApiResponse::error(
            $result['code'] ?? 'READ_FAILED',
            $result['error'] ?? 'Failed to read file',
            $statusCode
        );
    }
    
    // Log API access
    $authMiddleware->logApiAccess($user['id'], '/api/filemanager/read', 'GET', [
        'path' => $path
    ]);
    
    // Log file operation for audit trail
    $fileManagerService->logOperation(
        FileManagerService::ACTION_FILE_READ,
        $path,
        $user['id'],
        ['action' => 'read_file']
    );
    
    ApiResponse::success($result['data'], 'File content retrieved successfully');
    
} catch (Exception $e) {
    error_log("File Manager Read API Error: " . $e->getMessage());
    ApiResponse::serverError('Failed to read file content');
}
