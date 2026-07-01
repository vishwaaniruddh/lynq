<?php
/**
 * Material Master Service
 * Handles business logic for Material Master operations
 * 
 * Requirements: 1.4, 1.5, 1.6, 9.2, 9.3, 9.4
 * - 1.4: Create Material Master with products and quantities
 * - 1.5: Update Material Master with items replacement
 * - 1.6: Soft delete Material Master
 * - 9.2: API create with validation
 * - 9.3: API update with validation
 * - 9.4: API soft delete
 */

require_once __DIR__ . '/../config/autoload.php';
require_once __DIR__ . '/../repositories/MaterialMasterRepository.php';
require_once __DIR__ . '/../repositories/ProductRepository.php';

class MaterialMasterService {
    private $db;
    private $materialMasterRepository;
    private $productRepository;
    
    public function __construct() {
        $this->db = DatabaseConfig::getInstance();
        $this->materialMasterRepository = new MaterialMasterRepository();
        $this->productRepository = new ProductRepository();
    }
    
    /**
     * Create a new Material Master with items
     * Requirement 1.4, 9.2
     * 
     * @param array $data Material Master data: name, description, items
     * @param int $userId User ID performing the action
     * @param int $companyId Company ID for isolation
     * @return array Result with success status and data/errors
     */
    public function create(array $data, int $userId, int $companyId): array {
        // Validate required fields
        $validation = $this->validate($data);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validation['errors'],
                'code' => 'VALIDATION_ERROR'
            ];
        }
        
        // Validate items
        if (empty($data['items']) || !is_array($data['items'])) {
            return [
                'success' => false,
                'message' => 'At least one product is required',
                'errors' => ['items' => ['At least one product is required']],
                'code' => 'VALIDATION_ERROR'
            ];
        }
        
        // Validate each item
        $itemValidation = $this->validateItems($data['items']);
        if (!$itemValidation['valid']) {
            return [
                'success' => false,
                'message' => 'Item validation failed',
                'errors' => $itemValidation['errors'],
                'code' => 'VALIDATION_ERROR'
            ];
        }
        
        try {
            // Prepare master data
            $masterData = [
                'name' => trim($data['name']),
                'description' => isset($data['description']) ? trim($data['description']) : null,
                'status' => $data['status'] ?? MaterialMasterRepository::STATUS_ACTIVE,
                'company_id' => $companyId,
                'created_by' => $userId
            ];
            
            // Create master
            $masterId = $this->materialMasterRepository->createMaster($masterData);
            
            // Create items
            $this->materialMasterRepository->createItems($masterId, $data['items']);
            
            // Log audit
            $this->logAction($userId, $masterId, 'material_master_created', [
                'name' => $masterData['name'],
                'item_count' => count($data['items'])
            ]);
            
            // Return created master with items
            $master = $this->materialMasterRepository->findByIdWithItems($masterId, $companyId);
            
            return [
                'success' => true,
                'message' => 'Material Master created successfully',
                'data' => $master
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to create Material Master: ' . $e->getMessage(),
                'code' => 'CREATE_ERROR'
            ];
        }
    }
    
    /**
     * Update an existing Material Master with items replacement
     * Requirement 1.5, 9.3
     * 
     * @param int $id Material Master ID
     * @param array $data Data to update: name, description, status, items
     * @param int|null $companyId Company ID for validation
     * @return array Result with success status and data/errors
     */
    public function update(int $id, array $data, ?int $companyId = null): array {
        // Check if master exists
        $existing = $this->materialMasterRepository->findByIdWithItems($id, $companyId);
        if (!$existing) {
            return [
                'success' => false,
                'message' => 'Material Master not found',
                'code' => 'NOT_FOUND'
            ];
        }
        
        // Validate if name is being updated
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
        }
        
        // Validate items if provided
        if (isset($data['items'])) {
            if (empty($data['items']) || !is_array($data['items'])) {
                return [
                    'success' => false,
                    'message' => 'At least one product is required',
                    'errors' => ['items' => ['At least one product is required']],
                    'code' => 'VALIDATION_ERROR'
                ];
            }
            
            $itemValidation = $this->validateItems($data['items']);
            if (!$itemValidation['valid']) {
                return [
                    'success' => false,
                    'message' => 'Item validation failed',
                    'errors' => $itemValidation['errors'],
                    'code' => 'VALIDATION_ERROR'
                ];
            }
        }
        
        try {
            // Prepare update data
            $updateData = [];
            
            if (isset($data['name'])) {
                $updateData['name'] = trim($data['name']);
            }
            
            if (array_key_exists('description', $data)) {
                $updateData['description'] = $data['description'] !== null ? trim($data['description']) : null;
            }
            
            if (isset($data['status'])) {
                $updateData['status'] = $data['status'];
            }
            
            // Update master if there are changes
            if (!empty($updateData)) {
                $this->materialMasterRepository->updateMaster($id, $updateData);
            }
            
            // Replace items if provided
            if (isset($data['items'])) {
                $this->materialMasterRepository->deleteItems($id);
                $this->materialMasterRepository->createItems($id, $data['items']);
            }
            
            // Log audit
            $this->logAction(null, $id, 'material_master_updated', [
                'changes' => array_keys($updateData),
                'items_updated' => isset($data['items'])
            ]);
            
            // Return updated master with items
            $master = $this->materialMasterRepository->findByIdWithItems($id, $companyId);
            
            return [
                'success' => true,
                'message' => 'Material Master updated successfully',
                'data' => $master
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to update Material Master: ' . $e->getMessage(),
                'code' => 'UPDATE_ERROR'
            ];
        }
    }

    
    /**
     * Soft delete a Material Master
     * Requirement 1.6, 9.4
     * 
     * @param int $id Material Master ID
     * @param int|null $companyId Company ID for validation
     * @return array Result with success status
     */
    public function delete(int $id, ?int $companyId = null): array {
        // Check if master exists
        $existing = $this->materialMasterRepository->findByIdWithItems($id, $companyId);
        if (!$existing) {
            return [
                'success' => false,
                'message' => 'Material Master not found',
                'code' => 'NOT_FOUND'
            ];
        }
        
        try {
            // Soft delete
            $this->materialMasterRepository->softDelete($id);
            
            // Log audit
            $this->logAction(null, $id, 'material_master_deleted', [
                'name' => $existing['name']
            ]);
            
            return [
                'success' => true,
                'message' => 'Material Master deleted successfully'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to delete Material Master: ' . $e->getMessage(),
                'code' => 'DELETE_ERROR'
            ];
        }
    }
    
    /**
     * Get all Material Masters with filters
     * Requirement 9.1
     * 
     * @param array $filters Optional filters: search, status, page, limit, orderBy, orderDir
     * @param int|null $companyId Company ID for isolation
     * @return array Paginated result with data, total, page, limit, totalPages
     */
    public function getAll(array $filters = [], ?int $companyId = null): array {
        return $this->materialMasterRepository->findAllPaginated($filters, $companyId);
    }
    
    /**
     * Get Material Master by ID with items
     * 
     * @param int $id Material Master ID
     * @param int|null $companyId Company ID for validation
     * @return array|null Material Master with items or null
     */
    public function getById(int $id, ?int $companyId = null): ?array {
        return $this->materialMasterRepository->findByIdWithItems($id, $companyId);
    }
    
    /**
     * Get active Material Masters for selection (dropdowns)
     * Requirement 1.6
     * 
     * @param int $companyId Company ID
     * @return array Active Material Masters
     */
    public function getActiveForSelection(int $companyId): array {
        return $this->materialMasterRepository->findActive($companyId);
    }
    
    /**
     * Get status counts for dashboard
     * 
     * @param int $companyId Company ID
     * @return array Status counts
     */
    public function getStatusCounts(int $companyId): array {
        return $this->materialMasterRepository->getStatusCounts($companyId);
    }
    
    /**
     * Check if Material Master name exists
     * 
     * @param string $name Material Master name
     * @param int $companyId Company ID
     * @param int|null $excludeId ID to exclude (for updates)
     * @return bool True if name exists
     */
    public function nameExists(string $name, int $companyId, ?int $excludeId = null): bool {
        return $this->materialMasterRepository->nameExists($name, $companyId, $excludeId);
    }
    
    /**
     * Validate Material Master data
     * 
     * @param array $data Data to validate
     * @param int|null $id Material Master ID (for updates, to exclude from uniqueness check)
     * @return array Validation result with 'valid' and 'errors'
     */
    private function validate(array $data, ?int $id = null): array {
        $errors = [];
        
        // Required field: name
        if (!isset($data['name']) || trim($data['name']) === '') {
            $errors['name'] = ['Material Master name is required'];
        } elseif (strlen(trim($data['name'])) > 100) {
            $errors['name'] = ['Material Master name must not exceed 100 characters'];
        }
        
        // Description validation (if provided)
        if (isset($data['description']) && $data['description'] !== null && strlen(trim($data['description'])) > 500) {
            $errors['description'] = ['Description must not exceed 500 characters'];
        }
        
        // Status validation (if provided)
        if (isset($data['status']) && !in_array($data['status'], [MaterialMasterRepository::STATUS_ACTIVE, MaterialMasterRepository::STATUS_INACTIVE], true)) {
            $errors['status'] = ['Status must be "active" or "inactive"'];
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
    
    /**
     * Validate Material Master items
     * 
     * @param array $items Items to validate
     * @return array Validation result with 'valid' and 'errors'
     */
    private function validateItems(array $items): array {
        $errors = [];
        $productIds = [];
        
        foreach ($items as $index => $item) {
            $itemErrors = [];
            
            // Validate product_id
            if (!isset($item['product_id']) || !is_numeric($item['product_id']) || $item['product_id'] <= 0) {
                $itemErrors[] = 'Product ID is required and must be a positive integer';
            } else {
                // Check for duplicate product_id
                if (in_array($item['product_id'], $productIds)) {
                    $itemErrors[] = 'Duplicate product ID';
                }
                $productIds[] = $item['product_id'];
            }
            
            // Validate quantity
            if (!isset($item['quantity']) || !is_numeric($item['quantity']) || $item['quantity'] <= 0) {
                $itemErrors[] = 'Quantity is required and must be a positive integer';
            }
            
            if (!empty($itemErrors)) {
                $errors["item_$index"] = $itemErrors;
            }
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
    
    /**
     * Log action for audit trail
     * 
     * @param int|null $userId User performing the action
     * @param int $masterId Material Master ID
     * @param string $action Action type
     * @param array $details Additional details
     */
    private function logAction(?int $userId, int $masterId, string $action, array $details): void {
        try {
            $sql = "INSERT INTO user_audit_log (user_id, action, details, performed_by, ip_address) 
                    VALUES (?, ?, ?, ?, ?)";
            
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'CLI';
            $details['material_master_id'] = $masterId;
            $details['entity_type'] = 'material_master';
            
            $stmt = $this->db->executeQuery($sql, [
                $userId ?? 0,
                $action,
                json_encode($details),
                $userId ?? 0,
                $ipAddress
            ], 'issis');
            $stmt->close();
        } catch (Exception $e) {
            error_log("Failed to log material master action: " . $e->getMessage());
        }
    }
}
