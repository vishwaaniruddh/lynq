<?php
/**
 * Property Test for Site Creation
 * **Feature: site-management-delegation, Property 1: Site creation preserves all input data**
 * **Feature: site-management-delegation, Property 2: Site uniqueness within LHO**
 * **Validates: Requirements 1.1, 1.4, 1.5**
 * 
 * Property 1: For any valid site data submitted by an ADV user, creating the site and then 
 * retrieving it should return a site object with all original field values intact, 
 * plus automatically populated audit fields (created_at, created_by).
 * 
 * Property 2: For any site name and LHO combination that already exists in the system, 
 * attempting to create another site with the same name and LHO should be rejected.
 */

require_once 'PropertyTestBase.php';
require_once __DIR__ . '/../services/SiteService.php';
require_once __DIR__ . '/../repositories/SiteRepository.php';

class SiteCreationPropertyTest extends PropertyTestBase {
    
    private $siteService;
    private $siteRepository;
    private $testCompanyId;
    private $testUserId;
    private $createdSiteIds = [];
    
    public function __construct() {
        parent::__construct();
        $this->siteService = new SiteService();
        $this->siteRepository = new SiteRepository();
        $this->setupTestData();
    }
    
    /**
     * Setup test company and user
     */
    private function setupTestData(): void {
        // Get or create a test company
        $result = $this->getResults("SELECT id FROM companies WHERE status = 1 LIMIT 1");
        if (!empty($result)) {
            $this->testCompanyId = (int)$result[0]['id'];
        } else {
            // Create a test company
            $stmt = $this->executeQuery(
                "INSERT INTO companies (name, type, status) VALUES (?, ?, ?)",
                ['Test Company ' . uniqid(), 'adv', 1],
                'ssi'
            );
            $this->testCompanyId = $this->db->insert_id;
            $stmt->close();
        }
        
        // Get or create a test user
        $result = $this->getResults("SELECT id FROM users WHERE status = 1 LIMIT 1");
        if (!empty($result)) {
            $this->testUserId = (int)$result[0]['id'];
        } else {
            $this->testUserId = 1; // Default to 1 if no users exist
        }
    }
    
    public function runTests(): bool {
        echo "=== Site Creation Property Tests ===\n\n";
        
        $allPassed = true;
        
        // Property 1: Site creation preserves all input data
        $allPassed &= $this->runPropertyTest(
            "Property 1: Site creation preserves all input data",
            [$this, 'testSiteCreationPreservesData']
        );
        
        $allPassed &= $this->runPropertyTest(
            "Property 1: Audit fields are automatically populated",
            [$this, 'testAuditFieldsPopulated']
        );
        
        // Property 2: Site uniqueness within LHO
        $allPassed &= $this->runPropertyTest(
            "Property 2: Duplicate site name within same LHO is rejected",
            [$this, 'testDuplicateSiteNameRejected']
        );
        
        $allPassed &= $this->runPropertyTest(
            "Property 2: Same site name in different LHO is allowed",
            [$this, 'testSameNameDifferentLHOAllowed']
        );
        
        // Cleanup
        $this->cleanupTestData();
        
        return $allPassed;
    }
    
    /**
     * Generate valid site data for testing
     */
    private function generateValidSiteData(): array {
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
            'latitude' => $this->generateRandomLatitude(),
            'longitude' => $this->generateRandomLongitude(),
            'company_id' => $this->testCompanyId
        ];
    }
    
    /**
     * Generate random valid latitude (-90 to 90)
     */
    private function generateRandomLatitude(): float {
        return round((rand(-9000000, 9000000) / 100000), 6);
    }
    
    /**
     * Generate random valid longitude (-180 to 180)
     */
    private function generateRandomLongitude(): float {
        return round((rand(-18000000, 18000000) / 100000), 6);
    }
    
    /**
     * Property 1: Site creation preserves all input data
     * **Feature: site-management-delegation, Property 1: Site creation preserves all input data**
     * **Validates: Requirements 1.1**
     */
    public function testSiteCreationPreservesData(): array {
        try {
            $inputData = $this->generateValidSiteData();
            
            // Create site
            $result = $this->siteService->createSite($inputData, $this->testUserId);
            
            $this->assert($result['success'], "Site creation should succeed: " . ($result['message'] ?? 'Unknown error'));
            
            $createdSite = $result['data'];
            $this->createdSiteIds[] = $createdSite['id'];
            
            // Verify all input fields are preserved
            $fieldsToCheck = ['site_name', 'lho', 'bank_name', 'customer_name', 'city', 'state', 'country', 'zone', 'address'];
            
            foreach ($fieldsToCheck as $field) {
                $this->assert(
                    trim($createdSite[$field]) === trim($inputData[$field]),
                    "Field '$field' should be preserved. Expected: '{$inputData[$field]}', Got: '{$createdSite[$field]}'"
                );
            }
            
            // Check coordinates (with floating point tolerance)
            $this->assert(
                abs((float)$createdSite['latitude'] - $inputData['latitude']) < 0.0001,
                "Latitude should be preserved"
            );
            
            $this->assert(
                abs((float)$createdSite['longitude'] - $inputData['longitude']) < 0.0001,
                "Longitude should be preserved"
            );
            
            // Verify by retrieving again
            $retrievedSite = $this->siteService->getSite($createdSite['id']);
            
            $this->assert(
                $retrievedSite !== null,
                "Should be able to retrieve created site"
            );
            
            foreach ($fieldsToCheck as $field) {
                $this->assert(
                    trim($retrievedSite[$field]) === trim($inputData[$field]),
                    "Retrieved field '$field' should match input"
                );
            }
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => $inputData ?? null
            ];
        }
    }
    
    /**
     * Property 1: Audit fields are automatically populated
     * **Feature: site-management-delegation, Property 1: Site creation preserves all input data**
     * **Validates: Requirements 1.4**
     */
    public function testAuditFieldsPopulated(): array {
        try {
            $inputData = $this->generateValidSiteData();
            
            // Create site
            $result = $this->siteService->createSite($inputData, $this->testUserId);
            
            $this->assert($result['success'], "Site creation should succeed");
            
            $createdSite = $result['data'];
            $this->createdSiteIds[] = $createdSite['id'];
            
            // Verify created_by is set to the user who created it
            $this->assert(
                (int)$createdSite['created_by'] === $this->testUserId,
                "created_by should be set to the creating user's ID. Expected: {$this->testUserId}, Got: {$createdSite['created_by']}"
            );
            
            // Verify created_at is set (not null and is a valid timestamp)
            $this->assert(
                !empty($createdSite['created_at']),
                "created_at should be set"
            );
            
            // Verify created_at is a valid datetime format (YYYY-MM-DD HH:MM:SS)
            $createdTime = strtotime($createdSite['created_at']);
            $this->assert(
                $createdTime !== false && $createdTime > 0,
                "created_at should be a valid datetime. Got: {$createdSite['created_at']}"
            );
            
            // Verify the datetime is reasonable (year should be current or recent)
            $year = (int)date('Y', $createdTime);
            $currentYear = (int)date('Y');
            $this->assert(
                $year >= ($currentYear - 1) && $year <= ($currentYear + 1),
                "created_at year should be reasonable. Got year: $year"
            );
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => $inputData ?? null
            ];
        }
    }
    
    /**
     * Property 2: Duplicate site name within same LHO is rejected
     * **Feature: site-management-delegation, Property 2: Site uniqueness within LHO**
     * **Validates: Requirements 1.5**
     */
    public function testDuplicateSiteNameRejected(): array {
        try {
            $inputData = $this->generateValidSiteData();
            
            // Create first site
            $result1 = $this->siteService->createSite($inputData, $this->testUserId);
            $this->assert($result1['success'], "First site creation should succeed");
            $this->createdSiteIds[] = $result1['data']['id'];
            
            // Try to create second site with same name and LHO
            $duplicateData = $inputData;
            $duplicateData['address'] = 'Different Address ' . $this->generateRandomString(10);
            
            $result2 = $this->siteService->createSite($duplicateData, $this->testUserId);
            
            // Should fail
            $this->assert(
                !$result2['success'],
                "Duplicate site creation should fail"
            );
            
            $this->assert(
                $result2['code'] === 'DUPLICATE_ERROR',
                "Error code should be DUPLICATE_ERROR"
            );
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => $inputData ?? null
            ];
        }
    }
    
    /**
     * Property 2: Same site name in different LHO is allowed
     * **Feature: site-management-delegation, Property 2: Site uniqueness within LHO**
     * **Validates: Requirements 1.5**
     */
    public function testSameNameDifferentLHOAllowed(): array {
        try {
            $inputData = $this->generateValidSiteData();
            
            // Create first site
            $result1 = $this->siteService->createSite($inputData, $this->testUserId);
            $this->assert($result1['success'], "First site creation should succeed");
            $this->createdSiteIds[] = $result1['data']['id'];
            
            // Create second site with same name but different LHO
            $differentLHOData = $inputData;
            $differentLHOData['lho'] = 'LHO_' . $this->generateRandomString(5) . '_2';
            
            $result2 = $this->siteService->createSite($differentLHOData, $this->testUserId);
            
            // Should succeed
            $this->assert(
                $result2['success'],
                "Site with same name in different LHO should be allowed"
            );
            $this->createdSiteIds[] = $result2['data']['id'];
            
            // Verify both sites exist
            $site1 = $this->siteService->getSite($result1['data']['id']);
            $site2 = $this->siteService->getSite($result2['data']['id']);
            
            $this->assert($site1 !== null, "First site should exist");
            $this->assert($site2 !== null, "Second site should exist");
            $this->assert(
                $site1['site_name'] === $site2['site_name'],
                "Both sites should have the same name"
            );
            $this->assert(
                $site1['lho'] !== $site2['lho'],
                "Sites should have different LHOs"
            );
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => $inputData ?? null
            ];
        }
    }
    
    /**
     * Clean up test data
     */
    protected function cleanupTestData(): void {
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
