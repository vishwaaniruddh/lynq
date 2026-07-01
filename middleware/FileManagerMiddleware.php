<?php
/**
 * File Manager Middleware
 * Handles permission checking for File Manager module access
 * 
 * Requirements: 6.1, 6.3
 * - 6.1: Verify user has ADV company type and system.manage permission
 * - 6.3: Redirect non-ADV users to dashboard with access denied message
 */

require_once __DIR__ . '/../config/autoload.php';

class FileManagerMiddleware {
    private $permissionEngine;
    private $sessionService;
    
    public function __construct() {
        $this->permissionEngine = new PermissionEngine();
        $this->sessionService = new SessionService();
    }
    
    /**
     * Check if current user has File Manager access
     * Requires ADV company type AND system.manage permission
     * 
     * @return bool True if user has access
     */
    public function checkAccess(): bool {
        try {
            // Get current user from session
            $userId = $this->sessionService->getCurrentUserId();
            if (!$userId) {
                return false;
            }
            
            // Check ADV company type
            if (!$this->isAdvUser($userId)) {
                return false;
            }
            
            // Check system.manage permission
            if (!$this->permissionEngine->can($userId, 'system.manage')) {
                return false;
            }
            
            return true;
        } catch (Exception $e) {
            error_log("FileManagerMiddleware access check error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Require File Manager access for page/API
     * Redirects unauthorized users or sends JSON error for API requests
     * 
     * @return array|null User data if authorized, null otherwise (with redirect/response)
     */
    public function requireAccess(): ?array {
        try {
            // Get current user from session
            $userId = $this->sessionService->getCurrentUserId();
            if (!$userId) {
                $this->handleUnauthorized('User not authenticated');
                return null;
            }
            
            // Get user with company info
            $userModel = new User();
            $user = $userModel->findWithRelations($userId);
            
            if (!$user) {
                $this->handleUnauthorized('User not found');
                return null;
            }
            
            // Check ADV company type (Requirement 6.1)
            if ($user['company_type'] !== 'ADV') {
                $this->handleForbidden('File Manager access requires ADV company type');
                return null;
            }
            
            // Check system.manage permission (Requirement 6.1)
            if (!$this->permissionEngine->can($userId, 'system.manage')) {
                $this->handleForbidden('File Manager access requires system.manage permission');
                return null;
            }
            
            return $user;
        } catch (Exception $e) {
            error_log("FileManagerMiddleware requireAccess error: " . $e->getMessage());
            $this->handleServerError('Permission check failed');
            return null;
        }
    }
    
    /**
     * Check if user is ADV user
     * 
     * @param int $userId User ID
     * @return bool True if user is ADV
     */
    public function isAdvUser(int $userId): bool {
        try {
            $userModel = new User();
            $user = $userModel->findWithRelations($userId);
            
            return $user && $user['company_type'] === 'ADV';
        } catch (Exception $e) {
            error_log("FileManagerMiddleware isAdvUser error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check if user has system.manage permission
     * 
     * @param int $userId User ID
     * @return bool True if user has permission
     */
    public function hasSystemManagePermission(int $userId): bool {
        try {
            return $this->permissionEngine->can($userId, 'system.manage');
        } catch (Exception $e) {
            error_log("FileManagerMiddleware hasSystemManagePermission error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get current user ID from session
     * 
     * @return int|null User ID or null
     */
    public function getCurrentUserId(): ?int {
        return $this->sessionService->getCurrentUserId();
    }
    
    /**
     * Handle unauthorized access (401)
     * 
     * @param string $message Error message
     */
    private function handleUnauthorized(string $message): void {
        if ($this->isApiRequest()) {
            $this->sendJsonResponse(401, [
                'success' => false,
                'error' => [
                    'code' => 'UNAUTHORIZED',
                    'message' => $message
                ]
            ]);
        } else {
            // Redirect to login page
            header('Location: /clarity/new_crm/index.php?error=' . urlencode($message));
            exit;
        }
    }
    
    /**
     * Handle forbidden access (403)
     * Requirement 6.3: Redirect non-ADV users to dashboard with access denied message
     * 
     * @param string $message Error message
     */
    private function handleForbidden(string $message): void {
        if ($this->isApiRequest()) {
            $this->sendJsonResponse(403, [
                'success' => false,
                'error' => [
                    'code' => 'ACCESS_DENIED',
                    'message' => $message
                ]
            ]);
        } else {
            // Redirect to dashboard with access denied message (Requirement 6.3)
            header('Location: /clarity/new_crm/dashboard.php?error=' . urlencode('Access Denied: ' . $message));
            exit;
        }
    }
    
    /**
     * Handle server error (500)
     * 
     * @param string $message Error message
     */
    private function handleServerError(string $message): void {
        if ($this->isApiRequest()) {
            $this->sendJsonResponse(500, [
                'success' => false,
                'error' => [
                    'code' => 'SERVER_ERROR',
                    'message' => $message
                ]
            ]);
        } else {
            header('Location: /clarity/new_crm/dashboard.php?error=' . urlencode('Server Error: ' . $message));
            exit;
        }
    }
    
    /**
     * Check if current request is API request
     * 
     * @return bool True if API request
     */
    private function isApiRequest(): bool {
        return strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false ||
               (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);
    }
    
    /**
     * Send JSON response
     * 
     * @param int $statusCode HTTP status code
     * @param array $data Response data
     */
    private function sendJsonResponse(int $statusCode, array $data): void {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
    
    /**
     * Static method to protect a route with File Manager access
     * 
     * @param callable $handler Route handler
     * @return callable Protected handler
     */
    public static function protect(callable $handler): callable {
        return function() use ($handler) {
            $middleware = new FileManagerMiddleware();
            $user = $middleware->requireAccess();
            if ($user) {
                return call_user_func_array($handler, array_merge([$user], func_get_args()));
            }
        };
    }
    
    /**
     * Validate access for API endpoint
     * Returns user data if authorized, sends error response and exits if not
     * 
     * @return array User data
     */
    public function validateApiAccess(): array {
        $user = $this->requireAccess();
        if (!$user) {
            // Response already sent by requireAccess
            exit;
        }
        return $user;
    }
}
