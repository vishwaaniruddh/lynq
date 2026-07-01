<?php
/**
 * MaterialMaster Model
 * Represents a reusable template defining a set of products required for site installations
 * 
 * Requirements: 1.1, 1.4
 * - Material Master with name, description, status, company isolation
 * - Soft delete support
 * - Items relationship for associated products with quantities
 */

require_once __DIR__ . '/BaseModel.php';

class MaterialMaster extends BaseModel {
    protected $table = 'material_masters';
    protected $fillable = [
        'name', 'description', 'status', 'company_id',
        'created_by', 'created_at', 'updated_at', 'deleted_at'
    ];
    
    // Status constants
    const STATUS_ACTIVE = 'active';
    const STATUS_INACTIVE = 'inactive';
    
    /**
     * Get all valid statuses
     * 
     * @return array Valid status values
     */
    public static function getStatuses(): array {
        return [
            self::STATUS_ACTIVE,
            self::STATUS_INACTIVE
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
     * Find Material Master by ID with items
     * Requirement 1.1
     * 
     * @param int $id Material Master ID
     * @return array|null Material Master with items or null
     */
    public function findWithItems(int $id): ?array {
        $master = $this->find($id);
        if (!$master) {
            return null;
        }
        
        $master['items'] = $this->getItems($id);
        return $master;
    }
    
    /**
     * Get items for a Material Master
     * 
     * @param int $masterId Material Master ID
     * @return array Items with product details
     */
    public function getItems(int $masterId): array {
        $sql = "SELECT mmi.*, p.name as product_name, p.unit_of_measure, 
                       p.is_serializable, pc.name as category_name
                FROM material_master_items mmi
                LEFT JOIN products p ON mmi.product_id = p.id
                LEFT JOIN product_categories pc ON p.category_id = pc.id
                WHERE mmi.material_master_id = ?
                ORDER BY p.name";
        
        return DatabaseConfig::getInstance()->getResults($sql, [$masterId], 'i');
    }
    
    /**
     * Find all Material Masters by company
     * Requirement 1.1
     * 
     * @param int $companyId Company ID
     * @param bool $includeDeleted Include soft-deleted records
     * @return array Material Masters
     */
    public function findByCompany(int $companyId, bool $includeDeleted = false): array {
        $sql = "SELECT mm.*, 
                       (SELECT COUNT(*) FROM material_master_items WHERE material_master_id = mm.id) as item_count,
                       u.name as created_by_name
                FROM `{$this->table}` mm
                LEFT JOIN users u ON mm.created_by = u.id
                WHERE mm.company_id = ?";
        $params = [$companyId];
        $types = 'i';
        
        if (!$includeDeleted) {
            $sql .= " AND mm.deleted_at IS NULL";
        }
        
        $sql .= " ORDER BY mm.name";
        
        return DatabaseConfig::getInstance()->getResults($sql, $params, $types);
    }
    
    /**
     * Find active Material Masters for selection
     * Requirement 1.6
     * 
     * @param int $companyId Company ID
     * @return array Active Material Masters
     */
    public function findActiveForSelection(int $companyId): array {
        $sql = "SELECT mm.id, mm.name, mm.description,
                       (SELECT COUNT(*) FROM material_master_items WHERE material_master_id = mm.id) as item_count
                FROM `{$this->table}` mm
                WHERE mm.company_id = ? 
                  AND mm.status = ? 
                  AND mm.deleted_at IS NULL
                ORDER BY mm.name";
        
        return DatabaseConfig::getInstance()->getResults($sql, [$companyId, self::STATUS_ACTIVE], 'is');
    }
    
    /**
     * Soft delete Material Master
     * Requirement 1.6
     * 
     * @param int $id Material Master ID
     * @return bool Success
     */
    public function softDelete(int $id): bool {
        $sql = "UPDATE `{$this->table}` SET `deleted_at` = NOW(), `updated_at` = NOW() WHERE `id` = ?";
        $stmt = DatabaseConfig::getInstance()->executeQuery($sql, [$id], 'i');
        $affectedRows = $stmt->affected_rows;
        $stmt->close();
        
        return $affectedRows > 0;
    }
    
    /**
     * Check if Material Master name is unique within company
     * 
     * @param string $name Material Master name
     * @param int $companyId Company ID
     * @param int|null $excludeId ID to exclude (for updates)
     * @return bool True if name is unique
     */
    public function isNameUnique(string $name, int $companyId, ?int $excludeId = null): bool {
        $sql = "SELECT COUNT(*) as count FROM `{$this->table}` 
                WHERE `name` = ? AND `company_id` = ? AND `deleted_at` IS NULL";
        $params = [$name, $companyId];
        $types = 'si';
        
        if ($excludeId !== null) {
            $sql .= " AND `id` != ?";
            $params[] = $excludeId;
            $types .= 'i';
        }
        
        $result = DatabaseConfig::getInstance()->getResults($sql, $params, $types);
        return $result[0]['count'] == 0;
    }
    
    /**
     * Get product count for Material Master
     * 
     * @param int $id Material Master ID
     * @return int Product count
     */
    public function getProductCount(int $id): int {
        $sql = "SELECT COUNT(*) as count FROM material_master_items WHERE material_master_id = ?";
        $result = DatabaseConfig::getInstance()->getResults($sql, [$id], 'i');
        return (int)($result[0]['count'] ?? 0);
    }
    
    /**
     * Validate Material Master data
     * 
     * @param array $data Data to validate
     * @param int|null $excludeId ID to exclude for uniqueness check
     * @return array Validation result with 'isValid' and 'errors'
     */
    public function validate(array $data, ?int $excludeId = null): array {
        $errors = [];
        
        // Validate name
        if (!isset($data['name']) || trim($data['name']) === '') {
            $errors[] = [
                'field' => 'name',
                'message' => 'Name is required',
                'code' => 'REQUIRED_FIELD_MISSING'
            ];
        } elseif (strlen($data['name']) > 100) {
            $errors[] = [
                'field' => 'name',
                'message' => 'Name must not exceed 100 characters',
                'code' => 'FIELD_TOO_LONG'
            ];
        }
        
        // Validate description length
        if (isset($data['description']) && strlen($data['description']) > 500) {
            $errors[] = [
                'field' => 'description',
                'message' => 'Description must not exceed 500 characters',
                'code' => 'FIELD_TOO_LONG'
            ];
        }
        
        // Validate status
        if (isset($data['status']) && !self::isValidStatus($data['status'])) {
            $errors[] = [
                'field' => 'status',
                'message' => 'Invalid status value',
                'code' => 'INVALID_STATUS'
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
        
        // Check name uniqueness
        if (isset($data['name']) && isset($data['company_id']) && 
            !$this->isNameUnique($data['name'], $data['company_id'], $excludeId)) {
            $errors[] = [
                'field' => 'name',
                'message' => 'A Material Master with this name already exists',
                'code' => 'DUPLICATE_NAME'
            ];
        }
        
        return [
            'isValid' => empty($errors),
            'errors' => $errors
        ];
    }
}
