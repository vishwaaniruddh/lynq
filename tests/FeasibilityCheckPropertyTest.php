<?php
/**
 * Property Test for Feasibility Check Operations
 * **Feature: feasibility-module, Property 7: Feasibility form required field validation**
 * **Feature: feasibility-module, Property 8: Feasibility submission round-trip**
 * **Validates: Requirements 4.3, 4.4**
 */

require_once 'PropertyTestBase.php';
require_once __DIR__ . '/../services/FeasibilityService.php';
require_once __DIR__ . '/../services/ADAService.php';
require_once __DIR__ . '/../services/ETAService.php';
require_once __DIR__ . '/../services/SiteService.php';
require_once __DIR__ . '/../services/DelegationService.php';
require_once __DIR__ . '/../services/EngineerAssignmentService.php';

class FeasibilityCheckPropertyTest extends PropertyTestBase {
    
    private $feasibilityService;
    private $adaService;
    private $etaService;
    private $siteService;
    private $delegationService;
    private $assignmentService;
    private $testAdvCompanyId;
    private $testContractorId;
    private $testEngineerId;
    private $testAdminUserId;
    private $createdSiteIds = [];
    private $createdDelegationIds = [];
    private $createdAssignmentIds = [];
    private $createdETAIds = [];
    private $createdADAIds = [];
    private $createdFeasibilityIds = [];
    
    // Required fields for feasibility check
    private $requiredFields = [
        'no_of_atm',
        'operator',
        'signal_status',
        'ups_available',
        'earthing'
    ];
    
    public function __construct() {
        parent::__construct();
        $this->feasibilityService = new FeasibilityService();
        $this->adaService = new ADAService();
        $this->etaService = new ETAService();
        $this->siteService = new SiteService();
        $this->delegationService = new DelegationService();
        $this->assignmentService = new EngineerAssignmentService();
        $this->setupTestData();
    }
    
    /**
     * Setup test companies and users
     */
    private function setupTestData(): void {
        // Get or create ADV company
        $result = $this->getResults("SELECT id FROM companies WHERE type = 'adv' AND status = 1 LIMIT 1");
        if (!empty($result)) {
            $this->testAdvCompanyId = (int)$result[0]['id'];
        } else {
            $stmt = $this->executeQuery(
                "INSERT INTO companies (name, type, status) VALUES (?, ?, ?)",
                ['Test ADV Company ' . uniqid(), 'adv', 1],
                'ssi'
            );
            $this->testAdvCompanyId = $this->db->insert_id;
            $stmt->close();
        }
        
        // Get or create contractor company
        $result = $this->getResults("SELECT id FROM companies WHERE type = 'contractor' AND status = 1 LIMIT 1");
        if (!empty($result)) {
            $this->testContractorId = (int)$result[0]['id'];
        } else {
            $stmt = $this->executeQuery(
                "INSERT INTO companies (name, type, status) VALUES (?, ?, ?)",
                ['Test Contractor ' . uniqid(), 'contractor', 1],
                'ssi'
            );
            $this->testContractorId = $this->db->insert_id;
            $stmt->close();
        }
        
        // Get or create admin user
        $result = $this->getResults("SELECT id FROM users WHERE status = 1 LIMIT 1");
        if (!empty($result)) {
            $this->testAdminUserId = (int)$result[0]['id'];
        } else {
            $this->testAdminUserId = 1;
        }
        
        // Get or create engineer user
        $result = $this->getResults("SELECT id FROM users WHERE company_id = ? AND status = 1 LIMIT 1", [$this->testContractorId], 'i');
        if (!empty($result)) {
            $this->testEngineerId = (int)$result[0]['id'];
        } else {
            $stmt = $this->executeQuery(
                "INSERT INTO users (first_name, last_name, email, password, company_id, status) VALUES (?, ?, ?, ?, ?, ?)",
                ['Test', 'Engineer', 'engineer_feas_' . uniqid() . '@test.com', password_hash('password', PASSWORD_DEFAULT), $this->testContractorId, 1],
                'ssssii'
            );
            $this->testEngineerId = $this->db->insert_id;
            $stmt->close();
        }
    }
    
    /**
     * Create a test site with delegation, assignment, ETA, and ADA
     */
    private function createTestAssignmentWithADA(): ?array {
        // Create site
        $siteData = [
            'site_name' => 'Site_' . $this->generateRandomString(10),
            'lho' => 'LHO_' . $this->generateRandomString(5),
            'bank_name' => 'Bank_' . $this->generateRandomString(8),
            'customer_name' => 'Customer_' . $this->generateRandomString(8),
            'city' => 'City_' . $this->generateRandomString(6),
            'state' => 'State_' . $this->generateRandomString(6),
            'country' => 'Country_' . $this->generateRandomString(6),
            'zone' => 'Zone_' . $this->generateRandomString(4),
            'address' => 'Address ' . $this->generateRandomString(20),
            'latitude' => round((rand(-9000000, 9000000) / 100000), 6),
            'longitude' => round((rand(-18000000, 18000000) / 100000), 6),
            'company_id' => $this->testAdvCompanyId
        ];
        
        $siteResult = $this->siteService->createSite($siteData, $this->testAdminUserId);
        if (!$siteResult['success']) {
            return null;
        }
        $this->createdSiteIds[] = $siteResult['data']['id'];
        
        // Create delegation
        $delegationResult = $this->delegationService->delegateSite(
            $siteResult['data']['id'],
            $this->testContractorId,
            $this->testAdminUserId
        );
        if (!$delegationResult['success']) {
            return null;
        }
        $this->createdDelegationIds[] = $delegationResult['data']['id'];
        
        // Accept delegation
        $this->delegationService->acceptDelegation($delegationResult['data']['id'], $this->testAdminUserId);
        
        // Create assignment
        $assignmentResult = $this->assignmentService->assignToEngineer(
            $siteResult['data']['id'],
            $this->testEngineerId,
            $this->testAdminUserId,
            $this->testContractorId
        );
        if (!$assignmentResult['success']) {
            return null;
        }
        $this->createdAssignmentIds[] = $assignmentResult['data']['id'];
        
        // Submit ETA
        $etaDateTime = date('Y-m-d H:i:s', strtotime('+1 day'));
        $etaResult = $this->etaService->submitETA(
            $assignmentResult['data']['id'],
            $etaDateTime,
            $this->testEngineerId
        );
        if ($etaResult['success']) {
            $this->createdETAIds[] = $etaResult['data']['id'];
        }
        
        // Submit ADA
        $latitude = round((rand(-8900000, 8900000) / 100000), 6);
        $longitude = round((rand(-17900000, 17900000) / 100000), 6);
        if ($latitude == 0 && $longitude == 0) $latitude = 0.001;
        
        $adaResult = $this->adaService->submitADA(
            $assignmentResult['data']['id'],
            $latitude,
            $longitude,
            $this->testEngineerId
        );
        if ($adaResult['success']) {
            $this->createdADAIds[] = $adaResult['data']['id'];
        }
        
        return $assignmentResult['data'];
    }
    
    /**
     * Generate valid feasibility data
     */
    private function generateValidFeasibilityData(): array {
        return [
            'no_of_atm' => rand(1, 3),
            'atm_id_1' => 'ATM_' . $this->generateRandomString(8),
            'atm_1_status' => $this->generateRandomChoice(['working', 'not_working', 'maintenance']),
            'operator' => $this->generateRandomChoice(['Airtel', 'Jio', 'Vi', 'BSNL']),
            'signal_status' => $this->generateRandomChoice(['excellent', 'good', 'poor', 'no_signal']),
            'operator_2' => $this->generateRandomChoice(['Airtel', 'Jio', 'Vi', 'BSNL', '']),
            'signal_status_2' => $this->generateRandomChoice(['excellent', 'good', 'poor', 'no_signal', '']),
            'backroom_network_remark' => 'Network remark ' . $this->generateRandomString(20),
            'ups_available' => $this->generateRandomChoice(['yes', 'no']),
            'no_of_ups' => rand(0, 3),
            'ups_battery_backup' => $this->generateRandomChoice(['30min', '1hr', '2hr', '4hr']),
            'ups_working_1' => $this->generateRandomChoice(['yes', 'no']),
            'power_socket_availability' => $this->generateRandomChoice(['available', 'not_available']),
            'earthing' => $this->generateRandomChoice(['yes', 'no']),
            'earthing_voltage' => rand(0, 5) . 'V',
            'power_fluctuation_en' => rand(200, 250) . 'V',
            'power_fluctuation_pe' => rand(0, 5) . 'V',
            'power_fluctuation_pn' => rand(200, 250) . 'V',
            'frequent_power_cut' => $this->generateRandomChoice(['yes', 'no']),
            'em_lock_available' => $this->generateRandomChoice(['yes', 'no']),
            'remarks' => 'Test remarks ' . $this->generateRandomString(50)
        ];
    }
    
    public function runTests(): bool {
        echo "=== Feasibility Check Property Tests ===\n\n";
        
        $allPassed = true;
        
        // Property 7: Feasibility form required field validation
        $allPassed &= $this->runPropertyTest(
            "Property 7: Feasibility form required field validation",
            [$this, 'testFeasibilityFormRequiredFieldValidation']
        );
        
        // Property 8: Feasibility submission round-trip
        $allPassed &= $this->runPropertyTest(
            "Property 8: Feasibility submission round-trip",
            [$this, 'testFeasibilitySubmissionRoundTrip']
        );
        
        // Cleanup
        $this->cleanupTestData();
        
        return $allPassed;
    }
    
    /**
     * Property 7: Feasibility form required field validation
     * **Feature: feasibility-module, Property 7: Feasibility form required field validation**
     * **Validates: Requirements 4.3**
     * 
     * For any feasibility form submission with any required field empty, the submission
     * should be rejected with a specific validation error identifying the missing field.
     */
    public function testFeasibilityFormRequiredFieldValidation(): array {
        try {
            // Create test assignment with ADA
            $assignment = $this->createTestAssignmentWithADA();
            $this->assert($assignment !== null, "Assignment creation should succeed");
            
            // Pick a random required field to omit
            $fieldToOmit = $this->generateRandomChoice($this->requiredFields);
            
            // Generate valid data but omit one required field
            $data = $this->generateValidFeasibilityData();
            unset($data[$fieldToOmit]);
            
            // Try to create feasibility check
            $result = $this->feasibilityService->createFeasibilityCheck(
                $assignment['id'],
                $data,
                $this->testEngineerId
            );
            
            // Should fail
            $this->assert(
                !$result['success'],
                "Feasibility check with missing required field should fail"
            );
            
            $this->assert(
                $result['code'] === 'VALIDATION_ERROR',
                "Error code should be VALIDATION_ERROR"
            );
            
            // Verify error identifies the missing field
            $hasCorrectError = false;
            if (!empty($result['errors'])) {
                foreach ($result['errors'] as $error) {
                    if ($error['field'] === $fieldToOmit && $error['code'] === 'REQUIRED_FIELD_MISSING') {
                        $hasCorrectError = true;
                        break;
                    }
                }
            }
            
            $this->assert(
                $hasCorrectError,
                "Error should identify the missing field: {$fieldToOmit}"
            );
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Property 8: Feasibility submission round-trip
     * **Feature: feasibility-module, Property 8: Feasibility submission round-trip**
     * **Validates: Requirements 4.4**
     * 
     * For any valid feasibility form data, submitting the form and then retrieving the
     * feasibility check should return a record with all original field values intact,
     * plus audit fields (created_at, created_by).
     */
    public function testFeasibilitySubmissionRoundTrip(): array {
        try {
            // Create test assignment with ADA
            $assignment = $this->createTestAssignmentWithADA();
            $this->assert($assignment !== null, "Assignment creation should succeed");
            
            // Generate valid feasibility data
            $originalData = $this->generateValidFeasibilityData();
            
            // Create feasibility check
            $result = $this->feasibilityService->createFeasibilityCheck(
                $assignment['id'],
                $originalData,
                $this->testEngineerId
            );
            
            $this->assert($result['success'], "Feasibility check creation should succeed: " . ($result['message'] ?? ''));
            
            $this->createdFeasibilityIds[] = $result['data']['id'];
            
            // Retrieve the feasibility check
            $retrieved = $this->feasibilityService->getFeasibilityByAssignment($assignment['id']);
            
            $this->assert(
                $retrieved !== null,
                "Retrieved feasibility check should not be null"
            );
            
            // Verify all original fields are preserved
            foreach ($originalData as $field => $value) {
                if ($value !== '' && $value !== null) {
                    $retrievedValue = $retrieved[$field] ?? null;
                    
                    // Handle numeric comparisons
                    if (is_numeric($value) && is_numeric($retrievedValue)) {
                        $this->assert(
                            (int)$retrievedValue === (int)$value,
                            "Field {$field} should match: expected {$value}, got {$retrievedValue}"
                        );
                    } else {
                        $this->assert(
                            $retrievedValue === $value,
                            "Field {$field} should match: expected {$value}, got {$retrievedValue}"
                        );
                    }
                }
            }
            
            // Verify audit fields are set
            $this->assert(
                !empty($retrieved['created_at']),
                "created_at should be set"
            );
            
            $this->assert(
                (int)$retrieved['created_by'] === $this->testEngineerId,
                "created_by should be the engineer ID"
            );
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Clean up test data
     */
    protected function cleanupTestData(): void {
        // Delete feasibility checks first
        foreach ($this->createdFeasibilityIds as $feasibilityId) {
            try {
                $stmt = $this->executeQuery(
                    "DELETE FROM feasibility_checks WHERE id = ?",
                    [$feasibilityId],
                    'i'
                );
                $stmt->close();
            } catch (Exception $e) {
                // Ignore cleanup errors
            }
        }
        $this->createdFeasibilityIds = [];
        
        // Delete ADAs
        foreach ($this->createdADAIds as $adaId) {
            try {
                $stmt = $this->executeQuery(
                    "DELETE FROM feasibility_ada WHERE id = ?",
                    [$adaId],
                    'i'
                );
                $stmt->close();
            } catch (Exception $e) {
                // Ignore cleanup errors
            }
        }
        $this->createdADAIds = [];
        
        // Delete ETAs
        foreach ($this->createdETAIds as $etaId) {
            try {
                $stmt = $this->executeQuery(
                    "DELETE FROM feasibility_eta WHERE id = ?",
                    [$etaId],
                    'i'
                );
                $stmt->close();
            } catch (Exception $e) {
                // Ignore cleanup errors
            }
        }
        $this->createdETAIds = [];
        
        // Delete assignments
        foreach ($this->createdAssignmentIds as $assignmentId) {
            try {
                $stmt = $this->executeQuery(
                    "DELETE FROM feasibility_checks WHERE assignment_id = ?",
                    [$assignmentId],
                    'i'
                );
                $stmt->close();
                
                $stmt = $this->executeQuery(
                    "DELETE FROM feasibility_ada WHERE assignment_id = ?",
                    [$assignmentId],
                    'i'
                );
                $stmt->close();
                
                $stmt = $this->executeQuery(
                    "DELETE FROM feasibility_eta WHERE assignment_id = ?",
                    [$assignmentId],
                    'i'
                );
                $stmt->close();
                
                $stmt = $this->executeQuery(
                    "DELETE FROM engineer_assignments WHERE id = ?",
                    [$assignmentId],
                    'i'
                );
                $stmt->close();
            } catch (Exception $e) {
                // Ignore cleanup errors
            }
        }
        $this->createdAssignmentIds = [];
        
        // Delete delegations
        foreach ($this->createdDelegationIds as $delegationId) {
            try {
                $stmt = $this->executeQuery(
                    "DELETE FROM delegation_history WHERE delegation_id = ?",
                    [$delegationId],
                    'i'
                );
                $stmt->close();
                
                $stmt = $this->executeQuery(
                    "DELETE FROM site_delegations WHERE id = ?",
                    [$delegationId],
                    'i'
                );
                $stmt->close();
            } catch (Exception $e) {
                // Ignore cleanup errors
            }
        }
        $this->createdDelegationIds = [];
        
        // Delete sites
        foreach ($this->createdSiteIds as $siteId) {
            try {
                $stmt = $this->executeQuery(
                    "DELETE FROM sites WHERE id = ?",
                    [$siteId],
                    'i'
                );
                $stmt->close();
            } catch (Exception $e) {
                // Ignore cleanup errors
            }
        }
        $this->createdSiteIds = [];
    }
}
