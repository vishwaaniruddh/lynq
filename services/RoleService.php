<?php
/**
 * Role Service
 * Handles business logic for role management operations
 * 
 * This service enforces:
 * - Role assignment type restrictions (ADV roles for ADV users, contractor roles for contractor users)
 * - Role hierarchy validation
 * - Permission mapping for roles
 * - Audit logging for role changes
 */

require_once __DIR__ . '/../config/autoload.php';

class RoleService {
    private $db;
    private $roleModel;
    private $companyModel;
    private $userModel;
    private $permissionEngine;
    
    public function __construct() {
        $this->db = DatabaseConfig::getInstance();
        $this->roleModel = new Role();
        $this->companyModel = new Company();
        $this->userModel = new User();
        $this->permissionEngine = new PermissionEngine();
    }
    
    /**
     * Validate role assignment for a user
     * Enforces that ADV roles cannot be assigned to contractor users and vice versa
     * 
     * @param int $roleId Role ID to assign
     * @param int $companyId Company ID of the user
     * @return array ['valid' => bool, 'message' => string]
     */
    public function validateRoleAssignment(int $roleId, int $companyId): array {
        $company = $this->companyModel->find($companyId);
        if (!$company) {
            return [
                'valid' => false,
                'message' => 'Company not found'
            ];
        }
        
        return $this->roleModel->validateRoleAssignment($roleId, $company['type']);
    }
    
    /**
     * Validate role assignment with hierarchy check
     * Ensures the assigning user has sufficient level to assign the role
     * 
     * @param int $roleId Role ID to assign
     * @param int $companyId Company ID of the target user
     * @param int $assignerUserId User ID of the person assigning the role
     * @return array ['valid' => bool, 'message' => string]
     */
    public function validateRoleAssignmentWithHierarchy(int $roleId, int $companyId, int $assignerUserId): array {
        // First validate type restrictions
        $typeValidation = $this->validateRoleAssignment($roleId, $companyId);
        if (!$typeValidation['valid']) {
            return $typeValidation;
        }
        
        // Get assigner's role
        $assigner = $this->userModel->findWithRelations($assignerUserId);
        if (!$assigner) {
            return [
                'valid' => false,
                'message' => 'Assigning user not found'
            ];
        }
        
        // Check hierarchy
        if (!$this->roleModel->canAssignRoleByHierarchy($assigner['role_id'], $roleId)) {
            return [
                'valid' => false,
                'message' => 'Cannot assign a role with higher level than your own'
            ];
        }
        
        return [
            'valid' => true,
            'message' => 'Role assignment is valid'
        ];
    }
    
    /**
     * Check if a role can be assigned to a company type
     * 
     * @param int $roleId Role ID
     * @param string $companyType Company type (ADV or CONTRACTOR)
     * @return bool True if role can be assigned
     */
    public function canAssignRoleToCompanyType(int $roleId, string $companyType): bool {
        return $this->roleModel->canAssignToCompanyType($roleId, $companyType);
    }
    
    /**
     * Get available roles for a company
     * Returns roles that can be assigned to users in this company
     * 
     * @param int $companyId Company ID
     * @return array List of available roles
     */
    public function getAvailableRolesForCompany(int $companyId): array {
        $company = $this->companyModel->find($companyId);
        if (!$company) {
            return [];
        }
        
        return $this->roleModel->findByCompanyType($company['type']);
    }
    
    /**
     * Get roles that a user can assign based on their role level and target company
     * 
     * @param int $assignerUserId User ID of the person assigning
     * @param int $targetCompanyId Company ID of the target user
     * @return array List of assignable roles
     */
    public function getAssignableRolesForUser(int $assignerUserId, int $targetCompanyId): array {
        $assigner = $this->userModel->findWithRelations($assignerUserId);
        if (!$assigner) {
            return [];
        }
        
        $targetCompany = $this->companyModel->find($targetCompanyId);
        if (!$targetCompany) {
            return [];
        }
        
        return $this->roleModel->getAssignableRoles($assigner['role_id'], $targetCompany['type']);
    }
    
    /**
     * Get all roles with their details
     * 
     * @return array List of all roles
     */
    public function getAllRoles(): array {
        return $this->roleModel->findAllWithPermissionCounts();
    }
    
    /**
     * Get role by ID with permissions
     * 
     * @param int $roleId Role ID
     * @return array|null Role data or null if not found
     */
    public function getRoleWithPermissions(int $roleId): ?array {
        return $this->roleModel->findWithPermissions($roleId);
    }
    
    /**
     * Get ADV-only roles
     * 
     * @return array List of ADV-only roles
     */
    public function getAdvOnlyRoles(): array {
        return $this->roleModel->findAdvOnlyRoles();
    }
    
    /**
     * Get contractor-only roles
     * 
     * @return array List of contractor-only roles
     */
    public function getContractorOnlyRoles(): array {
        return $this->roleModel->findContractorOnlyRoles();
    }
    
    /**
     * Check if role is ADV-only
     * 
     * @param int $roleId Role ID
     * @return bool True if role is ADV-only
     */
    public function isAdvOnlyRole(int $roleId): bool {
        return $this->roleModel->isAdvOnlyRole($roleId);
    }
    
    /**
     * Check if role is contractor-only
     * 
     * @param int $roleId Role ID
     * @return bool True if role is contractor-only
     */
    public function isContractorOnlyRole(int $roleId): bool {
        return $this->roleModel->isContractorOnlyRole($roleId);
    }
    
    /**
     * Get role level
     * 
     * @param int $roleId Role ID
     * @return int|null Role level or null if not found
     */
    public function getRoleLevel(int $roleId): ?int {
        return $this->roleModel->getRoleLevel($roleId);
    }
    
    /**
     * Assign permission to role
     * 
     * @param int $roleId Role ID
     * @param int $permissionId Permission ID
     * @param int $actingUserId User performing the action
     * @return bool Success status
     */
    public function assignPermissionToRole(int $roleId, int $permissionId, int $actingUserId): bool {
        $result = $this->roleModel->assignPermission($roleId, $permissionId);
        
        if ($result) {
            $this->logRoleAction($actingUserId, $roleId, 'permission_assigned', [
                'permission_id' => $permissionId
            ]);
        }
        
        return $result;
    }
    
    /**
     * Remove permission from role
     * 
     * @param int $roleId Role ID
     * @param int $permissionId Permission ID
     * @param int $actingUserId User performing the action
     * @return bool Success status
     */
    public function removePermissionFromRole(int $roleId, int $permissionId, int $actingUserId): bool {
        $result = $this->roleModel->removePermission($roleId, $permissionId);
        
        if ($result) {
            $this->logRoleAction($actingUserId, $roleId, 'permission_removed', [
                'permission_id' => $permissionId
            ]);
        }
        
        return $result;
    }
    
    /**
     * Get all permissions for a role
     * 
     * @param int $roleId Role ID
     * @return array List of permissions
     */
    public function getRolePermissions(int $roleId): array {
        return $this->roleModel->getPermissions($roleId);
    }
    
    /**
     * Validate that a role assignment follows type restrictions
     * This is the core validation for Property 3
     * 
     * @param int $roleId Role ID to assign
     * @param string $userCompanyType Company type of the user (ADV or CONTRACTOR)
     * @return bool True if assignment is valid
     */
    public function isValidRoleTypeAssignment(int $roleId, string $userCompanyType): bool {
        $role = $this->roleModel->find($roleId);
        if (!$role) {
            return false;
        }
        
        // BOTH type roles can be assigned to any company type
        if ($role['company_type'] === Role::TYPE_BOTH) {
            return true;
        }
        
        // Role type must match company type
        return $role['company_type'] === $userCompanyType;
    }
    
    /**
     * Log role action for audit trail
     * 
     * @param int $actingUserId User performing the action
     * @param int $roleId Role being acted upon
     * @param string $action Action type
     * @param array $details Additional details
     */
    private function logRoleAction(int $actingUserId, int $roleId, string $action, array $details): void {
        try {
            $sql = "INSERT INTO user_audit_log (user_id, action, details, performed_by, ip_address) 
                    VALUES (?, ?, ?, ?, ?)";
            
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'CLI';
            $details['role_id'] = $roleId;
            
            $stmt = $this->db->executeQuery($sql, [
                $actingUserId,
                $action,
                json_encode($details),
                $actingUserId,
                $ipAddress
            ], 'issis');
            $stmt->close();
        } catch (Exception $e) {
            error_log("Failed to log role action: " . $e->getMessage());
        }
    }
}
