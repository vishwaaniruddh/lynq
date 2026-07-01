<?php
/**
 * Unit Tests for User CRUD Operations
 * Tests user creation, update, and deletion with validation
 * 
 * **Validates: Requirements 1.1, 1.3, 1.4, 8.1, 8.2, 8.3**
 */

require_once 'PropertyTestBase.php';
require_once __DIR__ . '/../services/UserService.php';

class UserCrudTest extends PropertyTestBase {
    private $userService;
    private $userModel;
    private $companyModel;
    private $roleModel;
    private $testCompanies = [];
    private $testUsers = [];
    private $createdUsers = [];
    
    public function __construct() {
        parent::__construct();
        $this->userService = new UserService();
        $this->userModel = new User();
        $this->companyModel = new Company();
        $this->roleModel = new Role();
    }
    
    public function runTests() {
        echo "=== User CRUD Operations Unit Tests ===\n";
        
        $this->setupTestData();
        
        $results = [];
        
        // Test user creation with validation
        $results['create_valid_user'] = $this->testCreateValidUser();
        $results['create_user_missing_fields'] = $this->testCreateUserMissingFields();
        $results['create_user_invalid_email'] = $this->testCreateUserInvalidEmail();
        $results['create_user_duplicate_username'] = $this->testCreateUserDuplicateUsername();
        $results['create_user_duplicate_email'] = $this->testCreateUserDuplicateEmail();
        
        // Test user update operations
        $results['update_user_valid'] = $this->testUpdateUserValid();
        $results['update_user_invalid_email'] = $this->testUpdateUserInvalidEmail();
        $results['update_user_not_found'] = $this->testUpdateUserNotFound();
        
        // Test user deletion and cleanup
        $results['delete_user_valid'] = $this->testDeleteUserValid();
        $results['delete_user_not_found'] = $this->testDeleteUserNotFound();
        $results['delete_self_prevention'] = $this->testDeleteSelfPrevention();
        
        $this->cleanupTestData();
        
        // Print results
        $passed = 0;
        $failed = 0;
        foreach ($results as $testName => $result) {
            if ($result) {
                echo "✓ $testName\n";
                $passed++;
            } else {
                echo "✗ $testName\n";
                $failed++;
            }
        }
        
        echo "\nTotal: $passed passed, $failed failed\n";
        
        return $failed === 0;
    }
    
    /**
     * Test creating a valid user
     */
    private function testCreateValidUser() {
        try {
            $timestamp = time();
            $userData = [
                'username' => 'testcrud_valid_' . $timestamp,
                'email' => 'testcrud_valid_' . $timestamp . '@test.com',
                'password' => 'TestPassword123!',
                'first_name' => 'Test',
                'last_name' => 'Valid',
                'company_id' => $this->testCompanies['adv']['id'],
                'role_id' => 1,
                'status' => 1
            ];
            
            $user = $this->userService->createUser($userData, $this->testUsers['adv']['id']);
            $this->createdUsers[] = $user['id'];
            
            // Verify user was created correctly
            $this->assert($user['username'] === $userData['username'], 'Username mismatch');
            $this->assert($user['email'] === $userData['email'], 'Email mismatch');
            $this->assert((int)$user['company_id'] === (int)$userData['company_id'], 'Company ID mismatch');
            $this->assert((int)$user['role_id'] === (int)$userData['role_id'], 'Role ID mismatch');
            
            return true;
        } catch (Exception $e) {
            echo "  Error: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * Test creating user with missing required fields
     */
    private function testCreateUserMissingFields() {
        try {
            $userData = [
                'username' => 'testcrud_missing_' . time(),
                // Missing email, password, company_id, role_id
            ];
            
            try {
                $this->userService->createUser($userData, $this->testUsers['adv']['id']);
                return false; // Should have thrown exception
            } catch (InvalidArgumentException $e) {
                return true; // Expected behavior
            }
        } catch (Exception $e) {
            echo "  Error: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * Test creating user with invalid email format
     */
    private function testCreateUserInvalidEmail() {
        try {
            $timestamp = time();
            $userData = [
                'username' => 'testcrud_invalidemail_' . $timestamp,
                'email' => 'invalid-email-format',
                'password' => 'TestPassword123!',
                'first_name' => 'Test',
                'last_name' => 'InvalidEmail',
                'company_id' => $this->testCompanies['adv']['id'],
                'role_id' => 1,
                'status' => 1
            ];
            
            try {
                $this->userService->createUser($userData, $this->testUsers['adv']['id']);
                return false; // Should have thrown exception
            } catch (InvalidArgumentException $e) {
                return strpos($e->getMessage(), 'email') !== false;
            }
        } catch (Exception $e) {
            echo "  Error: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * Test creating user with duplicate username
     */
    private function testCreateUserDuplicateUsername() {
        try {
            $timestamp = time();
            
            // Create first user
            $userData1 = [
                'username' => 'testcrud_dup_' . $timestamp,
                'email' => 'testcrud_dup1_' . $timestamp . '@test.com',
                'password' => 'TestPassword123!',
                'first_name' => 'Test',
                'last_name' => 'Dup1',
                'company_id' => $this->testCompanies['adv']['id'],
                'role_id' => 1,
                'status' => 1
            ];
            $user1 = $this->userService->createUser($userData1, $this->testUsers['adv']['id']);
            $this->createdUsers[] = $user1['id'];
            
            // Try to create second user with same username
            $userData2 = [
                'username' => 'testcrud_dup_' . $timestamp, // Same username
                'email' => 'testcrud_dup2_' . $timestamp . '@test.com',
                'password' => 'TestPassword123!',
                'first_name' => 'Test',
                'last_name' => 'Dup2',
                'company_id' => $this->testCompanies['adv']['id'],
                'role_id' => 1,
                'status' => 1
            ];
            
            try {
                $this->userService->createUser($userData2, $this->testUsers['adv']['id']);
                return false; // Should have thrown exception
            } catch (InvalidArgumentException $e) {
                return strpos($e->getMessage(), 'Username') !== false;
            }
        } catch (Exception $e) {
            echo "  Error: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * Test creating user with duplicate email
     */
    private function testCreateUserDuplicateEmail() {
        try {
            $timestamp = time();
            
            // Create first user
            $userData1 = [
                'username' => 'testcrud_dupemail1_' . $timestamp,
                'email' => 'testcrud_dupemail_' . $timestamp . '@test.com',
                'password' => 'TestPassword123!',
                'first_name' => 'Test',
                'last_name' => 'DupEmail1',
                'company_id' => $this->testCompanies['adv']['id'],
                'role_id' => 1,
                'status' => 1
            ];
            $user1 = $this->userService->createUser($userData1, $this->testUsers['adv']['id']);
            $this->createdUsers[] = $user1['id'];
            
            // Try to create second user with same email
            $userData2 = [
                'username' => 'testcrud_dupemail2_' . $timestamp,
                'email' => 'testcrud_dupemail_' . $timestamp . '@test.com', // Same email
                'password' => 'TestPassword123!',
                'first_name' => 'Test',
                'last_name' => 'DupEmail2',
                'company_id' => $this->testCompanies['adv']['id'],
                'role_id' => 1,
                'status' => 1
            ];
            
            try {
                $this->userService->createUser($userData2, $this->testUsers['adv']['id']);
                return false; // Should have thrown exception
            } catch (InvalidArgumentException $e) {
                return strpos($e->getMessage(), 'Email') !== false;
            }
        } catch (Exception $e) {
            echo "  Error: " . $e->getMessage() . "\n";
            return false;
        }
    }

    
    /**
     * Test updating a user with valid data
     */
    private function testUpdateUserValid() {
        try {
            $timestamp = time();
            
            // Create a user to update
            $userData = [
                'username' => 'testcrud_update_' . $timestamp,
                'email' => 'testcrud_update_' . $timestamp . '@test.com',
                'password' => 'TestPassword123!',
                'first_name' => 'Test',
                'last_name' => 'Update',
                'company_id' => $this->testCompanies['adv']['id'],
                'role_id' => 1,
                'status' => 1
            ];
            $user = $this->userService->createUser($userData, $this->testUsers['adv']['id']);
            $this->createdUsers[] = $user['id'];
            
            // Update the user
            $updateData = [
                'first_name' => 'Updated',
                'last_name' => 'Name',
                'email' => 'testcrud_updated_' . $timestamp . '@test.com'
            ];
            $updatedUser = $this->userService->updateUser($user['id'], $updateData, $this->testUsers['adv']['id']);
            
            // Verify updates
            $this->assert($updatedUser['first_name'] === 'Updated', 'First name not updated');
            $this->assert($updatedUser['last_name'] === 'Name', 'Last name not updated');
            $this->assert($updatedUser['email'] === $updateData['email'], 'Email not updated');
            
            return true;
        } catch (Exception $e) {
            echo "  Error: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * Test updating user with invalid email
     */
    private function testUpdateUserInvalidEmail() {
        try {
            $timestamp = time();
            
            // Create a user to update
            $userData = [
                'username' => 'testcrud_updateinv_' . $timestamp,
                'email' => 'testcrud_updateinv_' . $timestamp . '@test.com',
                'password' => 'TestPassword123!',
                'first_name' => 'Test',
                'last_name' => 'UpdateInv',
                'company_id' => $this->testCompanies['adv']['id'],
                'role_id' => 1,
                'status' => 1
            ];
            $user = $this->userService->createUser($userData, $this->testUsers['adv']['id']);
            $this->createdUsers[] = $user['id'];
            
            // Try to update with invalid email
            $updateData = [
                'email' => 'invalid-email'
            ];
            
            try {
                $this->userService->updateUser($user['id'], $updateData, $this->testUsers['adv']['id']);
                return false; // Should have thrown exception
            } catch (InvalidArgumentException $e) {
                return strpos($e->getMessage(), 'email') !== false;
            }
        } catch (Exception $e) {
            echo "  Error: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * Test updating non-existent user
     */
    private function testUpdateUserNotFound() {
        try {
            $updateData = [
                'first_name' => 'Test'
            ];
            
            try {
                $this->userService->updateUser(999999, $updateData, $this->testUsers['adv']['id']);
                return false; // Should have thrown exception
            } catch (InvalidArgumentException $e) {
                return strpos($e->getMessage(), 'not found') !== false;
            }
        } catch (Exception $e) {
            echo "  Error: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * Test deleting a user
     */
    private function testDeleteUserValid() {
        try {
            $timestamp = time();
            
            // Create a user to delete
            $userData = [
                'username' => 'testcrud_delete_' . $timestamp,
                'email' => 'testcrud_delete_' . $timestamp . '@test.com',
                'password' => 'TestPassword123!',
                'first_name' => 'Test',
                'last_name' => 'Delete',
                'company_id' => $this->testCompanies['adv']['id'],
                'role_id' => 1,
                'status' => 1
            ];
            $user = $this->userService->createUser($userData, $this->testUsers['adv']['id']);
            
            // Delete the user
            $result = $this->userService->deleteUser($user['id'], $this->testUsers['adv']['id']);
            
            // Verify deletion
            $this->assert($result === true, 'Delete should return true');
            
            // Verify user no longer exists
            $deletedUser = $this->userModel->find($user['id']);
            $this->assert($deletedUser === null, 'User should not exist after deletion');
            
            return true;
        } catch (Exception $e) {
            echo "  Error: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * Test deleting non-existent user
     */
    private function testDeleteUserNotFound() {
        try {
            try {
                $this->userService->deleteUser(999999, $this->testUsers['adv']['id']);
                return false; // Should have thrown exception
            } catch (InvalidArgumentException $e) {
                return strpos($e->getMessage(), 'not found') !== false;
            }
        } catch (Exception $e) {
            echo "  Error: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * Test that user cannot delete themselves
     */
    private function testDeleteSelfPrevention() {
        try {
            try {
                $this->userService->deleteUser($this->testUsers['adv']['id'], $this->testUsers['adv']['id']);
                return false; // Should have thrown exception
            } catch (InvalidArgumentException $e) {
                return strpos($e->getMessage(), 'own account') !== false;
            }
        } catch (Exception $e) {
            echo "  Error: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    private function setupTestData() {
        $timestamp = time();
        
        // Get or create ADV company
        $existingAdvCompany = $this->companyModel->findByType('ADV');
        if (!empty($existingAdvCompany)) {
            $this->testCompanies['adv'] = $existingAdvCompany[0];
        } else {
            $advCompanyData = [
                'name' => "Test ADV Company CRUD $timestamp",
                'type' => 'ADV',
                'status' => 'ACTIVE'
            ];
            $advCompanyRecord = $this->companyModel->create($advCompanyData);
            $this->testCompanies['adv'] = $this->companyModel->find($advCompanyRecord['id']);
        }
        
        // Create ADV user for testing
        $userData = [
            'username' => "testadvcrud_{$timestamp}",
            'email' => "testadvcrud_{$timestamp}@test.com",
            'password_hash' => password_hash('password123', PASSWORD_DEFAULT),
            'first_name' => 'Test',
            'last_name' => 'ADV CRUD',
            'company_id' => $this->testCompanies['adv']['id'],
            'role_id' => 1,
            'status' => 1
        ];
        $userRecord = $this->userModel->create($userData);
        $this->testUsers['adv'] = $this->userModel->findWithRelations($userRecord['id']);
    }
    
    protected function cleanupTestData() {
        // Clean up created users from tests
        foreach ($this->createdUsers as $userId) {
            $this->executeQuery("DELETE FROM users WHERE id = ?", [$userId], 'i');
        }
        
        // Clean up test ADV user
        if (isset($this->testUsers['adv'])) {
            $this->executeQuery("DELETE FROM users WHERE id = ?", [$this->testUsers['adv']['id']], 'i');
        }
        
        // Clean up test users by pattern
        $this->executeQuery("DELETE FROM users WHERE username LIKE 'testcrud_%'");
        $this->executeQuery("DELETE FROM users WHERE username LIKE 'testadvcrud_%'");
        
        // Don't delete the ADV company if we used an existing one
        if (isset($this->testCompanies['adv']) && strpos($this->testCompanies['adv']['name'], 'Test ADV Company CRUD') !== false) {
            $this->executeQuery("DELETE FROM companies WHERE id = ?", [$this->testCompanies['adv']['id']], 'i');
        }
    }
}

// Run the test if called directly
if (basename(__FILE__) == basename($_SERVER['SCRIPT_NAME'])) {
    $test = new UserCrudTest();
    $result = $test->runTests();
    exit($result ? 0 : 1);
}
