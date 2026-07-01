<?php
/**
 * Property Test for Audit Trail Completeness
 * **Feature: adv-crm-users-module, Property 14: Audit Trail Completeness**
 * **Validates: Requirements 4.5**
 */

require_once 'PropertyTestBase.php';

class AuditTrailCompletenessTest extends PropertyTestBase {
    private $permissionEngine;
    private $userModel;
    private $companyModel;
    private $permissionModel;
    private $testUsers = [];
    private $testCompanies = [];
    private $testPermissions = [];
    
    public function __construct() {
        parent::__construct();
        $this->permissionEngine = new PermissionEngine();
        $this->userModel = new User();
        $this->companyModel = new Company();
        $this->permissionModel = new Permission();
    }
    
    public function runTests() {
        echo "=== Audit Trail Completeness Tests ===\n";
        
        $this->setupTestData();
        
        $result = $this->runPropertyTest(
            "Audit Trail Completeness",
            [$this, 'testAuditTrailCompleteness']
        );
        
        $this->cleanupTestData();
        
        return $result;
    }
    
    /**
     * Property 14: Audit Trail Completeness
     * For any permission delegation or revocation operation, the system should create 
     * corresponding audit log entries with complete details
     */
    public function testAuditTrailCompleteness() {
        try {
            // Generate random test scenario
            $contractorCompany = $this->generateRandomChoice($this->testCompanies['contractors']);
            $permission = $this->generateRandomChoice($this->testPermissions);
            $advUser = $this->generateRandomChoice($this->testUsers['adv']);
            
            // First, ensure the permission is NOT delegated (clean state)
            // This handles cases where previous iterations left data behind
            $this->cleanupDelegation($contractorCompany['id'], $permission['id']);
            
            // Get initial audit log count
            $initialAuditCount = $this->getAuditLogCount($contractorCompany['id']);
            
            // Test 1: Delegation should create audit log entry
            $delegationResult = $this->permissionEngine->delegatePermission(
                $contractorCompany['id'], 
                $permission['name'], 
                $advUser['id']
            );
            
            if (!$delegationResult) {
                throw new Exception("Failed to delegate permission for test setup");
            }
            
            // Small delay to ensure different timestamps
            usleep(10000); // 10ms delay
            
            // Check audit log after delegation
            $auditCountAfterDelegation = $this->getAuditLogCount($contractorCompany['id']);
            $delegationAuditEntry = $this->getLatestAuditEntryByAction($contractorCompany['id'], 'DELEGATED');
            
            // Verify delegation audit entry completeness
            $delegationAuditComplete = (
                $delegationAuditEntry &&
                $delegationAuditEntry['action'] === 'DELEGATED' &&
                $delegationAuditEntry['company_id'] == $contractorCompany['id'] &&
                $delegationAuditEntry['permission_id'] == $permission['id'] &&
                $delegationAuditEntry['performed_by'] == $advUser['id'] &&
                !empty($delegationAuditEntry['timestamp'])
            );
            
            // Test 2: Revocation should create audit log entry
            $revocationResult = $this->permissionEngine->revokePermission(
                $contractorCompany['id'], 
                $permission['name'], 
                $advUser['id']
            );
            
            // Small delay to ensure different timestamps
            usleep(10000); // 10ms delay
            
            // Check audit log after revocation
            $auditCountAfterRevocation = $this->getAuditLogCount($contractorCompany['id']);
            $revocationAuditEntry = $this->getLatestAuditEntryByAction($contractorCompany['id'], 'REVOKED');
            
            // Verify revocation audit entry completeness
            $revocationAuditComplete = (
                $revocationAuditEntry &&
                $revocationAuditEntry['action'] === 'REVOKED' &&
                $revocationAuditEntry['company_id'] == $contractorCompany['id'] &&
                $revocationAuditEntry['permission_id'] == $permission['id'] &&
                $revocationAuditEntry['performed_by'] == $advUser['id'] &&
                !empty($revocationAuditEntry['timestamp'])
            );
            
            // Test 3: Verify audit trail completeness
            $auditTrail = $this->permissionEngine->getPermissionAuditTrail($contractorCompany['id'], 10);
            $auditTrailComplete = (
                count($auditTrail) >= 2 && // Should have at least delegation and revocation entries
                $this->verifyAuditTrailIntegrity($auditTrail)
            );
            
            // Test 4: Verify audit counts are correct
            $correctAuditCounts = (
                $auditCountAfterDelegation === ($initialAuditCount + 1) &&
                $auditCountAfterRevocation === ($initialAuditCount + 2)
            );
            
            // Verify the property holds
            $propertyHolds = (
                $delegationAuditComplete &&    // Delegation audit entry is complete
                $revocationAuditComplete &&    // Revocation audit entry is complete
                $auditTrailComplete &&         // Audit trail integrity is maintained
                $correctAuditCounts            // Audit counts are correct
            );
            
            if (!$propertyHolds) {
                return [
                    'success' => false,
                    'message' => 'Audit trail completeness failed',
                    'data' => [
                        'contractor_company' => $contractorCompany['name'],
                        'permission' => $permission['name'],
                        'initial_audit_count' => $initialAuditCount,
                        'audit_count_after_delegation' => $auditCountAfterDelegation,
                        'audit_count_after_revocation' => $auditCountAfterRevocation,
                        'delegation_audit_complete' => $delegationAuditComplete,
                        'revocation_audit_complete' => $revocationAuditComplete,
                        'audit_trail_complete' => $auditTrailComplete,
                        'correct_audit_counts' => $correctAuditCounts,
                        'delegation_entry' => $delegationAuditEntry,
                        'revocation_entry' => $revocationAuditEntry
                    ]
                ];
            }
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Exception during audit trail completeness test: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Clean up any existing delegation for a company/permission combination
     */
    private function cleanupDelegation($companyId, $permissionId) {
        $sql = "DELETE FROM company_permissions WHERE company_id = ? AND permission_id = ?";
        $this->executeQuery($sql, [$companyId, $permissionId], 'ii');
    }
    
    /**
     * Get audit log count for a company
     */
    private function getAuditLogCount($companyId) {
        $sql = "SELECT COUNT(*) as count FROM permission_audit_log WHERE company_id = ?";
        $result = $this->getResults($sql, [$companyId], 'i');
        return $result[0]['count'] ?? 0;
    }
    
    /**
     * Get latest audit entry for a company
     */
    private function getLatestAuditEntry($companyId) {
        $sql = "SELECT * FROM permission_audit_log 
                WHERE company_id = ? 
                ORDER BY id DESC 
                LIMIT 1";
        $result = $this->getResults($sql, [$companyId], 'i');
        return !empty($result) ? $result[0] : null;
    }
    
    /**
     * Get latest audit entry for a company by action type
     */
    private function getLatestAuditEntryByAction($companyId, $action) {
        $sql = "SELECT * FROM permission_audit_log 
                WHERE company_id = ? AND action = ?
                ORDER BY id DESC 
                LIMIT 1";
        $result = $this->getResults($sql, [$companyId, $action], 'is');
        return !empty($result) ? $result[0] : null;
    }
    
    /**
     * Verify audit trail integrity
     */
    private function verifyAuditTrailIntegrity($auditTrail) {
        foreach ($auditTrail as $entry) {
            // Check required fields are present
            if (empty($entry['company_id']) || 
                empty($entry['permission_id']) || 
                empty($entry['action']) || 
                empty($entry['performed_by']) || 
                empty($entry['timestamp'])) {
                return false;
            }
            
            // Check action is valid
            if (!in_array($entry['action'], ['DELEGATED', 'REVOKED'])) {
                return false;
            }
            
            // Check timestamp format
            if (!strtotime($entry['timestamp'])) {
                return false;
            }
        }
        
        return true;
    }
    
    private function setupTestData() {
        // Create test ADV company
        $advCompanyData = [
            'name' => 'Test ADV Company Audit',
            'type' => 'ADV',
            'status' => 'ACTIVE'
        ];
        $advCompanyRecord = $this->companyModel->create($advCompanyData);
        $this->testCompanies['adv'] = $advCompanyRecord;
        
        // Create test contractor companies
        $this->testCompanies['contractors'] = [];
        for ($i = 0; $i < 3; $i++) {
            $contractorData = [
                'name' => 'Test Contractor Audit ' . ($i + 1),
                'type' => 'CONTRACTOR',
                'status' => 'ACTIVE'
            ];
            $contractorRecord = $this->companyModel->create($contractorData);
            $this->testCompanies['contractors'][] = $contractorRecord;
        }
        
        // Create test ADV users
        $this->testUsers['adv'] = [];
        for ($i = 0; $i < 2; $i++) {
            $userData = [
                'username' => 'testadvaudit' . $i . '_' . time() . '_' . mt_rand(1000, 9999),
                'email' => 'testadvaudit' . $i . '_' . time() . '_' . mt_rand(1000, 9999) . '@test.com',
                'password_hash' => password_hash('password123', PASSWORD_DEFAULT),
                'first_name' => 'Test',
                'last_name' => 'ADV Audit ' . $i,
                'company_id' => $this->testCompanies['adv']['id'],
                'role_id' => 1, // Assume role 1 is ADV Super Admin
                'status' => 1
            ];
            $userRecord = $this->userModel->create($userData);
            $this->testUsers['adv'][] = $this->userModel->findWithRelations($userRecord['id']);
        }
        
        // Get available permissions for testing
        $this->testPermissions = $this->permissionModel->findContractorAccessible();
        if (empty($this->testPermissions)) {
            // Create some test permissions if none exist
            $testPerms = [
                ['name' => 'audit.view', 'module' => 'audit', 'action' => 'view', 'description' => 'View audit', 'is_adv_only' => 0],
                ['name' => 'audit.create', 'module' => 'audit', 'action' => 'create', 'description' => 'Create audit', 'is_adv_only' => 0],
                ['name' => 'audit.edit', 'module' => 'audit', 'action' => 'edit', 'description' => 'Edit audit', 'is_adv_only' => 0]
            ];
            
            foreach ($testPerms as $perm) {
                $permId = $this->permissionModel->create($perm);
                $this->testPermissions[] = $this->permissionModel->find($permId);
            }
        }
    }
    
    protected function cleanupTestData() {
        // Clean up in correct order to avoid foreign key constraints
        
        // First, clean up delegation records and audit logs (they reference users and companies)
        if (!empty($this->testCompanies['contractors'])) {
            foreach ($this->testCompanies['contractors'] as $company) {
                $this->executeQuery("DELETE FROM company_permissions WHERE company_id = ?", [$company['id']], 'i');
                $this->executeQuery("DELETE FROM permission_audit_log WHERE company_id = ?", [$company['id']], 'i');
            }
        }
        
        // Clean up test users
        if (!empty($this->testUsers['adv'])) {
            foreach ($this->testUsers['adv'] as $user) {
                $this->executeQuery("DELETE FROM users WHERE id = ?", [$user['id']], 'i');
            }
        }
        
        // Clean up test companies
        if (isset($this->testCompanies['adv'])) {
            $this->executeQuery("DELETE FROM companies WHERE id = ?", [$this->testCompanies['adv']['id']], 'i');
        }
        
        if (!empty($this->testCompanies['contractors'])) {
            foreach ($this->testCompanies['contractors'] as $company) {
                $this->executeQuery("DELETE FROM companies WHERE id = ?", [$company['id']], 'i');
            }
        }
        
        // Clean up test permissions (if we created them)
        $this->executeQuery("DELETE FROM permissions WHERE name LIKE 'audit.%' AND description LIKE '%audit%'");
    }
}

// Run the test if called directly
if (basename(__FILE__) == basename($_SERVER['SCRIPT_NAME'])) {
    $test = new AuditTrailCompletenessTest();
    $result = $test->runTests();
    exit($result ? 0 : 1);
}