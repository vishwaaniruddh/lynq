<?php
/**
 * MaterialMasterRepository
 * Provides data access for Material Masters with company isolation
 * 
 * Requirements: 1.1, 1.4, 1.5, 1.6, 9.1, 9.2, 9.3, 9.4
 * - CRUD operations for Material Masters
 * - Company isolation for multi-tenant support
 * - Soft delete functionality
 * - Item management for associated products
 */

require_once __DIR__ . '/BaseRepository.php';

class MaterialMasterRepository extends BaseRepository {
    protected $table = 'material_masters';
    protected $primaryKey = 'id';
    protected $companyIdColumn = 'company_id';
    protected $applyCompanyFilter = true;
    
    // Status constants
    const STATUS_ACTIVE = 'active';
    const STATUS_INACTIVE = 'inactive';
    
    /**
     * Find all Material Masters with pagination and filters
     * Requirement 9.1
     * 
     * @param array $filters Filters: search, status, page, limit, orderBy, orderDir
     * @param int|null $companyId Company ID for isolation
     * @return array Paginated result with data, total, page, limit, totalPages
     */
    public function findAllPaginated(array $filters = [], ?int $companyId = null): array {
        $page = isset($filters['page']) ? max(1, (int)$filters['page']) : 1;
        $limit = isset($filters['limit']) ? max(1, min(100, (int)$filters['limit'])) : 10;
        $offset = ($page - 1) * $limit;
        $orderBy = $filters['orderBy'] ?? 'name';
        $orderDir = strtoupper($filters['orderDir'] ?? 'ASC') === 'DESC' ? 'DESC' : 'ASC';
        
        $whereClause = ["mm.`deleted_at` IS NULL"];
        $params = [];
        $types = '';
        
        // Company filter
        if ($companyId !== null) {
            $whereClause[] = "mm.`company_id` = ?";
            $params[] = $companyId;
            $types .= 'i';
        }
        
        // Status filter
        if (isset($filters['status']) && $filters['status'] !== '') {
            $whereClause[] = "mm.`status` = ?";
            $params[] = $filters['status'];
            $types .= 's';
        }
        
        // Search filter (searches in name, description)
        if (!empty($filters['search'])) {
            $whereClause[] = "(mm.`name` LIKE ? OR mm.`description` LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $types .= 'ss';
        }
        
        $whereSQL = ' WHERE ' . implode(' AND ', $whereClause);
        
        // Whitelist allowed order columns
        $allowedOrderColumns = ['id', 'name', 'status', 'created_at', 'updated_at'];
        if (!in_array($orderBy, $allowedOrderColumns)) {
            $orderBy = 'name';
        }
        
        // Get total count
        $countSQL = "SELECT COUNT(*) as total FROM `{$this->table}` mm" . $whereSQL;
        $countResult = $this->db->getResults($countSQL, $params, $types);
        $total = (int)($countResult[0]['total'] ?? 0);
        
        // Get paginated data with product counts
        $dataSQL = "SELECT mm.*, 
                           (SELECT COUNT(*) FROM material_master_items WHERE material_master_id = mm.id) as product_count,
                           CONCAT(u.first_name, ' ', u.last_name) as created_by_name
                    FROM `{$this->table}` mm
                    LEFT JOIN users u ON mm.created_by = u.id" .
                   $whereSQL .
                   " ORDER BY mm.`$orderBy` $orderDir LIMIT ? OFFSET ?";
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
     * Find Material Master by ID with items
     * Requirement 1.1
     * 
     * @param int $id Material Master ID
     * @param int|null $companyId Optional company ID for validation
     * @return array|null Material Master with items or null
     */
    public function findByIdWithItems(int $id, ?int $companyId = null): ?array {
        $sql = "SELECT mm.*, CONCAT(u.first_name, ' ', u.last_name) as created_by_name
                FROM `{$this->table}` mm
                LEFT JOIN users u ON mm.created_by = u.id
                WHERE mm.`id` = ? AND mm.`deleted_at` IS NULL";
        $params = [$id];
        $types = 'i';
        
        if ($companyId !== null) {
            $sql .= " AND mm.`company_id` = ?";
            $params[] = $companyId;
            $types .= 'i';
        }
        
        $result = $this->db->getResults($sql, $params, $types);
        
        if (empty($result)) {
            return null;
        }
        
        $master = $result[0];
        $master['items'] = $this->getItems($id);
        
        return $master;
    }
    
    /**
     * Get items for a Material Master with product details
     * 
     * @param int $masterId Material Master ID
     * @return array Items with product details
     */
    public function getItems(int $masterId): array {
        $sql = "SELECT mmi.*, 
                       p.name as product_name, 
                       p.unit_of_measure,
                       p.is_serializable,
                       p.is_repairable,
                       p.inventory_type,
                       pc.name as category_name
                FROM material_master_items mmi
                LEFT JOIN products p ON mmi.product_id = p.id
                LEFT JOIN product_categories pc ON p.category_id = pc.id
                WHERE mmi.material_master_id = ?
                ORDER BY p.name";
        
        return $this->db->getResults($sql, [$masterId], 'i');
    }
    
    /**
     * Create a new Material Master
     * Requirement 9.2
     * 
     * @param array $data Material Master data
     * @return int The ID of the newly created Material Master
     * @throws Exception If creation fails
     */
    public function createMaster(array $data): int {
        // Validate required fields
        if (empty($data['name'])) {
            throw new Exception("Name is required");
        }
        if (empty($data['company_id'])) {
            throw new Exception("Company ID is required");
        }
        
        // Check for duplicate name within company
        if ($this->nameExists($data['name'], $data['company_id'])) {
            throw new Exception("A Material Master with this name already exists");
        }
        
        $now = date('Y-m-d H:i:s');
        
        $sql = "INSERT INTO `{$this->table}` 
                (`name`, `description`, `status`, `company_id`, `created_by`, `created_at`, `updated_at`) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        $params = [
            $data['name'],
            $data['description'] ?? null,
            $data['status'] ?? self::STATUS_ACTIVE,
            $data['company_id'],
            $data['created_by'] ?? null,
            $now,
            $now
        ];
        $types = 'sssiiss';
        
        $stmt = $this->db->executeQuery($sql, $params, $types);
        $insertId = $this->db->getConnection()->insert_id;
        $stmt->close();
        
        if ($insertId <= 0) {
            throw new Exception("Failed to create Material Master");
        }
        
        return $insertId;
    }
    
    /**
     * Update an existing Material Master
     * Requirement 9.3
     * 
     * @param int $id Material Master ID
     * @param array $data Data to update
     * @return bool True if update was successful
     * @throws Exception If update fails
     */
    public function updateMaster(int $id, array $data): bool {
        // Verify record exists
        $existing = $this->findByIdWithItems($id);
        if (!$existing) {
            throw new Exception("Material Master not found");
        }
        
        // Check for duplicate name if name is being changed
        if (isset($data['name']) && $data['name'] !== $existing['name']) {
            $companyId = $data['company_id'] ?? $existing['company_id'];
            if ($this->nameExists($data['name'], $companyId, $id)) {
                throw new Exception("A Material Master with this name already exists");
            }
        }
        
        $setClauses = [];
        $params = [];
        $types = '';
        
        // Allowed fields for update
        $allowedFields = [
            'name' => 's',
            'description' => 's',
            'status' => 's'
        ];
        
        foreach ($allowedFields as $field => $type) {
            if (array_key_exists($field, $data)) {
                $setClauses[] = "`$field` = ?";
                $params[] = $data[$field];
                $types .= $type;
            }
        }
        
        if (empty($setClauses)) {
            return true; // Nothing to update
        }
        
        // Always update updated_at
        $setClauses[] = "`updated_at` = ?";
        $params[] = date('Y-m-d H:i:s');
        $types .= 's';
        
        // Add ID to params
        $params[] = $id;
        $types .= 'i';
        
        $sql = "UPDATE `{$this->table}` SET " . implode(', ', $setClauses) . " WHERE `id` = ?";
        
        $stmt = $this->db->executeQuery($sql, $params, $types);
        $stmt->close();
        
        return true;
    }
    
    /**
     * Soft delete a Material Master
     * Requirement 9.4
     * 
     * @param int $id Material Master ID
     * @return bool True if soft delete was successful
     * @throws Exception If deletion fails
     */
    public function softDelete(int $id): bool {
        // Verify record exists
        $existing = $this->findByIdWithItems($id);
        if (!$existing) {
            throw new Exception("Material Master not found");
        }
        
        $sql = "UPDATE `{$this->table}` SET `deleted_at` = ?, `updated_at` = ? WHERE `id` = ?";
        $now = date('Y-m-d H:i:s');
        
        $stmt = $this->db->executeQuery($sql, [$now, $now, $id], 'ssi');
        $affectedRows = $stmt->affected_rows;
        $stmt->close();
        
        return $affectedRows > 0;
    }
    
    /**
     * Find active Material Masters for selection
     * Requirement 1.6
     * 
     * @param int $companyId Company ID
     * @return array Active Material Masters
     */
    public function findActive(int $companyId): array {
        $sql = "SELECT mm.id, mm.name, mm.description,
                       (SELECT COUNT(*) FROM material_master_items WHERE material_master_id = mm.id) as product_count
                FROM `{$this->table}` mm
                WHERE mm.company_id = ? 
                  AND mm.status = ? 
                  AND mm.deleted_at IS NULL
                ORDER BY mm.name";
        
        return $this->db->getResults($sql, [$companyId, self::STATUS_ACTIVE], 'is');
    }
    
    /**
     * Create items for a Material Master
     * 
     * @param int $masterId Material Master ID
     * @param array $items Array of items with product_id and quantity
     * @return bool Success
     */
    public function createItems(int $masterId, array $items): bool {
        if (empty($items)) {
            return true;
        }
        
        $sql = "INSERT INTO material_master_items (`material_master_id`, `product_id`, `quantity`, `created_at`) VALUES ";
        $placeholders = [];
        $params = [];
        $types = '';
        $now = date('Y-m-d H:i:s');
        
        foreach ($items as $item) {
            $placeholders[] = "(?, ?, ?, ?)";
            $params[] = $masterId;
            $params[] = $item['product_id'];
            $params[] = $item['quantity'];
            $params[] = $now;
            $types .= 'iiis';
        }
        
        $sql .= implode(', ', $placeholders);
        
        $stmt = $this->db->executeQuery($sql, $params, $types);
        $stmt->close();
        
        return true;
    }
    
    /**
     * Delete all items for a Material Master
     * 
     * @param int $masterId Material Master ID
     * @return bool Success
     */
    public function deleteItems(int $masterId): bool {
        $sql = "DELETE FROM material_master_items WHERE `material_master_id` = ?";
        $stmt = $this->db->executeQuery($sql, [$masterId], 'i');
        $stmt->close();
        return true;
    }
    
    /**
     * Check if a Material Master name exists within a company
     * 
     * @param string $name Material Master name
     * @param int $companyId Company ID
     * @param int|null $excludeId ID to exclude (for updates)
     * @return bool True if name exists
     */
    public function nameExists(string $name, int $companyId, ?int $excludeId = null): bool {
        $sql = "SELECT COUNT(*) as count FROM `{$this->table}` 
                WHERE `name` = ? AND `company_id` = ? AND `deleted_at` IS NULL";
        $params = [$name, $companyId];
        $types = 'si';
        
        if ($excludeId !== null) {
            $sql .= " AND `id` != ?";
            $params[] = $excludeId;
            $types .= 'i';
        }
        
        $result = $this->db->getResults($sql, $params, $types);
        return (int)($result[0]['count'] ?? 0) > 0;
    }
    
    /**
     * Get product count for a Material Master
     * 
     * @param int $masterId Material Master ID
     * @return int Product count
     */
    public function getProductCount(int $masterId): int {
        $sql = "SELECT COUNT(*) as count FROM material_master_items WHERE material_master_id = ?";
        $result = $this->db->getResults($sql, [$masterId], 'i');
        return (int)($result[0]['count'] ?? 0);
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
                WHERE company_id = ? AND deleted_at IS NULL
                GROUP BY status";
        
        $results = $this->db->getResults($sql, [$companyId], 'i');
        
        $counts = [
            self::STATUS_ACTIVE => 0,
            self::STATUS_INACTIVE => 0,
            'total' => 0
        ];
        
        foreach ($results as $row) {
            $counts[$row['status']] = (int)$row['count'];
            $counts['total'] += (int)$row['count'];
        }
        
        return $counts;
    }
}
