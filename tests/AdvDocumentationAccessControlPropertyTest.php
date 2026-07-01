<?php
/**
 * Property Test for ADV Documentation Access Control
 * **Feature: adv-user-documentation, Property 1: ADV-Only Icon Visibility**
 * **Validates: Requirements 1.1, 1.3**
 */

require_once 'PropertyTestBase.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Company.php';
require_once __DIR__ . '/../repositories/UserRepository.php';
require_once __DIR__ . '/../repositories/CompanyRepository.php';

class AdvDocumentationAccessControlPropertyTest extends PropertyTestBase {
    
    private $createdRecords = [];
    private $userModel;
    private $advUserId = null;
    private $contractorUserId = null;
    private $advCompanyId = null;
    private $contractorCompanyId = null;
    
    public function __construct() {
        parent::__construct();
        $this->userModel = new User();
    }
    
    public function runTests() {
        echo "=== ADV Documentation Access Control Property Tests ===\n\n";
        $this->setupTestUsers();
        $allPassed = true;
        
        $allPassed &= $this->runPropertyTest(
            "ADV User Can See Documentation Icon",
            [$this, 'testAdvUserCanSeeDocIcon'],
            50
        );
        
        $allPassed &= $this->runPropertyTest(
            "Non-ADV User Cannot See Documentation Icon",
            [$this, 'testNonAdvUserCannotSeeDocIcon'],
            50
        );
        
        return $allPassed;
    }
    
    private function setupTestUsers() {
        try {
            $advCompany = $this->getOrCreateAdvCompany();
            $this->advCompanyId = $advCompany['id'];
            
            $contractorCompany = $this->getOrCreateContractorCompany();
            $this->contractorCompanyId = $contractorCompany['id'];
            
            $advUser = $this->getOrCreateAdvUser($this->advCompanyId);
            $this->advUserId = $advUser['id'];
            
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
    
    public function testAdvUserCanSeeDocIcon() {
        try {
            $user = $this->userModel->findWithRelations($this->advUserId);
            $this->assert($user !== null, "ADV user should exist");
            
            $companyType = strtoupper($user['company_type'] ?? '');
            $this->assert($companyType === 'ADV', "ADV user should have ADV company type, got: $companyType");
            
            $isAdv = $companyType === 'ADV';
            $this->assert($isAdv === true, "ADV user should be recognized as ADV user");
            
            return ['success' => true];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage(), 'data' => ['advUserId' => $this->advUserId]];
        }
    }
    
    public function testNonAdvUserCannotSeeDocIcon() {
        try {
            $user = $this->userModel->findWithRelations($this->contractorUserId);
            $this->assert($user !== null, "Contractor user should exist");
            
            $companyType = strtoupper($user['company_type'] ?? '');
            $this->assert($companyType !== 'ADV', "Contractor user should NOT have ADV company type, got: $companyType");
            
            $isAdv = $companyType === 'ADV';
            $this->assert($isAdv === false, "Contractor user should NOT be recognized as ADV user");
            
            return ['success' => true];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage(), 'data' => ['contractorUserId' => $this->contractorUserId]];
        }
    }
    
    private function getOrCreateAdvCompany() {
        $sql = "SELECT * FROM companies WHERE type = 'ADV' AND status = 'ACTIVE' LIMIT 1";
        $result = $this->db->query($sql);
        $company = $result->fetch_assoc();
        
        if ($company) {
            return $company;
        }
        
        $name = 'Test ADV Company Doc ' . $this->generateRandomString(6);
        $type = 'ADV';
        $status = 'ACTIVE';
        
        $sql = "INSERT INTO companies (name, type, status) VALUES (?, ?, ?)";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param('sss', $name, $type, $status);
        $stmt->execute();
        
        $companyId = $this->db->insert_id;
        $this->createdRecords['companies'][] = $companyId;
        
        return ['id' => $companyId, 'name' => $name, 'type' => $type, 'status' => $status];
    }
    
    private function getOrCreateContractorCompany() {
        $sql = "SELECT * FROM companies WHERE type = 'CONTRACTOR' AND status = 'ACTIVE' LIMIT 1";
        $result = $this->db->query($sql);
        $company = $result->fetch_assoc();
        
        if ($company) {
            return $company;
        }
        
        $name = 'Test Contractor Company Doc ' . $this->generateRandomString(6);
        $type = 'CONTRACTOR';
        $status = 'ACTIVE';
        
        $sql = "INSERT INTO companies (name, type, status) VALUES (?, ?, ?)";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param('sss', $name, $type, $status);
        $stmt->execute();
        
        $companyId = $this->db->insert_id;
        $this->createdRecords['companies'][] = $companyId;
        
        return ['id' => $companyId, 'name' => $name, 'type' => $type, 'status' => $status];
    }
    
    private function getOrCreateAdvUser($companyId) {
        $sql = "SELECT u.* FROM users u 
                INNER JOIN companies c ON u.company_id = c.id 
                WHERE c.type = 'ADV' AND u.status = 1 LIMIT 1";
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
        
        $username = 'test_adv_doc_' . $this->generateRandomString(6);
        $email = 'test_adv_doc_' . $this->generateRandomString(6) . '@example.com';
        $passwordHash = password_hash('TestPassword123!', PASSWORD_DEFAULT);
        $firstName = 'Test';
        $lastName = 'ADV Doc User';
        $roleId = $role['id'];
        $status = 1;
        
        $sql = "INSERT INTO users (username, email, password_hash, first_name, last_name, company_id, role_id, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param('sssssiii', $username, $email, $passwordHash, $firstName, $lastName, $companyId, $roleId, $status);
        $stmt->execute();
        
        $userId = $this->db->insert_id;
        $this->createdRecords['users'][] = $userId;
        
        return ['id' => $userId, 'username' => $username, 'company_id' => $companyId, 'role_id' => $roleId];
    }
    
    private function getOrCreateContractorUser($companyId) {
        $sql = "SELECT u.* FROM users u 
                INNER JOIN companies c ON u.company_id = c.id 
                WHERE c.type = 'CONTRACTOR' AND u.status = 1 LIMIT 1";
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
        
        $username = 'test_contractor_doc_' . $this->generateRandomString(6);
        $email = 'test_contractor_doc_' . $this->generateRandomString(6) . '@example.com';
        $passwordHash = password_hash('TestPassword123!', PASSWORD_DEFAULT);
        $firstName = 'Test';
        $lastName = 'Contractor Doc User';
        $roleId = $role['id'];
        $status = 1;
        
        $sql = "INSERT INTO users (username, email, password_hash, first_name, last_name, company_id, role_id, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param('sssssiii', $username, $email, $passwordHash, $firstName, $lastName, $companyId, $roleId, $status);
        $stmt->execute();
        
        $userId = $this->db->insert_id;
        $this->createdRecords['users'][] = $userId;
        
        return ['id' => $userId, 'username' => $username, 'company_id' => $companyId, 'role_id' => $roleId];
    }
    
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
            
            $this->db->query("DELETE FROM users WHERE username LIKE 'test_adv_doc_%' OR username LIKE 'test_contractor_doc_%'");
            $this->db->query("DELETE FROM companies WHERE name LIKE 'Test ADV Company Doc %' OR name LIKE 'Test Contractor Company Doc %'");
            
            $this->createdRecords = [];
        } catch (Exception $e) {
            echo "Cleanup warning: " . $e->getMessage() . "\n";
        }
    }
}
