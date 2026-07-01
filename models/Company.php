<?php
/**
 * Company Model
 */

require_once 'BaseModel.php';

class Company extends BaseModel {
    protected $table = 'companies';
    protected $fillable = [
        'name', 'type', 'status', 'contact_email', 'contact_phone', 'address'
    ];
    
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
        $sql = "SELECT c.*, COUNT(u.id) as user_count
                FROM `{$this->table}` c
                LEFT JOIN users u ON c.id = u.company_id
                WHERE c.id = ?
                GROUP BY c.id";
        
        $result = DatabaseConfig::getInstance()->getResults($sql, [$id], 'i');
        
        return !empty($result) ? $result[0] : null;
    }
    
    /**
     * Get all companies with user counts
     */
    public function findAllWithUserCounts() {
        $sql = "SELECT c.*, COUNT(u.id) as user_count
                FROM `{$this->table}` c
                LEFT JOIN users u ON c.id = u.company_id
                GROUP BY c.id
                ORDER BY c.name";
        
        return DatabaseConfig::getInstance()->getResults($sql);
    }
}