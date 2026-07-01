<?php
/**
 * Courier Export Filter Consistency Property Test
 * 
 * **Feature: crm-sidebar-restructure, Property 11: Courier Export Filter Consistency**
 * **Validates: Requirements 2.6**
 * 
 * Property: For any export operation on couriers, the exported data should match 
 * the currently applied filters and search criteria.
 */

require_once __DIR__ . '/PropertyTestBase.php';
require_once __DIR__ . '/../services/CourierService.php';

class CourierExportFilterConsistencyTest extends PropertyTestBase {
    private $courierService;
    private $createdCouriers = [];
    
    public function __construct() {
        parent::__construct();
        $this->courierService = new CourierService();
    }
    
    /**
     * Run all courier export filter consistency property tests
     */
    public function runAllTests() {
        echo "\n=== Courier Export Filter Consistency Property Tests ===\n";
        echo "**Feature: crm-sidebar-restructure, Property 11: Courier Export Filter Consistency**\n";
        echo "**Validates: Requirements 2.6**\n\n";
        
        $results = [];
        
        // Setup test data
        $this->setupTestData();
        
        try {
            // Test 1: Courier export with status filter
            $results['courier_export_status'] = $this->runPropertyTest(
                'Courier Export Status Filter Consistency',
                function() { return $this->testCourierExportStatusFilter(); },
                100
            );
            
            // Test 2: Courier export with search filter
            $results['courier_export_search'] = $this->runPropertyTest(
                'Courier Export Search Filter Consistency',
                function() { return $this->testCourierExportSearchFilter(); },
                100
            );
            
            // Test 3: Courier export with combined filters
            $results['courier_export_combined'] = $this->runPropertyTest(
                'Courier Export Combined Filter Consistency',
                function() { return $this->testCourierExportCombinedFilters(); },
                100
            );
            
            // Test 4: Courier export returns all matching records (no pagination)
            $results['courier_export_no_pagination'] = $this->runPropertyTest(
                'Courier Export Returns All Matching Records',
                function() { return $this->testCourierExportNoPagination(); },
                50
            );
            
        } finally {
            $this->cleanupTestData();
        }
        
        // Summary
        echo "\n=== Test Summary ===\n";
        $passed = count(array_filter($results));
        $total = count($results);
        echo "Passed: $passed / $total\n";
        
        return $passed === $total;
    }
    
    /**
     * Setup test data for export filter tests
     */
    private function setupTestData() {
        echo "Setting up test data...\n";
        
        // Create couriers with varied status and names
        $testNames = [
            'ExportTestCourier_Alpha_' . $this->generateRandomString(4),
            'ExportTestCourier_Beta_' . $this->generateRandomString(4),
            'ExportTestCourier_Gamma_' . $this->generateRandomString(4),
            'ExportTestCourier_Delta_' . $this->generateRandomString(4),
            'DifferentPrefix_' . $this->generateRandomString(4),
            'AnotherPrefix_' . $this->generateRandomString(4),
        ];
        
        foreach ($testNames as $i => $name) {
            $status = $i % 2 === 0 ? 1 : 0; // Alternate between active and inactive
            $result = $this->courierService->create([
                'name' => $name,
                'status' => $status
            ]);
            if ($result['success']) {
                $this->createdCouriers[] = $result['data'];
            }
        }
        
        echo "Test data created: " . count($this->createdCouriers) . " couriers\n\n";
    }

    /**
     * Test: Courier export with status filter returns only matching records
     * 
     * Property: For any status filter value, all exported couriers should have that status
     */
    private function testCourierExportStatusFilter() {
        $status = $this->generateRandomChoice([0, 1]);
        
        $exportedCouriers = $this->courierService->export(['status' => $status]);
        
        foreach ($exportedCouriers as $courier) {
            if ((int)$courier['status'] !== $status) {
                return [
                    'success' => false,
                    'message' => "Exported courier with status '{$courier['status']}' when filtering for '$status'",
                    'data' => ['courier' => $courier, 'expected_status' => $status]
                ];
            }
        }
        
        return ['success' => true];
    }
    
    /**
     * Test: Courier export with search filter returns only matching records
     * 
     * Property: For any search term, all exported couriers should contain that term in their name
     */
    private function testCourierExportSearchFilter() {
        if (empty($this->createdCouriers)) {
            return ['success' => true];
        }
        
        // Use search terms that should match our test data
        $searchTerms = ['ExportTestCourier', 'Alpha', 'Beta', 'Gamma', 'Delta', 'DifferentPrefix'];
        $searchTerm = $this->generateRandomChoice($searchTerms);
        
        $exportedCouriers = $this->courierService->export(['search' => $searchTerm]);
        
        foreach ($exportedCouriers as $courier) {
            if (stripos($courier['name'], $searchTerm) === false) {
                return [
                    'success' => false,
                    'message' => "Exported courier '{$courier['name']}' does not contain search term '$searchTerm'",
                    'data' => ['courier' => $courier, 'search_term' => $searchTerm]
                ];
            }
        }
        
        return ['success' => true];
    }
    
    /**
     * Test: Courier export with combined filters returns only matching records
     * 
     * Property: For any combination of status and search filters, all exported couriers 
     * should satisfy both conditions
     */
    private function testCourierExportCombinedFilters() {
        if (empty($this->createdCouriers)) {
            return ['success' => true];
        }
        
        $status = $this->generateRandomChoice([0, 1]);
        $searchTerm = 'ExportTestCourier';
        
        $exportedCouriers = $this->courierService->export([
            'status' => $status,
            'search' => $searchTerm
        ]);
        
        foreach ($exportedCouriers as $courier) {
            // Check status filter
            if ((int)$courier['status'] !== $status) {
                return [
                    'success' => false,
                    'message' => "Exported courier with status '{$courier['status']}' when filtering for '$status'",
                    'data' => ['courier' => $courier, 'expected_status' => $status, 'search_term' => $searchTerm]
                ];
            }
            
            // Check search filter
            if (stripos($courier['name'], $searchTerm) === false) {
                return [
                    'success' => false,
                    'message' => "Exported courier '{$courier['name']}' does not contain search term '$searchTerm'",
                    'data' => ['courier' => $courier, 'expected_status' => $status, 'search_term' => $searchTerm]
                ];
            }
        }
        
        return ['success' => true];
    }
    
    /**
     * Test: Courier export returns all matching records without pagination
     * 
     * Property: Export should return all records matching the filter, not just a page
     */
    private function testCourierExportNoPagination() {
        // Get count of all couriers with a specific filter
        $searchTerm = 'ExportTestCourier';
        
        // Get paginated results to count total
        $paginatedResult = $this->courierService->getAll([
            'search' => $searchTerm,
            'page' => 1,
            'limit' => 1000 // Large limit to get all
        ]);
        
        $expectedTotal = $paginatedResult['total'];
        
        // Get export results
        $exportedCouriers = $this->courierService->export(['search' => $searchTerm]);
        $exportedCount = count($exportedCouriers);
        
        // Export should return at least as many records as the total count
        // (could be more if new records were added between calls)
        if ($exportedCount < $expectedTotal) {
            return [
                'success' => false,
                'message' => "Export returned $exportedCount records but expected at least $expectedTotal",
                'data' => ['exported_count' => $exportedCount, 'expected_total' => $expectedTotal]
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * Clean up test data
     */
    protected function cleanupTestData() {
        echo "\nCleaning up test data...\n";
        
        foreach ($this->createdCouriers as $courier) {
            try {
                // Hard delete for cleanup (direct SQL since soft delete just changes status)
                $sql = "DELETE FROM couriers WHERE id = ?";
                $this->executeQuery($sql, [$courier['id']], 'i');
            } catch (Exception $e) {
                // Ignore cleanup errors
            }
        }
        
        echo "Cleanup complete.\n";
    }
}

// Run tests if executed directly
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['PHP_SELF'])) {
    $test = new CourierExportFilterConsistencyTest();
    $success = $test->runAllTests();
    exit($success ? 0 : 1);
}
