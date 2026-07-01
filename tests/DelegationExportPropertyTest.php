<?php
/**
 * Property Test for Delegation Export
 * **Feature: site-management-delegation, Property 7: Delegation export round-trip**
 * **Validates: Requirements 3.4**
 * 
 * Property 7: For any set of delegation records, exporting to Excel and then parsing 
 * the Excel file should produce data equivalent to the original delegation records.
 */

require_once 'PropertyTestBase.php';
require_once __DIR__ . '/../services/DelegationService.php';
require_once __DIR__ . '/../services/SiteService.php';
require_once __DIR__ . '/../services/BulkOperationService.php';
require_once __DIR__ . '/../repositories/DelegationRepository.php';
require_once __DIR__ . '/../repositories/SiteRepository.php';

class DelegationExportPropertyTest extends PropertyTestBase {
    
    private $delegationService;
    private $siteService;
    private $bulkOperationService;
    private $delegationRepository;
    private $siteRepository;
    private $testCompanyId;
    private $testContractorId;
    private $testUserId;
    private $createdSiteIds = [];
    private $createdDelegationIds = [];
    private $createdExportFiles = [];
    
    public function __construct() {
        parent::__construct();
        $this->delegationService = new DelegationService();
        $this->siteService = new SiteService();
        $this->bulkOperationService = new BulkOperationService();
        $this->delegationRepository = new DelegationRepository();
        $this->siteRepository = new SiteRepository();
        $this->setupTestData();
    }
    
    /**
     * Setup test company and user
     */
    private function setupTestData(): void {
        // Get or create a test ADV company
        $result = $this->getResults("SELECT id FROM companies WHERE type = 'adv' AND status = 1 LIMIT 1");
        if (!empty($result)) {
            $this->testCompanyId = (int)$result[0]['id'];
        } else {
            $stmt = $this->executeQuery(
                "INSERT INTO companies (name, type, status) VALUES (?, ?, ?)",
                ['Test ADV Company ' . uniqid(), 'adv', 1],
                'ssi'
            );
            $this->testCompanyId = $this->db->insert_id;
            $stmt->close();
        }
        
        // Get or create a test contractor company
        $result = $this->getResults("SELECT id FROM companies WHERE type = 'contractor' AND status = 1 LIMIT 1");
        if (!empty($result)) {
            $this->testContractorId = (int)$result[0]['id'];
        } else {
            $stmt = $this->executeQuery(
                "INSERT INTO companies (name, type, status) VALUES (?, ?, ?)",
                ['Test Contractor Company ' . uniqid(), 'contractor', 1],
                'ssi'
            );
            $this->testContractorId = $this->db->insert_id;
            $stmt->close();
        }
        
        // Get or create a test user
        $result = $this->getResults("SELECT id FROM users WHERE status = 1 LIMIT 1");
        $this->testUserId = !empty($result) ? (int)$result[0]['id'] : 1;
    }
    
    public function runTests(): bool {
        echo "=== Delegation Export Property Tests ===\n\n";
        
        $allPassed = true;
        
        // Property 7: Delegation export round-trip
        $allPassed &= $this->runPropertyTest(
            "Property 7: Export produces valid Excel file",
            [$this, 'testExportProducesValidFile']
        );
        
        $allPassed &= $this->runPropertyTest(
            "Property 7: Exported data can be parsed back",
            [$this, 'testExportedDataCanBeParsed']
        );
        
        $allPassed &= $this->runPropertyTest(
            "Property 7: Round-trip preserves delegation data",
            [$this, 'testRoundTripPreservesData']
        );
        
        // Cleanup
        $this->cleanupTestData();
        
        return $allPassed;
    }
    
    /**
     * Generate valid site data for testing
     */
    private function generateValidSiteData(): array {
        return [
            'site_name' => 'Site_' . $this->generateRandomString(10),
            'lho' => 'LHO_' . $this->generateRandomString(5),
            'bank_name' => 'Bank_' . $this->generateRandomString(8),
            'customer_name' => 'Customer_' . $this->generateRandomString(8),
            'city' => 'City_' . $this->generateRandomString(6),
            'state' => 'State_' . $this->generateRandomString(6),
            'country' => 'Country_' . $this->generateRandomString(6),
            'zone' => 'Zone_' . $this->generateRandomString(4),
            'address' => 'Address ' . $this->generateRandomString(20),
            'latitude' => round(rand(-9000000, 9000000) / 100000, 6),
            'longitude' => round(rand(-18000000, 18000000) / 100000, 6),
            'company_id' => $this->testCompanyId
        ];
    }
    
    /**
     * Create a test site and delegation
     */
    private function createTestSiteAndDelegation(): ?array {
        // Create a site
        $siteData = $this->generateValidSiteData();
        $siteResult = $this->siteService->createSite($siteData, $this->testUserId);
        
        if (!$siteResult['success']) {
            return null;
        }
        
        $siteId = $siteResult['data']['id'];
        $this->createdSiteIds[] = $siteId;
        
        // Create a delegation
        $delegationResult = $this->delegationService->delegateSite($siteId, $this->testContractorId, $this->testUserId);
        
        if (!$delegationResult['success']) {
            return null;
        }
        
        $this->createdDelegationIds[] = $delegationResult['data']['id'];
        
        return [
            'site' => $siteResult['data'],
            'delegation' => $delegationResult['data']
        ];
    }
    
    /**
     * Property 7: Export produces valid Excel file
     * **Feature: site-management-delegation, Property 7: Delegation export round-trip**
     * **Validates: Requirements 3.4**
     */
    public function testExportProducesValidFile(): array {
        try {
            // Create some test delegations
            $numDelegations = rand(2, 4);
            for ($i = 0; $i < $numDelegations; $i++) {
                $result = $this->createTestSiteAndDelegation();
                if (!$result) {
                    throw new Exception("Failed to create test delegation");
                }
            }
            
            // Generate export
            $exportPath = $this->delegationService->generateDelegationExport($this->testCompanyId);
            
            $this->assert(
                !empty($exportPath),
                "Export should return a file path"
            );
            
            $this->assert(
                file_exists($exportPath),
                "Export file should exist at: $exportPath"
            );
            
            $this->createdExportFiles[] = $exportPath;
            
            // Check file extension
            $extension = pathinfo($exportPath, PATHINFO_EXTENSION);
            $this->assert(
                $extension === 'xlsx',
                "Export file should have .xlsx extension. Got: $extension"
            );
            
            // Check file size is reasonable (not empty)
            $fileSize = filesize($exportPath);
            $this->assert(
                $fileSize > 0,
                "Export file should not be empty. Size: $fileSize bytes"
            );
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Property 7: Exported data can be parsed back
     * **Feature: site-management-delegation, Property 7: Delegation export round-trip**
     * **Validates: Requirements 3.4**
     */
    public function testExportedDataCanBeParsed(): array {
        try {
            // Check if ZipArchive is available (required for reading xlsx files)
            if (!class_exists('ZipArchive')) {
                // Skip this test if ZipArchive is not available
                // The export functionality was already verified in the previous test
                echo "\n    [SKIPPED] ZipArchive extension not available for reading xlsx files\n";
                return ['success' => true, 'skipped' => true];
            }
            
            // Create some test delegations
            $numDelegations = rand(2, 4);
            for ($i = 0; $i < $numDelegations; $i++) {
                $result = $this->createTestSiteAndDelegation();
                if (!$result) {
                    throw new Exception("Failed to create test delegation");
                }
            }
            
            // Generate export
            $exportPath = $this->delegationService->generateDelegationExport($this->testCompanyId);
            
            $this->assert(!empty($exportPath), "Export should return a file path");
            $this->createdExportFiles[] = $exportPath;
            
            // Parse the exported file
            $columnMapping = [
                'A' => 'id',
                'B' => 'site_id',
                'C' => 'site_name',
                'D' => 'contractor_id',
                'E' => 'contractor_name',
                'F' => 'status',
                'G' => 'delegated_by',
                'H' => 'delegated_at',
                'I' => 'responded_by',
                'J' => 'responded_at',
                'K' => 'rejection_notes'
            ];
            
            $parseResult = $this->bulkOperationService->parseExcelFile($exportPath, $columnMapping);
            
            $this->assert(
                $parseResult['success'],
                "Should be able to parse exported file: " . ($parseResult['message'] ?? '')
            );
            
            $this->assert(
                !empty($parseResult['data']),
                "Parsed data should not be empty"
            );
            
            // Verify parsed data has expected structure
            $firstRow = $parseResult['data'][0];
            $expectedFields = ['id', 'site_id', 'status', 'delegated_by', 'delegated_at'];
            
            foreach ($expectedFields as $field) {
                $this->assert(
                    array_key_exists($field, $firstRow),
                    "Parsed data should have field: $field"
                );
            }
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Property 7: Round-trip preserves delegation data
     * **Feature: site-management-delegation, Property 7: Delegation export round-trip**
     * **Validates: Requirements 3.4**
     */
    public function testRoundTripPreservesData(): array {
        try {
            // Check if ZipArchive is available (required for reading xlsx files)
            if (!class_exists('ZipArchive')) {
                // Skip this test if ZipArchive is not available
                echo "\n    [SKIPPED] ZipArchive extension not available for reading xlsx files\n";
                return ['success' => true, 'skipped' => true];
            }
            
            // Create test delegations and track their data
            $originalDelegations = [];
            $numDelegations = rand(2, 4);
            
            for ($i = 0; $i < $numDelegations; $i++) {
                $result = $this->createTestSiteAndDelegation();
                if (!$result) {
                    throw new Exception("Failed to create test delegation");
                }
                $originalDelegations[] = $result['delegation'];
            }
            
            // Generate export
            $exportPath = $this->delegationService->generateDelegationExport($this->testCompanyId);
            
            $this->assert(!empty($exportPath), "Export should return a file path");
            $this->createdExportFiles[] = $exportPath;
            
            // Parse the exported file
            $columnMapping = [
                'A' => 'id',
                'B' => 'site_id',
                'C' => 'site_name',
                'D' => 'contractor_id',
                'E' => 'contractor_name',
                'F' => 'status',
                'G' => 'delegated_by',
                'H' => 'delegated_at',
                'I' => 'responded_by',
                'J' => 'responded_at',
                'K' => 'rejection_notes'
            ];
            
            $parseResult = $this->bulkOperationService->parseExcelFile($exportPath, $columnMapping);
            
            $this->assert($parseResult['success'], "Should be able to parse exported file");
            
            // Create lookup of parsed data by ID
            $parsedById = [];
            foreach ($parseResult['data'] as $row) {
                $parsedById[(int)$row['id']] = $row;
            }
            
            // Verify each original delegation is in the parsed data with correct values
            foreach ($originalDelegations as $original) {
                $originalId = (int)$original['id'];
                
                $this->assert(
                    isset($parsedById[$originalId]),
                    "Original delegation ID $originalId should be in exported data"
                );
                
                $parsed = $parsedById[$originalId];
                
                // Check key fields match
                $this->assert(
                    (int)$parsed['site_id'] === (int)$original['site_id'],
                    "Site ID should match. Original: {$original['site_id']}, Parsed: {$parsed['site_id']}"
                );
                
                $this->assert(
                    (int)$parsed['contractor_id'] === (int)$original['contractor_id'],
                    "Contractor ID should match. Original: {$original['contractor_id']}, Parsed: {$parsed['contractor_id']}"
                );
                
                $this->assert(
                    trim($parsed['status']) === trim($original['status']),
                    "Status should match. Original: {$original['status']}, Parsed: {$parsed['status']}"
                );
                
                $this->assert(
                    (int)$parsed['delegated_by'] === (int)$original['delegated_by'],
                    "Delegated by should match. Original: {$original['delegated_by']}, Parsed: {$parsed['delegated_by']}"
                );
            }
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Clean up test data
     */
    protected function cleanupTestData(): void {
        // Delete delegations first (foreign key constraint)
        foreach ($this->createdDelegationIds as $delegationId) {
            try {
                // Delete delegation history first
                $stmt = $this->executeQuery(
                    "DELETE FROM delegation_history WHERE delegation_id = ?",
                    [$delegationId],
                    'i'
                );
                $stmt->close();
                
                // Delete delegation
                $stmt = $this->executeQuery(
                    "DELETE FROM site_delegations WHERE id = ?",
                    [$delegationId],
                    'i'
                );
                $stmt->close();
            } catch (Exception $e) {
                // Ignore cleanup errors
            }
        }
        $this->createdDelegationIds = [];
        
        // Delete sites
        foreach ($this->createdSiteIds as $siteId) {
            try {
                $stmt = $this->executeQuery(
                    "DELETE FROM sites WHERE id = ?",
                    [$siteId],
                    'i'
                );
                $stmt->close();
            } catch (Exception $e) {
                // Ignore cleanup errors
            }
        }
        $this->createdSiteIds = [];
        
        // Delete export files
        foreach ($this->createdExportFiles as $filePath) {
            if (file_exists($filePath)) {
                @unlink($filePath);
            }
        }
        $this->createdExportFiles = [];
    }
}
