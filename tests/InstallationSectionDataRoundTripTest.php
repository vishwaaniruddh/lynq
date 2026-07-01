<?php
/**
 * Property Test: Section Data Round-Trip
 * 
 * **Feature: installation-module, Property 14: Section data round-trip (Router, Adaptor, LAN, Antenna, GPS, WiFi, SIMs)**
 * **Validates: Requirements 6.1-6.5, 7.1-7.3, 8.1-8.3, 9.1-9.3, 10.1-10.3, 11.1-11.3, 12.1-12.4**
 * 
 * Property: For any valid section data (router, adaptor, LAN cable, antenna, GPS, WiFi, 
 * Airtel SIM, Vodafone SIM, JIO SIM), saving and retrieving the section should return 
 * equivalent data with all fields intact.
 * 
 * This test verifies that:
 * 1. Each section's data is correctly serialized and deserialized
 * 2. All section-specific fields are preserved through round-trip
 * 3. Image paths are correctly stored and retrieved
 * 4. Yes/No and Working/NotWorking enum values are preserved
 */

require_once __DIR__ . '/../config/autoload.php';
require_once __DIR__ . '/../models/Installation.php';

class InstallationSectionDataRoundTripTest {
    private $testResults = [];
    private $iterations = 100;
    
    /**
     * Run all property tests
     */
    public function runTests(): bool {
        echo "\n=== Installation Section Data Round-Trip Property Tests ===\n";
        echo "**Feature: installation-module, Property 14: Section data round-trip**\n";
        echo "**Validates: Requirements 6.1-6.5, 7.1-7.3, 8.1-8.3, 9.1-9.3, 10.1-10.3, 11.1-11.3, 12.1-12.4**\n\n";
        
        // Test each section
        $this->runPropertyTest(
            'Router section data round-trip preserves all fields',
            [$this, 'testRouterSectionRoundTrip']
        );
        
        $this->runPropertyTest(
            'Adaptor section data round-trip preserves all fields',
            [$this, 'testAdaptorSectionRoundTrip']
        );
        
        $this->runPropertyTest(
            'LAN Cable section data round-trip preserves all fields',
            [$this, 'testLanCableSectionRoundTrip']
        );
        
        $this->runPropertyTest(
            'Antenna section data round-trip preserves all fields',
            [$this, 'testAntennaSectionRoundTrip']
        );
        
        $this->runPropertyTest(
            'GPS section data round-trip preserves all fields',
            [$this, 'testGpsSectionRoundTrip']
        );
        
        $this->runPropertyTest(
            'WiFi section data round-trip preserves all fields',
            [$this, 'testWifiSectionRoundTrip']
        );
        
        $this->runPropertyTest(
            'Airtel SIM section data round-trip preserves all fields',
            [$this, 'testAirtelSimSectionRoundTrip']
        );
        
        $this->runPropertyTest(
            'Vodafone SIM section data round-trip preserves all fields',
            [$this, 'testVodafoneSimSectionRoundTrip']
        );
        
        $this->runPropertyTest(
            'JIO SIM section data round-trip preserves all fields',
            [$this, 'testJioSimSectionRoundTrip']
        );
        
        $this->runPropertyTest(
            'All sections combined round-trip preserves all fields',
            [$this, 'testAllSectionsCombinedRoundTrip']
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
     * Property Test: Router section data round-trip
     * Requirements: 6.1-6.5
     */
    private function testRouterSectionRoundTrip(): array {
        $sectionData = $this->generateRouterSectionData();
        $baseData = $this->generateBaseInstallationData();
        $originalData = array_merge($baseData, $sectionData);
        
        $serialized = Installation::toArray($originalData);
        $deserialized = Installation::fromArray($serialized);
        
        $routerFields = [
            'router_serial', 'router_make', 'router_model',
            'router_fixed', 'router_fixed_remarks', 'router_fixed_snaps',
            'router_status', 'router_status_remarks', 'router_status_snaps'
        ];
        
        return $this->compareFields($originalData, $deserialized, $routerFields, 'Router');
    }
    
    /**
     * Property Test: Adaptor section data round-trip
     * Requirements: 7.1-7.3
     */
    private function testAdaptorSectionRoundTrip(): array {
        $sectionData = $this->generateAdaptorSectionData();
        $baseData = $this->generateBaseInstallationData();
        $originalData = array_merge($baseData, $sectionData);
        
        $serialized = Installation::toArray($originalData);
        $deserialized = Installation::fromArray($serialized);
        
        $adaptorFields = [
            'adaptor_installed', 'adaptor_snaps',
            'adaptor_status', 'adaptor_status_remarks', 'adaptor_status_snaps'
        ];
        
        return $this->compareFields($originalData, $deserialized, $adaptorFields, 'Adaptor');
    }
    
    /**
     * Property Test: LAN Cable section data round-trip
     * Requirements: 8.1-8.3
     */
    private function testLanCableSectionRoundTrip(): array {
        $sectionData = $this->generateLanCableSectionData();
        $baseData = $this->generateBaseInstallationData();
        $originalData = array_merge($baseData, $sectionData);
        
        $serialized = Installation::toArray($originalData);
        $deserialized = Installation::fromArray($serialized);
        
        $lanFields = [
            'lan_cable_installed', 'lan_cable_install_remark', 'lan_cable_install_snap',
            'lan_cable_status', 'lan_cable_status_not_working_reasons',
            'lan_cable_status_remark', 'lan_cable_status_snap'
        ];
        
        return $this->compareFields($originalData, $deserialized, $lanFields, 'LAN Cable');
    }
    
    /**
     * Property Test: Antenna section data round-trip
     * Requirements: 9.1-9.3
     */
    private function testAntennaSectionRoundTrip(): array {
        $sectionData = $this->generateAntennaSectionData();
        $baseData = $this->generateBaseInstallationData();
        $originalData = array_merge($baseData, $sectionData);
        
        $serialized = Installation::toArray($originalData);
        $deserialized = Installation::fromArray($serialized);
        
        $antennaFields = [
            'antenna_installed', 'antenna_remarks', 'antenna_snaps',
            'antenna_status', 'antenna_status_remarks', 'antenna_status_snaps'
        ];
        
        return $this->compareFields($originalData, $deserialized, $antennaFields, 'Antenna');
    }
    
    /**
     * Property Test: GPS section data round-trip
     * Requirements: 10.1-10.3
     */
    private function testGpsSectionRoundTrip(): array {
        $sectionData = $this->generateGpsSectionData();
        $baseData = $this->generateBaseInstallationData();
        $originalData = array_merge($baseData, $sectionData);
        
        $serialized = Installation::toArray($originalData);
        $deserialized = Installation::fromArray($serialized);
        
        $gpsFields = [
            'gps_installed', 'gps_remarks', 'gps_snaps',
            'gps_status', 'gps_status_remarks', 'gps_status_snaps'
        ];
        
        return $this->compareFields($originalData, $deserialized, $gpsFields, 'GPS');
    }
    
    /**
     * Property Test: WiFi section data round-trip
     * Requirements: 11.1-11.3
     */
    private function testWifiSectionRoundTrip(): array {
        $sectionData = $this->generateWifiSectionData();
        $baseData = $this->generateBaseInstallationData();
        $originalData = array_merge($baseData, $sectionData);
        
        $serialized = Installation::toArray($originalData);
        $deserialized = Installation::fromArray($serialized);
        
        $wifiFields = [
            'wifi_installed', 'wifi_remarks', 'wifi_snaps',
            'wifi_status', 'wifi_status_remarks', 'wifi_status_snaps'
        ];
        
        return $this->compareFields($originalData, $deserialized, $wifiFields, 'WiFi');
    }
    
    /**
     * Property Test: Airtel SIM section data round-trip
     * Requirements: 12.1-12.4
     */
    private function testAirtelSimSectionRoundTrip(): array {
        $sectionData = $this->generateAirtelSimSectionData();
        $baseData = $this->generateBaseInstallationData();
        $originalData = array_merge($baseData, $sectionData);
        
        $serialized = Installation::toArray($originalData);
        $deserialized = Installation::fromArray($serialized);
        
        $airtelFields = [
            'airtel_sim_installed', 'airtel_sim_remarks', 'airtel_sim_snaps',
            'airtel_sim_status', 'airtel_sim_status_remarks', 'airtel_sim_status_snaps'
        ];
        
        return $this->compareFields($originalData, $deserialized, $airtelFields, 'Airtel SIM');
    }
    
    /**
     * Property Test: Vodafone SIM section data round-trip
     * Requirements: 12.1-12.4
     */
    private function testVodafoneSimSectionRoundTrip(): array {
        $sectionData = $this->generateVodafoneSimSectionData();
        $baseData = $this->generateBaseInstallationData();
        $originalData = array_merge($baseData, $sectionData);
        
        $serialized = Installation::toArray($originalData);
        $deserialized = Installation::fromArray($serialized);
        
        $vodafoneFields = [
            'vodafone_sim_installed', 'vodafone_sim_remarks', 'vodafone_sim_snaps',
            'vodafone_sim_status', 'vodafone_sim_status_remarks', 'vodafone_sim_status_snaps'
        ];
        
        return $this->compareFields($originalData, $deserialized, $vodafoneFields, 'Vodafone SIM');
    }
    
    /**
     * Property Test: JIO SIM section data round-trip
     * Requirements: 12.1-12.4
     */
    private function testJioSimSectionRoundTrip(): array {
        $sectionData = $this->generateJioSimSectionData();
        $baseData = $this->generateBaseInstallationData();
        $originalData = array_merge($baseData, $sectionData);
        
        $serialized = Installation::toArray($originalData);
        $deserialized = Installation::fromArray($serialized);
        
        $jioFields = [
            'jio_sim_installed', 'jio_sim_remarks', 'jio_sim_snaps',
            'jio_sim_status', 'jio_sim_status_remarks', 'jio_sim_status_snaps'
        ];
        
        return $this->compareFields($originalData, $deserialized, $jioFields, 'JIO SIM');
    }
    
    /**
     * Property Test: All sections combined round-trip
     */
    private function testAllSectionsCombinedRoundTrip(): array {
        $originalData = array_merge(
            $this->generateBaseInstallationData(),
            $this->generateRouterSectionData(),
            $this->generateAdaptorSectionData(),
            $this->generateLanCableSectionData(),
            $this->generateAntennaSectionData(),
            $this->generateGpsSectionData(),
            $this->generateWifiSectionData(),
            $this->generateAirtelSimSectionData(),
            $this->generateVodafoneSimSectionData(),
            $this->generateJioSimSectionData()
        );
        
        $serialized = Installation::toArray($originalData);
        $deserialized = Installation::fromArray($serialized);
        
        // Check all section fields
        $allFields = array_keys($originalData);
        
        return $this->compareFields($originalData, $deserialized, $allFields, 'All Sections');
    }
    
    // ==================== Helper Methods ====================
    
    /**
     * Compare fields between original and deserialized data
     */
    private function compareFields(array $original, array $deserialized, array $fields, string $sectionName): array {
        foreach ($fields as $field) {
            $originalValue = $original[$field] ?? null;
            $deserializedValue = $deserialized[$field] ?? null;
            
            // Normalize empty strings to null
            if ($originalValue === '') $originalValue = null;
            if ($deserializedValue === '') $deserializedValue = null;
            
            // Handle integer comparison
            if (is_numeric($originalValue) && is_numeric($deserializedValue)) {
                if ((int)$originalValue !== (int)$deserializedValue) {
                    return [
                        'success' => false,
                        'message' => "$sectionName: Field '$field' mismatch: original=" . 
                                     var_export($originalValue, true) . ", deserialized=" . 
                                     var_export($deserializedValue, true)
                    ];
                }
            } elseif ($originalValue !== $deserializedValue) {
                return [
                    'success' => false,
                    'message' => "$sectionName: Field '$field' mismatch: original=" . 
                                 var_export($originalValue, true) . ", deserialized=" . 
                                 var_export($deserializedValue, true)
                ];
            }
        }
        
        return ['success' => true];
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
     * Get random yes/no value
     */
    private function getRandomYesNo(): string {
        return rand(0, 1) ? Installation::YES : Installation::NO;
    }
    
    /**
     * Get random working status
     */
    private function getRandomWorkingStatus(): string {
        return rand(0, 1) ? Installation::WORKING : Installation::NOT_WORKING;
    }
    
    /**
     * Generate base installation data
     */
    private function generateBaseInstallationData(): array {
        return [
            'id' => rand(1, 10000),
            'site_id' => rand(1, 1000),
            'feasibility_id' => rand(1, 1000),
            'initiated_by' => rand(1, 100),
            'atm_id' => 'ATM-' . $this->generateRandomString(8),
            'status' => Installation::STATUS_IN_PROGRESS
        ];
    }
    
    /**
     * Generate router section data
     */
    private function generateRouterSectionData(): array {
        return [
            'router_serial' => 'RSN-' . $this->generateRandomString(12),
            'router_make' => 'Make-' . $this->generateRandomString(6),
            'router_model' => 'Model-' . $this->generateRandomString(6),
            'router_fixed' => $this->getRandomYesNo(),
            'router_fixed_remarks' => rand(0, 1) ? 'Remarks: ' . $this->generateRandomString(20) : null,
            'router_fixed_snaps' => rand(0, 1) ? 'uploads/router_' . $this->generateRandomString(8) . '.jpg' : null,
            'router_status' => $this->getRandomWorkingStatus(),
            'router_status_remarks' => rand(0, 1) ? 'Status: ' . $this->generateRandomString(15) : null,
            'router_status_snaps' => rand(0, 1) ? 'uploads/router_status_' . $this->generateRandomString(8) . '.jpg' : null
        ];
    }
    
    /**
     * Generate adaptor section data
     */
    private function generateAdaptorSectionData(): array {
        return [
            'adaptor_installed' => $this->getRandomYesNo(),
            'adaptor_snaps' => rand(0, 1) ? 'uploads/adaptor_' . $this->generateRandomString(8) . '.jpg' : null,
            'adaptor_status' => $this->getRandomWorkingStatus(),
            'adaptor_status_remarks' => rand(0, 1) ? 'Remarks: ' . $this->generateRandomString(15) : null,
            'adaptor_status_snaps' => rand(0, 1) ? 'uploads/adaptor_status_' . $this->generateRandomString(8) . '.jpg' : null
        ];
    }
    
    /**
     * Generate LAN cable section data
     */
    private function generateLanCableSectionData(): array {
        return [
            'lan_cable_installed' => $this->getRandomYesNo(),
            'lan_cable_install_remark' => rand(0, 1) ? 'Install remark: ' . $this->generateRandomString(15) : null,
            'lan_cable_install_snap' => rand(0, 1) ? 'uploads/lan_' . $this->generateRandomString(8) . '.jpg' : null,
            'lan_cable_status' => $this->getRandomWorkingStatus(),
            'lan_cable_status_not_working_reasons' => rand(0, 1) ? 'Reason: ' . $this->generateRandomString(20) : null,
            'lan_cable_status_remark' => rand(0, 1) ? 'Status remark: ' . $this->generateRandomString(15) : null,
            'lan_cable_status_snap' => rand(0, 1) ? 'uploads/lan_status_' . $this->generateRandomString(8) . '.jpg' : null
        ];
    }
    
    /**
     * Generate antenna section data
     */
    private function generateAntennaSectionData(): array {
        return [
            'antenna_installed' => $this->getRandomYesNo(),
            'antenna_remarks' => rand(0, 1) ? 'Remarks: ' . $this->generateRandomString(15) : null,
            'antenna_snaps' => rand(0, 1) ? 'uploads/antenna_' . $this->generateRandomString(8) . '.jpg' : null,
            'antenna_status' => $this->getRandomWorkingStatus(),
            'antenna_status_remarks' => rand(0, 1) ? 'Status: ' . $this->generateRandomString(15) : null,
            'antenna_status_snaps' => rand(0, 1) ? 'uploads/antenna_status_' . $this->generateRandomString(8) . '.jpg' : null
        ];
    }
    
    /**
     * Generate GPS section data
     */
    private function generateGpsSectionData(): array {
        return [
            'gps_installed' => $this->getRandomYesNo(),
            'gps_remarks' => rand(0, 1) ? 'Remarks: ' . $this->generateRandomString(15) : null,
            'gps_snaps' => rand(0, 1) ? 'uploads/gps_' . $this->generateRandomString(8) . '.jpg' : null,
            'gps_status' => $this->getRandomWorkingStatus(),
            'gps_status_remarks' => rand(0, 1) ? 'Status: ' . $this->generateRandomString(15) : null,
            'gps_status_snaps' => rand(0, 1) ? 'uploads/gps_status_' . $this->generateRandomString(8) . '.jpg' : null
        ];
    }
    
    /**
     * Generate WiFi section data
     */
    private function generateWifiSectionData(): array {
        return [
            'wifi_installed' => $this->getRandomYesNo(),
            'wifi_remarks' => rand(0, 1) ? 'Remarks: ' . $this->generateRandomString(15) : null,
            'wifi_snaps' => rand(0, 1) ? 'uploads/wifi_' . $this->generateRandomString(8) . '.jpg' : null,
            'wifi_status' => $this->getRandomWorkingStatus(),
            'wifi_status_remarks' => rand(0, 1) ? 'Status: ' . $this->generateRandomString(15) : null,
            'wifi_status_snaps' => rand(0, 1) ? 'uploads/wifi_status_' . $this->generateRandomString(8) . '.jpg' : null
        ];
    }
    
    /**
     * Generate Airtel SIM section data
     */
    private function generateAirtelSimSectionData(): array {
        return [
            'airtel_sim_installed' => $this->getRandomYesNo(),
            'airtel_sim_remarks' => rand(0, 1) ? 'Remarks: ' . $this->generateRandomString(15) : null,
            'airtel_sim_snaps' => rand(0, 1) ? 'uploads/airtel_' . $this->generateRandomString(8) . '.jpg' : null,
            'airtel_sim_status' => $this->getRandomWorkingStatus(),
            'airtel_sim_status_remarks' => rand(0, 1) ? 'Status: ' . $this->generateRandomString(15) : null,
            'airtel_sim_status_snaps' => rand(0, 1) ? 'uploads/airtel_status_' . $this->generateRandomString(8) . '.jpg' : null
        ];
    }
    
    /**
     * Generate Vodafone SIM section data
     */
    private function generateVodafoneSimSectionData(): array {
        return [
            'vodafone_sim_installed' => $this->getRandomYesNo(),
            'vodafone_sim_remarks' => rand(0, 1) ? 'Remarks: ' . $this->generateRandomString(15) : null,
            'vodafone_sim_snaps' => rand(0, 1) ? 'uploads/vodafone_' . $this->generateRandomString(8) . '.jpg' : null,
            'vodafone_sim_status' => $this->getRandomWorkingStatus(),
            'vodafone_sim_status_remarks' => rand(0, 1) ? 'Status: ' . $this->generateRandomString(15) : null,
            'vodafone_sim_status_snaps' => rand(0, 1) ? 'uploads/vodafone_status_' . $this->generateRandomString(8) . '.jpg' : null
        ];
    }
    
    /**
     * Generate JIO SIM section data
     */
    private function generateJioSimSectionData(): array {
        return [
            'jio_sim_installed' => $this->getRandomYesNo(),
            'jio_sim_remarks' => rand(0, 1) ? 'Remarks: ' . $this->generateRandomString(15) : null,
            'jio_sim_snaps' => rand(0, 1) ? 'uploads/jio_' . $this->generateRandomString(8) . '.jpg' : null,
            'jio_sim_status' => $this->getRandomWorkingStatus(),
            'jio_sim_status_remarks' => rand(0, 1) ? 'Status: ' . $this->generateRandomString(15) : null,
            'jio_sim_status_snaps' => rand(0, 1) ? 'uploads/jio_status_' . $this->generateRandomString(8) . '.jpg' : null
        ];
    }
}

// Run tests if executed directly
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    $test = new InstallationSectionDataRoundTripTest();
    $success = $test->runTests();
    exit($success ? 0 : 1);
}
