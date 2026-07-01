<?php
/**
 * File Manager API - Rename File or Directory
 * POST /api/filemanager/rename.php
 * 
 * Renames a file or directory in the file system
 * 
 * Request Body (JSON):
 * - path: Current file or directory path relative to XAMPP root (required)
 * - newName: New name for the file or directory (required)
 * 
 * Response: { success: bool, data: { oldPath, newPath, oldName, newName }, message: string }
 * 
 * Requirements: 10.2, 6.1
 * - 10.2: Rename file or folder when user submits valid new name
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
    
    if (!isset($input['newName']) || trim($input['newName']) === '') {
        $errors['newName'] = 'New name is required';
    }
    
    if (!empty($errors)) {
        ApiResponse::validationError($errors);
    }
    
    $path = trim($input['path']);
    $newName = trim($input['newName']);
    
    // Initialize FileManagerService
    $fileManagerService = new FileManagerService();
    
    // Perform rename operation
    $result = $fileManagerService->renameItem($path, $newName, $user['id']);
    
    if (!$result['success']) {
        ApiResponse::error(
            $result['code'] ?? 'RENAME_FAILED',
            $result['error'] ?? 'Failed to rename item',
            400
        );
    }
    
    // Log API access
    $authMiddleware->logApiAccess($user['id'], '/api/filemanager/rename', 'POST', [
        'path' => $path,
        'newName' => $newName
    ]);
    
    ApiResponse::success($result['data'], $result['message']);
    
} catch (Exception $e) {
    error_log("File Manager Rename API Error: " . $e->getMessage());
    ApiResponse::serverError('Failed to rename item');
}
