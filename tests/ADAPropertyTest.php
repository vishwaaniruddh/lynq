<?php
/**
 * Property Test for ADA Operations
 * **Feature: feasibility-module, Property 5: ADA submission creates record with geolocation and updates status**
 * **Validates: Requirements 3.4, 3.5**
 */

require_once 'PropertyTestBase.php';
require_once __DIR__ . '/../services/ADAService.php';
require_once __DIR__ . '/../services/ETAService.php';
require_once __DIR__ . '/../services/SiteService.php';
require_once __DIR__ . '/../services/DelegationService.php';
require_once __DIR__ . '/../services/EngineerAssignmentService.php';

class ADAPropertyTest extends PropertyTestBase {
    
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
    
    public function __construct() {
        parent::__construct();
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
            // Create an engineer user
            $stmt = $this->executeQuery(
                "INSERT INTO users (first_name, last_name, email, password, company_id, status) VALUES (?, ?, ?, ?, ?, ?)",
                ['Test', 'Engineer', 'engineer_ada_' . uniqid() . '@test.com', password_hash('password', PASSWORD_DEFAULT), $this->testContractorId, 1],
                'ssssii'
            );
            $this->testEngineerId = $this->db->insert_id;
            $stmt->close();
        }
    }
    
    /**
     * Create a test site with delegation, assignment, and ETA
     */
    private function createTestAssignmentWithETA(): ?array {
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
        
        // Submit ETA (required before ADA)
        $etaDateTime = date('Y-m-d H:i:s', strtotime('+1 day'));
        $etaResult = $this->etaService->submitETA(
            $assignmentResult['data']['id'],
            $etaDateTime,
            $this->testEngineerId
        );
        if ($etaResult['success']) {
            $this->createdETAIds[] = $etaResult['data']['id'];
        }
        
        return $assignmentResult['data'];
    }
    
    /**
     * Generate valid GPS coordinates
     */
    private function generateValidCoordinates(): array {
        // Generate coordinates avoiding (0,0) which is considered invalid
        $latitude = (rand(-8900000, 8900000) / 100000);
        $longitude = (rand(-17900000, 17900000) / 100000);
        
        // Ensure we don't hit exactly 0,0
        if ($latitude == 0 && $longitude == 0) {
            $latitude = 0.001;
        }
        
        return [
            'latitude' => round($latitude, 6),
            'longitude' => round($longitude, 6)
        ];
    }
    
    public function runTests(): bool {
        echo "=== ADA Property Tests ===\n\n";
        
        $allPassed = true;
        
        // Property 5: ADA submission creates record with geolocation and updates status
        $allPassed &= $this->runPropertyTest(
            "Property 5: ADA submission creates record with geolocation and updates status",
            [$this, 'testADASubmissionCreatesRecordAndUpdatesStatus']
        );
        
        // Cleanup
        $this->cleanupTestData();
        
        return $allPassed;
    }
    
    /**
     * Property 5: ADA submission creates record with geolocation and updates status
     * **Feature: feasibility-module, Property 5: ADA submission creates record with geolocation and updates status**
     * **Validates: Requirements 3.4, 3.5**
     * 
     * For any valid ADA submission with GPS coordinates, the system should create an ADA record
     * with correct data (datetime, latitude, longitude, engineer_id) and update the assignment's
     * feasibility_status to "ada_submitted".
     */
    public function testADASubmissionCreatesRecordAndUpdatesStatus(): array {
        try {
            // Create test assignment with ETA
            $assignment = $this->createTestAssignmentWithETA();
            $this->assert($assignment !== null, "Assignment creation should succeed");
            
            // Generate valid coordinates
            $coords = $this->generateValidCoordinates();
            
            // Submit ADA
            $result = $this->adaService->submitADA(
                $assignment['id'],
                $coords['latitude'],
                $coords['longitude'],
                $this->testEngineerId
            );
            
            $this->assert($result['success'], "ADA submission should succeed: " . ($result['message'] ?? ''));
            
            $ada = $result['data'];
            $this->createdADAIds[] = $ada['id'];
            
            // Verify ADA record has correct data
            $this->assert(
                (int)$ada['assignment_id'] === (int)$assignment['id'],
                "ADA assignment_id should match"
            );
            
            $this->assert(
                abs((float)$ada['latitude'] - $coords['latitude']) < 0.0001,
                "ADA latitude should match submitted value"
            );
            
            $this->assert(
                abs((float)$ada['longitude'] - $coords['longitude']) < 0.0001,
                "ADA longitude should match submitted value"
            );
            
            $this->assert(
                (int)$ada['submitted_by'] === $this->testEngineerId,
                "ADA submitted_by should be the engineer ID"
            );
            
            $this->assert(
                !empty($ada['ada_datetime']),
                "ADA ada_datetime should be set"
            );
            
            $this->assert(
                !empty($ada['submitted_at']),
                "ADA submitted_at should be set"
            );
            
            // Verify assignment feasibility_status is updated
            $updatedAssignment = $this->getResults(
                "SELECT feasibility_status FROM engineer_assignments WHERE id = ?",
                [$assignment['id']],
                'i'
            );
            
            $this->assert(
                !empty($updatedAssignment) && $updatedAssignment[0]['feasibility_status'] === 'ada_submitted',
                "Assignment feasibility_status should be 'ada_submitted'"
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
        // Delete ADAs first
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
