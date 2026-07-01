<?php
/**
 * Product Category Service
 * Handles business logic for product category master module operations
 * 
 * Requirements: 2.1, 2.4
 * - Product category for organizing inventory items
 * - Support hierarchical categories with parent_id
 */

require_once __DIR__ . '/../config/autoload.php';
require_once __DIR__ . '/../repositories/ProductCategoryRepository.php';

class ProductCategoryService {
    private $db;
    private $categoryRepository;
    
    public function __construct() {
        $this->db = DatabaseConfig::getInstance();
        $this->categoryRepository = new ProductCategoryRepository();
    }
    
    /**
     * Get all categories with filters
     */
    public function getAll(array $filters = []): array {
        $page = $filters['page'] ?? 1;
        $limit = $filters['limit'] ?? 20;
        $search = $filters['search'] ?? null;
        $status = $filters['status'] ?? null;
        $offset = ($page - 1) * $limit;
        
        $conditions = [];
        $params = [];
        $types = '';
        
        if ($status !== null && $status !== '') {
            $conditions[] = "c.status = ?";
            $params[] = $status;
            $types .= 's';
        }
        
        if ($search) {
            $conditions[] = "(c.name LIKE ? OR c.description LIKE ?)";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
            $types .= 'ss';
        }
        
        $whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';
        
        // Get total count
        $countSql = "SELECT COUNT(*) as total FROM product_categories c {$whereClause}";
        $countResult = $this->db->getResults($countSql, $params, $types);
        $total = $countResult[0]['total'] ?? 0;
        
        // Get paginated data
        $sql = "SELECT c.*, p.name as parent_name,
                (SELECT COUNT(*) FROM products WHERE category_id = c.id) as product_count
                FROM product_categories c
                LEFT JOIN product_categories p ON c.parent_id = p.id
                {$whereClause}
                ORDER BY c.name ASC
                LIMIT ? OFFSET ?";
        
        $params[] = $limit;
        $params[] = $offset;
        $types .= 'ii';
        
        $data = $this->db->getResults($sql, $params, $types);
        
        return [
            'data' => $data ?: [],
            'total' => (int)$total,
            'page' => (int)$page,
            'limit' => (int)$limit,
            'totalPages' => ceil($total / $limit)
        ];
    }
    
    /**
     * Get category by ID
     */
    public function getById(int $id): ?array {
        return $this->categoryRepository->findWithParent($id);
    }
    
    /**
     * Create a new category
     */
    public function create(array $data, ?int $userId = null): array {
        $validation = $this->validate($data);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validation['errors'],
                'code' => 'VALIDATION_ERROR'
            ];
        }
        
        // Check uniqueness
        if ($this->nameExists(trim($data['name']))) {
            return [
                'success' => false,
                'message' => 'A category with this name already exists',
                'errors' => ['name' => ['Category name must be unique']],
                'code' => 'DUPLICATE_ERROR'
            ];
        }
        
        try {
            $categoryData = [
                'name' => trim($data['name']),
                'description' => isset($data['description']) ? trim($data['description']) : null,
                'parent_id' => !empty($data['parent_id']) ? (int)$data['parent_id'] : null,
                'status' => $data['status'] ?? 'active',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            $createdCategory = $this->categoryRepository->create($categoryData);
            $categoryId = is_array($createdCategory) ? $createdCategory['id'] : $createdCategory;
            
            $this->logAction($userId, (int)$categoryId, 'product_category_created', [
                'name' => $categoryData['name']
            ]);
            
            $category = $this->categoryRepository->findWithParent((int)$categoryId);
            
            return [
                'success' => true,
                'message' => 'Category created successfully',
                'data' => $category
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to create category: ' . $e->getMessage(),
                'code' => 'CREATE_ERROR'
            ];
        }
    }
    
    /**
     * Update an existing category
     */
    public function update(int $id, array $data, ?int $userId = null): array {
        $existing = $this->categoryRepository->find($id);
        if (!$existing) {
            return [
                'success' => false,
                'message' => 'Category not found',
                'code' => 'NOT_FOUND'
            ];
        }
        
        if (isset($data['name'])) {
            $validation = $this->validate($data, $id);
            if (!$validation['valid']) {
                return [
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validation['errors'],
                    'code' => 'VALIDATION_ERROR'
                ];
            }
            
            if ($this->nameExists(trim($data['name']), $id)) {
                return [
                    'success' => false,
                    'message' => 'A category with this name already exists',
                    'errors' => ['name' => ['Category name must be unique']],
                    'code' => 'DUPLICATE_ERROR'
                ];
            }
        }
        
        // Prevent setting parent to self or child
        if (isset($data['parent_id']) && $data['parent_id']) {
            if ((int)$data['parent_id'] === $id) {
                return [
                    'success' => false,
                    'message' => 'Category cannot be its own parent',
                    'errors' => ['parent_id' => ['Invalid parent category']],
                    'code' => 'VALIDATION_ERROR'
                ];
            }
        }
        
        try {
            $updateData = ['updated_at' => date('Y-m-d H:i:s')];
            
            if (isset($data['name'])) {
                $updateData['name'] = trim($data['name']);
            }
            if (isset($data['description'])) {
                $updateData['description'] = trim($data['description']);
            }
            if (array_key_exists('parent_id', $data)) {
                $updateData['parent_id'] = !empty($data['parent_id']) ? (int)$data['parent_id'] : null;
            }
            if (isset($data['status'])) {
                $updateData['status'] = $data['status'];
            }
            
            $this->categoryRepository->update($id, $updateData);
            
            $this->logAction($userId, $id, 'product_category_updated', [
                'changes' => array_keys($updateData)
            ]);
            
            $category = $this->categoryRepository->findWithParent($id);
            
            return [
                'success' => true,
                'message' => 'Category updated successfully',
                'data' => $category
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to update category: ' . $e->getMessage(),
                'code' => 'UPDATE_ERROR'
            ];
        }
    }
    
    /**
     * Delete a category (soft delete)
     */
    public function delete(int $id, ?int $userId = null): array {
        $existing = $this->categoryRepository->find($id);
        if (!$existing) {
            return [
                'success' => false,
                'message' => 'Category not found',
                'code' => 'NOT_FOUND'
            ];
        }
        
        if (!$this->categoryRepository->canDelete($id)) {
            return [
                'success' => false,
                'message' => 'Cannot delete category with products or child categories',
                'code' => 'DELETE_ERROR'
            ];
        }
        
        try {
            $this->categoryRepository->update($id, [
                'status' => 'inactive',
                'updated_at' => date('Y-m-d H:i:s')
            ]);
            
            $this->logAction($userId, $id, 'product_category_deleted', [
                'name' => $existing['name']
            ]);
            
            return [
                'success' => true,
                'message' => 'Category deleted successfully'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to delete category: ' . $e->getMessage(),
                'code' => 'DELETE_ERROR'
            ];
        }
    }
    
    /**
     * Export categories
     */
    public function export(array $filters = []): array {
        $result = $this->getAll(array_merge($filters, ['limit' => 10000]));
        return $result['data'];
    }
    
    /**
     * Get active categories for dropdown
     */
    public function getActiveList(): array {
        return $this->categoryRepository->findActive();
    }
    
    /**
     * Check if name exists
     */
    public function nameExists(string $name, ?int $excludeId = null): bool {
        $sql = "SELECT COUNT(*) as count FROM product_categories WHERE name = ?";
        $params = [$name];
        $types = 's';
        
        if ($excludeId) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
            $types .= 'i';
        }
        
        $result = $this->db->getResults($sql, $params, $types);
        return ($result[0]['count'] ?? 0) > 0;
    }
    
    /**
     * Validate category data
     */
    private function validate(array $data, ?int $id = null): array {
        $errors = [];
        
        if (!isset($data['name']) || trim($data['name']) === '') {
            $errors['name'] = ['Category name is required'];
        } elseif (strlen(trim($data['name'])) > 255) {
            $errors['name'] = ['Category name must not exceed 255 characters'];
        }
        
        if (isset($data['status']) && !in_array($data['status'], ['active', 'inactive'])) {
            $errors['status'] = ['Status must be active or inactive'];
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
    
    /**
     * Log action for audit
     */
    private function logAction(?int $userId, int $categoryId, string $action, array $details): void {
        try {
            $sql = "INSERT INTO user_audit_log (user_id, action, details, performed_by, ip_address) 
                    VALUES (?, ?, ?, ?, ?)";
            
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'CLI';
            $details['category_id'] = $categoryId;
            $details['entity_type'] = 'product_category';
            
            $stmt = $this->db->executeQuery($sql, [
                $userId ?? 0,
                $action,
                json_encode($details),
                $userId ?? 0,
                $ipAddress
            ], 'issis');
            $stmt->close();
        } catch (Exception $e) {
            error_log("Failed to log product category action: " . $e->getMessage());
        }
    }
}
