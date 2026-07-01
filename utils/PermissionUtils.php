<?php
/**
 * Permission Utility Functions
 * Provides global permission checking functions
 * 
 * Note: This file is loaded by autoload.php, do not require autoload here
 */

/**
 * Global can() function for consistent permission checking
 * This is the main function used throughout the application
 */
function can($permissionName, $userId = null) {
    static $permissionEngine = null;
    static $sessionService = null;
    
    if ($permissionEngine === null) {
        $permissionEngine = new PermissionEngine();
    }
    
    if ($sessionService === null) {
        $sessionService = new SessionService();
    }
    
    // If no user ID provided, get current user from session
    if ($userId === null) {
        $userId = $sessionService->getCurrentUserId();
        if (!$userId) {
            return false;
        }
    }
    
    return $permissionEngine->can($userId, $permissionName);
}

/**
 * Check if current user has any of the specified permissions
 */
function canAny($permissions, $userId = null) {
    foreach ($permissions as $permission) {
        if (can($permission, $userId)) {
            return true;
        }
    }
    return false;
}

/**
 * Check if current user has all of the specified permissions
 */
function canAll($permissions, $userId = null) {
    foreach ($permissions as $permission) {
        if (!can($permission, $userId)) {
            return false;
        }
    }
    return true;
}

/**
 * Check if current user is ADV user
 */
function isAdvUser($userId = null) {
    static $sessionService = null;
    static $userModel = null;
    
    if ($sessionService === null) {
        $sessionService = new SessionService();
    }
    
    if ($userModel === null) {
        $userModel = new User();
    }
    
    if ($userId === null) {
        $userId = $sessionService->getCurrentUserId();
        if (!$userId) {
            return false;
        }
    }
    
    $user = $userModel->findWithRelations($userId);
    return $user && strtoupper($user['company_type']) === 'ADV';
}

/**
 * Check if current user is contractor user
 */
function isContractorUser($userId = null) {
    static $sessionService = null;
    static $userModel = null;
    
    if ($sessionService === null) {
        $sessionService = new SessionService();
    }
    
    if ($userModel === null) {
        $userModel = new User();
    }
    
    if ($userId === null) {
        $userId = $sessionService->getCurrentUserId();
        if (!$userId) {
            return false;
        }
    }
    
    $user = $userModel->findWithRelations($userId);
    return $user && strtoupper($user['company_type']) === 'CONTRACTOR';
}

/**
 * Check if current user is a contractor admin (has contractor management permissions)
 * Contractor admins can see the full contractor dashboard with delegation stats
 * Regular engineers only see their assigned sites
 */
function isContractorAdmin($userId = null) {
    static $sessionService = null;
    static $userModel = null;
    
    if ($sessionService === null) {
        $sessionService = new SessionService();
    }
    
    if ($userModel === null) {
        $userModel = new User();
    }
    
    if ($userId === null) {
        $userId = $sessionService->getCurrentUserId();
        if (!$userId) {
            return false;
        }
    }
    
    // Must be a contractor user first
    if (!isContractorUser($userId)) {
        return false;
    }
    
    // Get user with role information
    $user = $userModel->findWithRelations($userId);
    if (!$user) {
        return false;
    }
    
    // Check if user's role name contains "Engineer" - if so, they're NOT a contractor admin
    $roleName = strtolower($user['role_name'] ?? '');
    if (strpos($roleName, 'engineer') !== false) {
        return false;
    }
    
    // Check if user's role name indicates admin/manager level
    // Contractor admins typically have roles like "Admin", "Manager", "Contractor Admin"
    $adminRoles = ['admin', 'manager', 'contractor admin', 'contractor manager'];
    foreach ($adminRoles as $adminRole) {
        if (strpos($roleName, $adminRole) !== false) {
            return true;
        }
    }
    
    // If role is not explicitly engineer and has high level (level >= 80), consider as admin
    $roleLevel = (int)($user['role_level'] ?? 0);
    if ($roleLevel >= 80) {
        return true;
    }
    
    // Default: if not an engineer role, check permissions as fallback
    $permissionEngine = new PermissionEngine();
    return $permissionEngine->can($userId, 'contractor.delegations.view') || 
           $permissionEngine->can($userId, 'contractor.assignments.manage');
}

/**
 * Check if current user is an engineer (contractor user with engineer role)
 * Engineers are contractor users who can be assigned to sites
 */
function isEngineerUser($userId = null) {
    static $sessionService = null;
    static $userModel = null;
    
    if ($sessionService === null) {
        $sessionService = new SessionService();
    }
    
    if ($userModel === null) {
        $userModel = new User();
    }
    
    if ($userId === null) {
        $userId = $sessionService->getCurrentUserId();
        if (!$userId) {
            return false;
        }
    }
    
    $user = $userModel->findWithRelations($userId);
    // Engineers are contractor users - they can view their assigned sites
    // For now, all contractor users can access the engineer portal
    return $user && strtoupper($user['company_type']) === 'CONTRACTOR';
}

/**
 * Get current user's company ID
 */
function getCurrentUserCompanyId($userId = null) {
    static $sessionService = null;
    static $userModel = null;
    
    if ($sessionService === null) {
        $sessionService = new SessionService();
    }
    
    if ($userModel === null) {
        $userModel = new User();
    }
    
    if ($userId === null) {
        $userId = $sessionService->getCurrentUserId();
        if (!$userId) {
            return null;
        }
    }
    
    $user = $userModel->findWithRelations($userId);
    return $user ? $user['company_id'] : null;
}

/**
 * Check if user can access specific company data
 */
function canAccessCompany($companyId, $userId = null) {
    static $sessionService = null;
    static $userModel = null;
    
    if ($sessionService === null) {
        $sessionService = new SessionService();
    }
    
    if ($userModel === null) {
        $userModel = new User();
    }
    
    if ($userId === null) {
        $userId = $sessionService->getCurrentUserId();
        if (!$userId) {
            return false;
        }
    }
    
    $user = $userModel->findWithRelations($userId);
    if (!$user) {
        return false;
    }
    
    // ADV users can access any company
    if (strtoupper($user['company_type']) === 'ADV') {
        return true;
    }
    
    // Contractor users can only access their own company
    return $user['company_id'] == $companyId;
}

/**
 * Get all permissions for current user
 */
function getCurrentUserPermissions($userId = null) {
    static $permissionEngine = null;
    static $sessionService = null;
    
    if ($permissionEngine === null) {
        $permissionEngine = new PermissionEngine();
    }
    
    if ($sessionService === null) {
        $sessionService = new SessionService();
    }
    
    if ($userId === null) {
        $userId = $sessionService->getCurrentUserId();
        if (!$userId) {
            return [];
        }
    }
    
    return $permissionEngine->getUserPermissions($userId);
}

/**
 * Log permission denial for audit purposes
 */
function logPermissionDenial($permissionName, $userId = null, $context = []) {
    static $sessionService = null;
    
    if ($sessionService === null) {
        $sessionService = new SessionService();
    }
    
    if ($userId === null) {
        $userId = $sessionService->getCurrentUserId();
    }
    
    $logData = [
        'user_id' => $userId,
        'permission' => $permissionName,
        'timestamp' => date('Y-m-d H:i:s'),
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'context' => $context
    ];
    
    error_log("Permission denied: " . json_encode($logData));
}