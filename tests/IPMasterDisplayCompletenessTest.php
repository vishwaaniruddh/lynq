<?php
/**
 * Property Test: IP_Master Display Completeness
 * 
 * **Feature: ip-configuration-management, Property 3: IP_Master Display Completeness**
 * **Validates: Requirements 1.3, 3.3**
 * 
 * Property: For any IP_Master record returned by the system, the response SHALL include 
 * all four IP addresses (Network IP, Router IP, Site IP, Subnet Mask) and the current 
 * status (Available, Locked, or Configured).
 */

require_once __DIR__ . '/PropertyTestBase.php';
require_once __DIR__ . '/../models/IPMaster.php';
require_once __DIR__ . '/../repositories/IPMasterRepository.php';
require_once __DIR__ . '/../services/IPMasterService.php';

class IPMasterDisplayCompletenessTest extends PropertyTestBase {
    private $repository;
    private $service;
    private $createdIds = [];
    private $testUserId = null;
    
    public function __construct() {
        parent::__construct();
        $this->repository = new IPMasterRepository();
        $this->service = new IPMasterService();
        
        // Get a valid user ID for testing
        $this->testUserId = $this->getValidUserId();
    }
    
    /**
     * Get a valid user ID from the database for testing
     */
    private function getValidUserId(): ?int {
        $result = $this->getResults('SELECT id FROM users LIMIT 1', [], '');
        return !empty($result) ? (int)$result[0]['id'] : null;
    }
    
    /**
     * Generate a valid IPv4 address
     */
    protected function generateValidIP(): string {
        $octets = [];
        for ($i = 0; $i < 4; $i++) {
            $octets[] = rand(0, 255);
        }
        return implode('.', $octets);
    }
    
    /**
     * Generate a unique IP_Master data set
     */
    protected function generateUniqueIPMasterData(): array {
        // Generate unique IPs by using timestamp and random values
        $timestamp = microtime(true);
        $random = rand(1, 254);
        
        return [
            'network_ip' => '10.' . rand(0, 255) . '.' . rand(0, 255) . '.' . $random,
            'router_ip' => '192.' . rand(0, 255) . '.' . rand(0, 255) . '.' . $random,
            'site_ip' => '172.' . rand(16, 31) . '.' . rand(0, 255) . '.' . $random,
            'subnet_mask' => '255.255.255.0'
        ];
    }
    
    /**
     * Create a test IP_Master record
     */
    protected function createTestIPMaster(): ?array {
        $data = $this->generateUniqueIPMasterData();
        
        // Ensure uniqueness by checking and regenerating if needed
        $attempts = 0;
        while ($this->repository->checkDuplicateFromArray($data) && $attempts < 10) {
            $data = $this->generateUniqueIPMasterData();
            $attempts++;
        }
        
        if ($attempts >= 10) {
            return null;
        }
        
        $result = $this->service->create($data, $this->testUserId);
        
        if ($result['success']) {
            $this->createdIds[] = $result['data']['id'];
            return $result['data'];
        }
        
        return null;
    }
    
    /**
     * Property Test: All IP_Master records contain required fields
     * 
     * For any IP_Master record returned by findById, it SHALL include:
     * - network_ip
     * - router_ip
     * - site_ip
     * - subnet_mask
     * - status (one of: available, locked, configured)
     */
    public function testFindByIdReturnsCompleteRecord(): bool {
        echo "\n=== Property Test: FindById Returns Complete Record ===\n";
        
        return $this->runPropertyTest(
            'FindById returns all required fields',
            function() {
                // Create a test IP_Master
                $created = $this->createTestIPMaster();
                if (!$created) {
                    return [
                        'success' => false,
                        'message' => 'Failed to create test IP_Master'
                    ];
                }
                
                // Retrieve it using findById
                $retrieved = $this->repository->findById($created['id']);
                
                if (!$retrieved) {
                    return [
                        'success' => false,
                        'message' => 'Failed to retrieve IP_Master by ID',
                        'data' => ['id' => $created['id']]
                    ];
                }
                
                // Check all required fields are present
                $requiredFields = ['network_ip', 'router_ip', 'site_ip', 'subnet_mask', 'status'];
                $missingFields = [];
                
                foreach ($requiredFields as $field) {
                    if (!isset($retrieved[$field]) || $retrieved[$field] === null) {
                        $missingFields[] = $field;
                    }
                }
                
                if (!empty($missingFields)) {
                    return [
                        'success' => false,
                        'message' => 'Missing required fields in response',
                        'data' => [
                            'missing_fields' => $missingFields,
                            'retrieved' => $retrieved
                        ]
                    ];
                }
                
                // Validate status is one of the valid values
                $validStatuses = [IPMaster::STATUS_AVAILABLE, IPMaster::STATUS_LOCKED, IPMaster::STATUS_CONFIGURED];
                if (!in_array($retrieved['status'], $validStatuses)) {
                    return [
                        'success' => false,
                        'message' => 'Invalid status value',
                        'data' => [
                            'status' => $retrieved['status'],
                            'valid_statuses' => $validStatuses
                        ]
                    ];
                }
                
                // Validate IP format for all IP fields
                $ipFields = ['network_ip', 'router_ip', 'site_ip', 'subnet_mask'];
                foreach ($ipFields as $field) {
                    if (!IPMaster::validateIPFormat($retrieved[$field])) {
                        return [
                            'success' => false,
                            'message' => "Invalid IP format for $field",
                            'data' => [
                                'field' => $field,
                                'value' => $retrieved[$field]
                            ]
                        ];
                    }
                }
                
                return ['success' => true];
            },
            50 // Reduced iterations for database tests
        );
    }
    
    /**
     * Property Test: All IP_Master records in list contain required fields
     * 
     * For any IP_Master record returned by findAllWithFilters, it SHALL include
     * all required fields.
     */
    public function testListReturnsCompleteRecords(): bool {
        echo "\n=== Property Test: List Returns Complete Records ===\n";
        
        // First, create some test records
        for ($i = 0; $i < 5; $i++) {
            $this->createTestIPMaster();
        }
        
        return $this->runPropertyTest(
            'List returns all required fields for each record',
            function() {
                // Get list of IP_Masters
                $result = $this->repository->findAllWithFilters([
                    'page' => 1,
                    'limit' => 100
                ]);
                
                if (empty($result['data'])) {
                    // No records to test - this is acceptable
                    return ['success' => true];
                }
                
                // Check each record
                $requiredFields = ['network_ip', 'router_ip', 'site_ip', 'subnet_mask', 'status'];
                $validStatuses = [IPMaster::STATUS_AVAILABLE, IPMaster::STATUS_LOCKED, IPMaster::STATUS_CONFIGURED];
                
                foreach ($result['data'] as $index => $record) {
                    // Check all required fields are present
                    foreach ($requiredFields as $field) {
                        if (!isset($record[$field]) || $record[$field] === null) {
                            return [
                                'success' => false,
                                'message' => "Missing field '$field' in record at index $index",
                                'data' => [
                                    'index' => $index,
                                    'record' => $record
                                ]
                            ];
                        }
                    }
                    
                    // Validate status
                    if (!in_array($record['status'], $validStatuses)) {
                        return [
                            'success' => false,
                            'message' => "Invalid status in record at index $index",
                            'data' => [
                                'index' => $index,
                                'status' => $record['status']
                            ]
                        ];
                    }
                }
                
                return ['success' => true];
            },
            20 // Fewer iterations for list tests
        );
    }
    
    /**
     * Property Test: Service getAll returns complete records
     * 
     * For any IP_Master record returned by the service layer, it SHALL include
     * all required fields.
     */
    public function testServiceGetAllReturnsCompleteRecords(): bool {
        echo "\n=== Property Test: Service GetAll Returns Complete Records ===\n";
        
        return $this->runPropertyTest(
            'Service getAll returns all required fields',
            function() {
                // Get list via service
                $result = $this->service->getAll([
                    'page' => 1,
                    'limit' => 50
                ]);
                
                if (empty($result['data'])) {
                    return ['success' => true];
                }
                
                $requiredFields = ['network_ip', 'router_ip', 'site_ip', 'subnet_mask', 'status'];
                $validStatuses = [IPMaster::STATUS_AVAILABLE, IPMaster::STATUS_LOCKED, IPMaster::STATUS_CONFIGURED];
                
                // Pick a random record to test
                $randomIndex = array_rand($result['data']);
                $record = $result['data'][$randomIndex];
                
                // Check all required fields
                foreach ($requiredFields as $field) {
                    if (!isset($record[$field]) || $record[$field] === null) {
                        return [
                            'success' => false,
                            'message' => "Missing field '$field' in service response",
                            'data' => ['record' => $record]
                        ];
                    }
                }
                
                // Validate status
                if (!in_array($record['status'], $validStatuses)) {
                    return [
                        'success' => false,
                        'message' => 'Invalid status in service response',
                        'data' => ['status' => $record['status']]
                    ];
                }
                
                return ['success' => true];
            },
            30
        );
    }
    
    /**
     * Property Test: Export returns complete records
     * 
     * For any IP_Master record returned by export, it SHALL include
     * all required fields.
     */
    public function testExportReturnsCompleteRecords(): bool {
        echo "\n=== Property Test: Export Returns Complete Records ===\n";
        
        return $this->runPropertyTest(
            'Export returns all required fields',
            function() {
                // Get export data
                $records = $this->service->export([]);
                
                if (empty($records)) {
                    return ['success' => true];
                }
                
                $requiredFields = ['network_ip', 'router_ip', 'site_ip', 'subnet_mask', 'status'];
                $validStatuses = [IPMaster::STATUS_AVAILABLE, IPMaster::STATUS_LOCKED, IPMaster::STATUS_CONFIGURED];
                
                // Pick a random record to test
                $randomIndex = array_rand($records);
                $record = $records[$randomIndex];
                
                // Check all required fields
                foreach ($requiredFields as $field) {
                    if (!isset($record[$field]) || $record[$field] === null) {
                        return [
                            'success' => false,
                            'message' => "Missing field '$field' in export",
                            'data' => ['record' => $record]
                        ];
                    }
                }
                
                // Validate status
                if (!in_array($record['status'], $validStatuses)) {
                    return [
                        'success' => false,
                        'message' => 'Invalid status in export',
                        'data' => ['status' => $record['status']]
                    ];
                }
                
                return ['success' => true];
            },
            20
        );
    }
    
    /**
     * Property Test: Created records have all required fields
     * 
     * For any newly created IP_Master, the returned data SHALL include
     * all required fields with valid values.
     */
    public function testCreatedRecordsHaveAllFields(): bool {
        echo "\n=== Property Test: Created Records Have All Fields ===\n";
        
        return $this->runPropertyTest(
            'Created records have all required fields',
            function() {
                $data = $this->generateUniqueIPMasterData();
                
                // Ensure uniqueness
                $attempts = 0;
                while ($this->repository->checkDuplicateFromArray($data) && $attempts < 10) {
                    $data = $this->generateUniqueIPMasterData();
                    $attempts++;
                }
                
                if ($attempts >= 10) {
                    return ['success' => true]; // Skip if can't generate unique data
                }
                
                $result = $this->service->create($data, $this->testUserId);
                
                if (!$result['success']) {
                    return [
                        'success' => false,
                        'message' => 'Failed to create IP_Master',
                        'data' => ['result' => $result]
                    ];
                }
                
                $this->createdIds[] = $result['data']['id'];
                $created = $result['data'];
                
                // Check all required fields
                $requiredFields = ['id', 'network_ip', 'router_ip', 'site_ip', 'subnet_mask', 'status'];
                foreach ($requiredFields as $field) {
                    if (!isset($created[$field]) || $created[$field] === null) {
                        return [
                            'success' => false,
                            'message' => "Missing field '$field' in created record",
                            'data' => ['created' => $created]
                        ];
                    }
                }
                
                // Verify status is 'available' for new records
                if ($created['status'] !== IPMaster::STATUS_AVAILABLE) {
                    return [
                        'success' => false,
                        'message' => 'New record should have status "available"',
                        'data' => ['status' => $created['status']]
                    ];
                }
                
                // Verify IP values match input
                $ipFields = ['network_ip', 'router_ip', 'site_ip', 'subnet_mask'];
                foreach ($ipFields as $field) {
                    if ($created[$field] !== $data[$field]) {
                        return [
                            'success' => false,
                            'message' => "Field '$field' doesn't match input",
                            'data' => [
                                'input' => $data[$field],
                                'created' => $created[$field]
                            ]
                        ];
                    }
                }
                
                return ['success' => true];
            },
            30
        );
    }
    
    /**
     * Clean up test data
     */
    protected function cleanupTestData(): void {
        foreach ($this->createdIds as $id) {
            try {
                // First check if it exists and is available
                $record = $this->repository->findById($id);
                if ($record && $record['status'] === IPMaster::STATUS_AVAILABLE) {
                    $this->repository->deleteIPMaster($id);
                }
            } catch (Exception $e) {
                // Ignore cleanup errors
            }
        }
        $this->createdIds = [];
    }
    
    /**
     * Run all property tests
     */
    public function runAllTests(): array {
        $results = [];
        
        try {
            $results['find_by_id_complete'] = $this->testFindByIdReturnsCompleteRecord();
            $results['list_complete'] = $this->testListReturnsCompleteRecords();
            $results['service_get_all_complete'] = $this->testServiceGetAllReturnsCompleteRecords();
            $results['export_complete'] = $this->testExportReturnsCompleteRecords();
            $results['created_records_complete'] = $this->testCreatedRecordsHaveAllFields();
        } finally {
            $this->cleanupTestData();
        }
        
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
    $test = new IPMasterDisplayCompletenessTest();
    $results = $test->runAllTests();
    exit(in_array(false, $results, true) ? 1 : 0);
}
