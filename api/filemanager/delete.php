<?php
/**
 * File Manager API - Delete File or Directory
 * POST /api/filemanager/delete.php
 * 
 * Deletes a file or directory from the file system
 * For directories, deletion is recursive (removes all contents)
 * 
 * Request Body (JSON):
 * - path: File or directory path relative to XAMPP root (required)
 * - type: 'file' or 'directory' (required)
 * 
 * Response: { success: bool, data: { path: string }, message: string }
 * 
 * Requirements: 5.2, 5.4, 6.1
 * - 5.2: Remove file from file system when user confirms deletion
 * - 5.4: Recursively remove folder and all its contents
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

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ApiResponse::methodNotAllowed(['POST']);
}

try {
    // Authentication and rate limiting
    $authMiddleware = new ApiAuthMiddleware();
    $authMiddleware->checkRateLimit();
    
    // File Manager access check (ADV + system.manage)
    $fileManagerMiddleware = new FileManagerMiddleware();
    $user = $fileManagerMiddleware->validateApiAccess();
    
    // Get request body
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        ApiResponse::validationError(['body' => 'Invalid JSON body'], 'Invalid request body');
    }
    
    // Validate required fields
    $errors = [];
    
    if (!isset($input['path']) || $input['path'] === '') {
        $errors['path'] = 'Path is required';
    }
    
    if (!isset($input['type']) || !in_array($input['type'], ['file', 'directory'])) {
        $errors['type'] = 'Type must be "file" or "directory"';
    }
    
    if (!empty($errors)) {
        ApiResponse::validationError($errors);
    }
    
    $path = trim($input['path']);
    $type = $input['type'];
    
    // Initialize FileManagerService
    $fileManagerService = new FileManagerService();
    
    // Delete file or directory based on type
    if ($type === 'file') {
        $result = $fileManagerService->deleteFile($path, $user['id']);
    } else {
        $result = $fileManagerService->deleteDirectory($path, $user['id']);
    }
    
    if (!$result['success']) {
        ApiResponse::error(
            $result['code'] ?? 'DELETE_FAILED',
            $result['error'] ?? 'Failed to delete ' . $type,
            400
        );
    }
    
    // Log API access
    $authMiddleware->logApiAccess($user['id'], '/api/filemanager/delete', 'POST', [
        'path' => $path,
        'type' => $type
    ]);
    
    ApiResponse::success($result['data'], $result['message']);
    
} catch (Exception $e) {
    error_log("File Manager Delete API Error: " . $e->getMessage());
    ApiResponse::serverError('Failed to delete ' . ($type ?? 'item'));
}
