<?php
/**
 * Property Test for Engineer Receipt Authorization
 * **Feature: material-request-module, Property 8: Engineer Receipt Authorization**
 * **Validates: Requirements 7.3, 7.4**
 */

require_once __DIR__ . '/PropertyTestBase.php';
require_once __DIR__ . '/../services/MaterialRequestService.php';
require_once __DIR__ . '/../services/MaterialMasterService.php';
require_once __DIR__ . '/../repositories/MaterialRequestRepository.php';

class EngineerReceiptAuthorizationPropertyTest extends PropertyTestBase {
    private $materialRequestService;
    private $materialMasterService;
    private $createdRecords = [];
    private $testCompanyId;
    private $testContractorCompanyId;
    private $testAdvUserId;
    private $testEngineerUserId;
    private $testOtherEngineerUserId;
    private $testProductIds = [];
    private $testMasterIds = [];
    
    public function __construct() {
        parent::__construct();
        $this->materialRequestService = new MaterialRequestService();
        $this->materialMasterService = new MaterialMasterService();
        $this->iterations = 20;
    }
    
    public function runTests() {
        echo "=== Engineer Receipt Authorization Property Tests ===\n\n";
        $this->setupTestData();
        $allPassed = true;
        $allPassed = $allPassed && $this->runPropertyTest("Assigned Engineer Can Confirm Receipt", [$this, 'testAssignedEngineerCanConfirmReceipt']);
        $allPassed = $allPassed && $this->runPropertyTest("Non-Assigned Engineer Cannot Confirm Receipt", [$this, 'testNonAssignedEngineerCannotConfirmReceipt']);
        $allPassed = $allPassed && $this->runPropertyTest("Only Dispatched Requests Can Be Confirmed", [$this, 'testOnlyDispatchedRequestsCanBeConfirmed']);
        $this->cleanupTestData();
        return $allPassed;
    }
    
    public function testAssignedEngineerCanConfirmReceipt() {
        try {
            $siteId = $this->createTestSite();
            $this->createdRecords['sites'][] = $siteId;
            $this->createEngineerAssignment($siteId, $this->testEngineerUserId);
            $masterId = $this->testMasterIds[array_rand($this->testMasterIds)];
            $createResult = $this->materialRequestService->create($siteId, $masterId, $this->testAdvUserId, $this->testCompanyId);
            $this->assert($createResult['success'], "Failed to create material request");
            $requestId = $createResult['data']['id'];
            $this->createdRecords['material_requests'][] = $requestId;
            $this->materialRequestService->updateStatus($requestId, MaterialRequestRepository::STATUS_APPROVED, $this->testAdvUserId, $this->testCompanyId);
            $this->materialRequestService->updateStatus($requestId, MaterialRequestRepository::STATUS_DISPATCHED, $this->testAdvUserId, $this->testCompanyId);
            $receiptResult = $this->materialRequestService->confirmReceipt($requestId, $this->testEngineerUserId);
            $this->assert($receiptResult['success'], "Assigned engineer should be able to confirm receipt");
            $updatedRequest = $this->materialRequestService->getById($requestId);
            $this->assert($updatedRequest['status'] === MaterialRequestRepository::STATUS_RECEIVED, "Request status should be 'received'");
            return ['success' => true];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    public function testNonAssignedEngineerCannotConfirmReceipt() {
        try {
            $siteId = $this->createTestSite();
            $this->createdRecords['sites'][] = $siteId;
            $this->createEngineerAssignment($siteId, $this->testEngineerUserId);
            $masterId = $this->testMasterIds[array_rand($this->testMasterIds)];
            $createResult = $this->materialRequestService->create($siteId, $masterId, $this->testAdvUserId, $this->testCompanyId);
            $this->assert($createResult['success'], "Failed to create material request");
            $requestId = $createResult['data']['id'];
            $this->createdRecords['material_requests'][] = $requestId;
            $this->materialRequestService->updateStatus($requestId, MaterialRequestRepository::STATUS_APPROVED, $this->testAdvUserId, $this->testCompanyId);
            $this->materialRequestService->updateStatus($requestId, MaterialRequestRepository::STATUS_DISPATCHED, $this->testAdvUserId, $this->testCompanyId);
            $receiptResult = $this->materialRequestService->confirmReceipt($requestId, $this->testOtherEngineerUserId);
            $this->assert(!$receiptResult['success'], "Non-assigned engineer should NOT be able to confirm receipt");
            $this->assert($receiptResult['code'] === 'UNAUTHORIZED', "Error code should be 'UNAUTHORIZED'");
            return ['success' => true];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    public function testOnlyDispatchedRequestsCanBeConfirmed() {
        try {
            $siteId = $this->createTestSite();
            $this->createdRecords['sites'][] = $siteId;
            $this->createEngineerAssignment($siteId, $this->testEngineerUserId);
            $masterId = $this->testMasterIds[array_rand($this->testMasterIds)];
            $createResult = $this->materialRequestService->create($siteId, $masterId, $this->testAdvUserId, $this->testCompanyId);
            $this->assert($createResult['success'], "Failed to create material request");
            $requestId = $createResult['data']['id'];
            $this->createdRecords['material_requests'][] = $requestId;
            $receiptResult = $this->materialRequestService->confirmReceipt($requestId, $this->testEngineerUserId);
            $this->assert(!$receiptResult['success'], "Should not be able to confirm receipt for 'requested' status");
            $this->assert($receiptResult['code'] === 'INVALID_STATUS', "Error code should be 'INVALID_STATUS'");
            return ['success' => true];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    private function createTestSite() {
        $siteName = 'Test Site ' . $this->generateRandomString(10);
        $stmt = $this->executeQuery("INSERT INTO sites (site_name, company_id, status, created_at) VALUES (?, ?, ?, NOW())", [$siteName, $this->testCompanyId, 'active'], 'sis');
        $siteId = $this->db->insert_id;
        $stmt->close();
        return $siteId;
    }
    
    private function createEngineerAssignment($siteId, $engineerId) {
        $stmt = $this->executeQuery("INSERT INTO engineer_assignments (site_id, engineer_id, status, assigned_at) VALUES (?, ?, ?, NOW())", [$siteId, $engineerId, 'assigned'], 'iis');
        $assignmentId = $this->db->insert_id;
        $stmt->close();
        $this->createdRecords['engineer_assignments'][] = $assignmentId;
    }
    
    private function setupTestData() {
        try {
            $result = $this->getResults("SELECT id FROM companies WHERE type = 'ADV' LIMIT 1");
            $this->testCompanyId = !empty($result) ? (int)$result[0]['id'] : $this->createCompany('Test ADV Company', 'ADV');
            $result = $this->getResults("SELECT id FROM companies WHERE type = 'CONTRACTOR' LIMIT 1");
            $this->testContractorCompanyId = !empty($result) ? (int)$result[0]['id'] : $this->createCompany('Test Contractor Company', 'CONTRACTOR');
            $result = $this->getResults("SELECT id FROM users WHERE company_id = ? LIMIT 1", [$this->testCompanyId], 'i');
            $this->testAdvUserId = !empty($result) ? (int)$result[0]['id'] : $this->createUser('test_adv_', $this->testCompanyId, 1);
            $this->testEngineerUserId = $this->createUser('test_engineer_', $this->testContractorCompanyId, 3);
            $this->testOtherEngineerUserId = $this->createUser('test_other_eng_', $this->testContractorCompanyId, 3);
            $result = $this->getResults("SELECT id FROM products LIMIT 5");
            foreach ($result as $row) { $this->testProductIds[] = (int)$row['id']; }
            while (count($this->testProductIds) < 3) { $this->testProductIds[] = $this->createProduct(); }
            for ($i = 0; $i < 3; $i++) { $this->createMaterialMaster(); }
            if (empty($this->testMasterIds)) { throw new Exception("Failed to create test Material Masters"); }
        } catch (Exception $e) { echo "Setup warning: " . $e->getMessage() . "\n"; }
    }
    
    private function createCompany($name, $type) {
        $stmt = $this->executeQuery("INSERT INTO companies (name, type, status) VALUES (?, ?, ?)", [$name, $type, 'ACTIVE'], 'sss');
        $id = $this->db->insert_id;
        $stmt->close();
        $this->createdRecords['companies'][] = $id;
        return $id;
    }
    
    private function createUser($prefix, $companyId, $roleId) {
        $stmt = $this->executeQuery("INSERT INTO users (username, email, password_hash, first_name, last_name, company_id, role_id, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
            [$prefix . $this->generateRandomString(5), $prefix . $this->generateRandomString(5) . '@test.com', password_hash('test123', PASSWORD_DEFAULT), 'Test', 'User', $companyId, $roleId, 1], 'sssssiii');
        $id = $this->db->insert_id;
        $stmt->close();
        $this->createdRecords['users'][] = $id;
        return $id;
    }
    
    private function createProduct() {
        $stmt = $this->executeQuery("INSERT INTO products (name, unit_of_measure, is_serializable, is_repairable, inventory_type, status) VALUES (?, ?, ?, ?, ?, ?)",
            ['Test Product ' . $this->generateRandomString(5), 'unit', 0, 0, 'consumable', 'active'], 'ssiiss');
        $id = $this->db->insert_id;
        $stmt->close();
        $this->createdRecords['products'][] = $id;
        return $id;
    }
    
    private function createMaterialMaster() {
        $masterData = ['name' => 'Test Master ' . $this->generateRandomString(10), 'description' => 'Test description', 'status' => 'active', 'items' => [['product_id' => $this->testProductIds[0], 'quantity' => rand(1, 10)]]];
        $result = $this->materialMasterService->create($masterData, $this->testAdvUserId, $this->testCompanyId);
        if ($result['success']) { $this->testMasterIds[] = $result['data']['id']; $this->createdRecords['material_masters'][] = $result['data']['id']; }
    }
    
    public function cleanupTestData() {
        try {
            if (!empty($this->createdRecords['engineer_assignments'])) {
                $ids = implode(',', array_map('intval', $this->createdRecords['engineer_assignments']));
                $this->db->query("DELETE FROM engineer_assignments WHERE id IN ($ids)");
            }
            if (!empty($this->createdRecords['material_requests'])) {
                $ids = implode(',', array_map('intval', $this->createdRecords['material_requests']));
                $this->db->query("DELETE FROM material_request_items WHERE material_request_id IN ($ids)");
                $this->db->query("DELETE FROM material_requests WHERE id IN ($ids)");
            }
            if (!empty($this->createdRecords['material_masters'])) {
                $ids = implode(',', array_map('intval', $this->createdRecords['material_masters']));
                $this->db->query("DELETE FROM material_master_items WHERE material_master_id IN ($ids)");
                $this->db->query("DELETE FROM material_masters WHERE id IN ($ids)");
            }
            if (!empty($this->createdRecords['sites'])) {
                $ids = implode(',', array_map('intval', $this->createdRecords['sites']));
                $this->db->query("DELETE FROM sites WHERE id IN ($ids)");
            }
            $this->db->query("DELETE FROM sites WHERE site_name LIKE 'Test Site %'");
            $this->db->query("DELETE FROM material_masters WHERE name LIKE 'Test Master %'");
            if (!empty($this->createdRecords['products'])) {
                $ids = implode(',', array_map('intval', $this->createdRecords['products']));
                $this->db->query("DELETE FROM products WHERE id IN ($ids)");
            }
            if (!empty($this->createdRecords['users'])) {
                $ids = implode(',', array_map('intval', $this->createdRecords['users']));
                $this->db->query("DELETE FROM users WHERE id IN ($ids)");
            }
            if (!empty($this->createdRecords['companies'])) {
                $ids = implode(',', array_map('intval', $this->createdRecords['companies']));
                $this->db->query("DELETE FROM companies WHERE id IN ($ids)");
            }
            $this->createdRecords = [];
        } catch (Exception $e) { echo "Cleanup warning: " . $e->getMessage() . "\n"; }
    }
}
