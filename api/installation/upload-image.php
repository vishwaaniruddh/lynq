<?php
/**
 * Installation Image Upload API
 * 
 * POST /api/installation/upload-image.php
 * Uploads an image for an installation section
 * 
 * Request (multipart/form-data):
 * - installation_id: (required) Installation ID
 * - section: (required) Section identifier (e.g., 'router_fixed', 'adaptor', 'verification')
 * - file: (required) Image file (JPEG, PNG, max 5MB)
 * 
 * Response:
 * - success: boolean
 * - message: string
 * - data: Uploaded file path and details (on success)
 * 
 * Requirements: 4.4, 11.4
 * - 4.4: Validate file type (JPEG, PNG) and size (max 5MB) for router photos
 * - 11.4: Validate file type (JPEG, PNG) and size (max 5MB) for vendor stamp
 */

require_once __DIR__ . '/../../config/autoload.php';
require_once __DIR__ . '/../ApiResponse.php';
require_once __DIR__ . '/../../middleware/ApiAuthMiddleware.php';
require_once __DIR__ . '/../../services/InstallationImageService.php';
require_once __DIR__ . '/../../services/InstallationService.php';

// Handle CORS
ApiResponse::setCorsHeaders();
ApiResponse::handlePreflight();

try {
    $authMiddleware = new ApiAuthMiddleware();
    
    // Check rate limiting
    $authMiddleware->checkRateLimit();
    
    // Require authenticated user
    $user = $authMiddleware->requireAuth();
    
    // Only allow POST method
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        ApiResponse::methodNotAllowed(['POST']);
    }
    
    // Validate required fields from POST data
    $installationId = isset($_POST['installation_id']) ? (int)$_POST['installation_id'] : 0;
    $section = isset($_POST['section']) ? trim($_POST['section']) : '';
    
    $errors = [];
    
    if (!$installationId) {
        $errors[] = ['field' => 'installation_id', 'message' => 'Installation ID is required'];
    }
    
    if (!$section) {
        $errors[] = ['field' => 'section', 'message' => 'Section is required'];
    }
    
    // Check if file was uploaded
    if (!isset($_FILES['file']) || $_FILES['file']['error'] === UPLOAD_ERR_NO_FILE) {
        $errors[] = ['field' => 'file', 'message' => 'Image file is required'];
    }
    
    if (!empty($errors)) {
        ApiResponse::validationError($errors, 'Validation failed');
    }
    
    // Initialize services
    $installationService = new InstallationService();
    $imageService = new InstallationImageService();
    
    // Verify installation exists and user can access form
    $installation = $installationService->getInstallation($installationId);
    if (!$installation) {
        ApiResponse::notFound('Installation not found');
    }
    
    // Check if form access is allowed
    if (!$installationService->canAccessForm($installationId)) {
        ApiResponse::forbidden('Form access is not allowed until materials are received');
    }
    
    // Check if installation is locked (ADV-approved)
    if ($installation['status'] === 'adv_approved') {
        ApiResponse::forbidden('Cannot modify ADV-approved installation');
    }
    
    // Upload the image
    $result = $imageService->uploadImage($_FILES['file'], $section, $installationId);
    
    // Log API access
    $authMiddleware->logApiAccess($user['id'], '/api/installation/upload-image', 'POST', [
        'installation_id' => $installationId,
        'section' => $section,
        'success' => $result['success']
    ]);
    
    if ($result['success']) {
        // Update installation record with the new image path
        $updateField = $section;
        $currentValue = $installation[$updateField] ?? '';
        
        // Append new path to existing paths (comma-separated)
        $newPath = $result['data']['path'];
        if (!empty($currentValue)) {
            $newValue = $currentValue . ',' . $newPath;
        } else {
            $newValue = $newPath;
        }
        
        // Save the updated path to installation
        $installationService->saveInstallationData($installationId, [$updateField => $newValue], $user['id']);
        
        ApiResponse::success([
            'filename' => $result['data']['filename'],
            'path' => $result['data']['path'],
            'section' => $result['data']['section'],
            'size' => $result['data']['size']
        ], $result['message'], 201);
    } else {
        $statusCode = 400;
        if ($result['code'] === 'VALIDATION_ERROR') {
            ApiResponse::validationError($result['errors'] ?? [], $result['message']);
            return;
        }
        
        ApiResponse::error($result['code'], $result['message'], $statusCode);
    }
    
} catch (Exception $e) {
    error_log("Installation Image Upload API Error: " . $e->getMessage() . " | Trace: " . $e->getTraceAsString());
    ApiResponse::serverError('An error occurred while uploading image');
}
