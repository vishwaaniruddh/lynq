<?php
/**
 * Master Module Permission Middleware
 * 
 * Handles permission checking for master module operations.
 * Implements ADV-only access control and individual permission checks for CRUD operations.
 * 
 * Requirements: 1.5, 2.6, 8.1, 8.2, 8.4
 * 
 * **Feature: crm-master-modules, Property 3: ADV-Only Access Control**
 * **Validates: Requirements 1.5, 2.6, 7.2, 8.1, 8.2**
 */

require_once __DIR__ . '/../config/autoload.php';

class MasterModuleMiddleware {
    private $permissionEngine;
    private $sessionService;
    private $userModel;
    
    /**
     * Permission mapping for master modules
     */
    private $permissionMap = [
        'banks' => [
            'view' => 'masters.banks.view',
            'create' => 'masters.banks.create',
            'edit' => 'masters.banks.edit',
            'delete' => 'masters.banks.delete'
        ],
        'customers' => [
            'view' => 'masters.customers.view',
            'create' => 'masters.customers.create',
            'edit' => 'masters.customers.edit',
            'delete' => 'masters.customers.delete'
        ],
        'couriers' => [
            'view' => 'masters.couriers.view',
            'create' => 'masters.couriers.create',
            'edit' => 'masters.couriers.edit',
            'delete' => 'masters.couriers.delete'
        ],
        'locations' => [
            'view' => 'masters.locations.view',
            'create' => 'masters.locations.create',
            'edit' => 'masters.locations.edit',
            'delete' => 'masters.locations.delete'
        ],
        'product_categories' => [
            'view' => 'masters.product_categories.view',
            'create' => 'masters.product_categories.create',
            'edit' => 'masters.product_categories.edit',
            'delete' => 'masters.product_categories.delete'
        ],
        'lhos' => [
            'view' => 'masters.lhos.view',
            'create' => 'masters.lhos.create',
            'edit' => 'masters.lhos.edit',
            'delete' => 'masters.lhos.delete'
        ]
    ];
    
    public function __construct() {
        $this->permissionEngine = new PermissionEngine();
        $this->sessionService = new SessionService();
        $this->userModel = new User();
    }
    
    /**
     * Check if user is ADV user
     * Per Requirements 1.5, 2.6, 8.1, 8.2: Only ADV users can access master modules
     * 
     * @param int|null $userId User ID (optional, uses current session if not provided)
     * @return bool True if user is ADV user
     */
    public function isAdvUser($userId = null) {
        if ($userId === null) {
            $userId = $this->sessionService->getCurrentUserId();
        }
        
        if (!$userId) {
            return false;
        }
        
        $user = $this->userModel->findWithRelations($userId);
        
        if (!$user) {
            return false;
        }
        
        return $user['company_type'] === 'ADV';
    }
    
    /**
     * Require ADV user access for master modules
     * Redirects non-ADV users to dashboard with error message
     * 
     * @param bool $isApiRequest Whether this is an API request
     * @return array|null User data if authorized, null otherwise
     */
    public function requireAdvAccess($isApiRequest = false) {
        $userId = $this->sessionService->getCurrentUserId();
        
        if (!$userId) {
            $this->handleUnauthorized('User not authenticated', $isApiRequest);
            return null;
        }
        
        $user = $this->userModel->findWithRelations($userId);
        
        if (!$user) {
            $this->handleUnauthorized('User not found', $isApiRequest);
            return null;
        }
        
        if ($user['company_type'] !== 'ADV') {
            $this->handleForbidden(
                'Access denied. This module is restricted to ADV administrators.',
                $isApiRequest
            );
            return null;
        }
        
        return $user;
    }
    
    /**
     * Check if user has specific master module permission
     * 
     * @param string $module Module name (banks, customers, locations)
     * @param string $action Action name (view, create, edit, delete)
     * @param int|null $userId User ID (optional)
     * @return bool True if user has permission
     */
    public function hasPermission($module, $action, $userId = null) {
        if ($userId === null) {
            $userId = $this->sessionService->getCurrentUserId();
        }
        
        if (!$userId) {
            return false;
        }
        
        // First check if user is ADV user
        if (!$this->isAdvUser($userId)) {
            return false;
        }
        
        // Get permission name
        $permissionName = $this->getPermissionName($module, $action);
        
        if (!$permissionName) {
            return false;
        }
        
        // Check permission
        return $this->permissionEngine->can($userId, $permissionName);
    }
    
    /**
     * Require specific master module permission
     * 
     * @param string $module Module name (banks, customers, locations)
     * @param string $action Action name (view, create, edit, delete)
     * @param bool $isApiRequest Whether this is an API request
     * @return array|null User data if authorized, null otherwise
     */
    public function requirePermission($module, $action, $isApiRequest = false) {
        // First require ADV access
        $user = $this->requireAdvAccess($isApiRequest);
        
        if (!$user) {
            return null;
        }
        
        // Get permission name
        $permissionName = $this->getPermissionName($module, $action);
        
        if (!$permissionName) {
            $this->handleForbidden(
                "Invalid module or action: {$module}.{$action}",
                $isApiRequest
            );
            return null;
        }
        
        // Check permission
        if (!$this->permissionEngine->can($user['id'], $permissionName)) {
            $this->handleForbidden(
                "You do not have permission to perform this action",
                $isApiRequest,
                ['required_permission' => $permissionName]
            );
            return null;
        }
        
        return $user;
    }
    
    /**
     * Require view permission for a master module
     * 
     * @param string $module Module name
     * @param bool $isApiRequest Whether this is an API request
     * @return array|null User data if authorized
     */
    public function requireViewPermission($module, $isApiRequest = false) {
        return $this->requirePermission($module, 'view', $isApiRequest);
    }
    
    /**
     * Require create permission for a master module
     * 
     * @param string $module Module name
     * @param bool $isApiRequest Whether this is an API request
     * @return array|null User data if authorized
     */
    public function requireCreatePermission($module, $isApiRequest = false) {
        return $this->requirePermission($module, 'create', $isApiRequest);
    }
    
    /**
     * Require edit permission for a master module
     * 
     * @param string $module Module name
     * @param bool $isApiRequest Whether this is an API request
     * @return array|null User data if authorized
     */
    public function requireEditPermission($module, $isApiRequest = false) {
        return $this->requirePermission($module, 'edit', $isApiRequest);
    }
    
    /**
     * Require delete permission for a master module
     * 
     * @param string $module Module name
     * @param bool $isApiRequest Whether this is an API request
     * @return array|null User data if authorized
     */
    public function requireDeletePermission($module, $isApiRequest = false) {
        return $this->requirePermission($module, 'delete', $isApiRequest);
    }
    
    /**
     * Get permission name for module and action
     * 
     * @param string $module Module name
     * @param string $action Action name
     * @return string|null Permission name or null if invalid
     */
    public function getPermissionName($module, $action) {
        if (!isset($this->permissionMap[$module])) {
            return null;
        }
        
        if (!isset($this->permissionMap[$module][$action])) {
            return null;
        }
        
        return $this->permissionMap[$module][$action];
    }
    
    /**
     * Get all permissions for a module
     * 
     * @param string $module Module name
     * @return array Permission names
     */
    public function getModulePermissions($module) {
        if (!isset($this->permissionMap[$module])) {
            return [];
        }
        
        return array_values($this->permissionMap[$module]);
    }
    
    /**
     * Get user's permissions for a specific module
     * Returns which actions the user can perform
     * 
     * @param string $module Module name
     * @param int|null $userId User ID (optional)
     * @return array Associative array of action => bool
     */
    public function getUserModulePermissions($module, $userId = null) {
        if ($userId === null) {
            $userId = $this->sessionService->getCurrentUserId();
        }
        
        $permissions = [
            'view' => false,
            'create' => false,
            'edit' => false,
            'delete' => false
        ];
        
        if (!$userId || !$this->isAdvUser($userId)) {
            return $permissions;
        }
        
        foreach ($permissions as $action => $value) {
            $permissions[$action] = $this->hasPermission($module, $action, $userId);
        }
        
        return $permissions;
    }
    
    /**
     * Check if user can access any master module
     * 
     * @param int|null $userId User ID (optional)
     * @return bool True if user can access at least one master module
     */
    public function canAccessMasterModules($userId = null) {
        if ($userId === null) {
            $userId = $this->sessionService->getCurrentUserId();
        }
        
        if (!$userId || !$this->isAdvUser($userId)) {
            return false;
        }
        
        // Check if user has view permission for any module
        foreach ($this->permissionMap as $module => $actions) {
            if ($this->hasPermission($module, 'view', $userId)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Handle unauthorized access (401)
     * 
     * @param string $message Error message
     * @param bool $isApiRequest Whether this is an API request
     */
    private function handleUnauthorized($message, $isApiRequest = false) {
        if ($isApiRequest) {
            $this->sendJsonResponse(401, [
                'success' => false,
                'error' => [
                    'code' => 'UNAUTHORIZED',
                    'message' => $message
                ]
            ]);
        } else {
            // Redirect to login page
            $_SESSION['error_message'] = $message;
            header('Location: /new_crm/index.php?error=' . urlencode($message));
            exit;
        }
    }
    
    /**
     * Handle forbidden access (403)
     * 
     * @param string $message Error message
     * @param bool $isApiRequest Whether this is an API request
     * @param array $details Additional error details
     */
    private function handleForbidden($message, $isApiRequest = false, $details = []) {
        if ($isApiRequest) {
            $response = [
                'success' => false,
                'error' => [
                    'code' => 'FORBIDDEN',
                    'message' => $message
                ]
            ];
            
            if (!empty($details)) {
                $response['error']['details'] = $details;
            }
            
            $this->sendJsonResponse(403, $response);
        } else {
            // Redirect to dashboard with error message
            $_SESSION['error_message'] = $message;
            header('Location: /new_crm/dashboard.php?error=' . urlencode($message));
            exit;
        }
    }
    
    /**
     * Send JSON response
     * 
     * @param int $statusCode HTTP status code
     * @param array $data Response data
     */
    private function sendJsonResponse($statusCode, $data) {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
    
    /**
     * Static method to check ADV access for a user
     * Useful for quick checks without instantiating the middleware
     * 
     * @param int $userId User ID
     * @return bool True if user is ADV user
     */
    public static function checkAdvAccess($userId) {
        $middleware = new self();
        return $middleware->isAdvUser($userId);
    }
    
    /**
     * Static method to check master module permission
     * 
     * @param int $userId User ID
     * @param string $module Module name
     * @param string $action Action name
     * @return bool True if user has permission
     */
    public static function checkPermission($userId, $module, $action) {
        $middleware = new self();
        return $middleware->hasPermission($module, $action, $userId);
    }
}
