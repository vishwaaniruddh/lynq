<?php
/**
 * Property Test: Low Stock Alert Generation
 * 
 * **Feature: adv-crm-inventory-module, Property 12: Low Stock Alert Generation**
 * **Validates: Requirements 13.1**
 * 
 * Property: For any product whose stock falls below its configured threshold,
 * a low stock alert SHALL be generated.
 */

require_once 'PropertyTestBase.php';
require_once __DIR__ . '/../services/InventoryAlertService.php';
require_once __DIR__ . '/../services/StockService.php';
require_once __DIR__ . '/../repositories/ProductRepository.php';
require_once __DIR__ . '/../repositories/WarehouseRepository.php';
require_once __DIR__ . '/../repositories/StockRepository.php';
require_once __DIR__ . '/../repositories/StockAlertRepository.php';

class LowStockAlertGenerationTest extends PropertyTestBase {
    
    private $alertService;
    private $stockService;
    private $productRepository;
    private $warehouseRepository;
    private $stockRepository;
    private $alertRepository;
    private $createdRecords = [];
    
    public function __construct() {
        parent::__construct();
        $this->alertService = new InventoryAlertService();
        $this->stockService = new StockService();
        $this->productRepository = new ProductRepository();
        $this->warehouseRepository = new WarehouseRepository();
        $this->warehouseRepository->disableCompanyFilter();
        $this->stockRepository = new StockRepository();
        $this->alertRepository = new StockAlertRepository();
    }
    
    public function runTests() {
        echo "=== Low Stock Alert Generation Property Test ===\n";
        echo "**Feature: adv-crm-inventory-module, Property 12: Low Stock Alert Generation**\n";
        echo "**Validates: Requirements 13.1**\n\n";
        
        // Check if required tables exist
        if (!$this->tablesExist()) {
            echo "SKIPPED: Required tables not found. Run migrations first.\n";
            return true;
        }
        
        $allPassed = true;
        
        // Run property test for low stock alert generation
        $allPassed &= $this->runPropertyTest(
            'Property 12: Low stock alert is generated when stock falls below threshold',
            function() {
                return $this->testLowStockAlertGeneration();
            },
            100
        );
        
        // Cleanup
        $this->cleanupTestData();
        
        return $allPassed;
    }
    
    /**
     * Test low stock alert generation
     * 
     * Property: For any product with threshold T and current stock S where S < T,
     * checkLowStock() should generate an active alert.
     * For any product with threshold T and current stock S where S >= T,
     * checkLowStock() should NOT generate an alert (or clear existing one).
     */
    private function testLowStockAlertGeneration() {
        // Generate random test data
        $threshold = $this->generateRandomInt(10, 100);
        $stockQuantity = $this->generateRandomInt(0, 150);
        
        // Create test product (non-serializable for simpler testing)
        $product = $this->createTestProduct($threshold);
        if (!$product) {
            return ['success' => false, 'message' => 'Failed to create test product'];
        }
        
        // Create test warehouse
        $warehouse = $this->createTestWarehouse();
        if (!$warehouse) {
            return ['success' => false, 'message' => 'Failed to create test warehouse'];
        }
        
        // Add stock to warehouse (if quantity > 0)
        if ($stockQuantity > 0) {
            $addResult = $this->stockService->addStock($product['id'], $warehouse['id'], $stockQuantity);
            if (!$addResult['success']) {
                return ['success' => false, 'message' => 'Failed to add stock: ' . $addResult['message']];
            }
        }
        
        // Check low stock
        $checkResult = $this->alertService->checkLowStock($product['id'], $warehouse['id']);
        
        if (!$checkResult['success']) {
            return [
                'success' => false,
                'message' => 'checkLowStock failed: ' . ($checkResult['message'] ?? 'Unknown error'),
                'data' => $checkResult
            ];
        }
        
        // Determine expected behavior
        $expectedLowStock = $stockQuantity < $threshold;
        
        // Verify the property
        if ($checkResult['is_low_stock'] !== $expectedLowStock) {
            return [
                'success' => false,
                'message' => "Property violated: stock=$stockQuantity, threshold=$threshold, " .
                            "expected is_low_stock=" . ($expectedLowStock ? 'true' : 'false') . 
                            ", got is_low_stock=" . ($checkResult['is_low_stock'] ? 'true' : 'false'),
                'data' => [
                    'stock_quantity' => $stockQuantity,
                    'threshold' => $threshold,
                    'expected_low_stock' => $expectedLowStock,
                    'actual_low_stock' => $checkResult['is_low_stock']
                ]
            ];
        }
        
        // If low stock, verify alert was generated
        if ($expectedLowStock) {
            $activeAlert = $this->alertRepository->findActiveAlert(
                $product['id'], 
                $warehouse['id'], 
                InventoryAlertService::TYPE_LOW_STOCK
            );
            
            if (!$activeAlert) {
                return [
                    'success' => false,
                    'message' => 'Property violated: Low stock condition exists but no alert was generated',
                    'data' => [
                        'stock_quantity' => $stockQuantity,
                        'threshold' => $threshold
                    ]
                ];
            }
            
            // Track for cleanup
            $this->createdRecords['alerts'][] = $activeAlert['id'];
            
            // Verify alert values
            if ($activeAlert['current_value'] != $stockQuantity) {
                return [
                    'success' => false,
                    'message' => 'Alert current_value mismatch',
                    'data' => [
                        'expected' => $stockQuantity,
                        'actual' => $activeAlert['current_value']
                    ]
                ];
            }
            
            if ($activeAlert['threshold_value'] != $threshold) {
                return [
                    'success' => false,
                    'message' => 'Alert threshold_value mismatch',
                    'data' => [
                        'expected' => $threshold,
                        'actual' => $activeAlert['threshold_value']
                    ]
                ];
            }
        } else {
            // If not low stock, verify no active alert exists
            $activeAlert = $this->alertRepository->findActiveAlert(
                $product['id'], 
                $warehouse['id'], 
                InventoryAlertService::TYPE_LOW_STOCK
            );
            
            if ($activeAlert) {
                return [
                    'success' => false,
                    'message' => 'Property violated: Stock is above threshold but alert still exists',
                    'data' => [
                        'stock_quantity' => $stockQuantity,
                        'threshold' => $threshold,
                        'alert_id' => $activeAlert['id']
                    ]
                ];
            }
        }
        
        return ['success' => true];
    }

    
    /**
     * Check if required tables exist
     */
    private function tablesExist() {
        $requiredTables = ['products', 'warehouses', 'stock', 'stock_alerts', 'companies'];
        
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
    private function createTestProduct(int $threshold) {
        try {
            $productData = [
                'name' => 'Test Product ' . $this->generateRandomString(8),
                'unit_of_measure' => 'unit',
                'inventory_type' => 'INTERNAL',
                'is_serializable' => 0,
                'is_repairable' => 0,
                'low_stock_threshold' => $threshold,
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
    private function createTestWarehouse() {
        try {
            // Get or create a test company
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
     * Get a test company ID
     */
    private function getTestCompanyId() {
        $sql = "SELECT id FROM companies WHERE status = 'ACTIVE' LIMIT 1";
        $result = $this->getResults($sql);
        
        if (empty($result)) {
            // Create a test company if none exists
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
            // Delete test alerts
            if (!empty($this->createdRecords['alerts'])) {
                $ids = implode(',', array_map('intval', $this->createdRecords['alerts']));
                $this->db->query("DELETE FROM `stock_alerts` WHERE id IN ($ids)");
            }
            
            // Delete alerts for test products
            if (!empty($this->createdRecords['products'])) {
                $productIds = implode(',', array_map('intval', $this->createdRecords['products']));
                $this->db->query("DELETE FROM `stock_alerts` WHERE product_id IN ($productIds)");
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
