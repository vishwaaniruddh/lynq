<?php
/**
 * Installation Repository
 * Provides data access operations for installation records with company isolation
 * 
 * Requirements: 1.2, 3.4
 * - 1.2: Create installation record linked to site and feasibility check
 * - 3.4: Create installation record with all captured data
 */

require_once __DIR__ . '/BaseRepository.php';
require_once __DIR__ . '/../models/Installation.php';

class InstallationRepository extends BaseRepository {
    protected $table = 'installations';
    protected $primaryKey = 'id';
    
    // Installations don't have direct company_id, but are linked through sites
    // Company isolation is handled through site relationship
    protected $companyIdColumn = null;
    protected $applyCompanyFilter = false;
    
    /**
     * Find installation by ID
     * 
     * @param int $id Installation ID
     * @return array|null Installation record or null if not found
     */
    public function findById(int $id): ?array {
        $sql = "SELECT i.*, s.company_id 
                FROM `{$this->table}` i
                LEFT JOIN sites s ON i.site_id = s.id
                WHERE i.id = ?";
        $params = [$id];
        $types = 'i';
        
        // Add company filter if user is set (through site relationship)
        if ($this->currentUserId) {
            $filter = $this->companyIsolationService->getCompanyFilterClause(
                $this->currentUserId, 
                's.company_id'
            );
            $sql .= " AND " . $filter['clause'];
            $params = array_merge($params, $filter['params']);
            $types .= $filter['types'];
        }
        
        $result = $this->db->getResults($sql, $params, $types);
        return !empty($result) ? $result[0] : null;
    }
    
    /**
     * Find installation by site ID
     * 
     * @param int $siteId Site ID
     * @return array|null Installation record or null if not found
     * 
     * Requirements: 1.2
     */
    public function findBySiteId(int $siteId): ?array {
        $sql = "SELECT i.*, s.company_id 
                FROM `{$this->table}` i
                LEFT JOIN sites s ON i.site_id = s.id
                WHERE i.site_id = ?";
        $params = [$siteId];
        $types = 'i';
        
        // Add company filter if user is set
        if ($this->currentUserId) {
            $filter = $this->companyIsolationService->getCompanyFilterClause(
                $this->currentUserId, 
                's.company_id'
            );
            $sql .= " AND " . $filter['clause'];
            $params = array_merge($params, $filter['params']);
            $types .= $filter['types'];
        }
        
        $result = $this->db->getResults($sql, $params, $types);
        return !empty($result) ? $result[0] : null;
    }
    
    /**
     * Find installation by feasibility ID
     * 
     * @param int $feasibilityId Feasibility check ID
     * @return array|null Installation record or null if not found
     * 
     * Requirements: 1.2
     */
    public function findByFeasibilityId(int $feasibilityId): ?array {
        $sql = "SELECT i.*, s.company_id 
                FROM `{$this->table}` i
                LEFT JOIN sites s ON i.site_id = s.id
                WHERE i.feasibility_id = ?";
        $params = [$feasibilityId];
        $types = 'i';
        
        // Add company filter if user is set
        if ($this->currentUserId) {
            $filter = $this->companyIsolationService->getCompanyFilterClause(
                $this->currentUserId, 
                's.company_id'
            );
            $sql .= " AND " . $filter['clause'];
            $params = array_merge($params, $filter['params']);
            $types .= $filter['types'];
        }
        
        $result = $this->db->getResults($sql, $params, $types);
        return !empty($result) ? $result[0] : null;
    }

    /**
     * Find all installations with optional filters
     * 
     * @param array $filters Optional filters: status, site_id, company_id, page, limit
     * @return array Array with 'data', 'total', 'page', 'limit', 'totalPages'
     */
    public function findAllWithFilters(array $filters = []): array {
        $page = isset($filters['page']) ? max(1, (int)$filters['page']) : 1;
        $limit = isset($filters['limit']) ? max(1, min(100, (int)$filters['limit'])) : 10;
        $offset = ($page - 1) * $limit;
        $orderBy = $filters['orderBy'] ?? 'i.created_at';
        $orderDir = strtoupper($filters['orderDir'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';
        
        $whereClause = ['1=1'];
        $params = [];
        $types = '';
        
        // Status filter
        if (isset($filters['status']) && $filters['status'] !== '') {
            $whereClause[] = "i.status = ?";
            $params[] = $filters['status'];
            $types .= 's';
        }
        
        // Site ID filter
        if (isset($filters['site_id']) && $filters['site_id'] !== '') {
            $whereClause[] = "i.site_id = ?";
            $params[] = (int)$filters['site_id'];
            $types .= 'i';
        }
        
        // Company filter (through site)
        if (isset($filters['company_id']) && $filters['company_id'] !== '') {
            $whereClause[] = "s.company_id = ?";
            $params[] = (int)$filters['company_id'];
            $types .= 'i';
        }
        
        // Contractor filter (Requirements: 2.1)
        if (isset($filters['contractor_id']) && $filters['contractor_id'] !== '') {
            $whereClause[] = "i.contractor_id = ?";
            $params[] = (int)$filters['contractor_id'];
            $types .= 'i';
        }
        
        // Engineer filter (Requirements: 3.1)
        if (isset($filters['engineer_id']) && $filters['engineer_id'] !== '') {
            $whereClause[] = "i.assigned_engineer_id = ?";
            $params[] = (int)$filters['engineer_id'];
            $types .= 'i';
        }
        
        // Add company isolation filter if user is set
        if ($this->currentUserId) {
            $filter = $this->companyIsolationService->getCompanyFilterClause(
                $this->currentUserId, 
                's.company_id'
            );
            $whereClause[] = $filter['clause'];
            $params = array_merge($params, $filter['params']);
            $types .= $filter['types'];
        }
        
        // Date range filter
        if (isset($filters['date_from']) && $filters['date_from'] !== '') {
            $whereClause[] = "i.created_at >= ?";
            $params[] = $filters['date_from'];
            $types .= 's';
        }
        
        if (isset($filters['date_to']) && $filters['date_to'] !== '') {
            $whereClause[] = "i.created_at <= ?";
            $params[] = $filters['date_to'] . ' 23:59:59';
            $types .= 's';
        }
        
        $whereSQL = ' WHERE ' . implode(' AND ', $whereClause);
        
        // Whitelist allowed order columns
        $allowedOrderColumns = ['i.id', 'i.created_at', 'i.status', 'i.submitted_at', 's.site_name'];
        if (!in_array($orderBy, $allowedOrderColumns)) {
            $orderBy = 'i.created_at';
        }
        
        // Get total count
        $countSQL = "SELECT COUNT(*) as total 
                     FROM `{$this->table}` i
                     LEFT JOIN sites s ON i.site_id = s.id" . $whereSQL;
        $countResult = $this->db->getResults($countSQL, $params, $types);
        $total = (int)($countResult[0]['total'] ?? 0);
        
        // Get paginated data with site info
        $dataSQL = "SELECT i.*, 
                           s.site_name, s.company_id, s.lho as site_lho,
                           CONCAT(u.first_name, ' ', u.last_name) as initiated_by_name,
                           CONCAT(su.first_name, ' ', su.last_name) as submitted_by_name,
                           CONCAT(eu.first_name, ' ', eu.last_name) as assigned_engineer_name,
                           c.name as contractor_name
                    FROM `{$this->table}` i
                    LEFT JOIN sites s ON i.site_id = s.id
                    LEFT JOIN users u ON i.initiated_by = u.id
                    LEFT JOIN users su ON i.submitted_by = su.id
                    LEFT JOIN users eu ON i.assigned_engineer_id = eu.id
                    LEFT JOIN companies c ON i.contractor_id = c.id" . 
                   $whereSQL . 
                   " ORDER BY $orderBy $orderDir LIMIT ? OFFSET ?";
        $dataParams = array_merge($params, [$limit, $offset]);
        $dataTypes = $types . 'ii';
        
        $data = $this->db->getResults($dataSQL, $dataParams, $dataTypes);
        
        return [
            'data' => $data,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'totalPages' => $total > 0 ? ceil($total / $limit) : 0
        ];
    }
    
    /**
     * Find installations by status
     * 
     * @param string $status Installation status
     * @return array List of installations
     */
    public function findByStatus(string $status): array {
        return $this->findAllWithFilters(['status' => $status, 'limit' => 1000])['data'];
    }
    
    /**
     * Create a new installation record
     * 
     * @param array $data Installation data
     * @return array Created installation record
     * @throws Exception If creation fails
     * 
     * Requirements: 1.2, 3.4
     */
    public function create($data): array {
        // Validate required fields
        $requiredFields = ['site_id', 'feasibility_id', 'initiated_by', 'atm_id'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || $data[$field] === '' || $data[$field] === null) {
                throw new Exception("The {$field} field is required");
            }
        }
        
        // Check if installation already exists for this site
        $existing = $this->findBySiteId((int)$data['site_id']);
        if ($existing) {
            throw new Exception("An installation already exists for this site");
        }
        
        // Set default status if not provided
        if (!isset($data['status']) || $data['status'] === '') {
            $data['status'] = Installation::STATUS_PENDING_MATERIALS;
        }
        
        // Set created_by if not provided
        if (!isset($data['created_by']) && isset($data['initiated_by'])) {
            $data['created_by'] = $data['initiated_by'];
        }
        
        // Build insert query
        $fields = [];
        $placeholders = [];
        $values = [];
        $types = '';
        
        // Define allowed fields and their types
        $allowedFields = $this->getAllowedFields();
        
        foreach ($allowedFields as $field => $type) {
            if (isset($data[$field]) && $data[$field] !== '') {
                $fields[] = $field;
                $placeholders[] = '?';
                $values[] = $data[$field];
                $types .= $type;
            }
        }
        
        $sql = "INSERT INTO `{$this->table}` (`" . implode('`, `', $fields) . "`) VALUES (" . implode(', ', $placeholders) . ")";
        
        $stmt = $this->db->executeQuery($sql, $values, $types);
        $insertId = $this->db->getConnection()->insert_id;
        $stmt->close();
        
        if ($insertId <= 0) {
            throw new Exception("Failed to create installation record");
        }
        
        return $this->findById($insertId);
    }

    /**
     * Update an existing installation record
     * 
     * @param int $id Installation ID
     * @param array $data Data to update
     * @return array Updated installation record
     * @throws Exception If update fails
     * 
     * Requirements: 3.4
     */
    public function update($id, $data): array {
        // Verify record exists and user has access
        $existing = $this->findById($id);
        if (!$existing) {
            throw new Exception("Installation record not found or access denied");
        }
        
        // Build update query
        $setClauses = [];
        $values = [];
        $types = '';
        
        // Define allowed fields and their types
        $allowedFields = $this->getAllowedFields();
        
        foreach ($allowedFields as $field => $type) {
            if (array_key_exists($field, $data)) {
                $setClauses[] = "`$field` = ?";
                $values[] = $data[$field] !== '' ? $data[$field] : null;
                $types .= $type;
            }
        }
        
        if (empty($setClauses)) {
            return $existing; // Nothing to update
        }
        
        // Add ID to params
        $values[] = $id;
        $types .= 'i';
        
        $sql = "UPDATE `{$this->table}` SET " . implode(', ', $setClauses) . " WHERE `id` = ?";
        
        $stmt = $this->db->executeQuery($sql, $values, $types);
        $stmt->close();
        
        return $this->findById($id);
    }
    
    /**
     * Update installation status
     * 
     * @param int $id Installation ID
     * @param string $status New status
     * @return array Updated installation record
     * @throws Exception If update fails
     */
    public function updateStatus(int $id, string $status): array {
        if (!Installation::isValidStatus($status)) {
            throw new Exception("Invalid status: $status");
        }
        
        return $this->update($id, ['status' => $status]);
    }
    
    /**
     * Delete an installation record
     * 
     * @param int $id Installation ID
     * @return bool True if deletion was successful
     * @throws Exception If deletion fails
     */
    public function delete($id): bool {
        // Verify record exists and user has access
        $existing = $this->findById($id);
        if (!$existing) {
            throw new Exception("Installation record not found or access denied");
        }
        
        $sql = "DELETE FROM `{$this->table}` WHERE `id` = ?";
        $stmt = $this->db->executeQuery($sql, [$id], 'i');
        $affectedRows = $stmt->affected_rows;
        $stmt->close();
        
        return $affectedRows > 0;
    }
    
    /**
     * Get installation with full details including site and user info
     * 
     * @param int $id Installation ID
     * @return array|null Installation with details or null
     */
    public function findWithDetails(int $id): ?array {
        $sql = "SELECT i.*, 
                       s.site_name, s.company_id, s.lho as site_lho, s.address as site_address,
                       s.city as site_city, s.state as site_state,
                       CONCAT(u.first_name, ' ', u.last_name) as initiated_by_name,
                       CONCAT(su.first_name, ' ', su.last_name) as submitted_by_name,
                       CONCAT(cu.first_name, ' ', cu.last_name) as created_by_name,
                       CONCAT(du.first_name, ' ', du.last_name) as delegated_by_name,
                       CONCAT(au.first_name, ' ', au.last_name) as assigned_by_name,
                       CONCAT(eu.first_name, ' ', eu.last_name) as assigned_engineer_name,
                       c.name as contractor_name
                FROM `{$this->table}` i
                LEFT JOIN sites s ON i.site_id = s.id
                LEFT JOIN users u ON i.initiated_by = u.id
                LEFT JOIN users su ON i.submitted_by = su.id
                LEFT JOIN users cu ON i.created_by = cu.id
                LEFT JOIN users du ON i.delegated_by = du.id
                LEFT JOIN users au ON i.assigned_by = au.id
                LEFT JOIN users eu ON i.assigned_engineer_id = eu.id
                LEFT JOIN companies c ON i.contractor_id = c.id
                WHERE i.id = ?";
        $params = [$id];
        $types = 'i';
        
        // Add company filter if user is set
        if ($this->currentUserId) {
            $filter = $this->companyIsolationService->getCompanyFilterClause(
                $this->currentUserId, 
                's.company_id'
            );
            $sql .= " AND " . $filter['clause'];
            $params = array_merge($params, $filter['params']);
            $types .= $filter['types'];
        }
        
        $result = $this->db->getResults($sql, $params, $types);
        return !empty($result) ? $result[0] : null;
    }
    
    /**
     * Count installations by status
     * 
     * @param int|null $companyId Optional company ID filter
     * @return array Array with status counts
     */
    public function countByStatus(?int $companyId = null): array {
        $whereClause = ['1=1'];
        $params = [];
        $types = '';
        
        if ($companyId !== null) {
            $whereClause[] = "s.company_id = ?";
            $params[] = $companyId;
            $types .= 'i';
        }
        
        // Add company isolation filter if user is set
        if ($this->currentUserId) {
            $filter = $this->companyIsolationService->getCompanyFilterClause(
                $this->currentUserId, 
                's.company_id'
            );
            $whereClause[] = $filter['clause'];
            $params = array_merge($params, $filter['params']);
            $types .= $filter['types'];
        }
        
        $whereSQL = ' WHERE ' . implode(' AND ', $whereClause);
        
        $sql = "SELECT i.status, COUNT(*) as count 
                FROM `{$this->table}` i
                LEFT JOIN sites s ON i.site_id = s.id" . 
                $whereSQL . " GROUP BY i.status";
        
        $result = $this->db->getResults($sql, $params, $types);
        
        // Initialize counts for all statuses
        $counts = [];
        foreach (Installation::getStatuses() as $status) {
            $counts[$status] = 0;
        }
        $counts['total'] = 0;
        
        foreach ($result as $row) {
            $counts[$row['status']] = (int)$row['count'];
            $counts['total'] += (int)$row['count'];
        }
        
        return $counts;
    }
    
    /**
     * Check if installation exists for a site
     * 
     * @param int $siteId Site ID
     * @return bool True if installation exists
     */
    public function existsForSite(int $siteId): bool {
        $sql = "SELECT COUNT(*) as count FROM `{$this->table}` WHERE site_id = ?";
        $result = $this->db->getResults($sql, [$siteId], 'i');
        return (int)($result[0]['count'] ?? 0) > 0;
    }
    
    /**
     * Get all installations for export (no pagination)
     * 
     * @param array $filters Optional filters
     * @return array List of installations
     */
    public function findAllForExport(array $filters = []): array {
        $filters['limit'] = 10000; // High limit for export
        return $this->findAllWithFilters($filters)['data'];
    }
    
    /**
     * Find installations by contractor ID
     * Returns all installations delegated to a specific contractor
     * 
     * @param int $contractorId Contractor company ID
     * @return array List of installations
     * 
     * Requirements: 2.1
     */
    public function findByContractor(int $contractorId): array {
        $sql = "SELECT i.*, 
                       s.site_name, s.company_id, s.lho as site_lho,
                       CONCAT(u.first_name, ' ', u.last_name) as initiated_by_name,
                       CONCAT(eu.first_name, ' ', eu.last_name) as assigned_engineer_name
                FROM `{$this->table}` i
                LEFT JOIN sites s ON i.site_id = s.id
                LEFT JOIN users u ON i.initiated_by = u.id
                LEFT JOIN users eu ON i.assigned_engineer_id = eu.id
                WHERE i.contractor_id = ?
                ORDER BY i.created_at DESC";
        
        return $this->db->getResults($sql, [$contractorId], 'i');
    }
    
    /**
     * Find installations by assigned engineer ID
     * Returns all installations assigned to a specific engineer
     * 
     * @param int $engineerId Engineer user ID
     * @return array List of installations
     * 
     * Requirements: 3.1
     */
    public function findByEngineer(int $engineerId): array {
        $sql = "SELECT i.*, 
                       s.site_name, s.company_id, s.lho as site_lho,
                       CONCAT(u.first_name, ' ', u.last_name) as initiated_by_name,
                       c.name as contractor_name
                FROM `{$this->table}` i
                LEFT JOIN sites s ON i.site_id = s.id
                LEFT JOIN users u ON i.initiated_by = u.id
                LEFT JOIN companies c ON i.contractor_id = c.id
                WHERE i.assigned_engineer_id = ?
                ORDER BY i.created_at DESC";
        
        return $this->db->getResults($sql, [$engineerId], 'i');
    }
    
    /**
     * Find installations pending assignment for a contractor
     * Returns installations with status 'pending_assignment' for a specific contractor
     * 
     * @param int $contractorId Contractor company ID
     * @return array List of installations pending assignment
     * 
     * Requirements: 2.1
     */
    public function findPendingAssignment(int $contractorId): array {
        $sql = "SELECT i.*, 
                       s.site_name, s.company_id, s.lho as site_lho,
                       CONCAT(u.first_name, ' ', u.last_name) as initiated_by_name
                FROM `{$this->table}` i
                LEFT JOIN sites s ON i.site_id = s.id
                LEFT JOIN users u ON i.initiated_by = u.id
                WHERE i.contractor_id = ? AND i.status = ?
                ORDER BY i.delegated_at ASC";
        
        return $this->db->getResults($sql, [$contractorId, Installation::STATUS_PENDING_ASSIGNMENT], 'is');
    }
    
    /**
     * Find installations pending ETA for an engineer
     * Returns installations with status 'pending_eta' assigned to a specific engineer
     * 
     * @param int $engineerId Engineer user ID
     * @return array List of installations pending ETA
     * 
     * Requirements: 3.1
     */
    public function findPendingETA(int $engineerId): array {
        $sql = "SELECT i.*, 
                       s.site_name, s.company_id, s.lho as site_lho,
                       CONCAT(u.first_name, ' ', u.last_name) as initiated_by_name,
                       c.name as contractor_name
                FROM `{$this->table}` i
                LEFT JOIN sites s ON i.site_id = s.id
                LEFT JOIN users u ON i.initiated_by = u.id
                LEFT JOIN companies c ON i.contractor_id = c.id
                WHERE i.assigned_engineer_id = ? AND i.status = ?
                ORDER BY i.assigned_at ASC";
        
        return $this->db->getResults($sql, [$engineerId, Installation::STATUS_PENDING_ETA], 'is');
    }
    
    /**
     * Find installations pending ADA for an engineer
     * Returns installations with status 'pending_ada' assigned to a specific engineer
     * 
     * @param int $engineerId Engineer user ID
     * @return array List of installations pending ADA
     * 
     * Requirements: 3.1
     */
    public function findPendingADA(int $engineerId): array {
        $sql = "SELECT i.*, 
                       s.site_name, s.company_id, s.lho as site_lho,
                       CONCAT(u.first_name, ' ', u.last_name) as initiated_by_name,
                       c.name as contractor_name
                FROM `{$this->table}` i
                LEFT JOIN sites s ON i.site_id = s.id
                LEFT JOIN users u ON i.initiated_by = u.id
                LEFT JOIN companies c ON i.contractor_id = c.id
                WHERE i.assigned_engineer_id = ? AND i.status = ?
                ORDER BY i.eta_submitted_at ASC";
        
        return $this->db->getResults($sql, [$engineerId, Installation::STATUS_PENDING_ADA], 'is');
    }
    
    /**
     * Get allowed fields and their types for database operations
     * 
     * @return array Field name => type mapping
     */
    private function getAllowedFields(): array {
        return [
            // Integer fields
            'site_id' => 'i',
            'feasibility_id' => 'i',
            'initiated_by' => 'i',
            'created_by' => 'i',
            'submitted_by' => 'i',
            // Delegation fields (Requirements: 1.4)
            'contractor_id' => 'i',
            'delegated_by' => 'i',
            // Assignment fields (Requirements: 2.4)
            'assigned_engineer_id' => 'i',
            'assigned_by' => 'i',
            
            // String fields - Site Information
            'atm_id' => 's',
            'atm_id_2' => 's',
            'atm_id_3' => 's',
            'address' => 's',
            'city' => 's',
            'location' => 's',
            'lho' => 's',
            'state' => 's',
            'atm_working_1' => 's',
            'atm_working_2' => 's',
            'atm_working_3' => 's',
            
            // String fields - Vendor/Engineer
            'vendor_name' => 's',
            'engineer_name' => 's',
            'engineer_number' => 's',
            
            // String fields - Router Section
            'router_serial' => 's',
            'router_make' => 's',
            'router_model' => 's',
            'router_fixed' => 's',
            'router_fixed_remarks' => 's',
            'router_fixed_snaps' => 's',
            'router_status' => 's',
            'router_status_remarks' => 's',
            'router_status_snaps' => 's',
            
            // String fields - Adaptor Section
            'adaptor_installed' => 's',
            'adaptor_snaps' => 's',
            'adaptor_status' => 's',
            'adaptor_status_remarks' => 's',
            'adaptor_status_snaps' => 's',
            
            // String fields - LAN Cable Section
            'lan_cable_installed' => 's',
            'lan_cable_install_remark' => 's',
            'lan_cable_install_snap' => 's',
            'lan_cable_status' => 's',
            'lan_cable_status_not_working_reasons' => 's',
            'lan_cable_status_remark' => 's',
            'lan_cable_status_snap' => 's',
            
            // String fields - Antenna Section
            'antenna_installed' => 's',
            'antenna_remarks' => 's',
            'antenna_snaps' => 's',
            'antenna_status' => 's',
            'antenna_status_remarks' => 's',
            'antenna_status_snaps' => 's',
            
            // String fields - GPS Section
            'gps_installed' => 's',
            'gps_remarks' => 's',
            'gps_snaps' => 's',
            'gps_status' => 's',
            'gps_status_remarks' => 's',
            'gps_status_snaps' => 's',
            
            // String fields - WiFi Section
            'wifi_installed' => 's',
            'wifi_remarks' => 's',
            'wifi_snaps' => 's',
            'wifi_status' => 's',
            'wifi_status_remarks' => 's',
            'wifi_status_snaps' => 's',
            
            // String fields - Airtel SIM Section
            'airtel_sim_installed' => 's',
            'airtel_sim_remarks' => 's',
            'airtel_sim_snaps' => 's',
            'airtel_sim_status' => 's',
            'airtel_sim_status_remarks' => 's',
            'airtel_sim_status_snaps' => 's',
            
            // String fields - Vodafone SIM Section
            'vodafone_sim_installed' => 's',
            'vodafone_sim_remarks' => 's',
            'vodafone_sim_snaps' => 's',
            'vodafone_sim_status' => 's',
            'vodafone_sim_status_remarks' => 's',
            'vodafone_sim_status_snaps' => 's',
            
            // String fields - JIO SIM Section
            'jio_sim_installed' => 's',
            'jio_sim_remarks' => 's',
            'jio_sim_snaps' => 's',
            'jio_sim_status' => 's',
            'jio_sim_status_remarks' => 's',
            'jio_sim_status_snaps' => 's',
            
            // String fields - Verification Section
            'signature_image' => 's',
            'vendor_stamp' => 's',
            
            // Status
            'status' => 's',
            
            // Datetime fields
            'initiated_at' => 's',
            'submitted_at' => 's',
            // Delegation datetime (Requirements: 1.4)
            'delegated_at' => 's',
            // Assignment datetime (Requirements: 2.4)
            'assigned_at' => 's',
            // ETA/ADA fields (Requirements: 3.3, 3.5)
            'eta_date' => 's',
            'eta_submitted_at' => 's',
            'ada_date' => 's',
            'ada_submitted_at' => 's'
        ];
    }
}
