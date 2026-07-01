<?php
/**
 * Property Test: Export Data Round-Trip
 * 
 * **Feature: ip-configuration-management, Property 23: Export Data Round-Trip**
 * **Validates: Requirements 8.4**
 * 
 * Property: For any IP_Master record exported to CSV format, parsing the CSV 
 * and re-importing SHALL produce an equivalent record.
 * 
 * This test verifies that:
 * 1. CSV export/import produces equivalent records for configuration reports
 * 2. CSV export/import produces equivalent records for IP usage reports
 * 3. All field types are preserved through serialization
 * 4. Special characters are handled correctly
 */

require_once __DIR__ . '/PropertyTestBase.php';
require_once __DIR__ . '/../services/ConfigurationReportService.php';
require_once __DIR__ . '/../repositories/IPMasterRepository.php';
require_once __DIR__ . '/../repositories/RouterIPBindingRepository.php';
require_once __DIR__ . '/../models/IPMaster.php';
require_once __DIR__ . '/../models/RouterIPBinding.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Role.php';
require_once __DIR__ . '/../repositories/CompanyRepository.php';

class ExportRoundTripConfigTest extends PropertyTestBase {
    private $reportService;
    private $ipMasterRepository;
    private $bindingRepository;
    private $companyRepository;
    private $userModel;
    private $roleModel;
    
    private $createdIPMasterIds = [];
    private $createdBindingIds = [];
    private $createdUserIds = [];
    private $createdRoleIds = [];
    private $createdCompanyIds = [];
    
    public function __construct() {
        parent::__construct();
        $this->reportService = new ConfigurationReportService();
        $this->ipMasterRepository = new IPMasterRepository();
        $this->bindingRepository = new RouterIPBindingRepository();
        $this->companyRepository = new CompanyRepository();
        $this->companyRepository->disableCompanyFilter();
        $this->userModel = new User();
        $this->roleModel = new Role();
    }
    
    /**
     * Generate a valid IPv4 address
     */
    protected function generateValidIP(): string {
        $octets = [];
        for ($i = 0; $i < 4; $i++) {
            $octets[] = rand(1, 254); // Avoid 0 and 255 for cleaner test data
        }
        return implode('.', $octets);
    }
    
    /**
     * Generate a unique IP combination
     */
    protected function generateUniqueIPCombination(): array {
        return [
            'network_ip' => $this->generateValidIP(),
            'router_ip' => $this->generateValidIP(),
            'site_ip' => $this->generateValidIP(),
            'subnet_mask' => '255.255.255.' . rand(0, 252),
            'status' => IPMaster::STATUS_AVAILABLE
        ];
    }
    
    /**
     * Generate a random router serial number
     */
    protected function generateRouterSerial(): string {
        return 'RTR-' . $this->generateRandomString(8) . '-' . rand(1000, 9999);
    }
    
    /**
     * Create test company
     */
    private function createTestCompany(): array {
        $data = [
            'name' => 'Test Company ' . $this->generateRandomString(8),
            'type' => 'ADV',
            'status' => 'ACTIVE'
        ];
        
        $company = $this->companyRepository->create($data);
        $this->createdCompanyIds[] = $company['id'];
        return $company;
    }
    
    /**
     * Create test role
     */
    private function createTestRole(): array {
        $data = [
            'name' => 'Test Role ' . $this->generateRandomString(6),
            'level' => 10,
            'description' => 'Test role for export round-trip test'
        ];
        
        $role = $this->roleModel->create($data);
        $this->createdRoleIds[] = $role['id'];
        return $role;
    }
    
    /**
     * Create test user
     */
    private function createTestUser(int $companyId, int $roleId): array {
        $username = 'testuser_' . $this->generateRandomString(8);
        $data = [
            'username' => $username,
            'email' => $username . '@test.com',
            'password_hash' => password_hash('test123', PASSWORD_DEFAULT),
            'first_name' => 'Test',
            'last_name' => 'User',
            'company_id' => $companyId,
            'role_id' => $roleId,
            'status' => 1
        ];
        
        $user = $this->userModel->create($data);
        $this->createdUserIds[] = $user['id'];
        return $user;
    }
    
    /**
     * Create test IP_Master record
     */
    private function createTestIPMaster(): array {
        $ipData = $this->generateUniqueIPCombination();
        
        // Ensure uniqueness
        while ($this->ipMasterRepository->checkDuplicateFromArray($ipData)) {
            $ipData = $this->generateUniqueIPCombination();
        }
        
        $id = $this->ipMasterRepository->createIPMaster($ipData);
        $this->createdIPMasterIds[] = $id;
        
        return $this->ipMasterRepository->findById($id);
    }
    
    /**
     * Create test binding
     */
    private function createTestBinding(int $ipMasterId, int $userId): ?array {
        $routerSerial = $this->generateRouterSerial();
        
        $result = $this->bindingRepository->createBinding(
            $routerSerial,
            $ipMasterId,
            $userId,
            'Test binding for export round-trip'
        );
        
        if ($result['success']) {
            $this->createdBindingIds[] = $result['data']['id'];
            
            // Update IP_Master status to configured
            $this->ipMasterRepository->updateStatus($ipMasterId, IPMaster::STATUS_CONFIGURED);
            
            return $result['data'];
        }
        
        return null;
    }
    
    /**
     * Clean up test data
     */
    protected function cleanupTestData(): void {
        // Delete bindings first (foreign key constraints)
        foreach ($this->createdBindingIds as $id) {
            try {
                // Reset IP status first
                $binding = $this->bindingRepository->findById($id);
                if ($binding) {
                    $this->ipMasterRepository->updateStatus($binding['ip_master_id'], IPMaster::STATUS_AVAILABLE);
                }
                
                // Delete binding directly
                $sql = "DELETE FROM router_ip_bindings WHERE id = ?";
                $this->executeQuery($sql, [$id], 'i');
            } catch (Exception $e) {
                // Ignore cleanup errors
            }
        }
        $this->createdBindingIds = [];
        
        // Delete IP_Master records
        foreach ($this->createdIPMasterIds as $id) {
            try {
                $this->ipMasterRepository->deleteIPMaster($id);
            } catch (Exception $e) {
                // Ignore cleanup errors
            }
        }
        $this->createdIPMasterIds = [];
        
        // Delete users
        foreach ($this->createdUserIds as $id) {
            try {
                $this->userModel->delete($id);
            } catch (Exception $e) {
                // Ignore cleanup errors
            }
        }
        $this->createdUserIds = [];
        
        // Delete roles
        foreach ($this->createdRoleIds as $id) {
            try {
                $this->roleModel->delete($id);
            } catch (Exception $e) {
                // Ignore cleanup errors
            }
        }
        $this->createdRoleIds = [];
        
        // Delete companies
        foreach ($this->createdCompanyIds as $id) {
            try {
                $this->companyRepository->delete($id);
            } catch (Exception $e) {
                // Ignore cleanup errors
            }
        }
        $this->createdCompanyIds = [];
    }

    
    /**
     * Property Test: IP Usage Report CSV Round-Trip
     * 
     * For any IP_Master record, exporting to CSV and parsing back should 
     * produce an equivalent record with all key fields preserved.
     */
    public function testIPUsageReportRoundTrip(): bool {
        echo "\n=== Property Test: IP Usage Report CSV Round-Trip ===\n";
        echo "**Feature: ip-configuration-management, Property 23: Export Data Round-Trip**\n";
        echo "**Validates: Requirements 8.4**\n\n";
        
        return $this->runPropertyTest(
            'IP usage report CSV round-trip preserves records',
            function() {
                // Create test IP_Master record
                $originalIP = $this->createTestIPMaster();
                
                // Get IP usage report
                $reportData = $this->reportService->getIPUsageReport();
                
                // Export to CSV
                $csvContent = $this->reportService->exportIPUsageReportToCSV($reportData);
                
                if (empty($csvContent)) {
                    return [
                        'success' => false,
                        'message' => 'CSV export produced empty content',
                        'data' => $originalIP
                    ];
                }
                
                // Parse CSV back
                $parseResult = $this->reportService->parseCSV($csvContent, 'ip_usage');
                
                if (!$parseResult['success']) {
                    return [
                        'success' => false,
                        'message' => 'CSV parsing failed: ' . $parseResult['message'],
                        'data' => $originalIP
                    ];
                }
                
                // Find the original IP in parsed data
                $foundIP = null;
                foreach ($parseResult['records'] as $record) {
                    if ($record['ip_master_id'] == $originalIP['id']) {
                        $foundIP = $record;
                        break;
                    }
                }
                
                if (!$foundIP) {
                    return [
                        'success' => false,
                        'message' => 'Original IP_Master not found in parsed CSV data',
                        'data' => ['original' => $originalIP, 'parsed_count' => count($parseResult['records'])]
                    ];
                }
                
                // Verify key fields are preserved
                $fieldsToCheck = [
                    'network_ip' => $originalIP['network_ip'],
                    'router_ip' => $originalIP['router_ip'],
                    'site_ip' => $originalIP['site_ip'],
                    'subnet_mask' => $originalIP['subnet_mask'],
                    'status' => $originalIP['status']
                ];
                
                foreach ($fieldsToCheck as $field => $expectedValue) {
                    $parsedValue = $foundIP[$field] ?? null;
                    
                    if ($parsedValue !== $expectedValue) {
                        return [
                            'success' => false,
                            'message' => "Field '$field' mismatch: expected='$expectedValue', parsed='$parsedValue'",
                            'data' => ['original' => $originalIP, 'parsed' => $foundIP]
                        ];
                    }
                }
                
                return ['success' => true];
            },
            20 // Reduced iterations for database tests
        );
    }
    
    /**
     * Property Test: Configuration Report CSV Round-Trip
     * 
     * For any configuration binding, exporting to CSV and parsing back should 
     * produce an equivalent record with all key fields preserved.
     */
    public function testConfigurationReportRoundTrip(): bool {
        echo "\n=== Property Test: Configuration Report CSV Round-Trip ===\n";
        
        return $this->runPropertyTest(
            'Configuration report CSV round-trip preserves records',
            function() {
                // Create test data
                $company = $this->createTestCompany();
                $role = $this->createTestRole();
                $user = $this->createTestUser($company['id'], $role['id']);
                $ipMaster = $this->createTestIPMaster();
                
                // Create binding
                $binding = $this->createTestBinding($ipMaster['id'], $user['id']);
                
                if (!$binding) {
                    return [
                        'success' => false,
                        'message' => 'Failed to create test binding',
                        'data' => ['ip_master' => $ipMaster, 'user' => $user]
                    ];
                }
                
                // Get configuration report
                $reportData = $this->reportService->getConfigurationReport();
                
                // Export to CSV
                $csvContent = $this->reportService->exportConfigurationReportToCSV($reportData);
                
                if (empty($csvContent)) {
                    return [
                        'success' => false,
                        'message' => 'CSV export produced empty content',
                        'data' => $binding
                    ];
                }
                
                // Parse CSV back
                $parseResult = $this->reportService->parseCSV($csvContent, 'configurations');
                
                if (!$parseResult['success']) {
                    return [
                        'success' => false,
                        'message' => 'CSV parsing failed: ' . $parseResult['message'],
                        'data' => $binding
                    ];
                }
                
                // Find the original binding in parsed data
                $foundBinding = null;
                foreach ($parseResult['records'] as $record) {
                    if ($record['binding_id'] == $binding['id']) {
                        $foundBinding = $record;
                        break;
                    }
                }
                
                if (!$foundBinding) {
                    return [
                        'success' => false,
                        'message' => 'Original binding not found in parsed CSV data',
                        'data' => ['original' => $binding, 'parsed_count' => count($parseResult['records'])]
                    ];
                }
                
                // Verify key fields are preserved
                $fieldsToCheck = [
                    'router_serial_number' => $binding['router_serial_number'],
                    'network_ip' => $ipMaster['network_ip'],
                    'router_ip' => $ipMaster['router_ip'],
                    'site_ip' => $ipMaster['site_ip'],
                    'subnet_mask' => $ipMaster['subnet_mask']
                ];
                
                foreach ($fieldsToCheck as $field => $expectedValue) {
                    $parsedValue = $foundBinding[$field] ?? null;
                    
                    if ($parsedValue !== $expectedValue) {
                        return [
                            'success' => false,
                            'message' => "Field '$field' mismatch: expected='$expectedValue', parsed='$parsedValue'",
                            'data' => ['original' => $binding, 'parsed' => $foundBinding]
                        ];
                    }
                }
                
                return ['success' => true];
            },
            20
        );
    }
    
    /**
     * Property Test: Special Characters Preservation
     * 
     * For any IP_Master record with special characters in notes,
     * exporting and re-importing should preserve those characters.
     */
    public function testSpecialCharactersPreservation(): bool {
        echo "\n=== Property Test: Special Characters Preservation ===\n";
        
        return $this->runPropertyTest(
            'Special characters are preserved through CSV export/import',
            function() {
                // Create test data with special characters
                $company = $this->createTestCompany();
                $role = $this->createTestRole();
                $user = $this->createTestUser($company['id'], $role['id']);
                $ipMaster = $this->createTestIPMaster();
                
                // Create binding with special characters in notes
                $specialNotes = 'Test with "quotes", commas, and special chars: <>&\'';
                $routerSerial = $this->generateRouterSerial();
                
                $result = $this->bindingRepository->createBinding(
                    $routerSerial,
                    $ipMaster['id'],
                    $user['id'],
                    $specialNotes
                );
                
                if (!$result['success']) {
                    return [
                        'success' => false,
                        'message' => 'Failed to create binding with special characters',
                        'data' => ['notes' => $specialNotes]
                    ];
                }
                
                $this->createdBindingIds[] = $result['data']['id'];
                $this->ipMasterRepository->updateStatus($ipMaster['id'], IPMaster::STATUS_CONFIGURED);
                
                // Get configuration report
                $reportData = $this->reportService->getConfigurationReport();
                
                // Export to CSV
                $csvContent = $this->reportService->exportConfigurationReportToCSV($reportData);
                
                // Parse CSV back
                $parseResult = $this->reportService->parseCSV($csvContent, 'configurations');
                
                if (!$parseResult['success']) {
                    return [
                        'success' => false,
                        'message' => 'CSV parsing failed',
                        'data' => ['notes' => $specialNotes]
                    ];
                }
                
                // Find the binding in parsed data
                $foundBinding = null;
                foreach ($parseResult['records'] as $record) {
                    if ($record['binding_id'] == $result['data']['id']) {
                        $foundBinding = $record;
                        break;
                    }
                }
                
                if (!$foundBinding) {
                    return [
                        'success' => false,
                        'message' => 'Binding not found in parsed data',
                        'data' => ['binding_id' => $result['data']['id']]
                    ];
                }
                
                // Verify special characters are preserved
                if ($foundBinding['notes'] !== $specialNotes) {
                    return [
                        'success' => false,
                        'message' => "Special characters not preserved: expected='$specialNotes', got='{$foundBinding['notes']}'",
                        'data' => ['expected' => $specialNotes, 'actual' => $foundBinding['notes']]
                    ];
                }
                
                return ['success' => true];
            },
            10
        );
    }
    
    /**
     * Property Test: Empty Fields Handling
     * 
     * For records with empty/null optional fields, exporting and re-importing
     * should handle them correctly.
     */
    public function testEmptyFieldsHandling(): bool {
        echo "\n=== Property Test: Empty Fields Handling ===\n";
        
        return $this->runPropertyTest(
            'Empty/null fields are handled correctly through CSV export/import',
            function() {
                // Create test data with empty notes
                $company = $this->createTestCompany();
                $role = $this->createTestRole();
                $user = $this->createTestUser($company['id'], $role['id']);
                $ipMaster = $this->createTestIPMaster();
                
                // Create binding with empty notes
                $routerSerial = $this->generateRouterSerial();
                
                $result = $this->bindingRepository->createBinding(
                    $routerSerial,
                    $ipMaster['id'],
                    $user['id'],
                    null // Empty notes
                );
                
                if (!$result['success']) {
                    return [
                        'success' => false,
                        'message' => 'Failed to create binding with empty notes',
                        'data' => []
                    ];
                }
                
                $this->createdBindingIds[] = $result['data']['id'];
                $this->ipMasterRepository->updateStatus($ipMaster['id'], IPMaster::STATUS_CONFIGURED);
                
                // Get configuration report
                $reportData = $this->reportService->getConfigurationReport();
                
                // Export to CSV
                $csvContent = $this->reportService->exportConfigurationReportToCSV($reportData);
                
                // Parse CSV back
                $parseResult = $this->reportService->parseCSV($csvContent, 'configurations');
                
                if (!$parseResult['success']) {
                    return [
                        'success' => false,
                        'message' => 'CSV parsing failed',
                        'data' => []
                    ];
                }
                
                // Find the binding in parsed data
                $foundBinding = null;
                foreach ($parseResult['records'] as $record) {
                    if ($record['binding_id'] == $result['data']['id']) {
                        $foundBinding = $record;
                        break;
                    }
                }
                
                if (!$foundBinding) {
                    return [
                        'success' => false,
                        'message' => 'Binding not found in parsed data',
                        'data' => ['binding_id' => $result['data']['id']]
                    ];
                }
                
                // Verify empty notes are handled (should be empty string or null)
                $parsedNotes = $foundBinding['notes'] ?? '';
                if ($parsedNotes !== '' && $parsedNotes !== null) {
                    return [
                        'success' => false,
                        'message' => "Empty notes not preserved: got='$parsedNotes'",
                        'data' => ['parsed_notes' => $parsedNotes]
                    ];
                }
                
                return ['success' => true];
            },
            10
        );
    }
    
    /**
     * Run all property tests
     */
    public function runAllTests(): array {
        $results = [];
        
        $results['ip_usage_round_trip'] = $this->testIPUsageReportRoundTrip();
        $this->cleanupTestData();
        
        $results['configuration_round_trip'] = $this->testConfigurationReportRoundTrip();
        $this->cleanupTestData();
        
        $results['special_characters'] = $this->testSpecialCharactersPreservation();
        $this->cleanupTestData();
        
        $results['empty_fields'] = $this->testEmptyFieldsHandling();
        $this->cleanupTestData();
        
        $passed = array_filter($results);
        $total = count($results);
        $passedCount = count($passed);
        
        echo "\n=== Summary ===\n";
        echo "Passed: $passedCount / $total\n";
        
        if ($passedCount === $total) {
            echo "✓ All property tests passed!\n";
        } else {
            echo "✗ Some property tests failed.\n";
            foreach ($results as $name => $result) {
                if (!$result) {
                    echo "  - Failed: $name\n";
                }
            }
        }
        
        return $results;
    }
}

// Run tests if executed directly
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    $test = new ExportRoundTripConfigTest();
    $results = $test->runAllTests();
    exit(in_array(false, $results, true) ? 1 : 0);
}
