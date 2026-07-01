<?php
/**
 * MaterialRequestRepository
 * Provides data access for Material Requests with company isolation and role-based access
 * 
 * Requirements: 4.1, 4.2, 6.1, 7.1, 9.5, 9.6
 * - CRUD operations for Material Requests
 * - Company isolation for multi-tenant support
 * - Role-based filtering (ADV, Contractor, Engineer)
 * - Status management with valid transitions
 * - Duplicate request prevention
 */

require_once __DIR__ . '/BaseRepository.php';

class MaterialRequestRepository extends BaseRepository {
    protected $table = 'material_requests';
    protected $primaryKey = 'id';
    protected $companyIdColumn = 'company_id';
    protected $applyCompanyFilter = true;
    
    // Status constants
    const STATUS_REQUESTED = 'requested';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';
    const STATUS_DISPATCHED = 'dispatched';
    const STATUS_RECEIVED = 'received';
    
    /**
     * Find all Material Requests with pagination and filters
     * Requirement 9.5
     * 
     * @param array $filters Filters: search, status, date_from, date_to, page, limit, orderBy, orderDir
     * @param int|null $companyId Company ID for isolation
     * @return array Paginated result with data, total, page, limit, totalPages
     */
    public function findAllPaginated(array $filters = [], ?int $companyId = null): array {
        $page = isset($filters['page']) ? max(1, (int)$filters['page']) : 1;
        $limit = isset($filters['limit']) ? max(1, min(100, (int)$filters['limit'])) : 10;
        $offset = ($page - 1) * $limit;
        $orderBy = $filters['orderBy'] ?? 'requested_at';
        $orderDir = strtoupper($filters['orderDir'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';
        
        $whereClause = [];
        $params = [];
        $types = '';
        
        // Company filter
        if ($companyId !== null) {
            $whereClause[] = "mr.`company_id` = ?";
            $params[] = $companyId;
            $types .= 'i';
        }
        
        // Status filter
        if (isset($filters['status']) && $filters['status'] !== '') {
            $whereClause[] = "mr.`status` = ?";
            $params[] = $filters['status'];
            $types .= 's';
        }
        
        // Date range filters
        if (!empty($filters['date_from'])) {
            $whereClause[] = "DATE(mr.`requested_at`) >= ?";
            $params[] = $filters['date_from'];
            $types .= 's';
        }
        
        if (!empty($filters['date_to'])) {
            $whereClause[] = "DATE(mr.`requested_at`) <= ?";
            $params[] = $filters['date_to'];
            $types .= 's';
        }
        
        // Search filter (searches in site name, material master name)
        if (!empty($filters['search'])) {
            $whereClause[] = "(s.`site_name` LIKE ? OR mm.`name` LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $types .= 'ss';
        }
        
        $whereSQL = !empty($whereClause) ? ' WHERE ' . implode(' AND ', $whereClause) : '';
        
        // Whitelist allowed order columns
        $allowedOrderColumns = ['id', 'status', 'requested_at', 'approved_at', 'dispatched_at', 'received_at'];
        if (!in_array($orderBy, $allowedOrderColumns)) {
            $orderBy = 'requested_at';
        }
        
        // Get total count
        $countSQL = "SELECT COUNT(*) as total 
                     FROM `{$this->table}` mr
                     LEFT JOIN sites s ON mr.site_id = s.id
                     LEFT JOIN material_masters mm ON mr.material_master_id = mm.id" . $whereSQL;
        $countResult = $this->db->getResults($countSQL, $params, $types);
        $total = (int)($countResult[0]['total'] ?? 0);
        
        // Get paginated data with site and material master info
        $dataSQL = "SELECT mr.*, 
                           s.site_name, s.lho, s.city, s.state,
                           mm.name as material_master_name,
                           CONCAT(rb.first_name, ' ', rb.last_name) as requested_by_name,
                           CONCAT(ab.first_name, ' ', ab.last_name) as approved_by_name,
                           CONCAT(rcb.first_name, ' ', rcb.last_name) as received_by_name,
                           (SELECT COUNT(*) FROM material_request_items WHERE material_request_id = mr.id) as item_count
                    FROM `{$this->table}` mr
                    LEFT JOIN sites s ON mr.site_id = s.id
                    LEFT JOIN material_masters mm ON mr.material_master_id = mm.id
                    LEFT JOIN users rb ON mr.requested_by = rb.id
                    LEFT JOIN users ab ON mr.approved_by = ab.id
                    LEFT JOIN users rcb ON mr.received_by = rcb.id" .
                   $whereSQL .
                   " ORDER BY mr.`$orderBy` $orderDir LIMIT ? OFFSET ?";
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
     * Find Material Request by ID with site and items
     * Requirement 4.1
     * 
     * @param int $id Material Request ID
     * @param int|null $companyId Optional company ID for validation
     * @return array|null Material Request with details or null
     */
    public function findByIdWithDetails(int $id, ?int $companyId = null): ?array {
        $sql = "SELECT mr.*, 
                       s.site_name, s.lho, s.city, s.state, s.address,
                       mm.name as material_master_name, mm.description as material_master_description,
                       CONCAT(rb.first_name, ' ', rb.last_name) as requested_by_name,
                       CONCAT(ab.first_name, ' ', ab.last_name) as approved_by_name,
                       CONCAT(rcb.first_name, ' ', rcb.last_name) as received_by_name
                FROM `{$this->table}` mr
                LEFT JOIN sites s ON mr.site_id = s.id
                LEFT JOIN material_masters mm ON mr.material_master_id = mm.id
                LEFT JOIN users rb ON mr.requested_by = rb.id
                LEFT JOIN users ab ON mr.approved_by = ab.id
                LEFT JOIN users rcb ON mr.received_by = rcb.id
                WHERE mr.`id` = ?";
        $params = [$id];
        $types = 'i';
        
        if ($companyId !== null) {
            $sql .= " AND mr.`company_id` = ?";
            $params[] = $companyId;
            $types .= 'i';
        }
        
        $result = $this->db->getResults($sql, $params, $types);
        
        if (empty($result)) {
            return null;
        }
        
        $request = $result[0];
        $request['items'] = $this->getItems($id);
        
        return $request;
    }
    
    /**
     * Get items for a Material Request with product details
     * 
     * @param int $requestId Material Request ID
     * @return array Items with product details
     */
    public function getItems(int $requestId): array {
        $sql = "SELECT mri.*, 
                       p.name as product_name, 
                       p.unit_of_measure,
                       p.is_serializable,
                       p.is_repairable,
                       p.inventory_type,
                       pc.name as category_name
                FROM material_request_items mri
                LEFT JOIN products p ON mri.product_id = p.id
                LEFT JOIN product_categories pc ON p.category_id = pc.id
                WHERE mri.material_request_id = ?
                ORDER BY p.name";
        
        return $this->db->getResults($sql, [$requestId], 'i');
    }
    
    /**
     * Create a new Material Request
     * Requirement 9.6
     * 
     * @param array $data Material Request data
     * @return int The ID of the newly created Material Request
     * @throws Exception If creation fails or duplicate exists
     */
    public function createRequest(array $data): int {
        // Validate required fields
        if (empty($data['site_id'])) {
            throw new Exception("Site ID is required");
        }
        if (empty($data['material_master_id'])) {
            throw new Exception("Material Master ID is required");
        }
        if (empty($data['company_id'])) {
            throw new Exception("Company ID is required");
        }
        
        // Check for duplicate active request
        if ($this->hasActiveRequest($data['site_id'])) {
            throw new Exception("An active material request already exists for this site");
        }
        
        $now = date('Y-m-d H:i:s');
        
        $sql = "INSERT INTO `{$this->table}` 
                (`site_id`, `material_master_id`, `status`, `company_id`, `requested_by`, `requested_at`, `notes`, `created_at`, `updated_at`) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $params = [
            $data['site_id'],
            $data['material_master_id'],
            self::STATUS_REQUESTED,
            $data['company_id'],
            $data['requested_by'] ?? null,
            $now,
            $data['notes'] ?? null,
            $now,
            $now
        ];
        $types = 'iisiissss';
        
        $stmt = $this->db->executeQuery($sql, $params, $types);
        $insertId = $this->db->getConnection()->insert_id;
        $stmt->close();
        
        if ($insertId <= 0) {
            throw new Exception("Failed to create Material Request");
        }
        
        return $insertId;
    }
    
    /**
     * Update Material Request status with timestamp
     * Requirement 9.6
     * 
     * @param int $id Material Request ID
     * @param string $status New status
     * @param int|null $userId User performing the action
     * @return bool True if update was successful
     * @throws Exception If update fails or invalid transition
     */
    public function updateStatus(int $id, string $status, ?int $userId = null): bool {
        // Verify record exists
        $existing = $this->findByIdWithDetails($id);
        if (!$existing) {
            throw new Exception("Material Request not found");
        }
        
        // Validate status transition
        if (!$this->isValidTransition($existing['status'], $status)) {
            throw new Exception("Invalid status transition from '{$existing['status']}' to '{$status}'");
        }
        
        $now = date('Y-m-d H:i:s');
        $setClauses = ["`status` = ?", "`updated_at` = ?"];
        $params = [$status, $now];
        $types = 'ss';
        
        // Set appropriate timestamp and user based on status
        switch ($status) {
            case self::STATUS_APPROVED:
                $setClauses[] = "`approved_at` = ?";
                $params[] = $now;
                $types .= 's';
                if ($userId) {
                    $setClauses[] = "`approved_by` = ?";
                    $params[] = $userId;
                    $types .= 'i';
                }
                break;
            case self::STATUS_REJECTED:
                $setClauses[] = "`rejected_at` = ?";
                $params[] = $now;
                $types .= 's';
                if ($userId) {
                    $setClauses[] = "`rejected_by` = ?";
                    $params[] = $userId;
                    $types .= 'i';
                }
                break;
            case self::STATUS_DISPATCHED:
                $setClauses[] = "`dispatched_at` = ?";
                $params[] = $now;
                $types .= 's';
                break;
            case self::STATUS_RECEIVED:
                $setClauses[] = "`received_at` = ?";
                $params[] = $now;
                $types .= 's';
                if ($userId) {
                    $setClauses[] = "`received_by` = ?";
                    $params[] = $userId;
                    $types .= 'i';
                }
                break;
        }
        
        // Add ID to params
        $params[] = $id;
        $types .= 'i';
        
        $sql = "UPDATE `{$this->table}` SET " . implode(', ', $setClauses) . " WHERE `id` = ?";
        
        $stmt = $this->db->executeQuery($sql, $params, $types);
        $stmt->close();
        
        return true;
    }
    
    /**
     * Find Material Requests for contractor's delegated sites
     * Requirement 6.1
     * 
     * @param int $contractorCompanyId Contractor's company ID
     * @param array $filters Optional filters
     * @return array Paginated result
     */
    public function findByContractor(int $contractorCompanyId, array $filters = []): array {
        $page = isset($filters['page']) ? max(1, (int)$filters['page']) : 1;
        $limit = isset($filters['limit']) ? max(1, min(100, (int)$filters['limit'])) : 10;
        $offset = ($page - 1) * $limit;
        
        $whereClause = ["sd.contractor_id = ?", "sd.status = 'accepted'"];
        $params = [$contractorCompanyId];
        $types = 'i';
        
        // Status filter
        if (isset($filters['status']) && $filters['status'] !== '') {
            $whereClause[] = "mr.`status` = ?";
            $params[] = $filters['status'];
            $types .= 's';
        }
        
        // Date range filters
        if (!empty($filters['date_from'])) {
            $whereClause[] = "DATE(mr.`requested_at`) >= ?";
            $params[] = $filters['date_from'];
            $types .= 's';
        }
        
        if (!empty($filters['date_to'])) {
            $whereClause[] = "DATE(mr.`requested_at`) <= ?";
            $params[] = $filters['date_to'];
            $types .= 's';
        }
        
        // Search filter
        if (!empty($filters['search'])) {
            $whereClause[] = "(s.`site_name` LIKE ? OR mm.`name` LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $types .= 'ss';
        }
        
        $whereSQL = ' WHERE ' . implode(' AND ', $whereClause);
        
        // Get total count
        $countSQL = "SELECT COUNT(*) as total 
                     FROM `{$this->table}` mr
                     INNER JOIN sites s ON mr.site_id = s.id
                     INNER JOIN site_delegations sd ON s.id = sd.site_id
                     LEFT JOIN material_masters mm ON mr.material_master_id = mm.id" . $whereSQL;
        $countResult = $this->db->getResults($countSQL, $params, $types);
        $total = (int)($countResult[0]['total'] ?? 0);
        
        // Get paginated data
        $dataSQL = "SELECT mr.*, 
                           s.site_name, s.lho, s.city, s.state,
                           mm.name as material_master_name,
                           CONCAT(rb.first_name, ' ', rb.last_name) as requested_by_name,
                           (SELECT COUNT(*) FROM material_request_items WHERE material_request_id = mr.id) as item_count
                    FROM `{$this->table}` mr
                    INNER JOIN sites s ON mr.site_id = s.id
                    INNER JOIN site_delegations sd ON s.id = sd.site_id
                    LEFT JOIN material_masters mm ON mr.material_master_id = mm.id
                    LEFT JOIN users rb ON mr.requested_by = rb.id" .
                   $whereSQL .
                   " ORDER BY mr.requested_at DESC LIMIT ? OFFSET ?";
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
     * Find Material Requests for engineer's assigned sites
     * Requirement 7.1
     * 
     * @param int $engineerId Engineer's user ID
     * @param array $filters Optional filters
     * @return array Paginated result
     */
    public function findByEngineer(int $engineerId, array $filters = []): array {
        $page = isset($filters['page']) ? max(1, (int)$filters['page']) : 1;
        $limit = isset($filters['limit']) ? max(1, min(100, (int)$filters['limit'])) : 10;
        $offset = ($page - 1) * $limit;
        
        $whereClause = ["ea.engineer_id = ?", "ea.status IN ('assigned', 'in_progress')"];
        $params = [$engineerId];
        $types = 'i';
        
        // Status filter
        if (isset($filters['status']) && $filters['status'] !== '') {
            $whereClause[] = "mr.`status` = ?";
            $params[] = $filters['status'];
            $types .= 's';
        }
        
        // Date range filters
        if (!empty($filters['date_from'])) {
            $whereClause[] = "DATE(mr.`requested_at`) >= ?";
            $params[] = $filters['date_from'];
            $types .= 's';
        }
        
        if (!empty($filters['date_to'])) {
            $whereClause[] = "DATE(mr.`requested_at`) <= ?";
            $params[] = $filters['date_to'];
            $types .= 's';
        }
        
        // Search filter
        if (!empty($filters['search'])) {
            $whereClause[] = "(s.`site_name` LIKE ? OR mm.`name` LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $types .= 'ss';
        }
        
        $whereSQL = ' WHERE ' . implode(' AND ', $whereClause);
        
        // Get total count
        $countSQL = "SELECT COUNT(*) as total 
                     FROM `{$this->table}` mr
                     INNER JOIN sites s ON mr.site_id = s.id
                     INNER JOIN engineer_assignments ea ON s.id = ea.site_id
                     LEFT JOIN material_masters mm ON mr.material_master_id = mm.id" . $whereSQL;
        $countResult = $this->db->getResults($countSQL, $params, $types);
        $total = (int)($countResult[0]['total'] ?? 0);
        
        // Get paginated data
        $dataSQL = "SELECT mr.*, 
                           s.site_name, s.lho, s.city, s.state,
                           mm.name as material_master_name,
                           CONCAT(rb.first_name, ' ', rb.last_name) as requested_by_name,
                           (SELECT COUNT(*) FROM material_request_items WHERE material_request_id = mr.id) as item_count
                    FROM `{$this->table}` mr
                    INNER JOIN sites s ON mr.site_id = s.id
                    INNER JOIN engineer_assignments ea ON s.id = ea.site_id
                    LEFT JOIN material_masters mm ON mr.material_master_id = mm.id
                    LEFT JOIN users rb ON mr.requested_by = rb.id" .
                   $whereSQL .
                   " ORDER BY mr.requested_at DESC LIMIT ? OFFSET ?";
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
     * Find Material Request by site ID
     * 
     * @param int $siteId Site ID
     * @return array|null Most recent request or null
     */
    public function findBySiteId(int $siteId): ?array {
        $sql = "SELECT mr.*, 
                       mm.name as material_master_name
                FROM `{$this->table}` mr
                LEFT JOIN material_masters mm ON mr.material_master_id = mm.id
                WHERE mr.`site_id` = ?
                ORDER BY mr.`requested_at` DESC
                LIMIT 1";
        
        $result = $this->db->getResults($sql, [$siteId], 'i');
        return !empty($result) ? $result[0] : null;
    }
    
    /**
     * Find active Material Request for a site (not received)
     * 
     * @param int $siteId Site ID
     * @return array|null Active request or null
     */
    public function findActiveBySiteId(int $siteId): ?array {
        $sql = "SELECT mr.*, 
                       mm.name as material_master_name
                FROM `{$this->table}` mr
                LEFT JOIN material_masters mm ON mr.material_master_id = mm.id
                WHERE mr.`site_id` = ? AND mr.`status` != ?
                ORDER BY mr.`requested_at` DESC
                LIMIT 1";
        
        $result = $this->db->getResults($sql, [$siteId, self::STATUS_RECEIVED], 'is');
        return !empty($result) ? $result[0] : null;
    }
    
    /**
     * Check if site has an active material request
     * 
     * @param int $siteId Site ID
     * @return bool True if active request exists
     */
    public function hasActiveRequest(int $siteId): bool {
        return $this->findActiveBySiteId($siteId) !== null;
    }
    
    /**
     * Get material status for a site
     * 
     * @param int $siteId Site ID
     * @return string Material status
     */
    public function getSiteStatus(int $siteId): string {
        $request = $this->findActiveBySiteId($siteId);
        
        if (!$request) {
            // Check if there's a completed request
            $sql = "SELECT * FROM `{$this->table}` 
                    WHERE `site_id` = ? AND `status` = ?
                    ORDER BY `received_at` DESC
                    LIMIT 1";
            $result = $this->db->getResults($sql, [$siteId, self::STATUS_RECEIVED], 'is');
            
            if (!empty($result)) {
                return self::STATUS_RECEIVED;
            }
            
            return 'not_requested';
        }
        
        return $request['status'];
    }
    
    /**
     * Check if engineer is assigned to the site of a material request
     * 
     * @param int $requestId Material Request ID
     * @param int $engineerId Engineer's user ID
     * @return bool True if engineer is assigned
     */
    public function isEngineerAssigned(int $requestId, int $engineerId): bool {
        $sql = "SELECT COUNT(*) as count
                FROM `{$this->table}` mr
                INNER JOIN engineer_assignments ea ON mr.site_id = ea.site_id
                WHERE mr.id = ? AND ea.engineer_id = ? AND ea.status IN ('assigned', 'in_progress')";
        
        $result = $this->db->getResults($sql, [$requestId, $engineerId], 'ii');
        return $result[0]['count'] > 0;
    }
    
    /**
     * Create items for a Material Request from Material Master
     * 
     * @param int $requestId Material Request ID
     * @param int $masterId Material Master ID
     * @return bool Success
     */
    public function createItemsFromMaster(int $requestId, int $masterId): bool {
        $sql = "INSERT INTO material_request_items 
                (`material_request_id`, `product_id`, `quantity_requested`, `quantity_dispatched`, `quantity_received`, `created_at`)
                SELECT ?, product_id, quantity, 0, 0, NOW()
                FROM material_master_items
                WHERE material_master_id = ?";
        
        $stmt = $this->db->executeQuery($sql, [$requestId, $masterId], 'ii');
        $stmt->close();
        
        return true;
    }
    
    /**
     * Get status counts by company
     * 
     * @param int $companyId Company ID
     * @return array Status counts
     */
    public function getStatusCounts(int $companyId): array {
        $sql = "SELECT status, COUNT(*) as count 
                FROM `{$this->table}` 
                WHERE company_id = ?
                GROUP BY status";
        
        $results = $this->db->getResults($sql, [$companyId], 'i');
        
        $counts = [
            self::STATUS_REQUESTED => 0,
            self::STATUS_APPROVED => 0,
            self::STATUS_DISPATCHED => 0,
            self::STATUS_RECEIVED => 0,
            'total' => 0
        ];
        
        foreach ($results as $row) {
            $counts[$row['status']] = (int)$row['count'];
            $counts['total'] += (int)$row['count'];
        }
        
        return $counts;
    }
    
    /**
     * Check if a status transition is valid
     * 
     * @param string $currentStatus Current status
     * @param string $newStatus New status
     * @return bool True if transition is valid
     */
    public function isValidTransition(string $currentStatus, string $newStatus): bool {
        $validTransitions = [
            self::STATUS_REQUESTED => [self::STATUS_APPROVED, self::STATUS_REJECTED],
            self::STATUS_APPROVED => [self::STATUS_DISPATCHED],
            self::STATUS_DISPATCHED => [self::STATUS_RECEIVED],
            self::STATUS_RECEIVED => [], // Terminal state
            self::STATUS_REJECTED => [] // Terminal state
        ];
        
        return isset($validTransitions[$currentStatus]) && 
               in_array($newStatus, $validTransitions[$currentStatus]);
    }
    
    /**
     * Mark all items as dispatched
     * 
     * @param int $requestId Material Request ID
     * @return bool Success
     */
    public function markItemsDispatched(int $requestId): bool {
        $sql = "UPDATE material_request_items 
                SET `quantity_dispatched` = `quantity_requested` 
                WHERE `material_request_id` = ?";
        
        $stmt = $this->db->executeQuery($sql, [$requestId], 'i');
        $stmt->close();
        
        return true;
    }
    
    /**
     * Mark all items as received
     * 
     * @param int $requestId Material Request ID
     * @return bool Success
     */
    public function markItemsReceived(int $requestId): bool {
        $sql = "UPDATE material_request_items 
                SET `quantity_received` = `quantity_dispatched` 
                WHERE `material_request_id` = ?";
        
        $stmt = $this->db->executeQuery($sql, [$requestId], 'i');
        $stmt->close();
        
        return true;
    }
}
