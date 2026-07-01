<?php
/**
 * Courier Repository
 * Provides data access operations for courier master records
 * 
 * Requirements: 2.1, 2.2, 2.3, 2.4
 * - 2.1: Display paginated list of couriers with search and filter
 * - 2.2: Create new courier records with uniqueness validation
 * - 2.3: Update existing courier records
 * - 2.4: Soft delete courier records
 */

require_once __DIR__ . '/BaseRepository.php';

class CourierRepository extends BaseRepository {
    protected $table = 'couriers';
    protected $primaryKey = 'id';
    
    // Couriers are global master data, not company-specific
    protected $applyCompanyFilter = false;
    protected $companyIdColumn = null;
    
    /**
     * Find all couriers with optional filters
     * Supports pagination, search, and status filtering
     * 
     * @param array $filters Optional filters: search, status, page, limit, orderBy, orderDir
     * @return array Array with 'data', 'total', 'page', 'limit', 'totalPages'
     * 
     * Requirements: 2.1
     */
    public function findAllWithFilters(array $filters = []): array {
        $page = isset($filters['page']) ? max(1, (int)$filters['page']) : 1;
        $limit = isset($filters['limit']) ? max(1, min(100, (int)$filters['limit'])) : 10;
        $offset = ($page - 1) * $limit;
        $orderBy = $filters['orderBy'] ?? 'name';
        $orderDir = strtoupper($filters['orderDir'] ?? 'ASC') === 'DESC' ? 'DESC' : 'ASC';
        
        $whereClause = [];
        $params = [];
        $types = '';
        
        // Status filter
        if (isset($filters['status']) && $filters['status'] !== '') {
            $whereClause[] = "`status` = ?";
            $params[] = (int)$filters['status'];
            $types .= 'i';
        }
        
        // Search filter (searches in name)
        if (!empty($filters['search'])) {
            $whereClause[] = "`name` LIKE ?";
            $params[] = '%' . $filters['search'] . '%';
            $types .= 's';
        }
        
        // Build WHERE clause
        $whereSQL = '';
        if (!empty($whereClause)) {
            $whereSQL = ' WHERE ' . implode(' AND ', $whereClause);
        }
        
        // Whitelist allowed order columns
        $allowedOrderColumns = ['id', 'name', 'status', 'created_at', 'updated_at'];
        if (!in_array($orderBy, $allowedOrderColumns)) {
            $orderBy = 'name';
        }
        
        // Get total count
        $countSQL = "SELECT COUNT(*) as total FROM `{$this->table}`" . $whereSQL;
        $countResult = $this->db->getResults($countSQL, $params, $types);
        $total = (int)($countResult[0]['total'] ?? 0);
        
        // Get paginated data
        $dataSQL = "SELECT * FROM `{$this->table}`" . $whereSQL . 
                   " ORDER BY `$orderBy` $orderDir LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        $types .= 'ii';
        
        $data = $this->db->getResults($dataSQL, $params, $types);
        
        return [
            'data' => $data,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'totalPages' => ceil($total / $limit)
        ];
    }
    
    /**
     * Find courier by ID
     * 
     * @param int $id Courier ID
     * @return array|null Courier record or null if not found
     * 
     * Requirements: 2.1
     */
    public function findById(int $id): ?array {
        $sql = "SELECT * FROM `{$this->table}` WHERE `id` = ?";
        $result = $this->db->getResults($sql, [$id], 'i');
        return !empty($result) ? $result[0] : null;
    }
    
    /**
     * Find courier by name (for uniqueness checking)
     * 
     * @param string $name Courier name
     * @param int|null $excludeId Optional ID to exclude (for updates)
     * @return array|null Courier record or null if not found
     * 
     * Requirements: 2.2 (uniqueness validation)
     */
    public function findByName(string $name, ?int $excludeId = null): ?array {
        $sql = "SELECT * FROM `{$this->table}` WHERE `name` = ?";
        $params = [$name];
        $types = 's';
        
        if ($excludeId !== null) {
            $sql .= " AND `id` != ?";
            $params[] = $excludeId;
            $types .= 'i';
        }
        
        $result = $this->db->getResults($sql, $params, $types);
        return !empty($result) ? $result[0] : null;
    }
    
    /**
     * Create a new courier record
     * 
     * @param array $data Courier data: name, status (optional), created_by (optional)
     * @return int The ID of the newly created courier
     * @throws Exception If creation fails
     * 
     * Requirements: 2.2
     */
    public function createCourier(array $data): int {
        $fields = [];
        $placeholders = [];
        $values = [];
        $types = '';
        
        // Required field: name
        if (!isset($data['name']) || trim($data['name']) === '') {
            throw new Exception("Courier name is required");
        }
        
        $fields[] = 'name';
        $placeholders[] = '?';
        $values[] = trim($data['name']);
        $types .= 's';
        
        // Optional field: status (default 1 = active)
        $fields[] = 'status';
        $placeholders[] = '?';
        $values[] = isset($data['status']) ? (int)$data['status'] : 1;
        $types .= 'i';
        
        // Optional field: created_by
        if (isset($data['created_by'])) {
            $fields[] = 'created_by';
            $placeholders[] = '?';
            $values[] = (int)$data['created_by'];
            $types .= 'i';
        }
        
        $sql = "INSERT INTO `{$this->table}` (`" . implode('`, `', $fields) . "`) VALUES (" . implode(', ', $placeholders) . ")";
        
        $stmt = $this->db->executeQuery($sql, $values, $types);
        $insertId = $this->db->getConnection()->insert_id;
        $stmt->close();
        
        if ($insertId <= 0) {
            throw new Exception("Failed to create courier record");
        }
        
        return $insertId;
    }
    
    /**
     * Update an existing courier record
     * 
     * @param int $id Courier ID
     * @param array $data Data to update: name, status, updated_by
     * @return bool True if update was successful
     * @throws Exception If update fails
     * 
     * Requirements: 2.3
     */
    public function updateCourier(int $id, array $data): bool {
        // Verify record exists
        $existing = $this->findById($id);
        if (!$existing) {
            throw new Exception("Courier record not found");
        }
        
        $setClauses = [];
        $values = [];
        $types = '';
        
        // Update name if provided
        if (isset($data['name'])) {
            if (trim($data['name']) === '') {
                throw new Exception("Courier name cannot be empty");
            }
            $setClauses[] = '`name` = ?';
            $values[] = trim($data['name']);
            $types .= 's';
        }
        
        // Update status if provided
        if (isset($data['status'])) {
            $setClauses[] = '`status` = ?';
            $values[] = (int)$data['status'];
            $types .= 'i';
        }
        
        // Update updated_by if provided
        if (isset($data['updated_by'])) {
            $setClauses[] = '`updated_by` = ?';
            $values[] = (int)$data['updated_by'];
            $types .= 'i';
        }
        
        if (empty($setClauses)) {
            return true; // Nothing to update
        }
        
        // Add ID to params
        $values[] = $id;
        $types .= 'i';
        
        $sql = "UPDATE `{$this->table}` SET " . implode(', ', $setClauses) . " WHERE `id` = ?";
        
        $stmt = $this->db->executeQuery($sql, $values, $types);
        $affectedRows = $stmt->affected_rows;
        $stmt->close();
        
        return $affectedRows >= 0; // 0 is valid if no changes were made
    }
    
    /**
     * Soft delete a courier record (set status to inactive)
     * 
     * @param int $id Courier ID
     * @param int|null $deletedBy User ID who performed the deletion
     * @return bool True if soft delete was successful
     * @throws Exception If deletion fails
     * 
     * Requirements: 2.4
     */
    public function softDelete(int $id, ?int $deletedBy = null): bool {
        // Verify record exists
        $existing = $this->findById($id);
        if (!$existing) {
            throw new Exception("Courier record not found");
        }
        
        $data = ['status' => 0];
        if ($deletedBy !== null) {
            $data['updated_by'] = $deletedBy;
        }
        
        return $this->updateCourier($id, $data);
    }
    
    /**
     * Get all active couriers (for dropdowns)
     * 
     * @return array Array of active courier records
     */
    public function findAllActive(): array {
        $sql = "SELECT * FROM `{$this->table}` WHERE `status` = 1 ORDER BY `name` ASC";
        return $this->db->getResults($sql, [], '');
    }
    
    /**
     * Check if a courier name already exists
     * 
     * @param string $name Courier name to check
     * @param int|null $excludeId Optional ID to exclude (for updates)
     * @return bool True if name exists, false otherwise
     */
    public function nameExists(string $name, ?int $excludeId = null): bool {
        return $this->findByName($name, $excludeId) !== null;
    }
    
    /**
     * Get couriers for export (all matching filters, no pagination)
     * 
     * @param array $filters Optional filters: search, status
     * @return array Array of courier records
     * 
     * Requirements: 2.6
     */
    public function findAllForExport(array $filters = []): array {
        $whereClause = [];
        $params = [];
        $types = '';
        
        // Status filter
        if (isset($filters['status']) && $filters['status'] !== '') {
            $whereClause[] = "`status` = ?";
            $params[] = (int)$filters['status'];
            $types .= 'i';
        }
        
        // Search filter
        if (!empty($filters['search'])) {
            $whereClause[] = "`name` LIKE ?";
            $params[] = '%' . $filters['search'] . '%';
            $types .= 's';
        }
        
        $whereSQL = '';
        if (!empty($whereClause)) {
            $whereSQL = ' WHERE ' . implode(' AND ', $whereClause);
        }
        
        $sql = "SELECT * FROM `{$this->table}`" . $whereSQL . " ORDER BY `name` ASC";
        
        return $this->db->getResults($sql, $params, $types);
    }
}
