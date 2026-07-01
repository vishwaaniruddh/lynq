<?php
/**
 * Property Test for Material Request Pagination Consistency
 * **Feature: material-request-module, Property 10: Pagination Consistency**
 * **Validates: Requirements 4.5**
 * 
 * For any number of material request records, the pagination should correctly divide results 
 * into pages and navigate between them without data loss or duplication.
 */

require_once __DIR__ . '/PropertyTestBase.php';
require_once __DIR__ . '/../services/MaterialRequestService.php';
require_once __DIR__ . '/../services/MaterialMasterService.php';
require_once __DIR__ . '/../repositories/MaterialRequestRepository.php';

class MaterialRequestPaginationConsistencyPropertyTest extends PropertyTestBase {
    
    private $materialRequestService;
    private $materialMasterService;
    private $materialRequestRepository;
    private $createdRecords = [];
    private $testCompanyId;
    private $testUserId;
    private $testProductIds = [];
    private $testMasterIds = [];
    
    public function __construct() {
        parent::__construct();
        $this->materialRequestService = new MaterialRequestService();
        $this->materialMasterService = new MaterialMasterService();
        $this->materialRequestRepository = new MaterialRequestRepository();
        $this->iterations = 20; // As specified in design doc
    }
    
    public function runTests() {
        echo "=== Material Request Pagination Consistency Property Tests ===\n\n";
        
        // Setup test data
        $this->setupTestData();
        
        $allPassed = true;
        
        // Test Pagination Total Consistency
        $allPassed &= $this->runPropertyTest(
            "Pagination Total Consistency",
            [$this, 'testPaginationTotalConsistency']
        );
        
        // Test No Data Loss Across Pages
        $allPassed &= $this->runPropertyTest(
            "No Data Loss Across Pages",
            [$this, 'testNoDataLossAcrossPages']
        );
        
        // Test No Duplicate Records Across Pages
        $allPassed &= $this->runPropertyTest(
            "No Duplicate Records Across Pages",
            [$this, 'testNoDuplicateRecordsAcrossPages']
        );
        
        // Cleanup test data
        $this->cleanupTestData();
        
        return $allPassed;
    }
    
    /**
     * Property 10: Pagination Total Consistency
     * The total count should remain consistent across all page requests
     * **Feature: material-request-module, Property 10: Pagination Consistency**
     * **Validates: Requirements 4.5**
     */
    public function testPaginationTotalConsistency() {
        try {
            // Get random page size
            $pageSize = rand(2, 5);
            
            // Get first page
            $filters = [
                'page' => 1,
                'limit' => $pageSize
            ];
            
            $firstPageResult = $this->materialRequestRepository->findAllPaginated($filters, $this->testCompanyId);
            $expectedTotal = $firstPageResult['total'];
            $expectedTotalPages = $firstPageResult['totalPages'];
            
            // Check multiple pages have consistent total
            $maxPages = min(3, $expectedTotalPages);
            for ($page = 1; $page <= $maxPages; $page++) {
                $filters['page'] = $page;
                $result = $this->materialRequestRepository->findAllPaginated($filters, $this->testCompanyId);
                
                $this->assert(
                    $result['total'] === $expectedTotal,
                    "Page $page total ({$result['total']}) differs from expected ($expectedTotal)"
                );
                
                $this->assert(
                    $result['totalPages'] === $expectedTotalPages,
                    "Page $page totalPages ({$result['totalPages']}) differs from expected ($expectedTotalPages)"
                );
            }
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => ['page_size' => $pageSize ?? null]
            ];
        }
    }
    
    /**
     * Property 10: No Data Loss Across Pages
     * The sum of records across all pages should equal the total count
     * **Feature: material-request-module, Property 10: Pagination Consistency**
     * **Validates: Requirements 4.5**
     */
    public function testNoDataLossAcrossPages() {
        try {
            // Get random page size
            $pageSize = rand(2, 4);
            
            // Get first page to know total
            $filters = [
                'page' => 1,
                'limit' => $pageSize
            ];
            
            $firstPageResult = $this->materialRequestRepository->findAllPaginated($filters, $this->testCompanyId);
            $expectedTotal = $firstPageResult['total'];
            $totalPages = $firstPageResult['totalPages'];
            
            // Collect all records across pages
            $allRecordIds = [];
            
            for ($page = 1; $page <= $totalPages; $page++) {
                $filters['page'] = $page;
                $result = $this->materialRequestRepository->findAllPaginated($filters, $this->testCompanyId);
                
                foreach ($result['data'] as $record) {
                    $allRecordIds[] = $record['id'];
                }
            }
            
            // Verify total count matches
            $actualCount = count($allRecordIds);
            $this->assert(
                $actualCount === $expectedTotal,
                "Total records across pages ($actualCount) does not match expected total ($expectedTotal)"
            );
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => ['page_size' => $pageSize ?? null, 'total_pages' => $totalPages ?? null]
            ];
        }
    }
    
    /**
     * Property 10: No Duplicate Records Across Pages
     * Each record should appear exactly once across all pages
     * **Feature: material-request-module, Property 10: Pagination Consistency**
     * **Validates: Requirements 4.5**
     */
    public function testNoDuplicateRecordsAcrossPages() {
        try {
            // Get random page size
            $pageSize = rand(2, 4);
            
            // Get first page to know total pages
            $filters = [
                'page' => 1,
                'limit' => $pageSize
            ];
            
            $firstPageResult = $this->materialRequestRepository->findAllPaginated($filters, $this->testCompanyId);
            $totalPages = $firstPageResult['totalPages'];
            
            // Collect all record IDs across pages
            $allRecordIds = [];
            
            for ($page = 1; $page <= $totalPages; $page++) {
                $filters['page'] = $page;
                $result = $this->materialRequestRepository->findAllPaginated($filters, $this->testCompanyId);
                
                foreach ($result['data'] as $record) {
                    $allRecordIds[] = $record['id'];
                }
            }
            
            // Check for duplicates
            $uniqueIds = array_unique($allRecordIds);
            $duplicateCount = count($allRecordIds) - count($uniqueIds);
            
            $this->assert(
                $duplicateCount === 0,
                "Found $duplicateCount duplicate records across pages"
            );
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => ['page_size' => $pageSize ?? null]
            ];
        }
    }
    
    /**
     * Create a test site
     */
    private function createTestSite(): int {
        $siteName = 'PaginationTest Site ' . $this->generateRandomString(8);
        
        $stmt = $this->executeQuery(
            "INSERT INTO sites (site_name, company_id, status, created_at) VALUES (?, ?, ?, NOW())",
            [$siteName, $this->testCompanyId, 'active'],
            'sis'
        );
        $siteId = $this->db->insert_id;
        $stmt->close();
        
        $this->createdRecords['sites'][] = $siteId;
        return $siteId;
    }
    
    /**
     * Setup test data with multiple requests for pagination testing
     */
    private function setupTestData() {
        try {
            // Get or create test company
            $result = $this->getResults("SELECT id FROM companies WHERE type = 'ADV' LIMIT 1");
            if (!empty($result)) {
                $this->testCompanyId = (int)$result[0]['id'];
            } else {
                $stmt = $this->executeQuery(
                    "INSERT INTO companies (name, type, status) VALUES (?, ?, ?)",
                    ['Test Company Pagination', 'ADV', 'ACTIVE'],
                    'sss'
                );
                $this->testCompanyId = $this->db->insert_id;
                $stmt->close();
                $this->createdRecords['companies'][] = $this->testCompanyId;
            }
            
            // Get or create test user
            $result = $this->getResults("SELECT id FROM users WHERE company_id = ? LIMIT 1", [$this->testCompanyId], 'i');
            if (!empty($result)) {
                $this->testUserId = (int)$result[0]['id'];
            } else {
                $stmt = $this->executeQuery(
                    "INSERT INTO users (username, email, password_hash, first_name, last_name, company_id, role_id, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                    ['test_pagination_' . $this->generateRandomString(5), 'pagination_' . $this->generateRandomString(5) . '@test.com', password_hash('test123', PASSWORD_DEFAULT), 'Test', 'User', $this->testCompanyId, 1, 1],
                    'sssssiii'
                );
                $this->testUserId = $this->db->insert_id;
                $stmt->close();
                $this->createdRecords['users'][] = $this->testUserId;
            }
            
            // Get existing products or create test products
            $result = $this->getResults("SELECT id FROM products LIMIT 2");
            if (!empty($result)) {
                foreach ($result as $row) {
                    $this->testProductIds[] = (int)$row['id'];
                }
            }
            
            // If not enough products, create some
            while (count($this->testProductIds) < 1) {
                $stmt = $this->executeQuery(
                    "INSERT INTO products (name, unit_of_measure, is_serializable, is_repairable, inventory_type, status) VALUES (?, ?, ?, ?, ?, ?)",
                    ['Pagination Test Product ' . $this->generateRandomString(5), 'unit', 0, 0, 'consumable', 'active'],
                    'ssiiss'
                );
                $productId = $this->db->insert_id;
                $stmt->close();
                $this->testProductIds[] = $productId;
                $this->createdRecords['products'][] = $productId;
            }
            
            // Create test Material Master
            $masterData = [
                'name' => 'PaginationTest Master ' . $this->generateRandomString(5),
                'description' => 'Test description for pagination',
                'status' => 'active',
                'items' => [
                    ['product_id' => $this->testProductIds[0], 'quantity' => 3]
                ]
            ];
            
            $result = $this->materialMasterService->create($masterData, $this->testUserId, $this->testCompanyId);
            if ($result['success']) {
                $this->testMasterIds[] = $result['data']['id'];
                $this->createdRecords['material_masters'][] = $result['data']['id'];
            }
            
            if (empty($this->testMasterIds)) {
                throw new Exception("Failed to create test Material Master");
            }
            
            // Create multiple test requests for pagination testing (at least 10)
            $numRequests = rand(8, 12);
            
            for ($i = 0; $i < $numRequests; $i++) {
                // Create a site for this request
                $siteId = $this->createTestSite();
                
                // Create request
                $createResult = $this->materialRequestService->create(
                    $siteId,
                    $this->testMasterIds[0],
                    $this->testUserId,
                    $this->testCompanyId,
                    'Pagination test ' . $i
                );
                
                if ($createResult['success']) {
                    $this->createdRecords['material_requests'][] = $createResult['data']['id'];
                }
            }
            
        } catch (Exception $e) {
            echo "Setup warning: " . $e->getMessage() . "\n";
        }
    }
    
    /**
     * Clean up all test data created during tests
     */
    public function cleanupTestData() {
        try {
            // Delete material request items first
            if (isset($this->createdRecords['material_requests']) && !empty($this->createdRecords['material_requests'])) {
                $ids = implode(',', array_map('intval', $this->createdRecords['material_requests']));
                $this->db->query("DELETE FROM material_request_items WHERE material_request_id IN ($ids)");
                $this->db->query("DELETE FROM material_requests WHERE id IN ($ids)");
            }
            
            // Delete material master items and masters
            if (isset($this->createdRecords['material_masters']) && !empty($this->createdRecords['material_masters'])) {
                $ids = implode(',', array_map('intval', $this->createdRecords['material_masters']));
                $this->db->query("DELETE FROM material_master_items WHERE material_master_id IN ($ids)");
                $this->db->query("DELETE FROM material_masters WHERE id IN ($ids)");
            }
            
            // Delete test sites
            if (isset($this->createdRecords['sites']) && !empty($this->createdRecords['sites'])) {
                $ids = implode(',', array_map('intval', $this->createdRecords['sites']));
                $this->db->query("DELETE FROM sites WHERE id IN ($ids)");
            }
            
            // Clean up by name pattern
            $this->db->query("DELETE FROM material_request_items WHERE material_request_id IN (SELECT id FROM material_requests WHERE notes LIKE 'Pagination test %')");
            $this->db->query("DELETE FROM material_requests WHERE notes LIKE 'Pagination test %'");
            $this->db->query("DELETE FROM material_master_items WHERE material_master_id IN (SELECT id FROM material_masters WHERE name LIKE 'PaginationTest Master %')");
            $this->db->query("DELETE FROM material_masters WHERE name LIKE 'PaginationTest Master %'");
            $this->db->query("DELETE FROM sites WHERE site_name LIKE 'PaginationTest Site %'");
            
            // Clean up test products
            if (isset($this->createdRecords['products']) && !empty($this->createdRecords['products'])) {
                $ids = implode(',', array_map('intval', $this->createdRecords['products']));
                $this->db->query("DELETE FROM products WHERE id IN ($ids)");
            }
            
            // Clean up test users
            if (isset($this->createdRecords['users']) && !empty($this->createdRecords['users'])) {
                $ids = implode(',', array_map('intval', $this->createdRecords['users']));
                $this->db->query("DELETE FROM users WHERE id IN ($ids)");
            }
            
            // Clean up test companies
            if (isset($this->createdRecords['companies']) && !empty($this->createdRecords['companies'])) {
                $ids = implode(',', array_map('intval', $this->createdRecords['companies']));
                $this->db->query("DELETE FROM companies WHERE id IN ($ids)");
            }
            
            $this->createdRecords = [];
            
        } catch (Exception $e) {
            echo "Cleanup warning: " . $e->getMessage() . "\n";
        }
    }
}
