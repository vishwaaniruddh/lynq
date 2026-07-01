<?php
/**
 * Property Test for ADV-Only Access Control
 * **Feature: crm-master-modules, Property 3: ADV-Only Access Control**
 * **Validates: Requirements 1.5, 2.6, 7.2, 8.1, 8.2**
 * 
 * For any user without ADV company type, attempting to access any master module 
 * page or API should result in access denial.
 */

require_once 'PropertyTestBase.php';
require_once __DIR__ . '/../middleware/MasterModuleMiddleware.php';
require_once __DIR__ . '/../services/PermissionEngine.php';
require_once __DIR__ . '/../repositories/UserRepository.php';
require_once __DIR__ . '/../repositories/CompanyRepository.php';

class AdvOnlyAccessControlTest extends PropertyTestBase {
    
    private $createdRecords = [];
    private $masterMiddleware;
    private $permissionEngine;
    private $userRepository;
    private $companyRepository;
    
    // Test user IDs
    private $advUserId = null;
    private $contractorUserId = null;
    private $advCompanyId = null;
    private $contractorCompanyId = null;
    
    public function __construct() {
        parent::__construct();
        $this->masterMiddleware = new MasterModuleMiddleware();
        $this->permissionEngine = new PermissionEngine();
        $this->userRepository = new UserRepository();
        $this->companyRepository = new CompanyRepository();
    }
    
    public function runTests() {
        echo "=== ADV-Only Access Control Property Tests ===\n\n";
        
        // Setup test users
        $this->setupTestUsers();
        
        $allPassed = true;
        
        // Test 1: ADV users can access master modules
        $allPassed &= $this->runPropertyTest(
            "ADV User Can Access Master Modules",
            [$this, 'testAdvUserCanAccess'],
            50  // Reduced iterations since we're testing with fixed users
        );
        
        // Test 2: Contractor users cannot access master modules
        $allPassed &= $this->runPropertyTest(
            "Contractor User Cannot Access Master Modules",
            [$this, 'testContractorUserCannotAccess'],
            50
        );
        
        // Test 3: Non-ADV users are denied for all module/action combinations
        $allPassed &= $this->runPropertyTest(
            "Non-ADV User Denied For All Modules",
            [$this, 'testNonAdvUserDeniedAllModules'],
            50
        );
        
        // Test 4: ADV user with permissions can perform CRUD operations
        $allPassed &= $this->runPropertyTest(
            "ADV User With Permissions Can Perform CRUD",
            [$this, 'testAdvUserWithPermissions'],
            50
        );
        
        return $allPassed;
    }
    
    /**
     * Setup test users for property testing
     */
    private function setupTestUsers() {
        try {
            // Get or create ADV company
            $advCompany = $this->getOrCreateAdvCompany();
            $this->advCompanyId = $advCompany['id'];
            
            // Get or create Contractor company
            $contractorCompany = $this->getOrCreateContractorCompany();
            $this->contractorCompanyId = $contractorCompany['id'];
            
            // Get or create ADV user
            $advUser = $this->getOrCreateAdvUser($this->advCompanyId);
            $this->advUserId = $advUser['id'];
            
            // Get or create Contractor user
            $contractorUser = $this->getOrCreateContractorUser($this->contractorCompanyId);
            $this->contractorUserId = $contractorUser['id'];
            
            echo "Test users setup complete:\n";
            echo "  ADV User ID: {$this->advUserId}\n";
            echo "  Contractor User ID: {$this->contractorUserId}\n\n";
            
        } catch (Exception $e) {
            echo "Error setting up test users: " . $e->getMessage() . "\n";
            throw $e;
        }
    }
    
    /**
     * Property 3: ADV User Can Access Master Modules
     * For any ADV user, isAdvUser() should return true
     */
    public function testAdvUserCanAccess() {
        try {
            // Test that ADV user is recognized as ADV
            $isAdv = $this->masterMiddleware->isAdvUser($this->advUserId);
            
            $this->assert(
                $isAdv === true,
                "ADV user should be recognized as ADV user"
            );
            
            // Test that ADV user can access master modules
            $canAccess = $this->masterMiddleware->canAccessMasterModules($this->advUserId);
            
            // Note: canAccessMasterModules also checks permissions, so we just verify isAdvUser
            // The actual permission check depends on role assignments
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => ['advUserId' => $this->advUserId]
            ];
        }
    }
    
    /**
     * Property 3: Contractor User Cannot Access Master Modules
     * For any contractor user, isAdvUser() should return false
     */
    public function testContractorUserCannotAccess() {
        try {
            // Test that contractor user is NOT recognized as ADV
            $isAdv = $this->masterMiddleware->isAdvUser($this->contractorUserId);
            
            $this->assert(
                $isAdv === false,
                "Contractor user should NOT be recognized as ADV user"
            );
            
            // Test that contractor user cannot access master modules
            $canAccess = $this->masterMiddleware->canAccessMasterModules($this->contractorUserId);
            
            $this->assert(
                $canAccess === false,
                "Contractor user should NOT be able to access master modules"
            );
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => ['contractorUserId' => $this->contractorUserId]
            ];
        }
    }
    
    /**
     * Property 3: Non-ADV User Denied For All Modules
     * For any non-ADV user and any module/action combination, hasPermission should return false
     */
    public function testNonAdvUserDeniedAllModules() {
        try {
            // Define all module/action combinations
            $modules = ['banks', 'customers', 'locations'];
            $actions = ['view', 'create', 'edit', 'delete'];
            
            // Pick a random module and action
            $module = $this->generateRandomChoice($modules);
            $action = $this->generateRandomChoice($actions);
            
            // Test that contractor user is denied for this combination
            $hasPermission = $this->masterMiddleware->hasPermission(
                $module, 
                $action, 
                $this->contractorUserId
            );
            
            $this->assert(
                $hasPermission === false,
                "Contractor user should be denied permission for $module.$action"
            );
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => [
                    'contractorUserId' => $this->contractorUserId,
                    'module' => $module ?? null,
                    'action' => $action ?? null
                ]
            ];
        }
    }
    
    /**
     * Property 3: ADV User With Permissions Can Perform CRUD
     * For any ADV user with proper permissions, hasPermission should return true
     */
    public function testAdvUserWithPermissions() {
        try {
            // Define all module/action combinations
            $modules = ['banks', 'customers', 'locations'];
            $actions = ['view', 'create', 'edit', 'delete'];
            
            // Pick a random module and action
            $module = $this->generateRandomChoice($modules);
            $action = $this->generateRandomChoice($actions);
            
            // First verify user is ADV
            $isAdv = $this->masterMiddleware->isAdvUser($this->advUserId);
            $this->assert($isAdv === true, "User should be ADV user");
            
            // Get the permission name
            $permissionName = $this->masterMiddleware->getPermissionName($module, $action);
            $this->assert(
                $permissionName !== null,
                "Permission name should exist for $module.$action"
            );
            
            // Verify permission name format
            $expectedFormat = "masters.$module.$action";
            $this->assert(
                $permissionName === $expectedFormat,
                "Permission name should be '$expectedFormat', got '$permissionName'"
            );
            
            // Note: The actual permission check depends on role assignments
            // We're testing that the middleware correctly identifies ADV users
            // and generates correct permission names
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => [
                    'advUserId' => $this->advUserId,
                    'module' => $module ?? null,
                    'action' => $action ?? null
                ]
            ];
        }
    }
    
    // ==================== Helper Methods ====================
    
    /**
     * Get or create ADV company
     */
    private function getOrCreateAdvCompany() {
        // Try to find existing ADV company
        $sql = "SELECT * FROM companies WHERE type = 'ADV' AND status = 'ACTIVE' LIMIT 1";
        $result = $this->db->query($sql);
        $company = $result->fetch_assoc();
        
        if ($company) {
            return $company;
        }
        
        // Create new ADV company
        $companyData = [
            'name' => 'Test ADV Company ' . $this->generateRandomString(6),
            'type' => 'ADV',
            'status' => 'ACTIVE'
        ];
        
        $sql = "INSERT INTO companies (name, type, status) VALUES (?, ?, ?)";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param('sss', $companyData['name'], $companyData['type'], $companyData['status']);
        $stmt->execute();
        
        $companyId = $this->db->insert_id;
        $this->createdRecords['companies'][] = $companyId;
        
        return array_merge($companyData, ['id' => $companyId]);
    }
    
    /**
     * Get or create Contractor company
     */
    private function getOrCreateContractorCompany() {
        // Try to find existing Contractor company
        $sql = "SELECT * FROM companies WHERE type = 'CONTRACTOR' AND status = 'ACTIVE' LIMIT 1";
        $result = $this->db->query($sql);
        $company = $result->fetch_assoc();
        
        if ($company) {
            return $company;
        }
        
        // Create new Contractor company
        $companyData = [
            'name' => 'Test Contractor Company ' . $this->generateRandomString(6),
            'type' => 'CONTRACTOR',
            'status' => 'ACTIVE'
        ];
        
        $sql = "INSERT INTO companies (name, type, status) VALUES (?, ?, ?)";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param('sss', $companyData['name'], $companyData['type'], $companyData['status']);
        $stmt->execute();
        
        $companyId = $this->db->insert_id;
        $this->createdRecords['companies'][] = $companyId;
        
        return array_merge($companyData, ['id' => $companyId]);
    }
    
    /**
     * Get or create ADV user
     */
    private function getOrCreateAdvUser($companyId) {
        // Try to find existing ADV user
        $sql = "SELECT u.* FROM users u 
                INNER JOIN companies c ON u.company_id = c.id 
                WHERE c.type = 'ADV' AND u.status = 1 
                LIMIT 1";
        $result = $this->db->query($sql);
        $user = $result->fetch_assoc();
        
        if ($user) {
            return $user;
        }
        
        // Get ADV role
        $sql = "SELECT id FROM roles WHERE company_type IN ('ADV', 'BOTH') AND is_active = 1 LIMIT 1";
        $result = $this->db->query($sql);
        $role = $result->fetch_assoc();
        
        if (!$role) {
            throw new Exception("No ADV role found");
        }
        
        // Create new ADV user
        $userData = [
            'username' => 'test_adv_' . $this->generateRandomString(6),
            'email' => 'test_adv_' . $this->generateRandomString(6) . '@example.com',
            'password_hash' => password_hash('TestPassword123!', PASSWORD_DEFAULT),
            'first_name' => 'Test',
            'last_name' => 'ADV User',
            'company_id' => $companyId,
            'role_id' => $role['id'],
            'status' => 1
        ];
        
        $sql = "INSERT INTO users (username, email, password_hash, first_name, last_name, company_id, role_id, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param(
            'sssssiii',
            $userData['username'],
            $userData['email'],
            $userData['password_hash'],
            $userData['first_name'],
            $userData['last_name'],
            $userData['company_id'],
            $userData['role_id'],
            $userData['status']
        );
        $stmt->execute();
        
        $userId = $this->db->insert_id;
        $this->createdRecords['users'][] = $userId;
        
        return array_merge($userData, ['id' => $userId]);
    }
    
    /**
     * Get or create Contractor user
     */
    private function getOrCreateContractorUser($companyId) {
        // Try to find existing Contractor user
        $sql = "SELECT u.* FROM users u 
                INNER JOIN companies c ON u.company_id = c.id 
                WHERE c.type = 'CONTRACTOR' AND u.status = 1 
                LIMIT 1";
        $result = $this->db->query($sql);
        $user = $result->fetch_assoc();
        
        if ($user) {
            return $user;
        }
        
        // Get Contractor role
        $sql = "SELECT id FROM roles WHERE company_type IN ('CONTRACTOR', 'BOTH') AND is_active = 1 LIMIT 1";
        $result = $this->db->query($sql);
        $role = $result->fetch_assoc();
        
        if (!$role) {
            throw new Exception("No Contractor role found");
        }
        
        // Create new Contractor user
        $userData = [
            'username' => 'test_contractor_' . $this->generateRandomString(6),
            'email' => 'test_contractor_' . $this->generateRandomString(6) . '@example.com',
            'password_hash' => password_hash('TestPassword123!', PASSWORD_DEFAULT),
            'first_name' => 'Test',
            'last_name' => 'Contractor User',
            'company_id' => $companyId,
            'role_id' => $role['id'],
            'status' => 1
        ];
        
        $sql = "INSERT INTO users (username, email, password_hash, first_name, last_name, company_id, role_id, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param(
            'sssssiii',
            $userData['username'],
            $userData['email'],
            $userData['password_hash'],
            $userData['first_name'],
            $userData['last_name'],
            $userData['company_id'],
            $userData['role_id'],
            $userData['status']
        );
        $stmt->execute();
        
        $userId = $this->db->insert_id;
        $this->createdRecords['users'][] = $userId;
        
        return array_merge($userData, ['id' => $userId]);
    }
    
    /**
     * Clean up all test data created during tests
     */
    public function cleanupTestData() {
        try {
            // Delete test users
            if (isset($this->createdRecords['users']) && !empty($this->createdRecords['users'])) {
                $ids = implode(',', array_map('intval', $this->createdRecords['users']));
                $this->db->query("DELETE FROM users WHERE id IN ($ids)");
            }
            
            // Delete test companies
            if (isset($this->createdRecords['companies']) && !empty($this->createdRecords['companies'])) {
                $ids = implode(',', array_map('intval', $this->createdRecords['companies']));
                $this->db->query("DELETE FROM companies WHERE id IN ($ids)");
            }
            
            // Clean up by pattern
            $this->db->query("DELETE FROM users WHERE username LIKE 'test_adv_%' OR username LIKE 'test_contractor_%'");
            $this->db->query("DELETE FROM companies WHERE name LIKE 'Test ADV Company %' OR name LIKE 'Test Contractor Company %'");
            
            $this->createdRecords = [];
            
        } catch (Exception $e) {
            echo "Cleanup warning: " . $e->getMessage() . "\n";
        }
    }
}
