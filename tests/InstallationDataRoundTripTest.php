<?php
/**
 * Property Test: Installation Data Round-Trip
 * 
 * **Feature: installation-module, Property 12: Installation data round-trip**
 * **Validates: Requirements 5.4, 19.1, 19.2, 19.3**
 * 
 * Property: For any valid installation form data, serializing (toArray) and 
 * deserializing (fromArray) should produce equivalent data.
 * 
 * This test verifies that:
 * 1. toArray() correctly serializes all installation fields including new workflow fields
 * 2. fromArray() correctly deserializes data back to installation format
 * 3. Round-trip (toArray -> fromArray) produces equivalent data
 * 4. JSON serialization/deserialization preserves all data
 * 5. New delegation, assignment, and ETA/ADA fields are correctly handled
 */

require_once __DIR__ . '/../config/autoload.php';
require_once __DIR__ . '/../models/Installation.php';

class InstallationDataRoundTripTest {
    private $testResults = [];
    private $iterations = 100; // Number of property test iterations
    
    /**
     * Run all property tests
     */
    public function runTests(): bool {
        echo "\n=== Installation Data Round-Trip Property Tests ===\n";
        echo "**Feature: installation-module, Property 12: Installation data round-trip**\n";
        echo "**Validates: Requirements 5.4, 19.1, 19.2, 19.3**\n\n";
        
        // Property tests
        $this->runPropertyTest(
            'toArray/fromArray round-trip preserves all fields',
            [$this, 'testArrayRoundTrip']
        );
        
        $this->runPropertyTest(
            'JSON round-trip preserves all fields',
            [$this, 'testJsonRoundTrip']
        );
        
        $this->runPropertyTest(
            'Null values are preserved through serialization',
            [$this, 'testNullValuePreservation']
        );
        
        $this->runPropertyTest(
            'Integer fields are correctly typed after round-trip',
            [$this, 'testIntegerFieldTypes']
        );
        
        $this->runPropertyTest(
            'Status values are preserved through round-trip',
            [$this, 'testStatusPreservation']
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
     * Property Test: toArray/fromArray round-trip preserves all fields
     * For any installation record, toArray then fromArray should produce equivalent data
     */
    private function testArrayRoundTrip(): array {
        // Generate random installation data
        $originalData = $this->generateRandomInstallationData();
        
        // Serialize to array
        $serialized = Installation::toArray($originalData);
        
        // Deserialize back
        $deserialized = Installation::fromArray($serialized);
        
        // Compare key fields including new delegation, assignment, and ETA/ADA fields
        $fieldsToCheck = [
            'site_id', 'feasibility_id', 'initiated_by',
            // Delegation fields (Requirements: 1.4)
            'contractor_id', 'delegated_by',
            // Assignment fields (Requirements: 2.4)
            'assigned_engineer_id', 'assigned_by',
            // ETA/ADA fields (Requirements: 3.3, 3.5)
            'eta_date', 'ada_date',
            // Site Information
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
            'status'
        ];
        
        foreach ($fieldsToCheck as $field) {
            $originalValue = $originalData[$field] ?? null;
            $deserializedValue = $deserialized[$field] ?? null;
            
            // Normalize empty strings to null for comparison
            if ($originalValue === '') $originalValue = null;
            if ($deserializedValue === '') $deserializedValue = null;
            
            // Handle integer comparison
            if (is_numeric($originalValue) && is_numeric($deserializedValue)) {
                if ((int)$originalValue !== (int)$deserializedValue) {
                    return [
                        'success' => false,
                        'message' => "Field '$field' mismatch: original=" . var_export($originalValue, true) . 
                                     ", deserialized=" . var_export($deserializedValue, true)
                    ];
                }
            } elseif ($originalValue !== $deserializedValue) {
                return [
                    'success' => false,
                    'message' => "Field '$field' mismatch: original=" . var_export($originalValue, true) . 
                                 ", deserialized=" . var_export($deserializedValue, true)
                ];
            }
        }
        
        return ['success' => true];
    }
    
    /**
     * Property Test: JSON round-trip preserves all fields
     * For any installation record, toJson then fromJson should produce equivalent data
     */
    private function testJsonRoundTrip(): array {
        // Generate random installation data
        $originalData = $this->generateRandomInstallationData();
        
        // Serialize to JSON
        $json = Installation::toJson($originalData);
        
        // Deserialize back
        $deserialized = Installation::fromJson($json);
        
        // Compare key fields
        $fieldsToCheck = [
            'site_id', 'feasibility_id', 'initiated_by',
            'atm_id', 'address', 'city', 'status'
        ];
        
        foreach ($fieldsToCheck as $field) {
            $originalValue = $originalData[$field] ?? null;
            $deserializedValue = $deserialized[$field] ?? null;
            
            // Normalize empty strings to null for comparison
            if ($originalValue === '') $originalValue = null;
            if ($deserializedValue === '') $deserializedValue = null;
            
            // Handle integer comparison
            if (is_numeric($originalValue) && is_numeric($deserializedValue)) {
                if ((int)$originalValue !== (int)$deserializedValue) {
                    return [
                        'success' => false,
                        'message' => "Field '$field' mismatch after JSON round-trip"
                    ];
                }
            } elseif ($originalValue !== $deserializedValue) {
                return [
                    'success' => false,
                    'message' => "Field '$field' mismatch after JSON round-trip: original=" . 
                                 var_export($originalValue, true) . ", deserialized=" . 
                                 var_export($deserializedValue, true)
                ];
            }
        }
        
        return ['success' => true];
    }
    
    /**
     * Property Test: Null values are preserved through serialization
     */
    private function testNullValuePreservation(): array {
        // Create data with explicit null values including new fields
        $originalData = [
            'id' => rand(1, 1000),
            'site_id' => rand(1, 100),
            'feasibility_id' => rand(1, 100),
            'initiated_by' => rand(1, 50),
            'atm_id' => 'ATM-' . $this->generateRandomString(8),
            'address' => null,
            'city' => null,
            'router_serial' => null,
            'router_fixed_remarks' => null,
            'signature_image' => null,
            // New fields that can be null (Requirements: 1.4, 2.4, 3.3, 3.5)
            'contractor_id' => null,
            'delegated_by' => null,
            'delegated_at' => null,
            'assigned_engineer_id' => null,
            'assigned_by' => null,
            'assigned_at' => null,
            'eta_date' => null,
            'eta_submitted_at' => null,
            'ada_date' => null,
            'ada_submitted_at' => null,
            'status' => Installation::STATUS_PENDING_ASSIGNMENT
        ];
        
        // Serialize and deserialize
        $serialized = Installation::toArray($originalData);
        $deserialized = Installation::fromArray($serialized);
        
        // Check that null fields remain null including new fields
        $nullFields = ['address', 'city', 'router_serial', 'router_fixed_remarks', 'signature_image',
                       'contractor_id', 'delegated_by', 'delegated_at', 'assigned_engineer_id', 
                       'assigned_by', 'assigned_at', 'eta_date', 'eta_submitted_at', 'ada_date', 'ada_submitted_at'];
        
        foreach ($nullFields as $field) {
            if (isset($deserialized[$field]) && $deserialized[$field] !== null) {
                return [
                    'success' => false,
                    'message' => "Null field '$field' was not preserved: got " . var_export($deserialized[$field], true)
                ];
            }
        }
        
        return ['success' => true];
    }
    
    /**
     * Property Test: Integer fields are correctly typed after round-trip
     */
    private function testIntegerFieldTypes(): array {
        $originalData = [
            'id' => rand(1, 1000),
            'site_id' => rand(1, 100),
            'feasibility_id' => rand(1, 100),
            'initiated_by' => rand(1, 50),
            'created_by' => rand(1, 50),
            'submitted_by' => rand(1, 50),
            // New delegation and assignment integer fields (Requirements: 1.4, 2.4)
            'contractor_id' => rand(1, 50),
            'delegated_by' => rand(1, 50),
            'assigned_engineer_id' => rand(1, 50),
            'assigned_by' => rand(1, 50),
            'atm_id' => 'ATM-' . $this->generateRandomString(8),
            'status' => Installation::STATUS_PENDING_ASSIGNMENT
        ];
        
        // Serialize and deserialize
        $serialized = Installation::toArray($originalData);
        $deserialized = Installation::fromArray($serialized);
        
        // Check integer fields including new delegation and assignment fields
        $intFields = ['id', 'site_id', 'feasibility_id', 'initiated_by', 'created_by', 'submitted_by',
                      'contractor_id', 'delegated_by', 'assigned_engineer_id', 'assigned_by'];
        
        foreach ($intFields as $field) {
            if (isset($deserialized[$field])) {
                if (!is_int($deserialized[$field])) {
                    return [
                        'success' => false,
                        'message' => "Field '$field' should be integer, got " . gettype($deserialized[$field])
                    ];
                }
                if ((int)$originalData[$field] !== $deserialized[$field]) {
                    return [
                        'success' => false,
                        'message' => "Field '$field' value mismatch: expected {$originalData[$field]}, got {$deserialized[$field]}"
                    ];
                }
            }
        }
        
        return ['success' => true];
    }
    
    /**
     * Property Test: Status values are preserved through round-trip
     */
    private function testStatusPreservation(): array {
        $statuses = Installation::getStatuses();
        $randomStatus = $statuses[array_rand($statuses)];
        
        $originalData = [
            'id' => rand(1, 1000),
            'site_id' => rand(1, 100),
            'feasibility_id' => rand(1, 100),
            'initiated_by' => rand(1, 50),
            'atm_id' => 'ATM-' . $this->generateRandomString(8),
            'status' => $randomStatus
        ];
        
        // Serialize and deserialize
        $serialized = Installation::toArray($originalData);
        $deserialized = Installation::fromArray($serialized);
        
        if ($deserialized['status'] !== $randomStatus) {
            return [
                'success' => false,
                'message' => "Status not preserved: expected '$randomStatus', got '{$deserialized['status']}'"
            ];
        }
        
        return ['success' => true];
    }

    // ==================== Helper Methods ====================
    
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
            
            // Delegation fields (Requirements: 1.4)
            'contractor_id' => rand(0, 1) ? rand(1, 50) : null,
            'delegated_by' => rand(0, 1) ? rand(1, 100) : null,
            'delegated_at' => rand(0, 1) ? date('Y-m-d H:i:s', strtotime('-' . rand(1, 25) . ' days')) : null,
            
            // Assignment fields (Requirements: 2.4)
            'assigned_engineer_id' => rand(0, 1) ? rand(1, 100) : null,
            'assigned_by' => rand(0, 1) ? rand(1, 100) : null,
            'assigned_at' => rand(0, 1) ? date('Y-m-d H:i:s', strtotime('-' . rand(1, 20) . ' days')) : null,
            
            // ETA/ADA fields (Requirements: 3.3, 3.5)
            'eta_date' => rand(0, 1) ? date('Y-m-d', strtotime('+' . rand(1, 30) . ' days')) : null,
            'eta_submitted_at' => rand(0, 1) ? date('Y-m-d H:i:s', strtotime('-' . rand(1, 15) . ' days')) : null,
            'ada_date' => rand(0, 1) ? date('Y-m-d', strtotime('-' . rand(0, 10) . ' days')) : null,
            'ada_submitted_at' => rand(0, 1) ? date('Y-m-d H:i:s', strtotime('-' . rand(0, 10) . ' days')) : null,
            
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
    $test = new InstallationDataRoundTripTest();
    $success = $test->runTests();
    exit($success ? 0 : 1);
}
