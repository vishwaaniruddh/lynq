<?php
/**
 * Company-Aware Base Repository Class
 * Provides company isolation for all data access operations
 * 
 * All repositories extending this class will automatically filter
 * data based on the current user's company access rights.
 */

require_once __DIR__ . '/../config/autoload.php';
require_once __DIR__ . '/../services/CompanyIsolationService.php';

abstract class BaseRepository {
    protected $db;
    protected $table;
    protected $primaryKey = 'id';
    protected $companyIdColumn = 'company_id';
    protected $companyIsolationService;
    protected $currentUserId = null;
    
    // Whether this repository should apply company filtering
    protected $applyCompanyFilter = true;
    
    public function __construct() {
        $this->db = DatabaseConfig::getInstance();
        $this->companyIsolationService = new CompanyIsolationService();
    }
    
    /**
     * Set the current user for company filtering
     */
    public function setCurrentUser($userId) {
        $this->currentUserId = $userId;
        return $this;
    }
    
    /**
     * Disable company filtering (use with caution)
     */
    public function withoutCompanyFilter() {
        $this->applyCompanyFilter = false;
        return $this;
    }
    
    /**
     * Enable company filtering
     */
    public function withCompanyFilter() {
        $this->applyCompanyFilter = true;
        return $this;
    }
    
    /**
     * Disable company filtering (alias for withoutCompanyFilter)
     */
    public function disableCompanyFilter() {
        $this->applyCompanyFilter = false;
        return $this;
    }
    
    /**
     * Enable company filtering (alias for withCompanyFilter)
     */
    public function enableCompanyFilter() {
        $this->applyCompanyFilter = true;
        return $this;
    }
    
    /**
     * Find record by ID with company isolation
     */
    public function find($id) {
        $sql = "SELECT * FROM `{$this->table}` WHERE `{$this->primaryKey}` = ?";
        $params = [$id];
        $types = 'i';
        
        // Add company filter if enabled and user is set
        if ($this->applyCompanyFilter && $this->currentUserId && $this->companyIdColumn) {
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
     * Find all records with company isolation
     */
    public function findAll($conditions = [], $orderBy = null, $limit = null) {
        $sql = "SELECT * FROM `{$this->table}`";
        $params = [];
        $types = '';
        $whereClause = [];
        
        // Add company filter if enabled and user is set
        if ($this->applyCompanyFilter && $this->currentUserId && $this->companyIdColumn) {
            $filter = $this->companyIsolationService->getCompanyFilterClause(
                $this->currentUserId, 
                $this->companyIdColumn
            );
            $whereClause[] = $filter['clause'];
            $params = array_merge($params, $filter['params']);
            $types .= $filter['types'];
        }
        
        // Add custom conditions
        foreach ($conditions as $field => $value) {
            $whereClause[] = "`$field` = ?";
            $params[] = $value;
            $types .= is_int($value) ? 'i' : 's';
        }
        
        if (!empty($whereClause)) {
            $sql .= " WHERE " . implode(' AND ', $whereClause);
        }
        
        if ($orderBy) {
            $sql .= " ORDER BY $orderBy";
        }
        
        if ($limit) {
            $sql .= " LIMIT $limit";
        }
        
        return $this->db->getResults($sql, $params, $types);
    }
    
    /**
     * Find records by company ID (with access validation)
     */
    public function findByCompany($companyId) {
        // Validate access if user is set
        if ($this->currentUserId) {
            $this->companyIsolationService->validateCompanyAccess($this->currentUserId, $companyId);
        }
        
        return $this->findAll([$this->companyIdColumn => $companyId]);
    }
    
    /**
     * Create record with company validation
     */
    public function create($data) {
        // Validate company access if user is set and data contains company_id
        if ($this->currentUserId && isset($data[$this->companyIdColumn])) {
            $this->companyIsolationService->validateCompanyAccess(
                $this->currentUserId, 
                $data[$this->companyIdColumn]
            );
        }
        
        $fields = array_keys($data);
        $placeholders = str_repeat('?,', count($fields) - 1) . '?';
        $values = array_values($data);
        
        $sql = "INSERT INTO `{$this->table}` (`" . implode('`, `', $fields) . "`) VALUES ($placeholders)";
        
        $types = '';
        foreach ($values as $value) {
            $types .= is_int($value) ? 'i' : 's';
        }
        
        $stmt = $this->db->executeQuery($sql, $values, $types);
        $insertId = $this->db->getConnection()->insert_id;
        $stmt->close();
        
        return $this->find($insertId);
    }
    
    /**
     * Update record with company validation
     */
    public function update($id, $data) {
        // First, verify the record exists and user has access
        $existing = $this->find($id);
        if (!$existing) {
            throw new Exception("Record not found or access denied");
        }
        
        // If changing company, validate access to new company
        if ($this->currentUserId && isset($data[$this->companyIdColumn])) {
            $this->companyIsolationService->validateCompanyAccess(
                $this->currentUserId, 
                $data[$this->companyIdColumn]
            );
        }
        
        $fields = array_keys($data);
        $setClause = implode(' = ?, ', $fields) . ' = ?';
        $values = array_values($data);
        $values[] = $id;
        
        $sql = "UPDATE `{$this->table}` SET $setClause WHERE `{$this->primaryKey}` = ?";
        
        $types = '';
        foreach ($values as $value) {
            $types .= is_int($value) ? 'i' : 's';
        }
        
        $stmt = $this->db->executeQuery($sql, $values, $types);
        $stmt->close();
        
        return $this->find($id);
    }
    
    /**
     * Delete record with company validation
     */
    public function delete($id) {
        // First, verify the record exists and user has access
        $existing = $this->find($id);
        if (!$existing) {
            throw new Exception("Record not found or access denied");
        }
        
        $sql = "DELETE FROM `{$this->table}` WHERE `{$this->primaryKey}` = ?";
        $stmt = $this->db->executeQuery($sql, [$id], 'i');
        $affectedRows = $stmt->affected_rows;
        $stmt->close();
        
        return $affectedRows > 0;
    }
    
    /**
     * Count records with company isolation
     */
    public function count($conditions = []) {
        $sql = "SELECT COUNT(*) as count FROM `{$this->table}`";
        $params = [];
        $types = '';
        $whereClause = [];
        
        // Add company filter if enabled and user is set
        if ($this->applyCompanyFilter && $this->currentUserId && $this->companyIdColumn) {
            $filter = $this->companyIsolationService->getCompanyFilterClause(
                $this->currentUserId, 
                $this->companyIdColumn
            );
            $whereClause[] = $filter['clause'];
            $params = array_merge($params, $filter['params']);
            $types .= $filter['types'];
        }
        
        // Add custom conditions
        foreach ($conditions as $field => $value) {
            $whereClause[] = "`$field` = ?";
            $params[] = $value;
            $types .= is_int($value) ? 'i' : 's';
        }
        
        if (!empty($whereClause)) {
            $sql .= " WHERE " . implode(' AND ', $whereClause);
        }
        
        $result = $this->db->getResults($sql, $params, $types);
        return $result[0]['count'] ?? 0;
    }
    
    /**
     * Check if record exists with company isolation
     */
    public function exists($id) {
        return $this->find($id) !== null;
    }
    
    /**
     * Get the company isolation service
     */
    public function getCompanyIsolationService() {
        return $this->companyIsolationService;
    }
}
