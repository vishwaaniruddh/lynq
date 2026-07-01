<?php
/**
 * Permission Engine Service
 * Handles authorization logic and permission checking
 */

require_once __DIR__ . '/../config/autoload.php';

class PermissionEngine {
    private $db;
    private $permissionModel;
    private $userModel;
    private $companyModel;
    
    public function __construct() {
        $this->db = DatabaseConfig::getInstance();
        $this->permissionModel = new Permission();
        $this->userModel = new User();
        $this->companyModel = new Company();
    }
    
    /**
     * Check if user has permission
     * This is the main can() function for consistent permission checking
     * 
     * For ADV users: Check role-based permissions
     * For Contractor users: 
     *   - For ADV-only permissions: Check company delegation ONLY
     *   - For non-ADV-only permissions: Check role-based permissions
     *   - Per Requirement 4.2: Contractor users must have permission through company delegation for ADV-only perms
     *   - Per Requirement 4.3: When ADV revokes permissions, access is immediately restricted
     */
    public function can($userId, $permissionName) {
        try {
            // Get user with company information
            $user = $this->userModel->findWithRelations($userId);
            if (!$user) {
                return false;
            }
            
            // Normalize company type for comparison
            $companyType = strtoupper($user['company_type'] ?? '');
            
            // For ADV users, check direct permission through role
            if ($companyType === 'ADV') {
                return $this->userHasDirectPermission($userId, $permissionName);
            }
            
            // For contractor users
            if ($companyType === 'CONTRACTOR') {
                // First check if this is an ADV-only permission
                $permission = $this->permissionModel->findByName($permissionName);
                
                if ($permission && $permission['is_adv_only']) {
                    // ADV-only permissions require company delegation
                    return $this->companyHasDelegatedPermission($user['company_id'], $permissionName);
                }
                
                // For non-ADV-only permissions (like contractor portal permissions),
                // check role-based permissions first, then company delegation
                if ($this->userHasDirectPermission($userId, $permissionName)) {
                    return true;
                }
                
                // Also check company delegation as fallback
                return $this->companyHasDelegatedPermission($user['company_id'], $permissionName);
            }
            
            return false;
        } catch (Exception $e) {
            error_log("Permission check failed for user $userId, permission $permissionName: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check if user has direct permission through their role
     */
    private function userHasDirectPermission($userId, $permissionName) {
        return $this->permissionModel->userHasPermission($userId, $permissionName);
    }
    
    /**
     * Check if company has delegated permission
     */
    private function companyHasDelegatedPermission($companyId, $permissionName) {
        return $this->permissionModel->companyHasPermission($companyId, $permissionName);
    }
    
    /**
     * Get all permissions for a user
     */
    public function getUserPermissions($userId) {
        $user = $this->userModel->findWithRelations($userId);
        if (!$user) {
            return [];
        }
        
        $permissions = [];
        
        // Get direct permissions from role
        $rolePermissions = $this->permissionModel->findUserPermissions($userId);
        foreach ($rolePermissions as $permission) {
            $permissions[$permission['name']] = [
                'source' => 'role',
                'permission' => $permission
            ];
        }
        
        // For contractor users, add delegated permissions
        if (strtoupper($user['company_type'] ?? '') === 'CONTRACTOR') {
            $delegatedPermissions = $this->permissionModel->findCompanyPermissions($user['company_id']);
            foreach ($delegatedPermissions as $permission) {
                $permissions[$permission['name']] = [
                    'source' => 'delegation',
                    'permission' => $permission
                ];
            }
        }
        
        return $permissions;
    }
    
    /**
     * Delegate permission to a company
     */
    public function delegatePermission($companyId, $permissionName, $grantedBy) {
        try {
            // Verify the granting user has permission to delegate
            $grantingUser = $this->userModel->findWithRelations($grantedBy);
            if (!$grantingUser || strtoupper($grantingUser['company_type'] ?? '') !== 'ADV') {
                throw new Exception("Only ADV users can delegate permissions");
            }
            
            // Verify the permission exists
            $permission = $this->permissionModel->findByName($permissionName);
            if (!$permission) {
                throw new Exception("Permission '$permissionName' does not exist");
            }
            
            // Verify target company is contractor
            $targetCompany = $this->companyModel->find($companyId);
            if (!$targetCompany || $targetCompany['type'] !== 'CONTRACTOR') {
                throw new Exception("Can only delegate permissions to contractor companies");
            }
            
            // Check if permission is already delegated
            if ($this->companyHasDelegatedPermission($companyId, $permissionName)) {
                return true; // Already delegated
            }
            
            // Insert delegation record
            $sql = "INSERT INTO company_permissions (company_id, permission_id, granted_by, granted_at, is_active) 
                    VALUES (?, ?, ?, NOW(), 1)";
            $stmt = $this->db->executeQuery($sql, [$companyId, $permission['id'], $grantedBy], 'iii');
            $stmt->close();
            
            // Log the delegation
            $this->logPermissionAction($companyId, $permission['id'], 'DELEGATED', $grantedBy);
            
            return true;
        } catch (Exception $e) {
            error_log("Permission delegation failed: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Revoke permission from a company
     */
    public function revokePermission($companyId, $permissionName, $revokedBy) {
        try {
            // Verify the revoking user has permission to revoke
            $revokingUser = $this->userModel->findWithRelations($revokedBy);
            if (!$revokingUser || strtoupper($revokingUser['company_type'] ?? '') !== 'ADV') {
                throw new Exception("Only ADV users can revoke permissions");
            }
            
            // Get permission details
            $permission = $this->permissionModel->findByName($permissionName);
            if (!$permission) {
                throw new Exception("Permission '$permissionName' does not exist");
            }
            
            // Deactivate the delegation
            $sql = "UPDATE company_permissions 
                    SET is_active = 0, revoked_by = ?, revoked_at = NOW() 
                    WHERE company_id = ? AND permission_id = ? AND is_active = 1";
            $stmt = $this->db->executeQuery($sql, [$revokedBy, $companyId, $permission['id']], 'iii');
            $affectedRows = $stmt->affected_rows;
            $stmt->close();
            
            if ($affectedRows > 0) {
                // Log the revocation
                $this->logPermissionAction($companyId, $permission['id'], 'REVOKED', $revokedBy);
            }
            
            return $affectedRows > 0;
        } catch (Exception $e) {
            error_log("Permission revocation failed: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Get all delegated permissions for a company
     */
    public function getCompanyDelegatedPermissions($companyId) {
        return $this->permissionModel->findCompanyPermissions($companyId);
    }
    
    /**
     * Check if permission is ADV-only
     */
    public function isAdvOnlyPermission($permissionName) {
        $permission = $this->permissionModel->findByName($permissionName);
        return $permission && $permission['is_adv_only'] == 1;
    }
    
    /**
     * Log permission actions for audit trail
     */
    private function logPermissionAction($companyId, $permissionId, $action, $performedBy) {
        $sql = "INSERT INTO permission_audit_log (company_id, permission_id, action, performed_by, timestamp) 
                VALUES (?, ?, ?, ?, NOW())";
        $stmt = $this->db->executeQuery($sql, [$companyId, $permissionId, $action, $performedBy], 'iisi');
        $stmt->close();
    }
    
    /**
     * Get permission audit trail for a company
     */
    public function getPermissionAuditTrail($companyId, $limit = 100) {
        $sql = "SELECT pal.*, p.name as permission_name, p.module, p.action as perm_action,
                       u.username as performed_by_username
                FROM permission_audit_log pal
                INNER JOIN permissions p ON pal.permission_id = p.id
                INNER JOIN users u ON pal.performed_by = u.id
                WHERE pal.company_id = ?
                ORDER BY pal.timestamp DESC
                LIMIT ?";
        
        return $this->db->getResults($sql, [$companyId, $limit], 'ii');
    }
    
    /**
     * Validate permission name format (module.action)
     */
    public function validatePermissionFormat($permissionName) {
        return preg_match('/^[a-z_]+\.[a-z_]+$/', $permissionName);
    }
    
    /**
     * Get permissions grouped by module
     */
    public function getPermissionsGroupedByModule() {
        return $this->permissionModel->findGroupedByModule();
    }
}