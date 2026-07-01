<?php
/**
 * User Repository with Company Isolation
 * Provides company-aware user data access
 */

require_once __DIR__ . '/BaseRepository.php';

class UserRepository extends BaseRepository {
    protected $table = 'users';
    protected $companyIdColumn = 'company_id';
    
    /**
     * Find user by email with company isolation
     */
    public function findByEmail($email) {
        $sql = "SELECT * FROM `{$this->table}` WHERE `email` = ?";
        $params = [$email];
        $types = 's';
        
        // Add company filter if enabled and user is set
        if ($this->applyCompanyFilter && $this->currentUserId) {
            $filter = $this->companyIsolationService->getCompanyFilterClause(
                $this->currentUserId, 
                $this->companyIdColumn
            );
            $sql .= " AND " . $filter['clause'];
            $params = array_merge($params, $filter['params']);
            $types .= $filter['types'];
        }
        
        $result = $this->db->getResults($sql, $params, $types);
        return !empty($result) ? $result[0] : null;
    }
    
    /**
     * Find user by username with company isolation
     */
    public function findByUsername($username) {
        $sql = "SELECT * FROM `{$this->table}` WHERE `username` = ?";
        $params = [$username];
        $types = 's';
        
        // Add company filter if enabled and user is set
        if ($this->applyCompanyFilter && $this->currentUserId) {
            $filter = $this->companyIsolationService->getCompanyFilterClause(
                $this->currentUserId, 
                $this->companyIdColumn
            );
            $sql .= " AND " . $filter['clause'];
            $params = array_merge($params, $filter['params']);
            $types .= $filter['types'];
        }
        
        $result = $this->db->getResults($sql, $params, $types);
        return !empty($result) ? $result[0] : null;
    }
    
    /**
     * Find user with company and role information (with company isolation)
     */
    public function findWithRelations($id) {
        $sql = "SELECT u.*, c.name as company_name, c.type as company_type, 
                       r.name as role_name, r.level as role_level
                FROM `{$this->table}` u
                LEFT JOIN companies c ON u.company_id = c.id
                LEFT JOIN roles r ON u.role_id = r.id
                WHERE u.id = ?";
        $params = [$id];
        $types = 'i';
        
        // Add company filter if enabled and user is set
        if ($this->applyCompanyFilter && $this->currentUserId) {
            $filter = $this->companyIsolationService->getCompanyFilterClause(
                $this->currentUserId, 
                'u.company_id'
            );
            $sql .= " AND " . $filter['clause'];
            $params = array_merge($params, $filter['params']);
            $types .= $filter['types'];
        }
        
        $result = $this->db->getResults($sql, $params, $types);
        return !empty($result) ? $result[0] : null;
    }
    
    /**
     * Find all users with company and role information (with company isolation)
     */
    public function findAllWithRelations($orderBy = 'u.first_name, u.last_name') {
        $sql = "SELECT u.*, c.name as company_name, c.type as company_type, 
                       r.name as role_name, r.level as role_level
                FROM `{$this->table}` u
                LEFT JOIN companies c ON u.company_id = c.id
                LEFT JOIN roles r ON u.role_id = r.id";
        $params = [];
        $types = '';
        
        // Add company filter if enabled and user is set
        if ($this->applyCompanyFilter && $this->currentUserId) {
            $filter = $this->companyIsolationService->getCompanyFilterClause(
                $this->currentUserId, 
                'u.company_id'
            );
            $sql .= " WHERE " . $filter['clause'];
            $params = array_merge($params, $filter['params']);
            $types .= $filter['types'];
        }
        
        if ($orderBy) {
            $sql .= " ORDER BY $orderBy";
        }
        
        return $this->db->getResults($sql, $params, $types);
    }
    
    /**
     * Find users by company with relations
     */
    public function findByCompanyWithRelations($companyId) {
        // Validate access if user is set
        if ($this->currentUserId) {
            $this->companyIsolationService->validateCompanyAccess($this->currentUserId, $companyId);
        }
        
        $sql = "SELECT u.*, c.name as company_name, c.type as company_type, 
                       r.name as role_name, r.level as role_level
                FROM `{$this->table}` u
                LEFT JOIN companies c ON u.company_id = c.id
                LEFT JOIN roles r ON u.role_id = r.id
                WHERE u.company_id = ?
                ORDER BY u.first_name, u.last_name";
        
        return $this->db->getResults($sql, [$companyId], 'i');
    }
    
    /**
     * Get user count by company (with company isolation)
     */
    public function countByCompany($companyId = null) {
        if ($companyId !== null) {
            // Validate access if user is set
            if ($this->currentUserId) {
                $this->companyIsolationService->validateCompanyAccess($this->currentUserId, $companyId);
            }
            return $this->count(['company_id' => $companyId]);
        }
        
        return $this->count();
    }
    
    /**
     * Search users with company isolation
     */
    public function search($searchTerm, $limit = 50) {
        $sql = "SELECT u.*, c.name as company_name, c.type as company_type, 
                       r.name as role_name, r.level as role_level
                FROM `{$this->table}` u
                LEFT JOIN companies c ON u.company_id = c.id
                LEFT JOIN roles r ON u.role_id = r.id
                WHERE (u.username LIKE ? OR u.email LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ?)";
        
        $searchPattern = "%$searchTerm%";
        $params = [$searchPattern, $searchPattern, $searchPattern, $searchPattern];
        $types = 'ssss';
        
        // Add company filter if enabled and user is set
        if ($this->applyCompanyFilter && $this->currentUserId) {
            $filter = $this->companyIsolationService->getCompanyFilterClause(
                $this->currentUserId, 
                'u.company_id'
            );
            $sql .= " AND " . $filter['clause'];
            $params = array_merge($params, $filter['params']);
            $types .= $filter['types'];
        }
        
        $sql .= " ORDER BY u.first_name, u.last_name LIMIT ?";
        $params[] = $limit;
        $types .= 'i';
        
        return $this->db->getResults($sql, $params, $types);
    }
    
    /**
     * Validate that a contractor user can only create users in their own company
     */
    public function validateContractorUserCreation($actingUserId, $targetCompanyId) {
        if ($this->companyIsolationService->isContractorUser($actingUserId)) {
            $actingUserCompanyId = $this->companyIsolationService->getUserCompanyId($actingUserId);
            if ((int)$actingUserCompanyId !== (int)$targetCompanyId) {
                throw new CompanyAccessDeniedException(
                    "Contractor users can only create users in their own company"
                );
            }
        }
        return true;
    }
    
    /**
     * Create user with company validation for contractors
     */
    public function createWithValidation($data, $actingUserId) {
        // Set current user for isolation
        $this->setCurrentUser($actingUserId);
        
        // Validate contractor restrictions
        if (isset($data['company_id'])) {
            $this->validateContractorUserCreation($actingUserId, $data['company_id']);
        }
        
        return $this->create($data);
    }
    
    /**
     * Find all users with company filter based on acting user
     */
    public function findAllWithCompanyFilter($actingUserId) {
        $this->setCurrentUser($actingUserId);
        return $this->findAllWithRelations();
    }
    
    /**
     * Update user with company validation for contractors
     */
    public function updateWithValidation($userId, $data, $actingUserId) {
        // Set current user for isolation
        $this->setCurrentUser($actingUserId);
        
        // Get the target user to check company
        $targetUser = $this->findWithRelations($userId);
        if (!$targetUser) {
            throw new Exception("User not found");
        }
        
        // Validate contractor restrictions - can only edit users in own company
        if ($this->companyIsolationService->isContractorUser($actingUserId)) {
            $actingUserCompanyId = $this->companyIsolationService->getUserCompanyId($actingUserId);
            if ((int)$actingUserCompanyId !== (int)$targetUser['company_id']) {
                throw new CompanyAccessDeniedException(
                    "Contractor users can only edit users in their own company"
                );
            }
        }
        
        return $this->update($userId, $data);
    }
    
    /**
     * Delete user with company validation for contractors
     */
    public function deleteWithValidation($userId, $actingUserId) {
        // Set current user for isolation
        $this->setCurrentUser($actingUserId);
        
        // Get the target user to check company
        $this->disableCompanyFilter();
        $targetUser = $this->findWithRelations($userId);
        $this->enableCompanyFilter();
        
        if (!$targetUser) {
            throw new Exception("User not found");
        }
        
        // Validate contractor restrictions - can only delete users in own company
        if ($this->companyIsolationService->isContractorUser($actingUserId)) {
            $actingUserCompanyId = $this->companyIsolationService->getUserCompanyId($actingUserId);
            if ((int)$actingUserCompanyId !== (int)$targetUser['company_id']) {
                throw new CompanyAccessDeniedException(
                    "Contractor users can only delete users in their own company"
                );
            }
        }
        
        return $this->delete($userId);
    }
}
