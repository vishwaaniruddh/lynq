<?php
/**
 * Location Repository
 * Provides data access operations for location master records (Countries, States, Zones, Cities)
 * 
 * Requirements: 3.1, 3.2, 4.1, 4.2, 5.1, 5.2, 6.1, 6.2
 * - 3.x: Country CRUD operations
 * - 4.x: State CRUD operations
 * - 5.x: Zone CRUD operations
 * - 6.x: City CRUD operations
 */

require_once __DIR__ . '/BaseRepository.php';

class LocationRepository extends BaseRepository {
    // Location data is global master data, not company-specific
    protected $applyCompanyFilter = false;
    protected $companyIdColumn = null;
    
    // ==================== COUNTRY OPERATIONS ====================
    
    /**
     * Find all countries with optional filters
     * 
     * @param array $filters Optional filters: search, status, page, limit, orderBy, orderDir
     * @return array Array with 'data', 'total', 'page', 'limit', 'totalPages'
     * 
     * Requirements: 3.1
     */
    public function findAllCountries(array $filters = []): array {
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
            $whereClause[] = "c.`status` = ?";
            $params[] = $filters['status'];
            $types .= 's';
        }
        
        // Search filter
        if (!empty($filters['search'])) {
            $whereClause[] = "c.`name` LIKE ?";
            $params[] = '%' . $filters['search'] . '%';
            $types .= 's';
        }

        
        $whereSQL = '';
        if (!empty($whereClause)) {
            $whereSQL = ' WHERE ' . implode(' AND ', $whereClause);
        }
        
        // Whitelist allowed order columns
        $allowedOrderColumns = ['id', 'name', 'status', 'created_at'];
        if (!in_array($orderBy, $allowedOrderColumns)) {
            $orderBy = 'name';
        }
        
        // Get total count
        $countSQL = "SELECT COUNT(*) as total FROM `countries` c" . $whereSQL;
        $countResult = $this->db->getResults($countSQL, $params, $types);
        $total = (int)($countResult[0]['total'] ?? 0);
        
        // Get paginated data with state and city counts
        $dataSQL = "SELECT c.*, 
                    (SELECT COUNT(*) FROM `states` WHERE `country_id` = c.`id`) as state_count,
                    (SELECT COUNT(*) FROM `cities` ci 
                     INNER JOIN `states` s ON ci.`state_id` = s.`id` 
                     WHERE s.`country_id` = c.`id`) as city_count
                    FROM `countries` c" . $whereSQL . 
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
     * Find country by ID
     * 
     * @param int $id Country ID
     * @return array|null Country record or null if not found
     */
    public function findCountryById(int $id): ?array {
        $sql = "SELECT c.*, 
                (SELECT COUNT(*) FROM `states` WHERE `country_id` = c.`id`) as state_count,
                (SELECT COUNT(*) FROM `cities` ci 
                 INNER JOIN `states` s ON ci.`state_id` = s.`id` 
                 WHERE s.`country_id` = c.`id`) as city_count
                FROM `countries` c WHERE c.`id` = ?";
        $result = $this->db->getResults($sql, [$id], 'i');
        return !empty($result) ? $result[0] : null;
    }
    
    /**
     * Find country by name (for uniqueness checking)
     * 
     * @param string $name Country name
     * @param int|null $excludeId Optional ID to exclude (for updates)
     * @return array|null Country record or null if not found
     */
    public function findCountryByName(string $name, ?int $excludeId = null): ?array {
        $sql = "SELECT * FROM `countries` WHERE `name` = ?";
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
     * Create a new country record
     * 
     * @param array $data Country data: name, status, created_by
     * @return int The ID of the newly created country
     * @throws Exception If creation fails
     * 
     * Requirements: 3.2
     */
    public function createCountry(array $data): int {
        if (!isset($data['name']) || trim($data['name']) === '') {
            throw new Exception("Country name is required");
        }
        
        $fields = ['name'];
        $placeholders = ['?'];
        $values = [trim($data['name'])];
        $types = 's';
        
        // Status (default: active)
        $fields[] = 'status';
        $placeholders[] = '?';
        $values[] = $data['status'] ?? 'active';
        $types .= 's';
        
        if (isset($data['created_by'])) {
            $fields[] = 'created_by';
            $placeholders[] = '?';
            $values[] = (int)$data['created_by'];
            $types .= 'i';
        }
        
        $sql = "INSERT INTO `countries` (`" . implode('`, `', $fields) . "`) VALUES (" . implode(', ', $placeholders) . ")";
        
        $stmt = $this->db->executeQuery($sql, $values, $types);
        $insertId = $this->db->getConnection()->insert_id;
        $stmt->close();
        
        if ($insertId <= 0) {
            throw new Exception("Failed to create country record");
        }
        
        return $insertId;
    }

    
    /**
     * Update an existing country record
     * 
     * @param int $id Country ID
     * @param array $data Data to update: name, status, updated_by
     * @return bool True if update was successful
     * @throws Exception If update fails
     * 
     * Requirements: 3.3
     */
    public function updateCountry(int $id, array $data): bool {
        $existing = $this->findCountryById($id);
        if (!$existing) {
            throw new Exception("Country record not found");
        }
        
        $setClauses = [];
        $values = [];
        $types = '';
        
        if (isset($data['name'])) {
            if (trim($data['name']) === '') {
                throw new Exception("Country name cannot be empty");
            }
            $setClauses[] = '`name` = ?';
            $values[] = trim($data['name']);
            $types .= 's';
        }
        
        if (isset($data['status'])) {
            $setClauses[] = '`status` = ?';
            $values[] = $data['status'];
            $types .= 's';
        }
        
        if (isset($data['updated_by'])) {
            $setClauses[] = '`updated_by` = ?';
            $values[] = (int)$data['updated_by'];
            $types .= 'i';
        }
        
        if (empty($setClauses)) {
            return true;
        }
        
        $values[] = $id;
        $types .= 'i';
        
        $sql = "UPDATE `countries` SET " . implode(', ', $setClauses) . " WHERE `id` = ?";
        
        $stmt = $this->db->executeQuery($sql, $values, $types);
        $stmt->close();
        
        return true;
    }
    
    /**
     * Delete a country record (only if no states exist)
     * 
     * @param int $id Country ID
     * @return bool True if deletion was successful
     * @throws Exception If country has states or deletion fails
     * 
     * Requirements: 3.4
     */
    public function deleteCountry(int $id): bool {
        $existing = $this->findCountryById($id);
        if (!$existing) {
            throw new Exception("Country record not found");
        }
        
        // Check for dependent states
        $stateCount = $this->countStatesByCountry($id);
        if ($stateCount > 0) {
            throw new Exception("Cannot delete country with $stateCount associated state(s)");
        }
        
        $sql = "DELETE FROM `countries` WHERE `id` = ?";
        $stmt = $this->db->executeQuery($sql, [$id], 'i');
        $affectedRows = $stmt->affected_rows;
        $stmt->close();
        
        return $affectedRows > 0;
    }
    
    /**
     * Count states by country
     * 
     * @param int $countryId Country ID
     * @return int Number of states
     */
    public function countStatesByCountry(int $countryId): int {
        $sql = "SELECT COUNT(*) as count FROM `states` WHERE `country_id` = ?";
        $result = $this->db->getResults($sql, [$countryId], 'i');
        return (int)($result[0]['count'] ?? 0);
    }
    
    /**
     * Get all active countries (for dropdowns)
     * 
     * @return array Array of active country records
     */
    public function findAllActiveCountries(): array {
        $sql = "SELECT * FROM `countries` WHERE `status` = 'active' ORDER BY `name` ASC";
        return $this->db->getResults($sql, [], '');
    }

    
    // ==================== ZONE OPERATIONS ====================
    
    /**
     * Find all zones with optional filters
     * 
     * @param array $filters Optional filters: search, status, page, limit, orderBy, orderDir
     * @return array Array with 'data', 'total', 'page', 'limit', 'totalPages'
     * 
     * Requirements: 5.1
     */
    public function findAllZones(array $filters = []): array {
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
            $whereClause[] = "z.`status` = ?";
            $params[] = $filters['status'];
            $types .= 's';
        }
        
        // Search filter
        if (!empty($filters['search'])) {
            $whereClause[] = "z.`name` LIKE ?";
            $params[] = '%' . $filters['search'] . '%';
            $types .= 's';
        }
        
        $whereSQL = '';
        if (!empty($whereClause)) {
            $whereSQL = ' WHERE ' . implode(' AND ', $whereClause);
        }
        
        $allowedOrderColumns = ['id', 'name', 'status', 'created_at'];
        if (!in_array($orderBy, $allowedOrderColumns)) {
            $orderBy = 'name';
        }
        
        // Get total count
        $countSQL = "SELECT COUNT(*) as total FROM `zones` z" . $whereSQL;
        $countResult = $this->db->getResults($countSQL, $params, $types);
        $total = (int)($countResult[0]['total'] ?? 0);
        
        // Get paginated data with state and city counts
        $dataSQL = "SELECT z.*, 
                    (SELECT COUNT(*) FROM `states` WHERE `zone_id` = z.`id`) as state_count,
                    (SELECT COUNT(*) FROM `cities` WHERE `zone_id` = z.`id`) as city_count
                    FROM `zones` z" . $whereSQL . 
                   " ORDER BY z.`$orderBy` $orderDir LIMIT ? OFFSET ?";
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
     * Find zone by ID
     * 
     * @param int $id Zone ID
     * @return array|null Zone record or null if not found
     */
    public function findZoneById(int $id): ?array {
        $sql = "SELECT z.*, 
                (SELECT COUNT(*) FROM `states` WHERE `zone_id` = z.`id`) as state_count,
                (SELECT COUNT(*) FROM `cities` WHERE `zone_id` = z.`id`) as city_count
                FROM `zones` z WHERE z.`id` = ?";
        $result = $this->db->getResults($sql, [$id], 'i');
        return !empty($result) ? $result[0] : null;
    }
    
    /**
     * Find zone by name (for uniqueness checking)
     * 
     * @param string $name Zone name
     * @param int|null $excludeId Optional ID to exclude (for updates)
     * @return array|null Zone record or null if not found
     */
    public function findZoneByName(string $name, ?int $excludeId = null): ?array {
        $sql = "SELECT * FROM `zones` WHERE `name` = ?";
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
     * Create a new zone record
     * 
     * @param array $data Zone data: name, status, created_by
     * @return int The ID of the newly created zone
     * @throws Exception If creation fails
     * 
     * Requirements: 5.2
     */
    public function createZone(array $data): int {
        if (!isset($data['name']) || trim($data['name']) === '') {
            throw new Exception("Zone name is required");
        }
        
        $fields = ['name'];
        $placeholders = ['?'];
        $values = [trim($data['name'])];
        $types = 's';
        
        $fields[] = 'status';
        $placeholders[] = '?';
        $values[] = $data['status'] ?? 'active';
        $types .= 's';
        
        if (isset($data['created_by'])) {
            $fields[] = 'created_by';
            $placeholders[] = '?';
            $values[] = (int)$data['created_by'];
            $types .= 'i';
        }
        
        $sql = "INSERT INTO `zones` (`" . implode('`, `', $fields) . "`) VALUES (" . implode(', ', $placeholders) . ")";
        
        $stmt = $this->db->executeQuery($sql, $values, $types);
        $insertId = $this->db->getConnection()->insert_id;
        $stmt->close();
        
        if ($insertId <= 0) {
            throw new Exception("Failed to create zone record");
        }
        
        return $insertId;
    }
    
    /**
     * Update an existing zone record
     * 
     * @param int $id Zone ID
     * @param array $data Data to update: name, status, updated_by
     * @return bool True if update was successful
     * @throws Exception If update fails
     * 
     * Requirements: 5.3
     */
    public function updateZone(int $id, array $data): bool {
        $existing = $this->findZoneById($id);
        if (!$existing) {
            throw new Exception("Zone record not found");
        }
        
        $setClauses = [];
        $values = [];
        $types = '';
        
        if (isset($data['name'])) {
            if (trim($data['name']) === '') {
                throw new Exception("Zone name cannot be empty");
            }
            $setClauses[] = '`name` = ?';
            $values[] = trim($data['name']);
            $types .= 's';
        }
        
        if (isset($data['status'])) {
            $setClauses[] = '`status` = ?';
            $values[] = $data['status'];
            $types .= 's';
        }
        
        if (isset($data['updated_by'])) {
            $setClauses[] = '`updated_by` = ?';
            $values[] = (int)$data['updated_by'];
            $types .= 'i';
        }
        
        if (empty($setClauses)) {
            return true;
        }
        
        $values[] = $id;
        $types .= 'i';
        
        $sql = "UPDATE `zones` SET " . implode(', ', $setClauses) . " WHERE `id` = ?";
        
        $stmt = $this->db->executeQuery($sql, $values, $types);
        $stmt->close();
        
        return true;
    }
    
    /**
     * Delete a zone record (cascade: set zone_id to NULL in states and cities)
     * 
     * @param int $id Zone ID
     * @return bool True if deletion was successful
     * @throws Exception If deletion fails
     * 
     * Requirements: 5.5
     */
    public function deleteZone(int $id): bool {
        $existing = $this->findZoneById($id);
        if (!$existing) {
            throw new Exception("Zone record not found");
        }
        
        // Cascade: Remove zone associations from states and cities
        // This is handled by ON DELETE SET NULL in the foreign key constraints
        
        $sql = "DELETE FROM `zones` WHERE `id` = ?";
        $stmt = $this->db->executeQuery($sql, [$id], 'i');
        $affectedRows = $stmt->affected_rows;
        $stmt->close();
        
        return $affectedRows > 0;
    }
    
    /**
     * Get all active zones (for dropdowns)
     * 
     * @return array Array of active zone records
     */
    public function findAllActiveZones(): array {
        $sql = "SELECT * FROM `zones` WHERE `status` = 'active' ORDER BY `name` ASC";
        return $this->db->getResults($sql, [], '');
    }
    
    /**
     * Count states by zone
     * 
     * @param int $zoneId Zone ID
     * @return int Number of states
     */
    public function countStatesByZone(int $zoneId): int {
        $sql = "SELECT COUNT(*) as count FROM `states` WHERE `zone_id` = ?";
        $result = $this->db->getResults($sql, [$zoneId], 'i');
        return (int)($result[0]['count'] ?? 0);
    }
    
    /**
     * Count cities by zone
     * 
     * @param int $zoneId Zone ID
     * @return int Number of cities
     */
    public function countCitiesByZone(int $zoneId): int {
        $sql = "SELECT COUNT(*) as count FROM `cities` WHERE `zone_id` = ?";
        $result = $this->db->getResults($sql, [$zoneId], 'i');
        return (int)($result[0]['count'] ?? 0);
    }

    
    // ==================== STATE OPERATIONS ====================
    
    /**
     * Find all states with optional filters
     * 
     * @param array $filters Optional filters: search, status, country_id, zone_id, page, limit, orderBy, orderDir
     * @return array Array with 'data', 'total', 'page', 'limit', 'totalPages'
     * 
     * Requirements: 4.1
     */
    public function findAllStates(array $filters = []): array {
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
            $whereClause[] = "s.`status` = ?";
            $params[] = $filters['status'];
            $types .= 's';
        }
        
        // Country filter
        if (isset($filters['country_id']) && $filters['country_id'] !== '') {
            $whereClause[] = "s.`country_id` = ?";
            $params[] = (int)$filters['country_id'];
            $types .= 'i';
        }
        
        // Zone filter
        if (isset($filters['zone_id']) && $filters['zone_id'] !== '') {
            $whereClause[] = "s.`zone_id` = ?";
            $params[] = (int)$filters['zone_id'];
            $types .= 'i';
        }
        
        // Search filter
        if (!empty($filters['search'])) {
            $whereClause[] = "s.`name` LIKE ?";
            $params[] = '%' . $filters['search'] . '%';
            $types .= 's';
        }
        
        $whereSQL = '';
        if (!empty($whereClause)) {
            $whereSQL = ' WHERE ' . implode(' AND ', $whereClause);
        }
        
        $allowedOrderColumns = ['id', 'name', 'status', 'created_at', 'country_name', 'zone_name'];
        if (!in_array($orderBy, $allowedOrderColumns)) {
            $orderBy = 'name';
        }
        
        // Map order columns to actual columns
        $orderColumn = $orderBy;
        if ($orderBy === 'country_name') {
            $orderColumn = 'c.name';
        } elseif ($orderBy === 'zone_name') {
            $orderColumn = 'z.name';
        } else {
            $orderColumn = "s.`$orderBy`";
        }
        
        // Get total count
        $countSQL = "SELECT COUNT(*) as total FROM `states` s" . $whereSQL;
        $countResult = $this->db->getResults($countSQL, $params, $types);
        $total = (int)($countResult[0]['total'] ?? 0);
        
        // Get paginated data with relationships
        $dataSQL = "SELECT s.*, 
                    c.name as country_name,
                    z.name as zone_name,
                    (SELECT COUNT(*) FROM `cities` WHERE `state_id` = s.`id`) as city_count
                    FROM `states` s
                    LEFT JOIN `countries` c ON s.`country_id` = c.`id`
                    LEFT JOIN `zones` z ON s.`zone_id` = z.`id`" . $whereSQL . 
                   " ORDER BY $orderColumn $orderDir LIMIT ? OFFSET ?";
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
     * Find state by ID
     * 
     * @param int $id State ID
     * @return array|null State record or null if not found
     */
    public function findStateById(int $id): ?array {
        $sql = "SELECT s.*, 
                c.name as country_name,
                z.name as zone_name,
                (SELECT COUNT(*) FROM `cities` WHERE `state_id` = s.`id`) as city_count
                FROM `states` s
                LEFT JOIN `countries` c ON s.`country_id` = c.`id`
                LEFT JOIN `zones` z ON s.`zone_id` = z.`id`
                WHERE s.`id` = ?";
        $result = $this->db->getResults($sql, [$id], 'i');
        return !empty($result) ? $result[0] : null;
    }
    
    /**
     * Find state by name within a country (for uniqueness checking)
     * 
     * @param string $name State name
     * @param int $countryId Country ID
     * @param int|null $excludeId Optional ID to exclude (for updates)
     * @return array|null State record or null if not found
     */
    public function findStateByNameAndCountry(string $name, int $countryId, ?int $excludeId = null): ?array {
        $sql = "SELECT * FROM `states` WHERE `name` = ? AND `country_id` = ?";
        $params = [$name, $countryId];
        $types = 'si';
        
        if ($excludeId !== null) {
            $sql .= " AND `id` != ?";
            $params[] = $excludeId;
            $types .= 'i';
        }
        
        $result = $this->db->getResults($sql, $params, $types);
        return !empty($result) ? $result[0] : null;
    }

    
    /**
     * Create a new state record
     * 
     * @param array $data State data: name, country_id, zone_id, status, created_by
     * @return int The ID of the newly created state
     * @throws Exception If creation fails
     * 
     * Requirements: 4.2
     */
    public function createState(array $data): int {
        if (!isset($data['name']) || trim($data['name']) === '') {
            throw new Exception("State name is required");
        }
        
        if (!isset($data['country_id']) || (int)$data['country_id'] <= 0) {
            throw new Exception("Country is required");
        }
        
        $fields = ['name', 'country_id'];
        $placeholders = ['?', '?'];
        $values = [trim($data['name']), (int)$data['country_id']];
        $types = 'si';
        
        // Optional: zone_id
        if (isset($data['zone_id']) && $data['zone_id'] !== '' && $data['zone_id'] !== null) {
            $fields[] = 'zone_id';
            $placeholders[] = '?';
            $values[] = (int)$data['zone_id'];
            $types .= 'i';
        }
        
        $fields[] = 'status';
        $placeholders[] = '?';
        $values[] = $data['status'] ?? 'active';
        $types .= 's';
        
        if (isset($data['created_by'])) {
            $fields[] = 'created_by';
            $placeholders[] = '?';
            $values[] = (int)$data['created_by'];
            $types .= 'i';
        }
        
        $sql = "INSERT INTO `states` (`" . implode('`, `', $fields) . "`) VALUES (" . implode(', ', $placeholders) . ")";
        
        $stmt = $this->db->executeQuery($sql, $values, $types);
        $insertId = $this->db->getConnection()->insert_id;
        $stmt->close();
        
        if ($insertId <= 0) {
            throw new Exception("Failed to create state record");
        }
        
        return $insertId;
    }
    
    /**
     * Update an existing state record
     * 
     * @param int $id State ID
     * @param array $data Data to update: name, country_id, zone_id, status, updated_by
     * @return bool True if update was successful
     * @throws Exception If update fails
     * 
     * Requirements: 4.3
     */
    public function updateState(int $id, array $data): bool {
        $existing = $this->findStateById($id);
        if (!$existing) {
            throw new Exception("State record not found");
        }
        
        $setClauses = [];
        $values = [];
        $types = '';
        
        if (isset($data['name'])) {
            if (trim($data['name']) === '') {
                throw new Exception("State name cannot be empty");
            }
            $setClauses[] = '`name` = ?';
            $values[] = trim($data['name']);
            $types .= 's';
        }
        
        if (isset($data['country_id'])) {
            $setClauses[] = '`country_id` = ?';
            $values[] = (int)$data['country_id'];
            $types .= 'i';
        }
        
        if (array_key_exists('zone_id', $data)) {
            $setClauses[] = '`zone_id` = ?';
            $values[] = $data['zone_id'] !== null && $data['zone_id'] !== '' ? (int)$data['zone_id'] : null;
            $types .= 'i';
        }
        
        if (isset($data['status'])) {
            $setClauses[] = '`status` = ?';
            $values[] = $data['status'];
            $types .= 's';
        }
        
        if (isset($data['updated_by'])) {
            $setClauses[] = '`updated_by` = ?';
            $values[] = (int)$data['updated_by'];
            $types .= 'i';
        }
        
        if (empty($setClauses)) {
            return true;
        }
        
        $values[] = $id;
        $types .= 'i';
        
        $sql = "UPDATE `states` SET " . implode(', ', $setClauses) . " WHERE `id` = ?";
        
        $stmt = $this->db->executeQuery($sql, $values, $types);
        $stmt->close();
        
        return true;
    }
    
    /**
     * Delete a state record (only if no cities exist)
     * 
     * @param int $id State ID
     * @return bool True if deletion was successful
     * @throws Exception If state has cities or deletion fails
     * 
     * Requirements: 4.4
     */
    public function deleteState(int $id): bool {
        $existing = $this->findStateById($id);
        if (!$existing) {
            throw new Exception("State record not found");
        }
        
        // Check for dependent cities
        $cityCount = $this->countCitiesByState($id);
        if ($cityCount > 0) {
            throw new Exception("Cannot delete state with $cityCount associated city(ies)");
        }
        
        $sql = "DELETE FROM `states` WHERE `id` = ?";
        $stmt = $this->db->executeQuery($sql, [$id], 'i');
        $affectedRows = $stmt->affected_rows;
        $stmt->close();
        
        return $affectedRows > 0;
    }
    
    /**
     * Count cities by state
     * 
     * @param int $stateId State ID
     * @return int Number of cities
     */
    public function countCitiesByState(int $stateId): int {
        $sql = "SELECT COUNT(*) as count FROM `cities` WHERE `state_id` = ?";
        $result = $this->db->getResults($sql, [$stateId], 'i');
        return (int)($result[0]['count'] ?? 0);
    }
    
    /**
     * Get states by country (for cascading dropdowns)
     * 
     * @param int $countryId Country ID
     * @return array Array of state records
     */
    public function getStatesByCountry(int $countryId): array {
        $sql = "SELECT s.*, z.name as zone_name 
                FROM `states` s 
                LEFT JOIN `zones` z ON s.`zone_id` = z.`id`
                WHERE s.`country_id` = ? AND s.`status` = 'active' 
                ORDER BY s.`name` ASC";
        return $this->db->getResults($sql, [$countryId], 'i');
    }
    
    /**
     * Get all active states (for dropdowns)
     * 
     * @return array Array of active state records
     */
    public function findAllActiveStates(): array {
        $sql = "SELECT s.*, c.name as country_name, z.name as zone_name 
                FROM `states` s 
                LEFT JOIN `countries` c ON s.`country_id` = c.`id`
                LEFT JOIN `zones` z ON s.`zone_id` = z.`id`
                WHERE s.`status` = 'active' 
                ORDER BY c.`name`, s.`name` ASC";
        return $this->db->getResults($sql, [], '');
    }

    
    // ==================== CITY OPERATIONS ====================
    
    /**
     * Find all cities with optional filters
     * 
     * @param array $filters Optional filters: search, status, country_id, state_id, zone_id, page, limit, orderBy, orderDir
     * @return array Array with 'data', 'total', 'page', 'limit', 'totalPages'
     * 
     * Requirements: 6.1
     */
    public function findAllCities(array $filters = []): array {
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
            $whereClause[] = "ci.`status` = ?";
            $params[] = $filters['status'];
            $types .= 's';
        }
        
        // Country filter (via state)
        if (isset($filters['country_id']) && $filters['country_id'] !== '') {
            $whereClause[] = "s.`country_id` = ?";
            $params[] = (int)$filters['country_id'];
            $types .= 'i';
        }
        
        // State filter
        if (isset($filters['state_id']) && $filters['state_id'] !== '') {
            $whereClause[] = "ci.`state_id` = ?";
            $params[] = (int)$filters['state_id'];
            $types .= 'i';
        }
        
        // Zone filter
        if (isset($filters['zone_id']) && $filters['zone_id'] !== '') {
            $whereClause[] = "ci.`zone_id` = ?";
            $params[] = (int)$filters['zone_id'];
            $types .= 'i';
        }
        
        // Search filter
        if (!empty($filters['search'])) {
            $whereClause[] = "ci.`name` LIKE ?";
            $params[] = '%' . $filters['search'] . '%';
            $types .= 's';
        }
        
        $whereSQL = '';
        if (!empty($whereClause)) {
            $whereSQL = ' WHERE ' . implode(' AND ', $whereClause);
        }
        
        $allowedOrderColumns = ['id', 'name', 'status', 'created_at', 'state_name', 'country_name', 'zone_name'];
        if (!in_array($orderBy, $allowedOrderColumns)) {
            $orderBy = 'name';
        }
        
        // Map order columns
        $orderColumn = $orderBy;
        if ($orderBy === 'state_name') {
            $orderColumn = 's.name';
        } elseif ($orderBy === 'country_name') {
            $orderColumn = 'c.name';
        } elseif ($orderBy === 'zone_name') {
            $orderColumn = 'z.name';
        } else {
            $orderColumn = "ci.`$orderBy`";
        }
        
        // Get total count
        $countSQL = "SELECT COUNT(*) as total FROM `cities` ci
                     LEFT JOIN `states` s ON ci.`state_id` = s.`id`" . $whereSQL;
        $countResult = $this->db->getResults($countSQL, $params, $types);
        $total = (int)($countResult[0]['total'] ?? 0);
        
        // Get paginated data with relationships
        $dataSQL = "SELECT ci.*, 
                    s.name as state_name,
                    s.country_id,
                    c.name as country_name,
                    z.name as zone_name
                    FROM `cities` ci
                    LEFT JOIN `states` s ON ci.`state_id` = s.`id`
                    LEFT JOIN `countries` c ON s.`country_id` = c.`id`
                    LEFT JOIN `zones` z ON ci.`zone_id` = z.`id`" . $whereSQL . 
                   " ORDER BY $orderColumn $orderDir LIMIT ? OFFSET ?";
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
     * Find city by ID
     * 
     * @param int $id City ID
     * @return array|null City record or null if not found
     */
    public function findCityById(int $id): ?array {
        $sql = "SELECT ci.*, 
                s.name as state_name,
                s.country_id,
                c.name as country_name,
                z.name as zone_name
                FROM `cities` ci
                LEFT JOIN `states` s ON ci.`state_id` = s.`id`
                LEFT JOIN `countries` c ON s.`country_id` = c.`id`
                LEFT JOIN `zones` z ON ci.`zone_id` = z.`id`
                WHERE ci.`id` = ?";
        $result = $this->db->getResults($sql, [$id], 'i');
        return !empty($result) ? $result[0] : null;
    }
    
    /**
     * Find city by name within a state (for uniqueness checking)
     * 
     * @param string $name City name
     * @param int $stateId State ID
     * @param int|null $excludeId Optional ID to exclude (for updates)
     * @return array|null City record or null if not found
     */
    public function findCityByNameAndState(string $name, int $stateId, ?int $excludeId = null): ?array {
        $sql = "SELECT * FROM `cities` WHERE `name` = ? AND `state_id` = ?";
        $params = [$name, $stateId];
        $types = 'si';
        
        if ($excludeId !== null) {
            $sql .= " AND `id` != ?";
            $params[] = $excludeId;
            $types .= 'i';
        }
        
        $result = $this->db->getResults($sql, $params, $types);
        return !empty($result) ? $result[0] : null;
    }

    
    /**
     * Create a new city record
     * 
     * @param array $data City data: name, state_id, zone_id, status, created_by
     * @return int The ID of the newly created city
     * @throws Exception If creation fails
     * 
     * Requirements: 6.2
     */
    public function createCity(array $data): int {
        if (!isset($data['name']) || trim($data['name']) === '') {
            throw new Exception("City name is required");
        }
        
        if (!isset($data['state_id']) || (int)$data['state_id'] <= 0) {
            throw new Exception("State is required");
        }
        
        $fields = ['name', 'state_id'];
        $placeholders = ['?', '?'];
        $values = [trim($data['name']), (int)$data['state_id']];
        $types = 'si';
        
        // Optional: zone_id
        if (isset($data['zone_id']) && $data['zone_id'] !== '' && $data['zone_id'] !== null) {
            $fields[] = 'zone_id';
            $placeholders[] = '?';
            $values[] = (int)$data['zone_id'];
            $types .= 'i';
        }
        
        $fields[] = 'status';
        $placeholders[] = '?';
        $values[] = $data['status'] ?? 'active';
        $types .= 's';
        
        if (isset($data['created_by'])) {
            $fields[] = 'created_by';
            $placeholders[] = '?';
            $values[] = (int)$data['created_by'];
            $types .= 'i';
        }
        
        $sql = "INSERT INTO `cities` (`" . implode('`, `', $fields) . "`) VALUES (" . implode(', ', $placeholders) . ")";
        
        $stmt = $this->db->executeQuery($sql, $values, $types);
        $insertId = $this->db->getConnection()->insert_id;
        $stmt->close();
        
        if ($insertId <= 0) {
            throw new Exception("Failed to create city record");
        }
        
        return $insertId;
    }
    
    /**
     * Update an existing city record
     * 
     * @param int $id City ID
     * @param array $data Data to update: name, state_id, zone_id, status, updated_by
     * @return bool True if update was successful
     * @throws Exception If update fails
     * 
     * Requirements: 6.3
     */
    public function updateCity(int $id, array $data): bool {
        $existing = $this->findCityById($id);
        if (!$existing) {
            throw new Exception("City record not found");
        }
        
        $setClauses = [];
        $values = [];
        $types = '';
        
        if (isset($data['name'])) {
            if (trim($data['name']) === '') {
                throw new Exception("City name cannot be empty");
            }
            $setClauses[] = '`name` = ?';
            $values[] = trim($data['name']);
            $types .= 's';
        }
        
        if (isset($data['state_id'])) {
            $setClauses[] = '`state_id` = ?';
            $values[] = (int)$data['state_id'];
            $types .= 'i';
        }
        
        if (array_key_exists('zone_id', $data)) {
            $setClauses[] = '`zone_id` = ?';
            $values[] = $data['zone_id'] !== null && $data['zone_id'] !== '' ? (int)$data['zone_id'] : null;
            $types .= 'i';
        }
        
        if (isset($data['status'])) {
            $setClauses[] = '`status` = ?';
            $values[] = $data['status'];
            $types .= 's';
        }
        
        if (isset($data['updated_by'])) {
            $setClauses[] = '`updated_by` = ?';
            $values[] = (int)$data['updated_by'];
            $types .= 'i';
        }
        
        if (empty($setClauses)) {
            return true;
        }
        
        $values[] = $id;
        $types .= 'i';
        
        $sql = "UPDATE `cities` SET " . implode(', ', $setClauses) . " WHERE `id` = ?";
        
        $stmt = $this->db->executeQuery($sql, $values, $types);
        $stmt->close();
        
        return true;
    }
    
    /**
     * Soft delete a city record (set status to inactive)
     * 
     * @param int $id City ID
     * @param int|null $deletedBy User ID who performed the deletion
     * @return bool True if soft delete was successful
     * @throws Exception If deletion fails
     * 
     * Requirements: 6.5
     */
    public function softDeleteCity(int $id, ?int $deletedBy = null): bool {
        $existing = $this->findCityById($id);
        if (!$existing) {
            throw new Exception("City record not found");
        }
        
        $data = ['status' => 'inactive'];
        if ($deletedBy !== null) {
            $data['updated_by'] = $deletedBy;
        }
        
        return $this->updateCity($id, $data);
    }
    
    /**
     * Get cities by state (for cascading dropdowns)
     * 
     * @param int $stateId State ID
     * @return array Array of city records
     */
    public function getCitiesByState(int $stateId): array {
        $sql = "SELECT ci.*, z.name as zone_name 
                FROM `cities` ci 
                LEFT JOIN `zones` z ON ci.`zone_id` = z.`id`
                WHERE ci.`state_id` = ? AND ci.`status` = 'active' 
                ORDER BY ci.`name` ASC";
        return $this->db->getResults($sql, [$stateId], 'i');
    }
    
    /**
     * Get all active cities (for dropdowns)
     * 
     * @return array Array of active city records
     */
    public function findAllActiveCities(): array {
        $sql = "SELECT ci.*, s.name as state_name, c.name as country_name, z.name as zone_name 
                FROM `cities` ci 
                LEFT JOIN `states` s ON ci.`state_id` = s.`id`
                LEFT JOIN `countries` c ON s.`country_id` = c.`id`
                LEFT JOIN `zones` z ON ci.`zone_id` = z.`id`
                WHERE ci.`status` = 'active' 
                ORDER BY c.`name`, s.`name`, ci.`name` ASC";
        return $this->db->getResults($sql, [], '');
    }
    
    // ==================== EXPORT OPERATIONS ====================
    
    /**
     * Get countries for export (all matching filters, no pagination)
     * 
     * @param array $filters Optional filters: search, status
     * @return array Array of country records
     */
    public function findAllCountriesForExport(array $filters = []): array {
        $whereClause = [];
        $params = [];
        $types = '';
        
        if (isset($filters['status']) && $filters['status'] !== '') {
            $whereClause[] = "`status` = ?";
            $params[] = $filters['status'];
            $types .= 's';
        }
        
        if (!empty($filters['search'])) {
            $whereClause[] = "`name` LIKE ?";
            $params[] = '%' . $filters['search'] . '%';
            $types .= 's';
        }
        
        $whereSQL = '';
        if (!empty($whereClause)) {
            $whereSQL = ' WHERE ' . implode(' AND ', $whereClause);
        }
        
        $sql = "SELECT * FROM `countries`" . $whereSQL . " ORDER BY `name` ASC";
        return $this->db->getResults($sql, $params, $types);
    }
    
    /**
     * Get states for export (all matching filters, no pagination)
     * 
     * @param array $filters Optional filters: search, status, country_id, zone_id
     * @return array Array of state records
     */
    public function findAllStatesForExport(array $filters = []): array {
        $whereClause = [];
        $params = [];
        $types = '';
        
        if (isset($filters['status']) && $filters['status'] !== '') {
            $whereClause[] = "s.`status` = ?";
            $params[] = $filters['status'];
            $types .= 's';
        }
        
        if (isset($filters['country_id']) && $filters['country_id'] !== '') {
            $whereClause[] = "s.`country_id` = ?";
            $params[] = (int)$filters['country_id'];
            $types .= 'i';
        }
        
        if (isset($filters['zone_id']) && $filters['zone_id'] !== '') {
            $whereClause[] = "s.`zone_id` = ?";
            $params[] = (int)$filters['zone_id'];
            $types .= 'i';
        }
        
        if (!empty($filters['search'])) {
            $whereClause[] = "s.`name` LIKE ?";
            $params[] = '%' . $filters['search'] . '%';
            $types .= 's';
        }
        
        $whereSQL = '';
        if (!empty($whereClause)) {
            $whereSQL = ' WHERE ' . implode(' AND ', $whereClause);
        }
        
        $sql = "SELECT s.*, c.name as country_name, z.name as zone_name 
                FROM `states` s
                LEFT JOIN `countries` c ON s.`country_id` = c.`id`
                LEFT JOIN `zones` z ON s.`zone_id` = z.`id`" . $whereSQL . " ORDER BY c.`name`, s.`name` ASC";
        return $this->db->getResults($sql, $params, $types);
    }
    
    /**
     * Get zones for export (all matching filters, no pagination)
     * 
     * @param array $filters Optional filters: search, status
     * @return array Array of zone records
     */
    public function findAllZonesForExport(array $filters = []): array {
        $whereClause = [];
        $params = [];
        $types = '';
        
        if (isset($filters['status']) && $filters['status'] !== '') {
            $whereClause[] = "`status` = ?";
            $params[] = $filters['status'];
            $types .= 's';
        }
        
        if (!empty($filters['search'])) {
            $whereClause[] = "`name` LIKE ?";
            $params[] = '%' . $filters['search'] . '%';
            $types .= 's';
        }
        
        $whereSQL = '';
        if (!empty($whereClause)) {
            $whereSQL = ' WHERE ' . implode(' AND ', $whereClause);
        }
        
        $sql = "SELECT * FROM `zones`" . $whereSQL . " ORDER BY `name` ASC";
        return $this->db->getResults($sql, $params, $types);
    }
    
    /**
     * Get cities for export (all matching filters, no pagination)
     * 
     * @param array $filters Optional filters: search, status, country_id, state_id, zone_id
     * @return array Array of city records
     */
    public function findAllCitiesForExport(array $filters = []): array {
        $whereClause = [];
        $params = [];
        $types = '';
        
        if (isset($filters['status']) && $filters['status'] !== '') {
            $whereClause[] = "ci.`status` = ?";
            $params[] = $filters['status'];
            $types .= 's';
        }
        
        if (isset($filters['country_id']) && $filters['country_id'] !== '') {
            $whereClause[] = "s.`country_id` = ?";
            $params[] = (int)$filters['country_id'];
            $types .= 'i';
        }
        
        if (isset($filters['state_id']) && $filters['state_id'] !== '') {
            $whereClause[] = "ci.`state_id` = ?";
            $params[] = (int)$filters['state_id'];
            $types .= 'i';
        }
        
        if (isset($filters['zone_id']) && $filters['zone_id'] !== '') {
            $whereClause[] = "ci.`zone_id` = ?";
            $params[] = (int)$filters['zone_id'];
            $types .= 'i';
        }
        
        if (!empty($filters['search'])) {
            $whereClause[] = "ci.`name` LIKE ?";
            $params[] = '%' . $filters['search'] . '%';
            $types .= 's';
        }
        
        $whereSQL = '';
        if (!empty($whereClause)) {
            $whereSQL = ' WHERE ' . implode(' AND ', $whereClause);
        }
        
        $sql = "SELECT ci.*, s.name as state_name, c.name as country_name, z.name as zone_name 
                FROM `cities` ci
                LEFT JOIN `states` s ON ci.`state_id` = s.`id`
                LEFT JOIN `countries` c ON s.`country_id` = c.`id`
                LEFT JOIN `zones` z ON ci.`zone_id` = z.`id`" . $whereSQL . " ORDER BY c.`name`, s.`name`, ci.`name` ASC";
        return $this->db->getResults($sql, $params, $types);
    }
    
    // ==================== LHO (Local Head Office) Methods ====================
    
    /**
     * Get all LHOs with pagination and filters
     * 
     * @param array $filters Filter options (search, status, page, limit)
     * @return array Paginated LHO records
     */
    public function findAllLhos(array $filters = []): array {
        $page = isset($filters['page']) ? max(1, (int)$filters['page']) : 1;
        $limit = isset($filters['limit']) ? max(1, min(100, (int)$filters['limit'])) : 10;
        $offset = ($page - 1) * $limit;
        
        $whereClause = [];
        $params = [];
        $types = '';
        
        // Search filter
        if (!empty($filters['search'])) {
            $whereClause[] = "`lho_name` LIKE ?";
            $params[] = '%' . $filters['search'] . '%';
            $types .= 's';
        }
        
        // Status filter
        if (!empty($filters['status'])) {
            $whereClause[] = "`status` = ?";
            $params[] = $filters['status'];
            $types .= 's';
        }
        
        $whereSQL = '';
        if (!empty($whereClause)) {
            $whereSQL = ' WHERE ' . implode(' AND ', $whereClause);
        }
        
        // Get total count
        $countSql = "SELECT COUNT(*) as total FROM `lhos`" . $whereSQL;
        $countResult = $this->db->getResults($countSql, $params, $types);
        $total = $countResult[0]['total'] ?? 0;
        
        // Get paginated results
        $sql = "SELECT * FROM `lhos`" . $whereSQL . " ORDER BY `lho_name` ASC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        $types .= 'ii';
        
        $lhos = $this->db->getResults($sql, $params, $types);
        
        return [
            'data' => $lhos,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'totalPages' => ceil($total / $limit)
        ];
    }
    
    /**
     * Find LHO by ID
     * 
     * @param int $id LHO ID
     * @return array|null LHO record or null if not found
     */
    public function findLhoById(int $id): ?array {
        $sql = "SELECT * FROM `lhos` WHERE `id` = ?";
        $result = $this->db->getResults($sql, [$id], 'i');
        return !empty($result) ? $result[0] : null;
    }
    
    /**
     * Find LHO by name
     * 
     * @param string $name LHO name
     * @param int|null $excludeId ID to exclude (for update validation)
     * @return array|null LHO record or null if not found
     */
    public function findLhoByName(string $name, ?int $excludeId = null): ?array {
        $sql = "SELECT * FROM `lhos` WHERE `lho_name` = ?";
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
     * Create a new LHO
     * 
     * @param array $data LHO data
     * @return int New LHO ID
     */
    public function createLho(array $data): int {
        if (!isset($data['lho_name']) || trim($data['lho_name']) === '') {
            throw new Exception("LHO name is required");
        }
        
        $fields = ['lho_name'];
        $placeholders = ['?'];
        $values = [trim($data['lho_name'])];
        $types = 's';
        
        // Status (default: active)
        $fields[] = 'status';
        $placeholders[] = '?';
        $values[] = $data['status'] ?? 'active';
        $types .= 's';
        
        if (isset($data['created_by'])) {
            $fields[] = 'created_by';
            $placeholders[] = '?';
            $values[] = (int)$data['created_by'];
            $types .= 'i';
        }
        
        $sql = "INSERT INTO `lhos` (`" . implode('`, `', $fields) . "`) VALUES (" . implode(', ', $placeholders) . ")";
        
        $stmt = $this->db->executeQuery($sql, $values, $types);
        $insertId = $this->db->getConnection()->insert_id;
        $stmt->close();
        
        if ($insertId <= 0) {
            throw new Exception("Failed to create LHO record");
        }
        
        return $insertId;
    }
    
    /**
     * Update an LHO
     * 
     * @param int $id LHO ID
     * @param array $data Update data
     * @return bool Success status
     */
    public function updateLho(int $id, array $data): bool {
        $existing = $this->findLhoById($id);
        if (!$existing) {
            throw new Exception("LHO not found");
        }
        
        $updates = [];
        $params = [];
        $types = '';
        
        if (isset($data['lho_name'])) {
            $updates[] = "`lho_name` = ?";
            $params[] = trim($data['lho_name']);
            $types .= 's';
        }
        
        if (isset($data['status'])) {
            $updates[] = "`status` = ?";
            $params[] = $data['status'];
            $types .= 's';
        }
        
        if (isset($data['updated_by'])) {
            $updates[] = "`updated_by` = ?";
            $params[] = $data['updated_by'];
            $types .= 'i';
        }
        
        if (empty($updates)) {
            return true;
        }
        
        $updates[] = "`updated_at` = NOW()";
        $params[] = $id;
        $types .= 'i';
        
        $sql = "UPDATE `lhos` SET " . implode(', ', $updates) . " WHERE `id` = ?";
        $stmt = $this->db->executeQuery($sql, $params, $types);
        $stmt->close();
        
        return true;
    }
    
    /**
     * Delete an LHO
     * 
     * @param int $id LHO ID
     * @return bool Success status
     */
    public function deleteLho(int $id): bool {
        $existing = $this->findLhoById($id);
        if (!$existing) {
            throw new Exception("LHO not found");
        }
        
        $sql = "DELETE FROM `lhos` WHERE `id` = ?";
        $stmt = $this->db->executeQuery($sql, [$id], 'i');
        $stmt->close();
        
        return true;
    }
    
    /**
     * Get all active LHOs
     * 
     * @return array Array of active LHO records
     */
    public function findAllActiveLhos(): array {
        $sql = "SELECT * FROM `lhos` WHERE `status` = 'active' ORDER BY `lho_name` ASC";
        return $this->db->getResults($sql, [], '');
    }
    
    /**
     * Get all LHOs for export
     * 
     * @param array $filters Filter options
     * @return array Array of LHO records
     */
    public function findAllLhosForExport(array $filters = []): array {
        $whereClause = [];
        $params = [];
        $types = '';
        
        if (!empty($filters['search'])) {
            $whereClause[] = "`lho_name` LIKE ?";
            $params[] = '%' . $filters['search'] . '%';
            $types .= 's';
        }
        
        if (!empty($filters['status'])) {
            $whereClause[] = "`status` = ?";
            $params[] = $filters['status'];
            $types .= 's';
        }
        
        $whereSQL = '';
        if (!empty($whereClause)) {
            $whereSQL = ' WHERE ' . implode(' AND ', $whereClause);
        }
        
        $sql = "SELECT * FROM `lhos`" . $whereSQL . " ORDER BY `lho_name` ASC";
        return $this->db->getResults($sql, $params, $types);
    }
}
