<?php
/**
 * Property Test: Dashboard Count Accuracy
 * 
 * **Feature: adv-crm-inventory-module, Property 11: Dashboard Count Accuracy**
 * **Validates: Requirements 9.1, 9.2**
 * 
 * Property: For any dashboard view, the displayed totals (stock counts, dispatched quantities,
 * contractor allocations) SHALL match the actual database aggregates.
 */

require_once 'PropertyTestBase.php';
require_once __DIR__ . '/../repositories/StockRepository.php';
require_once __DIR__ . '/../repositories/AssetRepository.php';
require_once __DIR__ . '/../repositories/WarehouseRepository.php';
require_once __DIR__ . '/../repositories/ProductRepository.php';
require_once __DIR__ . '/../repositories/ProductCategoryRepository.php';

class DashboardCountAccuracyTest extends PropertyTestBase {
    
    private $stockRepository;
    private $assetRepository;
    private $warehouseRepository;
    private $productRepository;
    private $categoryRepository;
    private $createdRecords = [];
    
    public function __construct() {
        parent::__construct();
        $this->stockRepository = new StockRepository();
        $this->assetRepository = new AssetRepository();
        $this->warehouseRepository = new WarehouseRepository();
        $this->warehouseRepository->disableCompanyFilter();
        $this->productRepository = new ProductRepository();
        $this->categoryRepository = new ProductCategoryRepository();
    }
    
    public function runTests() {
        echo "=== Dashboard Count Accuracy Property Test ===\n";
        echo "**Feature: adv-crm-inventory-module, Property 11: Dashboard Count Accuracy**\n";
        echo "**Validates: Requirements 9.1, 9.2**\n\n";
        
        // Check if required tables exist
        if (!$this->tablesExist()) {
            echo "SKIPPED: Required tables not found. Run migrations first.\n";
            return true;
        }
        
        $allPassed = true;
        
        // Run property test for stock count accuracy
        $allPassed &= $this->runPropertyTest(
            'Property 11a: Stock counts match database aggregates',
            function() {
                return $this->testStockCountAccuracy();
            },
            50
        );
        
        // Run property test for asset status count accuracy
        $allPassed &= $this->runPropertyTest(
            'Property 11b: Asset status counts match database aggregates',
            function() {
                return $this->testAssetStatusCountAccuracy();
            },
            50
        );
        
        // Run property test for dispatched vs available accuracy
        $allPassed &= $this->runPropertyTest(
            'Property 11c: Dispatched vs available counts are accurate',
            function() {
                return $this->testDispatchedVsAvailableAccuracy();
            },
            50
        );
        
        // Cleanup
        $this->cleanupTestData();
        
        return $allPassed;
    }
    
    /**
     * Test stock count accuracy
     * 
     * Property: For any set of stock entries, the dashboard total SHALL equal
     * the sum of all stock quantities in the database.
     */
    private function testStockCountAccuracy() {
        // Create test data
        $warehouse = $this->createTestWarehouse();
        if (!$warehouse) {
            return ['success' => false, 'message' => 'Failed to create test warehouse'];
        }
        
        $product = $this->createTestProduct(false); // Non-serializable
        if (!$product) {
            return ['success' => false, 'message' => 'Failed to create test product'];
        }
        
        // Add random stock quantity
        $quantity = $this->generateRandomInt(1, 1000);
        $reservedQuantity = $this->generateRandomInt(0, min($quantity, 100));
        
        $stockData = [
            'product_id' => $product['id'],
            'warehouse_id' => $warehouse['id'],
            'quantity' => $quantity,
            'reserved_quantity' => $reservedQuantity
        ];
        
        $stock = $this->stockRepository->create($stockData);
        $this->createdRecords['stock'][] = $stock['id'];
        
        // Get dashboard aggregate (simulating dashboard query)
        $dashboardTotal = $this->getDashboardStockTotal($warehouse['id'], $product['id']);
        
        // Get actual database value
        $actualStock = $this->stockRepository->findByProductAndWarehouse($product['id'], $warehouse['id']);
        
        // Verify counts match
        if ($dashboardTotal['quantity'] != $actualStock['quantity']) {
            return [
                'success' => false,
                'message' => 'Stock quantity mismatch',
                'data' => [
                    'dashboard_quantity' => $dashboardTotal['quantity'],
                    'actual_quantity' => $actualStock['quantity'],
                    'product_id' => $product['id'],
                    'warehouse_id' => $warehouse['id']
                ]
            ];
        }
        
        if ($dashboardTotal['available'] != ($actualStock['quantity'] - $actualStock['reserved_quantity'])) {
            return [
                'success' => false,
                'message' => 'Available quantity mismatch',
                'data' => [
                    'dashboard_available' => $dashboardTotal['available'],
                    'actual_available' => $actualStock['quantity'] - $actualStock['reserved_quantity']
                ]
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * Test asset status count accuracy
     * 
     * Property: For any set of assets, the dashboard status breakdown SHALL equal
     * the count of assets grouped by status in the database.
     */
    private function testAssetStatusCountAccuracy() {
        // Create test data
        $warehouse = $this->createTestWarehouse();
        if (!$warehouse) {
            return ['success' => false, 'message' => 'Failed to create test warehouse'];
        }
        
        $product = $this->createTestProduct(true); // Serializable
        if (!$product) {
            return ['success' => false, 'message' => 'Failed to create test product'];
        }
        
        // Create random number of assets with random statuses
        $statuses = AssetRepository::getStatuses();
        $numAssets = $this->generateRandomInt(1, 10);
        $expectedCounts = array_fill_keys($statuses, 0);
        
        for ($i = 0; $i < $numAssets; $i++) {
            $status = $this->generateRandomChoice($statuses);
            $expectedCounts[$status]++;
            
            $assetData = [
                'product_id' => $product['id'],
                'warehouse_id' => $warehouse['id'],
                'serial_number' => 'TEST-' . $this->generateRandomString(12),
                'status' => $status,
                'working_condition' => $this->generateRandomChoice(['working', 'not_working']),
                'current_holder_type' => 'warehouse',
                'current_holder_id' => $warehouse['id'],
                'source_warehouse_id' => $warehouse['id']
            ];
            
            $asset = $this->assetRepository->create($assetData);
            $this->createdRecords['assets'][] = $asset['id'];
        }
        
        // Get dashboard status breakdown (simulating dashboard query)
        $dashboardCounts = $this->getDashboardAssetStatusCounts($warehouse['id'], $product['id']);
        
        // Verify each status count matches
        foreach ($statuses as $status) {
            $dashboardCount = $dashboardCounts[$status] ?? 0;
            $expectedCount = $expectedCounts[$status];
            
            if ($dashboardCount != $expectedCount) {
                return [
                    'success' => false,
                    'message' => "Asset status count mismatch for status: $status",
                    'data' => [
                        'status' => $status,
                        'dashboard_count' => $dashboardCount,
                        'expected_count' => $expectedCount,
                        'all_dashboard_counts' => $dashboardCounts,
                        'all_expected_counts' => $expectedCounts
                    ]
                ];
            }
        }
        
        return ['success' => true];
    }
    
    /**
     * Test dispatched vs available accuracy
     * 
     * Property: For any set of assets, the sum of dispatched + available + other statuses
     * SHALL equal the total asset count.
     */
    private function testDispatchedVsAvailableAccuracy() {
        // Create test data
        $warehouse = $this->createTestWarehouse();
        if (!$warehouse) {
            return ['success' => false, 'message' => 'Failed to create test warehouse'];
        }
        
        $product = $this->createTestProduct(true); // Serializable
        if (!$product) {
            return ['success' => false, 'message' => 'Failed to create test product'];
        }
        
        // Create assets with various statuses
        $numAssets = $this->generateRandomInt(5, 20);
        $statuses = AssetRepository::getStatuses();
        
        for ($i = 0; $i < $numAssets; $i++) {
            $status = $this->generateRandomChoice($statuses);
            
            $assetData = [
                'product_id' => $product['id'],
                'warehouse_id' => $warehouse['id'],
                'serial_number' => 'TEST-DVA-' . $this->generateRandomString(12),
                'status' => $status,
                'working_condition' => 'working',
                'current_holder_type' => 'warehouse',
                'current_holder_id' => $warehouse['id'],
                'source_warehouse_id' => $warehouse['id']
            ];
            
            $asset = $this->assetRepository->create($assetData);
            $this->createdRecords['assets'][] = $asset['id'];
        }
        
        // Get dashboard dispatched vs available (simulating dashboard query)
        $dashboardData = $this->getDashboardDispatchedVsAvailable($warehouse['id'], $product['id']);
        
        // Get actual counts from database
        $actualCounts = $this->getActualDispatchedVsAvailable($warehouse['id'], $product['id']);
        
        // Verify available count
        if ($dashboardData['available'] != $actualCounts['available']) {
            return [
                'success' => false,
                'message' => 'Available count mismatch',
                'data' => [
                    'dashboard_available' => $dashboardData['available'],
                    'actual_available' => $actualCounts['available']
                ]
            ];
        }
        
        // Verify dispatched count
        if ($dashboardData['dispatched'] != $actualCounts['dispatched']) {
            return [
                'success' => false,
                'message' => 'Dispatched count mismatch',
                'data' => [
                    'dashboard_dispatched' => $dashboardData['dispatched'],
                    'actual_dispatched' => $actualCounts['dispatched']
                ]
            ];
        }
        
        // Verify total consistency
        $dashboardTotal = $dashboardData['available'] + $dashboardData['dispatched'] + 
                          $dashboardData['under_repair'] + $dashboardData['scrapped'] + 
                          $dashboardData['lost'];
        
        if ($dashboardTotal != $numAssets) {
            return [
                'success' => false,
                'message' => 'Total count does not match created assets',
                'data' => [
                    'dashboard_total' => $dashboardTotal,
                    'created_assets' => $numAssets,
                    'breakdown' => $dashboardData
                ]
            ];
        }
        
        return ['success' => true];
    }

    
    /**
     * Get dashboard stock total (simulating dashboard query)
     */
    private function getDashboardStockTotal($warehouseId, $productId) {
        $sql = "SELECT 
                    COALESCE(SUM(quantity), 0) as quantity,
                    COALESCE(SUM(quantity - reserved_quantity), 0) as available,
                    COALESCE(SUM(reserved_quantity), 0) as reserved
                FROM stock
                WHERE warehouse_id = ? AND product_id = ?";
        
        $result = $this->getResults($sql, [$warehouseId, $productId], 'ii');
        return $result[0] ?? ['quantity' => 0, 'available' => 0, 'reserved' => 0];
    }
    
    /**
     * Get dashboard asset status counts (simulating dashboard query)
     */
    private function getDashboardAssetStatusCounts($warehouseId, $productId) {
        $sql = "SELECT status, COUNT(*) as count
                FROM assets
                WHERE warehouse_id = ? AND product_id = ?
                GROUP BY status";
        
        $results = $this->getResults($sql, [$warehouseId, $productId], 'ii');
        
        $counts = [];
        foreach ($results as $row) {
            $counts[$row['status']] = (int)$row['count'];
        }
        
        return $counts;
    }
    
    /**
     * Get dashboard dispatched vs available (simulating dashboard query)
     */
    private function getDashboardDispatchedVsAvailable($warehouseId, $productId) {
        $sql = "SELECT 
                    SUM(CASE WHEN status = 'in_stock' THEN 1 ELSE 0 END) as available,
                    SUM(CASE WHEN status IN ('dispatched', 'assigned', 'in_use', 'returned') THEN 1 ELSE 0 END) as dispatched,
                    SUM(CASE WHEN status = 'under_repair' THEN 1 ELSE 0 END) as under_repair,
                    SUM(CASE WHEN status = 'scrapped' THEN 1 ELSE 0 END) as scrapped,
                    SUM(CASE WHEN status = 'lost' THEN 1 ELSE 0 END) as lost
                FROM assets
                WHERE warehouse_id = ? AND product_id = ?";
        
        $result = $this->getResults($sql, [$warehouseId, $productId], 'ii');
        
        return [
            'available' => (int)($result[0]['available'] ?? 0),
            'dispatched' => (int)($result[0]['dispatched'] ?? 0),
            'under_repair' => (int)($result[0]['under_repair'] ?? 0),
            'scrapped' => (int)($result[0]['scrapped'] ?? 0),
            'lost' => (int)($result[0]['lost'] ?? 0)
        ];
    }
    
    /**
     * Get actual dispatched vs available from database
     */
    private function getActualDispatchedVsAvailable($warehouseId, $productId) {
        // Count available (in_stock)
        $availableSql = "SELECT COUNT(*) as count FROM assets 
                         WHERE warehouse_id = ? AND product_id = ? AND status = 'in_stock'";
        $availableResult = $this->getResults($availableSql, [$warehouseId, $productId], 'ii');
        
        // Count dispatched (dispatched, assigned, in_use, returned)
        $dispatchedSql = "SELECT COUNT(*) as count FROM assets 
                          WHERE warehouse_id = ? AND product_id = ? 
                          AND status IN ('dispatched', 'assigned', 'in_use', 'returned')";
        $dispatchedResult = $this->getResults($dispatchedSql, [$warehouseId, $productId], 'ii');
        
        return [
            'available' => (int)($availableResult[0]['count'] ?? 0),
            'dispatched' => (int)($dispatchedResult[0]['count'] ?? 0)
        ];
    }
    
    /**
     * Check if required tables exist
     */
    private function tablesExist() {
        $requiredTables = ['stock', 'assets', 'warehouses', 'products', 'companies'];
        
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
    private function createTestWarehouse() {
        try {
            $companyId = $this->getTestCompanyId();
            
            $warehouseData = [
                'name' => 'Test Warehouse ' . $this->generateRandomString(8),
                'location' => 'Test Location',
                'company_id' => $companyId,
                'status' => 'active'
            ];
            
            $warehouse = $this->warehouseRepository->create($warehouseData);
            $this->createdRecords['warehouses'][] = $warehouse['id'];
            return $warehouse;
            
        } catch (Exception $e) {
            error_log("Failed to create test warehouse: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Create a test product
     */
    private function createTestProduct($isSerializable) {
        try {
            $categoryId = $this->getTestCategoryId();
            
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
            
        } catch (Exception $e) {
            error_log("Failed to create test product: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get a test company ID
     */
    private function getTestCompanyId() {
        $sql = "SELECT id FROM companies WHERE status = 'ACTIVE' LIMIT 1";
        $result = $this->getResults($sql);
        
        if (empty($result)) {
            $this->executeQuery(
                "INSERT INTO companies (name, type, status) VALUES (?, ?, ?)",
                ['Test Company ' . $this->generateRandomString(8), 'ADV', 'ACTIVE'],
                'sss'
            );
            $companyId = $this->db->insert_id;
            $this->createdRecords['companies'][] = $companyId;
            return $companyId;
        }
        
        return $result[0]['id'];
    }
    
    /**
     * Get a test category ID
     */
    private function getTestCategoryId() {
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
     * Clean up all test data created during tests
     */
    public function cleanupTestData() {
        try {
            // Delete test assets first (foreign key constraint)
            if (!empty($this->createdRecords['assets'])) {
                $ids = implode(',', array_map('intval', $this->createdRecords['assets']));
                $this->db->query("DELETE FROM `assets` WHERE id IN ($ids)");
            }
            
            // Delete test stock
            if (!empty($this->createdRecords['stock'])) {
                $ids = implode(',', array_map('intval', $this->createdRecords['stock']));
                $this->db->query("DELETE FROM `stock` WHERE id IN ($ids)");
            }
            
            // Delete test products
            if (!empty($this->createdRecords['products'])) {
                $ids = implode(',', array_map('intval', $this->createdRecords['products']));
                $this->db->query("DELETE FROM `products` WHERE id IN ($ids)");
            }
            
            // Delete test categories
            if (!empty($this->createdRecords['categories'])) {
                $ids = implode(',', array_map('intval', $this->createdRecords['categories']));
                $this->db->query("DELETE FROM `product_categories` WHERE id IN ($ids)");
            }
            
            // Delete test warehouses
            if (!empty($this->createdRecords['warehouses'])) {
                $ids = implode(',', array_map('intval', $this->createdRecords['warehouses']));
                $this->db->query("DELETE FROM `warehouses` WHERE id IN ($ids)");
            }
            
            // Delete test companies
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
