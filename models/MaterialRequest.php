<?php
/**
 * MaterialRequest Model
 * Represents a material request generated from a site linked to a Material Master
 * 
 * Requirements: 3.3, 4.3
 * - Full workflow from request to receipt
 * - Status tracking with timestamps
 * - Site, Material Master, and items relationships
 */

require_once __DIR__ . '/BaseModel.php';

class MaterialRequest extends BaseModel {
    protected $table = 'material_requests';
    protected $fillable = [
        'site_id', 'material_master_id', 'status', 'company_id',
        'requested_by', 'requested_at', 'approved_by', 'approved_at',
        'dispatched_at', 'received_at', 'received_by', 'notes',
        'created_at', 'updated_at'
    ];
    
    // Status constants
    const STATUS_REQUESTED = 'requested';
    const STATUS_APPROVED = 'approved';
    const STATUS_DISPATCHED = 'dispatched';
    const STATUS_RECEIVED = 'received';
    
    /**
     * Get all valid statuses
     * 
     * @return array Valid status values
     */
    public static function getStatuses(): array {
        return [
            self::STATUS_REQUESTED,
            self::STATUS_APPROVED,
            self::STATUS_DISPATCHED,
            self::STATUS_RECEIVED
        ];
    }
    
    /**
     * Check if a status is valid
     * 
     * @param string $status Status to validate
     * @return bool True if valid
     */
    public static function isValidStatus(string $status): bool {
        return in_array($status, self::getStatuses());
    }
    
    /**
     * Get valid status transitions
     * Requirement 5.2, 5.3, 5.4
     * 
     * @return array Map of current status to allowed next statuses
     */
    public static function getValidTransitions(): array {
        return [
            self::STATUS_REQUESTED => [self::STATUS_APPROVED],
            self::STATUS_APPROVED => [self::STATUS_DISPATCHED],
            self::STATUS_DISPATCHED => [self::STATUS_RECEIVED],
            self::STATUS_RECEIVED => [] // Terminal state
        ];
    }
    
    /**
     * Check if a status transition is valid
     * 
     * @param string $currentStatus Current status
     * @param string $newStatus New status
     * @return bool True if transition is valid
     */
    public static function isValidTransition(string $currentStatus, string $newStatus): bool {
        $transitions = self::getValidTransitions();
        return isset($transitions[$currentStatus]) && 
               in_array($newStatus, $transitions[$currentStatus]);
    }
    
    /**
     * Find Material Request by ID with all relationships
     * Requirement 4.3
     * 
     * @param int $id Material Request ID
     * @return array|null Material Request with relationships or null
     */
    public function findWithDetails(int $id): ?array {
        $sql = "SELECT mr.*, 
                       s.site_name, s.lho, s.city, s.state,
                       mm.name as material_master_name, mm.description as material_master_description,
                       rb.name as requested_by_name,
                       ab.name as approved_by_name,
                       rcb.name as received_by_name
                FROM `{$this->table}` mr
                LEFT JOIN sites s ON mr.site_id = s.id
                LEFT JOIN material_masters mm ON mr.material_master_id = mm.id
                LEFT JOIN users rb ON mr.requested_by = rb.id
                LEFT JOIN users ab ON mr.approved_by = ab.id
                LEFT JOIN users rcb ON mr.received_by = rcb.id
                WHERE mr.id = ?";
        
        $result = DatabaseConfig::getInstance()->getResults($sql, [$id], 'i');
        
        if (empty($result)) {
            return null;
        }
        
        $request = $result[0];
        $request['items'] = $this->getItems($id);
        
        return $request;
    }
    
    /**
     * Get items for a Material Request
     * 
     * @param int $requestId Material Request ID
     * @return array Items with product details
     */
    public function getItems(int $requestId): array {
        $sql = "SELECT mri.*, 
                       p.name as product_name, 
                       p.unit_of_measure,
                       p.is_serializable,
                       pc.name as category_name
                FROM material_request_items mri
                LEFT JOIN products p ON mri.product_id = p.id
                LEFT JOIN product_categories pc ON p.category_id = pc.id
                WHERE mri.material_request_id = ?
                ORDER BY p.name";
        
        return DatabaseConfig::getInstance()->getResults($sql, [$requestId], 'i');
    }
    
    /**
     * Find all Material Requests by company with details
     * Requirement 4.1
     * 
     * @param int $companyId Company ID
     * @param array $filters Optional filters (status, date_from, date_to, search)
     * @return array Material Requests
     */
    public function findByCompanyWithDetails(int $companyId, array $filters = []): array {
        $sql = "SELECT mr.*, 
                       s.site_name, s.lho, s.city,
                       mm.name as material_master_name,
                       rb.name as requested_by_name,
                       (SELECT COUNT(*) FROM material_request_items WHERE material_request_id = mr.id) as item_count
                FROM `{$this->table}` mr
                LEFT JOIN sites s ON mr.site_id = s.id
                LEFT JOIN material_masters mm ON mr.material_master_id = mm.id
                LEFT JOIN users rb ON mr.requested_by = rb.id
                WHERE mr.company_id = ?";
        $params = [$companyId];
        $types = 'i';
        
        // Apply filters
        if (!empty($filters['status'])) {
            $sql .= " AND mr.status = ?";
            $params[] = $filters['status'];
            $types .= 's';
        }
        
        if (!empty($filters['date_from'])) {
            $sql .= " AND DATE(mr.requested_at) >= ?";
            $params[] = $filters['date_from'];
            $types .= 's';
        }
        
        if (!empty($filters['date_to'])) {
            $sql .= " AND DATE(mr.requested_at) <= ?";
            $params[] = $filters['date_to'];
            $types .= 's';
        }
        
        if (!empty($filters['search'])) {
            $sql .= " AND (s.site_name LIKE ? OR mm.name LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $types .= 'ss';
        }
        
        $sql .= " ORDER BY mr.requested_at DESC";
        
        return DatabaseConfig::getInstance()->getResults($sql, $params, $types);
    }
    
    /**
     * Find Material Requests by site ID
     * 
     * @param int $siteId Site ID
     * @return array Material Requests
     */
    public function findBySiteId(int $siteId): array {
        return $this->findAll(['site_id' => $siteId], 'requested_at DESC');
    }
    
    /**
     * Find active Material Request for a site
     * Requirement 3.5
     * 
     * @param int $siteId Site ID
     * @return array|null Active request or null
     */
    public function findActiveBySiteId(int $siteId): ?array {
        $sql = "SELECT * FROM `{$this->table}` 
                WHERE `site_id` = ? AND `status` != ?
                ORDER BY `requested_at` DESC
                LIMIT 1";
        
        $result = DatabaseConfig::getInstance()->getResults($sql, [$siteId, self::STATUS_RECEIVED], 'is');
        return !empty($result) ? $result[0] : null;
    }
    
    /**
     * Check if site has an active material request
     * Requirement 3.5
     * 
     * @param int $siteId Site ID
     * @return bool True if active request exists
     */
    public function hasActiveRequest(int $siteId): bool {
        return $this->findActiveBySiteId($siteId) !== null;
    }
    
    /**
     * Get material status for a site
     * Requirements 2.2, 2.3, 2.4, 2.5, 2.6
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
            $result = DatabaseConfig::getInstance()->getResults($sql, [$siteId, self::STATUS_RECEIVED], 'is');
            
            if (!empty($result)) {
                return self::STATUS_RECEIVED;
            }
            
            return 'not_requested';
        }
        
        return $request['status'];
    }
    
    /**
     * Find Material Requests for contractor's delegated sites
     * Requirement 6.1
     * 
     * @param int $contractorCompanyId Contractor's company ID
     * @param array $filters Optional filters
     * @return array Material Requests
     */
    public function findByContractor(int $contractorCompanyId, array $filters = []): array {
        $sql = "SELECT mr.*, 
                       s.site_name, s.lho, s.city,
                       mm.name as material_master_name,
                       rb.name as requested_by_name,
                       (SELECT COUNT(*) FROM material_request_items WHERE material_request_id = mr.id) as item_count
                FROM `{$this->table}` mr
                INNER JOIN sites s ON mr.site_id = s.id
                INNER JOIN site_delegations sd ON s.id = sd.site_id AND sd.contractor_company_id = ?
                LEFT JOIN material_masters mm ON mr.material_master_id = mm.id
                LEFT JOIN users rb ON mr.requested_by = rb.id
                WHERE sd.status = 'active'";
        $params = [$contractorCompanyId];
        $types = 'i';
        
        // Apply filters
        if (!empty($filters['status'])) {
            $sql .= " AND mr.status = ?";
            $params[] = $filters['status'];
            $types .= 's';
        }
        
        if (!empty($filters['search'])) {
            $sql .= " AND (s.site_name LIKE ? OR mm.name LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $types .= 'ss';
        }
        
        $sql .= " ORDER BY mr.requested_at DESC";
        
        return DatabaseConfig::getInstance()->getResults($sql, $params, $types);
    }
    
    /**
     * Find Material Requests for engineer's assigned sites
     * Requirement 7.1
     * 
     * @param int $engineerId Engineer's user ID
     * @param array $filters Optional filters
     * @return array Material Requests
     */
    public function findByEngineer(int $engineerId, array $filters = []): array {
        $sql = "SELECT mr.*, 
                       s.site_name, s.lho, s.city,
                       mm.name as material_master_name,
                       rb.name as requested_by_name,
                       (SELECT COUNT(*) FROM material_request_items WHERE material_request_id = mr.id) as item_count
                FROM `{$this->table}` mr
                INNER JOIN sites s ON mr.site_id = s.id
                INNER JOIN engineer_assignments ea ON s.id = ea.site_id AND ea.engineer_id = ?
                LEFT JOIN material_masters mm ON mr.material_master_id = mm.id
                LEFT JOIN users rb ON mr.requested_by = rb.id
                WHERE ea.status = 'active'";
        $params = [$engineerId];
        $types = 'i';
        
        // Apply filters
        if (!empty($filters['status'])) {
            $sql .= " AND mr.status = ?";
            $params[] = $filters['status'];
            $types .= 's';
        }
        
        if (!empty($filters['search'])) {
            $sql .= " AND (s.site_name LIKE ? OR mm.name LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $types .= 'ss';
        }
        
        $sql .= " ORDER BY mr.requested_at DESC";
        
        return DatabaseConfig::getInstance()->getResults($sql, $params, $types);
    }
    
    /**
     * Check if engineer is assigned to the site of a material request
     * Requirement 7.4
     * 
     * @param int $requestId Material Request ID
     * @param int $engineerId Engineer's user ID
     * @return bool True if engineer is assigned
     */
    public function isEngineerAssigned(int $requestId, int $engineerId): bool {
        $sql = "SELECT COUNT(*) as count
                FROM `{$this->table}` mr
                INNER JOIN engineer_assignments ea ON mr.site_id = ea.site_id
                WHERE mr.id = ? AND ea.engineer_id = ? AND ea.status = 'active'";
        
        $result = DatabaseConfig::getInstance()->getResults($sql, [$requestId, $engineerId], 'ii');
        return $result[0]['count'] > 0;
    }
    
    /**
     * Update status with timestamp
     * Requirements 5.2, 5.3, 7.3
     * 
     * @param int $id Material Request ID
     * @param string $status New status
     * @param int|null $userId User performing the action
     * @return bool Success
     */
    public function updateStatus(int $id, string $status, ?int $userId = null): bool {
        $data = [
            'status' => $status,
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        // Set appropriate timestamp and user based on status
        switch ($status) {
            case self::STATUS_APPROVED:
                $data['approved_at'] = date('Y-m-d H:i:s');
                if ($userId) {
                    $data['approved_by'] = $userId;
                }
                break;
            case self::STATUS_DISPATCHED:
                $data['dispatched_at'] = date('Y-m-d H:i:s');
                break;
            case self::STATUS_RECEIVED:
                $data['received_at'] = date('Y-m-d H:i:s');
                if ($userId) {
                    $data['received_by'] = $userId;
                }
                break;
        }
        
        $result = $this->update($id, $data);
        return $result !== null;
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
        
        $results = DatabaseConfig::getInstance()->getResults($sql, [$companyId], 'i');
        
        $counts = [
            self::STATUS_REQUESTED => 0,
            self::STATUS_APPROVED => 0,
            self::STATUS_DISPATCHED => 0,
            self::STATUS_RECEIVED => 0
        ];
        
        foreach ($results as $row) {
            $counts[$row['status']] = (int)$row['count'];
        }
        
        return $counts;
    }
    
    /**
     * Validate Material Request data
     * 
     * @param array $data Data to validate
     * @return array Validation result with 'isValid' and 'errors'
     */
    public function validate(array $data): array {
        $errors = [];
        
        // Validate site_id
        if (!isset($data['site_id']) || !is_numeric($data['site_id'])) {
            $errors[] = [
                'field' => 'site_id',
                'message' => 'Site ID is required',
                'code' => 'REQUIRED_FIELD_MISSING'
            ];
        }
        
        // Validate material_master_id
        if (!isset($data['material_master_id']) || !is_numeric($data['material_master_id'])) {
            $errors[] = [
                'field' => 'material_master_id',
                'message' => 'Material Master ID is required',
                'code' => 'REQUIRED_FIELD_MISSING'
            ];
        }
        
        // Validate company_id
        if (!isset($data['company_id']) || !is_numeric($data['company_id'])) {
            $errors[] = [
                'field' => 'company_id',
                'message' => 'Company ID is required',
                'code' => 'REQUIRED_FIELD_MISSING'
            ];
        }
        
        // Validate status if provided
        if (isset($data['status']) && !self::isValidStatus($data['status'])) {
            $errors[] = [
                'field' => 'status',
                'message' => 'Invalid status value',
                'code' => 'INVALID_STATUS'
            ];
        }
        
        return [
            'isValid' => empty($errors),
            'errors' => $errors
        ];
    }
}
