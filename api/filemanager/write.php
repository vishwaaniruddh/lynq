<?php
/**
 * File Manager API - Write File Content
 * POST /api/filemanager/write.php
 * 
 * Writes content to an existing file, creating a backup before overwriting
 * 
 * Request Body (JSON):
 * - path: File path relative to XAMPP root (required)
 * - content: New file content (required)
 * 
 * Response: { success: bool, data: { path: string, backupPath: string, backupCreated: bool }, message: string }
 * 
 * Requirements: 4.2, 4.4, 6.1
 * - 4.2: Write updated content to file
 * - 4.4: Display success confirmation message
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
    
    if (!isset($input['content'])) {
        $errors['content'] = 'Content is required';
    }
    
    if (!empty($errors)) {
        ApiResponse::validationError($errors);
    }
    
    $path = trim($input['path']);
    $content = $input['content'];
    
    // Initialize FileManagerService
    $fileManagerService = new FileManagerService();
    
    // Write file content with user ID for audit logging
    $result = $fileManagerService->writeFile($path, $content, $user['id']);
    
    if (!$result['success']) {
        ApiResponse::error(
            $result['code'] ?? 'WRITE_FAILED',
            $result['error'] ?? 'Failed to write file',
            400
        );
    }
    
    // Log API access
    $authMiddleware->logApiAccess($user['id'], '/api/filemanager/write', 'POST', [
        'path' => $path,
        'content_length' => strlen($content)
    ]);
    
    ApiResponse::success($result['data'], $result['message']);
    
} catch (Exception $e) {
    error_log("File Manager Write API Error: " . $e->getMessage());
    ApiResponse::serverError('Failed to write file');
}
