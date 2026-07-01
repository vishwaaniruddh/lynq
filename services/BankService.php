<?php
/**
 * Bank Service
 * Handles business logic for bank master module operations
 * 
 * Requirements: 1.2, 1.3, 1.4, 1.6, 9.1, 9.2
 * - 1.2: Create new bank records with validation
 * - 1.3: Update existing bank records
 * - 1.4: Soft delete bank records
 * - 1.6: Export bank data
 * - 9.1: Required field validation
 * - 9.2: Uniqueness validation
 */

require_once __DIR__ . '/../config/autoload.php';
require_once __DIR__ . '/../repositories/BankRepository.php';

class BankService {
    private $db;
    private $bankRepository;
    
    public function __construct() {
        $this->db = DatabaseConfig::getInstance();
        $this->bankRepository = new BankRepository();
    }
    
    /**
     * Get all banks with filters
     * 
     * @param array $filters Optional filters: search, status, page, limit, orderBy, orderDir
     * @return array Paginated result with data, total, page, limit, totalPages
     * 
     * Requirements: 1.1
     */
    public function getAll(array $filters = []): array {
        return $this->bankRepository->findAllWithFilters($filters);
    }
    
    /**
     * Get bank by ID
     * 
     * @param int $id Bank ID
     * @return array|null Bank record or null if not found
     */
    public function getById(int $id): ?array {
        return $this->bankRepository->findById($id);
    }
    
    /**
     * Create a new bank record
     * 
     * @param array $data Bank data: name, status (optional)
     * @param int|null $userId User ID performing the action (for audit)
     * @return array Result with success status and data/errors
     * 
     * Requirements: 1.2, 9.1, 9.2
     */
    public function create(array $data, ?int $userId = null): array {
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
        
        // Check uniqueness
        if ($this->bankRepository->nameExists(trim($data['name']))) {
            return [
                'success' => false,
                'message' => 'A bank with this name already exists',
                'errors' => ['name' => ['Bank name must be unique']],
                'code' => 'DUPLICATE_ERROR'
            ];
        }
        
        try {
            // Prepare data
            $bankData = [
                'name' => trim($data['name']),
                'status' => isset($data['status']) ? (int)$data['status'] : 1
            ];
            
            if ($userId !== null) {
                $bankData['created_by'] = $userId;
            }
            
            // Create bank
            $bankId = $this->bankRepository->createBank($bankData);
            
            // Log audit
            $this->logAction($userId, $bankId, 'bank_created', [
                'name' => $bankData['name'],
                'status' => $bankData['status']
            ]);
            
            // Return created bank
            $bank = $this->bankRepository->findById($bankId);
            
            return [
                'success' => true,
                'message' => 'Bank created successfully',
                'data' => $bank
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to create bank: ' . $e->getMessage(),
                'code' => 'CREATE_ERROR'
            ];
        }
    }
    
    /**
     * Update an existing bank record
     * 
     * @param int $id Bank ID
     * @param array $data Data to update: name, status
     * @param int|null $userId User ID performing the action (for audit)
     * @return array Result with success status and data/errors
     * 
     * Requirements: 1.3, 9.1, 9.2
     */
    public function update(int $id, array $data, ?int $userId = null): array {
        // Check if bank exists
        $existing = $this->bankRepository->findById($id);
        if (!$existing) {
            return [
                'success' => false,
                'message' => 'Bank not found',
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
            
            // Check uniqueness (excluding current record)
            if ($this->bankRepository->nameExists(trim($data['name']), $id)) {
                return [
                    'success' => false,
                    'message' => 'A bank with this name already exists',
                    'errors' => ['name' => ['Bank name must be unique']],
                    'code' => 'DUPLICATE_ERROR'
                ];
            }
        }
        
        try {
            // Prepare update data
            $updateData = [];
            
            if (isset($data['name'])) {
                $updateData['name'] = trim($data['name']);
            }
            
            if (isset($data['status'])) {
                $updateData['status'] = (int)$data['status'];
            }
            
            if ($userId !== null) {
                $updateData['updated_by'] = $userId;
            }
            
            // Update bank
            $this->bankRepository->updateBank($id, $updateData);
            
            // Log audit
            $this->logAction($userId, $id, 'bank_updated', [
                'changes' => array_keys($updateData),
                'old_name' => $existing['name'],
                'new_name' => $updateData['name'] ?? $existing['name']
            ]);
            
            // Return updated bank
            $bank = $this->bankRepository->findById($id);
            
            return [
                'success' => true,
                'message' => 'Bank updated successfully',
                'data' => $bank
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to update bank: ' . $e->getMessage(),
                'code' => 'UPDATE_ERROR'
            ];
        }
    }
    
    /**
     * Soft delete a bank record (set status to inactive)
     * 
     * @param int $id Bank ID
     * @param int|null $userId User ID performing the action (for audit)
     * @return array Result with success status
     * 
     * Requirements: 1.4
     */
    public function delete(int $id, ?int $userId = null): array {
        // Check if bank exists
        $existing = $this->bankRepository->findById($id);
        if (!$existing) {
            return [
                'success' => false,
                'message' => 'Bank not found',
                'code' => 'NOT_FOUND'
            ];
        }
        
        try {
            // Soft delete
            $this->bankRepository->softDelete($id, $userId);
            
            // Log audit
            $this->logAction($userId, $id, 'bank_deleted', [
                'name' => $existing['name']
            ]);
            
            return [
                'success' => true,
                'message' => 'Bank deleted successfully'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to delete bank: ' . $e->getMessage(),
                'code' => 'DELETE_ERROR'
            ];
        }
    }
    
    /**
     * Export banks with current filters
     * 
     * @param array $filters Optional filters: search, status
     * @return array Array of bank records for export
     * 
     * Requirements: 1.6
     */
    public function export(array $filters = []): array {
        return $this->bankRepository->findAllForExport($filters);
    }
    
    /**
     * Get all active banks (for dropdowns)
     * 
     * @return array Array of active bank records
     */
    public function getActiveList(): array {
        return $this->bankRepository->findAllActive();
    }
    
    /**
     * Check if bank name exists
     * 
     * @param string $name Bank name
     * @param int|null $excludeId ID to exclude (for updates)
     * @return bool True if name exists
     */
    public function nameExists(string $name, ?int $excludeId = null): bool {
        return $this->bankRepository->nameExists($name, $excludeId);
    }
    
    /**
     * Validate bank data
     * 
     * @param array $data Data to validate
     * @param int|null $id Bank ID (for updates, to exclude from uniqueness check)
     * @return array Validation result with 'valid' and 'errors'
     * 
     * Requirements: 9.1
     */
    private function validate(array $data, ?int $id = null): array {
        $errors = [];
        
        // Required field: name
        if (!isset($data['name']) || trim($data['name']) === '') {
            $errors['name'] = ['Bank name is required'];
        } elseif (strlen(trim($data['name'])) > 255) {
            $errors['name'] = ['Bank name must not exceed 255 characters'];
        }
        
        // Status validation (if provided)
        if (isset($data['status']) && !in_array((int)$data['status'], [0, 1], true)) {
            $errors['status'] = ['Status must be 0 (inactive) or 1 (active)'];
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
     * @param int $bankId Bank ID
     * @param string $action Action type
     * @param array $details Additional details
     */
    private function logAction(?int $userId, int $bankId, string $action, array $details): void {
        try {
            $sql = "INSERT INTO user_audit_log (user_id, action, details, performed_by, ip_address) 
                    VALUES (?, ?, ?, ?, ?)";
            
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'CLI';
            $details['bank_id'] = $bankId;
            $details['entity_type'] = 'bank';
            
            $stmt = $this->db->executeQuery($sql, [
                $userId ?? 0,
                $action,
                json_encode($details),
                $userId ?? 0,
                $ipAddress
            ], 'issis');
            $stmt->close();
        } catch (Exception $e) {
            error_log("Failed to log bank action: " . $e->getMessage());
        }
    }
}
