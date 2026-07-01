<?php
/**
 * Property Test for Bulk Site Import
 * **Feature: site-management-delegation, Property 3: Bulk import processes all valid rows**
 * **Validates: Requirements 1.2, 1.3**
 * 
 * Property 3: For any Excel file containing N valid site rows and M invalid rows, 
 * bulk import should create exactly N site records and return exactly M error entries 
 * with specific error messages.
 */

require_once 'PropertyTestBase.php';
require_once __DIR__ . '/../services/SiteService.php';
require_once __DIR__ . '/../services/BulkOperationService.php';
require_once __DIR__ . '/../repositories/SiteRepository.php';

class BulkSiteImportPropertyTest extends PropertyTestBase {
    
    private $siteService;
    private $bulkOperationService;
    private $siteRepository;
    private $testCompanyId;
    private $testUserId;
    private $createdSiteIds = [];
    
    public function __construct() {
        parent::__construct();
        $this->siteService = new SiteService();
        $this->bulkOperationService = new BulkOperationService();
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
        $this->testUserId = !empty($result) ? (int)$result[0]['id'] : 1;
    }
    
    public function runTests(): bool {
        echo "=== Bulk Site Import Property Tests ===\n\n";
        
        $allPassed = true;
        
        // Property 3: Bulk import processes all valid rows
        $allPassed &= $this->runPropertyTest(
            "Property 3: Bulk import creates exactly N sites for N valid rows",
            [$this, 'testBulkImportCreatesCorrectCount']
        );
        
        $allPassed &= $this->runPropertyTest(
            "Property 3: Bulk import returns exactly M errors for M invalid rows",
            [$this, 'testBulkImportReturnsCorrectErrorCount']
        );
        
        $allPassed &= $this->runPropertyTest(
            "Property 3: Mixed valid/invalid rows are processed correctly",
            [$this, 'testMixedValidInvalidRows']
        );
        
        $allPassed &= $this->runPropertyTest(
            "Property 3: Error messages are specific to the validation failure",
            [$this, 'testErrorMessagesAreSpecific']
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
     * Generate invalid site data (missing required fields)
     */
    private function generateInvalidSiteData(string $invalidationType = 'missing_required'): array {
        $data = $this->generateValidSiteData();
        
        switch ($invalidationType) {
            case 'missing_site_name':
                $data['site_name'] = '';
                break;
            case 'missing_lho':
                $data['lho'] = '';
                break;
            case 'missing_city':
                $data['city'] = '';
                break;
            case 'missing_state':
                $data['state'] = '';
                break;
            case 'missing_country':
                $data['country'] = '';
                break;
            case 'invalid_latitude':
                $data['latitude'] = 100; // Out of range
                break;
            case 'invalid_longitude':
                $data['longitude'] = 200; // Out of range
                break;
            case 'missing_required':
            default:
                $data['site_name'] = '';
                $data['lho'] = '';
                break;
        }
        
        return $data;
    }
    
    /**
     * Generate random valid latitude (-90 to 90)
     */
    private function generateRandomLatitude(): float {
        return round(rand(-9000000, 9000000) / 100000, 6);
    }
    
    /**
     * Generate random valid longitude (-180 to 180)
     */
    private function generateRandomLongitude(): float {
        return round(rand(-18000000, 18000000) / 100000, 6);
    }
    
    /**
     * Property 3: Bulk import creates exactly N sites for N valid rows
     * **Feature: site-management-delegation, Property 3: Bulk import processes all valid rows**
     * **Validates: Requirements 1.2**
     */
    public function testBulkImportCreatesCorrectCount(): array {
        try {
            // Generate N valid site rows (random N between 3 and 7)
            $n = rand(3, 7);
            $validSites = [];
            
            for ($i = 0; $i < $n; $i++) {
                $validSites[] = $this->generateValidSiteData();
            }
            
            // Perform bulk create
            $result = $this->siteService->bulkCreateSites($validSites, $this->testUserId);
            
            // Track created IDs for cleanup
            if (!empty($result['createdIds'])) {
                $this->createdSiteIds = array_merge($this->createdSiteIds, $result['createdIds']);
            }
            
            // Verify exactly N sites were created
            $this->assert(
                $result['successCount'] === $n,
                "Should create exactly $n sites. Created: {$result['successCount']}"
            );
            
            $this->assert(
                count($result['createdIds']) === $n,
                "Should return exactly $n created IDs. Got: " . count($result['createdIds'])
            );
            
            $this->assert(
                $result['errorCount'] === 0,
                "Should have 0 errors for all valid rows. Got: {$result['errorCount']}"
            );
            
            // Verify all sites actually exist in database
            foreach ($result['createdIds'] as $siteId) {
                $site = $this->siteService->getSite($siteId);
                $this->assert(
                    $site !== null,
                    "Created site with ID $siteId should exist in database"
                );
            }
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => ['n' => $n ?? 0, 'validSites' => $validSites ?? []]
            ];
        }
    }
    
    /**
     * Property 3: Bulk import returns exactly M errors for M invalid rows
     * **Feature: site-management-delegation, Property 3: Bulk import processes all valid rows**
     * **Validates: Requirements 1.3**
     */
    public function testBulkImportReturnsCorrectErrorCount(): array {
        try {
            // Generate M invalid site rows (random M between 2 and 5)
            $m = rand(2, 5);
            $invalidSites = [];
            
            $invalidationTypes = ['missing_site_name', 'missing_lho', 'missing_city', 'missing_state', 'missing_country'];
            
            for ($i = 0; $i < $m; $i++) {
                $invalidationType = $invalidationTypes[$i % count($invalidationTypes)];
                $invalidSites[] = $this->generateInvalidSiteData($invalidationType);
            }
            
            // Perform bulk create
            $result = $this->siteService->bulkCreateSites($invalidSites, $this->testUserId);
            
            // Verify exactly M errors were returned
            $this->assert(
                $result['errorCount'] === $m,
                "Should have exactly $m errors. Got: {$result['errorCount']}"
            );
            
            $this->assert(
                $result['successCount'] === 0,
                "Should have 0 successes for all invalid rows. Got: {$result['successCount']}"
            );
            
            $this->assert(
                count($result['errors']) === $m,
                "Should return exactly $m error entries. Got: " . count($result['errors'])
            );
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => ['m' => $m ?? 0, 'invalidSites' => $invalidSites ?? []]
            ];
        }
    }
    
    /**
     * Property 3: Mixed valid/invalid rows are processed correctly
     * **Feature: site-management-delegation, Property 3: Bulk import processes all valid rows**
     * **Validates: Requirements 1.2, 1.3**
     */
    public function testMixedValidInvalidRows(): array {
        try {
            // Generate N valid and M invalid rows
            $n = rand(2, 4);
            $m = rand(2, 4);
            
            $mixedSites = [];
            
            // Add valid sites
            for ($i = 0; $i < $n; $i++) {
                $site = $this->generateValidSiteData();
                $site['_row_number'] = $i + 2; // Simulate row numbers starting from 2
                $mixedSites[] = $site;
            }
            
            // Add invalid sites
            $invalidationTypes = ['missing_site_name', 'missing_lho', 'missing_city'];
            for ($i = 0; $i < $m; $i++) {
                $site = $this->generateInvalidSiteData($invalidationTypes[$i % count($invalidationTypes)]);
                $site['_row_number'] = $n + $i + 2;
                $mixedSites[] = $site;
            }
            
            // Shuffle to mix valid and invalid
            shuffle($mixedSites);
            
            // Perform bulk create
            $result = $this->siteService->bulkCreateSites($mixedSites, $this->testUserId);
            
            // Track created IDs for cleanup
            if (!empty($result['createdIds'])) {
                $this->createdSiteIds = array_merge($this->createdSiteIds, $result['createdIds']);
            }
            
            // Verify counts
            $this->assert(
                $result['successCount'] === $n,
                "Should create exactly $n valid sites. Created: {$result['successCount']}"
            );
            
            $this->assert(
                $result['errorCount'] === $m,
                "Should have exactly $m errors. Got: {$result['errorCount']}"
            );
            
            $this->assert(
                $result['totalRows'] === ($n + $m),
                "Total rows should be " . ($n + $m) . ". Got: {$result['totalRows']}"
            );
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => ['n' => $n ?? 0, 'm' => $m ?? 0]
            ];
        }
    }
    
    /**
     * Property 3: Error messages are specific to the validation failure
     * **Feature: site-management-delegation, Property 3: Bulk import processes all valid rows**
     * **Validates: Requirements 1.3**
     */
    public function testErrorMessagesAreSpecific(): array {
        try {
            // Create sites with specific validation failures
            $testCases = [
                ['type' => 'missing_site_name', 'expectedField' => 'site_name'],
                ['type' => 'missing_lho', 'expectedField' => 'lho'],
                ['type' => 'invalid_latitude', 'expectedField' => 'latitude'],
                ['type' => 'invalid_longitude', 'expectedField' => 'longitude']
            ];
            
            foreach ($testCases as $testCase) {
                $invalidSite = $this->generateInvalidSiteData($testCase['type']);
                $invalidSite['_row_number'] = 2;
                
                $result = $this->siteService->bulkCreateSites([$invalidSite], $this->testUserId);
                
                $this->assert(
                    $result['errorCount'] === 1,
                    "Should have 1 error for {$testCase['type']}"
                );
                
                // Check that error message references the specific field
                $hasSpecificError = false;
                foreach ($result['errors'] as $rowErrors) {
                    if (is_array($rowErrors)) {
                        foreach ($rowErrors as $error) {
                            if (is_array($error) && isset($error['field'])) {
                                if ($error['field'] === $testCase['expectedField']) {
                                    $hasSpecificError = true;
                                    break 2;
                                }
                            } elseif (is_string($error) && stripos($error, $testCase['expectedField']) !== false) {
                                $hasSpecificError = true;
                                break 2;
                            }
                        }
                    }
                }
                
                $this->assert(
                    $hasSpecificError,
                    "Error for {$testCase['type']} should reference field '{$testCase['expectedField']}'"
                );
            }
            
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
