<?php
/**
 * Unit Tests for StockService
 * Tests stock addition for serializable/non-serializable items
 * Tests availability validation
 * Tests reservation logic
 * 
 * Requirements: 3.1, 3.2, 5.2
 */

require_once 'PropertyTestBase.php';
require_once __DIR__ . '/../services/StockService.php';
require_once __DIR__ . '/../repositories/ProductRepository.php';
require_once __DIR__ . '/../repositories/WarehouseRepository.php';
require_once __DIR__ . '/../repositories/StockRepository.php';
require_once __DIR__ . '/../repositories/AssetRepository.php';

class StockServiceTest extends PropertyTestBase {
    
    private $stockService;
    private $productRepository;
    private $warehouseRepository;
    private $stockRepository;
    private $assetRepository;
    private $createdRecords = [];
    
    public function __construct() {
        parent::__construct();
        $this->stockService = new StockService();
        $this->productRepository = new ProductRepository();
        $this->warehouseRepository = new WarehouseRepository();
        $this->warehouseRepository->disableCompanyFilter();
        $this->stockRepository = new StockRepository();
        $this->assetRepository = new AssetRepository();
    }
    
    public function runTests() {
        echo "=== StockService Unit Tests ===\n\n";
        
        // Check if required tables exist
        if (!$this->tablesExist()) {
            echo "SKIPPED: Required tables not found. Run migrations first.\n";
            return true;
        }
        
        $allPassed = true;
        
        // Stock addition tests
        $allPassed &= $this->testAddStockForNonSerializableProduct();
        $allPassed &= $this->testAddStockRejectsSerializableProduct();
        $allPassed &= $this->testAddStockRejectsInvalidQuantity();
        $allPassed &= $this->testAddStockRejectsInvalidProduct();
        $allPassed &= $this->testAddStockRejectsInvalidWarehouse();
        
        // Asset addition tests
        $allPassed &= $this->testAddAssetForSerializableProduct();
        $allPassed &= $this->testAddAssetRejectsNonSerializableProduct();
        $allPassed &= $this->testAddAssetRejectsDuplicateSerialNumber();
        $allPassed &= $this->testAddAssetRejectsEmptySerialNumber();
        
        // Availability validation tests
        $allPassed &= $this->testValidateStockAvailabilitySuccess();
        $allPassed &= $this->testValidateStockAvailabilityInsufficientStock();
        $allPassed &= $this->testValidateStockAvailabilityInactiveWarehouse();
        
        // Reservation tests
        $allPassed &= $this->testReserveStockSuccess();
        $allPassed &= $this->testReserveStockInsufficientAvailable();
        $allPassed &= $this->testReleaseReservation();
        
        // Cleanup
        $this->cleanupTestData();
        
        return $allPassed;
    }
    
    /**
     * Test adding stock for non-serializable product
     * Requirement 3.2
     */
    public function testAddStockForNonSerializableProduct() {
        echo "Testing addStock for non-serializable product... ";
        
        try {
            $product = $this->createTestProduct(false);
            $warehouse = $this->createTestWarehouse();
            
            $result = $this->stockService->addStock($product['id'], $warehouse['id'], 50);
            
            $this->assert($result['success'], "addStock should succeed");
            
            // Verify stock was added
            $available = $this->stockService->getAvailableStock($product['id'], $warehouse['id']);
            $this->assert($available === 50, "Available stock should be 50, got $available");
            
            echo "PASSED\n";
            return true;
            
        } catch (Exception $e) {
            echo "FAILED: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * Test addStock rejects serializable products
     */
    public function testAddStockRejectsSerializableProduct() {
        echo "Testing addStock rejects serializable product... ";
        
        try {
            $product = $this->createTestProduct(true);
            $warehouse = $this->createTestWarehouse();
            
            $result = $this->stockService->addStock($product['id'], $warehouse['id'], 10);
            
            $this->assert(!$result['success'], "addStock should fail for serializable product");
            $this->assert($result['code'] === 'SERIALIZABLE_PRODUCT', "Error code should be SERIALIZABLE_PRODUCT");
            
            echo "PASSED\n";
            return true;
            
        } catch (Exception $e) {
            echo "FAILED: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * Test addStock rejects invalid quantity
     */
    public function testAddStockRejectsInvalidQuantity() {
        echo "Testing addStock rejects invalid quantity... ";
        
        try {
            $product = $this->createTestProduct(false);
            $warehouse = $this->createTestWarehouse();
            
            // Test zero quantity
            $result = $this->stockService->addStock($product['id'], $warehouse['id'], 0);
            $this->assert(!$result['success'], "addStock should fail for zero quantity");
            $this->assert($result['code'] === 'INVALID_QUANTITY', "Error code should be INVALID_QUANTITY");
            
            // Test negative quantity
            $result = $this->stockService->addStock($product['id'], $warehouse['id'], -5);
            $this->assert(!$result['success'], "addStock should fail for negative quantity");
            
            echo "PASSED\n";
            return true;
            
        } catch (Exception $e) {
            echo "FAILED: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * Test addStock rejects invalid product
     */
    public function testAddStockRejectsInvalidProduct() {
        echo "Testing addStock rejects invalid product... ";
        
        try {
            $warehouse = $this->createTestWarehouse();
            
            $result = $this->stockService->addStock(999999, $warehouse['id'], 10);
            
            $this->assert(!$result['success'], "addStock should fail for invalid product");
            $this->assert($result['code'] === 'PRODUCT_NOT_FOUND', "Error code should be PRODUCT_NOT_FOUND");
            
            echo "PASSED\n";
            return true;
            
        } catch (Exception $e) {
            echo "FAILED: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * Test addStock rejects invalid warehouse
     */
    public function testAddStockRejectsInvalidWarehouse() {
        echo "Testing addStock rejects invalid warehouse... ";
        
        try {
            $product = $this->createTestProduct(false);
            
            $result = $this->stockService->addStock($product['id'], 999999, 10);
            
            $this->assert(!$result['success'], "addStock should fail for invalid warehouse");
            $this->assert($result['code'] === 'WAREHOUSE_NOT_FOUND', "Error code should be WAREHOUSE_NOT_FOUND");
            
            echo "PASSED\n";
            return true;
            
        } catch (Exception $e) {
            echo "FAILED: " . $e->getMessage() . "\n";
            return false;
        }
    }

    
    /**
     * Test adding asset for serializable product
     * Requirement 3.1
     */
    public function testAddAssetForSerializableProduct() {
        echo "Testing addAsset for serializable product... ";
        
        try {
            $product = $this->createTestProduct(true);
            $warehouse = $this->createTestWarehouse();
            $serialNumber = 'SN-TEST-' . $this->generateRandomString(10);
            
            $result = $this->stockService->addAsset($product['id'], $warehouse['id'], $serialNumber);
            
            $this->assert($result['success'], "addAsset should succeed");
            $this->assert(isset($result['data']['id']), "Result should contain asset ID");
            $this->assert($result['data']['serial_number'] === $serialNumber, "Serial number should match");
            $this->assert($result['data']['status'] === 'in_stock', "Status should be in_stock");
            
            $this->createdRecords['assets'][] = $result['data']['id'];
            
            // Verify asset was created
            $available = $this->stockService->getAvailableStock($product['id'], $warehouse['id']);
            $this->assert($available === 1, "Available stock should be 1, got $available");
            
            echo "PASSED\n";
            return true;
            
        } catch (Exception $e) {
            echo "FAILED: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * Test addAsset rejects non-serializable products
     */
    public function testAddAssetRejectsNonSerializableProduct() {
        echo "Testing addAsset rejects non-serializable product... ";
        
        try {
            $product = $this->createTestProduct(false);
            $warehouse = $this->createTestWarehouse();
            
            $result = $this->stockService->addAsset($product['id'], $warehouse['id'], 'SN-TEST-123');
            
            $this->assert(!$result['success'], "addAsset should fail for non-serializable product");
            $this->assert($result['code'] === 'NON_SERIALIZABLE_PRODUCT', "Error code should be NON_SERIALIZABLE_PRODUCT");
            
            echo "PASSED\n";
            return true;
            
        } catch (Exception $e) {
            echo "FAILED: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * Test addAsset rejects duplicate serial number
     */
    public function testAddAssetRejectsDuplicateSerialNumber() {
        echo "Testing addAsset rejects duplicate serial number... ";
        
        try {
            $product = $this->createTestProduct(true);
            $warehouse = $this->createTestWarehouse();
            $serialNumber = 'SN-DUP-' . $this->generateRandomString(10);
            
            // Create first asset
            $result1 = $this->stockService->addAsset($product['id'], $warehouse['id'], $serialNumber);
            $this->assert($result1['success'], "First addAsset should succeed");
            $this->createdRecords['assets'][] = $result1['data']['id'];
            
            // Try to create duplicate
            $result2 = $this->stockService->addAsset($product['id'], $warehouse['id'], $serialNumber);
            $this->assert(!$result2['success'], "Second addAsset should fail");
            $this->assert($result2['code'] === 'DUPLICATE_SERIAL_NUMBER', "Error code should be DUPLICATE_SERIAL_NUMBER");
            
            echo "PASSED\n";
            return true;
            
        } catch (Exception $e) {
            echo "FAILED: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * Test addAsset rejects empty serial number
     */
    public function testAddAssetRejectsEmptySerialNumber() {
        echo "Testing addAsset rejects empty serial number... ";
        
        try {
            $product = $this->createTestProduct(true);
            $warehouse = $this->createTestWarehouse();
            
            // Test empty string
            $result = $this->stockService->addAsset($product['id'], $warehouse['id'], '');
            $this->assert(!$result['success'], "addAsset should fail for empty serial number");
            $this->assert($result['code'] === 'SERIAL_NUMBER_REQUIRED', "Error code should be SERIAL_NUMBER_REQUIRED");
            
            // Test whitespace only
            $result = $this->stockService->addAsset($product['id'], $warehouse['id'], '   ');
            $this->assert(!$result['success'], "addAsset should fail for whitespace-only serial number");
            
            echo "PASSED\n";
            return true;
            
        } catch (Exception $e) {
            echo "FAILED: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * Test validateStockAvailability success
     * Requirement 5.2
     */
    public function testValidateStockAvailabilitySuccess() {
        echo "Testing validateStockAvailability success... ";
        
        try {
            $product = $this->createTestProduct(false);
            $warehouse = $this->createTestWarehouse();
            
            // Add stock
            $this->stockService->addStock($product['id'], $warehouse['id'], 100);
            
            // Validate availability
            $result = $this->stockService->validateStockAvailability($product['id'], $warehouse['id'], 50);
            
            $this->assert($result['success'], "Validation should succeed");
            $this->assert($result['available'] === 100, "Available should be 100");
            $this->assert($result['requested'] === 50, "Requested should be 50");
            
            echo "PASSED\n";
            return true;
            
        } catch (Exception $e) {
            echo "FAILED: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * Test validateStockAvailability with insufficient stock
     */
    public function testValidateStockAvailabilityInsufficientStock() {
        echo "Testing validateStockAvailability with insufficient stock... ";
        
        try {
            $product = $this->createTestProduct(false);
            $warehouse = $this->createTestWarehouse();
            
            // Add limited stock
            $this->stockService->addStock($product['id'], $warehouse['id'], 10);
            
            // Try to validate more than available
            $result = $this->stockService->validateStockAvailability($product['id'], $warehouse['id'], 50);
            
            $this->assert(!$result['success'], "Validation should fail");
            $this->assert($result['code'] === 'INSUFFICIENT_STOCK', "Error code should be INSUFFICIENT_STOCK");
            $this->assert($result['available'] === 10, "Available should be 10");
            $this->assert($result['requested'] === 50, "Requested should be 50");
            
            echo "PASSED\n";
            return true;
            
        } catch (Exception $e) {
            echo "FAILED: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * Test validateStockAvailability with inactive warehouse
     */
    public function testValidateStockAvailabilityInactiveWarehouse() {
        echo "Testing validateStockAvailability with inactive warehouse... ";
        
        try {
            $product = $this->createTestProduct(false);
            $warehouse = $this->createTestWarehouse('inactive');
            
            $result = $this->stockService->validateStockAvailability($product['id'], $warehouse['id'], 10);
            
            $this->assert(!$result['success'], "Validation should fail for inactive warehouse");
            $this->assert($result['code'] === 'WAREHOUSE_INACTIVE', "Error code should be WAREHOUSE_INACTIVE");
            
            echo "PASSED\n";
            return true;
            
        } catch (Exception $e) {
            echo "FAILED: " . $e->getMessage() . "\n";
            return false;
        }
    }

    
    /**
     * Test reserveStock success
     * Requirement 5.2
     */
    public function testReserveStockSuccess() {
        echo "Testing reserveStock success... ";
        
        try {
            $product = $this->createTestProduct(false);
            $warehouse = $this->createTestWarehouse();
            
            // Add stock
            $this->stockService->addStock($product['id'], $warehouse['id'], 100);
            
            // Reserve stock
            $result = $this->stockService->reserveStock($product['id'], $warehouse['id'], 30);
            
            $this->assert($result['success'], "Reserve should succeed");
            $this->assert($result['quantity'] === 30, "Reserved quantity should be 30");
            
            // Verify available stock is reduced
            $available = $this->stockService->getAvailableStock($product['id'], $warehouse['id']);
            $this->assert($available === 70, "Available stock should be 70 after reservation, got $available");
            
            echo "PASSED\n";
            return true;
            
        } catch (Exception $e) {
            echo "FAILED: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * Test reserveStock with insufficient available stock
     */
    public function testReserveStockInsufficientAvailable() {
        echo "Testing reserveStock with insufficient available stock... ";
        
        try {
            $product = $this->createTestProduct(false);
            $warehouse = $this->createTestWarehouse();
            
            // Add limited stock
            $this->stockService->addStock($product['id'], $warehouse['id'], 20);
            
            // Try to reserve more than available
            $result = $this->stockService->reserveStock($product['id'], $warehouse['id'], 50);
            
            $this->assert(!$result['success'], "Reserve should fail");
            $this->assert($result['code'] === 'INSUFFICIENT_STOCK', "Error code should be INSUFFICIENT_STOCK");
            
            echo "PASSED\n";
            return true;
            
        } catch (Exception $e) {
            echo "FAILED: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * Test releaseReservation
     */
    public function testReleaseReservation() {
        echo "Testing releaseReservation... ";
        
        try {
            $product = $this->createTestProduct(false);
            $warehouse = $this->createTestWarehouse();
            
            // Add stock and reserve
            $this->stockService->addStock($product['id'], $warehouse['id'], 100);
            $this->stockService->reserveStock($product['id'], $warehouse['id'], 30);
            
            // Verify available is reduced
            $available = $this->stockService->getAvailableStock($product['id'], $warehouse['id']);
            $this->assert($available === 70, "Available should be 70 after reservation");
            
            // Release reservation
            $result = $this->stockService->releaseReservation($product['id'], $warehouse['id'], 30);
            $this->assert($result['success'], "Release should succeed");
            
            // Verify available is restored
            $available = $this->stockService->getAvailableStock($product['id'], $warehouse['id']);
            $this->assert($available === 100, "Available should be 100 after release, got $available");
            
            echo "PASSED\n";
            return true;
            
        } catch (Exception $e) {
            echo "FAILED: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * Check if required tables exist
     */
    private function tablesExist() {
        $requiredTables = ['products', 'warehouses', 'stock', 'assets', 'companies'];
        
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
            throw new Exception("Failed to create test product: " . $e->getMessage());
        }
    }
    
    /**
     * Create a test warehouse
     */
    private function createTestWarehouse($status = 'active') {
        try {
            $companyId = $this->getTestCompanyId();
            
            $warehouseData = [
                'name' => 'Test Warehouse ' . $this->generateRandomString(8),
                'location' => 'Test Location',
                'company_id' => $companyId,
                'status' => $status
            ];
            
            $warehouse = $this->warehouseRepository->create($warehouseData);
            $this->createdRecords['warehouses'][] = $warehouse['id'];
            return $warehouse;
            
        } catch (Exception $e) {
            throw new Exception("Failed to create test warehouse: " . $e->getMessage());
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
     * Clean up all test data
     */
    public function cleanupTestData() {
        try {
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
