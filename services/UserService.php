<?php
/**
 * User Service
 * Handles business logic for user management operations
 * 
 * This service enforces:
 * - Company isolation for user operations
 * - Role assignment restrictions (ADV roles for ADV users, contractor roles for contractor users)
 * - Permission checks for all operations
 * - Audit logging for user changes
 */

require_once __DIR__ . '/../config/autoload.php';
require_once __DIR__ . '/EmailEventDispatcher.php';

class UserService {
    private $db;
    private $userModel;
    private $userRepository;
    private $companyModel;
    private $roleModel;
    private $companyIsolationService;
    private $permissionEngine;
    private $lhoManagerRepository;
    
    public function __construct() {
        $this->db = DatabaseConfig::getInstance();
        $this->userModel = new User();
        $this->userRepository = new UserRepository();
        $this->companyModel = new Company();
        $this->roleModel = new Role();
        $this->companyIsolationService = new CompanyIsolationService();
        $this->permissionEngine = new PermissionEngine();
        $this->lhoManagerRepository = new LhoManagerRepository();
    }
    
    /**
     * Create a new user with validation
     * 
     * @param array $userData User data to create
     * @param int $actingUserId ID of user performing the action
     * @return array Created user data
     * @throws Exception on validation failure
     */
    public function createUser(array $userData, int $actingUserId): array {
        // Validate required fields
        $this->validateRequiredFields($userData, ['username', 'email', 'password', 'company_id', 'role_id']);
        
        // Validate email format
        if (!filter_var($userData['email'], FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Invalid email format');
        }
        
        // Check username uniqueness
        if ($this->userModel->findByUsername($userData['username'])) {
            throw new InvalidArgumentException('Username already exists');
        }
        
        // Check email uniqueness
        if ($this->userModel->findByEmail($userData['email'])) {
            throw new InvalidArgumentException('Email already exists');
        }
        
        // Validate company access for acting user
        $this->validateCompanyAccessForUserCreation($actingUserId, $userData['company_id']);
        
        // Validate role assignment
        $this->validateRoleAssignment($userData['company_id'], $userData['role_id']);
        
        // Hash password
        $userData['password_hash'] = password_hash($userData['password'], PASSWORD_DEFAULT);
        unset($userData['password']);
        
        // Set default status if not provided
        if (!isset($userData['status'])) {
            $userData['status'] = 1;
        }
        
        // Create user
        $user = $this->userModel->create($userData);
        
        // Log audit
        $this->logUserAction($actingUserId, $user['id'], 'user_created', [
            'username' => $userData['username'],
            'email' => $userData['email'],
            'company_id' => $userData['company_id'],
            'role_id' => $userData['role_id']
        ]);
        
        // Dispatch email event for user creation
        $this->dispatchUserEvent('user_created', [
            'user_id' => $user['id'],
            'username' => $userData['username'],
            'email' => $userData['email'],
            'company_id' => $userData['company_id'],
            'role_id' => $userData['role_id']
        ]);
        
        return $user;
    }
    
    /**
     * Update an existing user
     * 
     * @param int $userId User ID to update
     * @param array $userData Updated user data
     * @param int $actingUserId ID of user performing the action
     * @return array Updated user data
     * @throws Exception on validation failure
     */
    public function updateUser(int $userId, array $userData, int $actingUserId): array {
        // Get existing user
        $existingUser = $this->userModel->find($userId);
        if (!$existingUser) {
            throw new InvalidArgumentException('User not found');
        }
        
        // Validate company access for acting user
        $this->companyIsolationService->validateCompanyAccess($actingUserId, $existingUser['company_id']);
        
        // If changing company, validate access to new company
        if (isset($userData['company_id']) && $userData['company_id'] != $existingUser['company_id']) {
            $this->validateCompanyAccessForUserCreation($actingUserId, $userData['company_id']);
        }
        
        // Validate email format if provided
        if (isset($userData['email']) && !filter_var($userData['email'], FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Invalid email format');
        }
        
        // Check username uniqueness if changed
        if (isset($userData['username']) && $userData['username'] !== $existingUser['username']) {
            $existingUsername = $this->userModel->findByUsername($userData['username']);
            if ($existingUsername && $existingUsername['id'] != $userId) {
                throw new InvalidArgumentException('Username already exists');
            }
        }
        
        // Check email uniqueness if changed
        if (isset($userData['email']) && $userData['email'] !== $existingUser['email']) {
            $existingEmail = $this->userModel->findByEmail($userData['email']);
            if ($existingEmail && $existingEmail['id'] != $userId) {
                throw new InvalidArgumentException('Email already exists');
            }
        }
        
        // Validate role assignment if role is being changed
        $targetCompanyId = $userData['company_id'] ?? $existingUser['company_id'];
        if (isset($userData['role_id'])) {
            $this->validateRoleAssignment($targetCompanyId, $userData['role_id']);
        }
        
        // Hash password if provided
        if (isset($userData['password']) && !empty($userData['password'])) {
            $userData['password_hash'] = password_hash($userData['password'], PASSWORD_DEFAULT);
            unset($userData['password']);
        } else {
            unset($userData['password']);
        }
        
        // Handle user deactivation cascade - remove LHO manager assignments
        // Requirements: 4.1 - When an ADV user is deactivated, remove all LHO-manager assignments
        if (isset($userData['status']) && (int)$userData['status'] === 0 && (int)$existingUser['status'] === 1) {
            $this->lhoManagerRepository->removeAllByUserId($userId);
        }
        
        // Update user
        $user = $this->userModel->update($userId, $userData);
        
        // Log audit
        $this->logUserAction($actingUserId, $userId, 'user_updated', [
            'changes' => array_keys($userData)
        ]);
        
        return $user;
    }
    
    /**
     * Delete a user
     * 
     * @param int $userId User ID to delete
     * @param int $actingUserId ID of user performing the action
     * @return bool Success status
     * @throws Exception on validation failure
     */
    public function deleteUser(int $userId, int $actingUserId): bool {
        // Get existing user
        $existingUser = $this->userModel->find($userId);
        if (!$existingUser) {
            throw new InvalidArgumentException('User not found');
        }
        
        // Cannot delete yourself
        if ($userId === $actingUserId) {
            throw new InvalidArgumentException('Cannot delete your own account');
        }
        
        // Validate company access for acting user
        $this->companyIsolationService->validateCompanyAccess($actingUserId, $existingUser['company_id']);
        
        // Store user info for audit log before deletion
        $deletedUserInfo = [
            'username' => $existingUser['username'],
            'email' => $existingUser['email'],
            'deleted_user_id' => $userId
        ];
        
        // Delete user first
        $result = $this->userModel->delete($userId);
        
        // Log audit after deletion (without target_user_id foreign key reference)
        if ($result) {
            $this->logUserActionWithoutTarget($actingUserId, 'user_deleted', $deletedUserInfo);
        }
        
        return $result;
    }

    
    /**
     * Get user by ID with company isolation
     * 
     * @param int $userId User ID
     * @param int $actingUserId ID of user performing the action
     * @return array|null User data or null if not found/not accessible
     */
    public function getUser(int $userId, int $actingUserId): ?array {
        $this->userRepository->setCurrentUser($actingUserId);
        return $this->userRepository->findWithRelations($userId);
    }
    
    /**
     * Get all users with company isolation
     * 
     * @param int $actingUserId ID of user performing the action
     * @return array List of users
     */
    public function getAllUsers(int $actingUserId): array {
        $this->userRepository->setCurrentUser($actingUserId);
        return $this->userRepository->findAllWithRelations();
    }
    
    /**
     * Get users by company
     * 
     * @param int $companyId Company ID
     * @param int $actingUserId ID of user performing the action
     * @return array List of users
     */
    public function getUsersByCompany(int $companyId, int $actingUserId): array {
        // Validate company access
        $this->companyIsolationService->validateCompanyAccess($actingUserId, $companyId);
        
        $this->userRepository->setCurrentUser($actingUserId);
        return $this->userRepository->findByCompanyWithRelations($companyId);
    }
    
    /**
     * Search users with company isolation
     * 
     * @param string $searchTerm Search term
     * @param int $actingUserId ID of user performing the action
     * @return array List of matching users
     */
    public function searchUsers(string $searchTerm, int $actingUserId): array {
        $this->userRepository->setCurrentUser($actingUserId);
        return $this->userRepository->search($searchTerm);
    }
    
    /**
     * Validate company access for user creation
     * ADV users can create users in any company
     * Contractor users can only create users in their own company
     * 
     * @param int $actingUserId ID of user performing the action
     * @param int $targetCompanyId Target company ID
     * @throws CompanyAccessDeniedException if access denied
     */
    private function validateCompanyAccessForUserCreation(int $actingUserId, int $targetCompanyId): void {
        $actingUser = $this->userModel->findWithRelations($actingUserId);
        
        if (!$actingUser) {
            throw new InvalidArgumentException('Acting user not found');
        }
        
        // ADV users can create users in any company
        if ($actingUser['company_type'] === 'ADV') {
            // Verify target company exists
            $targetCompany = $this->companyModel->find($targetCompanyId);
            if (!$targetCompany) {
                throw new InvalidArgumentException('Target company not found');
            }
            return;
        }
        
        // Contractor users can only create users in their own company
        if ((int)$actingUser['company_id'] !== (int)$targetCompanyId) {
            throw new CompanyAccessDeniedException(
                'Contractor users can only create users in their own company'
            );
        }
    }
    
    /**
     * Validate role assignment
     * ADV roles can only be assigned to users in ADV companies
     * Contractor roles can only be assigned to users in contractor companies
     * 
     * @param int $companyId Company ID
     * @param int $roleId Role ID
     * @throws InvalidArgumentException if role assignment is invalid
     */
    private function validateRoleAssignment(int $companyId, int $roleId): void {
        $company = $this->companyModel->find($companyId);
        if (!$company) {
            throw new InvalidArgumentException('Company not found');
        }
        
        $role = $this->roleModel->find($roleId);
        if (!$role) {
            throw new InvalidArgumentException('Role not found');
        }
        
        // Role type must match company type OR role type must be 'BOTH'
        if ($role['company_type'] !== 'BOTH' && $role['company_type'] !== $company['type']) {
            if ($role['company_type'] === 'ADV' && $company['type'] === 'CONTRACTOR') {
                throw new InvalidArgumentException('ADV roles cannot be assigned to contractor users');
            }
            if ($role['company_type'] === 'CONTRACTOR' && $company['type'] === 'ADV') {
                throw new InvalidArgumentException('Contractor roles cannot be assigned to ADV users');
            }
        }
    }
    
    /**
     * Validate required fields
     * 
     * @param array $data Data to validate
     * @param array $requiredFields List of required field names
     * @throws InvalidArgumentException if required field is missing
     */
    private function validateRequiredFields(array $data, array $requiredFields): void {
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || (is_string($data[$field]) && trim($data[$field]) === '')) {
                throw new InvalidArgumentException("$field is required");
            }
        }
    }
    
    /**
     * Log user action for audit trail
     * 
     * @param int $actingUserId User performing the action
     * @param int $targetUserId User being acted upon
     * @param string $action Action type
     * @param array $details Additional details
     */
    private function logUserAction(int $actingUserId, int $targetUserId, string $action, array $details): void {
        try {
            $sql = "INSERT INTO user_audit_log (user_id, target_user_id, action, details, performed_by, ip_address) 
                    VALUES (?, ?, ?, ?, ?, ?)";
            
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'CLI';
            
            $stmt = $this->db->executeQuery($sql, [
                $actingUserId,
                $targetUserId,
                $action,
                json_encode($details),
                $actingUserId,
                $ipAddress
            ], 'iissis');
            $stmt->close();
        } catch (Exception $e) {
            error_log("Failed to log user action: " . $e->getMessage());
        }
    }
    
    /**
     * Log user action without target user reference (for delete operations)
     * 
     * @param int $actingUserId User performing the action
     * @param string $action Action type
     * @param array $details Additional details (should include deleted_user_id)
     */
    private function logUserActionWithoutTarget(int $actingUserId, string $action, array $details): void {
        try {
            $sql = "INSERT INTO user_audit_log (user_id, target_user_id, action, details, performed_by, ip_address) 
                    VALUES (?, NULL, ?, ?, ?, ?)";
            
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'CLI';
            
            $stmt = $this->db->executeQuery($sql, [
                $actingUserId,
                $action,
                json_encode($details),
                $actingUserId,
                $ipAddress
            ], 'issis');
            $stmt->close();
        } catch (Exception $e) {
            error_log("Failed to log user action: " . $e->getMessage());
        }
    }
    
    /**
     * Check if ADV user can assign users to any company
     * This is used for property testing
     * 
     * @param int $advUserId ADV user ID
     * @param int $targetCompanyId Target company ID
     * @return bool True if ADV user can assign to this company
     */
    public function canAdvUserAssignToCompany(int $advUserId, int $targetCompanyId): bool {
        $advUser = $this->userModel->findWithRelations($advUserId);
        
        if (!$advUser || $advUser['company_type'] !== 'ADV') {
            return false;
        }
        
        // ADV users can assign to any existing company
        $targetCompany = $this->companyModel->find($targetCompanyId);
        return $targetCompany !== null;
    }
    
    /**
     * Get available companies for user creation
     * ADV users see all companies, contractors see only their own
     * 
     * @param int $actingUserId ID of user performing the action
     * @return array List of available companies
     */
    public function getAvailableCompaniesForUserCreation(int $actingUserId): array {
        return $this->companyIsolationService->getAccessibleCompanies($actingUserId);
    }
    
    /**
     * Get available roles for user creation based on target company type
     * 
     * @param int $companyId Target company ID
     * @return array List of available roles
     */
    public function getAvailableRolesForCompany(int $companyId): array {
        $company = $this->companyModel->find($companyId);
        if (!$company) {
            return [];
        }
        
        return $this->roleModel->findByCompanyType($company['type']);
    }
    
    /**
     * Dispatch user event to email system
     * 
     * @param string $eventType Event type
     * @param array $eventData Event data
     */
    private function dispatchUserEvent(string $eventType, array $eventData): void {
        try {
            EmailEventDispatcher::dispatchUserEvent($eventType, $eventData);
        } catch (Exception $e) {
            error_log("Failed to dispatch user email event: " . $e->getMessage());
        }
    }
}
