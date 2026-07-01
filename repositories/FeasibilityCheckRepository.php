<?php
/**
 * Feasibility Check Repository
 * Handles database operations for comprehensive site feasibility check records
 * 
 * Requirements: 4.4, 8.1, 8.3, 8.4
 */

require_once __DIR__ . '/BaseRepository.php';

class FeasibilityCheckRepository extends BaseRepository {
    protected $table = 'feasibility_checks';
    protected $primaryKey = 'id';
    protected $companyIdColumn = null; // No direct company relation
    
    /**
     * Create a new feasibility check record
     * Note: Only one feasibility check per assignment is allowed (unique constraint)
     * 
     * @param array $data Feasibility check data
     * @return array Created feasibility check record
     * @throws Exception If required fields are missing or creation fails
     * 
     * Requirements: 4.4
     */
    public function create($data): array {
        $requiredFields = ['assignment_id', 'site_id', 'created_by'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || $data[$field] === '') {
                throw new Exception("The {$field} field is required");
            }
        }
        
        // Check if feasibility check already exists for this assignment
        $existing = $this->findByAssignment((int)$data['assignment_id']);
        if ($existing) {
            throw new Exception("Feasibility check already exists for this assignment");
        }
        
        // Build dynamic insert query based on provided data
        $fields = [];
        $placeholders = [];
        $values = [];
        $types = '';
        
        // Define all possible fields with their types
        $fieldTypes = [
            'assignment_id' => 'i',
            'site_id' => 'i',
            'no_of_atm' => 'i',
            'atm_id_1' => 's',
            'atm_id_2' => 's',
            'atm_id_3' => 's',
            'atm_1_status' => 's',
            'atm_2_status' => 's',
            'atm_3_status' => 's',
            'operator' => 's',
            'signal_status' => 's',
            'operator_2' => 's',
            'signal_status_2' => 's',
            'backroom_network_remark' => 's',
            'ups_available' => 's',
            'no_of_ups' => 'i',
            'ups_battery_backup' => 's',
            'ups_working_1' => 's',
            'ups_working_2' => 's',
            'ups_working_3' => 's',
            'power_socket_availability' => 's',
            'power_socket_availability_ups' => 's',
            'earthing' => 's',
            'earthing_voltage' => 's',
            'power_fluctuation_en' => 's',
            'power_fluctuation_pe' => 's',
            'power_fluctuation_pn' => 's',
            'frequent_power_cut' => 's',
            'frequent_power_cut_from' => 's',
            'frequent_power_cut_to' => 's',
            'frequent_power_cut_remark' => 's',
            'em_lock_available' => 's',
            'em_lock_password' => 's',
            'password_received' => 's',
            'backroom_key_name' => 's',
            'backroom_key_number' => 's',
            'backroom_key_status' => 's',
            'antenna_routing_detail' => 's',
            'router_antenna_position' => 's',
            'router_position' => 's',
            'nearest_shop_name' => 's',
            'nearest_shop_number' => 's',
            'nearest_shop_distance' => 's',
            'backroom_disturbing_material' => 's',
            'backroom_disturbing_material_remark' => 's',
            'remarks' => 's',
            'backroom_network_snap' => 's',
            'router_antenna_snap' => 's',
            'antenna_routing_snap' => 's',
            'ups_available_snap' => 's',
            'no_of_ups_snap' => 's',
            'ups_working_snap' => 's',
            'power_socket_availability_snap' => 's',
            'earthing_snap' => 's',
            'power_fluctuation_snap' => 's',
            'remarks_snap' => 's',
            'status' => 's',
            'created_by' => 'i'
        ];
        
        foreach ($fieldTypes as $field => $type) {
            if (isset($data[$field]) && $data[$field] !== '') {
                $fields[] = "`{$field}`";
                $placeholders[] = '?';
                $values[] = $type === 'i' ? (int)$data[$field] : $data[$field];
                $types .= $type;
            }
        }
        
        $sql = "INSERT INTO `{$this->table}` (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")";
        
        $stmt = $this->db->executeQuery($sql, $values, $types);
        $insertId = $this->db->getConnection()->insert_id;
        $stmt->close();
        
        if ($insertId <= 0) {
            throw new Exception("Failed to create feasibility check record");
        }
        
        return $this->findById($insertId);
    }
    
    /**
     * Find feasibility check by ID
     * 
     * @param int $id Feasibility check ID
     * @return array|null Feasibility check record or null
     */
    public function findById(int $id): ?array {
        $sql = "SELECT fc.*, 
                       CONCAT(u.first_name, ' ', u.last_name) as created_by_name,
                       u.email as created_by_email,
                       s.site_name, s.lho, s.address, s.city, s.state, s.country,
                       s.latitude as site_latitude, s.longitude as site_longitude,
                       s.bank_name, s.customer_name, s.zone,
                       sd.contractor_id
                FROM `{$this->table}` fc
                LEFT JOIN `users` u ON fc.created_by = u.id
                LEFT JOIN `sites` s ON fc.site_id = s.id
                LEFT JOIN `engineer_assignments` ea ON fc.assignment_id = ea.id
                LEFT JOIN `site_delegations` sd ON ea.delegation_id = sd.id
                WHERE fc.id = ?";
        
        $result = $this->db->getResults($sql, [$id], 'i');
        return !empty($result) ? $result[0] : null;
    }
    
    /**
     * Find feasibility check by assignment ID
     * Note: Only one feasibility check per assignment exists
     * 
     * @param int $assignmentId Engineer assignment ID
     * @return array|null Feasibility check record or null
     * 
     * Requirements: 4.4
     */
    public function findByAssignment(int $assignmentId): ?array {
        $sql = "SELECT fc.*, 
                       CONCAT(u.first_name, ' ', u.last_name) as created_by_name,
                       u.email as created_by_email,
                       s.site_name, s.lho, s.address, s.city, s.state, s.country,
                       s.latitude as site_latitude, s.longitude as site_longitude,
                       s.bank_name, s.customer_name, s.zone,
                       sd.contractor_id
                FROM `{$this->table}` fc
                LEFT JOIN `users` u ON fc.created_by = u.id
                LEFT JOIN `sites` s ON fc.site_id = s.id
                LEFT JOIN `engineer_assignments` ea ON fc.assignment_id = ea.id
                LEFT JOIN `site_delegations` sd ON ea.delegation_id = sd.id
                WHERE fc.assignment_id = ?";
        
        $result = $this->db->getResults($sql, [$assignmentId], 'i');
        return !empty($result) ? $result[0] : null;
    }
    
    /**
     * Find all feasibility checks for a site
     * 
     * @param int $siteId Site ID
     * @return array List of feasibility check records
     */
    public function findBySite(int $siteId): array {
        $sql = "SELECT fc.*, 
                       CONCAT(u.first_name, ' ', u.last_name) as created_by_name,
                       u.email as created_by_email,
                       ea.engineer_id,
                       CONCAT(eng.first_name, ' ', eng.last_name) as engineer_name
                FROM `{$this->table}` fc
                LEFT JOIN `users` u ON fc.created_by = u.id
                LEFT JOIN `engineer_assignments` ea ON fc.assignment_id = ea.id
                LEFT JOIN `users` eng ON ea.engineer_id = eng.id
                WHERE fc.site_id = ?
                ORDER BY fc.created_at DESC";
        
        return $this->db->getResults($sql, [$siteId], 'i');
    }
    
    /**
     * Find feasibility checks with filters for tracking
     * 
     * @param array $filters Filter options (status, search, page, limit)
     * @return array Paginated results with total count
     * 
     * Requirements: 8.1, 8.3
     */
    public function findWithFilters(array $filters = []): array {
        $page = max(1, (int)($filters['page'] ?? 1));
        $limit = min(100, max(1, (int)($filters['limit'] ?? 10)));
        $offset = ($page - 1) * $limit;
        
        // Build WHERE clause
        $where = "1=1";
        $params = [];
        $types = '';
        
        if (!empty($filters['status'])) {
            $where .= " AND fc.status = ?";
            $params[] = $filters['status'];
            $types .= 's';
        }
        
        if (!empty($filters['site_id'])) {
            $where .= " AND fc.site_id = ?";
            $params[] = (int)$filters['site_id'];
            $types .= 'i';
        }
        
        if (!empty($filters['created_by'])) {
            $where .= " AND fc.created_by = ?";
            $params[] = (int)$filters['created_by'];
            $types .= 'i';
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
        
        if (!empty($filters['date_from'])) {
            $where .= " AND DATE(fc.created_at) >= ?";
            $params[] = $filters['date_from'];
            $types .= 's';
        }
        
        if (!empty($filters['date_to'])) {
            $where .= " AND DATE(fc.created_at) <= ?";
            $params[] = $filters['date_to'];
            $types .= 's';
        }
        
        // Count total
        $countSql = "SELECT COUNT(*) as total 
                     FROM `{$this->table}` fc
                     LEFT JOIN `sites` s ON fc.site_id = s.id
                     WHERE {$where}";
        $countResult = $this->db->getResults($countSql, $params, $types);
        $total = (int)($countResult[0]['total'] ?? 0);
        
        // Get paginated data
        $sql = "SELECT fc.*, 
                       CONCAT(u.first_name, ' ', u.last_name) as created_by_name,
                       s.site_name, s.lho, s.address, s.city, s.state,
                       s.bank_name, s.customer_name,
                       ea.engineer_id, ea.feasibility_status,
                       CONCAT(eng.first_name, ' ', eng.last_name) as engineer_name
                FROM `{$this->table}` fc
                LEFT JOIN `users` u ON fc.created_by = u.id
                LEFT JOIN `sites` s ON fc.site_id = s.id
                LEFT JOIN `engineer_assignments` ea ON fc.assignment_id = ea.id
                LEFT JOIN `users` eng ON ea.engineer_id = eng.id
                WHERE {$where}
                ORDER BY fc.created_at DESC
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
    
    /**
     * Get feasibility data for export
     * Returns all feasibility checks with complete details for Excel export
     * 
     * @param array $filters Optional filters
     * @return array Complete feasibility data for export
     * 
     * Requirements: 8.4
     */
    public function getForExport(array $filters = []): array {
        $where = "1=1";
        $params = [];
        $types = '';
        
        if (!empty($filters['status'])) {
            $where .= " AND fc.status = ?";
            $params[] = $filters['status'];
            $types .= 's';
        }
        
        if (!empty($filters['date_from'])) {
            $where .= " AND DATE(fc.created_at) >= ?";
            $params[] = $filters['date_from'];
            $types .= 's';
        }
        
        if (!empty($filters['date_to'])) {
            $where .= " AND DATE(fc.created_at) <= ?";
            $params[] = $filters['date_to'];
            $types .= 's';
        }
        
        $sql = "SELECT 
                    fc.*,
                    s.site_name, s.lho, s.address, s.city, s.state, s.country,
                    s.latitude as site_latitude, s.longitude as site_longitude,
                    s.bank_name, s.customer_name, s.zone,
                    ea.engineer_id, ea.feasibility_status, ea.assigned_at,
                    CONCAT(eng.first_name, ' ', eng.last_name) as engineer_name,
                    eng.email as engineer_email,
                    CONCAT(creator.first_name, ' ', creator.last_name) as created_by_name,
                    eta.eta_datetime, eta.submitted_at as eta_submitted_at,
                    ada.ada_datetime, ada.latitude as ada_latitude, ada.longitude as ada_longitude,
                    ada.submitted_at as ada_submitted_at
                FROM `{$this->table}` fc
                LEFT JOIN `sites` s ON fc.site_id = s.id
                LEFT JOIN `engineer_assignments` ea ON fc.assignment_id = ea.id
                LEFT JOIN `users` eng ON ea.engineer_id = eng.id
                LEFT JOIN `users` creator ON fc.created_by = creator.id
                LEFT JOIN `feasibility_eta` eta ON ea.id = eta.assignment_id AND eta.is_current = TRUE
                LEFT JOIN `feasibility_ada` ada ON ea.id = ada.assignment_id
                WHERE {$where}
                ORDER BY fc.created_at DESC";
        
        if (empty($params)) {
            return $this->db->getResults($sql);
        }
        
        return $this->db->getResults($sql, $params, $types);
    }
    
    /**
     * Check if a feasibility check exists for an assignment
     * 
     * @param int $assignmentId Engineer assignment ID
     * @return bool True if feasibility check exists
     */
    public function hasFeasibilityCheck(int $assignmentId): bool {
        $sql = "SELECT COUNT(*) as count FROM `{$this->table}` WHERE `assignment_id` = ?";
        $result = $this->db->getResults($sql, [$assignmentId], 'i');
        return (int)($result[0]['count'] ?? 0) > 0;
    }
    
    /**
     * Update feasibility check record
     * 
     * @param int $id Feasibility check ID
     * @param array $data Data to update
     * @return array|null Updated record or null
     */
    public function updateFeasibilityCheck(int $id, array $data): ?array {
        // Build dynamic update query
        $setClauses = [];
        $values = [];
        $types = '';
        
        // Define updatable fields with their types
        $fieldTypes = [
            'no_of_atm' => 'i',
            'atm_id_1' => 's',
            'atm_id_2' => 's',
            'atm_id_3' => 's',
            'atm_1_status' => 's',
            'atm_2_status' => 's',
            'atm_3_status' => 's',
            'operator' => 's',
            'signal_status' => 's',
            'operator_2' => 's',
            'signal_status_2' => 's',
            'backroom_network_remark' => 's',
            'ups_available' => 's',
            'no_of_ups' => 'i',
            'ups_battery_backup' => 's',
            'ups_working_1' => 's',
            'ups_working_2' => 's',
            'ups_working_3' => 's',
            'power_socket_availability' => 's',
            'power_socket_availability_ups' => 's',
            'earthing' => 's',
            'earthing_voltage' => 's',
            'power_fluctuation_en' => 's',
            'power_fluctuation_pe' => 's',
            'power_fluctuation_pn' => 's',
            'frequent_power_cut' => 's',
            'frequent_power_cut_from' => 's',
            'frequent_power_cut_to' => 's',
            'frequent_power_cut_remark' => 's',
            'em_lock_available' => 's',
            'em_lock_password' => 's',
            'password_received' => 's',
            'backroom_key_name' => 's',
            'backroom_key_number' => 's',
            'backroom_key_status' => 's',
            'antenna_routing_detail' => 's',
            'router_antenna_position' => 's',
            'router_position' => 's',
            'nearest_shop_name' => 's',
            'nearest_shop_number' => 's',
            'nearest_shop_distance' => 's',
            'backroom_disturbing_material' => 's',
            'backroom_disturbing_material_remark' => 's',
            'remarks' => 's',
            'backroom_network_snap' => 's',
            'router_antenna_snap' => 's',
            'antenna_routing_snap' => 's',
            'ups_available_snap' => 's',
            'no_of_ups_snap' => 's',
            'ups_working_snap' => 's',
            'power_socket_availability_snap' => 's',
            'earthing_snap' => 's',
            'power_fluctuation_snap' => 's',
            'remarks_snap' => 's',
            'status' => 's'
        ];
        
        foreach ($fieldTypes as $field => $type) {
            if (array_key_exists($field, $data)) {
                $setClauses[] = "`{$field}` = ?";
                $values[] = $type === 'i' ? (int)$data[$field] : $data[$field];
                $types .= $type;
            }
        }
        
        if (empty($setClauses)) {
            return $this->findById($id);
        }
        
        $values[] = $id;
        $types .= 'i';
        
        $sql = "UPDATE `{$this->table}` SET " . implode(', ', $setClauses) . ", `updated_at` = NOW() WHERE `id` = ?";
        
        $stmt = $this->db->executeQuery($sql, $values, $types);
        $stmt->close();
        
        return $this->findById($id);
    }
    
    /**
     * Count feasibility checks by status
     * 
     * @return array Status counts
     */
    public function countByStatus(): array {
        $sql = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
                    SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive
                FROM `{$this->table}`";
        
        $result = $this->db->getResults($sql);
        
        return [
            'total' => (int)($result[0]['total'] ?? 0),
            'active' => (int)($result[0]['active'] ?? 0),
            'inactive' => (int)($result[0]['inactive'] ?? 0)
        ];
    }
}
