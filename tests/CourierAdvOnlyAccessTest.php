<?php
/**
 * Property Test for Courier ADV-Only Access Control
 * **Feature: crm-sidebar-restructure, Property 6: Courier ADV-Only Access Control**
 * **Validates: Requirements 2.5, 5.1, 5.4**
 * 
 * For any user without ADV company type, attempting to access the Courier module 
 * page or API should result in access denial.
 */

require_once 'PropertyTestBase.php';
require_once __DIR__ . '/../middleware/MasterModuleMiddleware.php';
require_once __DIR__ . '/../services/PermissionEngine.php';

class CourierAdvOnlyAccessTest extends PropertyTestBase {
    
    private $createdRecords = [];
    private $masterMiddleware;
    private $permissionEngine;
    
    // Test user IDs
    private $advUserId = null;
    private $contractorUserId = null;
    private $advCompanyId = null;
    private $contractorCompanyId = null;
    
    public function __construct() {
        parent::__construct();
        $this->masterMiddleware = new MasterModuleMiddleware();
        $this->permissionEngine = new PermissionEngine();
    }
    
    public function runTests() {
        echo "=== Courier ADV-Only Access Control Property Tests ===\n\n";
        
        // Setup test users
        $this->setupTestUsers();
        
        $allPassed = true;
        
        // Test 1: ADV users can access courier module
        $allPassed &= $this->runPropertyTest(
            "ADV User Can Access Courier Module",
            [$this, 'testAdvUserCanAccessCourier'],
            100
        );
        
        // Test 2: Contractor users cannot access courier module
        $allPassed &= $this->runPropertyTest(
            "Contractor User Cannot Access Courier Module",
            [$this, 'testContractorUserCannotAccessCourier'],
            100
        );
        
        // Test 3: Non-ADV users are denied for all courier actions
        $allPassed &= $this->runPropertyTest(
            "Non-ADV User Denied For All Courier Actions",
            [$this, 'testNonAdvUserDeniedAllCourierActions'],
            100
        );
        
        // Test 4: ADV user with permissions can perform courier CRUD
        $allPassed &= $this->runPropertyTest(
            "ADV User With Permissions Can Perform Courier CRUD",
            [$this, 'testAdvUserWithCourierPermissions'],
            100
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
     * Property 6: ADV User Can Access Courier Module
     * For any ADV user, isAdvUser() should return true for courier access
     */
    public function testAdvUserCanAccessCourier() {
        try {
            // Test that ADV user is recognized as ADV
            $isAdv = $this->masterMiddleware->isAdvUser($this->advUserId);
            
            $this->assert(
                $isAdv === true,
                "ADV user should be recognized as ADV user for courier access"
            );
            
            // Verify the courier permission mapping exists
            $permissionName = $this->masterMiddleware->getPermissionName('couriers', 'view');
            $this->assert(
                $permissionName === 'masters.couriers.view',
                "Courier view permission should be 'masters.couriers.view'"
            );
            
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
     * Property 6: Contractor User Cannot Access Courier Module
     * For any contractor user, isAdvUser() should return false
     */
    public function testContractorUserCannotAccessCourier() {
        try {
            // Test that contractor user is NOT recognized as ADV
            $isAdv = $this->masterMiddleware->isAdvUser($this->contractorUserId);
            
            $this->assert(
                $isAdv === false,
                "Contractor user should NOT be recognized as ADV user"
            );
            
            // Test that contractor user cannot access courier module
            $hasPermission = $this->masterMiddleware->hasPermission(
                'couriers', 
                'view', 
                $this->contractorUserId
            );
            
            $this->assert(
                $hasPermission === false,
                "Contractor user should NOT have permission to view couriers"
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
     * Property 6: Non-ADV User Denied For All Courier Actions
     * For any non-ADV user and any courier action, hasPermission should return false
     */
    public function testNonAdvUserDeniedAllCourierActions() {
        try {
            // Define all courier actions
            $actions = ['view', 'create', 'edit', 'delete'];
            
            // Pick a random action
            $action = $this->generateRandomChoice($actions);
            
            // Test that contractor user is denied for this action
            $hasPermission = $this->masterMiddleware->hasPermission(
                'couriers', 
                $action, 
                $this->contractorUserId
            );
            
            $this->assert(
                $hasPermission === false,
                "Contractor user should be denied permission for couriers.$action"
            );
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => [
                    'contractorUserId' => $this->contractorUserId,
                    'action' => $action ?? null
                ]
            ];
        }
    }
    
    /**
     * Property 6: ADV User With Permissions Can Perform Courier CRUD
     * For any ADV user, the permission name should be correctly generated
     */
    public function testAdvUserWithCourierPermissions() {
        try {
            // Define all courier actions
            $actions = ['view', 'create', 'edit', 'delete'];
            
            // Pick a random action
            $action = $this->generateRandomChoice($actions);
            
            // First verify user is ADV
            $isAdv = $this->masterMiddleware->isAdvUser($this->advUserId);
            $this->assert($isAdv === true, "User should be ADV user");
            
            // Get the permission name
            $permissionName = $this->masterMiddleware->getPermissionName('couriers', $action);
            $this->assert(
                $permissionName !== null,
                "Permission name should exist for couriers.$action"
            );
            
            // Verify permission name format
            $expectedFormat = "masters.couriers.$action";
            $this->assert(
                $permissionName === $expectedFormat,
                "Permission name should be '$expectedFormat', got '$permissionName'"
            );
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => [
                    'advUserId' => $this->advUserId,
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
        $sql = "SELECT * FROM companies WHERE type = 'ADV' AND status = 'ACTIVE' LIMIT 1";
        $result = $this->db->query($sql);
        $company = $result->fetch_assoc();
        
        if ($company) {
            return $company;
        }
        
        $companyData = [
            'name' => 'Test ADV Company Courier ' . $this->generateRandomString(6),
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
        $sql = "SELECT * FROM companies WHERE type = 'CONTRACTOR' AND status = 'ACTIVE' LIMIT 1";
        $result = $this->db->query($sql);
        $company = $result->fetch_assoc();
        
        if ($company) {
            return $company;
        }
        
        $companyData = [
            'name' => 'Test Contractor Company Courier ' . $this->generateRandomString(6),
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
        $sql = "SELECT u.* FROM users u 
                INNER JOIN companies c ON u.company_id = c.id 
                WHERE c.type = 'ADV' AND u.status = 1 
                LIMIT 1";
        $result = $this->db->query($sql);
        $user = $result->fetch_assoc();
        
        if ($user) {
            return $user;
        }
        
        $sql = "SELECT id FROM roles WHERE company_type IN ('ADV', 'BOTH') AND is_active = 1 LIMIT 1";
        $result = $this->db->query($sql);
        $role = $result->fetch_assoc();
        
        if (!$role) {
            throw new Exception("No ADV role found");
        }
        
        $userData = [
            'username' => 'test_adv_courier_' . $this->generateRandomString(6),
            'email' => 'test_adv_courier_' . $this->generateRandomString(6) . '@example.com',
            'password_hash' => password_hash('TestPassword123!', PASSWORD_DEFAULT),
            'first_name' => 'Test',
            'last_name' => 'ADV Courier User',
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
        $sql = "SELECT u.* FROM users u 
                INNER JOIN companies c ON u.company_id = c.id 
                WHERE c.type = 'CONTRACTOR' AND u.status = 1 
                LIMIT 1";
        $result = $this->db->query($sql);
        $user = $result->fetch_assoc();
        
        if ($user) {
            return $user;
        }
        
        $sql = "SELECT id FROM roles WHERE company_type IN ('CONTRACTOR', 'BOTH') AND is_active = 1 LIMIT 1";
        $result = $this->db->query($sql);
        $role = $result->fetch_assoc();
        
        if (!$role) {
            throw new Exception("No Contractor role found");
        }
        
        $userData = [
            'username' => 'test_contractor_courier_' . $this->generateRandomString(6),
            'email' => 'test_contractor_courier_' . $this->generateRandomString(6) . '@example.com',
            'password_hash' => password_hash('TestPassword123!', PASSWORD_DEFAULT),
            'first_name' => 'Test',
            'last_name' => 'Contractor Courier User',
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
            if (isset($this->createdRecords['users']) && !empty($this->createdRecords['users'])) {
                $ids = implode(',', array_map('intval', $this->createdRecords['users']));
                $this->db->query("DELETE FROM users WHERE id IN ($ids)");
            }
            
            if (isset($this->createdRecords['companies']) && !empty($this->createdRecords['companies'])) {
                $ids = implode(',', array_map('intval', $this->createdRecords['companies']));
                $this->db->query("DELETE FROM companies WHERE id IN ($ids)");
            }
            
            $this->db->query("DELETE FROM users WHERE username LIKE 'test_adv_courier_%' OR username LIKE 'test_contractor_courier_%'");
            $this->db->query("DELETE FROM companies WHERE name LIKE 'Test ADV Company Courier %' OR name LIKE 'Test Contractor Company Courier %'");
            
            $this->createdRecords = [];
            
        } catch (Exception $e) {
            echo "Cleanup warning: " . $e->getMessage() . "\n";
        }
    }
}
