<?php
/**
 * File Manager API - Download File
 * GET /api/filemanager/download.php
 * 
 * Streams file content to browser for download
 * 
 * Query Parameters:
 * - path: File path relative to XAMPP root (required)
 * 
 * Response: File stream with appropriate headers, or JSON error
 * 
 * Requirements: 8.1, 8.2, 8.3, 6.1
 * - 8.1: Initiate file download to user's browser
 * - 8.2: Set appropriate content-type headers based on file extension
 * - 8.3: Log download action to audit trail
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
    
    // Validate path before attempting download
    $pathValidator = $fileManagerService->getPathValidator();
    
    if (!$pathValidator->validate($path)) {
        ApiResponse::error('PATH_INVALID', 'Invalid or unsafe path', 400);
    }
    
    $absolutePath = $pathValidator->getAbsolutePath($path);
    
    if (!file_exists($absolutePath)) {
        ApiResponse::error('PATH_NOT_FOUND', 'File not found', 404);
    }
    
    if (!is_file($absolutePath)) {
        ApiResponse::error('NOT_A_FILE', 'Path is not a file', 400);
    }
    
    // Log API access
    $authMiddleware->logApiAccess($user['id'], '/api/filemanager/download', 'GET', [
        'path' => $path
    ]);
    
    // Download file (includes audit logging via userId parameter)
    // This method will stream the file and exit
    $fileManagerService->downloadFile($path, $user['id']);
    
    // Note: downloadFile() calls exit after streaming, so this line won't be reached
    
} catch (Exception $e) {
    error_log("File Manager Download API Error: " . $e->getMessage());
    ApiResponse::serverError('Failed to download file');
}
