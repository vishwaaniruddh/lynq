<?php
/**
 * Permission Middleware
 * Handles route-level permission checking and authorization
 */

require_once __DIR__ . '/../config/autoload.php';

class PermissionMiddleware {
    private $permissionEngine;
    private $sessionService;
    
    public function __construct() {
        $this->permissionEngine = new PermissionEngine();
        $this->sessionService = new SessionService();
    }
    
    /**
     * Check if current user has required permission
     */
    public function requirePermission($permissionName) {
        try {
            // Get current user from session
            $userId = $this->sessionService->getCurrentUserId();
            if (!$userId) {
                $this->handleUnauthorized('User not authenticated');
                return false;
            }
            
            // Check permission
            if (!$this->permissionEngine->can($userId, $permissionName)) {
                $this->handleForbidden("Permission '$permissionName' required");
                return false;
            }
            
            return true;
        } catch (Exception $e) {
            error_log("Permission middleware error: " . $e->getMessage());
            $this->handleServerError('Permission check failed');
            return false;
        }
    }
    
    /**
     * Check multiple permissions (user must have at least one)
     */
    public function requireAnyPermission($permissions) {
        try {
            $userId = $this->sessionService->getCurrentUserId();
            if (!$userId) {
                $this->handleUnauthorized('User not authenticated');
                return false;
            }
            
            foreach ($permissions as $permission) {
                if ($this->permissionEngine->can($userId, $permission)) {
                    return true;
                }
            }
            
            $this->handleForbidden('One of the following permissions required: ' . implode(', ', $permissions));
            return false;
        } catch (Exception $e) {
            error_log("Permission middleware error: " . $e->getMessage());
            $this->handleServerError('Permission check failed');
            return false;
        }
    }
    
    /**
     * Check multiple permissions (user must have all)
     */
    public function requireAllPermissions($permissions) {
        try {
            $userId = $this->sessionService->getCurrentUserId();
            if (!$userId) {
                $this->handleUnauthorized('User not authenticated');
                return false;
            }
            
            foreach ($permissions as $permission) {
                if (!$this->permissionEngine->can($userId, $permission)) {
                    $this->handleForbidden("Permission '$permission' required");
                    return false;
                }
            }
            
            return true;
        } catch (Exception $e) {
            error_log("Permission middleware error: " . $e->getMessage());
            $this->handleServerError('Permission check failed');
            return false;
        }
    }
    
    /**
     * Check if user is ADV user
     */
    public function requireAdvUser() {
        try {
            $userId = $this->sessionService->getCurrentUserId();
            if (!$userId) {
                $this->handleUnauthorized('User not authenticated');
                return false;
            }
            
            $userModel = new User();
            $user = $userModel->findWithRelations($userId);
            
            if (!$user || $user['company_type'] !== 'ADV') {
                $this->handleForbidden('ADV user access required');
                return false;
            }
            
            return true;
        } catch (Exception $e) {
            error_log("ADV user check error: " . $e->getMessage());
            $this->handleServerError('User verification failed');
            return false;
        }
    }
    
    /**
     * Check if user belongs to specific company
     */
    public function requireCompanyAccess($companyId) {
        try {
            $userId = $this->sessionService->getCurrentUserId();
            if (!$userId) {
                $this->handleUnauthorized('User not authenticated');
                return false;
            }
            
            $userModel = new User();
            $user = $userModel->findWithRelations($userId);
            
            if (!$user) {
                $this->handleUnauthorized('User not found');
                return false;
            }
            
            // ADV users can access any company
            if ($user['company_type'] === 'ADV') {
                return true;
            }
            
            // Contractor users can only access their own company
            if ($user['company_id'] != $companyId) {
                $this->handleForbidden('Access to this company is not allowed');
                return false;
            }
            
            return true;
        } catch (Exception $e) {
            error_log("Company access check error: " . $e->getMessage());
            $this->handleServerError('Company access verification failed');
            return false;
        }
    }
    
    /**
     * Get current user permissions for debugging
     */
    public function getCurrentUserPermissions() {
        try {
            $userId = $this->sessionService->getCurrentUserId();
            if (!$userId) {
                return [];
            }
            
            return $this->permissionEngine->getUserPermissions($userId);
        } catch (Exception $e) {
            error_log("Get user permissions error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Handle unauthorized access (401)
     */
    private function handleUnauthorized($message) {
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
            header('Location: /login.php?error=' . urlencode($message));
            exit;
        }
    }
    
    /**
     * Handle forbidden access (403)
     */
    private function handleForbidden($message) {
        if ($this->isApiRequest()) {
            $this->sendJsonResponse(403, [
                'success' => false,
                'error' => [
                    'code' => 'FORBIDDEN',
                    'message' => $message
                ]
            ]);
        } else {
            // Show error page or redirect
            header('Location: /error.php?code=403&message=' . urlencode($message));
            exit;
        }
    }
    
    /**
     * Handle server error (500)
     */
    private function handleServerError($message) {
        if ($this->isApiRequest()) {
            $this->sendJsonResponse(500, [
                'success' => false,
                'error' => [
                    'code' => 'SERVER_ERROR',
                    'message' => $message
                ]
            ]);
        } else {
            header('Location: /error.php?code=500&message=' . urlencode($message));
            exit;
        }
    }
    
    /**
     * Check if current request is API request
     */
    private function isApiRequest() {
        return strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false ||
               (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);
    }
    
    /**
     * Send JSON response
     */
    private function sendJsonResponse($statusCode, $data) {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
    
    /**
     * Create permission-protected route handler
     */
    public static function protect($permission, $handler) {
        return function() use ($permission, $handler) {
            $middleware = new PermissionMiddleware();
            if ($middleware->requirePermission($permission)) {
                return call_user_func_array($handler, func_get_args());
            }
        };
    }
    
    /**
     * Create ADV-only route handler
     */
    public static function advOnly($handler) {
        return function() use ($handler) {
            $middleware = new PermissionMiddleware();
            if ($middleware->requireAdvUser()) {
                return call_user_func_array($handler, func_get_args());
            }
        };
    }
}