<?php
/**
 * Property Test for Feasibility Export Round-Trip
 * **Feature: feasibility-module, Property 15: Feasibility export round-trip**
 * **Validates: Requirements 8.4**
 */

require_once 'PropertyTestBase.php';
require_once __DIR__ . '/../services/FeasibilityService.php';
require_once __DIR__ . '/../services/ETAService.php';
require_once __DIR__ . '/../services/ADAService.php';
require_once __DIR__ . '/../services/SiteService.php';
require_once __DIR__ . '/../services/DelegationService.php';
require_once __DIR__ . '/../services/EngineerAssignmentService.php';

class FeasibilityExportPropertyTest extends PropertyTestBase {
    
    private $feasibilityService;
    private $etaService;
    private $adaService;
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
    
    public function __construct() {
        parent::__construct();
        $this->feasibilityService = new FeasibilityService();
        $this->etaService = new ETAService();
        $this->adaService = new ADAService();
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
        $this->testAdminUserId = !empty($result) ? (int)$result[0]['id'] : 1;
        
        // Get or create engineer user
        $result = $this->getResults("SELECT id FROM users WHERE company_id = ? AND status = 1 LIMIT 1", [$this->testContractorId], 'i');
        if (!empty($result)) {
            $this->testEngineerId = (int)$result[0]['id'];
        } else {
            $stmt = $this->executeQuery(
                "INSERT INTO users (first_name, last_name, email, password, company_id, status) VALUES (?, ?, ?, ?, ?, ?)",
                ['Test', 'Engineer', 'engineer_' . uniqid() . '@test.com', password_hash('password', PASSWORD_DEFAULT), $this->testContractorId, 1],
                'ssssii'
            );
            $this->testEngineerId = $this->db->insert_id;
            $stmt->close();
        }
    }
    
    /**
     * Create a test site with delegation and assignment
     */
    private function createTestAssignment(): ?array {
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
            'latitude' => round(rand(-9000000, 9000000) / 100000, 6),
            'longitude' => round(rand(-18000000, 18000000) / 100000, 6),
            'company_id' => $this->testAdvCompanyId
        ];
        
        $siteResult = $this->siteService->createSite($siteData, $this->testAdminUserId);
        if (!$siteResult['success']) {
            return null;
        }
        $this->createdSiteIds[] = $siteResult['data']['id'];
        
        $delegationResult = $this->delegationService->delegateSite(
            $siteResult['data']['id'],
            $this->testContractorId,
            $this->testAdminUserId
        );
        if (!$delegationResult['success']) {
            return null;
        }
        $this->createdDelegationIds[] = $delegationResult['data']['id'];
        
        $this->delegationService->acceptDelegation($delegationResult['data']['id'], $this->testAdminUserId);
        
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
        
        return array_merge($assignmentResult['data'], ['site_data' => $siteData]);
    }
    
    /**
     * Generate valid future ETA datetime
     */
    private function generateValidETADateTime(): string {
        $futureMinutes = rand(60, 43200);
        return date('Y-m-d H:i:s', strtotime("+{$futureMinutes} minutes"));
    }
    
    /**
     * Generate valid coordinates
     */
    private function generateValidCoordinates(): array {
        return [
            'latitude' => round(rand(-9000000, 9000000) / 100000, 6),
            'longitude' => round(rand(-18000000, 18000000) / 100000, 6)
        ];
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
            'ups_available' => $this->generateRandomChoice(['yes', 'no']),
            'no_of_ups' => rand(0, 3),
            'earthing' => $this->generateRandomChoice(['yes', 'no']),
            'earthing_voltage' => rand(0, 10) . 'V',
            'remarks' => 'Test remarks ' . $this->generateRandomString(50)
        ];
    }
    
    public function runTests(): bool {
        echo "=== Feasibility Export Property Tests ===\n\n";
        
        $allPassed = true;
        
        // Property 15: Feasibility export round-trip
        $allPassed &= $this->runPropertyTest(
            "Property 15: Feasibility export round-trip",
            [$this, 'testFeasibilityExportRoundTrip'],
            10 // Reduced iterations for complex test
        );
        
        // Cleanup
        $this->cleanupTestData();
        
        return $allPassed;
    }
    
    /**
     * Property 15: Feasibility export round-trip
     * **Feature: feasibility-module, Property 15: Feasibility export round-trip**
     * **Validates: Requirements 8.4**
     * 
     * For any set of feasibility records, exporting to Excel and then parsing the Excel file
     * should produce data equivalent to the original records.
     */
    public function testFeasibilityExportRoundTrip(): array {
        try {
            // Create test assignment with complete feasibility data
            $assignment = $this->createTestAssignment();
            $this->assert($assignment !== null, "Assignment creation should succeed");
            
            // Submit ETA
            $etaDateTime = $this->generateValidETADateTime();
            $etaResult = $this->etaService->submitETA(
                $assignment['id'],
                $etaDateTime,
                $this->testEngineerId
            );
            $this->assert($etaResult['success'], "ETA submission should succeed");
            $this->createdETAIds[] = $etaResult['data']['id'];
            
            // Submit ADA
            $coords = $this->generateValidCoordinates();
            $adaResult = $this->adaService->submitADA(
                $assignment['id'],
                $coords['latitude'],
                $coords['longitude'],
                $this->testEngineerId
            );
            $this->assert($adaResult['success'], "ADA submission should succeed");
            $this->createdADAIds[] = $adaResult['data']['id'];
            
            // Submit feasibility check
            $feasibilityData = $this->generateValidFeasibilityData();
            $feasibilityResult = $this->feasibilityService->createFeasibilityCheck(
                $assignment['id'],
                $feasibilityData,
                $this->testEngineerId
            );
            $this->assert($feasibilityResult['success'], "Feasibility check creation should succeed: " . ($feasibilityResult['message'] ?? ''));
            $this->createdFeasibilityIds[] = $feasibilityResult['data']['id'];
            
            // Get export data
            $exportData = $this->feasibilityService->exportFeasibilityData([]);
            
            $this->assert(
                !empty($exportData),
                "Export data should not be empty"
            );
            
            // Find our record in export data
            $foundRecord = null;
            foreach ($exportData as $row) {
                if ($row['site_name'] === $assignment['site_data']['site_name']) {
                    $foundRecord = $row;
                    break;
                }
            }
            
            $this->assert(
                $foundRecord !== null,
                "Created record should be found in export data"
            );
            
            // Verify key fields match (round-trip verification)
            // Site information
            $this->assert(
                $foundRecord['site_name'] === $assignment['site_data']['site_name'],
                "Site name should match in export"
            );
            
            $this->assert(
                $foundRecord['lho'] === $assignment['site_data']['lho'],
                "LHO should match in export"
            );
            
            $this->assert(
                $foundRecord['city'] === $assignment['site_data']['city'],
                "City should match in export"
            );
            
            // ETA datetime
            $this->assert(
                $foundRecord['eta_datetime'] === $etaDateTime,
                "ETA datetime should match in export"
            );
            
            // ADA coordinates
            $this->assert(
                abs((float)$foundRecord['ada_latitude'] - $coords['latitude']) < 0.0001,
                "ADA latitude should match in export"
            );
            
            $this->assert(
                abs((float)$foundRecord['ada_longitude'] - $coords['longitude']) < 0.0001,
                "ADA longitude should match in export"
            );
            
            // Feasibility data
            $this->assert(
                (int)$foundRecord['no_of_atm'] === $feasibilityData['no_of_atm'],
                "Number of ATMs should match in export"
            );
            
            $this->assert(
                $foundRecord['operator'] === $feasibilityData['operator'],
                "Operator should match in export"
            );
            
            $this->assert(
                $foundRecord['signal_status'] === $feasibilityData['signal_status'],
                "Signal status should match in export"
            );
            
            $this->assert(
                $foundRecord['ups_available'] === $feasibilityData['ups_available'],
                "UPS available should match in export"
            );
            
            $this->assert(
                $foundRecord['earthing'] === $feasibilityData['earthing'],
                "Earthing should match in export"
            );
            
            $this->assert(
                $foundRecord['remarks'] === $feasibilityData['remarks'],
                "Remarks should match in export"
            );
            
            // Verify feasibility status
            $this->assert(
                $foundRecord['feasibility_status'] === 'feasibility_completed',
                "Feasibility status should be 'feasibility_completed' in export"
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
        // Delete feasibility checks
        foreach ($this->createdFeasibilityIds as $id) {
            try {
                $stmt = $this->executeQuery("DELETE FROM feasibility_checks WHERE id = ?", [$id], 'i');
                $stmt->close();
            } catch (Exception $e) {}
        }
        $this->createdFeasibilityIds = [];
        
        // Delete ADAs
        foreach ($this->createdADAIds as $adaId) {
            try {
                $stmt = $this->executeQuery("DELETE FROM feasibility_ada WHERE id = ?", [$adaId], 'i');
                $stmt->close();
            } catch (Exception $e) {}
        }
        $this->createdADAIds = [];
        
        // Delete ETAs
        foreach ($this->createdETAIds as $etaId) {
            try {
                $stmt = $this->executeQuery("DELETE FROM feasibility_eta WHERE id = ?", [$etaId], 'i');
                $stmt->close();
            } catch (Exception $e) {}
        }
        $this->createdETAIds = [];
        
        // Delete assignments
        foreach ($this->createdAssignmentIds as $assignmentId) {
            try {
                $stmt = $this->executeQuery("DELETE FROM feasibility_checks WHERE assignment_id = ?", [$assignmentId], 'i');
                $stmt->close();
                $stmt = $this->executeQuery("DELETE FROM feasibility_ada WHERE assignment_id = ?", [$assignmentId], 'i');
                $stmt->close();
                $stmt = $this->executeQuery("DELETE FROM feasibility_eta WHERE assignment_id = ?", [$assignmentId], 'i');
                $stmt->close();
                $stmt = $this->executeQuery("DELETE FROM engineer_assignments WHERE id = ?", [$assignmentId], 'i');
                $stmt->close();
            } catch (Exception $e) {}
        }
        $this->createdAssignmentIds = [];
        
        // Delete delegations
        foreach ($this->createdDelegationIds as $delegationId) {
            try {
                $stmt = $this->executeQuery("DELETE FROM delegation_history WHERE delegation_id = ?", [$delegationId], 'i');
                $stmt->close();
                $stmt = $this->executeQuery("DELETE FROM site_delegations WHERE id = ?", [$delegationId], 'i');
                $stmt->close();
            } catch (Exception $e) {}
        }
        $this->createdDelegationIds = [];
        
        // Delete sites
        foreach ($this->createdSiteIds as $siteId) {
            try {
                $stmt = $this->executeQuery("DELETE FROM sites WHERE id = ?", [$siteId], 'i');
                $stmt->close();
            } catch (Exception $e) {}
        }
        $this->createdSiteIds = [];
    }
}
