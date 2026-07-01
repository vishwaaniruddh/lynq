<?php
/**
 * Customer Repository
 * Provides data access operations for customer master records
 * 
 * Requirements: 2.1, 2.2, 2.3, 2.4
 * - 2.1: Display paginated list of customers with search and filter
 * - 2.2: Create new customer records with email uniqueness validation
 * - 2.3: Update existing customer records with audit trail
 * - 2.4: Soft delete customer records
 */

require_once __DIR__ . '/BaseRepository.php';

class CustomerRepository extends BaseRepository {
    protected $table = 'customers';
    protected $primaryKey = 'id';
    
    // Customers are global master data, not company-specific
    protected $applyCompanyFilter = false;
    protected $companyIdColumn = null;
    
    /**
     * Find all customers with optional filters
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
        
        // Search filter (searches in name, email, phone)
        if (!empty($filters['search'])) {
            $searchTerm = '%' . $filters['search'] . '%';
            $whereClause[] = "(`name` LIKE ? OR `email` LIKE ? OR `phone` LIKE ?)";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $types .= 'sss';
        }

        
        // Build WHERE clause
        $whereSQL = '';
        if (!empty($whereClause)) {
            $whereSQL = ' WHERE ' . implode(' AND ', $whereClause);
        }
        
        // Whitelist allowed order columns
        $allowedOrderColumns = ['id', 'name', 'email', 'phone', 'city', 'state', 'country', 'status', 'created_at', 'updated_at'];
        if (!in_array($orderBy, $allowedOrderColumns)) {
            $orderBy = 'name';
        }
        
        // Get total count
        $countSQL = "SELECT COUNT(*) as total FROM `{$this->table}`" . $whereSQL;
        $countResult = $this->db->getResults($countSQL, $params, $types);
        $total = (int)($countResult[0]['total'] ?? 0);
        
        // Get paginated data with location names
        $dataSQL = "SELECT c.*, 
                    co.name as country_name, 
                    s.name as state_name, 
                    ci.name as city_name
                    FROM `{$this->table}` c
                    LEFT JOIN countries co ON c.country_id = co.id
                    LEFT JOIN states s ON c.state_id = s.id
                    LEFT JOIN cities ci ON c.city_id = ci.id" . 
                    str_replace("`", "c.`", $whereSQL) . 
                   " ORDER BY c.`$orderBy` $orderDir LIMIT ? OFFSET ?";
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
     * Find customer by ID
     * 
     * @param int $id Customer ID
     * @return array|null Customer record or null if not found
     * 
     * Requirements: 2.1, 2.5
     */
    public function findById(int $id): ?array {
        $sql = "SELECT c.*, 
                co.name as country_name, 
                s.name as state_name, 
                ci.name as city_name
                FROM `{$this->table}` c
                LEFT JOIN countries co ON c.country_id = co.id
                LEFT JOIN states s ON c.state_id = s.id
                LEFT JOIN cities ci ON c.city_id = ci.id
                WHERE c.`id` = ?";
        $result = $this->db->getResults($sql, [$id], 'i');
        return !empty($result) ? $result[0] : null;
    }
    
    /**
     * Find customer by email (for uniqueness checking)
     * 
     * @param string $email Customer email
     * @param int|null $excludeId Optional ID to exclude (for updates)
     * @return array|null Customer record or null if not found
     * 
     * Requirements: 2.2, 2.3 (uniqueness validation)
     */
    public function findByEmail(string $email, ?int $excludeId = null): ?array {
        $sql = "SELECT * FROM `{$this->table}` WHERE `email` = ?";
        $params = [$email];
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
     * Create a new customer record
     * 
     * @param array $data Customer data: name, email, phone, address, city, state, country, postal_code, status, created_by
     * @return int The ID of the newly created customer
     * @throws Exception If creation fails
     * 
     * Requirements: 2.2
     */
    public function createCustomer(array $data): int {
        $fields = [];
        $placeholders = [];
        $values = [];
        $types = '';
        
        // Required field: name
        if (!isset($data['name']) || trim($data['name']) === '') {
            throw new Exception("Customer name is required");
        }
        $fields[] = 'name';
        $placeholders[] = '?';
        $values[] = trim($data['name']);
        $types .= 's';
        
        // Required field: email
        if (!isset($data['email']) || trim($data['email']) === '') {
            throw new Exception("Customer email is required");
        }
        $fields[] = 'email';
        $placeholders[] = '?';
        $values[] = trim($data['email']);
        $types .= 's';
        
        // Optional field: phone
        if (isset($data['phone']) && trim($data['phone']) !== '') {
            $fields[] = 'phone';
            $placeholders[] = '?';
            $values[] = trim($data['phone']);
            $types .= 's';
        }
        
        // Optional field: address
        if (isset($data['address']) && trim($data['address']) !== '') {
            $fields[] = 'address';
            $placeholders[] = '?';
            $values[] = trim($data['address']);
            $types .= 's';
        }
        
        // Optional field: city
        if (isset($data['city']) && trim($data['city']) !== '') {
            $fields[] = 'city';
            $placeholders[] = '?';
            $values[] = trim($data['city']);
            $types .= 's';
        }
        
        // Optional field: state
        if (isset($data['state']) && trim($data['state']) !== '') {
            $fields[] = 'state';
            $placeholders[] = '?';
            $values[] = trim($data['state']);
            $types .= 's';
        }
        
        // Optional field: country (default: India)
        $fields[] = 'country';
        $placeholders[] = '?';
        $values[] = isset($data['country']) && trim($data['country']) !== '' ? trim($data['country']) : 'India';
        $types .= 's';
        
        // Optional field: postal_code
        if (isset($data['postal_code']) && trim($data['postal_code']) !== '') {
            $fields[] = 'postal_code';
            $placeholders[] = '?';
            $values[] = trim($data['postal_code']);
            $types .= 's';
        }
        
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
            throw new Exception("Failed to create customer record");
        }
        
        return $insertId;
    }

    
    /**
     * Update an existing customer record
     * 
     * @param int $id Customer ID
     * @param array $data Data to update
     * @return bool True if update was successful
     * @throws Exception If update fails
     * 
     * Requirements: 2.3
     */
    public function updateCustomer(int $id, array $data): bool {
        // Verify record exists
        $existing = $this->findById($id);
        if (!$existing) {
            throw new Exception("Customer record not found");
        }
        
        $setClauses = [];
        $values = [];
        $types = '';
        
        // Update name if provided
        if (isset($data['name'])) {
            if (trim($data['name']) === '') {
                throw new Exception("Customer name cannot be empty");
            }
            $setClauses[] = '`name` = ?';
            $values[] = trim($data['name']);
            $types .= 's';
        }
        
        // Update email if provided
        if (isset($data['email'])) {
            if (trim($data['email']) === '') {
                throw new Exception("Customer email cannot be empty");
            }
            $setClauses[] = '`email` = ?';
            $values[] = trim($data['email']);
            $types .= 's';
        }
        
        // Update phone if provided
        if (array_key_exists('phone', $data)) {
            $setClauses[] = '`phone` = ?';
            $values[] = $data['phone'] !== null ? trim($data['phone']) : null;
            $types .= 's';
        }
        
        // Update address if provided
        if (array_key_exists('address', $data)) {
            $setClauses[] = '`address` = ?';
            $values[] = $data['address'] !== null ? trim($data['address']) : null;
            $types .= 's';
        }
        
        // Update city if provided
        if (array_key_exists('city', $data)) {
            $setClauses[] = '`city` = ?';
            $values[] = $data['city'] !== null ? trim($data['city']) : null;
            $types .= 's';
        }
        
        // Update state if provided
        if (array_key_exists('state', $data)) {
            $setClauses[] = '`state` = ?';
            $values[] = $data['state'] !== null ? trim($data['state']) : null;
            $types .= 's';
        }
        
        // Update country if provided
        if (array_key_exists('country', $data)) {
            $setClauses[] = '`country` = ?';
            $values[] = $data['country'] !== null ? trim($data['country']) : null;
            $types .= 's';
        }
        
        // Update postal_code if provided
        if (array_key_exists('postal_code', $data)) {
            $setClauses[] = '`postal_code` = ?';
            $values[] = $data['postal_code'] !== null ? trim($data['postal_code']) : null;
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
     * Soft delete a customer record (set status to inactive)
     * 
     * @param int $id Customer ID
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
            throw new Exception("Customer record not found");
        }
        
        $data = ['status' => 0];
        if ($deletedBy !== null) {
            $data['updated_by'] = $deletedBy;
        }
        
        return $this->updateCustomer($id, $data);
    }
    
    /**
     * Get all active customers (for dropdowns)
     * 
     * @return array Array of active customer records
     */
    public function findAllActive(): array {
        $sql = "SELECT * FROM `{$this->table}` WHERE `status` = 1 ORDER BY `name` ASC";
        return $this->db->getResults($sql, [], '');
    }
    
    /**
     * Check if a customer email already exists
     * 
     * @param string $email Customer email to check
     * @param int|null $excludeId Optional ID to exclude (for updates)
     * @return bool True if email exists, false otherwise
     */
    public function emailExists(string $email, ?int $excludeId = null): bool {
        return $this->findByEmail($email, $excludeId) !== null;
    }
    
    /**
     * Get customers for export (all matching filters, no pagination)
     * 
     * @param array $filters Optional filters: search, status
     * @return array Array of customer records
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
            $searchTerm = '%' . $filters['search'] . '%';
            $whereClause[] = "(`name` LIKE ? OR `email` LIKE ? OR `phone` LIKE ?)";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $types .= 'sss';
        }
        
        $whereSQL = '';
        if (!empty($whereClause)) {
            $whereSQL = ' WHERE ' . implode(' AND ', $whereClause);
        }
        
        $sql = "SELECT * FROM `{$this->table}`" . $whereSQL . " ORDER BY `name` ASC";
        
        return $this->db->getResults($sql, $params, $types);
    }
}
