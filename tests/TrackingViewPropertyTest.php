<?php
/**
 * Property Test for Tracking View Data
 * **Feature: feasibility-module, Property 13: Tracking view displays complete data**
 * **Feature: feasibility-module, Property 14: Tracking filter returns correct results**
 * **Validates: Requirements 8.1, 8.2, 8.3**
 */

require_once 'PropertyTestBase.php';
require_once __DIR__ . '/../services/FeasibilityService.php';
require_once __DIR__ . '/../services/ETAService.php';
require_once __DIR__ . '/../services/ADAService.php';
require_once __DIR__ . '/../services/SiteService.php';
require_once __DIR__ . '/../services/DelegationService.php';
require_once __DIR__ . '/../services/EngineerAssignmentService.php';

class TrackingViewPropertyTest extends PropertyTestBase {
    
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
    
    public function runTests(): bool {
        echo "=== Tracking View Property Tests ===\n\n";
        
        $allPassed = true;
        
        // Property 13: Tracking view displays complete data
        $allPassed &= $this->runPropertyTest(
            "Property 13: Tracking view displays complete data",
            [$this, 'testTrackingViewDisplaysCompleteData'],
            20 // Reduced iterations for complex test
        );
        
        // Property 14: Tracking filter returns correct results
        $allPassed &= $this->runPropertyTest(
            "Property 14: Tracking filter returns correct results",
            [$this, 'testTrackingFilterReturnsCorrectResults'],
            20
        );
        
        // Cleanup
        $this->cleanupTestData();
        
        return $allPassed;
    }
    
    /**
     * Property 13: Tracking view displays complete data
     * **Feature: feasibility-module, Property 13: Tracking view displays complete data**
     * **Validates: Requirements 8.1, 8.2**
     * 
     * For any set of site assignments, the tracking view should display all assignments
     * with their correct feasibility_status, ETA datetime, ADA datetime, and ADA coordinates
     * where available.
     */
    public function testTrackingViewDisplaysCompleteData(): array {
        try {
            // Create test assignment
            $assignment = $this->createTestAssignment();
            $this->assert($assignment !== null, "Assignment creation should succeed");
            
            // Randomly decide what data to add
            $addETA = $this->generateRandomBool();
            $addADA = $addETA && $this->generateRandomBool(); // ADA requires ETA first
            
            $expectedETA = null;
            $expectedADA = null;
            $expectedStatus = 'pending_eta';
            
            if ($addETA) {
                $etaDateTime = $this->generateValidETADateTime();
                $etaResult = $this->etaService->submitETA(
                    $assignment['id'],
                    $etaDateTime,
                    $this->testEngineerId
                );
                $this->assert($etaResult['success'], "ETA submission should succeed");
                $this->createdETAIds[] = $etaResult['data']['id'];
                $expectedETA = $etaDateTime;
                $expectedStatus = 'eta_submitted';
            }
            
            if ($addADA) {
                $coords = $this->generateValidCoordinates();
                $adaResult = $this->adaService->submitADA(
                    $assignment['id'],
                    $coords['latitude'],
                    $coords['longitude'],
                    $this->testEngineerId
                );
                $this->assert($adaResult['success'], "ADA submission should succeed");
                $this->createdADAIds[] = $adaResult['data']['id'];
                $expectedADA = $coords;
                $expectedStatus = 'ada_submitted';
            }
            
            // Get tracking data
            $trackingResult = $this->feasibilityService->getFeasibilityTracking([
                'page' => 1,
                'limit' => 100
            ]);
            
            $this->assert(
                !empty($trackingResult['data']),
                "Tracking data should not be empty"
            );
            
            // Find our assignment in tracking data
            $foundAssignment = null;
            foreach ($trackingResult['data'] as $row) {
                if ((int)$row['assignment_id'] === (int)$assignment['id']) {
                    $foundAssignment = $row;
                    break;
                }
            }
            
            $this->assert(
                $foundAssignment !== null,
                "Assignment should be found in tracking data"
            );
            
            // Verify feasibility_status (Requirement 8.1)
            $this->assert(
                $foundAssignment['feasibility_status'] === $expectedStatus,
                "Feasibility status should match expected: got {$foundAssignment['feasibility_status']}, expected {$expectedStatus}"
            );
            
            // Verify ETA datetime (Requirement 8.2)
            if ($expectedETA !== null) {
                $this->assert(
                    $foundAssignment['eta_datetime'] === $expectedETA,
                    "ETA datetime should match"
                );
            }
            
            // Verify ADA datetime and location (Requirement 8.2)
            if ($expectedADA !== null) {
                $this->assert(
                    !empty($foundAssignment['ada_datetime']),
                    "ADA datetime should be present"
                );
                
                $this->assert(
                    abs((float)$foundAssignment['ada_latitude'] - $expectedADA['latitude']) < 0.0001,
                    "ADA latitude should match"
                );
                
                $this->assert(
                    abs((float)$foundAssignment['ada_longitude'] - $expectedADA['longitude']) < 0.0001,
                    "ADA longitude should match"
                );
            }
            
            // Verify site information is present
            $this->assert(
                !empty($foundAssignment['site_name']),
                "Site name should be present in tracking data"
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
     * Property 14: Tracking filter returns correct results
     * **Feature: feasibility-module, Property 14: Tracking filter returns correct results**
     * **Validates: Requirements 8.3**
     * 
     * For any filter criteria (status), the filtered tracking results should contain
     * only assignments that match the specified status.
     */
    public function testTrackingFilterReturnsCorrectResults(): array {
        try {
            // Create multiple assignments with different statuses
            $statuses = ['pending_eta', 'eta_submitted', 'ada_submitted'];
            $createdAssignments = [];
            
            foreach ($statuses as $targetStatus) {
                $assignment = $this->createTestAssignment();
                $this->assert($assignment !== null, "Assignment creation should succeed");
                
                if ($targetStatus === 'eta_submitted' || $targetStatus === 'ada_submitted') {
                    $etaDateTime = $this->generateValidETADateTime();
                    $etaResult = $this->etaService->submitETA(
                        $assignment['id'],
                        $etaDateTime,
                        $this->testEngineerId
                    );
                    $this->assert($etaResult['success'], "ETA submission should succeed");
                    $this->createdETAIds[] = $etaResult['data']['id'];
                }
                
                if ($targetStatus === 'ada_submitted') {
                    $coords = $this->generateValidCoordinates();
                    $adaResult = $this->adaService->submitADA(
                        $assignment['id'],
                        $coords['latitude'],
                        $coords['longitude'],
                        $this->testEngineerId
                    );
                    $this->assert($adaResult['success'], "ADA submission should succeed");
                    $this->createdADAIds[] = $adaResult['data']['id'];
                }
                
                $createdAssignments[$targetStatus] = $assignment['id'];
            }
            
            // Test filtering by each status
            $filterStatus = $this->generateRandomChoice($statuses);
            
            $trackingResult = $this->feasibilityService->getFeasibilityTracking([
                'status' => $filterStatus,
                'page' => 1,
                'limit' => 100
            ]);
            
            // Verify all returned results match the filter
            foreach ($trackingResult['data'] as $row) {
                $this->assert(
                    $row['feasibility_status'] === $filterStatus,
                    "Filtered result should have status '{$filterStatus}', got '{$row['feasibility_status']}'"
                );
            }
            
            // Verify our created assignment with matching status is in results
            $foundMatchingAssignment = false;
            foreach ($trackingResult['data'] as $row) {
                if ((int)$row['assignment_id'] === $createdAssignments[$filterStatus]) {
                    $foundMatchingAssignment = true;
                    break;
                }
            }
            
            $this->assert(
                $foundMatchingAssignment,
                "Assignment with status '{$filterStatus}' should be in filtered results"
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
