<?php
/**
 * Company Repository with Access Control
 * Provides company-aware company data access
 */

require_once __DIR__ . '/BaseRepository.php';

class CompanyRepository extends BaseRepository {
    protected $table = 'companies';
    protected $companyIdColumn = 'id'; // Companies filter by their own ID
    
    /**
     * Find companies by type
     */
    public function findByType($type) {
        return $this->findAll(['type' => $type], 'name');
    }
    
    /**
     * Find active companies
     */
    public function findActive() {
        return $this->findAll(['status' => 'ACTIVE'], 'name');
    }
    
    /**
     * Find contractor companies
     */
    public function findContractors() {
        return $this->findAll(['type' => 'CONTRACTOR'], 'name');
    }
    
    /**
     * Find ADV companies
     */
    public function findAdv() {
        return $this->findAll(['type' => 'ADV'], 'name');
    }
    
    /**
     * Check if company is ADV
     */
    public function isAdv($id) {
        $company = $this->find($id);
        return $company && $company['type'] === 'ADV';
    }
    
    /**
     * Check if company is contractor
     */
    public function isContractor($id) {
        $company = $this->find($id);
        return $company && $company['type'] === 'CONTRACTOR';
    }
    
    /**
     * Get company with user count
     */
    public function findWithUserCount($id) {
        // Validate access if user is set
        if ($this->currentUserId) {
            $this->companyIsolationService->validateCompanyAccess($this->currentUserId, $id);
        }
        
        $sql = "SELECT c.*, COUNT(u.id) as user_count
                FROM `{$this->table}` c
                LEFT JOIN users u ON c.id = u.company_id
                WHERE c.id = ?
                GROUP BY c.id";
        
        $result = $this->db->getResults($sql, [$id], 'i');
        return !empty($result) ? $result[0] : null;
    }
    
    /**
     * Get all companies with user counts (with access control)
     */
    public function findAllWithUserCounts() {
        $sql = "SELECT c.*, COUNT(u.id) as user_count
                FROM `{$this->table}` c
                LEFT JOIN users u ON c.id = u.company_id";
        $params = [];
        $types = '';
        
        // Add company filter if enabled and user is set
        if ($this->applyCompanyFilter && $this->currentUserId) {
            $filter = $this->companyIsolationService->getCompanyFilterClause(
                $this->currentUserId, 
                'c.id'
            );
            $sql .= " WHERE " . $filter['clause'];
            $params = array_merge($params, $filter['params']);
            $types .= $filter['types'];
        }
        
        $sql .= " GROUP BY c.id ORDER BY c.name";
        
        return $this->db->getResults($sql, $params, $types);
    }
    
    /**
     * Get accessible companies for a user
     */
    public function getAccessibleCompanies($userId) {
        return $this->companyIsolationService->getAccessibleCompanies($userId);
    }
    
    /**
     * Get companies that can be assigned to users (for user creation/edit)
     * ADV users can assign to any company, contractors only to their own
     */
    public function getAssignableCompanies($userId) {
        if ($this->companyIsolationService->isAdvUser($userId)) {
            return $this->findActive();
        }
        
        // Contractors can only assign to their own company
        $companyId = $this->companyIsolationService->getUserCompanyId($userId);
        $company = $this->find($companyId);
        return $company ? [$company] : [];
    }
}
