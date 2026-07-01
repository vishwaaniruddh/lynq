<?php
/**
 * Property Test: Alert Auto-Clear on Replenishment
 * 
 * **Feature: adv-crm-inventory-module, Property 13: Alert Auto-Clear on Replenishment**
 * **Validates: Requirements 13.4**
 * 
 * Property: For any active low stock alert, when stock is replenished above the threshold,
 * the alert SHALL be automatically cleared.
 */

require_once 'PropertyTestBase.php';
require_once __DIR__ . '/../services/InventoryAlertService.php';
require_once __DIR__ . '/../services/StockService.php';
require_once __DIR__ . '/../repositories/ProductRepository.php';
require_once __DIR__ . '/../repositories/WarehouseRepository.php';
require_once __DIR__ . '/../repositories/StockRepository.php';
require_once __DIR__ . '/../repositories/StockAlertRepository.php';

class AlertAutoClearReplenishmentTest extends PropertyTestBase {
    
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
        echo "=== Alert Auto-Clear on Replenishment Property Test ===\n";
        echo "**Feature: adv-crm-inventory-module, Property 13: Alert Auto-Clear on Replenishment**\n";
        echo "**Validates: Requirements 13.4**\n\n";
        
        // Check if required tables exist
        if (!$this->tablesExist()) {
            echo "SKIPPED: Required tables not found. Run migrations first.\n";
            return true;
        }
        
        $allPassed = true;
        
        // Run property test for alert auto-clear
        $allPassed &= $this->runPropertyTest(
            'Property 13: Alert is automatically cleared when stock is replenished above threshold',
            function() {
                return $this->testAlertAutoClearOnReplenishment();
            },
            100
        );
        
        // Cleanup
        $this->cleanupTestData();
        
        return $allPassed;
    }
    
    /**
     * Test alert auto-clear on replenishment
     * 
     * Property: For any product with an active low stock alert,
     * when stock is replenished to a level >= threshold,
     * the alert SHALL be automatically cleared.
     */
    private function testAlertAutoClearOnReplenishment() {
        // Generate random test data
        $threshold = $this->generateRandomInt(20, 100);
        $initialStock = $this->generateRandomInt(0, $threshold - 1); // Below threshold
        $replenishAmount = $this->generateRandomInt($threshold - $initialStock, 200); // Enough to go above threshold
        
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
        
        // Add initial stock (below threshold)
        if ($initialStock > 0) {
            $addResult = $this->stockService->addStock($product['id'], $warehouse['id'], $initialStock);
            if (!$addResult['success']) {
                return ['success' => false, 'message' => 'Failed to add initial stock: ' . $addResult['message']];
            }
        }
        
        // Check low stock - this should generate an alert
        $checkResult = $this->alertService->checkLowStock($product['id'], $warehouse['id']);
        
        if (!$checkResult['success']) {
            return [
                'success' => false,
                'message' => 'Initial checkLowStock failed: ' . ($checkResult['message'] ?? 'Unknown error'),
                'data' => $checkResult
            ];
        }
        
        // Verify alert was generated
        if (!$checkResult['is_low_stock']) {
            return [
                'success' => false,
                'message' => 'Expected low stock condition but got is_low_stock=false',
                'data' => [
                    'initial_stock' => $initialStock,
                    'threshold' => $threshold
                ]
            ];
        }
        
        // Verify active alert exists
        $activeAlert = $this->alertRepository->findActiveAlert(
            $product['id'], 
            $warehouse['id'], 
            InventoryAlertService::TYPE_LOW_STOCK
        );
        
        if (!$activeAlert) {
            return [
                'success' => false,
                'message' => 'Expected active alert but none found',
                'data' => [
                    'initial_stock' => $initialStock,
                    'threshold' => $threshold
                ]
            ];
        }
        
        $alertId = $activeAlert['id'];
        $this->createdRecords['alerts'][] = $alertId;
        
        // Now replenish stock to go above threshold
        $addResult = $this->stockService->addStock($product['id'], $warehouse['id'], $replenishAmount);
        if (!$addResult['success']) {
            return ['success' => false, 'message' => 'Failed to replenish stock: ' . $addResult['message']];
        }
        
        $finalStock = $initialStock + $replenishAmount;
        
        // Check low stock again - this should clear the alert
        $checkResult = $this->alertService->checkLowStock($product['id'], $warehouse['id']);
        
        if (!$checkResult['success']) {
            return [
                'success' => false,
                'message' => 'Replenishment checkLowStock failed: ' . ($checkResult['message'] ?? 'Unknown error'),
                'data' => $checkResult
            ];
        }
        
        // Verify stock is now above threshold
        if ($finalStock < $threshold) {
            // This shouldn't happen based on our test setup, but check anyway
            return [
                'success' => false,
                'message' => 'Test setup error: final stock still below threshold',
                'data' => [
                    'initial_stock' => $initialStock,
                    'replenish_amount' => $replenishAmount,
                    'final_stock' => $finalStock,
                    'threshold' => $threshold
                ]
            ];
        }
        
        // Verify is_low_stock is now false
        if ($checkResult['is_low_stock']) {
            return [
                'success' => false,
                'message' => 'Property violated: Stock is above threshold but is_low_stock=true',
                'data' => [
                    'final_stock' => $finalStock,
                    'threshold' => $threshold,
                    'is_low_stock' => $checkResult['is_low_stock']
                ]
            ];
        }
        
        // Verify the alert was cleared (no active alert should exist)
        $activeAlertAfter = $this->alertRepository->findActiveAlert(
            $product['id'], 
            $warehouse['id'], 
            InventoryAlertService::TYPE_LOW_STOCK
        );
        
        if ($activeAlertAfter) {
            return [
                'success' => false,
                'message' => 'Property violated: Stock replenished above threshold but alert still active',
                'data' => [
                    'initial_stock' => $initialStock,
                    'replenish_amount' => $replenishAmount,
                    'final_stock' => $finalStock,
                    'threshold' => $threshold,
                    'alert_id' => $activeAlertAfter['id'],
                    'alert_status' => $activeAlertAfter['status']
                ]
            ];
        }
        
        // Verify the original alert was cleared (status changed to 'cleared')
        $clearedAlert = $this->alertRepository->find($alertId);
        if ($clearedAlert && $clearedAlert['status'] !== 'cleared') {
            return [
                'success' => false,
                'message' => 'Property violated: Alert status not changed to cleared',
                'data' => [
                    'alert_id' => $alertId,
                    'expected_status' => 'cleared',
                    'actual_status' => $clearedAlert['status']
                ]
            ];
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
