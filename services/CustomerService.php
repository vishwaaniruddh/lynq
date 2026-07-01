<?php
/**
 * Customer Service
 * Handles business logic for customer master module operations
 * 
 * Requirements: 2.2, 2.3, 2.4, 2.5, 9.1, 9.2, 9.4
 * - 2.2: Create new customer records with email uniqueness validation
 * - 2.3: Update existing customer records with audit trail
 * - 2.4: Soft delete customer records
 * - 2.5: View customer details
 * - 9.1: Required field validation
 * - 9.2: Uniqueness validation
 * - 9.4: Audit field maintenance
 */

require_once __DIR__ . '/../config/autoload.php';
require_once __DIR__ . '/../repositories/CustomerRepository.php';

class CustomerService {
    private $db;
    private $customerRepository;
    
    public function __construct() {
        $this->db = DatabaseConfig::getInstance();
        $this->customerRepository = new CustomerRepository();
    }
    
    /**
     * Get all customers with filters
     * 
     * @param array $filters Optional filters: search, status, page, limit, orderBy, orderDir
     * @return array Paginated result with data, total, page, limit, totalPages
     * 
     * Requirements: 2.1
     */
    public function getAll(array $filters = []): array {
        return $this->customerRepository->findAllWithFilters($filters);
    }
    
    /**
     * Get customer by ID
     * 
     * @param int $id Customer ID
     * @return array|null Customer record or null if not found
     * 
     * Requirements: 2.5
     */
    public function getById(int $id): ?array {
        return $this->customerRepository->findById($id);
    }
    
    /**
     * Create a new customer record
     * 
     * @param array $data Customer data: name, email, phone, address, city, state, country, postal_code, status
     * @param int|null $userId User ID performing the action (for audit)
     * @return array Result with success status and data/errors
     * 
     * Requirements: 2.2, 9.1, 9.2
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
        
        // Check email uniqueness
        if (!$this->checkEmailUniqueness(trim($data['email']))) {
            return [
                'success' => false,
                'message' => 'A customer with this email already exists',
                'errors' => ['email' => ['Email must be unique']],
                'code' => 'DUPLICATE_ERROR'
            ];
        }
        
        try {
            // Prepare data
            $customerData = [
                'name' => trim($data['name']),
                'email' => trim($data['email']),
                'status' => isset($data['status']) ? (int)$data['status'] : 1
            ];
            
            // Optional fields
            if (isset($data['phone']) && trim($data['phone']) !== '') {
                $customerData['phone'] = trim($data['phone']);
            }
            if (isset($data['address']) && trim($data['address']) !== '') {
                $customerData['address'] = trim($data['address']);
            }
            if (isset($data['city']) && trim($data['city']) !== '') {
                $customerData['city'] = trim($data['city']);
            }
            if (isset($data['state']) && trim($data['state']) !== '') {
                $customerData['state'] = trim($data['state']);
            }
            if (isset($data['country']) && trim($data['country']) !== '') {
                $customerData['country'] = trim($data['country']);
            }
            if (isset($data['postal_code']) && trim($data['postal_code']) !== '') {
                $customerData['postal_code'] = trim($data['postal_code']);
            }
            // Location IDs
            if (isset($data['country_id']) && $data['country_id']) {
                $customerData['country_id'] = (int)$data['country_id'];
            }
            if (isset($data['state_id']) && $data['state_id']) {
                $customerData['state_id'] = (int)$data['state_id'];
            }
            if (isset($data['city_id']) && $data['city_id']) {
                $customerData['city_id'] = (int)$data['city_id'];
            }
            
            if ($userId !== null) {
                $customerData['created_by'] = $userId;
            }
            
            // Create customer
            $customerId = $this->customerRepository->createCustomer($customerData);
            
            // Log audit
            $this->logAction($userId, $customerId, 'customer_created', [
                'name' => $customerData['name'],
                'email' => $customerData['email']
            ]);
            
            // Return created customer
            $customer = $this->customerRepository->findById($customerId);
            
            return [
                'success' => true,
                'message' => 'Customer created successfully',
                'data' => $customer
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to create customer: ' . $e->getMessage(),
                'code' => 'CREATE_ERROR'
            ];
        }
    }
    
    /**
     * Update an existing customer record
     * 
     * @param int $id Customer ID
     * @param array $data Data to update
     * @param int|null $userId User ID performing the action (for audit)
     * @return array Result with success status and data/errors
     * 
     * Requirements: 2.3, 9.1, 9.2, 9.4
     */
    public function update(int $id, array $data, ?int $userId = null): array {
        // Check if customer exists
        $existing = $this->customerRepository->findById($id);
        if (!$existing) {
            return [
                'success' => false,
                'message' => 'Customer not found',
                'code' => 'NOT_FOUND'
            ];
        }
        
        // Validate if name or email is being updated
        if (isset($data['name']) || isset($data['email'])) {
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
        
        // Check email uniqueness if email is being updated
        if (isset($data['email']) && trim($data['email']) !== $existing['email']) {
            if (!$this->checkEmailUniqueness(trim($data['email']), $id)) {
                return [
                    'success' => false,
                    'message' => 'A customer with this email already exists',
                    'errors' => ['email' => ['Email must be unique']],
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
            if (isset($data['email'])) {
                $updateData['email'] = trim($data['email']);
            }
            if (array_key_exists('phone', $data)) {
                $updateData['phone'] = $data['phone'];
            }
            if (array_key_exists('address', $data)) {
                $updateData['address'] = $data['address'];
            }
            if (array_key_exists('city', $data)) {
                $updateData['city'] = $data['city'];
            }
            if (array_key_exists('state', $data)) {
                $updateData['state'] = $data['state'];
            }
            if (array_key_exists('country', $data)) {
                $updateData['country'] = $data['country'];
            }
            if (array_key_exists('postal_code', $data)) {
                $updateData['postal_code'] = $data['postal_code'];
            }
            if (isset($data['status'])) {
                $updateData['status'] = (int)$data['status'];
            }
            // Location IDs
            if (array_key_exists('country_id', $data)) {
                $updateData['country_id'] = $data['country_id'] ? (int)$data['country_id'] : null;
            }
            if (array_key_exists('state_id', $data)) {
                $updateData['state_id'] = $data['state_id'] ? (int)$data['state_id'] : null;
            }
            if (array_key_exists('city_id', $data)) {
                $updateData['city_id'] = $data['city_id'] ? (int)$data['city_id'] : null;
            }
            
            // Audit field maintenance - Requirements: 9.4
            if ($userId !== null) {
                $updateData['updated_by'] = $userId;
            }
            
            // Update customer
            $this->customerRepository->updateCustomer($id, $updateData);
            
            // Log audit
            $this->logAction($userId, $id, 'customer_updated', [
                'changes' => array_keys($updateData),
                'old_email' => $existing['email'],
                'new_email' => $updateData['email'] ?? $existing['email']
            ]);
            
            // Return updated customer
            $customer = $this->customerRepository->findById($id);
            
            return [
                'success' => true,
                'message' => 'Customer updated successfully',
                'data' => $customer
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to update customer: ' . $e->getMessage(),
                'code' => 'UPDATE_ERROR'
            ];
        }
    }
    
    /**
     * Soft delete a customer record (set status to inactive)
     * 
     * @param int $id Customer ID
     * @param int|null $userId User ID performing the action (for audit)
     * @return array Result with success status
     * 
     * Requirements: 2.4
     */
    public function delete(int $id, ?int $userId = null): array {
        // Check if customer exists
        $existing = $this->customerRepository->findById($id);
        if (!$existing) {
            return [
                'success' => false,
                'message' => 'Customer not found',
                'code' => 'NOT_FOUND'
            ];
        }
        
        try {
            // Soft delete
            $this->customerRepository->softDelete($id, $userId);
            
            // Log audit
            $this->logAction($userId, $id, 'customer_deleted', [
                'name' => $existing['name'],
                'email' => $existing['email']
            ]);
            
            return [
                'success' => true,
                'message' => 'Customer deleted successfully'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to delete customer: ' . $e->getMessage(),
                'code' => 'DELETE_ERROR'
            ];
        }
    }
    
    /**
     * Export customers with current filters
     * 
     * @param array $filters Optional filters: search, status
     * @return array Array of customer records for export
     */
    public function export(array $filters = []): array {
        return $this->customerRepository->findAllForExport($filters);
    }
    
    /**
     * Get all active customers (for dropdowns)
     * 
     * @return array Array of active customer records
     */
    public function getActiveList(): array {
        return $this->customerRepository->findAllActive();
    }
    
    /**
     * Check if email is unique
     * 
     * @param string $email Email to check
     * @param int|null $excludeId ID to exclude (for updates)
     * @return bool True if email is unique (doesn't exist)
     * 
     * Requirements: 9.2
     */
    public function checkEmailUniqueness(string $email, ?int $excludeId = null): bool {
        return !$this->customerRepository->emailExists($email, $excludeId);
    }
    
    /**
     * Validate customer data
     * 
     * @param array $data Data to validate
     * @param int|null $id Customer ID (for updates, to exclude from uniqueness check)
     * @return array Validation result with 'valid' and 'errors'
     * 
     * Requirements: 9.1
     */
    private function validate(array $data, ?int $id = null): array {
        $errors = [];
        
        // Required field: name (only validate if provided or creating new)
        if ($id === null || isset($data['name'])) {
            if (!isset($data['name']) || trim($data['name']) === '') {
                $errors['name'] = ['Customer name is required'];
            } elseif (strlen(trim($data['name'])) > 255) {
                $errors['name'] = ['Customer name must not exceed 255 characters'];
            }
        }
        
        // Required field: email (only validate if provided or creating new)
        if ($id === null || isset($data['email'])) {
            if (!isset($data['email']) || trim($data['email']) === '') {
                $errors['email'] = ['Customer email is required'];
            } elseif (!filter_var(trim($data['email']), FILTER_VALIDATE_EMAIL)) {
                $errors['email'] = ['Invalid email format'];
            } elseif (strlen(trim($data['email'])) > 255) {
                $errors['email'] = ['Email must not exceed 255 characters'];
            }
        }
        
        // Optional field validations
        if (isset($data['phone']) && strlen(trim($data['phone'])) > 50) {
            $errors['phone'] = ['Phone must not exceed 50 characters'];
        }
        
        if (isset($data['city']) && strlen(trim($data['city'])) > 100) {
            $errors['city'] = ['City must not exceed 100 characters'];
        }
        
        if (isset($data['state']) && strlen(trim($data['state'])) > 100) {
            $errors['state'] = ['State must not exceed 100 characters'];
        }
        
        if (isset($data['country']) && strlen(trim($data['country'])) > 100) {
            $errors['country'] = ['Country must not exceed 100 characters'];
        }
        
        if (isset($data['postal_code']) && strlen(trim($data['postal_code'])) > 20) {
            $errors['postal_code'] = ['Postal code must not exceed 20 characters'];
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
     * @param int $customerId Customer ID
     * @param string $action Action type
     * @param array $details Additional details
     * 
     * Requirements: 9.4
     */
    private function logAction(?int $userId, int $customerId, string $action, array $details): void {
        try {
            $sql = "INSERT INTO user_audit_log (user_id, action, details, performed_by, ip_address) 
                    VALUES (?, ?, ?, ?, ?)";
            
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'CLI';
            $details['customer_id'] = $customerId;
            $details['entity_type'] = 'customer';
            
            $stmt = $this->db->executeQuery($sql, [
                $userId ?? 0,
                $action,
                json_encode($details),
                $userId ?? 0,
                $ipAddress
            ], 'issis');
            $stmt->close();
        } catch (Exception $e) {
            error_log("Failed to log customer action: " . $e->getMessage());
        }
    }
}
