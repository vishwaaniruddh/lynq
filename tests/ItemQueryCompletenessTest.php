<?php
/**
 * Property Test: Item Query Completeness
 * 
 * **Feature: adv-crm-inventory-module, Property 20: Item Query Completeness**
 * **Validates: Requirements 6.2, 12.4**
 * 
 * Property: For any item query, the response SHALL include current status, 
 * current holder, source warehouse, working condition, and repair/scrap status.
 * 
 * Requirements:
 * - 6.2: Return current status, current holder, source warehouse, and working condition
 * - 12.4: Provide answers to: current location, current holder, source warehouse, 
 *         working status, and repair/scrap status
 */

require_once 'PropertyTestBase.php';
require_once __DIR__ . '/../repositories/AssetRepository.php';
require_once __DIR__ . '/../repositories/ProductRepository.php';
require_once __DIR__ . '/../repositories/WarehouseRepository.php';
require_once __DIR__ . '/../repositories/RepairRepository.php';

class ItemQueryCompletenessTest extends PropertyTestBase {
    
    private $assetRepository;
    private $productRepository;
    private $warehouseRepository;
    private $repairRepository;
    private $createdRecords = [];
    
    public function __construct() {
        parent::__construct();
        $this->assetRepository = new AssetRepository();
        $this->productRepository = new ProductRepository();
        $this->warehouseRepository = new WarehouseRepository();
        $this->warehouseRepository->disableCompanyFilter();
        $this->repairRepository = new RepairRepository();
    }
    
    public function runTests() {
        echo "=== Item Query Completeness Property Test ===\n";
        echo "**Feature: adv-crm-inventory-module, Property 20: Item Query Completeness**\n";
        echo "**Validates: Requirements 6.2, 12.4**\n\n";
        
        // Check if required tables exist
        if (!$this->tablesExist()) {
            echo "SKIPPED: Required tables not found. Run migrations first.\n";
            return true;
        }
        
        $allPassed = true;
        
        // Run property test for item query completeness
        $allPassed &= $this->runPropertyTest(
            'Property 20: Item query returns all required fields',
            function() {
                return $this->testItemQueryCompleteness();
            },
            100
        );
        
        // Cleanup
        $this->cleanupTestData();
        
        return $allPassed;
    }

    
    /**
     * Test item query completeness
     * 
     * Property: For any item query, the response SHALL include:
     * - current status (valid status value)
     * - current holder (type and id)
     * - source warehouse (id)
     * - working condition (working/not_working)
     * - repair/scrap status indicators
     */
    private function testItemQueryCompleteness() {
        // Generate random test data
        $status = $this->generateRandomChoice(AssetRepository::getStatuses());
        $workingCondition = $this->generateRandomChoice(AssetRepository::getWorkingConditions());
        $holderType = $this->generateRandomChoice([
            AssetRepository::HOLDER_WAREHOUSE,
            AssetRepository::HOLDER_COMPANY,
            AssetRepository::HOLDER_USER
        ]);
        
        // Create test warehouse
        $warehouse = $this->createTestWarehouse();
        if (!$warehouse) {
            return ['success' => false, 'message' => 'Failed to create test warehouse'];
        }
        
        // Create test product (serializable)
        $product = $this->createTestProduct();
        if (!$product) {
            return ['success' => false, 'message' => 'Failed to create test product'];
        }
        
        // Get holder ID based on type
        $holderId = $this->getHolderIdByType($holderType, $warehouse);
        if (!$holderId) {
            return ['success' => false, 'message' => 'Failed to get holder ID for type: ' . $holderType];
        }
        
        // Create test asset with random status and condition
        $serialNumber = 'TEST-' . $this->generateRandomString(12);
        $assetData = [
            'product_id' => $product['id'],
            'serial_number' => $serialNumber,
            'warehouse_id' => $warehouse['id'],
            'status' => $status,
            'working_condition' => $workingCondition,
            'current_holder_type' => $holderType,
            'current_holder_id' => $holderId,
            'source_warehouse_id' => $warehouse['id']
        ];
        
        try {
            $asset = $this->assetRepository->create($assetData);
            $this->createdRecords['assets'][] = $asset['id'];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to create test asset: ' . $e->getMessage(),
                'data' => $assetData
            ];
        }
        
        // Query the asset using findWithDetails (the method used by API)
        $queriedAsset = $this->assetRepository->findWithDetails($asset['id']);
        
        if (!$queriedAsset) {
            return [
                'success' => false,
                'message' => 'Failed to query asset with details',
                'data' => ['asset_id' => $asset['id']]
            ];
        }
        
        // Verify all required fields are present per Requirements 6.2 and 12.4
        
        // 1. Check current status (Requirement 6.2)
        if (!isset($queriedAsset['status']) || empty($queriedAsset['status'])) {
            return [
                'success' => false,
                'message' => 'Current status is missing from query response',
                'data' => ['queried_asset' => $queriedAsset]
            ];
        }
        
        if ($queriedAsset['status'] !== $status) {
            return [
                'success' => false,
                'message' => 'Current status does not match expected value',
                'data' => [
                    'expected_status' => $status,
                    'actual_status' => $queriedAsset['status']
                ]
            ];
        }
        
        // 2. Check current holder (Requirement 6.2, 12.4)
        if (!isset($queriedAsset['current_holder_type'])) {
            return [
                'success' => false,
                'message' => 'Current holder type is missing from query response',
                'data' => ['queried_asset' => $queriedAsset]
            ];
        }
        
        if (!isset($queriedAsset['current_holder_id'])) {
            return [
                'success' => false,
                'message' => 'Current holder ID is missing from query response',
                'data' => ['queried_asset' => $queriedAsset]
            ];
        }
        
        if ($queriedAsset['current_holder_type'] !== $holderType) {
            return [
                'success' => false,
                'message' => 'Current holder type does not match expected value',
                'data' => [
                    'expected_holder_type' => $holderType,
                    'actual_holder_type' => $queriedAsset['current_holder_type']
                ]
            ];
        }
        
        // 3. Check source warehouse (Requirement 6.2, 12.4)
        if (!array_key_exists('source_warehouse_id', $queriedAsset)) {
            return [
                'success' => false,
                'message' => 'Source warehouse ID is missing from query response',
                'data' => ['queried_asset' => $queriedAsset]
            ];
        }
        
        // 4. Check working condition (Requirement 6.2, 12.4)
        if (!isset($queriedAsset['working_condition']) || empty($queriedAsset['working_condition'])) {
            return [
                'success' => false,
                'message' => 'Working condition is missing from query response',
                'data' => ['queried_asset' => $queriedAsset]
            ];
        }
        
        if ($queriedAsset['working_condition'] !== $workingCondition) {
            return [
                'success' => false,
                'message' => 'Working condition does not match expected value',
                'data' => [
                    'expected_condition' => $workingCondition,
                    'actual_condition' => $queriedAsset['working_condition']
                ]
            ];
        }
        
        // 5. Check current location/warehouse (Requirement 12.4)
        if (!array_key_exists('warehouse_id', $queriedAsset)) {
            return [
                'success' => false,
                'message' => 'Current warehouse ID (location) is missing from query response',
                'data' => ['queried_asset' => $queriedAsset]
            ];
        }
        
        // 6. Verify repair/scrap status can be determined (Requirement 12.4)
        // The status field should allow determining if item is under repair or scrapped
        $isUnderRepair = ($queriedAsset['status'] === AssetRepository::STATUS_UNDER_REPAIR);
        $isScrapped = ($queriedAsset['status'] === AssetRepository::STATUS_SCRAPPED);
        $isLost = ($queriedAsset['status'] === AssetRepository::STATUS_LOST);
        
        // Verify status allows determining repair/scrap status
        if ($status === AssetRepository::STATUS_UNDER_REPAIR && !$isUnderRepair) {
            return [
                'success' => false,
                'message' => 'Cannot determine repair status from query response',
                'data' => [
                    'expected_under_repair' => true,
                    'actual_status' => $queriedAsset['status']
                ]
            ];
        }
        
        if ($status === AssetRepository::STATUS_SCRAPPED && !$isScrapped) {
            return [
                'success' => false,
                'message' => 'Cannot determine scrap status from query response',
                'data' => [
                    'expected_scrapped' => true,
                    'actual_status' => $queriedAsset['status']
                ]
            ];
        }
        
        // 7. Verify product details are included (for context)
        if (!array_key_exists('product_name', $queriedAsset)) {
            return [
                'success' => false,
                'message' => 'Product name is missing from query response',
                'data' => ['queried_asset' => $queriedAsset]
            ];
        }
        
        // 8. Verify is_repairable flag is included (needed for repair workflow)
        if (!array_key_exists('is_repairable', $queriedAsset)) {
            return [
                'success' => false,
                'message' => 'is_repairable flag is missing from query response',
                'data' => ['queried_asset' => $queriedAsset]
            ];
        }
        
        return ['success' => true];
    }

    
    /**
     * Check if required tables exist
     */
    private function tablesExist() {
        $requiredTables = ['assets', 'products', 'warehouses', 'companies'];
        
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
                'location' => 'Test Location ' . $this->generateRandomString(5),
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
     * Create a test product (serializable)
     */
    private function createTestProduct() {
        try {
            // Get or create a category
            $categoryId = $this->getTestCategoryId();
            
            $productData = [
                'name' => 'Test Product ' . $this->generateRandomString(8),
                'category_id' => $categoryId,
                'unit_of_measure' => 'piece',
                'inventory_type' => $this->generateRandomChoice(['INTERNAL', 'SITE']),
                'is_serializable' => 1,
                'is_repairable' => $this->generateRandomBool() ? 1 : 0,
                'low_stock_threshold' => $this->generateRandomInt(5, 20),
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
     * Get a test category ID
     */
    private function getTestCategoryId() {
        $sql = "SELECT id FROM product_categories WHERE status = 'active' LIMIT 1";
        $result = $this->getResults($sql);
        
        if (empty($result)) {
            // Create a test category if none exists
            $this->executeQuery(
                "INSERT INTO product_categories (name, status) VALUES (?, ?)",
                ['Test Category ' . $this->generateRandomString(8), 'active'],
                'ss'
            );
            $categoryId = $this->db->insert_id;
            $this->createdRecords['categories'][] = $categoryId;
            return $categoryId;
        }
        
        return $result[0]['id'];
    }
    
    /**
     * Get a test user ID
     */
    private function getTestUserId() {
        $sql = "SELECT id FROM users WHERE status = 'ACTIVE' LIMIT 1";
        $result = $this->getResults($sql);
        
        if (empty($result)) {
            $sql = "SELECT id FROM users LIMIT 1";
            $result = $this->getResults($sql);
            
            if (empty($result)) {
                $companyId = $this->getTestCompanyId();
                $this->executeQuery(
                    "INSERT INTO users (name, email, password, company_id, status) VALUES (?, ?, ?, ?, ?)",
                    ['Test User ' . $this->generateRandomString(8), 'test' . $this->generateRandomString(8) . '@test.com', password_hash('test123', PASSWORD_DEFAULT), $companyId, 'ACTIVE'],
                    'sssss'
                );
                $userId = $this->db->insert_id;
                $this->createdRecords['users'][] = $userId;
                return $userId;
            }
        }
        
        return $result[0]['id'];
    }
    
    /**
     * Get holder ID based on holder type
     */
    private function getHolderIdByType($holderType, $warehouse) {
        switch ($holderType) {
            case AssetRepository::HOLDER_WAREHOUSE:
                return $warehouse['id'];
            case AssetRepository::HOLDER_COMPANY:
                return $warehouse['company_id'];
            case AssetRepository::HOLDER_USER:
                return $this->getTestUserId();
            default:
                return null;
        }
    }
    
    /**
     * Clean up all test data created during tests
     */
    public function cleanupTestData() {
        try {
            // Delete test assets first (due to foreign key constraints)
            if (!empty($this->createdRecords['assets'])) {
                $ids = implode(',', array_map('intval', $this->createdRecords['assets']));
                $this->db->query("DELETE FROM `assets` WHERE id IN ($ids)");
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
            
            // Delete test users
            if (!empty($this->createdRecords['users'])) {
                $ids = implode(',', array_map('intval', $this->createdRecords['users']));
                $this->db->query("DELETE FROM `users` WHERE id IN ($ids)");
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
