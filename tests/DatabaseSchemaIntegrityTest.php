<?php
/**
 * Property Test for Database Schema Integrity
 * **Feature: adv-crm-users-module, Property 15: Referential Integrity Maintenance**
 * **Validates: Requirements 1.4, 8.5**
 */

require_once 'PropertyTestBase.php';

class DatabaseSchemaIntegrityTest extends PropertyTestBase {
    
    public function runTests() {
        echo "=== Database Schema Integrity Property Tests ===\n\n";
        
        $allPassed = true;
        
        // Test referential integrity maintenance
        $allPassed &= $this->runPropertyTest(
            "Referential Integrity Maintenance",
            [$this, 'testReferentialIntegrityMaintenance']
        );
        
        return $allPassed;
    }
    
    /**
     * Property 15: Referential Integrity Maintenance
     * For any operation that could affect relationships between users, companies, and roles,
     * the system should maintain referential integrity and prevent orphaned records
     */
    public function testReferentialIntegrityMaintenance() {
        try {
            // Generate test data
            $companyData = $this->generateCompanyData();
            $roleData = $this->generateRoleData();
            
            // Create company and role first
            $company = new Company();
            $role = new Role();
            
            $createdCompany = $company->create($companyData);
            $createdRole = $role->create($roleData);
            
            $this->assert($createdCompany !== null, "Company creation failed");
            $this->assert($createdRole !== null, "Role creation failed");
            
            // Create user with references to company and role
            $userData = $this->generateUserData($createdCompany['id'], $createdRole['id']);
            $user = new User();
            $createdUser = $user->create($userData);
            
            $this->assert($createdUser !== null, "User creation failed");
            $this->assert($createdUser['company_id'] == $createdCompany['id'], "User company_id reference incorrect");
            $this->assert($createdUser['role_id'] == $createdRole['id'], "User role_id reference incorrect");
            
            // Test that foreign key constraints prevent deletion of referenced records
            $companyDeleteAttempt = false;
            $roleDeleteAttempt = false;
            
            try {
                $company->delete($createdCompany['id']);
                $companyDeleteAttempt = true;
            } catch (Exception $e) {
                // Expected - should fail due to foreign key constraint
            }
            
            try {
                $role->delete($createdRole['id']);
                $roleDeleteAttempt = true;
            } catch (Exception $e) {
                // Expected - should fail due to foreign key constraint
            }
            
            // Verify that referenced records still exist
            $companyExists = $company->find($createdCompany['id']) !== null;
            $roleExists = $role->find($createdRole['id']) !== null;
            $userExists = $user->find($createdUser['id']) !== null;
            
            $this->assert($companyExists, "Company should still exist due to foreign key constraint");
            $this->assert($roleExists, "Role should still exist due to foreign key constraint");
            $this->assert($userExists, "User should still exist");
            
            // Test proper deletion order (delete user first, then referenced records)
            $userDeleted = $user->delete($createdUser['id']);
            $this->assert($userDeleted, "User deletion should succeed");
            
            // Now company and role deletion should succeed
            $companyDeleted = $company->delete($createdCompany['id']);
            $roleDeleted = $role->delete($createdRole['id']);
            
            $this->assert($companyDeleted, "Company deletion should succeed after user deletion");
            $this->assert($roleDeleted, "Role deletion should succeed after user deletion");
            
            // Verify records are actually deleted
            $this->assert($company->find($createdCompany['id']) === null, "Company should be deleted");
            $this->assert($role->find($createdRole['id']) === null, "Role should be deleted");
            $this->assert($user->find($createdUser['id']) === null, "User should be deleted");
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => [
                    'company_data' => $companyData ?? null,
                    'role_data' => $roleData ?? null,
                    'user_data' => $userData ?? null
                ]
            ];
        }
    }
    
    /**
     * Generate random company data
     */
    private function generateCompanyData() {
        return [
            'name' => 'Test Company ' . $this->generateRandomString(8),
            'type' => $this->generateRandomChoice(['ADV', 'CONTRACTOR']),
            'status' => $this->generateRandomChoice(['ACTIVE', 'INACTIVE']),
            'contact_email' => $this->generateRandomEmail(),
            'contact_phone' => '+1' . $this->generateRandomString(10, '0123456789'),
            'address' => $this->generateRandomString(50) . ' Street, Test City'
        ];
    }
    
    /**
     * Generate random role data
     */
    private function generateRoleData() {
        return [
            'name' => 'Test Role ' . $this->generateRandomString(8),
            'level' => $this->generateRandomInt(1, 10),
            'company_type' => $this->generateRandomChoice(['ADV', 'CONTRACTOR', 'BOTH']),
            'description' => 'Test role description ' . $this->generateRandomString(20),
            'is_active' => $this->generateRandomBool() ? 1 : 0
        ];
    }
    
    /**
     * Generate random user data
     */
    private function generateUserData($companyId, $roleId) {
        return [
            'username' => 'testuser' . $this->generateRandomString(8),
            'email' => $this->generateRandomEmail(),
            'password_hash' => password_hash('testpassword123', PASSWORD_DEFAULT),
            'first_name' => 'Test' . $this->generateRandomString(6),
            'last_name' => 'User' . $this->generateRandomString(6),
            'company_id' => $companyId,
            'role_id' => $roleId,
            'status' => $this->generateRandomChoice([0, 1, 2])
        ];
    }
    
    /**
     * Clean up any remaining test data
     */
    public function cleanupTestData() {
        try {
            // Clean up any test records that might have been left behind
            $this->db->query("DELETE FROM users WHERE username LIKE 'testuser%'");
            $this->db->query("DELETE FROM companies WHERE name LIKE 'Test Company %'");
            $this->db->query("DELETE FROM roles WHERE name LIKE 'Test Role %'");
        } catch (Exception $e) {
            // Ignore cleanup errors
            echo "Cleanup warning: " . $e->getMessage() . "\n";
        }
    }
}