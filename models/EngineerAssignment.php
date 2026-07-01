<?php
/**
 * EngineerAssignment Model
 * Handles engineer assignment data for site feasibility assessments
 * 
 * Requirements: 5.1, 5.2
 */

require_once __DIR__ . '/BaseModel.php';

class EngineerAssignment extends BaseModel {
    protected $table = 'engineer_assignments';
    protected $fillable = [
        'site_id', 'delegation_id', 'engineer_id', 'assigned_by', 
        'assigned_at', 'status'
    ];
    
    // Assignment status constants
    const STATUS_ASSIGNED = 'assigned';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_COMPLETED = 'completed';
    
    /**
     * Find assignments by engineer
     * Requirement 6.1
     * 
     * @param int $engineerId Engineer user ID
     * @param array $filters Optional filters
     * @return array Assignments
     */
    public function findByEngineer(int $engineerId, array $filters = []): array {
        $sql = "SELECT a.*, 
                       s.site_name, s.lho, s.city, s.state, s.country, s.address, s.latitude, s.longitude,
                       CONCAT(u.first_name, ' ', u.last_name) as assigned_by_name
                FROM `{$this->table}` a
                JOIN `sites` s ON a.site_id = s.id
                LEFT JOIN `users` u ON a.assigned_by = u.id
                WHERE a.engineer_id = ?";
        $params = [$engineerId];
        $types = 'i';
        
        if (isset($filters['status'])) {
            $sql .= " AND a.status = ?";
            $params[] = $filters['status'];
            $types .= 's';
        }
        
        if (isset($filters['city'])) {
            $sql .= " AND s.city = ?";
            $params[] = $filters['city'];
            $types .= 's';
        }
        
        if (isset($filters['state'])) {
            $sql .= " AND s.state = ?";
            $params[] = $filters['state'];
            $types .= 's';
        }
        
        $sql .= " ORDER BY a.assigned_at DESC";
        
        return DatabaseConfig::getInstance()->getResults($sql, $params, $types);
    }
    
    /**
     * Find assignments by contractor (through delegation)
     * 
     * @param int $contractorId Contractor company ID
     * @return array Assignments
     */
    public function findByContractor(int $contractorId): array {
        $sql = "SELECT a.*, 
                       s.site_name, s.lho, s.city, s.state,
                       CONCAT(e.first_name, ' ', e.last_name) as engineer_name,
                       CONCAT(u.first_name, ' ', u.last_name) as assigned_by_name
                FROM `{$this->table}` a
                JOIN `sites` s ON a.site_id = s.id
                JOIN `site_delegations` d ON a.delegation_id = d.id
                JOIN `users` e ON a.engineer_id = e.id
                LEFT JOIN `users` u ON a.assigned_by = u.id
                WHERE d.contractor_id = ?
                ORDER BY a.assigned_at DESC";
        
        return DatabaseConfig::getInstance()->getResults($sql, [$contractorId], 'i');
    }
    
    /**
     * Find assignments by site
     * 
     * @param int $siteId Site ID
     * @return array Assignments
     */
    public function findBySite(int $siteId): array {
        $sql = "SELECT a.*, 
                       CONCAT(e.first_name, ' ', e.last_name) as engineer_name,
                       CONCAT(u.first_name, ' ', u.last_name) as assigned_by_name
                FROM `{$this->table}` a
                JOIN `users` e ON a.engineer_id = e.id
                LEFT JOIN `users` u ON a.assigned_by = u.id
                WHERE a.site_id = ?
                ORDER BY a.assigned_at DESC";
        
        return DatabaseConfig::getInstance()->getResults($sql, [$siteId], 'i');
    }
    
    /**
     * Check if active assignment exists for site
     * Requirement 5.4
     * 
     * @param int $siteId Site ID
     * @param int|null $excludeId Assignment ID to exclude (for updates)
     * @return bool True if active assignment exists
     */
    public function hasActiveAssignment(int $siteId, ?int $excludeId = null): bool {
        $sql = "SELECT COUNT(*) as count FROM `{$this->table}` 
                WHERE `site_id` = ? AND `status` IN ('assigned', 'in_progress')";
        $params = [$siteId];
        $types = 'i';
        
        if ($excludeId !== null) {
            $sql .= " AND `id` != ?";
            $params[] = $excludeId;
            $types .= 'i';
        }
        
        $result = DatabaseConfig::getInstance()->getResults($sql, $params, $types);
        return $result[0]['count'] > 0;
    }
    
    /**
     * Get assignment with full details
     * Requirement 6.2
     * 
     * @param int $id Assignment ID
     * @return array|null Assignment with details
     */
    public function findWithDetails(int $id): ?array {
        $sql = "SELECT a.*, 
                       s.site_name, s.lho, s.city, s.state, s.country, s.address, s.latitude, s.longitude,
                       s.bank_name, s.customer_name, s.zone,
                       CONCAT(e.first_name, ' ', e.last_name) as engineer_name, e.email as engineer_email,
                       CONCAT(u.first_name, ' ', u.last_name) as assigned_by_name,
                       d.delegated_at, d.status as delegation_status,
                       c.name as contractor_name
                FROM `{$this->table}` a
                JOIN `sites` s ON a.site_id = s.id
                JOIN `site_delegations` d ON a.delegation_id = d.id
                JOIN `companies` c ON d.contractor_id = c.id
                JOIN `users` e ON a.engineer_id = e.id
                LEFT JOIN `users` u ON a.assigned_by = u.id
                WHERE a.id = ?";
        
        $result = DatabaseConfig::getInstance()->getResults($sql, [$id], 'i');
        return !empty($result) ? $result[0] : null;
    }
    
    /**
     * Update assignment status
     * 
     * @param int $id Assignment ID
     * @param string $status New status
     * @return bool Success
     */
    public function updateStatus(int $id, string $status): bool {
        $validStatuses = [self::STATUS_ASSIGNED, self::STATUS_IN_PROGRESS, self::STATUS_COMPLETED];
        if (!in_array($status, $validStatuses)) {
            return false;
        }
        
        $sql = "UPDATE `{$this->table}` SET `status` = ? WHERE `id` = ?";
        $stmt = DatabaseConfig::getInstance()->executeQuery($sql, [$status, $id], 'si');
        $affectedRows = $stmt->affected_rows;
        $stmt->close();
        
        return $affectedRows > 0;
    }
    
    /**
     * Get assignment history for a site
     * Requirement 6.2
     * 
     * @param int $siteId Site ID
     * @return array Assignment history
     */
    public function getAssignmentHistory(int $siteId): array {
        $sql = "SELECT a.*, 
                       CONCAT(e.first_name, ' ', e.last_name) as engineer_name,
                       CONCAT(u.first_name, ' ', u.last_name) as assigned_by_name
                FROM `{$this->table}` a
                JOIN `users` e ON a.engineer_id = e.id
                LEFT JOIN `users` u ON a.assigned_by = u.id
                WHERE a.site_id = ?
                ORDER BY a.assigned_at ASC";
        
        return DatabaseConfig::getInstance()->getResults($sql, [$siteId], 'i');
    }
    
    /**
     * Check if engineer can access assignment
     * Requirement 6.3
     * 
     * @param int $assignmentId Assignment ID
     * @param int $engineerId Engineer user ID
     * @return bool True if engineer can access
     */
    public function canEngineerAccess(int $assignmentId, int $engineerId): bool {
        $sql = "SELECT COUNT(*) as count FROM `{$this->table}` 
                WHERE `id` = ? AND `engineer_id` = ?";
        
        $result = DatabaseConfig::getInstance()->getResults($sql, [$assignmentId, $engineerId], 'ii');
        return $result[0]['count'] > 0;
    }
}
