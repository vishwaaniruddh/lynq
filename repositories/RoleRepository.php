<?php
/**
 * Role Repository
 * Handles data access for roles with company isolation awareness
 */

require_once __DIR__ . '/BaseRepository.php';

class RoleRepository extends BaseRepository {
    protected $table = 'roles';
    
    /**
     * Find all roles with permission counts
     * 
     * @return array List of roles with permission counts
     */
    public function findAllWithPermissionCounts(): array {
        $sql = "SELECT r.*, COUNT(rp.permission_id) as permission_count
                FROM `{$this->table}` r
                LEFT JOIN role_permissions rp ON r.id = rp.role_id
                WHERE r.is_active = 1
                GROUP BY r.id
                ORDER BY r.level DESC, r.name";
        
        return $this->db->getResults($sql);
    }
    
    /**
     * Find role with permissions
     * 
     * @param int $id Role ID
     * @return array|null Role with permissions or null
     */
    public function findWithPermissions(int $id): ?array {
        $sql = "SELECT r.*, GROUP_CONCAT(p.name) as permissions
                FROM `{$this->table}` r
                LEFT JOIN role_permissions rp ON r.id = rp.role_id
                LEFT JOIN permissions p ON rp.permission_id = p.id
                WHERE r.id = ?
                GROUP BY r.id";
        
        $result = $this->db->getResults($sql, [$id], 'i');
        
        if (!empty($result)) {
            $role = $result[0];
            $role['permissions'] = $role['permissions'] ? explode(',', $role['permissions']) : [];
            return $role;
        }
        
        return null;
    }
    
    /**
     * Find roles by company type
     * 
     * @param string $companyType Company type (ADV, CONTRACTOR)
     * @return array List of roles
     */
    public function findByCompanyType(string $companyType): array {
        $sql = "SELECT * FROM `{$this->table}` 
                WHERE (`company_type` = ? OR `company_type` = 'BOTH') 
                AND `is_active` = 1 
                ORDER BY `level` DESC, `name`";
        
        return $this->db->getResults($sql, [$companyType], 's');
    }
    
    /**
     * Find ADV-only roles
     * 
     * @return array List of ADV-only roles
     */
    public function findAdvOnlyRoles(): array {
        $sql = "SELECT * FROM `{$this->table}` 
                WHERE `company_type` = 'ADV' 
                AND `is_active` = 1 
                ORDER BY `level` DESC, `name`";
        
        return $this->db->getResults($sql);
    }
    
    /**
     * Find contractor-only roles
     * 
     * @return array List of contractor-only roles
     */
    public function findContractorOnlyRoles(): array {
        $sql = "SELECT * FROM `{$this->table}` 
                WHERE `company_type` = 'CONTRACTOR' 
                AND `is_active` = 1 
                ORDER BY `level` DESC, `name`";
        
        return $this->db->getResults($sql);
    }
    
    /**
     * Find roles assignable by a user based on their role level
     * 
     * @param int $assignerRoleLevel Level of the assigning user's role
     * @param string|null $companyType Optional company type filter
     * @return array List of assignable roles
     */
    public function findAssignableRoles(int $assignerRoleLevel, ?string $companyType = null): array {
        $sql = "SELECT * FROM `{$this->table}` WHERE `level` <= ? AND `is_active` = 1";
        $params = [$assignerRoleLevel];
        $types = 'i';
        
        if ($companyType !== null) {
            $sql .= " AND (`company_type` = ? OR `company_type` = 'BOTH')";
            $params[] = $companyType;
            $types .= 's';
        }
        
        $sql .= " ORDER BY `level` DESC, `name`";
        
        return $this->db->getResults($sql, $params, $types);
    }
    
    /**
     * Get role permissions
     * 
     * @param int $roleId Role ID
     * @return array List of permissions
     */
    public function getPermissions(int $roleId): array {
        $sql = "SELECT p.* FROM `permissions` p
                INNER JOIN `role_permissions` rp ON p.id = rp.permission_id
                WHERE rp.role_id = ?
                ORDER BY p.module, p.action";
        
        return $this->db->getResults($sql, [$roleId], 'i');
    }
    
    /**
     * Assign permission to role
     * 
     * @param int $roleId Role ID
     * @param int $permissionId Permission ID
     * @return bool Success status
     */
    public function assignPermission(int $roleId, int $permissionId): bool {
        $sql = "INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`) VALUES (?, ?)";
        $stmt = $this->db->executeQuery($sql, [$roleId, $permissionId], 'ii');
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
    public function removePermission(int $roleId, int $permissionId): bool {
        $sql = "DELETE FROM `role_permissions` WHERE `role_id` = ? AND `permission_id` = ?";
        $stmt = $this->db->executeQuery($sql, [$roleId, $permissionId], 'ii');
        $stmt->close();
        return true;
    }
    
    /**
     * Check if role has permission
     * 
     * @param int $roleId Role ID
     * @param int $permissionId Permission ID
     * @return bool True if role has permission
     */
    public function hasPermission(int $roleId, int $permissionId): bool {
        $sql = "SELECT COUNT(*) as count FROM `role_permissions` 
                WHERE `role_id` = ? AND `permission_id` = ?";
        
        $result = $this->db->getResults($sql, [$roleId, $permissionId], 'ii');
        
        return !empty($result) && $result[0]['count'] > 0;
    }
    
    /**
     * Get role by name and company type
     * 
     * @param string $name Role name
     * @param string $companyType Company type
     * @return array|null Role or null
     */
    public function findByNameAndType(string $name, string $companyType): ?array {
        $sql = "SELECT * FROM `{$this->table}` 
                WHERE `name` = ? AND `company_type` = ?";
        
        $result = $this->db->getResults($sql, [$name, $companyType], 'ss');
        
        return !empty($result) ? $result[0] : null;
    }
}
