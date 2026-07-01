<?php
/**
 * Integration Tests for Permission Delegation
 * Tests the complete permission delegation workflow including:
 * - Permission granting workflow
 * - Permission revocation workflow
 * - Permission audit trail functionality
 * 
 * **Validates: Requirements 4.1, 4.3, 4.5**
 */

require_once __DIR__ . '/PropertyTestBase.php';

class PermissionDelegationIntegrationTest extends PropertyTestBase {
    private $permissionEngine;
    private $userModel;
    private $companyModel;
    private $permissionModel;
    private $testData = [];
    
    public function __construct() {
        parent::__construct();
        $this->permissionEngine = new PermissionEngine();
        $this->userModel = new User();
        $this->companyModel = new Company();
        $this->permissionModel = new Permission();
    }
    
    public function runTests() {
        echo "=== Permission Delegation Integration Tests ===\n\n";
        
        $this->setupTestData();
        
        $results = [];
        
        // Test 1: Permission Granting Workflow
        echo "Test 1: Permission Granting Workflow\n";
        $results['granting'] = $this->testPermissionGrantingWorkflow();
        echo $results['granting'] ? "✓ PASSED\n\n" : "✗ FAILED\n\n";
        
        // Test 2: Permission Revocation Workflow
        echo "Test 2: Permission Revocation Workflow\n";
        $results['revocation'] = $this->testPermissionRevocationWorkflow();
        echo $results['revocation'] ? "✓ PASSED\n\n" : "✗ FAILED\n\n";
        
        // Test 3: Permission Audit Trail Functionality
        echo "Test 3: Permission Audit Trail Functionality\n";
        $results['audit'] = $this->testPermissionAuditTrailFunctionality();
        echo $results['audit'] ? "✓ PASSED\n\n" : "✗ FAILED\n\n";
        
        // Test 4: Bulk Permission Operations
        echo "Test 4: Bulk Permission Operations\n";
        $results['bulk'] = $this->testBulkPermissionOperations();
        echo $results['bulk'] ? "✓ PASSED\n\n" : "✗ FAILED\n\n";
        
        // Test 5: Permission Delegation Constraints
        echo "Test 5: Permission Delegation Constraints\n";
        $results['constraints'] = $this->testPermissionDelegationConstraints();
        echo $results['constraints'] ? "✓ PASSED\n\n" : "✗ FAILED\n\n";
        
        $this->cleanupTestData();
        
        // Summary
        $passed = count(array_filter($results));
        $total = count($results);
        echo "=== Summary: $passed/$total tests passed ===\n";
        
        return $passed === $total;
    }
    
    /**
     * Test 1: Permission Granting Workflow
     * Tests that ADV users can successfully grant permissions to contractor companies
     * **Validates: Requirements 4.1**
     */
    private function testPermissionGrantingWorkflow() {
        try {
            $advUser = $this->testData['advUser'];
            $contractorCompany = $this->testData['contractorCompany'];
            $permission = $this->testData['testPermission'];
            
            // Step 1: Verify permission is not delegated initially
            $hasBefore = $this->permissionModel->companyHasPermission(
                $contractorCompany['id'], 
                $permission['name']
            );
            
            if ($hasBefore) {
                echo "  - Initial state check failed: permission already exists\n";
                return false;
            }
            echo "  - Initial state verified: permission not delegated\n";
            
            // Step 2: Delegate permission
            $result = $this->permissionEngine->delegatePermission(
                $contractorCompany['id'],
                $permission['name'],
                $advUser['id']
            );
            
            if (!$result) {
                echo "  - Delegation failed\n";
                return false;
            }
            echo "  - Permission delegated successfully\n";
            
            // Step 3: Verify permission is now delegated
            $hasAfter = $this->permissionModel->companyHasPermission(
                $contractorCompany['id'], 
                $permission['name']
            );
            
            if (!$hasAfter) {
                echo "  - Post-delegation check failed: permission not found\n";
                return false;
            }
            echo "  - Post-delegation verified: permission exists\n";
            
            // Step 4: Verify contractor user can now use the permission
            $contractorUser = $this->testData['contractorUser'];
            $canUse = $this->permissionEngine->can($contractorUser['id'], $permission['name']);
            
            if (!$canUse) {
                echo "  - Contractor user cannot use delegated permission\n";
                return false;
            }
            echo "  - Contractor user can use delegated permission\n";
            
            return true;
            
        } catch (Exception $e) {
            echo "  - Exception: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * Test 2: Permission Revocation Workflow
     * Tests that ADV users can revoke permissions and the effect is immediate
     * **Validates: Requirements 4.3**
     */
    private function testPermissionRevocationWorkflow() {
        try {
            $advUser = $this->testData['advUser'];
            $contractorCompany = $this->testData['contractorCompany'];
            $contractorUser = $this->testData['contractorUser'];
            
            // Create a new permission for this test
            $permData = [
                'name' => 'test.revoke_' . time(),
                'module' => 'test',
                'action' => 'revoke_' . time(),
                'description' => 'Test revocation permission',
                'is_adv_only' => 0
            ];
            $permRecord = $this->permissionModel->create($permData);
            $permission = $this->permissionModel->find($permRecord['id']);
            $this->testData['revokePermission'] = $permission;
            
            // Step 1: Delegate permission first
            $this->permissionEngine->delegatePermission(
                $contractorCompany['id'],
                $permission['name'],
                $advUser['id']
            );
            
            // Verify delegation
            $hasBefore = $this->permissionEngine->can($contractorUser['id'], $permission['name']);
            if (!$hasBefore) {
                echo "  - Setup failed: permission not delegated\n";
                return false;
            }
            echo "  - Setup verified: permission delegated\n";
            
            // Step 2: Revoke permission
            $result = $this->permissionEngine->revokePermission(
                $contractorCompany['id'],
                $permission['name'],
                $advUser['id']
            );
            
            if (!$result) {
                echo "  - Revocation failed\n";
                return false;
            }
            echo "  - Permission revoked successfully\n";
            
            // Step 3: Verify immediate effect - contractor user should NOT have permission
            $hasAfter = $this->permissionEngine->can($contractorUser['id'], $permission['name']);
            
            if ($hasAfter) {
                echo "  - Revocation not immediate: user still has permission\n";
                return false;
            }
            echo "  - Immediate effect verified: user no longer has permission\n";
            
            // Step 4: Verify company no longer has delegated permission
            $companyHas = $this->permissionModel->companyHasPermission(
                $contractorCompany['id'],
                $permission['name']
            );
            
            if ($companyHas) {
                echo "  - Company still has permission after revocation\n";
                return false;
            }
            echo "  - Company permission removed\n";
            
            return true;
            
        } catch (Exception $e) {
            echo "  - Exception: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * Test 3: Permission Audit Trail Functionality
     * Tests that all permission operations are properly logged
     * **Validates: Requirements 4.5**
     */
    private function testPermissionAuditTrailFunctionality() {
        try {
            $advUser = $this->testData['advUser'];
            $contractorCompany = $this->testData['contractorCompany'];
            
            // Create a unique permission for audit testing
            $uniqueId = time() . '_' . rand(1000, 9999);
            $permData = [
                'name' => "test.audit_$uniqueId",
                'module' => 'test',
                'action' => "audit_$uniqueId",
                'description' => 'Test audit permission',
                'is_adv_only' => 0
            ];
            $permRecord = $this->permissionModel->create($permData);
            $permission = $this->permissionModel->find($permRecord['id']);
            $this->testData['auditPermission'] = $permission;
            
            // Get initial audit count
            $initialAudit = $this->permissionEngine->getPermissionAuditTrail($contractorCompany['id'], 1000);
            $initialCount = count($initialAudit);
            
            // Step 1: Delegate permission
            $this->permissionEngine->delegatePermission(
                $contractorCompany['id'],
                $permission['name'],
                $advUser['id']
            );
            
            // Check audit trail for delegation
            $afterDelegate = $this->permissionEngine->getPermissionAuditTrail($contractorCompany['id'], 1000);
            $afterDelegateCount = count($afterDelegate);
            
            if ($afterDelegateCount <= $initialCount) {
                echo "  - Delegation not logged in audit trail\n";
                return false;
            }
            echo "  - Delegation logged in audit trail\n";
            
            // Verify the audit entry contains correct information
            $latestEntry = $afterDelegate[0];
            if ($latestEntry['action'] !== 'DELEGATED') {
                echo "  - Audit entry action incorrect: expected DELEGATED, got {$latestEntry['action']}\n";
                return false;
            }
            echo "  - Audit entry action verified: DELEGATED\n";
            
            // Step 2: Revoke permission
            $this->permissionEngine->revokePermission(
                $contractorCompany['id'],
                $permission['name'],
                $advUser['id']
            );
            
            // Check audit trail for revocation
            $afterRevoke = $this->permissionEngine->getPermissionAuditTrail($contractorCompany['id'], 1000);
            $afterRevokeCount = count($afterRevoke);
            
            if ($afterRevokeCount <= $afterDelegateCount) {
                echo "  - Revocation not logged in audit trail\n";
                return false;
            }
            echo "  - Revocation logged in audit trail\n";
            
            // Find the revocation entry (should be one of the recent entries)
            $foundRevokeEntry = null;
            foreach ($afterRevoke as $entry) {
                if ($entry['action'] === 'REVOKED' && $entry['permission_name'] === $permission['name']) {
                    $foundRevokeEntry = $entry;
                    break;
                }
            }
            
            if (!$foundRevokeEntry) {
                echo "  - REVOKED action not found in audit trail\n";
                return false;
            }
            echo "  - Audit entry action verified: REVOKED\n";
            
            // Verify performer is recorded
            if ($foundRevokeEntry['performed_by'] != $advUser['id']) {
                echo "  - Performer not correctly recorded\n";
                return false;
            }
            echo "  - Performer correctly recorded\n";
            
            return true;
            
        } catch (Exception $e) {
            echo "  - Exception: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * Test 4: Bulk Permission Operations
     * Tests bulk delegation and revocation functionality
     */
    private function testBulkPermissionOperations() {
        try {
            $advUser = $this->testData['advUser'];
            $contractorCompany = $this->testData['contractorCompany'];
            $contractorUser = $this->testData['contractorUser'];
            
            // Create multiple test permissions
            $bulkPermissions = [];
            for ($i = 0; $i < 3; $i++) {
                $uniqueId = time() . '_bulk_' . $i;
                $permData = [
                    'name' => "test.bulk_$uniqueId",
                    'module' => 'test_bulk',
                    'action' => "bulk_$uniqueId",
                    'description' => 'Test bulk permission ' . $i,
                    'is_adv_only' => 0
                ];
                $permRecord = $this->permissionModel->create($permData);
                $bulkPermissions[] = $this->permissionModel->find($permRecord['id']);
            }
            $this->testData['bulkPermissions'] = $bulkPermissions;
            
            // Step 1: Delegate all permissions
            foreach ($bulkPermissions as $perm) {
                $this->permissionEngine->delegatePermission(
                    $contractorCompany['id'],
                    $perm['name'],
                    $advUser['id']
                );
            }
            
            // Verify all delegated
            $allDelegated = true;
            foreach ($bulkPermissions as $perm) {
                if (!$this->permissionEngine->can($contractorUser['id'], $perm['name'])) {
                    $allDelegated = false;
                    break;
                }
            }
            
            if (!$allDelegated) {
                echo "  - Bulk delegation failed: not all permissions delegated\n";
                return false;
            }
            echo "  - Bulk delegation successful: all permissions delegated\n";
            
            // Step 2: Revoke all permissions
            foreach ($bulkPermissions as $perm) {
                $this->permissionEngine->revokePermission(
                    $contractorCompany['id'],
                    $perm['name'],
                    $advUser['id']
                );
            }
            
            // Verify all revoked
            $allRevoked = true;
            foreach ($bulkPermissions as $perm) {
                if ($this->permissionEngine->can($contractorUser['id'], $perm['name'])) {
                    $allRevoked = false;
                    break;
                }
            }
            
            if (!$allRevoked) {
                echo "  - Bulk revocation failed: not all permissions revoked\n";
                return false;
            }
            echo "  - Bulk revocation successful: all permissions revoked\n";
            
            return true;
            
        } catch (Exception $e) {
            echo "  - Exception: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * Test 5: Permission Delegation Constraints
     * Tests that delegation constraints are enforced
     * **Validates: Requirements 4.4**
     */
    private function testPermissionDelegationConstraints() {
        try {
            $contractorUser = $this->testData['contractorUser'];
            $contractorCompany = $this->testData['contractorCompany'];
            
            // Create a permission for constraint testing
            $uniqueId = time() . '_constraint';
            $permData = [
                'name' => "test.constraint_$uniqueId",
                'module' => 'test',
                'action' => "constraint_$uniqueId",
                'description' => 'Test constraint permission',
                'is_adv_only' => 0
            ];
            $permRecord = $this->permissionModel->create($permData);
            $permission = $this->permissionModel->find($permRecord['id']);
            $this->testData['constraintPermission'] = $permission;
            
            // Step 1: Contractor user should NOT be able to delegate permissions
            $exceptionThrown = false;
            try {
                $this->permissionEngine->delegatePermission(
                    $contractorCompany['id'],
                    $permission['name'],
                    $contractorUser['id']
                );
            } catch (Exception $e) {
                $exceptionThrown = true;
                if (strpos($e->getMessage(), 'Only ADV') === false) {
                    echo "  - Wrong exception message: " . $e->getMessage() . "\n";
                    return false;
                }
            }
            
            if (!$exceptionThrown) {
                echo "  - Contractor was able to delegate permission (should be denied)\n";
                return false;
            }
            echo "  - Contractor delegation correctly denied\n";
            
            // Step 2: Test delegation to non-contractor company should fail
            $advCompany = $this->testData['advCompany'];
            $advUser = $this->testData['advUser'];
            
            $exceptionThrown = false;
            try {
                $this->permissionEngine->delegatePermission(
                    $advCompany['id'],
                    $permission['name'],
                    $advUser['id']
                );
            } catch (Exception $e) {
                $exceptionThrown = true;
                if (strpos($e->getMessage(), 'contractor') === false) {
                    echo "  - Wrong exception for ADV company delegation: " . $e->getMessage() . "\n";
                    return false;
                }
            }
            
            if (!$exceptionThrown) {
                echo "  - Delegation to ADV company was allowed (should be denied)\n";
                return false;
            }
            echo "  - Delegation to ADV company correctly denied\n";
            
            // Step 3: Test delegation of non-existent permission
            $exceptionThrown = false;
            try {
                $this->permissionEngine->delegatePermission(
                    $contractorCompany['id'],
                    'nonexistent.permission',
                    $advUser['id']
                );
            } catch (Exception $e) {
                $exceptionThrown = true;
            }
            
            if (!$exceptionThrown) {
                echo "  - Non-existent permission delegation was allowed\n";
                return false;
            }
            echo "  - Non-existent permission delegation correctly denied\n";
            
            return true;
            
        } catch (Exception $e) {
            echo "  - Exception: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    private function setupTestData() {
        echo "Setting up test data...\n";
        
        // Get or create ADV company
        $advCompanies = $this->companyModel->findByType('ADV');
        if (!empty($advCompanies)) {
            $this->testData['advCompany'] = $advCompanies[0];
        } else {
            $advData = ['name' => 'Test ADV Integration', 'type' => 'ADV', 'status' => 'ACTIVE'];
            $advRecord = $this->companyModel->create($advData);
            $this->testData['advCompany'] = $this->companyModel->find($advRecord['id']);
        }
        
        // Create contractor company
        $contractorData = [
            'name' => 'Test Contractor Integration ' . time(),
            'type' => 'CONTRACTOR',
            'status' => 'ACTIVE'
        ];
        $contractorRecord = $this->companyModel->create($contractorData);
        $this->testData['contractorCompany'] = $this->companyModel->find($contractorRecord['id']);
        
        // Create ADV user
        $advUserData = [
            'username' => 'testadvint_' . time(),
            'email' => 'testadvint_' . time() . '@test.com',
            'password_hash' => password_hash('password123', PASSWORD_DEFAULT),
            'first_name' => 'Test',
            'last_name' => 'ADV Int',
            'company_id' => $this->testData['advCompany']['id'],
            'role_id' => 1,
            'status' => 1
        ];
        $advUserRecord = $this->userModel->create($advUserData);
        $this->testData['advUser'] = $this->userModel->findWithRelations($advUserRecord['id']);
        
        // Create contractor user
        $contractorUserData = [
            'username' => 'testcontractorint_' . time(),
            'email' => 'testcontractorint_' . time() . '@test.com',
            'password_hash' => password_hash('password123', PASSWORD_DEFAULT),
            'first_name' => 'Test',
            'last_name' => 'Contractor Int',
            'company_id' => $this->testData['contractorCompany']['id'],
            'role_id' => 5,
            'status' => 1
        ];
        $contractorUserRecord = $this->userModel->create($contractorUserData);
        $this->testData['contractorUser'] = $this->userModel->findWithRelations($contractorUserRecord['id']);
        
        // Create test permission
        $permData = [
            'name' => 'test.integration_' . time(),
            'module' => 'test',
            'action' => 'integration_' . time(),
            'description' => 'Test integration permission',
            'is_adv_only' => 0
        ];
        $permRecord = $this->permissionModel->create($permData);
        $this->testData['testPermission'] = $this->permissionModel->find($permRecord['id']);
        
        echo "Test data setup complete.\n\n";
    }
    
    protected function cleanupTestData() {
        echo "\nCleaning up test data...\n";
        
        // Clean up company permissions
        if (isset($this->testData['contractorCompany'])) {
            $this->executeQuery(
                "DELETE FROM company_permissions WHERE company_id = ?",
                [$this->testData['contractorCompany']['id']],
                'i'
            );
            $this->executeQuery(
                "DELETE FROM permission_audit_log WHERE company_id = ?",
                [$this->testData['contractorCompany']['id']],
                'i'
            );
        }
        
        // Clean up users
        if (isset($this->testData['advUser'])) {
            $this->executeQuery("DELETE FROM users WHERE id = ?", [$this->testData['advUser']['id']], 'i');
        }
        if (isset($this->testData['contractorUser'])) {
            $this->executeQuery("DELETE FROM users WHERE id = ?", [$this->testData['contractorUser']['id']], 'i');
        }
        
        // Clean up contractor company
        if (isset($this->testData['contractorCompany'])) {
            $this->executeQuery("DELETE FROM companies WHERE id = ?", [$this->testData['contractorCompany']['id']], 'i');
        }
        
        // Clean up test permissions
        $this->executeQuery("DELETE FROM permissions WHERE name LIKE 'test.%' AND description LIKE '%Test%'");
        
        echo "Cleanup complete.\n";
    }
}

// Run the test if called directly
if (basename(__FILE__) == basename($_SERVER['SCRIPT_NAME'])) {
    $test = new PermissionDelegationIntegrationTest();
    $result = $test->runTests();
    exit($result ? 0 : 1);
}
