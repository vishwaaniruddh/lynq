<?php
/**
 * Property Test: IP_Master Bulk Upload Validation
 * 
 * **Feature: ip-configuration-management, Property 21: Bulk Upload Validation**
 * **Validates: Requirements 10.1**
 * 
 * Property: For any bulk IP_Master upload, all rows SHALL be validated for IP format 
 * and uniqueness before any records are committed.
 */

require_once __DIR__ . '/PropertyTestBase.php';
require_once __DIR__ . '/../models/IPMaster.php';
require_once __DIR__ . '/../repositories/IPMasterRepository.php';
require_once __DIR__ . '/../services/IPMasterService.php';

class IPMasterBulkUploadTest extends PropertyTestBase {
    private $repository;
    private $service;
    
    public function __construct() {
        parent::__construct();
        $this->repository = new IPMasterRepository();
        $this->service = new IPMasterService();
    }
    
    protected function generateValidIP(): string {
        $octets = [];
        for ($i = 0; $i < 4; $i++) {
            $octets[] = rand(0, 255);
        }
        return implode('.', $octets);
    }
    
    protected function generateInvalidIP(): string {
        $types = [
            '',
            $this->generateRandomString(10),
            rand(0, 255) . '.' . rand(0, 255),
            rand(0, 255) . '.' . rand(256, 999) . '.' . rand(0, 255) . '.' . rand(0, 255),
            rand(0, 255) . '.abc.' . rand(0, 255) . '.' . rand(0, 255),
        ];
        return $types[array_rand($types)];
    }
    
    protected function generateValidRow(): array {
        return [
            'network_ip' => $this->generateValidIP(),
            'router_ip' => $this->generateValidIP(),
            'site_ip' => $this->generateValidIP(),
            'subnet_mask' => $this->generateValidIP()
        ];
    }

    
    protected function generateValidBatch(int $count): array {
        $rows = [];
        $seen = [];
        for ($i = 0; $i < $count; $i++) {
            do {
                $row = $this->generateValidRow();
                $key = implode('|', $row);
            } while (isset($seen[$key]));
            $seen[$key] = true;
            $rows[] = $row;
        }
        return $rows;
    }
    
    protected function validateBulkUpload(array $rows): array {
        $result = [
            'success' => true,
            'total_rows' => count($rows),
            'valid_count' => 0,
            'error_count' => 0,
            'errors' => []
        ];
        
        $seenCombinations = [];
        
        foreach ($rows as $index => $row) {
            $rowNumber = $index + 1;
            $rowErrors = [];
            
            $requiredFields = ['network_ip', 'router_ip', 'site_ip', 'subnet_mask'];
            foreach ($requiredFields as $field) {
                if (empty($row[$field])) {
                    $rowErrors[] = ucfirst(str_replace('_', ' ', $field)) . ' is required';
                }
            }
            
            if (!empty($rowErrors)) {
                $result['errors'][$rowNumber] = $rowErrors;
                $result['error_count']++;
                continue;
            }
            
            $ipErrors = IPMaster::validateAllIPs($row);
            if (!empty($ipErrors)) {
                foreach ($ipErrors as $error) {
                    $rowErrors[] = $error;
                }
            }
            
            $combination = implode('|', $row);
            if (isset($seenCombinations[$combination])) {
                $rowErrors[] = "Duplicate IP combination found in row {$seenCombinations[$combination]}";
            } else {
                $seenCombinations[$combination] = $rowNumber;
            }
            
            if (empty($rowErrors) && $this->repository->checkDuplicateFromArray($row)) {
                $rowErrors[] = 'This IP combination already exists in the database';
            }
            
            if (!empty($rowErrors)) {
                $result['errors'][$rowNumber] = $rowErrors;
                $result['error_count']++;
            } else {
                $result['valid_count']++;
            }
        }
        
        if ($result['error_count'] > 0) {
            $result['success'] = false;
        }
        
        return $result;
    }

    
    public function testAllValidRowsPassValidation(): bool {
        echo "\n=== Property Test: All Valid Rows Pass Validation ===\n";
        
        return $this->runPropertyTest(
            'All valid rows pass validation',
            function() {
                $batchSize = rand(2, 5);
                $rows = $this->generateValidBatch($batchSize);
                $result = $this->validateBulkUpload($rows);
                
                if (!$result['success']) {
                    return [
                        'success' => false,
                        'message' => "Valid batch failed validation",
                        'data' => ['rows' => $rows, 'errors' => $result['errors']]
                    ];
                }
                
                if ($result['valid_count'] !== $batchSize) {
                    return [
                        'success' => false,
                        'message' => "Expected $batchSize valid rows, got {$result['valid_count']}",
                        'data' => ['rows' => $rows, 'result' => $result]
                    ];
                }
                
                return ['success' => true];
            }
        );
    }
    
    public function testInvalidIPFormatFailsValidation(): bool {
        echo "\n=== Property Test: Invalid IP Format Fails Validation ===\n";
        
        return $this->runPropertyTest(
            'Invalid IP format fails validation',
            function() {
                $rows = $this->generateValidBatch(3);
                $corruptIndex = rand(0, count($rows) - 1);
                $fields = ['network_ip', 'router_ip', 'site_ip', 'subnet_mask'];
                $corruptField = $fields[array_rand($fields)];
                $rows[$corruptIndex][$corruptField] = $this->generateInvalidIP();
                
                $result = $this->validateBulkUpload($rows);
                
                if ($result['success']) {
                    return [
                        'success' => false,
                        'message' => "Batch with invalid IP should have failed validation",
                        'data' => ['corrupt_row' => $corruptIndex + 1, 'corrupt_field' => $corruptField]
                    ];
                }
                
                $expectedRowNumber = $corruptIndex + 1;
                if (!isset($result['errors'][$expectedRowNumber])) {
                    return [
                        'success' => false,
                        'message' => "Error not reported for corrupt row $expectedRowNumber",
                        'data' => ['errors' => $result['errors']]
                    ];
                }
                
                return ['success' => true];
            }
        );
    }

    
    public function testDuplicateWithinBatchFailsValidation(): bool {
        echo "\n=== Property Test: Duplicate Within Batch Fails Validation ===\n";
        
        return $this->runPropertyTest(
            'Duplicate within batch fails validation',
            function() {
                $rows = $this->generateValidBatch(3);
                $rows[] = $rows[0];
                
                $result = $this->validateBulkUpload($rows);
                
                if ($result['success']) {
                    return [
                        'success' => false,
                        'message' => "Batch with duplicate should have failed validation",
                        'data' => ['rows' => $rows]
                    ];
                }
                
                $hasDuplicateError = false;
                foreach ($result['errors'] as $rowErrors) {
                    foreach ($rowErrors as $error) {
                        if (stripos($error, 'duplicate') !== false) {
                            $hasDuplicateError = true;
                            break 2;
                        }
                    }
                }
                
                if (!$hasDuplicateError) {
                    return [
                        'success' => false,
                        'message' => "No duplicate error reported",
                        'data' => ['errors' => $result['errors']]
                    ];
                }
                
                return ['success' => true];
            }
        );
    }
    
    public function testMissingFieldsFailsValidation(): bool {
        echo "\n=== Property Test: Missing Fields Fails Validation ===\n";
        
        return $this->runPropertyTest(
            'Missing required fields fails validation',
            function() {
                $rows = $this->generateValidBatch(3);
                $corruptIndex = rand(0, count($rows) - 1);
                $fields = ['network_ip', 'router_ip', 'site_ip', 'subnet_mask'];
                $removeField = $fields[array_rand($fields)];
                $rows[$corruptIndex][$removeField] = '';
                
                $result = $this->validateBulkUpload($rows);
                
                if ($result['success']) {
                    return [
                        'success' => false,
                        'message' => "Batch with missing field should have failed validation",
                        'data' => ['corrupt_row' => $corruptIndex + 1, 'missing_field' => $removeField]
                    ];
                }
                
                $expectedRowNumber = $corruptIndex + 1;
                if (!isset($result['errors'][$expectedRowNumber])) {
                    return [
                        'success' => false,
                        'message' => "Error not reported for row with missing field",
                        'data' => ['errors' => $result['errors']]
                    ];
                }
                
                return ['success' => true];
            }
        );
    }

    
    public function testAllOrNothingCommit(): bool {
        echo "\n=== Property Test: All-or-Nothing Commit Behavior ===\n";
        
        return $this->runPropertyTest(
            'All-or-nothing commit behavior',
            function() {
                $rows = $this->generateValidBatch(3);
                $rows[] = [
                    'network_ip' => 'invalid',
                    'router_ip' => $this->generateValidIP(),
                    'site_ip' => $this->generateValidIP(),
                    'subnet_mask' => $this->generateValidIP()
                ];
                
                $result = $this->validateBulkUpload($rows);
                
                if ($result['success']) {
                    return [
                        'success' => false,
                        'message' => "Batch with invalid row should have failed",
                        'data' => ['rows' => $rows]
                    ];
                }
                
                if ($result['error_count'] < 1) {
                    return [
                        'success' => false,
                        'message' => "Expected at least 1 error, got {$result['error_count']}",
                        'data' => ['result' => $result]
                    ];
                }
                
                return ['success' => true];
            }
        );
    }
    
    public function testErrorCountAccuracy(): bool {
        echo "\n=== Property Test: Error Count Accuracy ===\n";
        
        return $this->runPropertyTest(
            'Error count matches actual errors',
            function() {
                $validCount = rand(2, 4);
                $invalidCount = rand(1, 3);
                
                $rows = $this->generateValidBatch($validCount);
                
                for ($i = 0; $i < $invalidCount; $i++) {
                    $rows[] = [
                        'network_ip' => 'invalid' . $i,
                        'router_ip' => $this->generateValidIP(),
                        'site_ip' => $this->generateValidIP(),
                        'subnet_mask' => $this->generateValidIP()
                    ];
                }
                
                shuffle($rows);
                
                $result = $this->validateBulkUpload($rows);
                
                $actualErrorCount = count($result['errors']);
                if ($result['error_count'] !== $actualErrorCount) {
                    return [
                        'success' => false,
                        'message' => "Error count mismatch: reported {$result['error_count']}, actual $actualErrorCount",
                        'data' => ['result' => $result]
                    ];
                }
                
                if ($result['valid_count'] + $result['error_count'] !== $result['total_rows']) {
                    return [
                        'success' => false,
                        'message' => "Count mismatch: valid + error != total",
                        'data' => ['result' => $result]
                    ];
                }
                
                return ['success' => true];
            }
        );
    }

    
    public function runAllTests(): array {
        $results = [];
        
        $results['valid_rows_pass'] = $this->testAllValidRowsPassValidation();
        $results['invalid_ip_fails'] = $this->testInvalidIPFormatFailsValidation();
        $results['duplicate_batch_fails'] = $this->testDuplicateWithinBatchFailsValidation();
        $results['missing_fields_fails'] = $this->testMissingFieldsFailsValidation();
        $results['all_or_nothing'] = $this->testAllOrNothingCommit();
        $results['error_count_accuracy'] = $this->testErrorCountAccuracy();
        
        $passed = array_filter($results);
        $total = count($results);
        $passedCount = count($passed);
        
        echo "\n=== Summary ===\n";
        echo "Passed: $passedCount / $total\n";
        
        if ($passedCount === $total) {
            echo "All property tests passed!\n";
        } else {
            echo "Some property tests failed.\n";
            foreach ($results as $name => $result) {
                if (!$result) {
                    echo "  - Failed: $name\n";
                }
            }
        }
        
        return $results;
    }
}

if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    $test = new IPMasterBulkUploadTest();
    $results = $test->runAllTests();
    exit(in_array(false, $results, true) ? 1 : 0);
}
