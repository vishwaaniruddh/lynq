<?php
/**
 * Users API - Check Uniqueness
 * GET /api/users/check-uniqueness.php
 * 
 * Checks if username or email already exists in the database.
 * 
 * Query Parameters:
 * - username: string (optional)
 * - email: string (optional)
 * 
 * Response: { success: true, data: { username_exists: bool, email_exists: bool } }
 */

require_once __DIR__ . '/../../config/autoload.php';
require_once __DIR__ . '/../ApiResponse.php';
require_once __DIR__ . '/../../middleware/ApiAuthMiddleware.php';

// Handle CORS
ApiResponse::setCorsHeaders();
ApiResponse::handlePreflight();

// Only allow GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    ApiResponse::methodNotAllowed(['GET']);
}

try {
    $authMiddleware = new ApiAuthMiddleware();
    
    // Check rate limiting
    $authMiddleware->checkRateLimit();
    
    // Require authentication and users.create permission
    $currentUser = $authMiddleware->requirePermission('users.create');
    
    $username = isset($_GET['username']) ? trim($_GET['username']) : null;
    $email = isset($_GET['email']) ? trim($_GET['email']) : null;
    
    $db = Database::getInstance()->getConnection();
    
    $usernameExists = false;
    $emailExists = false;
    
    if ($username !== null && $username !== '') {
        $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $usernameExists = (bool)$stmt->fetch();
    }
    
    if ($email !== null && $email !== '') {
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $emailExists = (bool)$stmt->fetch();
    }
    
    ApiResponse::success([
        'username_exists' => $usernameExists,
        'email_exists' => $emailExists
    ], 'Check completed successfully');
    
} catch (Exception $e) {
    error_log("Users Uniqueness Check API Error: " . $e->getMessage());
    ApiResponse::serverError('Failed to perform uniqueness check');
}
