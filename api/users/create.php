<?php
/**
 * Users API - Create User
 * POST /api/users/create.php
 * 
 * Creates a new user with validation and company isolation
 * 
 * Request Body (JSON):
 * {
 *   "username": "string (required)",
 *   "email": "string (required)",
 *   "password": "string (required)",
 *   "first_name": "string (optional)",
 *   "last_name": "string (optional)",
 *   "company_id": "int (required)",
 *   "role_id": "int (required)"
 * }
 * 
 * Response: { success: bool, data: { user: {} } }
 * 
 * **Validates: Requirements 7.1, 7.2, 7.3, 8.1, 8.4**
 */

require_once __DIR__ . '/../../config/autoload.php';
require_once __DIR__ . '/../ApiResponse.php';
require_once __DIR__ . '/../../middleware/ApiAuthMiddleware.php';

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
    
    // Require authentication and users.create permission
    $currentUser = $authMiddleware->requirePermission('users.create');
    
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        ApiResponse::validationError(['body' => 'Invalid JSON body']);
    }
    
    // Validate required fields
    $requiredFields = ['username', 'email', 'password', 'company_id', 'role_id'];
    $errors = [];
    
    foreach ($requiredFields as $field) {
        if (!isset($input[$field]) || (is_string($input[$field]) && trim($input[$field]) === '')) {
            $errors[$field] = ucfirst(str_replace('_', ' ', $field)) . ' is required';
        }
    }
    
    if (!empty($errors)) {
        ApiResponse::validationError($errors);
    }
    
    // Validate email format
    if (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
        ApiResponse::validationError(['email' => 'Invalid email format']);
    }
    
    // Validate password strength
    $passwordErrors = ValidationUtils::validatePasswordStrength($input['password']);
    if (!empty($passwordErrors)) {
        ApiResponse::validationError(['password' => $passwordErrors]);
    }
    
    // Sanitize input
    $userData = [
        'username' => ValidationUtils::sanitizeInput($input['username'], 'string'),
        'email' => ValidationUtils::sanitizeInput($input['email'], 'email'),
        'password' => $input['password'], // Don't sanitize password
        'first_name' => ValidationUtils::sanitizeInput($input['first_name'] ?? '', 'string'),
        'last_name' => ValidationUtils::sanitizeInput($input['last_name'] ?? '', 'string'),
        'company_id' => (int)$input['company_id'],
        'role_id' => (int)$input['role_id'],
        'status' => USER_STATUS_ACTIVE
    ];
    
    // Validate company access
    $authMiddleware->requireCompanyAccess($userData['company_id']);
    
    $userService = new UserService();
    
    // Create user
    $user = $userService->createUser($userData, $currentUser['id']);
    
    // Sanitize response
    unset($user['password_hash']);
    unset($user['failed_login_attempts']);
    unset($user['locked_until']);
    
    // Log API access
    $authMiddleware->logApiAccess($currentUser['id'], '/api/users/create', 'POST', [
        'username' => $userData['username'],
        'company_id' => $userData['company_id']
    ]);
    
    ApiResponse::success(['user' => $user], 'User created successfully', 201);
    
} catch (InvalidArgumentException $e) {
    ApiResponse::validationError(['error' => $e->getMessage()]);
} catch (CompanyAccessDeniedException $e) {
    ApiResponse::forbidden($e->getMessage());
} catch (Exception $e) {
    error_log("Users API Error: " . $e->getMessage());
    ApiResponse::serverError('Failed to create user');
}
