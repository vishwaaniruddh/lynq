<?php
/**
 * File Manager API - Create File or Directory
 * POST /api/filemanager/create.php
 * 
 * Creates a new file or directory in the specified path
 * 
 * Request Body (JSON):
 * - type: 'file' or 'directory' (required)
 * - path: Parent directory path relative to XAMPP root (required)
 * - name: Name of the new file or directory (required)
 * - content: Initial content for files (optional, default empty)
 * 
 * Response: { success: bool, data: { path: string, name: string }, message: string }
 * 
 * Requirements: 3.2, 3.4, 6.1
 * - 3.2: Create file in current directory
 * - 3.4: Create folder in current directory
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
    
    if (empty($input['type'])) {
        $errors['type'] = 'Type is required (file or directory)';
    } elseif (!in_array($input['type'], ['file', 'directory'])) {
        $errors['type'] = 'Type must be either "file" or "directory"';
    }
    
    if (!isset($input['path'])) {
        $errors['path'] = 'Path is required';
    }
    
    if (empty($input['name'])) {
        $errors['name'] = 'Name is required';
    }
    
    if (!empty($errors)) {
        ApiResponse::validationError($errors);
    }
    
    $type = $input['type'];
    $path = trim($input['path']);
    $name = trim($input['name']);
    $content = $input['content'] ?? '';
    
    // Initialize FileManagerService
    $fileManagerService = new FileManagerService();
    
    // Create file or directory based on type
    if ($type === 'file') {
        $result = $fileManagerService->createFile($path, $name, $content, $user['id']);
    } else {
        $result = $fileManagerService->createDirectory($path, $name, $user['id']);
    }
    
    if (!$result['success']) {
        ApiResponse::error(
            $result['code'] ?? 'CREATE_FAILED',
            $result['error'] ?? 'Failed to create ' . $type,
            400
        );
    }
    
    // Log API access
    $authMiddleware->logApiAccess($user['id'], '/api/filemanager/create', 'POST', [
        'type' => $type,
        'path' => $path,
        'name' => $name
    ]);
    
    ApiResponse::success($result['data'], $result['message']);
    
} catch (Exception $e) {
    error_log("File Manager Create API Error: " . $e->getMessage());
    ApiResponse::serverError('Failed to create ' . ($type ?? 'item'));
}
