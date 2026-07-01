<?php
/**
 * Property Test: Material Receipt Confirmation
 * 
 * **Feature: installation-module, Property 8: Material receipt confirmation updates status and records data**
 * **Validates: Requirements 4.2, 4.3**
 * 
 * Property: For any valid material receipt confirmation, the system should record the confirmation
 * with timestamp and engineer ID, and update the installation status to "materials_received".
 */

require_once __DIR__ . '/../config/autoload.php';
require_once __DIR__ . '/../services/MaterialReceiptService.php';
require_once __DIR__ . '/../services/InstallationService.php';
require_once __DIR__ . '/../models/Installation.php';

class MaterialReceiptConfirmationPropertyTest {
    private $testResults = [];
    private $iterations = 100;
    private $materialReceiptService;
    private $installationService;
    private $db;
    private $createdInstallationIds = [];
    private $createdSiteIds = [];
    private $createdFeasibilityIds = [];
    private $createdAssignmentIds = [];
    private $createdDelegationIds = [];
    private $createdMaterialReceiptIds = [];
    private $createdUserIds = [];
    private $testEngineerId = null;
    
    public function __construct() {
        $this->db = DatabaseConfig::getInstance();
        $this->materialReceiptService = new MaterialReceiptService();
        $this->installationService = new InstallationService();
        $this->setupTestUser();
    }
    
    /**
     * Setup test user for the tests
     */
    private function setupTestUser(): void {
        try {
            // Check if test user already exists
            $sql = "SELECT id FROM users WHERE email = 'test_engineer_material@test.com' LIMIT 1";
            $result = $this->db->getResults($sql, [], '');
            
            if (!empty($result)) {
                $this->testEngineerId = (int)$result[0]['id'];
            } else {
                // Get a valid role_id (engineer role or any role)
                $roleResult = $this->db->getResults("SELECT id FROM roles WHERE name LIKE '%engineer%' OR name LIKE '%Engineer%' LIMIT 1", [], '');
                if (empty($roleResult)) {
                    // Fallback to any role
                    $roleResult = $this->db->getResults("SELECT id FROM roles LIMIT 1", [], '');
                }
                $roleId = !empty($roleResult) ? (int)$roleResult[0]['id'] : 1;
                
                // Create a test engineer user (status = 1 for active)
                $sql = "INSERT INTO users (username, first_name, last_name, email, password_hash, company_id, role_id, status) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $this->db->executeQuery($sql, [
                    'test_engineer_material',
                    'Test',
                    'Engineer',
                    'test_engineer_material@test.com',
                    password_hash('password', PASSWORD_DEFAULT),
                    1,
                    $roleId,
                    1  // Integer status: 1 = active
                ], 'sssssiii');
                $this->testEngineerId = $this->db->getConnection()->insert_id;
                $stmt->close();
                $this->createdUserIds[] = $this->testEngineerId;
            }
        } catch (Exception $e) {
            echo "Warning: Failed to setup test user - " . $e->getMessage() . "\n";
            $this->testEngineerId = 1; // Fallback to ID 1
        }
    }
    
    /**
     * Run all property tests
     */
    public function runTests(): bool {
        echo "\n=== Material Receipt Confirmation Property Tests ===\n";
        echo "**Feature: installation-module, Property 8: Material receipt confirmation updates status and records data**\n";
        echo "**Validates: Requirements 4.2, 4.3**\n\n";
        
        // Property tests
        $this->runPropertyTest(
            'Material receipt confirmation records timestamp and engineer ID',
            [$this, 'testConfirmationRecordsTimestampAndEngineerId']
        );
        
        $this->runPropertyTest(
            'Material receipt confirmation updates status to materials_received',
            [$this, 'testConfirmationUpdatesStatusToMaterialsReceived']
        );
        
        $this->runPropertyTest(
            'Cannot confirm materials for non-pending_materials status',
            [$this, 'testCannotConfirmForNonPendingStatus']
        );
        
        $this->runPropertyTest(
            'Cannot confirm materials twice for same installation',
            [$this, 'testCannotConfirmMaterialsTwice']
        );
        
        // Cleanup
        $this->cleanup();
        
        // Summary
        echo "\n=== Test Summary ===\n";
        $passed = count(array_filter($this->testResults));
        $total = count($this->testResults);
        echo "Passed: $passed / $total\n";
        
        return $passed === $total;
    }
    
    /**
     * Run a property test with multiple iterations
     */
    private function runPropertyTest(string $name, callable $testFunction): void {
        echo "Testing: $name\n";
        $failures = [];
        
        for ($i = 0; $i < $this->iterations; $i++) {
            try {
                $result = $testFunction();
                if (!$result['success']) {
                    $failures[] = "Iteration $i: {$result['message']}";
                }
            } catch (Exception $e) {
                $failures[] = "Iteration $i: Exception - {$e->getMessage()}";
            }
        }
        
        if (empty($failures)) {
            echo "  ✓ Passed ({$this->iterations} iterations)\n";
            $this->testResults[$name] = true;
        } else {
            echo "  ✗ Failed\n";
            foreach (array_slice($failures, 0, 3) as $failure) {
                echo "    - $failure\n";
            }
            if (count($failures) > 3) {
                echo "    ... and " . (count($failures) - 3) . " more failures\n";
            }
            $this->testResults[$name] = false;
        }
    }

    
    /**
     * Property Test: Material receipt confirmation records timestamp and engineer ID
     * For any valid confirmation, the receipt should contain the engineer ID and a valid timestamp
     * 
     * Requirements: 4.2
     */
    private function testConfirmationRecordsTimestampAndEngineerId(): array {
        // Create test installation with pending_materials status
        $testData = $this->createTestInstallation(Installation::STATUS_PENDING_MATERIALS);
        if (!$testData['success']) {
            return $testData;
        }
        
        $installationId = $testData['installation_id'];
        $engineerId = $testData['engineer_id'];
        
        // Record time before confirmation
        $beforeTime = date('Y-m-d H:i:s');
        
        // Confirm material receipt
        $result = $this->materialReceiptService->confirmMaterialReceipt($installationId, $engineerId);
        
        // Record time after confirmation
        $afterTime = date('Y-m-d H:i:s');
        
        if (!$result['success']) {
            return [
                'success' => false,
                'message' => 'Failed to confirm material receipt: ' . $result['message']
            ];
        }
        
        $receipt = $result['data']['receipt'];
        $this->createdMaterialReceiptIds[] = $receipt['id'];
        
        // Verify engineer ID is recorded (Requirement 2.2)
        if ((int)$receipt['confirmed_by'] !== $engineerId) {
            return [
                'success' => false,
                'message' => "Expected confirmed_by $engineerId, got {$receipt['confirmed_by']}"
            ];
        }
        
        // Verify timestamp is recorded and within expected range (Requirement 2.2)
        if (empty($receipt['confirmed_at'])) {
            return [
                'success' => false,
                'message' => 'Timestamp (confirmed_at) was not recorded'
            ];
        }
        
        $confirmedAt = $receipt['confirmed_at'];
        if ($confirmedAt < $beforeTime || $confirmedAt > $afterTime) {
            return [
                'success' => false,
                'message' => "Timestamp $confirmedAt is not within expected range [$beforeTime, $afterTime]"
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * Property Test: Material receipt confirmation updates status to materials_received
     * For any valid confirmation, the installation status should be updated to "materials_received"
     * 
     * Requirements: 4.3
     */
    private function testConfirmationUpdatesStatusToMaterialsReceived(): array {
        // Create test installation with pending_materials status
        $testData = $this->createTestInstallation(Installation::STATUS_PENDING_MATERIALS);
        if (!$testData['success']) {
            return $testData;
        }
        
        $installationId = $testData['installation_id'];
        $engineerId = $testData['engineer_id'];
        
        // Confirm material receipt
        $result = $this->materialReceiptService->confirmMaterialReceipt($installationId, $engineerId);
        
        if (!$result['success']) {
            return [
                'success' => false,
                'message' => 'Failed to confirm material receipt: ' . $result['message']
            ];
        }
        
        $this->createdMaterialReceiptIds[] = $result['data']['receipt']['id'];
        
        // Verify returned status (Requirement 2.3)
        if ($result['data']['installation_status'] !== Installation::STATUS_MATERIALS_RECEIVED) {
            return [
                'success' => false,
                'message' => "Expected returned status 'materials_received', got '{$result['data']['installation_status']}'"
            ];
        }
        
        // Verify installation status in database (Requirement 2.3)
        $installation = $this->installationService->getInstallation($installationId);
        if ($installation['status'] !== Installation::STATUS_MATERIALS_RECEIVED) {
            return [
                'success' => false,
                'message' => "Expected installation status 'materials_received', got '{$installation['status']}'"
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * Property Test: Cannot confirm materials for non-pending_materials status
     * For any installation not in pending_materials status, confirmation should fail
     * 
     * Requirements: 4.1
     */
    private function testCannotConfirmForNonPendingStatus(): array {
        // Test with various non-pending statuses
        $nonPendingStatuses = [
            Installation::STATUS_MATERIALS_RECEIVED,
            Installation::STATUS_IN_PROGRESS,
            Installation::STATUS_SUBMITTED,
            Installation::STATUS_CONTRACTOR_APPROVED,
            Installation::STATUS_ADV_APPROVED
        ];
        $randomStatus = $nonPendingStatuses[array_rand($nonPendingStatuses)];
        
        // Create test installation with non-pending status
        $testData = $this->createTestInstallation($randomStatus);
        if (!$testData['success']) {
            return $testData;
        }
        
        $installationId = $testData['installation_id'];
        $engineerId = $testData['engineer_id'];
        
        // Attempt to confirm material receipt
        $result = $this->materialReceiptService->confirmMaterialReceipt($installationId, $engineerId);
        
        // Should fail
        if ($result['success']) {
            $this->createdMaterialReceiptIds[] = $result['data']['receipt']['id'];
            return [
                'success' => false,
                'message' => "Material receipt should not be confirmed for status '$randomStatus'"
            ];
        }
        
        // Verify correct error code
        if ($result['code'] !== 'INVALID_STATUS') {
            return [
                'success' => false,
                'message' => "Expected error code 'INVALID_STATUS', got '{$result['code']}'"
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * Property Test: Cannot confirm materials twice for same installation
     * For any installation that already has materials confirmed, confirmation should fail
     * 
     * Requirements: 4.2
     */
    private function testCannotConfirmMaterialsTwice(): array {
        // Create test installation with pending_materials status
        $testData = $this->createTestInstallation(Installation::STATUS_PENDING_MATERIALS);
        if (!$testData['success']) {
            return $testData;
        }
        
        $installationId = $testData['installation_id'];
        $engineerId = $testData['engineer_id'];
        
        // First confirmation should succeed
        $result1 = $this->materialReceiptService->confirmMaterialReceipt($installationId, $engineerId);
        if (!$result1['success']) {
            return [
                'success' => false,
                'message' => 'First confirmation failed: ' . $result1['message']
            ];
        }
        $this->createdMaterialReceiptIds[] = $result1['data']['receipt']['id'];
        
        // Second confirmation should fail
        $result2 = $this->materialReceiptService->confirmMaterialReceipt($installationId, $engineerId);
        if ($result2['success']) {
            $this->createdMaterialReceiptIds[] = $result2['data']['receipt']['id'];
            return [
                'success' => false,
                'message' => 'Duplicate material receipt confirmation should not be allowed'
            ];
        }
        
        // Verify correct error code (could be INVALID_STATUS or ALREADY_CONFIRMED)
        $validCodes = ['INVALID_STATUS', 'ALREADY_CONFIRMED'];
        if (!in_array($result2['code'], $validCodes)) {
            return [
                'success' => false,
                'message' => "Expected error code in [INVALID_STATUS, ALREADY_CONFIRMED], got '{$result2['code']}'"
            ];
        }
        
        return ['success' => true];
    }

    
    // ==================== Helper Methods ====================
    
    /**
     * Generate random string
     */
    private function generateRandomString(int $length = 10): string {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $result = '';
        for ($i = 0; $i < $length; $i++) {
            $result .= $chars[rand(0, strlen($chars) - 1)];
        }
        return $result;
    }
    
    /**
     * Create test installation with specified status
     */
    private function createTestInstallation(string $status): array {
        try {
            // Create a test site
            $siteName = 'TestSite-' . $this->generateRandomString(8);
            $sql = "INSERT INTO sites (site_name, lho, city, state, country, company_id, status) 
                    VALUES (?, ?, ?, ?, ?, ?, 'active')";
            $stmt = $this->db->executeQuery($sql, [
                $siteName,
                'LHO-' . $this->generateRandomString(4),
                'City-' . $this->generateRandomString(6),
                'State-' . $this->generateRandomString(6),
                'Country',
                1
            ], 'sssssi');
            $siteId = $this->db->getConnection()->insert_id;
            $stmt->close();
            $this->createdSiteIds[] = $siteId;
            
            // Create a site delegation
            $sql = "INSERT INTO site_delegations (site_id, contractor_id, delegated_by, status) 
                    VALUES (?, 1, 1, 'accepted')";
            $stmt = $this->db->executeQuery($sql, [$siteId], 'i');
            $delegationId = $this->db->getConnection()->insert_id;
            $stmt->close();
            $this->createdDelegationIds[] = $delegationId;
            
            // Create an engineer assignment
            $sql = "INSERT INTO engineer_assignments (site_id, delegation_id, engineer_id, assigned_by, status, feasibility_status) 
                    VALUES (?, ?, 1, 1, 'assigned', 'feasibility_completed')";
            $stmt = $this->db->executeQuery($sql, [$siteId, $delegationId], 'ii');
            $assignmentId = $this->db->getConnection()->insert_id;
            $stmt->close();
            $this->createdAssignmentIds[] = $assignmentId;
            
            // Create a feasibility check with ADV-approved status
            $sql = "INSERT INTO feasibility_checks (assignment_id, site_id, created_by, status, approval_status) 
                    VALUES (?, ?, 1, 'active', 'adv_approved')";
            $stmt = $this->db->executeQuery($sql, [$assignmentId, $siteId], 'ii');
            $feasibilityId = $this->db->getConnection()->insert_id;
            $stmt->close();
            $this->createdFeasibilityIds[] = $feasibilityId;
            
            // Create an installation with the specified status
            $sql = "INSERT INTO installations (site_id, feasibility_id, initiated_by, created_by, atm_id, status) 
                    VALUES (?, ?, 1, 1, ?, ?)";
            $stmt = $this->db->executeQuery($sql, [
                $siteId,
                $feasibilityId,
                'ATM-' . $this->generateRandomString(6),
                $status
            ], 'iiss');
            $installationId = $this->db->getConnection()->insert_id;
            $stmt->close();
            $this->createdInstallationIds[] = $installationId;
            
            return [
                'success' => true,
                'installation_id' => $installationId,
                'site_id' => $siteId,
                'feasibility_id' => $feasibilityId,
                'engineer_id' => $this->testEngineerId
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to create test data: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Cleanup test data
     */
    private function cleanup(): void {
        try {
            // Delete material receipts
            if (!empty($this->createdMaterialReceiptIds)) {
                $ids = implode(',', array_map('intval', $this->createdMaterialReceiptIds));
                $this->db->executeQuery("DELETE FROM installation_material_receipts WHERE id IN ($ids)", [], '');
            }
            
            // Delete installations
            if (!empty($this->createdInstallationIds)) {
                $ids = implode(',', array_map('intval', $this->createdInstallationIds));
                // First delete any material receipts for these installations
                $this->db->executeQuery("DELETE FROM installation_material_receipts WHERE installation_id IN ($ids)", [], '');
                $this->db->executeQuery("DELETE FROM installations WHERE id IN ($ids)", [], '');
            }
            
            // Delete feasibility checks
            if (!empty($this->createdFeasibilityIds)) {
                $ids = implode(',', array_map('intval', $this->createdFeasibilityIds));
                $this->db->executeQuery("DELETE FROM feasibility_checks WHERE id IN ($ids)", [], '');
            }
            
            // Delete engineer assignments
            if (!empty($this->createdAssignmentIds)) {
                $ids = implode(',', array_map('intval', $this->createdAssignmentIds));
                $this->db->executeQuery("DELETE FROM engineer_assignments WHERE id IN ($ids)", [], '');
            }
            
            // Delete site delegations
            if (!empty($this->createdDelegationIds)) {
                $ids = implode(',', array_map('intval', $this->createdDelegationIds));
                $this->db->executeQuery("DELETE FROM site_delegations WHERE id IN ($ids)", [], '');
            }
            
            // Delete sites
            if (!empty($this->createdSiteIds)) {
                $ids = implode(',', array_map('intval', $this->createdSiteIds));
                $this->db->executeQuery("DELETE FROM sites WHERE id IN ($ids)", [], '');
            }
            
            // Delete test users (only those created by this test)
            if (!empty($this->createdUserIds)) {
                $ids = implode(',', array_map('intval', $this->createdUserIds));
                $this->db->executeQuery("DELETE FROM users WHERE id IN ($ids)", [], '');
            }
        } catch (Exception $e) {
            echo "Warning: Cleanup failed - " . $e->getMessage() . "\n";
        }
    }
}

// Run tests if executed directly
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    $test = new MaterialReceiptConfirmationPropertyTest();
    $success = $test->runTests();
    exit($success ? 0 : 1);
}
