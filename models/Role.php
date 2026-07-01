<?php
/**
 * Role Model
 * 
 * Handles role data with level hierarchy and company type restrictions.
 * Roles define user identity and determine which permissions can be assigned.
 * 
 * Level hierarchy: Higher level = more authority
 * Company type: ADV, CONTRACTOR, or BOTH
 */

require_once 'BaseModel.php';

class Role extends BaseModel {
    protected $table = 'roles';
    protected $fillable = [
        'name', 'level', 'company_type', 'description', 'is_active'
    ];
    
    // Company type constants
    const TYPE_ADV = 'ADV';
    const TYPE_CONTRACTOR = 'CONTRACTOR';
    const TYPE_BOTH = 'BOTH';
    
    /**
     * Find roles by company type
     * Returns roles that match the company type OR are marked as 'BOTH'
     */
    public function findByCompanyType($companyType) {
        $sql = "SELECT * FROM `{$this->table}` WHERE `company_type` IN (?, 'BOTH') AND `is_active` = 1 ORDER BY `level` DESC, `name`";
        return DatabaseConfig::getInstance()->getResults($sql, [$companyType], 's');
    }
    
    /**
     * Find ADV-only roles (excludes BOTH type)
     */
    public function findAdvOnlyRoles() {
        $sql = "SELECT * FROM `{$this->table}` WHERE `company_type` = 'ADV' AND `is_active` = 1 ORDER BY `level` DESC, `name`";
        return DatabaseConfig::getInstance()->getResults($sql);
    }
    
    /**
     * Find contractor-only roles (excludes BOTH type)
     */
    public function findContractorOnlyRoles() {
        $sql = "SELECT * FROM `{$this->table}` WHERE `company_type` = 'CONTRACTOR' AND `is_active` = 1 ORDER BY `level` DESC, `name`";
        return DatabaseConfig::getInstance()->getResults($sql);
    }
    
    /**
     * Find ADV roles (includes BOTH type)
     */
    public function findAdvRoles() {
        return $this->findByCompanyType(self::TYPE_ADV);
    }
    
    /**
     * Find contractor roles (includes BOTH type)
     */
    public function findContractorRoles() {
        return $this->findByCompanyType(self::TYPE_CONTRACTOR);
    }
    
    /**
     * Check if role is ADV only (not BOTH)
     */
    public function isAdvOnlyRole($id) {
        $role = $this->find($id);
        return $role && $role['company_type'] === self::TYPE_ADV;
    }
    
    /**
     * Check if role is ADV only
     * @deprecated Use isAdvOnlyRole() for clarity
     */
    public function isAdvRole($id) {
        return $this->isAdvOnlyRole($id);
    }
    
    /**
     * Check if role is contractor only (not BOTH)
     */
    public function isContractorOnlyRole($id) {
        $role = $this->find($id);
        return $role && $role['company_type'] === self::TYPE_CONTRACTOR;
    }
    
    /**
     * Check if role is contractor only
     * @deprecated Use isContractorOnlyRole() for clarity
     */
    public function isContractorRole($id) {
        return $this->isContractorOnlyRole($id);
    }
    
    /**
     * Check if role can be assigned to a company type
     * 
     * @param int $roleId Role ID
     * @param string $companyType Company type (ADV or CONTRACTOR)
     * @return bool True if role can be assigned to this company type
     */
    public function canAssignToCompanyType($roleId, $companyType) {
        $role = $this->find($roleId);
        if (!$role) {
            return false;
        }
        
        // BOTH type roles can be assigned to any company
        if ($role['company_type'] === self::TYPE_BOTH) {
            return true;
        }
        
        // Role type must match company type
        return $role['company_type'] === $companyType;
    }
    
    /**
     * Validate role assignment for a user in a company
     * 
     * @param int $roleId Role ID to assign
     * @param string $companyType Company type of the user's company
     * @return array ['valid' => bool, 'message' => string]
     */
    public function validateRoleAssignment($roleId, $companyType) {
        $role = $this->find($roleId);
        
        if (!$role) {
            return [
                'valid' => false,
                'message' => 'Role not found'
            ];
        }
        
        if (!$role['is_active']) {
            return [
                'valid' => false,
                'message' => 'Role is not active'
            ];
        }
        
        // BOTH type roles can be assigned to any company
        if ($role['company_type'] === self::TYPE_BOTH) {
            return [
                'valid' => true,
                'message' => 'Role can be assigned to any company type'
            ];
        }
        
        // Check type match
        if ($role['company_type'] !== $companyType) {
            if ($role['company_type'] === self::TYPE_ADV && $companyType === self::TYPE_CONTRACTOR) {
                return [
                    'valid' => false,
                    'message' => 'ADV roles cannot be assigned to contractor users'
                ];
            }
            if ($role['company_type'] === self::TYPE_CONTRACTOR && $companyType === self::TYPE_ADV) {
                return [
                    'valid' => false,
                    'message' => 'Contractor roles cannot be assigned to ADV users'
                ];
            }
        }
        
        return [
            'valid' => true,
            'message' => 'Role assignment is valid'
        ];
    }
    
    /**
     * Get role level
     * 
     * @param int $roleId Role ID
     * @return int|null Role level or null if not found
     */
    public function getRoleLevel($roleId) {
        $role = $this->find($roleId);
        return $role ? (int)$role['level'] : null;
    }
    
    /**
     * Check if one role has higher or equal level than another
     * 
     * @param int $roleId Role to check
     * @param int $targetRoleId Role to compare against
     * @return bool True if roleId has higher or equal level
     */
    public function hasHigherOrEqualLevel($roleId, $targetRoleId) {
        $roleLevel = $this->getRoleLevel($roleId);
        $targetLevel = $this->getRoleLevel($targetRoleId);
        
        if ($roleLevel === null || $targetLevel === null) {
            return false;
        }
        
        return $roleLevel >= $targetLevel;
    }
    
    /**
     * Check if user can assign a role based on their own role level
     * Users can only assign roles at or below their own level
     * 
     * @param int $assignerRoleId Role ID of the user assigning
     * @param int $targetRoleId Role ID being assigned
     * @return bool True if assignment is allowed by hierarchy
     */
    public function canAssignRoleByHierarchy($assignerRoleId, $targetRoleId) {
        return $this->hasHigherOrEqualLevel($assignerRoleId, $targetRoleId);
    }
    
    /**
     * Get roles that a user can assign based on their role level
     * 
     * @param int $assignerRoleId Role ID of the user assigning
     * @param string|null $companyType Optional company type filter
     * @return array List of assignable roles
     */
    public function getAssignableRoles($assignerRoleId, $companyType = null) {
        $assignerLevel = $this->getRoleLevel($assignerRoleId);
        
        if ($assignerLevel === null) {
            return [];
        }
        
        $sql = "SELECT * FROM `{$this->table}` WHERE `level` <= ? AND `is_active` = 1";
        $params = [$assignerLevel];
        $types = 'i';
        
        if ($companyType !== null) {
            $sql .= " AND (`company_type` = ? OR `company_type` = 'BOTH')";
            $params[] = $companyType;
            $types .= 's';
        }
        
        $sql .= " ORDER BY `level` DESC, `name`";
        
        return DatabaseConfig::getInstance()->getResults($sql, $params, $types);
    }
    
    /**
     * Get role with permissions
     */
    public function findWithPermissions($id) {
        $sql = "SELECT r.*, GROUP_CONCAT(p.name) as permissions
                FROM `{$this->table}` r
                LEFT JOIN role_permissions rp ON r.id = rp.role_id
                LEFT JOIN permissions p ON rp.permission_id = p.id
                WHERE r.id = ?
                GROUP BY r.id";
        
        $result = DatabaseConfig::getInstance()->getResults($sql, [$id], 'i');
        
        if (!empty($result)) {
            $role = $result[0];
            $role['permissions'] = $role['permissions'] ? explode(',', $role['permissions']) : [];
            return $role;
        }
        
        return null;
    }
    
    /**
     * Get all roles with permission counts
     */
    public function findAllWithPermissionCounts() {
        $sql = "SELECT r.*, COUNT(rp.permission_id) as permission_count
                FROM `{$this->table}` r
                LEFT JOIN role_permissions rp ON r.id = rp.role_id
                WHERE r.is_active = 1
                GROUP BY r.id
                ORDER BY r.level DESC, r.name";
        
        return DatabaseConfig::getInstance()->getResults($sql);
    }
    
    /**
     * Get roles by level range
     * 
     * @param int $minLevel Minimum level (inclusive)
     * @param int $maxLevel Maximum level (inclusive)
     * @return array List of roles
     */
    public function findByLevelRange($minLevel, $maxLevel) {
        $sql = "SELECT * FROM `{$this->table}` WHERE `level` >= ? AND `level` <= ? AND `is_active` = 1 ORDER BY `level` DESC, `name`";
        return DatabaseConfig::getInstance()->getResults($sql, [$minLevel, $maxLevel], 'ii');
    }
    
    /**
     * Assign permission to role
     * 
     * @param int $roleId Role ID
     * @param int $permissionId Permission ID
     * @return bool Success status
     */
    public function assignPermission($roleId, $permissionId) {
        $sql = "INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`) VALUES (?, ?)";
        $stmt = DatabaseConfig::getInstance()->executeQuery($sql, [$roleId, $permissionId], 'ii');
        $stmt->close();
        return true;
    }
    
    /**
     * Remove permission from role
     * 
     * @param int $roleId Role ID
     * @param int $permissionId Permission ID
     * @return bool Success status
     */
    public function removePermission($roleId, $permissionId) {
        $sql = "DELETE FROM `role_permissions` WHERE `role_id` = ? AND `permission_id` = ?";
        $stmt = DatabaseConfig::getInstance()->executeQuery($sql, [$roleId, $permissionId], 'ii');
        $stmt->close();
        return true;
    }
    
    /**
     * Get all permissions for a role
     * 
     * @param int $roleId Role ID
     * @return array List of permissions
     */
    public function getPermissions($roleId) {
        $sql = "SELECT p.* FROM `permissions` p
                INNER JOIN `role_permissions` rp ON p.id = rp.permission_id
                WHERE rp.role_id = ?
                ORDER BY p.module, p.action";
        return DatabaseConfig::getInstance()->getResults($sql, [$roleId], 'i');
    }
}