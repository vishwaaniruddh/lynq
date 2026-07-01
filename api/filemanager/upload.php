<?php
/**
 * File Manager API - Upload File
 * POST /api/filemanager/upload.php
 * 
 * Uploads a file to the specified directory
 * 
 * Request: multipart/form-data
 * - file: The uploaded file (required)
 * - path: Target directory path relative to XAMPP root (required)
 * - overwrite: Whether to overwrite existing file (optional, default false)
 * 
 * Response: { success: bool, data: { path: string, name: string, size: int }, message: string }
 * 
 * Requirements: 9.2, 6.1
 * - 9.2: Save file to current directory when user confirms upload
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
    
    // Validate file upload
    if (!isset($_FILES['file']) || $_FILES['file']['error'] === UPLOAD_ERR_NO_FILE) {
        ApiResponse::validationError(['file' => 'No file uploaded'], 'File is required');
    }

    // Validate required fields
    $errors = [];
    
    if (!isset($_POST['path'])) {
        $errors['path'] = 'Target path is required';
    }
    
    if (!empty($errors)) {
        ApiResponse::validationError($errors);
    }
    
    $path = trim($_POST['path']);
    $overwrite = isset($_POST['overwrite']) && ($_POST['overwrite'] === 'true' || $_POST['overwrite'] === '1');
    
    // Initialize FileManagerService
    $fileManagerService = new FileManagerService();
    
    // Upload file
    $result = $fileManagerService->uploadFile($path, $_FILES['file'], $user['id'], $overwrite);
    
    if (!$result['success']) {
        // Check if it's a file exists error that requires confirmation
        if ($result['code'] === 'FILE_EXISTS' && isset($result['data']['requiresConfirmation'])) {
            ApiResponse::error(
                $result['code'],
                $result['error'],
                409, // Conflict status code
                $result['data']
            );
        }
        
        ApiResponse::error(
            $result['code'] ?? 'UPLOAD_FAILED',
            $result['error'] ?? 'Failed to upload file',
            400
        );
    }
    
    // Log API access
    $authMiddleware->logApiAccess($user['id'], '/api/filemanager/upload', 'POST', [
        'path' => $path,
        'filename' => $result['data']['name'] ?? 'unknown'
    ]);
    
    ApiResponse::success($result['data'], $result['message']);
    
} catch (Exception $e) {
    error_log("File Manager Upload API Error: " . $e->getMessage());
    ApiResponse::serverError('Failed to upload file');
}
