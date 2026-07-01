<?php
/**
 * User Profile Picture Upload API
 * POST /api/users/profile-picture.php - Upload profile picture
 * 
 * Accepts multipart form data with image file
 * Validates file type (JPEG, PNG, GIF) and size (max 2MB)
 * 
 * Requirements: 6.2, 6.3, 6.4
 * 
 * **Feature: user-profile-enhancement**
 */

require_once __DIR__ . '/../../config/autoload.php';
require_once __DIR__ . '/../ApiResponse.php';
require_once __DIR__ . '/../../middleware/ApiAuthMiddleware.php';
require_once __DIR__ . '/../../services/ProfileService.php';

// Handle CORS
ApiResponse::setCorsHeaders();
ApiResponse::handlePreflight();

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ApiResponse::methodNotAllowed(['POST']);
}

try {
    $authMiddleware = new ApiAuthMiddleware();
    
    // Check rate limiting
    $authMiddleware->checkRateLimit();
    
    // Require authentication (no specific permission needed for own profile)
    $currentUser = $authMiddleware->requireAuth();
    
    // Check if file was uploaded
    if (!isset($_FILES['profile_picture']) || $_FILES['profile_picture']['error'] === UPLOAD_ERR_NO_FILE) {
        ApiResponse::validationError(['profile_picture' => 'No file uploaded']);
    }
    
    // Check for upload errors
    if ($_FILES['profile_picture']['error'] !== UPLOAD_ERR_OK) {
        $errorMessages = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds server upload limit',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds form upload limit',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'File upload stopped by extension'
        ];
        $errorCode = $_FILES['profile_picture']['error'];
        $message = $errorMessages[$errorCode] ?? 'Unknown upload error';
        ApiResponse::validationError(['profile_picture' => $message]);
    }
    
    $profileService = new ProfileService();
    
    // Upload profile picture
    $result = $profileService->uploadProfilePicture($currentUser['id'], $_FILES['profile_picture']);
    
    if (!$result['success']) {
        if ($result['code'] === 'VALIDATION_ERROR') {
            ApiResponse::validationError(['profile_picture' => $result['message']]);
        }
        ApiResponse::serverError($result['message']);
    }
    
    // Log API access
    $authMiddleware->logApiAccess($currentUser['id'], '/api/users/profile-picture', 'POST');
    
    ApiResponse::success($result['data'], $result['message']);
    
} catch (Exception $e) {
    error_log("Profile Picture Upload API Error: " . $e->getMessage());
    ApiResponse::serverError('Failed to upload profile picture');
}
