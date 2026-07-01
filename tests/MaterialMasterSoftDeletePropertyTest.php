<?php
/**
 * Property Test for Material Master Soft-Delete Exclusion
 * **Feature: material-request-module, Property 3: Material Master Soft-Delete Exclusion**
 * **Validates: Requirements 1.6, 9.4**
 * 
 * For any Material Master that has been deleted via API, it should not appear in active 
 * selection lists but should remain in the database with a non-null deleted_at timestamp.
 */

require_once __DIR__ . '/PropertyTestBase.php';
require_once __DIR__ . '/../services/MaterialMasterService.php';
require_once __DIR__ . '/../repositories/MaterialMasterRepository.php';

class MaterialMasterSoftDeletePropertyTest extends PropertyTestBase {
    
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
        echo "=== Material Master Soft-Delete Property Tests ===\n\n";
        
        // Setup test data
        $this->setupTestData();
        
        $allPassed = true;
        
        // Test Soft-Delete Exclusion
        $allPassed &= $this->runPropertyTest(
            "Material Master Soft-Delete Exclusion",
            [$this, 'testMaterialMasterSoftDeleteExclusion']
        );
        
        // Cleanup test data
        $this->cleanupTestData();
        
        return $allPassed;
    }
    
    /**
     * Property 3: Material Master Soft-Delete Exclusion
     * For any deleted Material Master, it should not appear in active selection lists
     * but should remain in the database with a non-null deleted_at timestamp.
     * **Feature: material-request-module, Property 3: Material Master Soft-Delete Exclusion**
     * **Validates: Requirements 1.6, 9.4**
     */
    public function testMaterialMasterSoftDeleteExclusion() {
        try {
            // Generate and create a Material Master
            $masterData = $this->generateMaterialMasterData();
            
            $createResult = $this->materialMasterService->create(
                $masterData,
                $this->testUserId,
                $this->testCompanyId
            );
            
            $this->assert($createResult['success'], "Material Master creation failed: " . ($createResult['message'] ?? 'Unknown error'));
            
            $masterId = $createResult['data']['id'];
            $this->createdRecords['material_masters'][] = $masterId;
            
            // Verify it appears in active selection list before deletion
            $activeListBefore = $this->materialMasterService->getActiveForSelection($this->testCompanyId);
            $foundBefore = false;
            foreach ($activeListBefore as $master) {
                if ((int)$master['id'] === $masterId) {
                    $foundBefore = true;
                    break;
                }
            }
            $this->assert($foundBefore, "Material Master should appear in active list before deletion");
            
            // Delete the Material Master
            $deleteResult = $this->materialMasterService->delete($masterId, $this->testCompanyId);
            $this->assert($deleteResult['success'], "Material Master deletion failed: " . ($deleteResult['message'] ?? 'Unknown error'));
            
            // Verify it does NOT appear in active selection list after deletion
            $activeListAfter = $this->materialMasterService->getActiveForSelection($this->testCompanyId);
            $foundAfter = false;
            foreach ($activeListAfter as $master) {
                if ((int)$master['id'] === $masterId) {
                    $foundAfter = true;
                    break;
                }
            }
            $this->assert(!$foundAfter, "Deleted Material Master should NOT appear in active selection list");
            
            // Verify it does NOT appear in getById (normal retrieval)
            $retrieved = $this->materialMasterService->getById($masterId, $this->testCompanyId);
            $this->assert($retrieved === null, "Deleted Material Master should NOT be retrievable via getById");
            
            // Verify it still exists in database with deleted_at timestamp
            $sql = "SELECT id, deleted_at FROM material_masters WHERE id = ?";
            $result = $this->getResults($sql, [$masterId], 'i');
            
            $this->assert(!empty($result), "Deleted Material Master should still exist in database");
            $this->assert($result[0]['deleted_at'] !== null, "Deleted Material Master should have non-null deleted_at timestamp");
            
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
            'status' => 'active', // Must be active to appear in selection list
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
