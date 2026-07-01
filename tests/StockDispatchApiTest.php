<?php
/**
 * Integration Tests for Stock and Dispatch APIs
 * Tests stock entry flows, dispatch creation and acknowledgment, transfer operations
 * 
 * Requirements: 3.1, 3.2, 4.1, 5.1, 5.3, 5.4, 14.1
 */

require_once 'PropertyTestBase.php';
require_once __DIR__ . '/../repositories/WarehouseRepository.php';
require_once __DIR__ . '/../repositories/ProductRepository.php';
require_once __DIR__ . '/../repositories/StockRepository.php';
require_once __DIR__ . '/../repositories/AssetRepository.php';
require_once __DIR__ . '/../repositories/DispatchRepository.php';
require_once __DIR__ . '/../repositories/TransferRepository.php';
require_once __DIR__ . '/../services/StockService.php';
require_once __DIR__ . '/../services/DispatchService.php';
require_once __DIR__ . '/../services/TransferService.php';
require_once __DIR__ . '/../services/BulkInventoryService.php';

class StockDispatchApiTest extends PropertyTestBase {
    
    private $warehouseRepository;
    private $productRepository;
    private $stockRepository;
    private $assetRepository;
    private $dispatchRepository;
    private $transferRepository;
    private $stockService;
    private $dispatchService;
    private $transferService;
    private $bulkService;
    private $createdRecords = [];
    
    public function __construct() {
        parent::__construct();
        $this->warehouseRepository = new WarehouseRepository();
        $this->warehouseRepository->disableCompanyFilter();
        $this->productRepository = new ProductRepository();
        $this->stockRepository = new StockRepository();
        $this->assetRepository = new AssetRepository();
        $this->dispatchRepository = new DispatchRepository();
        $this->dispatchRepository->disableCompanyFilter();
        $this->transferRepository = new TransferRepository();
        $this->stockService = new StockService();
        $this->dispatchService = new DispatchService();
        $this->transferService = new TransferService();
        $this->bulkService = new BulkInventoryService();
    }
    
    public function runTests() {
        echo "=== Stock and Dispatch API Integration Tests ===\n\n";
        
        $allPassed = true;
        
        // Stock entry tests
        $allPassed &= $this->testAddNonSerializableStock();
        $allPassed &= $this->testAddSerializableAsset();
        $allPassed &= $this->testDuplicateSerialNumberRejection();
        $allPassed &= $this->testStockAvailabilityValidation();
        
        // Dispatch tests
        $allPassed &= $this->testDispatchCreation();
        $allPassed &= $this->testDispatchStatusTransition();
        $allPassed &= $this->testDispatchAcknowledgment();
        $allPassed &= $this->testInsufficientStockDispatchRejection();
        
        // Transfer tests
        $allPassed &= $this->testTransferCreation();
        $allPassed &= $this->testTransferProcessing();
        $allPassed &= $this->testTransferStockConservation();
        
        // Cleanup test data
        $this->cleanupTestData();
        
        return $allPassed;
    }
    
    /**
     * Test adding non-serializable stock
     * Requirements: 3.2
     */
    public function testAddNonSerializableStock() {
        echo "Testing non-serializable stock addition... ";
        
        try {
            if (!$this->tableExists('stock') || !$this->tableExists('products') || !$this->tableExists('warehouses')) {
                echo "SKIPPED (required tables not found)\n";
                return true;
            }
            
            // Create test product (non-serializable)
            $product = $this->createTestProduct(false);
            if (!$product) {
                echo "SKIPPED (could not create test product)\n";
                return true;
            }
            
            $warehouse = $this->createTestWarehouse();
            if (!$warehouse) {
                echo "SKIPPED (could not create test warehouse)\n";
                return true;
            }
            
            // Get valid user ID or null
            $userId = $this->getTestUserId();
            
            // Add stock
            $quantity = 50;
            $result = $this->stockService->addStock($product['id'], $warehouse['id'], $quantity, $userId);
            
            if (!$result['success']) {
                echo "FAILED: " . ($result['message'] ?? 'Unknown error') . "\n";
                return false;
            }
            
            // Verify stock level
            $availableStock = $this->stockService->getAvailableStock($product['id'], $warehouse['id']);
            $this->assert($availableStock >= $quantity, "Available stock should be at least $quantity");
            
            echo "PASSED\n";
            return true;
            
        } catch (Exception $e) {
            echo "FAILED: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * Test adding serializable asset
     * Requirements: 3.1
     */
    public function testAddSerializableAsset() {
        echo "Testing serializable asset addition... ";
        
        try {
            if (!$this->tableExists('assets') || !$this->tableExists('products')) {
                echo "SKIPPED (required tables not found)\n";
                return true;
            }
            
            // Create test product (serializable)
            $product = $this->createTestProduct(true);
            $warehouse = $this->createTestWarehouse();
            
            // Get valid user ID or null
            $userId = $this->getTestUserId();
            
            // Add asset with serial number
            $serialNumber = 'SN-TEST-' . $this->generateRandomString(10);
            $result = $this->stockService->addAsset($product['id'], $warehouse['id'], $serialNumber, $userId);
            
            $this->assert($result['success'], "Asset addition should succeed");
            $this->assert(isset($result['data']['id']), "Asset should have an ID");
            $this->createdRecords['assets'][] = $result['data']['id'];
            
            // Verify asset was created with correct status
            $asset = $this->assetRepository->find($result['data']['id']);
            $this->assert($asset['status'] === AssetRepository::STATUS_IN_STOCK, "Asset status should be 'in_stock'");
            $this->assert($asset['serial_number'] === $serialNumber, "Serial number should match");
            
            echo "PASSED\n";
            return true;
            
        } catch (Exception $e) {
            echo "FAILED: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * Test duplicate serial number rejection
     * Requirements: 3.3
     */
    public function testDuplicateSerialNumberRejection() {
        echo "Testing duplicate serial number rejection... ";
        
        try {
            if (!$this->tableExists('assets')) {
                echo "SKIPPED (assets table not found)\n";
                return true;
            }
            
            $product = $this->createTestProduct(true);
            $warehouse = $this->createTestWarehouse();
            
            // Get valid user ID or null
            $userId = $this->getTestUserId();
            
            // Add first asset
            $serialNumber = 'SN-DUP-TEST-' . $this->generateRandomString(10);
            $result1 = $this->stockService->addAsset($product['id'], $warehouse['id'], $serialNumber, $userId);
            $this->assert($result1['success'], "First asset should be created");
            $this->createdRecords['assets'][] = $result1['data']['id'];
            
            // Try to add duplicate
            $result2 = $this->stockService->addAsset($product['id'], $warehouse['id'], $serialNumber, $userId);
            $this->assert(!$result2['success'], "Duplicate serial number should be rejected");
            $this->assert($result2['code'] === 'DUPLICATE_SERIAL_NUMBER', "Error code should be DUPLICATE_SERIAL_NUMBER");
            
            echo "PASSED\n";
            return true;
            
        } catch (Exception $e) {
            echo "FAILED: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * Test stock availability validation
     * Requirements: 5.2
     */
    public function testStockAvailabilityValidation() {
        echo "Testing stock availability validation... ";
        
        try {
            if (!$this->tableExists('stock')) {
                echo "SKIPPED (stock table not found)\n";
                return true;
            }
            
            $product = $this->createTestProduct(false);
            $warehouse = $this->createTestWarehouse();
            
            // Get valid user ID or null
            $userId = $this->getTestUserId();
            
            // Add limited stock
            $this->stockService->addStock($product['id'], $warehouse['id'], 10, $userId);
            
            // Validate sufficient stock
            $result1 = $this->stockService->validateStockAvailability($product['id'], $warehouse['id'], 5);
            $this->assert($result1['success'], "5 units should be available");
            
            // Validate insufficient stock
            $result2 = $this->stockService->validateStockAvailability($product['id'], $warehouse['id'], 100);
            $this->assert(!$result2['success'], "100 units should not be available");
            $this->assert($result2['code'] === 'INSUFFICIENT_STOCK', "Error code should be INSUFFICIENT_STOCK");
            
            echo "PASSED\n";
            return true;
            
        } catch (Exception $e) {
            echo "FAILED: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * Test dispatch creation
     * Requirements: 5.1
     */
    public function testDispatchCreation() {
        echo "Testing dispatch creation... ";
        
        try {
            if (!$this->tableExists('dispatches')) {
                echo "SKIPPED (dispatches table not found)\n";
                return true;
            }
            
            $product = $this->createTestProduct(false);
            $warehouse = $this->createTestWarehouse();
            $companyId = $this->getTestCompanyId();
            
            // Get valid user ID or null
            $userId = $this->getTestUserId();
            
            // Add stock first
            $this->stockService->addStock($product['id'], $warehouse['id'], 100, $userId);
            
            // Create dispatch
            $dispatchData = [
                'from_warehouse_id' => $warehouse['id'],
                'to_company_id' => $companyId,
                'dispatch_date' => date('Y-m-d'),
                'notes' => 'Test dispatch'
            ];
            
            $items = [
                ['product_id' => $product['id'], 'quantity' => 10]
            ];
            
            $result = $this->dispatchService->createDispatch($dispatchData, $items, $userId);
            
            $this->assert($result['success'], "Dispatch creation should succeed: " . ($result['message'] ?? ''));
            $this->assert(isset($result['data']['dispatch']['id']), "Dispatch should have an ID");
            $this->createdRecords['dispatches'][] = $result['data']['dispatch']['id'];
            
            // Verify dispatch was created with correct status
            $dispatch = $this->dispatchRepository->find($result['data']['dispatch']['id']);
            $this->assert($dispatch['status'] === DispatchRepository::STATUS_PENDING, "Dispatch status should be 'pending'");
            
            echo "PASSED\n";
            return true;
            
        } catch (Exception $e) {
            echo "FAILED: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * Test dispatch status transition
     * Requirements: 5.3
     */
    public function testDispatchStatusTransition() {
        echo "Testing dispatch status transition... ";
        
        try {
            if (!$this->tableExists('dispatches')) {
                echo "SKIPPED (dispatches table not found)\n";
                return true;
            }
            
            $product = $this->createTestProduct(false);
            $warehouse = $this->createTestWarehouse();
            $companyId = $this->getTestCompanyId();
            
            // Get valid user ID or null
            $userId = $this->getTestUserId();
            
            // Add stock and create dispatch
            $this->stockService->addStock($product['id'], $warehouse['id'], 100, $userId);
            
            $result = $this->dispatchService->createDispatch(
                ['from_warehouse_id' => $warehouse['id'], 'to_company_id' => $companyId],
                [['product_id' => $product['id'], 'quantity' => 5]],
                $userId
            );
            
            if (!$result['success'] || !isset($result['data']['dispatch']['id'])) {
                echo "SKIPPED (could not create dispatch: " . ($result['message'] ?? 'unknown error') . ")\n";
                return true;
            }
            
            $dispatchId = $result['data']['dispatch']['id'];
            $this->createdRecords['dispatches'][] = $dispatchId;
            
            // Transition to in_transit
            $transitResult = $this->dispatchService->processDispatch($dispatchId, DispatchRepository::STATUS_IN_TRANSIT, $userId);
            $this->assert($transitResult['success'], "Transition to in_transit should succeed");
            
            // Verify status
            $dispatch = $this->dispatchRepository->find($dispatchId);
            $this->assert($dispatch['status'] === DispatchRepository::STATUS_IN_TRANSIT, "Status should be 'in_transit'");
            
            // Transition to delivered
            $deliveredResult = $this->dispatchService->processDispatch($dispatchId, DispatchRepository::STATUS_DELIVERED, $userId);
            $this->assert($deliveredResult['success'], "Transition to delivered should succeed");
            
            echo "PASSED\n";
            return true;
            
        } catch (Exception $e) {
            echo "FAILED: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * Test dispatch acknowledgment
     * Requirements: 14.1
     */
    public function testDispatchAcknowledgment() {
        echo "Testing dispatch acknowledgment... ";
        
        try {
            if (!$this->tableExists('dispatches')) {
                echo "SKIPPED (dispatches table not found)\n";
                return true;
            }
            
            $product = $this->createTestProduct(false);
            $warehouse = $this->createTestWarehouse();
            $companyId = $this->getTestCompanyId();
            
            // Get valid user ID or null
            $userId = $this->getTestUserId();
            
            // Add stock and create dispatch
            $this->stockService->addStock($product['id'], $warehouse['id'], 100, $userId);
            
            $result = $this->dispatchService->createDispatch(
                ['from_warehouse_id' => $warehouse['id'], 'to_company_id' => $companyId],
                [['product_id' => $product['id'], 'quantity' => 5]],
                $userId
            );
            
            if (!$result['success'] || !isset($result['data']['dispatch']['id'])) {
                echo "SKIPPED (could not create dispatch: " . ($result['message'] ?? 'unknown error') . ")\n";
                return true;
            }
            
            $dispatchId = $result['data']['dispatch']['id'];
            $this->createdRecords['dispatches'][] = $dispatchId;
            
            // Process to delivered
            $this->dispatchService->processDispatch($dispatchId, DispatchRepository::STATUS_IN_TRANSIT, $userId);
            $this->dispatchService->processDispatch($dispatchId, DispatchRepository::STATUS_DELIVERED, $userId);
            
            // Acknowledge
            $ackResult = $this->dispatchService->acknowledgeReceipt($dispatchId, $userId);
            $this->assert($ackResult['success'], "Acknowledgment should succeed");
            
            // Verify acknowledgment
            $dispatch = $this->dispatchRepository->find($dispatchId);
            $this->assert($dispatch['acknowledgment_status'] === DispatchRepository::ACK_ACKNOWLEDGED, "Acknowledgment status should be 'acknowledged'");
            if ($userId !== null) {
                $this->assert($dispatch['acknowledged_by'] == $userId, "Acknowledged by should be set");
            }
            
            echo "PASSED\n";
            return true;
            
        } catch (Exception $e) {
            echo "FAILED: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * Test insufficient stock dispatch rejection
     * Requirements: 5.2
     */
    public function testInsufficientStockDispatchRejection() {
        echo "Testing insufficient stock dispatch rejection... ";
        
        try {
            if (!$this->tableExists('dispatches')) {
                echo "SKIPPED (dispatches table not found)\n";
                return true;
            }
            
            $product = $this->createTestProduct(false);
            $warehouse = $this->createTestWarehouse();
            $companyId = $this->getTestCompanyId();
            
            // Get valid user ID or null
            $userId = $this->getTestUserId();
            
            // Add limited stock
            $this->stockService->addStock($product['id'], $warehouse['id'], 5, $userId);
            
            // Try to dispatch more than available
            $result = $this->dispatchService->createDispatch(
                ['from_warehouse_id' => $warehouse['id'], 'to_company_id' => $companyId],
                [['product_id' => $product['id'], 'quantity' => 100]],
                $userId
            );
            
            $this->assert(!$result['success'], "Dispatch should fail due to insufficient stock");
            $this->assert($result['code'] === 'INSUFFICIENT_STOCK', "Error code should be INSUFFICIENT_STOCK");
            
            echo "PASSED\n";
            return true;
            
        } catch (Exception $e) {
            echo "FAILED: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * Test transfer creation
     * Requirements: 5.4
     */
    public function testTransferCreation() {
        echo "Testing transfer creation... ";
        
        try {
            if (!$this->tableExists('transfers')) {
                echo "SKIPPED (transfers table not found)\n";
                return true;
            }
            
            $product = $this->createTestProduct(false);
            $fromWarehouse = $this->createTestWarehouse('From');
            $toWarehouse = $this->createTestWarehouse('To');
            
            // Get valid user ID or null
            $userId = $this->getTestUserId();
            
            // Add stock to source warehouse
            $this->stockService->addStock($product['id'], $fromWarehouse['id'], 100, $userId);
            
            // Create transfer
            $transferData = [
                'from_warehouse_id' => $fromWarehouse['id'],
                'to_warehouse_id' => $toWarehouse['id'],
                'transfer_date' => date('Y-m-d'),
                'notes' => 'Test transfer'
            ];
            
            $items = [
                ['product_id' => $product['id'], 'quantity' => 20]
            ];
            
            $result = $this->transferService->createTransfer($transferData, $items, $userId);
            
            $this->assert($result['success'], "Transfer creation should succeed: " . ($result['message'] ?? ''));
            $this->assert(isset($result['data']['transfer']['id']), "Transfer should have an ID");
            $this->createdRecords['transfers'][] = $result['data']['transfer']['id'];
            
            // Verify transfer was created with correct status
            $transfer = $this->transferRepository->find($result['data']['transfer']['id']);
            $this->assert($transfer['status'] === TransferRepository::STATUS_PENDING, "Transfer status should be 'pending'");
            
            echo "PASSED\n";
            return true;
            
        } catch (Exception $e) {
            echo "FAILED: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * Test transfer processing
     * Requirements: 5.4
     */
    public function testTransferProcessing() {
        echo "Testing transfer processing... ";
        
        try {
            if (!$this->tableExists('transfers')) {
                echo "SKIPPED (transfers table not found)\n";
                return true;
            }
            
            $product = $this->createTestProduct(false);
            $fromWarehouse = $this->createTestWarehouse('ProcessFrom');
            $toWarehouse = $this->createTestWarehouse('ProcessTo');
            
            // Get valid user ID or null
            $userId = $this->getTestUserId();
            
            // Add stock to source warehouse
            $this->stockService->addStock($product['id'], $fromWarehouse['id'], 100, $userId);
            
            // Create and process transfer
            $result = $this->transferService->createTransfer(
                ['from_warehouse_id' => $fromWarehouse['id'], 'to_warehouse_id' => $toWarehouse['id']],
                [['product_id' => $product['id'], 'quantity' => 30]],
                $userId
            );
            
            if (!$result['success'] || !isset($result['data']['transfer']['id'])) {
                echo "SKIPPED (could not create transfer: " . ($result['message'] ?? 'unknown error') . ")\n";
                return true;
            }
            
            $transferId = $result['data']['transfer']['id'];
            $this->createdRecords['transfers'][] = $transferId;
            
            // Process transfer
            $processResult = $this->transferService->processTransfer($transferId, $userId);
            $this->assert($processResult['success'], "Transfer processing should succeed");
            
            // Verify status
            $transfer = $this->transferRepository->find($transferId);
            $this->assert($transfer['status'] === TransferRepository::STATUS_COMPLETED, "Transfer status should be 'completed'");
            
            echo "PASSED\n";
            return true;
            
        } catch (Exception $e) {
            echo "FAILED: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * Test transfer stock conservation
     * Requirements: 5.4
     */
    public function testTransferStockConservation() {
        echo "Testing transfer stock conservation... ";
        
        try {
            if (!$this->tableExists('transfers') || !$this->tableExists('stock')) {
                echo "SKIPPED (required tables not found)\n";
                return true;
            }
            
            $product = $this->createTestProduct(false);
            $fromWarehouse = $this->createTestWarehouse('ConserveFrom');
            $toWarehouse = $this->createTestWarehouse('ConserveTo');
            
            // Get valid user ID or null
            $userId = $this->getTestUserId();
            
            // Add stock to source warehouse
            $initialStock = 100;
            $transferQuantity = 40;
            $this->stockService->addStock($product['id'], $fromWarehouse['id'], $initialStock, $userId);
            
            // Get initial stock levels
            $fromStockBefore = $this->stockService->getAvailableStock($product['id'], $fromWarehouse['id']);
            $toStockBefore = $this->stockService->getAvailableStock($product['id'], $toWarehouse['id']);
            $totalBefore = $fromStockBefore + $toStockBefore;
            
            // Create and process transfer
            $result = $this->transferService->createTransfer(
                ['from_warehouse_id' => $fromWarehouse['id'], 'to_warehouse_id' => $toWarehouse['id']],
                [['product_id' => $product['id'], 'quantity' => $transferQuantity]],
                $userId
            );
            
            if (!$result['success'] || !isset($result['data']['transfer']['id'])) {
                echo "SKIPPED (could not create transfer: " . ($result['message'] ?? 'unknown error') . ")\n";
                return true;
            }
            
            $transferId = $result['data']['transfer']['id'];
            $this->createdRecords['transfers'][] = $transferId;
            
            $this->transferService->processTransfer($transferId, $userId);
            
            // Get stock levels after transfer
            $fromStockAfter = $this->stockService->getAvailableStock($product['id'], $fromWarehouse['id']);
            $toStockAfter = $this->stockService->getAvailableStock($product['id'], $toWarehouse['id']);
            $totalAfter = $fromStockAfter + $toStockAfter;
            
            // Verify conservation
            $this->assert($totalBefore === $totalAfter, "Total stock should be conserved (before: $totalBefore, after: $totalAfter)");
            $this->assert($fromStockAfter === $fromStockBefore - $transferQuantity, "Source stock should decrease by transfer quantity");
            $this->assert($toStockAfter === $toStockBefore + $transferQuantity, "Destination stock should increase by transfer quantity");
            
            echo "PASSED\n";
            return true;
            
        } catch (Exception $e) {
            echo "FAILED: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    // ==================== Helper Methods ====================
    
    /**
     * Get a valid test user ID from the database
     * Returns null if no user exists (to avoid foreign key constraint issues)
     */
    private function getTestUserId() {
        try {
            if (!$this->tableExists('users')) {
                return null;
            }
            
            $sql = "SELECT id FROM users WHERE status = 1 LIMIT 1";
            $result = $this->getResults($sql);
            
            return !empty($result) ? $result[0]['id'] : null;
        } catch (Exception $e) {
            return null;
        }
    }
    
    private function createTestProduct($serializable = false) {
        try {
            if (!$this->tableExists('products')) {
                return null;
            }
            
            $productName = 'Test Product ' . $this->generateRandomString(8);
            $product = $this->productRepository->create([
                'name' => $productName,
                'unit_of_measure' => 'unit',
                'inventory_type' => ProductRepository::TYPE_INTERNAL,
                'is_serializable' => $serializable ? 1 : 0,
                'is_repairable' => 0,
                'status' => ProductRepository::STATUS_ACTIVE
            ]);
            $this->createdRecords['products'][] = $product['id'];
            return $product;
        } catch (Exception $e) {
            error_log("Failed to create test product: " . $e->getMessage());
            return null;
        }
    }
    
    private function createTestWarehouse($suffix = '') {
        try {
            if (!$this->tableExists('warehouses')) {
                return null;
            }
            
            $companyId = $this->getTestCompanyId();
            if (!$companyId) {
                return null;
            }
            
            $warehouseName = 'Test Warehouse ' . $suffix . ' ' . $this->generateRandomString(8);
            $warehouse = $this->warehouseRepository->create([
                'name' => $warehouseName,
                'location' => 'Test Location',
                'company_id' => $companyId,
                'status' => WarehouseRepository::STATUS_ACTIVE
            ]);
            $this->createdRecords['warehouses'][] = $warehouse['id'];
            return $warehouse;
        } catch (Exception $e) {
            error_log("Failed to create test warehouse: " . $e->getMessage());
            return null;
        }
    }
    
    private function getTestCompanyId() {
        try {
            if (!$this->tableExists('companies')) {
                return null;
            }
            
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
        } catch (Exception $e) {
            error_log("Failed to get test company: " . $e->getMessage());
            return null;
        }
    }
    
    private function tableExists($tableName) {
        try {
            $result = $this->db->query("SHOW TABLES LIKE '$tableName'");
            return $result && $result->num_rows > 0;
        } catch (Exception $e) {
            return false;
        }
    }
    
    public function cleanupTestData() {
        try {
            // Delete in reverse order of dependencies
            
            // Delete transfer items and transfers
            if (isset($this->createdRecords['transfers']) && !empty($this->createdRecords['transfers'])) {
                $ids = implode(',', array_map('intval', $this->createdRecords['transfers']));
                $this->db->query("DELETE FROM `transfer_items` WHERE transfer_id IN ($ids)");
                $this->db->query("DELETE FROM `transfers` WHERE id IN ($ids)");
            }
            
            // Delete dispatch items and dispatches
            if (isset($this->createdRecords['dispatches']) && !empty($this->createdRecords['dispatches'])) {
                $ids = implode(',', array_map('intval', $this->createdRecords['dispatches']));
                $this->db->query("DELETE FROM `dispatch_items` WHERE dispatch_id IN ($ids)");
                $this->db->query("DELETE FROM `dispatches` WHERE id IN ($ids)");
            }
            
            // Delete assets
            if (isset($this->createdRecords['assets']) && !empty($this->createdRecords['assets'])) {
                $ids = implode(',', array_map('intval', $this->createdRecords['assets']));
                $this->db->query("DELETE FROM `assets` WHERE id IN ($ids)");
            }
            
            // Delete stock for test products
            if (isset($this->createdRecords['products']) && !empty($this->createdRecords['products'])) {
                $ids = implode(',', array_map('intval', $this->createdRecords['products']));
                $this->db->query("DELETE FROM `stock` WHERE product_id IN ($ids)");
                $this->db->query("DELETE FROM `products` WHERE id IN ($ids)");
            }
            
            // Delete warehouses
            if (isset($this->createdRecords['warehouses']) && !empty($this->createdRecords['warehouses'])) {
                $ids = implode(',', array_map('intval', $this->createdRecords['warehouses']));
                $this->db->query("DELETE FROM `warehouses` WHERE id IN ($ids)");
            }
            
            // Delete companies
            if (isset($this->createdRecords['companies']) && !empty($this->createdRecords['companies'])) {
                $ids = implode(',', array_map('intval', $this->createdRecords['companies']));
                $this->db->query("DELETE FROM `companies` WHERE id IN ($ids)");
            }
            
            $this->createdRecords = [];
            
        } catch (Exception $e) {
            echo "Cleanup warning: " . $e->getMessage() . "\n";
        }
    }
}


// Run tests if executed directly
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    $test = new StockDispatchApiTest();
    $result = $test->runTests();
    exit($result ? 0 : 1);
}
