<?php
/**
 * Permission Model
 */

require_once 'BaseModel.php';

class Permission extends BaseModel {
    protected $table = 'permissions';
    protected $fillable = [
        'name', 'module', 'action', 'description', 'is_adv_only'
    ];
    
    /**
     * Find permissions by module
     */
    public function findByModule($module) {
        return $this->findAll(['module' => $module], 'action');
    }
    
    /**
     * Find ADV-only permissions
     */
    public function findAdvOnly() {
        return $this->findAll(['is_adv_only' => 1], 'module, action');
    }
    
    /**
     * Find contractor-accessible permissions
     */
    public function findContractorAccessible() {
        return $this->findAll(['is_adv_only' => 0], 'module, action');
    }
    
    /**
     * Find permission by name
     */
    public function findByName($name) {
        $sql = "SELECT * FROM `{$this->table}` WHERE `name` = ?";
        $result = DatabaseConfig::getInstance()->getResults($sql, [$name], 's');
        
        return !empty($result) ? $result[0] : null;
    }
    
    /**
     * Get permissions grouped by module
     */
    public function findGroupedByModule() {
        $sql = "SELECT * FROM `{$this->table}` ORDER BY `module`, `action`";
        $permissions = DatabaseConfig::getInstance()->getResults($sql);
        
        $grouped = [];
        foreach ($permissions as $permission) {
            $grouped[$permission['module']][] = $permission;
        }
        
        return $grouped;
    }
    
    /**
     * Check if permission is ADV only
     */
    public function isAdvOnly($id) {
        $permission = $this->find($id);
        return $permission && $permission['is_adv_only'] == 1;
    }
    
    /**
     * Get user permissions
     */
    public function findUserPermissions($userId) {
        $sql = "SELECT DISTINCT p.*
                FROM `{$this->table}` p
                INNER JOIN role_permissions rp ON p.id = rp.permission_id
                INNER JOIN users u ON rp.role_id = u.role_id
                WHERE u.id = ?
                ORDER BY p.module, p.action";
        
        return DatabaseConfig::getInstance()->getResults($sql, [$userId], 'i');
    }
    
    /**
     * Get company delegated permissions
     */
    public function findCompanyPermissions($companyId) {
        $sql = "SELECT p.*
                FROM `{$this->table}` p
                INNER JOIN company_permissions cp ON p.id = cp.permission_id
                WHERE cp.company_id = ? AND cp.is_active = 1
                ORDER BY p.module, p.action";
        
        return DatabaseConfig::getInstance()->getResults($sql, [$companyId], 'i');
    }
    
    /**
     * Check if user has permission
     */
    public function userHasPermission($userId, $permissionName) {
        $sql = "SELECT COUNT(*) as count
                FROM `{$this->table}` p
                INNER JOIN role_permissions rp ON p.id = rp.permission_id
                INNER JOIN users u ON rp.role_id = u.role_id
                WHERE u.id = ? AND p.name = ?";
        
        $result = DatabaseConfig::getInstance()->getResults($sql, [$userId, $permissionName], 'is');
        
        return !empty($result) && $result[0]['count'] > 0;
    }
    
    /**
     * Check if company has delegated permission
     */
    public function companyHasPermission($companyId, $permissionName) {
        $sql = "SELECT COUNT(*) as count
                FROM `{$this->table}` p
                INNER JOIN company_permissions cp ON p.id = cp.permission_id
                WHERE cp.company_id = ? AND p.name = ? AND cp.is_active = 1";
        
        $result = DatabaseConfig::getInstance()->getResults($sql, [$companyId, $permissionName], 'is');
        
        return !empty($result) && $result[0]['count'] > 0;
    }
}