<?php
/**
 * Property Test for Material Master Round-Trip Persistence
 * **Feature: material-request-module, Property 1: Material Master Round-Trip Persistence**
 * **Validates: Requirements 1.4, 9.2**
 * 
 * For any valid Material Master data (name, description, products with quantities), 
 * creating the master via API and then retrieving it should return equivalent data 
 * with all product associations intact.
 */

require_once __DIR__ . '/PropertyTestBase.php';
require_once __DIR__ . '/../services/MaterialMasterService.php';
require_once __DIR__ . '/../repositories/MaterialMasterRepository.php';

class MaterialMasterRoundTripPropertyTest extends PropertyTestBase {
    
    private $materialMasterService;
    private $materialMasterRepository;
    private $createdRecords = [];
    private $testCompanyId;
    private $testUserId;
    private $testProductIds = [];
    
    public function __construct() {
        parent::__construct();
        $this->materialMasterService = new MaterialMasterService();
        $this->materialMasterRepository = new MaterialMasterRepository();
        $this->iterations = 20; // As specified in design doc
    }
    
    public function runTests() {
        echo "=== Material Master Round-Trip Property Tests ===\n\n";
        
        // Setup test data
        $this->setupTestData();
        
        $allPassed = true;
        
        // Test Create Round-Trip
        $allPassed &= $this->runPropertyTest(
            "Material Master Create Round-Trip Persistence",
            [$this, 'testMaterialMasterCreateRoundTrip']
        );
        
        // Test Update Round-Trip
        $allPassed &= $this->runPropertyTest(
            "Material Master Update Round-Trip Persistence",
            [$this, 'testMaterialMasterUpdateRoundTrip']
        );
        
        // Cleanup test data
        $this->cleanupTestData();
        
        return $allPassed;
    }
    
    /**
     * Property 1: Material Master Round-Trip Persistence (Create)
     * For any valid Material Master data, creating and retrieving should return equivalent data
     * **Feature: material-request-module, Property 1: Material Master Round-Trip Persistence**
     * **Validates: Requirements 1.4, 9.2**
     */
    public function testMaterialMasterCreateRoundTrip() {
        try {
            // Generate random Material Master data
            $masterData = $this->generateMaterialMasterData();
            
            // Create Material Master via service
            $result = $this->materialMasterService->create(
                $masterData,
                $this->testUserId,
                $this->testCompanyId
            );
            
            $this->assert($result['success'], "Material Master creation failed: " . ($result['message'] ?? 'Unknown error'));
            $this->assert(isset($result['data']['id']), "Created Material Master should have an ID");
            
            $masterId = $result['data']['id'];
            $this->createdRecords['material_masters'][] = $masterId;
            
            // Retrieve Material Master
            $retrieved = $this->materialMasterService->getById($masterId, $this->testCompanyId);
            
            $this->assert($retrieved !== null, "Material Master retrieval failed");
            
            // Verify round-trip consistency for master data
            $this->assert(
                $retrieved['name'] === $masterData['name'],
                "Name mismatch: expected '{$masterData['name']}', got '{$retrieved['name']}'"
            );
            
            $expectedDesc = $masterData['description'] ?? null;
            $this->assert(
                $retrieved['description'] === $expectedDesc,
                "Description mismatch: expected '$expectedDesc', got '{$retrieved['description']}'"
            );
            
            $this->assert(
                $retrieved['status'] === ($masterData['status'] ?? 'active'),
                "Status mismatch: expected '" . ($masterData['status'] ?? 'active') . "', got '{$retrieved['status']}'"
            );
            
            // Verify items round-trip consistency
            $this->assert(
                count($retrieved['items']) === count($masterData['items']),
                "Item count mismatch: expected " . count($masterData['items']) . ", got " . count($retrieved['items'])
            );
            
            // Verify each item
            foreach ($masterData['items'] as $inputItem) {
                $found = false;
                foreach ($retrieved['items'] as $retrievedItem) {
                    if ((int)$retrievedItem['product_id'] === (int)$inputItem['product_id']) {
                        $this->assert(
                            (int)$retrievedItem['quantity'] === (int)$inputItem['quantity'],
                            "Quantity mismatch for product {$inputItem['product_id']}: expected {$inputItem['quantity']}, got {$retrievedItem['quantity']}"
                        );
                        $found = true;
                        break;
                    }
                }
                $this->assert($found, "Product {$inputItem['product_id']} not found in retrieved items");
            }
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => $masterData ?? null
            ];
        }
    }
    
    /**
     * Property 1: Material Master Round-Trip Persistence (Update)
     * For any Material Master update, updating and retrieving should return equivalent data
     * **Feature: material-request-module, Property 1: Material Master Round-Trip Persistence**
     * **Validates: Requirements 1.4, 9.2**
     */
    public function testMaterialMasterUpdateRoundTrip() {
        try {
            // First create a Material Master
            $initialData = $this->generateMaterialMasterData();
            
            $createResult = $this->materialMasterService->create(
                $initialData,
                $this->testUserId,
                $this->testCompanyId
            );
            
            $this->assert($createResult['success'], "Initial Material Master creation failed");
            
            $masterId = $createResult['data']['id'];
            $this->createdRecords['material_masters'][] = $masterId;
            
            // Generate new data for update
            $updateData = $this->generateMaterialMasterData();
            
            // Update Material Master
            $updateResult = $this->materialMasterService->update($masterId, $updateData, $this->testCompanyId);
            
            $this->assert($updateResult['success'], "Material Master update failed: " . ($updateResult['message'] ?? 'Unknown error'));
            
            // Retrieve updated Material Master
            $retrieved = $this->materialMasterService->getById($masterId, $this->testCompanyId);
            
            $this->assert($retrieved !== null, "Material Master retrieval after update failed");
            
            // Verify update round-trip consistency
            $this->assert(
                $retrieved['name'] === $updateData['name'],
                "Updated name mismatch: expected '{$updateData['name']}', got '{$retrieved['name']}'"
            );
            
            $expectedDesc = $updateData['description'] ?? null;
            $this->assert(
                $retrieved['description'] === $expectedDesc,
                "Updated description mismatch: expected '$expectedDesc', got '{$retrieved['description']}'"
            );
            
            // Verify items were replaced
            $this->assert(
                count($retrieved['items']) === count($updateData['items']),
                "Updated item count mismatch: expected " . count($updateData['items']) . ", got " . count($retrieved['items'])
            );
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => $updateData ?? null
            ];
        }
    }
    
    /**
     * Generate random Material Master data
     */
    private function generateMaterialMasterData() {
        // Ensure we have products
        if (empty($this->testProductIds)) {
            throw new Exception("No test products available");
        }
        
        // Generate 1-3 random items
        $itemCount = rand(1, min(3, count($this->testProductIds)));
        
        // Shuffle and take first N products
        $shuffled = $this->testProductIds;
        shuffle($shuffled);
        $selectedProducts = array_slice($shuffled, 0, $itemCount);
        
        $items = [];
        foreach ($selectedProducts as $productId) {
            $items[] = [
                'product_id' => $productId,
                'quantity' => rand(1, 100)
            ];
        }
        
        return [
            'name' => 'Test Master ' . $this->generateRandomString(10),
            'description' => $this->generateRandomBool() ? 'Description ' . $this->generateRandomString(20) : null,
            'status' => $this->generateRandomChoice(['active', 'inactive']),
            'items' => $items
        ];
    }
    
    /**
     * Setup test data (company, user, products)
     */
    private function setupTestData() {
        try {
            // Get or create test company
            $result = $this->getResults("SELECT id FROM companies WHERE type = 'ADV' LIMIT 1");
            if (!empty($result)) {
                $this->testCompanyId = (int)$result[0]['id'];
            } else {
                // Create test company
                $stmt = $this->executeQuery(
                    "INSERT INTO companies (name, type, status) VALUES (?, ?, ?)",
                    ['Test Company', 'ADV', 'ACTIVE'],
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
                // Create test user
                $stmt = $this->executeQuery(
                    "INSERT INTO users (name, email, password, company_id, status) VALUES (?, ?, ?, ?, ?)",
                    ['Test User', 'test_' . $this->generateRandomString(5) . '@test.com', password_hash('test123', PASSWORD_DEFAULT), $this->testCompanyId, 'ACTIVE'],
                    'sssis'
                );
                $this->testUserId = $this->db->insert_id;
                $stmt->close();
                $this->createdRecords['users'][] = $this->testUserId;
            }
            
            // Get existing products or create test products
            $result = $this->getResults("SELECT id FROM products LIMIT 5");
            if (!empty($result)) {
                foreach ($result as $row) {
                    $this->testProductIds[] = (int)$row['id'];
                }
            }
            
            // If not enough products, create some
            while (count($this->testProductIds) < 3) {
                $stmt = $this->executeQuery(
                    "INSERT INTO products (name, unit_of_measure, is_serializable, is_repairable, inventory_type, status) VALUES (?, ?, ?, ?, ?, ?)",
                    ['Test Product ' . $this->generateRandomString(5), 'unit', 0, 0, 'consumable', 'active'],
                    'ssiiss'
                );
                $productId = $this->db->insert_id;
                $stmt->close();
                $this->testProductIds[] = $productId;
                $this->createdRecords['products'][] = $productId;
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
            // Delete material master items first (foreign key constraint)
            if (isset($this->createdRecords['material_masters']) && !empty($this->createdRecords['material_masters'])) {
                $ids = implode(',', array_map('intval', $this->createdRecords['material_masters']));
                $this->db->query("DELETE FROM material_master_items WHERE material_master_id IN ($ids)");
                $this->db->query("DELETE FROM material_masters WHERE id IN ($ids)");
            }
            
            // Also clean up any test records by name pattern
            $this->db->query("DELETE FROM material_master_items WHERE material_master_id IN (SELECT id FROM material_masters WHERE name LIKE 'Test Master %')");
            $this->db->query("DELETE FROM material_masters WHERE name LIKE 'Test Master %'");
            
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
