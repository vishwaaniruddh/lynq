<?php
/**
 * Property Test: Stock Conservation During Transfer
 * 
 * **Feature: adv-crm-inventory-module, Property 3: Stock Conservation During Transfer**
 * **Validates: Requirements 5.4**
 * 
 * Property: For any inter-warehouse transfer, the sum of stock quantities across 
 * source and destination warehouses SHALL remain constant before and after the transfer.
 */

require_once 'PropertyTestBase.php';
require_once __DIR__ . '/../services/TransferService.php';
require_once __DIR__ . '/../services/StockService.php';
require_once __DIR__ . '/../repositories/ProductRepository.php';
require_once __DIR__ . '/../repositories/WarehouseRepository.php';
require_once __DIR__ . '/../repositories/StockRepository.php';
require_once __DIR__ . '/../repositories/AssetRepository.php';

class StockConservationTransferTest extends PropertyTestBase {
    
    private $transferService;
    private $stockService;
    private $productRepository;
    private $warehouseRepository;
    private $stockRepository;
    private $assetRepository;
    private $createdRecords = [];
    
    public function __construct() {
        parent::__construct();
        $this->transferService = new TransferService();
        $this->stockService = new StockService();
        $this->productRepository = new ProductRepository();
        $this->warehouseRepository = new WarehouseRepository();
        $this->warehouseRepository->disableCompanyFilter();
        $this->stockRepository = new StockRepository();
        $this->assetRepository = new AssetRepository();
    }
    
    public function runTests() {
        echo "=== Stock Conservation During Transfer Property Test ===\n";
        echo "**Feature: adv-crm-inventory-module, Property 3: Stock Conservation During Transfer**\n";
        echo "**Validates: Requirements 5.4**\n\n";
        
        // Check if required tables exist
        if (!$this->tablesExist()) {
            echo "SKIPPED: Required tables not found. Run migrations first.\n";
            return true;
        }
        
        $allPassed = true;
        
        // Run property test for non-serializable items
        $allPassed &= $this->runPropertyTest(
            'Property 3: Stock conservation during transfer (non-serializable)',
            function() {
                return $this->testStockConservationNonSerializable();
            },
            100
        );
        
        // Run property test for serializable items
        $allPassed &= $this->runPropertyTest(
            'Property 3: Stock conservation during transfer (serializable)',
            function() {
                return $this->testStockConservationSerializable();
            },
            100
        );
        
        // Cleanup
        $this->cleanupTestData();
        
        return $allPassed;
    }
    
    /**
     * Test stock conservation for non-serializable items
     * 
     * Property: For any non-serializable product transfer from warehouse A to warehouse B,
     * the total stock (A + B) before transfer should equal total stock (A + B) after transfer.
     */
    private function testStockConservationNonSerializable() {
        // Generate random test data
        $initialStockA = $this->generateRandomInt(50, 200);
        $initialStockB = $this->generateRandomInt(0, 100);
        $transferQuantity = $this->generateRandomInt(1, min($initialStockA, 50));
        
        // Create test product (non-serializable)
        $product = $this->createTestProduct(false);
        if (!$product) {
            return ['success' => false, 'message' => 'Failed to create test product'];
        }
        
        // Create two test warehouses
        $warehouseA = $this->createTestWarehouse('A');
        $warehouseB = $this->createTestWarehouse('B');
        if (!$warehouseA || !$warehouseB) {
            return ['success' => false, 'message' => 'Failed to create test warehouses'];
        }
        
        // Add initial stock to both warehouses
        $addResultA = $this->stockService->addStock($product['id'], $warehouseA['id'], $initialStockA);
        if (!$addResultA['success']) {
            return ['success' => false, 'message' => 'Failed to add stock to warehouse A: ' . $addResultA['message']];
        }
        
        if ($initialStockB > 0) {
            $addResultB = $this->stockService->addStock($product['id'], $warehouseB['id'], $initialStockB);
            if (!$addResultB['success']) {
                return ['success' => false, 'message' => 'Failed to add stock to warehouse B: ' . $addResultB['message']];
            }
        }
        
        // Calculate total stock before transfer
        $stockBeforeA = $this->stockService->getAvailableStock($product['id'], $warehouseA['id']);
        $stockBeforeB = $this->stockService->getAvailableStock($product['id'], $warehouseB['id']);
        $totalBefore = $stockBeforeA + $stockBeforeB;
        
        // Create and process transfer
        $transferResult = $this->transferService->createTransfer(
            [
                'from_warehouse_id' => $warehouseA['id'],
                'to_warehouse_id' => $warehouseB['id'],
                'transfer_date' => date('Y-m-d')
            ],
            [
                ['product_id' => $product['id'], 'quantity' => $transferQuantity]
            ]
        );
        
        if (!$transferResult['success']) {
            return ['success' => false, 'message' => 'Failed to create transfer: ' . $transferResult['message']];
        }
        
        $this->createdRecords['transfers'][] = $transferResult['data']['transfer']['id'];
        
        // Process the transfer
        $processResult = $this->transferService->processTransfer($transferResult['data']['transfer']['id']);
        if (!$processResult['success']) {
            return ['success' => false, 'message' => 'Failed to process transfer: ' . $processResult['message']];
        }
        
        // Calculate total stock after transfer
        $stockAfterA = $this->stockService->getAvailableStock($product['id'], $warehouseA['id']);
        $stockAfterB = $this->stockService->getAvailableStock($product['id'], $warehouseB['id']);
        $totalAfter = $stockAfterA + $stockAfterB;
        
        // Property check: total stock should be conserved
        if ($totalBefore !== $totalAfter) {
            return [
                'success' => false,
                'message' => "Stock conservation violated: total before=$totalBefore, total after=$totalAfter",
                'data' => [
                    'initial_stock_a' => $initialStockA,
                    'initial_stock_b' => $initialStockB,
                    'transfer_quantity' => $transferQuantity,
                    'stock_before_a' => $stockBeforeA,
                    'stock_before_b' => $stockBeforeB,
                    'stock_after_a' => $stockAfterA,
                    'stock_after_b' => $stockAfterB,
                    'total_before' => $totalBefore,
                    'total_after' => $totalAfter
                ]
            ];
        }
        
        // Additional check: verify individual warehouse changes
        $expectedAfterA = $stockBeforeA - $transferQuantity;
        $expectedAfterB = $stockBeforeB + $transferQuantity;
        
        if ($stockAfterA !== $expectedAfterA || $stockAfterB !== $expectedAfterB) {
            return [
                'success' => false,
                'message' => "Individual warehouse stock mismatch",
                'data' => [
                    'expected_after_a' => $expectedAfterA,
                    'actual_after_a' => $stockAfterA,
                    'expected_after_b' => $expectedAfterB,
                    'actual_after_b' => $stockAfterB
                ]
            ];
        }
        
        return ['success' => true];
    }

    
    /**
     * Test stock conservation for serializable items (assets)
     * 
     * Property: For any serializable product transfer from warehouse A to warehouse B,
     * the total asset count (A + B) before transfer should equal total asset count (A + B) after transfer.
     */
    private function testStockConservationSerializable() {
        // Generate random test data
        $assetCountA = $this->generateRandomInt(3, 10);
        $assetCountB = $this->generateRandomInt(0, 5);
        $transferCount = $this->generateRandomInt(1, min($assetCountA, 3));
        
        // Create test product (serializable)
        $product = $this->createTestProduct(true);
        if (!$product) {
            return ['success' => false, 'message' => 'Failed to create test product'];
        }
        
        // Create two test warehouses
        $warehouseA = $this->createTestWarehouse('A');
        $warehouseB = $this->createTestWarehouse('B');
        if (!$warehouseA || !$warehouseB) {
            return ['success' => false, 'message' => 'Failed to create test warehouses'];
        }
        
        // Add assets to warehouse A
        $assetsA = [];
        for ($i = 0; $i < $assetCountA; $i++) {
            $serialNumber = 'SN-A-' . $this->generateRandomString(12) . '-' . $i;
            $addResult = $this->stockService->addAsset($product['id'], $warehouseA['id'], $serialNumber);
            if (!$addResult['success']) {
                return ['success' => false, 'message' => 'Failed to add asset to warehouse A: ' . $addResult['message']];
            }
            $assetsA[] = $addResult['data'];
            $this->createdRecords['assets'][] = $addResult['data']['id'];
        }
        
        // Add assets to warehouse B
        for ($i = 0; $i < $assetCountB; $i++) {
            $serialNumber = 'SN-B-' . $this->generateRandomString(12) . '-' . $i;
            $addResult = $this->stockService->addAsset($product['id'], $warehouseB['id'], $serialNumber);
            if (!$addResult['success']) {
                return ['success' => false, 'message' => 'Failed to add asset to warehouse B: ' . $addResult['message']];
            }
            $this->createdRecords['assets'][] = $addResult['data']['id'];
        }
        
        // Calculate total assets before transfer
        $countBeforeA = $this->stockService->getAvailableStock($product['id'], $warehouseA['id']);
        $countBeforeB = $this->stockService->getAvailableStock($product['id'], $warehouseB['id']);
        $totalBefore = $countBeforeA + $countBeforeB;
        
        // Select assets to transfer
        $assetsToTransfer = array_slice($assetsA, 0, $transferCount);
        $transferItems = [];
        foreach ($assetsToTransfer as $asset) {
            $transferItems[] = [
                'product_id' => $product['id'],
                'asset_id' => $asset['id']
            ];
        }
        
        // Create and process transfer
        $transferResult = $this->transferService->createTransfer(
            [
                'from_warehouse_id' => $warehouseA['id'],
                'to_warehouse_id' => $warehouseB['id'],
                'transfer_date' => date('Y-m-d')
            ],
            $transferItems
        );
        
        if (!$transferResult['success']) {
            return ['success' => false, 'message' => 'Failed to create transfer: ' . $transferResult['message']];
        }
        
        $this->createdRecords['transfers'][] = $transferResult['data']['transfer']['id'];
        
        // Process the transfer
        $processResult = $this->transferService->processTransfer($transferResult['data']['transfer']['id']);
        if (!$processResult['success']) {
            return ['success' => false, 'message' => 'Failed to process transfer: ' . $processResult['message']];
        }
        
        // Calculate total assets after transfer
        $countAfterA = $this->stockService->getAvailableStock($product['id'], $warehouseA['id']);
        $countAfterB = $this->stockService->getAvailableStock($product['id'], $warehouseB['id']);
        $totalAfter = $countAfterA + $countAfterB;
        
        // Property check: total asset count should be conserved
        if ($totalBefore !== $totalAfter) {
            return [
                'success' => false,
                'message' => "Asset conservation violated: total before=$totalBefore, total after=$totalAfter",
                'data' => [
                    'asset_count_a' => $assetCountA,
                    'asset_count_b' => $assetCountB,
                    'transfer_count' => $transferCount,
                    'count_before_a' => $countBeforeA,
                    'count_before_b' => $countBeforeB,
                    'count_after_a' => $countAfterA,
                    'count_after_b' => $countAfterB,
                    'total_before' => $totalBefore,
                    'total_after' => $totalAfter
                ]
            ];
        }
        
        // Additional check: verify individual warehouse changes
        $expectedAfterA = $countBeforeA - $transferCount;
        $expectedAfterB = $countBeforeB + $transferCount;
        
        if ($countAfterA !== $expectedAfterA || $countAfterB !== $expectedAfterB) {
            return [
                'success' => false,
                'message' => "Individual warehouse asset count mismatch",
                'data' => [
                    'expected_after_a' => $expectedAfterA,
                    'actual_after_a' => $countAfterA,
                    'expected_after_b' => $expectedAfterB,
                    'actual_after_b' => $countAfterB
                ]
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * Check if required tables exist
     */
    private function tablesExist() {
        $requiredTables = ['products', 'warehouses', 'stock', 'assets', 'companies', 'transfers', 'transfer_items'];
        
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
     * Create a test product
     */
    private function createTestProduct(bool $isSerializable) {
        try {
            $productData = [
                'name' => 'Test Product ' . $this->generateRandomString(8),
                'unit_of_measure' => 'unit',
                'inventory_type' => 'INTERNAL',
                'is_serializable' => $isSerializable ? 1 : 0,
                'is_repairable' => 0,
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
     * Create a test warehouse
     */
    private function createTestWarehouse(string $suffix = '') {
        try {
            $companyId = $this->getTestCompanyId();
            
            $warehouseData = [
                'name' => 'Test Warehouse ' . $suffix . ' ' . $this->generateRandomString(8),
                'location' => 'Test Location ' . $suffix,
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
     * Clean up all test data created during tests
     */
    public function cleanupTestData() {
        try {
            // Delete transfer items first (foreign key constraint)
            if (!empty($this->createdRecords['transfers'])) {
                $ids = implode(',', array_map('intval', $this->createdRecords['transfers']));
                $this->db->query("DELETE FROM `transfer_items` WHERE transfer_id IN ($ids)");
                $this->db->query("DELETE FROM `transfers` WHERE id IN ($ids)");
            }
            
            // Delete test assets
            if (!empty($this->createdRecords['assets'])) {
                $ids = implode(',', array_map('intval', $this->createdRecords['assets']));
                $this->db->query("DELETE FROM `assets` WHERE id IN ($ids)");
            }
            
            // Delete test stock
            if (!empty($this->createdRecords['products'])) {
                $productIds = implode(',', array_map('intval', $this->createdRecords['products']));
                $this->db->query("DELETE FROM `stock` WHERE product_id IN ($productIds)");
            }
            
            // Delete test products
            if (!empty($this->createdRecords['products'])) {
                $ids = implode(',', array_map('intval', $this->createdRecords['products']));
                $this->db->query("DELETE FROM `products` WHERE id IN ($ids)");
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
