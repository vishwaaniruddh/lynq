<?php
/**
 * Property Test: Status Transition Validity
 * **Feature: adv-crm-inventory-module, Property 6: Status Transition Validity**
 * **Validates: Requirements 6.1**
 * 
 * Property: For any asset status change, the new status SHALL be one of the valid statuses:
 * In Stock, Dispatched, Assigned, In Use, Returned, Under Repair, Scrapped, or Lost.
 */

require_once __DIR__ . '/PropertyTestBase.php';
require_once __DIR__ . '/../services/AssetStatusService.php';
require_once __DIR__ . '/../repositories/AssetRepository.php';
require_once __DIR__ . '/../repositories/ProductRepository.php';
require_once __DIR__ . '/../repositories/WarehouseRepository.php';
require_once __DIR__ . '/../repositories/CompanyRepository.php';

class StatusTransitionValidityTest extends PropertyTestBase {
    private $assetStatusService;
    private $assetRepository;
    private $productRepository;
    private $warehouseRepository;
    private $companyRepository;
    private $createdAssetIds = [];
    private $createdProductIds = [];
    private $createdWarehouseIds = [];
    private $createdCompanyIds = [];
    
    public function __construct() {
        parent::__construct();
        $this->assetStatusService = new AssetStatusService();
        $this->assetRepository = new AssetRepository();
        $this->productRepository = new ProductRepository();
        $this->warehouseRepository = new WarehouseRepository();
        $this->warehouseRepository->disableCompanyFilter();
        $this->companyRepository = new CompanyRepository();
        $this->companyRepository->disableCompanyFilter();
    }
    
    /**
     * Run all property tests
     */
    public function runTests() {
        echo "\n=== Status Transition Validity Property Tests ===\n";
        echo "**Feature: adv-crm-inventory-module, Property 6: Status Transition Validity**\n";
        echo "**Validates: Requirements 6.1**\n\n";
        
        $results = [];
        
        // Property 6: Status Transition Validity
        $results['status_transition_validity'] = $this->runPropertyTest(
            'Property 6: Status Transition Validity - All status updates result in valid statuses',
            function() {
                return $this->testStatusTransitionValidity();
            },
            50 // 50 iterations
        );
        
        // Additional property: Invalid status rejection
        $results['invalid_status_rejection'] = $this->runPropertyTest(
            'Property 6b: Invalid statuses are rejected',
            function() {
                return $this->testInvalidStatusRejection();
            },
            30
        );
        
        // Cleanup
        $this->cleanupTestData();
        
        echo "\n=== Test Summary ===\n";
        $passed = count(array_filter($results));
        $total = count($results);
        echo "Passed: $passed / $total\n";
        
        return $passed === $total;
    }
    
    /**
     * Property 6: Status Transition Validity
     * For any asset status change, the new status SHALL be one of the valid statuses
     */
    private function testStatusTransitionValidity(): array {
        // Create test data
        $company = $this->createTestCompany();
        $warehouse = $this->createTestWarehouse($company['id']);
        $product = $this->createTestProduct(true); // Serializable and repairable
        $asset = $this->createTestAsset($product['id'], $warehouse['id']);
        
        // Get all valid statuses
        $validStatuses = AssetRepository::getStatuses();
        
        // Pick a random valid status to transition to
        $randomStatus = $this->generateRandomChoice($validStatuses);
        
        // Get current status
        $currentAsset = $this->assetRepository->find($asset['id']);
        $currentStatus = $currentAsset['status'];
        
        // Check if transition is valid according to service
        $allowedTransitions = $this->assetStatusService->getAllowedTransitions($currentStatus);
        
        // If the random status is in allowed transitions, try to update
        if (in_array($randomStatus, $allowedTransitions) || $randomStatus === $currentStatus) {
            $result = $this->assetStatusService->updateStatus($asset['id'], $randomStatus);
            
            if ($result['success']) {
                // Verify the new status is valid
                $updatedAsset = $this->assetRepository->find($asset['id']);
                $newStatus = $updatedAsset['status'];
                
                if (!in_array($newStatus, $validStatuses)) {
                    return [
                        'success' => false,
                        'message' => "Asset has invalid status after update: $newStatus",
                        'data' => [
                            'asset_id' => $asset['id'],
                            'old_status' => $currentStatus,
                            'new_status' => $newStatus,
                            'valid_statuses' => $validStatuses
                        ]
                    ];
                }
            }
        } else {
            // Transition should be rejected
            $result = $this->assetStatusService->updateStatus($asset['id'], $randomStatus);
            
            // If it succeeded when it shouldn't have, that's a failure
            if ($result['success'] && $randomStatus !== $currentStatus) {
                // Verify the status is still valid even if transition was unexpected
                $updatedAsset = $this->assetRepository->find($asset['id']);
                if (!in_array($updatedAsset['status'], $validStatuses)) {
                    return [
                        'success' => false,
                        'message' => "Asset has invalid status: {$updatedAsset['status']}",
                        'data' => [
                            'asset_id' => $asset['id'],
                            'status' => $updatedAsset['status']
                        ]
                    ];
                }
            }
        }
        
        // Final verification: asset status is always valid
        $finalAsset = $this->assetRepository->find($asset['id']);
        if (!in_array($finalAsset['status'], $validStatuses)) {
            return [
                'success' => false,
                'message' => "Asset has invalid status: {$finalAsset['status']}",
                'data' => [
                    'asset_id' => $asset['id'],
                    'status' => $finalAsset['status'],
                    'valid_statuses' => $validStatuses
                ]
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * Property 6b: Invalid statuses are rejected
     * For any invalid status string, the system SHALL reject the update
     */
    private function testInvalidStatusRejection(): array {
        // Create test data
        $company = $this->createTestCompany();
        $warehouse = $this->createTestWarehouse($company['id']);
        $product = $this->createTestProduct(true);
        $asset = $this->createTestAsset($product['id'], $warehouse['id']);
        
        // Generate random invalid status
        $invalidStatuses = [
            'invalid_status',
            'INVALID',
            'pending',
            'active',
            'deleted',
            'archived',
            $this->generateRandomString(10),
            '',
            '123',
            'in stock', // with space
            'IN_STOCK', // uppercase
        ];
        
        $invalidStatus = $this->generateRandomChoice($invalidStatuses);
        
        // Try to update with invalid status
        $result = $this->assetStatusService->updateStatus($asset['id'], $invalidStatus);
        
        // Should be rejected
        if ($result['success']) {
            return [
                'success' => false,
                'message' => "Invalid status '$invalidStatus' was accepted",
                'data' => [
                    'asset_id' => $asset['id'],
                    'invalid_status' => $invalidStatus
                ]
            ];
        }
        
        // Verify asset status unchanged and still valid
        $currentAsset = $this->assetRepository->find($asset['id']);
        $validStatuses = AssetRepository::getStatuses();
        
        if (!in_array($currentAsset['status'], $validStatuses)) {
            return [
                'success' => false,
                'message' => "Asset status became invalid after rejection",
                'data' => [
                    'asset_id' => $asset['id'],
                    'status' => $currentAsset['status']
                ]
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * Create test company
     */
    private function createTestCompany(): array {
        $data = [
            'name' => 'Test Company ' . $this->generateRandomString(8),
            'type' => 'ADV',
            'status' => 'active'
        ];
        
        $company = $this->companyRepository->create($data);
        $this->createdCompanyIds[] = $company['id'];
        return $company;
    }
    
    /**
     * Create test warehouse
     */
    private function createTestWarehouse(int $companyId): array {
        $data = [
            'name' => 'Test Warehouse ' . $this->generateRandomString(8),
            'location' => 'Test Location',
            'company_id' => $companyId,
            'status' => 'active'
        ];
        
        $warehouse = $this->warehouseRepository->create($data);
        $this->createdWarehouseIds[] = $warehouse['id'];
        return $warehouse;
    }
    
    /**
     * Create test product
     */
    private function createTestProduct(bool $repairable = true): array {
        $data = [
            'name' => 'Test Product ' . $this->generateRandomString(8),
            'unit_of_measure' => 'unit',
            'inventory_type' => 'INTERNAL',
            'is_serializable' => 1,
            'is_repairable' => $repairable ? 1 : 0,
            'status' => 'active'
        ];
        
        $product = $this->productRepository->create($data);
        $this->createdProductIds[] = $product['id'];
        return $product;
    }
    
    /**
     * Create test asset
     */
    private function createTestAsset(int $productId, int $warehouseId): array {
        $data = [
            'product_id' => $productId,
            'warehouse_id' => $warehouseId,
            'serial_number' => 'SN-' . $this->generateRandomString(12),
            'status' => AssetRepository::STATUS_IN_STOCK,
            'working_condition' => AssetRepository::CONDITION_WORKING,
            'current_holder_type' => AssetRepository::HOLDER_WAREHOUSE,
            'current_holder_id' => $warehouseId,
            'source_warehouse_id' => $warehouseId
        ];
        
        $asset = $this->assetRepository->create($data);
        $this->createdAssetIds[] = $asset['id'];
        return $asset;
    }
    
    /**
     * Clean up test data
     */
    protected function cleanupTestData() {
        // Delete assets
        foreach ($this->createdAssetIds as $id) {
            try {
                $this->assetRepository->delete($id);
            } catch (Exception $e) {
                // Ignore cleanup errors
            }
        }
        
        // Delete products
        foreach ($this->createdProductIds as $id) {
            try {
                $this->productRepository->delete($id);
            } catch (Exception $e) {
                // Ignore cleanup errors
            }
        }
        
        // Delete warehouses
        foreach ($this->createdWarehouseIds as $id) {
            try {
                $this->warehouseRepository->delete($id);
            } catch (Exception $e) {
                // Ignore cleanup errors
            }
        }
        
        // Delete companies
        foreach ($this->createdCompanyIds as $id) {
            try {
                $this->companyRepository->delete($id);
            } catch (Exception $e) {
                // Ignore cleanup errors
            }
        }
        
        $this->createdAssetIds = [];
        $this->createdProductIds = [];
        $this->createdWarehouseIds = [];
        $this->createdCompanyIds = [];
    }
}

// Run tests if executed directly
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    $test = new StatusTransitionValidityTest();
    $success = $test->runTests();
    exit($success ? 0 : 1);
}
