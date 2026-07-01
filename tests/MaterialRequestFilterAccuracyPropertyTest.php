<?php
/**
 * Property Test for Material Request Filter Accuracy
 * **Feature: material-request-module, Property 9: Filter Accuracy**
 * **Validates: Requirements 4.2**
 * 
 * For any filter criteria (status, date range, site name) applied to the material requests API,
 * all returned results should match the specified filter criteria.
 */

require_once __DIR__ . '/PropertyTestBase.php';
require_once __DIR__ . '/../services/MaterialRequestService.php';
require_once __DIR__ . '/../services/MaterialMasterService.php';
require_once __DIR__ . '/../repositories/MaterialRequestRepository.php';

class MaterialRequestFilterAccuracyPropertyTest extends PropertyTestBase {
    
    private $materialRequestService;
    private $materialMasterService;
    private $materialRequestRepository;
    private $createdRecords = [];
    private $testCompanyId;
    private $testUserId;
    private $testProductIds = [];
    private $testSiteIds = [];
    private $testMasterIds = [];
    private $testRequestIds = [];
    
    public function __construct() {
        parent::__construct();
        $this->materialRequestService = new MaterialRequestService();
        $this->materialMasterService = new MaterialMasterService();
        $this->materialRequestRepository = new MaterialRequestRepository();
        $this->iterations = 20; // As specified in design doc
    }
    
    public function runTests() {
        echo "=== Material Request Filter Accuracy Property Tests ===\n\n";
        
        // Setup test data
        $this->setupTestData();
        
        $allPassed = true;
        
        // Test Status Filter Accuracy
        $allPassed &= $this->runPropertyTest(
            "Status Filter Accuracy",
            [$this, 'testStatusFilterAccuracy']
        );
        
        // Test Date Range Filter Accuracy
        $allPassed &= $this->runPropertyTest(
            "Date Range Filter Accuracy",
            [$this, 'testDateRangeFilterAccuracy']
        );
        
        // Test Search Filter Accuracy
        $allPassed &= $this->runPropertyTest(
            "Search Filter Accuracy",
            [$this, 'testSearchFilterAccuracy']
        );
        
        // Cleanup test data
        $this->cleanupTestData();
        
        return $allPassed;
    }
    
    /**
     * Property 9: Status Filter Accuracy
     * For any status filter, all returned results should have that status
     * **Feature: material-request-module, Property 9: Filter Accuracy**
     * **Validates: Requirements 4.2**
     */
    public function testStatusFilterAccuracy() {
        try {
            // Pick a random status to filter by
            $statuses = ['requested', 'approved', 'dispatched', 'received'];
            $filterStatus = $this->generateRandomChoice($statuses);
            
            // Get filtered results
            $filters = [
                'status' => $filterStatus,
                'page' => 1,
                'limit' => 100
            ];
            
            $result = $this->materialRequestRepository->findAllPaginated($filters, $this->testCompanyId);
            
            // Verify all returned results match the filter
            foreach ($result['data'] as $request) {
                $this->assert(
                    $request['status'] === $filterStatus,
                    "Request ID {$request['id']} has status '{$request['status']}' but filter was '$filterStatus'"
                );
            }
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => ['filter_status' => $filterStatus ?? null]
            ];
        }
    }
    
    /**
     * Property 9: Date Range Filter Accuracy
     * For any date range filter, all returned results should fall within that range
     * **Feature: material-request-module, Property 9: Filter Accuracy**
     * **Validates: Requirements 4.2**
     */
    public function testDateRangeFilterAccuracy() {
        try {
            // Generate random date range (within last 30 days)
            $daysAgo = rand(1, 30);
            $dateFrom = date('Y-m-d', strtotime("-$daysAgo days"));
            $dateTo = date('Y-m-d'); // Today
            
            // Get filtered results
            $filters = [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'page' => 1,
                'limit' => 100
            ];
            
            $result = $this->materialRequestRepository->findAllPaginated($filters, $this->testCompanyId);
            
            // Verify all returned results fall within the date range
            foreach ($result['data'] as $request) {
                $requestDate = date('Y-m-d', strtotime($request['requested_at']));
                
                $this->assert(
                    $requestDate >= $dateFrom,
                    "Request ID {$request['id']} date '$requestDate' is before filter start '$dateFrom'"
                );
                
                $this->assert(
                    $requestDate <= $dateTo,
                    "Request ID {$request['id']} date '$requestDate' is after filter end '$dateTo'"
                );
            }
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => ['date_from' => $dateFrom ?? null, 'date_to' => $dateTo ?? null]
            ];
        }
    }
    
    /**
     * Property 9: Search Filter Accuracy
     * For any search term, all returned results should contain that term in site name or material master name
     * **Feature: material-request-module, Property 9: Filter Accuracy**
     * **Validates: Requirements 4.2**
     */
    public function testSearchFilterAccuracy() {
        try {
            // Use a known search term from our test data
            $searchTerms = ['FilterTest', 'Site', 'Master'];
            $searchTerm = $this->generateRandomChoice($searchTerms);
            
            // Get filtered results
            $filters = [
                'search' => $searchTerm,
                'page' => 1,
                'limit' => 100
            ];
            
            $result = $this->materialRequestRepository->findAllPaginated($filters, $this->testCompanyId);
            
            // Verify all returned results contain the search term
            foreach ($result['data'] as $request) {
                $siteName = strtolower($request['site_name'] ?? '');
                $masterName = strtolower($request['material_master_name'] ?? '');
                $searchLower = strtolower($searchTerm);
                
                $matchesSite = strpos($siteName, $searchLower) !== false;
                $matchesMaster = strpos($masterName, $searchLower) !== false;
                
                $this->assert(
                    $matchesSite || $matchesMaster,
                    "Request ID {$request['id']} (site: '{$request['site_name']}', master: '{$request['material_master_name']}') does not match search term '$searchTerm'"
                );
            }
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => ['search_term' => $searchTerm ?? null]
            ];
        }
    }
    
    /**
     * Create a test site
     */
    private function createTestSite($nameSuffix = ''): int {
        $siteName = 'FilterTest Site ' . $nameSuffix . $this->generateRandomString(5);
        
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
     * Setup test data with various statuses for filter testing
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
                    ['Test Company Filter', 'ADV', 'ACTIVE'],
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
                    ['test_filter_' . $this->generateRandomString(5), 'filter_' . $this->generateRandomString(5) . '@test.com', password_hash('test123', PASSWORD_DEFAULT), 'Test', 'User', $this->testCompanyId, 1, 1],
                    'sssssiii'
                );
                $this->testUserId = $this->db->insert_id;
                $stmt->close();
                $this->createdRecords['users'][] = $this->testUserId;
            }
            
            // Get existing products or create test products
            $result = $this->getResults("SELECT id FROM products LIMIT 3");
            if (!empty($result)) {
                foreach ($result as $row) {
                    $this->testProductIds[] = (int)$row['id'];
                }
            }
            
            // If not enough products, create some
            while (count($this->testProductIds) < 2) {
                $stmt = $this->executeQuery(
                    "INSERT INTO products (name, unit_of_measure, is_serializable, is_repairable, inventory_type, status) VALUES (?, ?, ?, ?, ?, ?)",
                    ['Filter Test Product ' . $this->generateRandomString(5), 'unit', 0, 0, 'consumable', 'active'],
                    'ssiiss'
                );
                $productId = $this->db->insert_id;
                $stmt->close();
                $this->testProductIds[] = $productId;
                $this->createdRecords['products'][] = $productId;
            }
            
            // Create test Material Master
            $masterData = [
                'name' => 'FilterTest Master ' . $this->generateRandomString(5),
                'description' => 'Test description for filter',
                'status' => 'active',
                'items' => [
                    ['product_id' => $this->testProductIds[0], 'quantity' => 5]
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
            
            // Create test requests with different statuses
            $statuses = ['requested', 'approved', 'dispatched', 'received'];
            
            foreach ($statuses as $status) {
                // Create a site for this request
                $siteId = $this->createTestSite($status);
                
                // Create request
                $createResult = $this->materialRequestService->create(
                    $siteId,
                    $this->testMasterIds[0],
                    $this->testUserId,
                    $this->testCompanyId,
                    'Filter test ' . $status
                );
                
                if ($createResult['success']) {
                    $requestId = $createResult['data']['id'];
                    $this->testRequestIds[] = $requestId;
                    $this->createdRecords['material_requests'][] = $requestId;
                    
                    // Update status if not 'requested'
                    if ($status !== 'requested') {
                        // Progress through statuses
                        if (in_array($status, ['approved', 'dispatched', 'received'])) {
                            $this->materialRequestService->updateStatus($requestId, 'approved', $this->testUserId, $this->testCompanyId);
                        }
                        if (in_array($status, ['dispatched', 'received'])) {
                            $this->materialRequestService->updateStatus($requestId, 'dispatched', $this->testUserId, $this->testCompanyId);
                        }
                        if ($status === 'received') {
                            $this->materialRequestService->updateStatus($requestId, 'received', $this->testUserId, $this->testCompanyId);
                        }
                    }
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
            $this->db->query("DELETE FROM material_request_items WHERE material_request_id IN (SELECT id FROM material_requests WHERE notes LIKE 'Filter test %')");
            $this->db->query("DELETE FROM material_requests WHERE notes LIKE 'Filter test %'");
            $this->db->query("DELETE FROM material_master_items WHERE material_master_id IN (SELECT id FROM material_masters WHERE name LIKE 'FilterTest Master %')");
            $this->db->query("DELETE FROM material_masters WHERE name LIKE 'FilterTest Master %'");
            $this->db->query("DELETE FROM sites WHERE site_name LIKE 'FilterTest Site %'");
            
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
