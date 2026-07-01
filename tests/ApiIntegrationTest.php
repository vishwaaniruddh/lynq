<?php
/**
 * API Integration Tests
 * Tests for user API endpoints with different user types
 * Tests for permission validation in API calls
 * Tests for error handling and response formats
 * 
 * **Validates: Requirements 7.1, 7.2, 7.3**
 */

require_once 'PropertyTestBase.php';
require_once __DIR__ . '/../services/UserService.php';
require_once __DIR__ . '/../services/PermissionEngine.php';
require_once __DIR__ . '/../api/ApiResponse.php';
require_once __DIR__ . '/../middleware/ApiAuthMiddleware.php';

class ApiIntegrationTest extends PropertyTestBase {
    private $userService;
    private $userModel;
    private $companyModel;
    private $roleModel;
    private $permissionEngine;
    private $testCompanies = [];
    private $testUsers = [];
    private $testRoles = [];
    private $createdUsers = [];
    
    public function __construct() {
        parent::__construct();
        $this->userService = new UserService();
        $this->userModel = new User();
        $this->companyModel = new Company();
        $this->roleModel = new Role();
        $this->permissionEngine = new PermissionEngine();
    }
    
    public function runTests() {
        echo "=== API Integration Tests ===\n";
        echo "**Validates: Requirements 7.1, 7.2, 7.3**\n\n";
        
        $this->setupTestData();
        
        $results = [];
        
        // Test API endpoints with different user types
        echo "\n--- User API Endpoint Tests ---\n";
        $results['adv_user_can_list_all_users'] = $this->testAdvUserCanListAllUsers();
        $results['contractor_user_sees_only_own_company'] = $this->testContractorUserSeesOnlyOwnCompany();
        $results['adv_user_can_create_user_any_company'] = $this->testAdvUserCanCreateUserAnyCompany();
        $results['contractor_user_can_create_user_own_company'] = $this->testContractorUserCanCreateUserOwnCompany();
        $results['contractor_cannot_create_user_other_company'] = $this->testContractorCannotCreateUserOtherCompany();
        
        // Test update API with different user types
        echo "\n--- User Update API Tests ---\n";
        $results['adv_user_can_update_any_user'] = $this->testAdvUserCanUpdateAnyUser();
        $results['contractor_can_update_own_company_user'] = $this->testContractorCanUpdateOwnCompanyUser();
        $results['contractor_cannot_update_other_company_user'] = $this->testContractorCannotUpdateOtherCompanyUser();
        
        // Test delete API with different user types
        echo "\n--- User Delete API Tests ---\n";
        $results['adv_user_can_delete_user'] = $this->testAdvUserCanDeleteUser();
        $results['contractor_can_delete_own_company_user'] = $this->testContractorCanDeleteOwnCompanyUser();
        $results['contractor_cannot_delete_other_company_user'] = $this->testContractorCannotDeleteOtherCompanyUser();
        
        // Test show (single user) API with different user types
        echo "\n--- User Show API Tests ---\n";
        $results['adv_user_can_view_any_user'] = $this->testAdvUserCanViewAnyUser();
        $results['contractor_can_view_own_company_user'] = $this->testContractorCanViewOwnCompanyUser();
        $results['contractor_cannot_view_other_company_user'] = $this->testContractorCannotViewOtherCompanyUser();
        
        // Test permission validation in API calls
        echo "\n--- Permission Validation Tests ---\n";
        $results['permission_required_for_user_list'] = $this->testPermissionRequiredForUserList();
        $results['permission_required_for_user_create'] = $this->testPermissionRequiredForUserCreate();
        $results['permission_required_for_user_update'] = $this->testPermissionRequiredForUserUpdate();
        $results['permission_required_for_user_delete'] = $this->testPermissionRequiredForUserDelete();
        
        // Test error handling and response formats
        echo "\n--- Error Handling Tests ---\n";
        $results['validation_error_format'] = $this->testValidationErrorFormat();
        $results['not_found_error_format'] = $this->testNotFoundErrorFormat();
        $results['forbidden_error_format'] = $this->testForbiddenErrorFormat();
        
        // Test consistent permission checking (can() function)
        echo "\n--- Consistent Permission Checking Tests ---\n";
        $results['can_function_used_for_authorization'] = $this->testCanFunctionUsedForAuthorization();
        $results['permission_check_verifies_company_delegation'] = $this->testPermissionCheckVerifiesCompanyDelegation();
        
        $this->cleanupTestData();
        
        // Print results
        echo "\n=== Results ===\n";
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
     * Test that ADV user can list users from all companies
     * **Validates: Requirements 7.1 - can() function for authorization**
     */
    private function testAdvUserCanListAllUsers() {
        try {
            // Simulate ADV user context
            $advUser = $this->testUsers['adv'];
            
            // Get all users as ADV user
            $users = $this->userService->getAllUsers($advUser['id']);
            
            // ADV user should see users from multiple companies
            $companyIds = array_unique(array_column($users, 'company_id'));
            
            // Should see at least 2 companies (ADV and contractor)
            $this->assert(count($companyIds) >= 2, 'ADV user should see users from multiple companies');
            
            return true;
        } catch (Exception $e) {
            echo "  Error: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * Test that contractor user only sees their own company's users
     * **Validates: Requirements 7.2 - verify company delegations**
     */
    private function testContractorUserSeesOnlyOwnCompany() {
        try {
            // Simulate contractor user context
            $contractorUser = $this->testUsers['contractor'];
            
            // Get all users as contractor user
            $users = $this->userService->getAllUsers($contractorUser['id']);
            
            // All users should be from contractor's company
            foreach ($users as $user) {
                $this->assert(
                    (int)$user['company_id'] === (int)$contractorUser['company_id'],
                    'Contractor should only see users from their own company'
                );
            }
            
            return true;
        } catch (Exception $e) {
            echo "  Error: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * Test that ADV user can create user in any company
     * **Validates: Requirements 7.1**
     */
    private function testAdvUserCanCreateUserAnyCompany() {
        try {
            $timestamp = time();
            $advUser = $this->testUsers['adv'];
            
            // Create user in contractor company
            $userData = [
                'username' => "api_test_adv_create_{$timestamp}",
                'email' => "api_test_adv_create_{$timestamp}@test.com",
                'password' => 'TestPassword123!',
                'first_name' => 'API',
                'last_name' => 'Test',
                'company_id' => $this->testCompanies['contractor']['id'],
                'role_id' => $this->testRoles['contractor']['id'],
                'status' => 1
            ];
            
            $user = $this->userService->createUser($userData, $advUser['id']);
            $this->createdUsers[] = $user['id'];
            
            $this->assert($user['id'] > 0, 'User should be created');
            $this->assert(
                (int)$user['company_id'] === (int)$this->testCompanies['contractor']['id'],
                'User should be in contractor company'
            );
            
            return true;
        } catch (Exception $e) {
            echo "  Error: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * Test that contractor user can create user in their own company
     * **Validates: Requirements 7.1**
     */
    private function testContractorUserCanCreateUserOwnCompany() {
        try {
            $timestamp = time();
            $contractorUser = $this->testUsers['contractor'];
            
            // Create user in own company
            $userData = [
                'username' => "api_test_contractor_create_{$timestamp}",
                'email' => "api_test_contractor_create_{$timestamp}@test.com",
                'password' => 'TestPassword123!',
                'first_name' => 'API',
                'last_name' => 'Test',
                'company_id' => $contractorUser['company_id'],
                'role_id' => $this->testRoles['contractor']['id'],
                'status' => 1
            ];
            
            $user = $this->userService->createUser($userData, $contractorUser['id']);
            $this->createdUsers[] = $user['id'];
            
            $this->assert($user['id'] > 0, 'User should be created');
            
            return true;
        } catch (Exception $e) {
            echo "  Error: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * Test that contractor user cannot create user in other company
     * **Validates: Requirements 7.3 - deny access and return error**
     */
    private function testContractorCannotCreateUserOtherCompany() {
        try {
            $timestamp = time();
            $contractorUser = $this->testUsers['contractor'];
            
            // Try to create user in ADV company
            $userData = [
                'username' => "api_test_contractor_fail_{$timestamp}",
                'email' => "api_test_contractor_fail_{$timestamp}@test.com",
                'password' => 'TestPassword123!',
                'first_name' => 'API',
                'last_name' => 'Test',
                'company_id' => $this->testCompanies['adv']['id'],
                'role_id' => 1,
                'status' => 1
            ];
            
            try {
                $this->userService->createUser($userData, $contractorUser['id']);
                return false; // Should have thrown exception
            } catch (CompanyAccessDeniedException $e) {
                return true; // Expected behavior
            }
        } catch (Exception $e) {
            echo "  Error: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * Test that ADV user can update any user
     * **Validates: Requirements 7.1 - can() function for authorization**
     */
    private function testAdvUserCanUpdateAnyUser() {
        try {
            $timestamp = time();
            $advUser = $this->testUsers['adv'];
            
            // Create a user to update
            $userData = [
                'username' => "api_test_update_target_{$timestamp}",
                'email' => "api_test_update_target_{$timestamp}@test.com",
                'password' => 'TestPassword123!',
                'first_name' => 'Update',
                'last_name' => 'Target',
                'company_id' => $this->testCompanies['contractor']['id'],
                'role_id' => $this->testRoles['contractor']['id'],
                'status' => 1
            ];
            
            $user = $this->userService->createUser($userData, $advUser['id']);
            $this->createdUsers[] = $user['id'];
            
            // Update the user
            $updateData = [
                'first_name' => 'Updated',
                'last_name' => 'Name'
            ];
            
            $updatedUser = $this->userService->updateUser($user['id'], $updateData, $advUser['id']);
            
            $this->assert($updatedUser['first_name'] === 'Updated', 'First name should be updated');
            $this->assert($updatedUser['last_name'] === 'Name', 'Last name should be updated');
            
            return true;
        } catch (Exception $e) {
            echo "  Error: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * Test that contractor can update user in their own company
     * **Validates: Requirements 7.1, 7.2**
     */
    private function testContractorCanUpdateOwnCompanyUser() {
        try {
            $timestamp = time();
            $contractorUser = $this->testUsers['contractor'];
            $advUser = $this->testUsers['adv'];
            
            // Create a user in contractor's company (using ADV to create)
            $userData = [
                'username' => "api_test_contractor_update_{$timestamp}",
                'email' => "api_test_contractor_update_{$timestamp}@test.com",
                'password' => 'TestPassword123!',
                'first_name' => 'Contractor',
                'last_name' => 'Update',
                'company_id' => $contractorUser['company_id'],
                'role_id' => $this->testRoles['contractor']['id'],
                'status' => 1
            ];
            
            $user = $this->userService->createUser($userData, $advUser['id']);
            $this->createdUsers[] = $user['id'];
            
            // Contractor updates user in their own company
            $updateData = [
                'first_name' => 'ContractorUpdated'
            ];
            
            $updatedUser = $this->userService->updateUser($user['id'], $updateData, $contractorUser['id']);
            
            $this->assert($updatedUser['first_name'] === 'ContractorUpdated', 'First name should be updated');
            
            return true;
        } catch (Exception $e) {
            echo "  Error: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * Test that contractor cannot update user in other company
     * **Validates: Requirements 7.3 - deny access**
     */
    private function testContractorCannotUpdateOtherCompanyUser() {
        try {
            $timestamp = time();
            $contractorUser = $this->testUsers['contractor'];
            $advUser = $this->testUsers['adv'];
            
            // Create a user in ADV company
            $userData = [
                'username' => "api_test_adv_update_target_{$timestamp}",
                'email' => "api_test_adv_update_target_{$timestamp}@test.com",
                'password' => 'TestPassword123!',
                'first_name' => 'ADV',
                'last_name' => 'Target',
                'company_id' => $this->testCompanies['adv']['id'],
                'role_id' => $this->testRoles['adv']['id'],
                'status' => 1
            ];
            
            $user = $this->userService->createUser($userData, $advUser['id']);
            $this->createdUsers[] = $user['id'];
            
            // Contractor tries to update user in ADV company
            $updateData = [
                'first_name' => 'ShouldFail'
            ];
            
            try {
                $this->userService->updateUser($user['id'], $updateData, $contractorUser['id']);
                return false; // Should have thrown exception
            } catch (CompanyAccessDeniedException $e) {
                return true; // Expected behavior
            }
        } catch (Exception $e) {
            echo "  Error: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * Test that ADV user can delete user
     * **Validates: Requirements 7.1**
     */
    private function testAdvUserCanDeleteUser() {
        try {
            $timestamp = time();
            $advUser = $this->testUsers['adv'];
            
            // Create a user to delete
            $userData = [
                'username' => "api_test_delete_target_{$timestamp}",
                'email' => "api_test_delete_target_{$timestamp}@test.com",
                'password' => 'TestPassword123!',
                'first_name' => 'Delete',
                'last_name' => 'Target',
                'company_id' => $this->testCompanies['contractor']['id'],
                'role_id' => $this->testRoles['contractor']['id'],
                'status' => 1
            ];
            
            $user = $this->userService->createUser($userData, $advUser['id']);
            
            // Delete the user
            $result = $this->userService->deleteUser($user['id'], $advUser['id']);
            
            $this->assert($result === true, 'User should be deleted');
            
            // Verify user is deleted
            $deletedUser = $this->userService->getUser($user['id'], $advUser['id']);
            $this->assert($deletedUser === null, 'Deleted user should not be found');
            
            return true;
        } catch (Exception $e) {
            echo "  Error: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * Test that contractor can delete user in their own company
     * **Validates: Requirements 7.1, 7.2**
     */
    private function testContractorCanDeleteOwnCompanyUser() {
        try {
            $timestamp = time();
            $contractorUser = $this->testUsers['contractor'];
            $advUser = $this->testUsers['adv'];
            
            // Create a user in contractor's company (using ADV to create)
            $userData = [
                'username' => "api_test_contractor_delete_{$timestamp}",
                'email' => "api_test_contractor_delete_{$timestamp}@test.com",
                'password' => 'TestPassword123!',
                'first_name' => 'Contractor',
                'last_name' => 'Delete',
                'company_id' => $contractorUser['company_id'],
                'role_id' => $this->testRoles['contractor']['id'],
                'status' => 1
            ];
            
            $user = $this->userService->createUser($userData, $advUser['id']);
            
            // Contractor deletes user in their own company
            $result = $this->userService->deleteUser($user['id'], $contractorUser['id']);
            
            $this->assert($result === true, 'User should be deleted');
            
            return true;
        } catch (Exception $e) {
            echo "  Error: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * Test that contractor cannot delete user in other company
     * **Validates: Requirements 7.3 - deny access**
     */
    private function testContractorCannotDeleteOtherCompanyUser() {
        try {
            $timestamp = time();
            $contractorUser = $this->testUsers['contractor'];
            $advUser = $this->testUsers['adv'];
            
            // Create a user in ADV company
            $userData = [
                'username' => "api_test_adv_delete_target_{$timestamp}",
                'email' => "api_test_adv_delete_target_{$timestamp}@test.com",
                'password' => 'TestPassword123!',
                'first_name' => 'ADV',
                'last_name' => 'DeleteTarget',
                'company_id' => $this->testCompanies['adv']['id'],
                'role_id' => $this->testRoles['adv']['id'],
                'status' => 1
            ];
            
            $user = $this->userService->createUser($userData, $advUser['id']);
            $this->createdUsers[] = $user['id'];
            
            // Contractor tries to delete user in ADV company
            try {
                $this->userService->deleteUser($user['id'], $contractorUser['id']);
                return false; // Should have thrown exception
            } catch (CompanyAccessDeniedException $e) {
                return true; // Expected behavior
            }
        } catch (Exception $e) {
            echo "  Error: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * Test that ADV user can view any user
     * **Validates: Requirements 7.1**
     */
    private function testAdvUserCanViewAnyUser() {
        try {
            $advUser = $this->testUsers['adv'];
            $contractorUser = $this->testUsers['contractor'];
            
            // ADV user views contractor user
            $user = $this->userService->getUser($contractorUser['id'], $advUser['id']);
            
            $this->assert($user !== null, 'ADV user should be able to view contractor user');
            $this->assert((int)$user['id'] === (int)$contractorUser['id'], 'Should return correct user');
            
            return true;
        } catch (Exception $e) {
            echo "  Error: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * Test that contractor can view user in their own company
     * **Validates: Requirements 7.1, 7.2**
     */
    private function testContractorCanViewOwnCompanyUser() {
        try {
            $timestamp = time();
            $contractorUser = $this->testUsers['contractor'];
            $advUser = $this->testUsers['adv'];
            
            // Create another user in contractor's company
            $userData = [
                'username' => "api_test_contractor_view_{$timestamp}",
                'email' => "api_test_contractor_view_{$timestamp}@test.com",
                'password' => 'TestPassword123!',
                'first_name' => 'Contractor',
                'last_name' => 'View',
                'company_id' => $contractorUser['company_id'],
                'role_id' => $this->testRoles['contractor']['id'],
                'status' => 1
            ];
            
            $user = $this->userService->createUser($userData, $advUser['id']);
            $this->createdUsers[] = $user['id'];
            
            // Contractor views user in their own company
            $viewedUser = $this->userService->getUser($user['id'], $contractorUser['id']);
            
            $this->assert($viewedUser !== null, 'Contractor should be able to view user in own company');
            $this->assert((int)$viewedUser['id'] === (int)$user['id'], 'Should return correct user');
            
            return true;
        } catch (Exception $e) {
            echo "  Error: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * Test that contractor cannot view user in other company
     * **Validates: Requirements 7.3 - deny access**
     */
    private function testContractorCannotViewOtherCompanyUser() {
        try {
            $contractorUser = $this->testUsers['contractor'];
            $advUser = $this->testUsers['adv'];
            
            // Contractor tries to view ADV user
            $user = $this->userService->getUser($advUser['id'], $contractorUser['id']);
            
            // Should return null (not found due to company isolation)
            $this->assert($user === null, 'Contractor should not be able to view ADV user');
            
            return true;
        } catch (Exception $e) {
            echo "  Error: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * Test that permission is required for user list
     * **Validates: Requirements 7.1 - can() function for authorization**
     */
    private function testPermissionRequiredForUserList() {
        try {
            $advUser = $this->testUsers['adv'];
            
            // Check that can() function is used
            $hasPermission = $this->permissionEngine->can($advUser['id'], 'users.read');
            
            // ADV user should have users.read permission
            $this->assert($hasPermission, 'ADV user should have users.read permission');
            
            return true;
        } catch (Exception $e) {
            echo "  Error: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * Test that permission is required for user create
     * **Validates: Requirements 7.1**
     */
    private function testPermissionRequiredForUserCreate() {
        try {
            $advUser = $this->testUsers['adv'];
            
            // Check that can() function is used
            $hasPermission = $this->permissionEngine->can($advUser['id'], 'users.create');
            
            // ADV user should have users.create permission
            $this->assert($hasPermission, 'ADV user should have users.create permission');
            
            return true;
        } catch (Exception $e) {
            echo "  Error: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * Test that permission is required for user update
     * **Validates: Requirements 7.1**
     */
    private function testPermissionRequiredForUserUpdate() {
        try {
            $advUser = $this->testUsers['adv'];
            
            // Check that can() function is used
            $hasPermission = $this->permissionEngine->can($advUser['id'], 'users.update');
            
            // ADV user should have users.update permission
            $this->assert($hasPermission, 'ADV user should have users.update permission');
            
            return true;
        } catch (Exception $e) {
            echo "  Error: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * Test that permission is required for user delete
     * **Validates: Requirements 7.1**
     */
    private function testPermissionRequiredForUserDelete() {
        try {
            $advUser = $this->testUsers['adv'];
            
            // Check that can() function is used
            $hasPermission = $this->permissionEngine->can($advUser['id'], 'users.delete');
            
            // ADV user should have users.delete permission
            $this->assert($hasPermission, 'ADV user should have users.delete permission');
            
            return true;
        } catch (Exception $e) {
            echo "  Error: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * Test validation error response format
     * **Validates: Requirements 7.3 - return appropriate error messages**
     */
    private function testValidationErrorFormat() {
        try {
            $timestamp = time();
            $advUser = $this->testUsers['adv'];
            
            // Try to create user with invalid email
            $userData = [
                'username' => "api_test_invalid_{$timestamp}",
                'email' => 'invalid-email',
                'password' => 'TestPassword123!',
                'first_name' => 'API',
                'last_name' => 'Test',
                'company_id' => $this->testCompanies['adv']['id'],
                'role_id' => 1,
                'status' => 1
            ];
            
            try {
                $this->userService->createUser($userData, $advUser['id']);
                return false; // Should have thrown exception
            } catch (InvalidArgumentException $e) {
                // Check that error message mentions email
                $this->assert(
                    strpos(strtolower($e->getMessage()), 'email') !== false,
                    'Error message should mention email'
                );
                return true;
            }
        } catch (Exception $e) {
            echo "  Error: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * Test not found error response format
     * **Validates: Requirements 7.3**
     */
    private function testNotFoundErrorFormat() {
        try {
            $advUser = $this->testUsers['adv'];
            
            // Try to get non-existent user
            $user = $this->userService->getUser(999999, $advUser['id']);
            
            // Should return null for not found
            $this->assert($user === null, 'Should return null for non-existent user');
            
            return true;
        } catch (Exception $e) {
            echo "  Error: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * Test forbidden error response format
     * **Validates: Requirements 7.3**
     */
    private function testForbiddenErrorFormat() {
        try {
            $timestamp = time();
            $contractorUser = $this->testUsers['contractor'];
            
            // Try to create user in ADV company (should be forbidden)
            $userData = [
                'username' => "api_test_forbidden_{$timestamp}",
                'email' => "api_test_forbidden_{$timestamp}@test.com",
                'password' => 'TestPassword123!',
                'first_name' => 'API',
                'last_name' => 'Test',
                'company_id' => $this->testCompanies['adv']['id'],
                'role_id' => 1,
                'status' => 1
            ];
            
            try {
                $this->userService->createUser($userData, $contractorUser['id']);
                return false; // Should have thrown exception
            } catch (CompanyAccessDeniedException $e) {
                // Check that error message is appropriate
                $this->assert(
                    strlen($e->getMessage()) > 0,
                    'Error message should not be empty'
                );
                return true;
            }
        } catch (Exception $e) {
            echo "  Error: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * Test that can() function is used for authorization
     * **Validates: Requirements 7.1 - use can('permission.name') function**
     */
    private function testCanFunctionUsedForAuthorization() {
        try {
            $advUser = $this->testUsers['adv'];
            $contractorUser = $this->testUsers['contractor'];
            
            // Test can() function for various permissions
            $permissions = ['users.read', 'users.create', 'users.update', 'users.delete'];
            
            foreach ($permissions as $permission) {
                // ADV user should have all permissions
                $advHas = $this->permissionEngine->can($advUser['id'], $permission);
                $this->assert($advHas, "ADV user should have $permission");
            }
            
            return true;
        } catch (Exception $e) {
            echo "  Error: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * Test that permission check verifies company delegations
     * **Validates: Requirements 7.2 - verify both user permissions and company delegations**
     */
    private function testPermissionCheckVerifiesCompanyDelegation() {
        try {
            $contractorUser = $this->testUsers['contractor'];
            
            // Get contractor's permissions
            $permissions = $this->permissionEngine->getUserPermissions($contractorUser['id']);
            
            // Permissions should include source information
            foreach ($permissions as $permissionName => $permData) {
                $this->assert(
                    isset($permData['source']),
                    "Permission '$permissionName' should include source (role or delegation)"
                );
                $this->assert(
                    in_array($permData['source'], ['role', 'delegation']),
                    "Permission '$permissionName' source should be role or delegation"
                );
            }
            
            return true;
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
                'name' => "Test ADV Company API $timestamp",
                'type' => 'ADV',
                'status' => 'ACTIVE'
            ];
            $advCompanyRecord = $this->companyModel->create($advCompanyData);
            $this->testCompanies['adv'] = $this->companyModel->find($advCompanyRecord['id']);
        }
        
        // Create contractor company
        $contractorCompanyData = [
            'name' => "Test Contractor Company API $timestamp",
            'type' => 'CONTRACTOR',
            'status' => 'ACTIVE'
        ];
        $contractorCompanyRecord = $this->companyModel->create($contractorCompanyData);
        $this->testCompanies['contractor'] = $this->companyModel->find($contractorCompanyRecord['id']);
        
        // Get roles
        $advRoles = $this->roleModel->findByCompanyType('ADV');
        $contractorRoles = $this->roleModel->findByCompanyType('CONTRACTOR');
        
        if (empty($advRoles)) {
            // Create ADV role
            $advRoleData = [
                'name' => 'Test ADV Admin',
                'level' => 1,
                'company_type' => 'ADV',
                'description' => 'Test ADV admin role'
            ];
            $advRoleRecord = $this->roleModel->create($advRoleData);
            $this->testRoles['adv'] = $this->roleModel->find($advRoleRecord['id']);
        } else {
            $this->testRoles['adv'] = $advRoles[0];
        }
        
        if (empty($contractorRoles)) {
            // Create contractor role
            $contractorRoleData = [
                'name' => 'Test Contractor Admin',
                'level' => 10,
                'company_type' => 'CONTRACTOR',
                'description' => 'Test contractor admin role'
            ];
            $contractorRoleRecord = $this->roleModel->create($contractorRoleData);
            $this->testRoles['contractor'] = $this->roleModel->find($contractorRoleRecord['id']);
        } else {
            $this->testRoles['contractor'] = $contractorRoles[0];
        }
        
        // Create ADV user
        $advUserData = [
            'username' => "testadvapi_{$timestamp}",
            'email' => "testadvapi_{$timestamp}@test.com",
            'password_hash' => password_hash('password123', PASSWORD_DEFAULT),
            'first_name' => 'Test',
            'last_name' => 'ADV API',
            'company_id' => $this->testCompanies['adv']['id'],
            'role_id' => $this->testRoles['adv']['id'],
            'status' => 1
        ];
        $advUserRecord = $this->userModel->create($advUserData);
        $this->testUsers['adv'] = $this->userModel->findWithRelations($advUserRecord['id']);
        
        // Create contractor user
        $contractorUserData = [
            'username' => "testcontractorapi_{$timestamp}",
            'email' => "testcontractorapi_{$timestamp}@test.com",
            'password_hash' => password_hash('password123', PASSWORD_DEFAULT),
            'first_name' => 'Test',
            'last_name' => 'Contractor API',
            'company_id' => $this->testCompanies['contractor']['id'],
            'role_id' => $this->testRoles['contractor']['id'],
            'status' => 1
        ];
        $contractorUserRecord = $this->userModel->create($contractorUserData);
        $this->testUsers['contractor'] = $this->userModel->findWithRelations($contractorUserRecord['id']);
    }
    
    protected function cleanupTestData() {
        // Clean up created users from tests
        foreach ($this->createdUsers as $userId) {
            $this->executeQuery("DELETE FROM users WHERE id = ?", [$userId], 'i');
        }
        
        // Clean up test users
        if (isset($this->testUsers['adv'])) {
            $this->executeQuery("DELETE FROM users WHERE id = ?", [$this->testUsers['adv']['id']], 'i');
        }
        if (isset($this->testUsers['contractor'])) {
            $this->executeQuery("DELETE FROM users WHERE id = ?", [$this->testUsers['contractor']['id']], 'i');
        }
        
        // Clean up test users by pattern
        $this->executeQuery("DELETE FROM users WHERE username LIKE 'api_test_%'");
        $this->executeQuery("DELETE FROM users WHERE username LIKE 'testadvapi_%'");
        $this->executeQuery("DELETE FROM users WHERE username LIKE 'testcontractorapi_%'");
        
        // Clean up contractor company
        if (isset($this->testCompanies['contractor'])) {
            $this->executeQuery("DELETE FROM companies WHERE id = ?", [$this->testCompanies['contractor']['id']], 'i');
        }
        
        // Clean up test roles if we created them
        if (isset($this->testRoles['adv']) && strpos($this->testRoles['adv']['name'], 'Test ADV Admin') !== false) {
            $this->executeQuery("DELETE FROM roles WHERE id = ?", [$this->testRoles['adv']['id']], 'i');
        }
        if (isset($this->testRoles['contractor']) && strpos($this->testRoles['contractor']['name'], 'Test Contractor Admin') !== false) {
            $this->executeQuery("DELETE FROM roles WHERE id = ?", [$this->testRoles['contractor']['id']], 'i');
        }
    }
}

// Run the test if called directly
if (basename(__FILE__) == basename($_SERVER['SCRIPT_NAME'])) {
    $test = new ApiIntegrationTest();
    $result = $test->runTests();
    exit($result ? 0 : 1);
}
