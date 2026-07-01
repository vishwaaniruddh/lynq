<?php
/**
 * Property Test for Master Site Information Display
 * **Feature: feasibility-module, Property 6: Feasibility form displays master site information**
 * **Validates: Requirements 4.2**
 */

require_once 'PropertyTestBase.php';
require_once __DIR__ . '/../services/FeasibilityService.php';
require_once __DIR__ . '/../services/SiteService.php';
require_once __DIR__ . '/../services/DelegationService.php';
require_once __DIR__ . '/../services/EngineerAssignmentService.php';

class MasterSiteInfoPropertyTest extends PropertyTestBase {
    
    private $feasibilityService;
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
    
    public function __construct() {
        parent::__construct();
        $this->feasibilityService = new FeasibilityService();
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
                ['Test', 'Engineer', 'engineer_' . uniqid() . '@test.com', password_hash('password', PASSWORD_DEFAULT), $this->testContractorId, 1],
                'ssssii'
            );
            $this->testEngineerId = $this->db->insert_id;
            $stmt->close();
        }
    }
    
    /**
     * Generate random site data
     */
    private function generateRandomSiteData(): array {
        return [
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
    }
    
    /**
     * Create a test site with delegation and assignment
     */
    private function createTestAssignment(array $siteData): ?array {
        // Create site
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
        
        return [
            'site' => $siteResult['data'],
            'assignment' => $assignmentResult['data'],
            'original_data' => $siteData
        ];
    }
    
    public function runTests(): bool {
        echo "=== Master Site Information Property Tests ===\n\n";
        
        $allPassed = true;
        
        // Property 6: Feasibility form displays master site information
        $allPassed &= $this->runPropertyTest(
            "Property 6: Feasibility form displays master site information",
            [$this, 'testMasterSiteInfoDisplay']
        );
        
        // Cleanup
        $this->cleanupTestData();
        
        return $allPassed;
    }
    
    /**
     * Property 6: Feasibility form displays master site information
     * **Feature: feasibility-module, Property 6: Feasibility form displays master site information**
     * **Validates: Requirements 4.2**
     * 
     * For any site assignment, the feasibility form should display all master site fields
     * (site_name, lho, address, city, state, bank_name, customer_name, latitude, longitude)
     * matching the original site data.
     */
    public function testMasterSiteInfoDisplay(): array {
        try {
            // Generate random site data
            $originalSiteData = $this->generateRandomSiteData();
            
            // Create test assignment
            $testData = $this->createTestAssignment($originalSiteData);
            $this->assert($testData !== null, "Assignment creation should succeed");
            
            $assignmentId = $testData['assignment']['id'];
            
            // Get master site info from FeasibilityService
            $masterSiteInfo = $this->feasibilityService->getMasterSiteInfo($assignmentId);
            
            $this->assert($masterSiteInfo !== null, "Master site info should be returned");
            
            // Verify all required fields match original data
            // Requirement 4.2: Display read-only master site information
            
            // site_name
            $this->assert(
                $masterSiteInfo['site_name'] === $originalSiteData['site_name'],
                "site_name should match: expected '{$originalSiteData['site_name']}', got '{$masterSiteInfo['site_name']}'"
            );
            
            // lho
            $this->assert(
                $masterSiteInfo['lho'] === $originalSiteData['lho'],
                "lho should match: expected '{$originalSiteData['lho']}', got '{$masterSiteInfo['lho']}'"
            );
            
            // address
            $this->assert(
                $masterSiteInfo['address'] === $originalSiteData['address'],
                "address should match: expected '{$originalSiteData['address']}', got '{$masterSiteInfo['address']}'"
            );
            
            // city
            $this->assert(
                $masterSiteInfo['city'] === $originalSiteData['city'],
                "city should match: expected '{$originalSiteData['city']}', got '{$masterSiteInfo['city']}'"
            );
            
            // state
            $this->assert(
                $masterSiteInfo['state'] === $originalSiteData['state'],
                "state should match: expected '{$originalSiteData['state']}', got '{$masterSiteInfo['state']}'"
            );
            
            // bank_name
            $this->assert(
                $masterSiteInfo['bank_name'] === $originalSiteData['bank_name'],
                "bank_name should match: expected '{$originalSiteData['bank_name']}', got '{$masterSiteInfo['bank_name']}'"
            );
            
            // customer_name
            $this->assert(
                $masterSiteInfo['customer_name'] === $originalSiteData['customer_name'],
                "customer_name should match: expected '{$originalSiteData['customer_name']}', got '{$masterSiteInfo['customer_name']}'"
            );
            
            // latitude (compare as floats with tolerance)
            $latDiff = abs((float)$masterSiteInfo['latitude'] - (float)$originalSiteData['latitude']);
            $this->assert(
                $latDiff < 0.000001,
                "latitude should match: expected '{$originalSiteData['latitude']}', got '{$masterSiteInfo['latitude']}'"
            );
            
            // longitude (compare as floats with tolerance)
            $lonDiff = abs((float)$masterSiteInfo['longitude'] - (float)$originalSiteData['longitude']);
            $this->assert(
                $lonDiff < 0.000001,
                "longitude should match: expected '{$originalSiteData['longitude']}', got '{$masterSiteInfo['longitude']}'"
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
        // Delete assignments
        foreach ($this->createdAssignmentIds as $assignmentId) {
            try {
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
