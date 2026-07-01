<?php
/**
 * Users API - List Users
 * GET /api/users/index.php
 * 
 * Lists users with company isolation applied
 * ADV users see all users, contractors see only their company's users
 * 
 * Query Parameters:
 * - company_id: Filter by company (optional, ADV only)
 * - search: Search term for username/email (optional)
 * - page: Page number (default: 1)
 * - limit: Results per page (default: 20, max: 100)
 * 
 * Response: { success: bool, data: { users: [], pagination: {} } }
 * 
 * **Validates: Requirements 7.1, 7.2, 7.3**
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
    
    // Require authentication and users.read permission
    $user = $authMiddleware->requirePermission('users.read');
    
    // Get query parameters
    $companyId = isset($_GET['company_id']) ? (int)$_GET['company_id'] : null;
    $search = isset($_GET['search']) ? trim($_GET['search']) : null;
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = min(100, max(1, (int)($_GET['limit'] ?? 20)));
    $offset = ($page - 1) * $limit;
    
    $userService = new UserService();
    $userRepository = new UserRepository();
    $userRepository->setCurrentUser($user['id']);
    
    // Build query based on filters
    if ($companyId !== null) {
        // Validate company access
        $authMiddleware->requireCompanyAccess($companyId);
        $users = $userRepository->findByCompanyWithRelations($companyId);
    } elseif ($search !== null && $search !== '') {
        $users = $userRepository->search($search);
    } else {
        $users = $userRepository->findAllWithRelations();
    }
    
    // Get total count for pagination
    $totalCount = count($users);
    
    // Apply pagination
    $paginatedUsers = array_slice($users, $offset, $limit);
    
    // Sanitize user data (remove sensitive fields)
    $sanitizedUsers = array_map(function($u) {
        unset($u['password_hash']);
        unset($u['failed_login_attempts']);
        unset($u['locked_until']);
        return $u;
    }, $paginatedUsers);
    
    // Log API access
    $authMiddleware->logApiAccess($user['id'], '/api/users', 'GET', [
        'company_id' => $companyId,
        'search' => $search,
        'page' => $page
    ]);
    
    ApiResponse::success([
        'users' => $sanitizedUsers,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $totalCount,
            'total_pages' => ceil($totalCount / $limit)
        ]
    ], 'Users retrieved successfully');
    
} catch (Exception $e) {
    error_log("Users API Error: " . $e->getMessage());
    ApiResponse::serverError('Failed to retrieve users');
}
