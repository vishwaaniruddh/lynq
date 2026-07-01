<?php
/**
 * Engineer Assignment Repository
 */

require_once __DIR__ . '/BaseRepository.php';

class EngineerAssignmentRepository extends BaseRepository {
    protected $table = 'engineer_assignments';
    protected $primaryKey = 'id';
    protected $companyIdColumn = null;
    
    const STATUS_ASSIGNED = 'assigned';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_COMPLETED = 'completed';
    
    public function findById(int $id): ?array {
        $sql = "SELECT a.*, s.site_name, s.lho, s.city, s.state, s.country, s.address,
                       s.latitude, s.longitude, s.bank_name, s.customer_name, s.zone,
                       s.company_id as adv_company_id, d.contractor_id, d.status as delegation_status,
                       c.name as contractor_name,
                       CONCAT(e.first_name, ' ', e.last_name) as engineer_name, e.email as engineer_email,
                       CONCAT(u.first_name, ' ', u.last_name) as assigned_by_name
                FROM `{$this->table}` a
                JOIN `sites` s ON a.site_id = s.id
                JOIN `site_delegations` d ON a.delegation_id = d.id
                JOIN `companies` c ON d.contractor_id = c.id
                JOIN `users` e ON a.engineer_id = e.id
                LEFT JOIN `users` u ON a.assigned_by = u.id
                WHERE a.id = ?";
        $result = $this->db->getResults($sql, [$id], 'i');
        return !empty($result) ? $result[0] : null;
    }
    
    public function checkDuplicateAssignment(int $siteId, ?int $excludeId = null): bool {
        $sql = "SELECT COUNT(*) as count FROM `{$this->table}` WHERE `site_id` = ? AND `status` IN ('assigned', 'in_progress')";
        $params = [$siteId]; $types = 'i';
        if ($excludeId !== null) { $sql .= " AND `id` != ?"; $params[] = $excludeId; $types .= 'i'; }
        $result = $this->db->getResults($sql, $params, $types);
        return (int)($result[0]['count'] ?? 0) > 0;
    }
    
    public function create($data): int {
        $requiredFields = ['site_id', 'delegation_id', 'engineer_id', 'assigned_by'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || $data[$field] === '') { throw new Exception("The {$field} field is required"); }
        }
        if ($this->checkDuplicateAssignment($data['site_id'])) { throw new Exception("An active assignment already exists for this site"); }
        $sql = "INSERT INTO `{$this->table}` (`site_id`, `delegation_id`, `engineer_id`, `assigned_by`, `status`) VALUES (?, ?, ?, ?, ?)";
        $stmt = $this->db->executeQuery($sql, [(int)$data['site_id'], (int)$data['delegation_id'], (int)$data['engineer_id'], (int)$data['assigned_by'], self::STATUS_ASSIGNED], 'iiiis');
        $insertId = $this->db->getConnection()->insert_id;
        $stmt->close();
        if ($insertId <= 0) { throw new Exception("Failed to create assignment record"); }
        return $insertId;
    }
    
    public function findByEngineer(int $engineerId, array $filters = []): array {
        $page = max(1, (int)($filters['page'] ?? 1));
        $limit = min(100, max(1, (int)($filters['limit'] ?? 10)));
        $offset = ($page - 1) * $limit;
        
        // Build WHERE clause
        $where = "a.engineer_id = ?";
        $params = [$engineerId];
        $types = 'i';
        
        if (!empty($filters['status'])) {
            $where .= " AND a.status = ?";
            $params[] = $filters['status'];
            $types .= 's';
        }
        
        // Filter by feasibility_status
        if (!empty($filters['feasibility_status'])) {
            // Handle 'feasibility_completed' as a group of completed statuses
            if ($filters['feasibility_status'] === 'feasibility_completed') {
                $where .= " AND a.feasibility_status IN ('feasibility_completed', 'pending_contractor_review', 'contractor_approved', 'contractor_rejected', 'adv_approved', 'adv_rejected')";
            } else {
                $where .= " AND a.feasibility_status = ?";
                $params[] = $filters['feasibility_status'];
                $types .= 's';
            }
        }
        
        if (!empty($filters['city'])) {
            $where .= " AND s.city = ?";
            $params[] = $filters['city'];
            $types .= 's';
        }
        
        if (!empty($filters['state'])) {
            $where .= " AND s.state = ?";
            $params[] = $filters['state'];
            $types .= 's';
        }
        
        if (!empty($filters['lho'])) {
            $where .= " AND s.lho = ?";
            $params[] = $filters['lho'];
            $types .= 's';
        }
        
        if (!empty($filters['search'])) {
            $searchTerm = '%' . $filters['search'] . '%';
            $where .= " AND (s.site_name LIKE ? OR s.lho LIKE ? OR s.city LIKE ? OR s.address LIKE ?)";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $types .= 'ssss';
        }
        
        // Count total
        $countSql = "SELECT COUNT(*) as total FROM `{$this->table}` a
                     JOIN `sites` s ON a.site_id = s.id
                     WHERE {$where}";
        $countResult = $this->db->getResults($countSql, $params, $types);
        $total = (int)($countResult[0]['total'] ?? 0);
        
        // Get paginated data with feasibility info (Requirements 1.1, 1.5)
        $sql = "SELECT a.*, 
                       s.site_name, s.lho, s.city, s.state, s.country, s.address, 
                       s.latitude, s.longitude, s.bank_name, s.customer_name, s.zone,
                       d.contractor_id, d.status as delegation_status,
                       c.name as contractor_name,
                       CONCAT(u.first_name, ' ', u.last_name) as assigned_by_name,
                       eta.eta_datetime, eta.submitted_at as eta_submitted_at,
                       ada.ada_datetime, ada.latitude as ada_latitude, ada.longitude as ada_longitude
                FROM `{$this->table}` a
                JOIN `sites` s ON a.site_id = s.id
                JOIN `site_delegations` d ON a.delegation_id = d.id
                JOIN `companies` c ON d.contractor_id = c.id
                LEFT JOIN `users` u ON a.assigned_by = u.id
                LEFT JOIN `feasibility_eta` eta ON a.id = eta.assignment_id AND eta.is_current = TRUE
                LEFT JOIN `feasibility_ada` ada ON a.id = ada.assignment_id
                WHERE {$where}
                ORDER BY a.assigned_at DESC
                LIMIT ? OFFSET ?";
        
        $params[] = $limit;
        $params[] = $offset;
        $types .= 'ii';
        
        $data = $this->db->getResults($sql, $params, $types);
        
        return [
            'data' => $data,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'totalPages' => $total > 0 ? ceil($total / $limit) : 0
        ];
    }
    
    public function findByContractor(int $contractorId, array $filters = []): array {
        $page = max(1, (int)($filters['page'] ?? 1));
        $limit = min(100, max(1, (int)($filters['limit'] ?? 10)));
        $offset = ($page - 1) * $limit;
        
        // Build WHERE clause
        $where = "d.contractor_id = ?";
        $params = [$contractorId];
        $types = 'i';
        
        if (!empty($filters['status'])) {
            $where .= " AND a.status = ?";
            $params[] = $filters['status'];
            $types .= 's';
        }
        
        if (!empty($filters['engineer_id'])) {
            $where .= " AND a.engineer_id = ?";
            $params[] = (int)$filters['engineer_id'];
            $types .= 'i';
        }
        
        if (!empty($filters['search'])) {
            $searchTerm = '%' . $filters['search'] . '%';
            $where .= " AND (s.site_name LIKE ? OR s.lho LIKE ? OR s.city LIKE ?)";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $types .= 'sss';
        }
        
        // Count total
        $countSql = "SELECT COUNT(*) as total FROM `{$this->table}` a
                     JOIN `site_delegations` d ON a.delegation_id = d.id
                     JOIN `sites` s ON a.site_id = s.id
                     WHERE {$where}";
        $countResult = $this->db->getResults($countSql, $params, $types);
        $total = (int)($countResult[0]['total'] ?? 0);
        
        // Get paginated data
        $sql = "SELECT a.*, 
                       s.site_name, s.lho, s.city, s.state, s.country, s.address,
                       CONCAT(e.first_name, ' ', e.last_name) as engineer_name, e.email as engineer_email,
                       CONCAT(u.first_name, ' ', u.last_name) as assigned_by_name
                FROM `{$this->table}` a
                JOIN `sites` s ON a.site_id = s.id
                JOIN `site_delegations` d ON a.delegation_id = d.id
                JOIN `users` e ON a.engineer_id = e.id
                LEFT JOIN `users` u ON a.assigned_by = u.id
                WHERE {$where}
                ORDER BY a.assigned_at DESC
                LIMIT ? OFFSET ?";
        
        $params[] = $limit;
        $params[] = $offset;
        $types .= 'ii';
        
        $data = $this->db->getResults($sql, $params, $types);
        
        return [
            'data' => $data,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'totalPages' => $total > 0 ? ceil($total / $limit) : 0
        ];
    }
    
    public function canEngineerAccess(int $assignmentId, int $engineerId): bool {
        $result = $this->db->getResults("SELECT COUNT(*) as count FROM `{$this->table}` WHERE `id` = ? AND `engineer_id` = ?", [$assignmentId, $engineerId], 'ii');
        return (int)($result[0]['count'] ?? 0) > 0;
    }
    
    /**
     * Get assignment history for a site
     * 
     * @param int $siteId Site ID
     * @return array Assignment history
     */
    public function getAssignmentHistory(int $siteId): array {
        $sql = "SELECT a.*, 
                       CONCAT(e.first_name, ' ', e.last_name) as engineer_name, e.email as engineer_email,
                       CONCAT(u.first_name, ' ', u.last_name) as assigned_by_name
                FROM `{$this->table}` a
                JOIN `users` e ON a.engineer_id = e.id
                LEFT JOIN `users` u ON a.assigned_by = u.id
                WHERE a.site_id = ?
                ORDER BY a.assigned_at ASC";
        
        return $this->db->getResults($sql, [$siteId], 'i');
    }
    
    /**
     * Find assignments by site
     * 
     * @param int $siteId Site ID
     * @return array Assignments
     */
    public function findBySite(int $siteId): array {
        $sql = "SELECT a.*, 
                       CONCAT(e.first_name, ' ', e.last_name) as engineer_name, e.email as engineer_email,
                       CONCAT(u.first_name, ' ', u.last_name) as assigned_by_name
                FROM `{$this->table}` a
                JOIN `users` e ON a.engineer_id = e.id
                LEFT JOIN `users` u ON a.assigned_by = u.id
                WHERE a.site_id = ?
                ORDER BY a.assigned_at DESC";
        
        return $this->db->getResults($sql, [$siteId], 'i');
    }
    
    /**
     * Find assignments by delegation
     * 
     * @param int $delegationId Delegation ID
     * @return array Assignments
     */
    public function findByDelegation(int $delegationId): array {
        $sql = "SELECT a.*, 
                       s.site_name, s.lho, s.city, s.state,
                       CONCAT(e.first_name, ' ', e.last_name) as engineer_name, e.email as engineer_email,
                       CONCAT(u.first_name, ' ', u.last_name) as assigned_by_name
                FROM `{$this->table}` a
                JOIN `sites` s ON a.site_id = s.id
                JOIN `users` e ON a.engineer_id = e.id
                LEFT JOIN `users` u ON a.assigned_by = u.id
                WHERE a.delegation_id = ?
                ORDER BY a.assigned_at DESC";
        
        return $this->db->getResults($sql, [$delegationId], 'i');
    }
    
    /**
     * Update assignment status
     * 
     * @param int $assignmentId Assignment ID
     * @param string $status New status
     * @return bool Success
     */
    public function updateStatus(int $assignmentId, string $status): bool {
        $sql = "UPDATE `{$this->table}` SET `status` = ?, `updated_at` = NOW() WHERE `id` = ?";
        $stmt = $this->db->executeQuery($sql, [$status, $assignmentId], 'si');
        $affectedRows = $stmt->affected_rows;
        $stmt->close();
        return $affectedRows > 0;
    }
    
    /**
     * Get active assignment for a site
     * 
     * @param int $siteId Site ID
     * @return array|null Active assignment or null
     */
    public function getActiveAssignment(int $siteId): ?array {
        $sql = "SELECT a.*, 
                       CONCAT(e.first_name, ' ', e.last_name) as engineer_name, e.email as engineer_email
                FROM `{$this->table}` a
                JOIN `users` e ON a.engineer_id = e.id
                WHERE a.site_id = ? AND a.status IN ('assigned', 'in_progress')
                ORDER BY a.assigned_at DESC
                LIMIT 1";
        
        $result = $this->db->getResults($sql, [$siteId], 'i');
        return !empty($result) ? $result[0] : null;
    }
    
    /**
     * Count assignments by status for an engineer
     * 
     * @param int $engineerId Engineer user ID
     * @return array Status counts
     */
    public function countByStatusForEngineer(int $engineerId): array {
        $sql = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'assigned' THEN 1 ELSE 0 END) as assigned,
                    SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed
                FROM `{$this->table}`
                WHERE engineer_id = ?";
        
        $result = $this->db->getResults($sql, [$engineerId], 'i');
        
        return [
            'total' => (int)($result[0]['total'] ?? 0),
            'assigned' => (int)($result[0]['assigned'] ?? 0),
            'in_progress' => (int)($result[0]['in_progress'] ?? 0),
            'completed' => (int)($result[0]['completed'] ?? 0)
        ];
    }
    
    /**
     * Count assignments by feasibility status for an engineer
     * 
     * @param int $engineerId Engineer user ID
     * @return array Feasibility status counts
     */
    public function countByFeasibilityStatusForEngineer(int $engineerId): array {
        $sql = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN eta.id IS NULL THEN 1 ELSE 0 END) as pending_eta,
                    SUM(CASE WHEN eta.id IS NOT NULL THEN 1 ELSE 0 END) as eta_submitted,
                    SUM(CASE WHEN ada.id IS NOT NULL THEN 1 ELSE 0 END) as ada_submitted,
                    SUM(CASE WHEN a.feasibility_status IN ('feasibility_completed', 'pending_contractor_review', 'contractor_approved', 'contractor_rejected', 'adv_approved', 'adv_rejected') THEN 1 ELSE 0 END) as feasibility_completed
                FROM `{$this->table}` a
                LEFT JOIN `feasibility_eta` eta ON a.id = eta.assignment_id AND eta.is_current = TRUE
                LEFT JOIN `feasibility_ada` ada ON a.id = ada.assignment_id
                WHERE a.engineer_id = ?";
        
        $result = $this->db->getResults($sql, [$engineerId], 'i');
        
        return [
            'total' => (int)($result[0]['total'] ?? 0),
            'pending_eta' => (int)($result[0]['pending_eta'] ?? 0),
            'eta_submitted' => (int)($result[0]['eta_submitted'] ?? 0),
            'ada_submitted' => (int)($result[0]['ada_submitted'] ?? 0),
            'feasibility_completed' => (int)($result[0]['feasibility_completed'] ?? 0)
        ];
    }
    
    /**
     * Count assignments by status for a contractor
     * 
     * @param int $contractorId Contractor company ID
     * @return array Status counts
     */
    public function countByStatusForContractor(int $contractorId): array {
        $sql = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN a.status = 'assigned' THEN 1 ELSE 0 END) as assigned,
                    SUM(CASE WHEN a.status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
                    SUM(CASE WHEN a.status = 'completed' THEN 1 ELSE 0 END) as completed
                FROM `{$this->table}` a
                JOIN `site_delegations` d ON a.delegation_id = d.id
                WHERE d.contractor_id = ?";
        
        $result = $this->db->getResults($sql, [$contractorId], 'i');
        
        return [
            'total' => (int)($result[0]['total'] ?? 0),
            'assigned' => (int)($result[0]['assigned'] ?? 0),
            'in_progress' => (int)($result[0]['in_progress'] ?? 0),
            'completed' => (int)($result[0]['completed'] ?? 0)
        ];
    }
    
    /**
     * Get distinct engineers for a contractor's assignments
     * 
     * @param int $contractorId Contractor company ID
     * @return array Engineers
     */
    public function getDistinctEngineers(int $contractorId): array {
        $sql = "SELECT DISTINCT e.id, CONCAT(e.first_name, ' ', e.last_name) as name, e.email
                FROM `{$this->table}` a
                JOIN `site_delegations` d ON a.delegation_id = d.id
                JOIN `users` e ON a.engineer_id = e.id
                WHERE d.contractor_id = ?
                ORDER BY name";
        
        return $this->db->getResults($sql, [$contractorId], 'i');
    }
    
    /**
     * Get distinct LHOs for an engineer's assignments
     * 
     * @param int $engineerId Engineer user ID
     * @return array LHOs
     */
    public function getDistinctLHOsForEngineer(int $engineerId): array {
        $sql = "SELECT DISTINCT s.lho
                FROM `{$this->table}` a
                JOIN `sites` s ON a.site_id = s.id
                WHERE a.engineer_id = ? AND s.lho IS NOT NULL AND s.lho != ''
                ORDER BY s.lho";
        
        $result = $this->db->getResults($sql, [$engineerId], 'i');
        return array_column($result, 'lho');
    }
    
    /**
     * Get distinct cities for an engineer's assignments
     * 
     * @param int $engineerId Engineer user ID
     * @return array Cities
     */
    public function getDistinctCitiesForEngineer(int $engineerId): array {
        $sql = "SELECT DISTINCT s.city
                FROM `{$this->table}` a
                JOIN `sites` s ON a.site_id = s.id
                WHERE a.engineer_id = ? AND s.city IS NOT NULL AND s.city != ''
                ORDER BY s.city";
        
        $result = $this->db->getResults($sql, [$engineerId], 'i');
        return array_column($result, 'city');
    }
    
    /**
     * Get distinct states for an engineer's assignments
     * 
     * @param int $engineerId Engineer user ID
     * @return array States
     */
    public function getDistinctStatesForEngineer(int $engineerId): array {
        $sql = "SELECT DISTINCT s.state
                FROM `{$this->table}` a
                JOIN `sites` s ON a.site_id = s.id
                WHERE a.engineer_id = ? AND s.state IS NOT NULL AND s.state != ''
                ORDER BY s.state";
        
        $result = $this->db->getResults($sql, [$engineerId], 'i');
        return array_column($result, 'state');
    }
    
    /**
     * Find all assignments for export
     * 
     * @param int $contractorId Contractor company ID
     * @param array $filters Optional filters
     * @return array Assignments
     */
    public function findAllForExport(int $contractorId, array $filters = []): array {
        $where = "d.contractor_id = ?";
        $params = [$contractorId];
        $types = 'i';
        
        if (!empty($filters['status'])) {
            $where .= " AND a.status = ?";
            $params[] = $filters['status'];
            $types .= 's';
        }
        
        if (!empty($filters['engineer_id'])) {
            $where .= " AND a.engineer_id = ?";
            $params[] = (int)$filters['engineer_id'];
            $types .= 'i';
        }
        
        $sql = "SELECT a.id, a.site_id, a.status, a.assigned_at,
                       s.site_name, s.lho, s.city, s.state, s.country, s.address,
                       CONCAT(e.first_name, ' ', e.last_name) as engineer_name, e.email as engineer_email,
                       CONCAT(u.first_name, ' ', u.last_name) as assigned_by_name
                FROM `{$this->table}` a
                JOIN `sites` s ON a.site_id = s.id
                JOIN `site_delegations` d ON a.delegation_id = d.id
                JOIN `users` e ON a.engineer_id = e.id
                LEFT JOIN `users` u ON a.assigned_by = u.id
                WHERE {$where}
                ORDER BY a.assigned_at DESC";
        
        return $this->db->getResults($sql, $params, $types);
    }
}
