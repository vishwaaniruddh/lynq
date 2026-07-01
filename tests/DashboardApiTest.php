<?php
/**
 * Integration Tests for Dashboard APIs
 * 
 * Tests data aggregation accuracy and role-based filtering for:
 * - ADV Dashboard API
 * - Contractor Dashboard API
 * - Engineer Dashboard API
 * 
 * Requirements: 9.1, 10.1, 11.1
 */

require_once 'PropertyTestBase.php';
require_once __DIR__ . '/../services/InventoryAccessService.php';
require_once __DIR__ . '/../repositories/StockRepository.php';
require_once __DIR__ . '/../repositories/AssetRepository.php';
require_once __DIR__ . '/../repositories/WarehouseRepository.php';
require_once __DIR__ . '/../repositories/ProductRepository.php';
require_once __DIR__ . '/../repositories/ProductCategoryRepository.php';
require_once __DIR__ . '/../repositories/DispatchRepository.php';
require_once __DIR__ . '/../models/User.php';

class DashboardApiTest extends PropertyTestBase {
    
    private $stockRepository;
    private $assetRepository;
    private $warehouseRepository;
    private $productRepository;
    private $categoryRepository;
    private $dispatchRepository;
    private $userModel;
    private $accessService;
    private $createdRecords = [];
    
    public function __construct() {
        parent::__construct();
        $this->stockRepository = new StockRepository();
        $this->assetRepository = new AssetRepository();
        $this->warehouseRepository = new WarehouseRepository();
        $this->warehouseRepository->disableCompanyFilter();
        $this->productRepository = new ProductRepository();
        $this->categoryRepository = new ProductCategoryRepository();
        $this->dispatchRepository = new DispatchRepository();
        $this->dispatchRepository->disableCompanyFilter();
        $this->userModel = new User();
        $this->accessService = new InventoryAccessService();
    }
    
    public function runTests() {
        echo "=== Dashboard API Integration Tests ===\n";
        echo "Testing data aggregation accuracy and role-based filtering\n";
        echo "**Validates: Requirements 9.1, 10.1, 11.1**\n\n";
        
        // Check if required tables exist
        if (!$this->tablesExist()) {
            echo "SKIPPED: Required tables not found. Run migrations first.\n";
            return true;
        }
        
        $allPassed = true;
        
        // Test ADV Dashboard data aggregation
        $allPassed &= $this->runTest(
            'ADV Dashboard: Stock summary aggregation',
            function() { return $this->testAdvDashboardStockSummary(); }
        );
        
        $allPassed &= $this->runTest(
            'ADV Dashboard: Asset status breakdown',
            function() { return $this->testAdvDashboardAssetStatusBreakdown(); }
        );
        
        $allPassed &= $this->runTest(
            'ADV Dashboard: Warehouse summary',
            function() { return $this->testAdvDashboardWarehouseSummary(); }
        );
        
        // Test Contractor Dashboard data filtering
        $allPassed &= $this->runTest(
            'Contractor Dashboard: Only shows company inventory',
            function() { return $this->testContractorDashboardCompanyFilter(); }
        );
        
        $allPassed &= $this->runTest(
            'Contractor Dashboard: Engineer assignments',
            function() { return $this->testContractorDashboardEngineerAssignments(); }
        );
        
        // Test Engineer Dashboard data filtering
        $allPassed &= $this->runTest(
            'Engineer Dashboard: Only shows assigned items',
            function() { return $this->testEngineerDashboardAssignedItems(); }
        );
        
        $allPassed &= $this->runTest(
            'Engineer Dashboard: Allowed actions',
            function() { return $this->testEngineerDashboardAllowedActions(); }
        );
        
        // Cleanup
        $this->cleanupTestData();
        
        return $allPassed;
    }
    
    /**
     * Run a single test
     */
    private function runTest($name, $testFn) {
        echo "Testing: $name... ";
        
        try {
            $result = $testFn();
            
            if ($result['success']) {
                echo "PASSED\n";
                return true;
            } else {
                echo "FAILED\n";
                echo "  Reason: " . $result['message'] . "\n";
                if (isset($result['data'])) {
                    echo "  Data: " . json_encode($result['data'], JSON_PRETTY_PRINT) . "\n";
                }
                return false;
            }
        } catch (Exception $e) {
            echo "ERROR\n";
            echo "  Exception: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * Test ADV Dashboard stock summary aggregation
     */
    private function testAdvDashboardStockSummary() {
        // Create test data
        $warehouse = $this->createTestWarehouse();
        $product = $this->createTestProduct(false); // Non-serializable
        
        // Add stock
        $quantity = 100;
        $reserved = 20;
        $stock = $this->stockRepository->create([
            'product_id' => $product['id'],
            'warehouse_id' => $warehouse['id'],
            'quantity' => $quantity,
            'reserved_quantity' => $reserved
        ]);
        $this->createdRecords['stock'][] = $stock['id'];
        
        // Query dashboard aggregate
        $sql = "SELECT 
                    COALESCE(SUM(s.quantity), 0) as total_quantity,
                    COALESCE(SUM(s.quantity - s.reserved_quantity), 0) as available_quantity
                FROM stock s
                WHERE s.warehouse_id = ?";
        $result = $this->getResults($sql, [$warehouse['id']], 'i');
        
        // Verify
        if ((int)$result[0]['total_quantity'] !== $quantity) {
            return [
                'success' => false,
                'message' => 'Total quantity mismatch',
                'data' => [
                    'expected' => $quantity,
                    'actual' => $result[0]['total_quantity']
                ]
            ];
        }
        
        if ((int)$result[0]['available_quantity'] !== ($quantity - $reserved)) {
            return [
                'success' => false,
                'message' => 'Available quantity mismatch',
                'data' => [
                    'expected' => $quantity - $reserved,
                    'actual' => $result[0]['available_quantity']
                ]
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * Test ADV Dashboard asset status breakdown
     */
    private function testAdvDashboardAssetStatusBreakdown() {
        // Create test data
        $warehouse = $this->createTestWarehouse();
        $product = $this->createTestProduct(true); // Serializable
        
        // Create assets with different statuses
        $statusCounts = [
            'in_stock' => 5,
            'dispatched' => 3,
            'in_use' => 2
        ];
        
        foreach ($statusCounts as $status => $count) {
            for ($i = 0; $i < $count; $i++) {
                $asset = $this->assetRepository->create([
                    'product_id' => $product['id'],
                    'warehouse_id' => $warehouse['id'],
                    'serial_number' => 'TEST-STATUS-' . $status . '-' . $this->generateRandomString(8),
                    'status' => $status,
                    'working_condition' => 'working',
                    'current_holder_type' => 'warehouse',
                    'current_holder_id' => $warehouse['id'],
                    'source_warehouse_id' => $warehouse['id']
                ]);
                $this->createdRecords['assets'][] = $asset['id'];
            }
        }
        
        // Query dashboard aggregate
        $sql = "SELECT status, COUNT(*) as count
                FROM assets
                WHERE warehouse_id = ?
                GROUP BY status";
        $results = $this->getResults($sql, [$warehouse['id']], 'i');
        
        $actualCounts = [];
        foreach ($results as $row) {
            $actualCounts[$row['status']] = (int)$row['count'];
        }
        
        // Verify each status count
        foreach ($statusCounts as $status => $expectedCount) {
            $actualCount = $actualCounts[$status] ?? 0;
            if ($actualCount !== $expectedCount) {
                return [
                    'success' => false,
                    'message' => "Status count mismatch for $status",
                    'data' => [
                        'status' => $status,
                        'expected' => $expectedCount,
                        'actual' => $actualCount
                    ]
                ];
            }
        }
        
        return ['success' => true];
    }
    
    /**
     * Test ADV Dashboard warehouse summary
     */
    private function testAdvDashboardWarehouseSummary() {
        // Create test warehouses
        $warehouse1 = $this->createTestWarehouse('active');
        $warehouse2 = $this->createTestWarehouse('inactive');
        
        // Query warehouse summary
        $sql = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_count
                FROM warehouses
                WHERE id IN (?, ?)";
        $result = $this->getResults($sql, [$warehouse1['id'], $warehouse2['id']], 'ii');
        
        // Verify
        if ((int)$result[0]['total'] !== 2) {
            return [
                'success' => false,
                'message' => 'Total warehouse count mismatch',
                'data' => ['expected' => 2, 'actual' => $result[0]['total']]
            ];
        }
        
        if ((int)$result[0]['active_count'] !== 1) {
            return [
                'success' => false,
                'message' => 'Active warehouse count mismatch',
                'data' => ['expected' => 1, 'actual' => $result[0]['active_count']]
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * Test Contractor Dashboard only shows company inventory
     */
    private function testContractorDashboardCompanyFilter() {
        // Create two companies
        $advCompanyId = $this->createTestCompany('ADV');
        $contractorCompanyId = $this->createTestCompany('CONTRACTOR');
        
        // Create warehouses for each
        $advWarehouse = $this->createTestWarehouseForCompany($advCompanyId);
        $contractorWarehouse = $this->createTestWarehouseForCompany($contractorCompanyId);
        
        // Create product
        $product = $this->createTestProduct(true);
        
        // Create assets - some for ADV, some for contractor
        $advAsset = $this->assetRepository->create([
            'product_id' => $product['id'],
            'warehouse_id' => $advWarehouse['id'],
            'serial_number' => 'ADV-' . $this->generateRandomString(8),
            'status' => 'in_stock',
            'working_condition' => 'working',
            'current_holder_type' => 'company',
            'current_holder_id' => $advCompanyId,
            'source_warehouse_id' => $advWarehouse['id']
        ]);
        $this->createdRecords['assets'][] = $advAsset['id'];
        
        $contractorAsset = $this->assetRepository->create([
            'product_id' => $product['id'],
            'warehouse_id' => $contractorWarehouse['id'],
            'serial_number' => 'CONTRACTOR-' . $this->generateRandomString(8),
            'status' => 'assigned',
            'working_condition' => 'working',
            'current_holder_type' => 'company',
            'current_holder_id' => $contractorCompanyId,
            'source_warehouse_id' => $advWarehouse['id']
        ]);
        $this->createdRecords['assets'][] = $contractorAsset['id'];
        
        // Query contractor's inventory (simulating contractor dashboard)
        $sql = "SELECT COUNT(*) as count
                FROM assets a
                WHERE a.current_holder_type = 'company' AND a.current_holder_id = ?";
        $result = $this->getResults($sql, [$contractorCompanyId], 'i');
        
        // Contractor should only see their own asset
        if ((int)$result[0]['count'] !== 1) {
            return [
                'success' => false,
                'message' => 'Contractor sees wrong number of assets',
                'data' => ['expected' => 1, 'actual' => $result[0]['count']]
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * Test Contractor Dashboard engineer assignments
     */
    private function testContractorDashboardEngineerAssignments() {
        // Create contractor company
        $contractorCompanyId = $this->createTestCompany('CONTRACTOR');
        
        // Create engineer user
        $engineerId = $this->createTestUser($contractorCompanyId, 'engineer');
        
        // Create warehouse and product
        $warehouse = $this->createTestWarehouseForCompany($contractorCompanyId);
        $product = $this->createTestProduct(true);
        
        // Create assets assigned to engineer
        $numAssets = 3;
        for ($i = 0; $i < $numAssets; $i++) {
            $asset = $this->assetRepository->create([
                'product_id' => $product['id'],
                'warehouse_id' => $warehouse['id'],
                'serial_number' => 'ENG-ASSIGN-' . $this->generateRandomString(8),
                'status' => 'assigned',
                'working_condition' => 'working',
                'current_holder_type' => 'user',
                'current_holder_id' => $engineerId,
                'source_warehouse_id' => $warehouse['id']
            ]);
            $this->createdRecords['assets'][] = $asset['id'];
        }
        
        // Query engineer assignments (simulating contractor dashboard)
        $sql = "SELECT COUNT(*) as count
                FROM assets a
                WHERE a.current_holder_type = 'user' AND a.current_holder_id = ?";
        $result = $this->getResults($sql, [$engineerId], 'i');
        
        if ((int)$result[0]['count'] !== $numAssets) {
            return [
                'success' => false,
                'message' => 'Engineer assignment count mismatch',
                'data' => ['expected' => $numAssets, 'actual' => $result[0]['count']]
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * Test Engineer Dashboard only shows assigned items
     */
    private function testEngineerDashboardAssignedItems() {
        // Create contractor company
        $contractorCompanyId = $this->createTestCompany('CONTRACTOR');
        
        // Create two engineers
        $engineer1Id = $this->createTestUser($contractorCompanyId, 'engineer');
        $engineer2Id = $this->createTestUser($contractorCompanyId, 'engineer');
        
        // Create warehouse and product
        $warehouse = $this->createTestWarehouseForCompany($contractorCompanyId);
        $product = $this->createTestProduct(true);
        
        // Create assets for engineer 1
        $eng1Assets = 2;
        for ($i = 0; $i < $eng1Assets; $i++) {
            $asset = $this->assetRepository->create([
                'product_id' => $product['id'],
                'warehouse_id' => $warehouse['id'],
                'serial_number' => 'ENG1-' . $this->generateRandomString(8),
                'status' => 'in_use',
                'working_condition' => 'working',
                'current_holder_type' => 'user',
                'current_holder_id' => $engineer1Id,
                'source_warehouse_id' => $warehouse['id']
            ]);
            $this->createdRecords['assets'][] = $asset['id'];
        }
        
        // Create assets for engineer 2
        $eng2Assets = 3;
        for ($i = 0; $i < $eng2Assets; $i++) {
            $asset = $this->assetRepository->create([
                'product_id' => $product['id'],
                'warehouse_id' => $warehouse['id'],
                'serial_number' => 'ENG2-' . $this->generateRandomString(8),
                'status' => 'in_use',
                'working_condition' => 'working',
                'current_holder_type' => 'user',
                'current_holder_id' => $engineer2Id,
                'source_warehouse_id' => $warehouse['id']
            ]);
            $this->createdRecords['assets'][] = $asset['id'];
        }
        
        // Query engineer 1's items (simulating engineer dashboard)
        $sql = "SELECT COUNT(*) as count
                FROM assets a
                WHERE a.current_holder_type = 'user' AND a.current_holder_id = ?
                AND a.status NOT IN ('scrapped', 'lost')";
        $result = $this->getResults($sql, [$engineer1Id], 'i');
        
        // Engineer 1 should only see their own assets
        if ((int)$result[0]['count'] !== $eng1Assets) {
            return [
                'success' => false,
                'message' => 'Engineer sees wrong number of assets',
                'data' => ['expected' => $eng1Assets, 'actual' => $result[0]['count']]
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * Test Engineer Dashboard allowed actions
     */
    private function testEngineerDashboardAllowedActions() {
        $allowedStatuses = $this->accessService->getEngineerAllowedStatuses();
        $allowedConditions = $this->accessService->getEngineerAllowedConditions();
        
        // Verify allowed statuses
        $expectedStatuses = ['in_use', 'returned'];
        foreach ($expectedStatuses as $status) {
            if (!in_array($status, $allowedStatuses)) {
                return [
                    'success' => false,
                    'message' => "Expected status '$status' not in allowed list",
                    'data' => ['allowed' => $allowedStatuses]
                ];
            }
        }
        
        // Verify disallowed statuses
        $disallowedStatuses = ['in_stock', 'dispatched', 'under_repair', 'scrapped', 'lost'];
        foreach ($disallowedStatuses as $status) {
            if (in_array($status, $allowedStatuses)) {
                return [
                    'success' => false,
                    'message' => "Status '$status' should not be allowed for engineers",
                    'data' => ['allowed' => $allowedStatuses]
                ];
            }
        }
        
        // Verify allowed conditions
        $expectedConditions = ['working', 'not_working'];
        foreach ($expectedConditions as $condition) {
            if (!in_array($condition, $allowedConditions)) {
                return [
                    'success' => false,
                    'message' => "Expected condition '$condition' not in allowed list",
                    'data' => ['allowed' => $allowedConditions]
                ];
            }
        }
        
        return ['success' => true];
    }

    
    /**
     * Check if required tables exist
     */
    private function tablesExist() {
        $requiredTables = ['stock', 'assets', 'warehouses', 'products', 'companies', 'users'];
        
        foreach ($requiredTables as $table) {
            try {
                $result = $this->db->query("SHOW TABLES LIKE '$table'");
                if (!$result || $result->num_rows === 0) {
                    return false;
                }
            } catch (Exception $e) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Create a test warehouse
     */
    private function createTestWarehouse($status = 'active') {
        $companyId = $this->getOrCreateTestCompanyId();
        return $this->createTestWarehouseForCompany($companyId, $status);
    }
    
    /**
     * Create a test warehouse for a specific company
     */
    private function createTestWarehouseForCompany($companyId, $status = 'active') {
        $warehouseData = [
            'name' => 'Test Warehouse ' . $this->generateRandomString(8),
            'location' => 'Test Location',
            'company_id' => $companyId,
            'status' => $status
        ];
        
        $warehouse = $this->warehouseRepository->create($warehouseData);
        $this->createdRecords['warehouses'][] = $warehouse['id'];
        return $warehouse;
    }
    
    /**
     * Create a test product
     */
    private function createTestProduct($isSerializable) {
        $categoryId = $this->getOrCreateTestCategoryId();
        
        $productData = [
            'name' => 'Test Product ' . $this->generateRandomString(8),
            'category_id' => $categoryId,
            'unit_of_measure' => 'unit',
            'inventory_type' => 'INTERNAL',
            'is_serializable' => $isSerializable ? 1 : 0,
            'is_repairable' => 1,
            'low_stock_threshold' => 10,
            'status' => 'active'
        ];
        
        $product = $this->productRepository->create($productData);
        $this->createdRecords['products'][] = $product['id'];
        return $product;
    }
    
    /**
     * Create a test company
     */
    private function createTestCompany($type = 'ADV') {
        $this->executeQuery(
            "INSERT INTO companies (name, type, status) VALUES (?, ?, ?)",
            ['Test Company ' . $this->generateRandomString(8), $type, 'ACTIVE'],
            'sss'
        );
        $companyId = $this->db->insert_id;
        $this->createdRecords['companies'][] = $companyId;
        return $companyId;
    }
    
    /**
     * Create a test user
     */
    private function createTestUser($companyId, $roleType = 'user') {
        // Get or create role
        $roleId = $this->getOrCreateTestRoleId($roleType);
        
        $this->executeQuery(
            "INSERT INTO users (first_name, last_name, email, company_id, role_id, status) VALUES (?, ?, ?, ?, ?, ?)",
            [
                'Test',
                'User ' . $this->generateRandomString(4),
                'test' . $this->generateRandomString(8) . '@test.com',
                $companyId,
                $roleId,
                'ACTIVE'
            ],
            'sssiss'
        );
        $userId = $this->db->insert_id;
        $this->createdRecords['users'][] = $userId;
        return $userId;
    }
    
    /**
     * Get or create test company ID
     */
    private function getOrCreateTestCompanyId() {
        $sql = "SELECT id FROM companies WHERE status = 'ACTIVE' LIMIT 1";
        $result = $this->getResults($sql);
        
        if (empty($result)) {
            return $this->createTestCompany('ADV');
        }
        
        return $result[0]['id'];
    }
    
    /**
     * Get or create test category ID
     */
    private function getOrCreateTestCategoryId() {
        $sql = "SELECT id FROM product_categories WHERE status = 'active' LIMIT 1";
        $result = $this->getResults($sql);
        
        if (empty($result)) {
            $categoryData = [
                'name' => 'Test Category ' . $this->generateRandomString(8),
                'status' => 'active'
            ];
            $category = $this->categoryRepository->create($categoryData);
            $this->createdRecords['categories'][] = $category['id'];
            return $category['id'];
        }
        
        return $result[0]['id'];
    }
    
    /**
     * Get or create test role ID
     */
    private function getOrCreateTestRoleId($roleType) {
        $level = $roleType === 'engineer' ? 1 : 5;
        $roleName = $roleType === 'engineer' ? 'Engineer' : 'Manager';
        
        $sql = "SELECT id FROM roles WHERE level = ? LIMIT 1";
        $result = $this->getResults($sql, [$level], 'i');
        
        if (empty($result)) {
            $this->executeQuery(
                "INSERT INTO roles (name, level) VALUES (?, ?)",
                [$roleName . ' ' . $this->generateRandomString(4), $level],
                'si'
            );
            $roleId = $this->db->insert_id;
            $this->createdRecords['roles'][] = $roleId;
            return $roleId;
        }
        
        return $result[0]['id'];
    }
    
    /**
     * Clean up all test data created during tests
     */
    public function cleanupTestData() {
        try {
            // Delete in order of dependencies
            if (!empty($this->createdRecords['assets'])) {
                $ids = implode(',', array_map('intval', $this->createdRecords['assets']));
                $this->db->query("DELETE FROM `assets` WHERE id IN ($ids)");
            }
            
            if (!empty($this->createdRecords['stock'])) {
                $ids = implode(',', array_map('intval', $this->createdRecords['stock']));
                $this->db->query("DELETE FROM `stock` WHERE id IN ($ids)");
            }
            
            if (!empty($this->createdRecords['products'])) {
                $ids = implode(',', array_map('intval', $this->createdRecords['products']));
                $this->db->query("DELETE FROM `products` WHERE id IN ($ids)");
            }
            
            if (!empty($this->createdRecords['categories'])) {
                $ids = implode(',', array_map('intval', $this->createdRecords['categories']));
                $this->db->query("DELETE FROM `product_categories` WHERE id IN ($ids)");
            }
            
            if (!empty($this->createdRecords['warehouses'])) {
                $ids = implode(',', array_map('intval', $this->createdRecords['warehouses']));
                $this->db->query("DELETE FROM `warehouses` WHERE id IN ($ids)");
            }
            
            if (!empty($this->createdRecords['users'])) {
                $ids = implode(',', array_map('intval', $this->createdRecords['users']));
                $this->db->query("DELETE FROM `users` WHERE id IN ($ids)");
            }
            
            if (!empty($this->createdRecords['roles'])) {
                $ids = implode(',', array_map('intval', $this->createdRecords['roles']));
                $this->db->query("DELETE FROM `roles` WHERE id IN ($ids)");
            }
            
            if (!empty($this->createdRecords['companies'])) {
                $ids = implode(',', array_map('intval', $this->createdRecords['companies']));
                $this->db->query("DELETE FROM `companies` WHERE id IN ($ids)");
            }
            
            $this->createdRecords = [];
            
        } catch (Exception $e) {
            echo "Cleanup warning: " . $e->getMessage() . "\n";
        }
    }
}
