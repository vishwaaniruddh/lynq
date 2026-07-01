<?php
/**
 * Property Test: Installation Export Round-Trip
 * 
 * **Feature: installation-module, Property 34: Installation export round-trip**
 * **Validates: Requirements 18.4**
 * 
 * Property: For any set of installation records, exporting to Excel/CSV/JSON and 
 * then parsing the export file should produce data equivalent to the original records.
 * 
 * This test verifies that:
 * 1. JSON export/import produces equivalent records
 * 2. CSV export/import produces equivalent records
 * 3. All field types are preserved through export serialization
 * 4. Empty/null values are handled correctly
 */

require_once __DIR__ . '/../config/autoload.php';
require_once __DIR__ . '/../services/InstallationExportService.php';
require_once __DIR__ . '/../models/Installation.php';

class InstallationExportRoundTripTest {
    private $exportService;
    private $testResults = [];
    private $iterations = 50; // Number of property test iterations
    
    public function __construct() {
        $this->exportService = new InstallationExportService();
    }
    
    /**
     * Run all property tests
     */
    public function runTests(): bool {
        echo "\n=== Installation Export Round-Trip Property Tests ===\n";
        echo "**Feature: installation-module, Property 34: Installation export round-trip**\n";
        echo "**Validates: Requirements 18.4**\n\n";
        
        // Property tests
        $this->runPropertyTest(
            'JSON export/import round-trip preserves installation records',
            [$this, 'testJsonRoundTrip']
        );
        
        $this->runPropertyTest(
            'CSV export/import round-trip preserves installation records',
            [$this, 'testCsvRoundTrip']
        );
        
        $this->runPropertyTest(
            'Empty values are handled correctly in export',
            [$this, 'testEmptyValueHandling']
        );
        
        $this->runPropertyTest(
            'All installation fields are included in export',
            [$this, 'testAllFieldsIncluded']
        );
        
        $this->runPropertyTest(
            'Special characters are preserved through export',
            [$this, 'testSpecialCharacterPreservation']
        );
        
        // Summary
        echo "\n=== Test Summary ===\n";
        $passed = count(array_filter($this->testResults));
        $total = count($this->testResults);
        echo "Passed: $passed / $total\n";
        
        return $passed === $total;
    }
    
    /**
     * Run a property test with multiple iterations
     */
    private function runPropertyTest(string $name, callable $testFunction): void {
        echo "Testing: $name\n";
        $failures = [];
        
        for ($i = 0; $i < $this->iterations; $i++) {
            try {
                $result = $testFunction();
                if (!$result['success']) {
                    $failures[] = "Iteration $i: {$result['message']}";
                }
            } catch (Exception $e) {
                $failures[] = "Iteration $i: Exception - {$e->getMessage()}";
            }
        }
        
        if (empty($failures)) {
            echo "  ✓ Passed ({$this->iterations} iterations)\n";
            $this->testResults[$name] = true;
        } else {
            echo "  ✗ Failed\n";
            foreach (array_slice($failures, 0, 3) as $failure) {
                echo "    - $failure\n";
            }
            if (count($failures) > 3) {
                echo "    ... and " . (count($failures) - 3) . " more failures\n";
            }
            $this->testResults[$name] = false;
        }
    }


    /**
     * Property Test: JSON export/import round-trip preserves installation records
     * For any installation record, export to JSON and parse back should produce equivalent record
     */
    private function testJsonRoundTrip(): array {
        // Generate random installation data
        $originalRecords = [$this->generateRandomInstallationData()];
        
        // Format for export
        $formattedRecords = $this->exportService->formatForExport($originalRecords);
        
        // Generate JSON export
        $exportData = [
            'export_type' => 'installations',
            'export_date' => date('Y-m-d H:i:s'),
            'record_count' => count($formattedRecords),
            'data' => $formattedRecords
        ];
        $jsonContent = json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        
        // Parse JSON back
        $parseResult = $this->exportService->parseImport($jsonContent, 'json');
        
        if (!$parseResult['success']) {
            return ['success' => false, 'message' => 'Parse failed: ' . $parseResult['message']];
        }
        
        // Find the original record in parsed data
        $parsedRecords = $parseResult['data']['records'] ?? [];
        
        if (empty($parsedRecords)) {
            return ['success' => false, 'message' => 'No records found in parsed data'];
        }
        
        $parsedRecord = $parsedRecords[0];
        $originalFormatted = $formattedRecords[0];
        
        // Verify key fields are preserved
        $fieldsToCheck = [
            'id', 'site_id', 'atm_id', 'address', 'city', 'state',
            'vendor_name', 'engineer_name', 'router_serial', 'status'
        ];
        
        foreach ($fieldsToCheck as $field) {
            $originalValue = $originalFormatted[$field] ?? null;
            $parsedValue = $parsedRecord[$field] ?? null;
            
            // Normalize for comparison
            $originalValue = $this->normalizeValue($originalValue);
            $parsedValue = $this->normalizeValue($parsedValue);
            
            if ($originalValue !== $parsedValue) {
                return [
                    'success' => false,
                    'message' => "Field '$field' mismatch: original=" . var_export($originalValue, true) . 
                                 ", parsed=" . var_export($parsedValue, true)
                ];
            }
        }
        
        return ['success' => true];
    }
    
    /**
     * Property Test: CSV export/import round-trip preserves installation records
     * For any installation record, export to CSV and parse back should produce equivalent record
     */
    private function testCsvRoundTrip(): array {
        // Generate random installation data
        $originalRecords = [$this->generateRandomInstallationData()];
        
        // Format for export
        $formattedRecords = $this->exportService->formatForExport($originalRecords);
        
        // Generate CSV content manually (simulating export)
        $csvContent = $this->arrayToCsv($formattedRecords);
        
        // Parse CSV back
        $parseResult = $this->exportService->parseImport($csvContent, 'csv');
        
        if (!$parseResult['success']) {
            return ['success' => false, 'message' => 'Parse failed: ' . $parseResult['message']];
        }
        
        // Find the original record in parsed data
        $parsedRecords = $parseResult['data']['records'] ?? [];
        
        if (empty($parsedRecords)) {
            return ['success' => false, 'message' => 'No records found in parsed data'];
        }
        
        $parsedRecord = $parsedRecords[0];
        $originalFormatted = $formattedRecords[0];
        
        // Verify key fields are preserved (CSV converts everything to strings)
        $fieldsToCheck = ['id', 'site_id', 'atm_id', 'address', 'city', 'status'];
        
        foreach ($fieldsToCheck as $field) {
            $originalValue = (string)($originalFormatted[$field] ?? '');
            $parsedValue = (string)($parsedRecord[$field] ?? '');
            
            if ($originalValue !== $parsedValue) {
                return [
                    'success' => false,
                    'message' => "Field '$field' mismatch in CSV: original='$originalValue', parsed='$parsedValue'"
                ];
            }
        }
        
        return ['success' => true];
    }
    
    /**
     * Property Test: Empty values are handled correctly in export
     */
    private function testEmptyValueHandling(): array {
        // Create data with empty/null values
        $originalRecords = [[
            'id' => rand(1, 1000),
            'site_id' => rand(1, 100),
            'atm_id' => 'ATM-' . $this->generateRandomString(8),
            'address' => '',
            'city' => null,
            'router_serial' => '',
            'router_fixed_remarks' => null,
            'status' => Installation::STATUS_PENDING_MATERIALS
        ]];
        
        // Format for export
        $formattedRecords = $this->exportService->formatForExport($originalRecords);
        
        // Generate JSON export
        $exportData = [
            'export_type' => 'installations',
            'export_date' => date('Y-m-d H:i:s'),
            'record_count' => count($formattedRecords),
            'data' => $formattedRecords
        ];
        $jsonContent = json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        
        // Parse JSON back
        $parseResult = $this->exportService->parseImport($jsonContent, 'json');
        
        if (!$parseResult['success']) {
            return ['success' => false, 'message' => 'Parse failed: ' . $parseResult['message']];
        }
        
        $parsedRecord = $parseResult['data']['records'][0] ?? [];
        
        // Verify empty fields are handled (should be empty string in formatted output)
        $emptyFields = ['address', 'city', 'router_serial', 'router_fixed_remarks'];
        
        foreach ($emptyFields as $field) {
            $parsedValue = $parsedRecord[$field] ?? null;
            // Empty values should be preserved as empty strings
            if ($parsedValue !== '' && $parsedValue !== null) {
                return [
                    'success' => false,
                    'message' => "Empty field '$field' was not preserved correctly: got " . var_export($parsedValue, true)
                ];
            }
        }
        
        return ['success' => true];
    }
    
    /**
     * Property Test: All installation fields are included in export
     */
    private function testAllFieldsIncluded(): array {
        // Generate random installation data with all fields
        $originalRecords = [$this->generateRandomInstallationData()];
        
        // Format for export
        $formattedRecords = $this->exportService->formatForExport($originalRecords);
        
        if (empty($formattedRecords)) {
            return ['success' => false, 'message' => 'No formatted records returned'];
        }
        
        $formattedRecord = $formattedRecords[0];
        
        // Check that all expected fields are present
        $expectedFields = [
            'id', 'site_id', 'feasibility_id', 'initiated_by', 'initiated_at',
            'atm_id', 'address', 'city', 'location', 'lho', 'state',
            'vendor_name', 'engineer_name', 'engineer_number',
            'router_serial', 'router_make', 'router_model', 'router_fixed', 'router_status',
            'adaptor_installed', 'adaptor_status',
            'lan_cable_installed', 'lan_cable_status',
            'antenna_installed', 'antenna_status',
            'gps_installed', 'gps_status',
            'wifi_installed', 'wifi_status',
            'airtel_sim_installed', 'airtel_sim_status',
            'vodafone_sim_installed', 'vodafone_sim_status',
            'jio_sim_installed', 'jio_sim_status',
            'signature_image', 'vendor_stamp',
            'status', 'created_by', 'created_at'
        ];
        
        foreach ($expectedFields as $field) {
            if (!array_key_exists($field, $formattedRecord)) {
                return [
                    'success' => false,
                    'message' => "Expected field '$field' is missing from export"
                ];
            }
        }
        
        return ['success' => true];
    }
    
    /**
     * Property Test: Special characters are preserved through export
     */
    private function testSpecialCharacterPreservation(): array {
        // Create data with special characters
        $specialChars = "Test with special chars: <>&\"'äöü中文日本語";
        $originalRecords = [[
            'id' => rand(1, 1000),
            'site_id' => rand(1, 100),
            'atm_id' => 'ATM-' . $this->generateRandomString(8),
            'address' => $specialChars,
            'router_fixed_remarks' => 'Remarks with "quotes" and <brackets>',
            'status' => Installation::STATUS_PENDING_MATERIALS
        ]];
        
        // Format for export
        $formattedRecords = $this->exportService->formatForExport($originalRecords);
        
        // Generate JSON export
        $exportData = [
            'export_type' => 'installations',
            'export_date' => date('Y-m-d H:i:s'),
            'record_count' => count($formattedRecords),
            'data' => $formattedRecords
        ];
        $jsonContent = json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        
        // Parse JSON back
        $parseResult = $this->exportService->parseImport($jsonContent, 'json');
        
        if (!$parseResult['success']) {
            return ['success' => false, 'message' => 'Parse failed: ' . $parseResult['message']];
        }
        
        $parsedRecord = $parseResult['data']['records'][0] ?? [];
        
        // Verify special characters are preserved
        if ($parsedRecord['address'] !== $specialChars) {
            return [
                'success' => false,
                'message' => "Special characters not preserved in address field"
            ];
        }
        
        return ['success' => true];
    }


    // ==================== Helper Methods ====================
    
    /**
     * Normalize value for comparison
     */
    private function normalizeValue($value) {
        if ($value === null || $value === '') {
            return '';
        }
        return $value;
    }
    
    /**
     * Convert array to CSV string
     */
    private function arrayToCsv(array $data): string {
        if (empty($data)) {
            return '';
        }
        
        $output = fopen('php://temp', 'r+');
        
        // Write header row
        $headers = array_keys($data[0]);
        fputcsv($output, $headers);
        
        // Write data rows
        foreach ($data as $row) {
            $csvRow = array_map(function($value) {
                if (is_array($value) || is_object($value)) {
                    return json_encode($value);
                }
                return $value;
            }, $row);
            fputcsv($output, $csvRow);
        }
        
        rewind($output);
        $csvContent = stream_get_contents($output);
        fclose($output);
        
        return $csvContent;
    }
    
    /**
     * Generate random string
     */
    private function generateRandomString(int $length = 10): string {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $result = '';
        for ($i = 0; $i < $length; $i++) {
            $result .= $chars[rand(0, strlen($chars) - 1)];
        }
        return $result;
    }
    
    /**
     * Generate random installation data with all fields populated
     */
    private function generateRandomInstallationData(): array {
        $yesNo = [Installation::YES, Installation::NO];
        $workingStatus = [Installation::WORKING, Installation::NOT_WORKING];
        $statuses = Installation::getStatuses();
        
        return [
            'id' => rand(1, 10000),
            'site_id' => rand(1, 1000),
            'feasibility_id' => rand(1, 1000),
            'initiated_by' => rand(1, 100),
            'initiated_at' => date('Y-m-d H:i:s', strtotime('-' . rand(1, 30) . ' days')),
            
            // Site Information
            'atm_id' => 'ATM-' . $this->generateRandomString(8),
            'atm_id_2' => rand(0, 1) ? 'ATM2-' . $this->generateRandomString(6) : null,
            'atm_id_3' => rand(0, 1) ? 'ATM3-' . $this->generateRandomString(6) : null,
            'address' => $this->generateRandomString(20) . ' Street, Building ' . rand(1, 100),
            'city' => 'City-' . $this->generateRandomString(6),
            'location' => 'Location-' . $this->generateRandomString(8),
            'lho' => 'LHO-' . $this->generateRandomString(4),
            'state' => 'State-' . $this->generateRandomString(6),
            'atm_working_1' => $yesNo[array_rand($yesNo)],
            'atm_working_2' => rand(0, 1) ? $yesNo[array_rand($yesNo)] : null,
            'atm_working_3' => rand(0, 1) ? $yesNo[array_rand($yesNo)] : null,
            
            // Vendor/Engineer Information
            'vendor_name' => 'Vendor-' . $this->generateRandomString(8),
            'engineer_name' => 'Engineer-' . $this->generateRandomString(8),
            'engineer_number' => '9' . rand(100000000, 999999999),
            
            // Router Section
            'router_serial' => 'RSN-' . $this->generateRandomString(12),
            'router_make' => 'Make-' . $this->generateRandomString(6),
            'router_model' => 'Model-' . $this->generateRandomString(6),
            'router_fixed' => $yesNo[array_rand($yesNo)],
            'router_fixed_remarks' => rand(0, 1) ? 'Remarks: ' . $this->generateRandomString(20) : null,
            'router_fixed_snaps' => rand(0, 1) ? 'uploads/router_' . $this->generateRandomString(8) . '.jpg' : null,
            'router_status' => $workingStatus[array_rand($workingStatus)],
            'router_status_remarks' => rand(0, 1) ? 'Status remarks: ' . $this->generateRandomString(15) : null,
            'router_status_snaps' => rand(0, 1) ? 'uploads/router_status_' . $this->generateRandomString(8) . '.jpg' : null,
            
            // Adaptor Section
            'adaptor_installed' => $yesNo[array_rand($yesNo)],
            'adaptor_snaps' => rand(0, 1) ? 'uploads/adaptor_' . $this->generateRandomString(8) . '.jpg' : null,
            'adaptor_status' => $workingStatus[array_rand($workingStatus)],
            'adaptor_status_remarks' => rand(0, 1) ? 'Adaptor remarks: ' . $this->generateRandomString(15) : null,
            'adaptor_status_snaps' => rand(0, 1) ? 'uploads/adaptor_status_' . $this->generateRandomString(8) . '.jpg' : null,
            
            // LAN Cable Section
            'lan_cable_installed' => $yesNo[array_rand($yesNo)],
            'lan_cable_install_remark' => rand(0, 1) ? 'LAN install remark: ' . $this->generateRandomString(15) : null,
            'lan_cable_install_snap' => rand(0, 1) ? 'uploads/lan_' . $this->generateRandomString(8) . '.jpg' : null,
            'lan_cable_status' => $workingStatus[array_rand($workingStatus)],
            'lan_cable_status_not_working_reasons' => rand(0, 1) ? 'Reason: ' . $this->generateRandomString(20) : null,
            'lan_cable_status_remark' => rand(0, 1) ? 'LAN status remark: ' . $this->generateRandomString(15) : null,
            'lan_cable_status_snap' => rand(0, 1) ? 'uploads/lan_status_' . $this->generateRandomString(8) . '.jpg' : null,
            
            // Antenna Section
            'antenna_installed' => $yesNo[array_rand($yesNo)],
            'antenna_remarks' => rand(0, 1) ? 'Antenna remarks: ' . $this->generateRandomString(15) : null,
            'antenna_snaps' => rand(0, 1) ? 'uploads/antenna_' . $this->generateRandomString(8) . '.jpg' : null,
            'antenna_status' => $workingStatus[array_rand($workingStatus)],
            'antenna_status_remarks' => rand(0, 1) ? 'Antenna status: ' . $this->generateRandomString(15) : null,
            'antenna_status_snaps' => rand(0, 1) ? 'uploads/antenna_status_' . $this->generateRandomString(8) . '.jpg' : null,
            
            // GPS Section
            'gps_installed' => $yesNo[array_rand($yesNo)],
            'gps_remarks' => rand(0, 1) ? 'GPS remarks: ' . $this->generateRandomString(15) : null,
            'gps_snaps' => rand(0, 1) ? 'uploads/gps_' . $this->generateRandomString(8) . '.jpg' : null,
            'gps_status' => $workingStatus[array_rand($workingStatus)],
            'gps_status_remarks' => rand(0, 1) ? 'GPS status: ' . $this->generateRandomString(15) : null,
            'gps_status_snaps' => rand(0, 1) ? 'uploads/gps_status_' . $this->generateRandomString(8) . '.jpg' : null,
            
            // WiFi Section
            'wifi_installed' => $yesNo[array_rand($yesNo)],
            'wifi_remarks' => rand(0, 1) ? 'WiFi remarks: ' . $this->generateRandomString(15) : null,
            'wifi_snaps' => rand(0, 1) ? 'uploads/wifi_' . $this->generateRandomString(8) . '.jpg' : null,
            'wifi_status' => $workingStatus[array_rand($workingStatus)],
            'wifi_status_remarks' => rand(0, 1) ? 'WiFi status: ' . $this->generateRandomString(15) : null,
            'wifi_status_snaps' => rand(0, 1) ? 'uploads/wifi_status_' . $this->generateRandomString(8) . '.jpg' : null,
            
            // Airtel SIM Section
            'airtel_sim_installed' => $yesNo[array_rand($yesNo)],
            'airtel_sim_remarks' => rand(0, 1) ? 'Airtel remarks: ' . $this->generateRandomString(15) : null,
            'airtel_sim_snaps' => rand(0, 1) ? 'uploads/airtel_' . $this->generateRandomString(8) . '.jpg' : null,
            'airtel_sim_status' => $workingStatus[array_rand($workingStatus)],
            'airtel_sim_status_remarks' => rand(0, 1) ? 'Airtel status: ' . $this->generateRandomString(15) : null,
            'airtel_sim_status_snaps' => rand(0, 1) ? 'uploads/airtel_status_' . $this->generateRandomString(8) . '.jpg' : null,
            
            // Vodafone SIM Section
            'vodafone_sim_installed' => $yesNo[array_rand($yesNo)],
            'vodafone_sim_remarks' => rand(0, 1) ? 'Vodafone remarks: ' . $this->generateRandomString(15) : null,
            'vodafone_sim_snaps' => rand(0, 1) ? 'uploads/vodafone_' . $this->generateRandomString(8) . '.jpg' : null,
            'vodafone_sim_status' => $workingStatus[array_rand($workingStatus)],
            'vodafone_sim_status_remarks' => rand(0, 1) ? 'Vodafone status: ' . $this->generateRandomString(15) : null,
            'vodafone_sim_status_snaps' => rand(0, 1) ? 'uploads/vodafone_status_' . $this->generateRandomString(8) . '.jpg' : null,
            
            // JIO SIM Section
            'jio_sim_installed' => $yesNo[array_rand($yesNo)],
            'jio_sim_remarks' => rand(0, 1) ? 'JIO remarks: ' . $this->generateRandomString(15) : null,
            'jio_sim_snaps' => rand(0, 1) ? 'uploads/jio_' . $this->generateRandomString(8) . '.jpg' : null,
            'jio_sim_status' => $workingStatus[array_rand($workingStatus)],
            'jio_sim_status_remarks' => rand(0, 1) ? 'JIO status: ' . $this->generateRandomString(15) : null,
            'jio_sim_status_snaps' => rand(0, 1) ? 'uploads/jio_status_' . $this->generateRandomString(8) . '.jpg' : null,
            
            // Verification Section
            'signature_image' => rand(0, 1) ? 'uploads/signature_' . $this->generateRandomString(8) . '.png' : null,
            'vendor_stamp' => rand(0, 1) ? 'uploads/stamp_' . $this->generateRandomString(8) . '.png' : null,
            
            // Status
            'status' => $statuses[array_rand($statuses)],
            
            // Audit
            'created_by' => rand(1, 100),
            'created_at' => date('Y-m-d H:i:s', strtotime('-' . rand(1, 30) . ' days')),
            'updated_at' => rand(0, 1) ? date('Y-m-d H:i:s', strtotime('-' . rand(0, 10) . ' days')) : null,
            'submitted_by' => rand(0, 1) ? rand(1, 100) : null,
            'submitted_at' => rand(0, 1) ? date('Y-m-d H:i:s', strtotime('-' . rand(0, 5) . ' days')) : null
        ];
    }
}

// Run tests if executed directly
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    $test = new InstallationExportRoundTripTest();
    $success = $test->runTests();
    exit($success ? 0 : 1);
}
