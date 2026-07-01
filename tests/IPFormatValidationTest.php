<?php
/**
 * Property Test: IP Format Validation
 * 
 * **Feature: ip-configuration-management, Property 1: IP Format Validation**
 * **Validates: Requirements 1.1**
 * 
 * Property: For any string submitted as an IP address (Network IP, Router IP, Site IP, 
 * or Subnet Mask), the system SHALL accept only valid IPv4 format (four octets 0-255 
 * separated by dots) and reject all invalid formats.
 */

require_once __DIR__ . '/PropertyTestBase.php';
require_once __DIR__ . '/../models/IPMaster.php';

class IPFormatValidationTest extends PropertyTestBase {
    
    /**
     * Generate a valid IPv4 address
     * Each octet is 0-255
     */
    protected function generateValidIP(): string {
        $octets = [];
        for ($i = 0; $i < 4; $i++) {
            $octets[] = rand(0, 255);
        }
        return implode('.', $octets);
    }
    
    /**
     * Generate an invalid IP address
     * Various types of invalid formats
     */
    protected function generateInvalidIP(): string {
        $invalidTypes = [
            'empty' => '',
            'null_string' => 'null',
            'text' => $this->generateRandomString(10),
            'too_few_octets' => rand(0, 255) . '.' . rand(0, 255),
            'too_many_octets' => rand(0, 255) . '.' . rand(0, 255) . '.' . rand(0, 255) . '.' . rand(0, 255) . '.' . rand(0, 255),
            'octet_too_high' => rand(0, 255) . '.' . rand(256, 999) . '.' . rand(0, 255) . '.' . rand(0, 255),
            'negative_octet' => rand(0, 255) . '.' . (-rand(1, 255)) . '.' . rand(0, 255) . '.' . rand(0, 255),
            'non_numeric_octet' => rand(0, 255) . '.abc.' . rand(0, 255) . '.' . rand(0, 255),
            'spaces' => rand(0, 255) . '. ' . rand(0, 255) . '.' . rand(0, 255) . '.' . rand(0, 255),
            'leading_zeros_invalid' => '01.02.03.04', // This is actually valid in PHP filter_var
            'double_dots' => rand(0, 255) . '..' . rand(0, 255) . '.' . rand(0, 255),
            'trailing_dot' => rand(0, 255) . '.' . rand(0, 255) . '.' . rand(0, 255) . '.' . rand(0, 255) . '.',
            'leading_dot' => '.' . rand(0, 255) . '.' . rand(0, 255) . '.' . rand(0, 255),
            'ipv6_format' => '2001:0db8:85a3:0000:0000:8a2e:0370:7334',
            'special_chars' => rand(0, 255) . '.' . rand(0, 255) . '@' . rand(0, 255) . '.' . rand(0, 255),
            'float_octet' => rand(0, 255) . '.' . rand(0, 255) . '.5.5.' . rand(0, 255),
        ];
        
        $type = array_rand($invalidTypes);
        return $invalidTypes[$type];
    }
    
    /**
     * Property Test: Valid IPv4 addresses should be accepted
     * 
     * For any valid IPv4 address (four octets 0-255 separated by dots),
     * validateIPFormat should return true.
     */
    public function testValidIPsAreAccepted(): bool {
        echo "\n=== Property Test: Valid IPs Are Accepted ===\n";
        
        return $this->runPropertyTest(
            'Valid IPv4 addresses are accepted',
            function() {
                $ip = $this->generateValidIP();
                $result = IPMaster::validateIPFormat($ip);
                
                if (!$result) {
                    return [
                        'success' => false,
                        'message' => "Valid IP '$ip' was rejected",
                        'data' => ['ip' => $ip]
                    ];
                }
                
                return ['success' => true];
            }
        );
    }
    
    /**
     * Property Test: Invalid IP addresses should be rejected
     * 
     * For any invalid IP address format, validateIPFormat should return false.
     */
    public function testInvalidIPsAreRejected(): bool {
        echo "\n=== Property Test: Invalid IPs Are Rejected ===\n";
        
        return $this->runPropertyTest(
            'Invalid IP addresses are rejected',
            function() {
                $ip = $this->generateInvalidIP();
                $result = IPMaster::validateIPFormat($ip);
                
                if ($result) {
                    return [
                        'success' => false,
                        'message' => "Invalid IP '$ip' was accepted",
                        'data' => ['ip' => $ip]
                    ];
                }
                
                return ['success' => true];
            }
        );
    }
    
    /**
     * Property Test: Boundary values for octets
     * 
     * Tests that octets at boundaries (0, 255) are valid,
     * and values outside (negative, >255) are invalid.
     */
    public function testOctetBoundaryValues(): bool {
        echo "\n=== Property Test: Octet Boundary Values ===\n";
        
        return $this->runPropertyTest(
            'Octet boundary values are handled correctly',
            function() {
                // Test valid boundary values
                $validBoundaryIPs = [
                    '0.0.0.0',
                    '255.255.255.255',
                    '0.255.0.255',
                    '255.0.255.0',
                    rand(0, 255) . '.0.' . rand(0, 255) . '.255',
                ];
                
                $validIP = $validBoundaryIPs[array_rand($validBoundaryIPs)];
                if (!IPMaster::validateIPFormat($validIP)) {
                    return [
                        'success' => false,
                        'message' => "Valid boundary IP '$validIP' was rejected",
                        'data' => ['ip' => $validIP, 'type' => 'valid_boundary']
                    ];
                }
                
                // Test invalid boundary values
                $invalidBoundaryIPs = [
                    '256.0.0.0',
                    '0.256.0.0',
                    '0.0.256.0',
                    '0.0.0.256',
                    '-1.0.0.0',
                    '0.-1.0.0',
                    '300.300.300.300',
                ];
                
                $invalidIP = $invalidBoundaryIPs[array_rand($invalidBoundaryIPs)];
                if (IPMaster::validateIPFormat($invalidIP)) {
                    return [
                        'success' => false,
                        'message' => "Invalid boundary IP '$invalidIP' was accepted",
                        'data' => ['ip' => $invalidIP, 'type' => 'invalid_boundary']
                    ];
                }
                
                return ['success' => true];
            }
        );
    }
    
    /**
     * Property Test: All IP fields validation
     * 
     * Tests that validateAllIPs correctly validates all four IP fields
     * in a data array.
     */
    public function testAllIPFieldsValidation(): bool {
        echo "\n=== Property Test: All IP Fields Validation ===\n";
        
        return $this->runPropertyTest(
            'All IP fields are validated correctly',
            function() {
                // Test with all valid IPs
                $validData = [
                    'network_ip' => $this->generateValidIP(),
                    'router_ip' => $this->generateValidIP(),
                    'site_ip' => $this->generateValidIP(),
                    'subnet_mask' => $this->generateValidIP(),
                ];
                
                $errors = IPMaster::validateAllIPs($validData);
                if (!empty($errors)) {
                    return [
                        'success' => false,
                        'message' => 'Valid IP data produced errors',
                        'data' => ['data' => $validData, 'errors' => $errors]
                    ];
                }
                
                // Test with one invalid IP
                $fields = ['network_ip', 'router_ip', 'site_ip', 'subnet_mask'];
                $invalidField = $fields[array_rand($fields)];
                $invalidData = $validData;
                $invalidData[$invalidField] = 'invalid.ip.address';
                
                $errors = IPMaster::validateAllIPs($invalidData);
                if (empty($errors) || !isset($errors[$invalidField])) {
                    return [
                        'success' => false,
                        'message' => "Invalid IP in '$invalidField' was not detected",
                        'data' => ['data' => $invalidData, 'errors' => $errors]
                    ];
                }
                
                return ['success' => true];
            }
        );
    }
    
    /**
     * Run all property tests
     */
    public function runAllTests(): array {
        $results = [];
        
        $results['valid_ips_accepted'] = $this->testValidIPsAreAccepted();
        $results['invalid_ips_rejected'] = $this->testInvalidIPsAreRejected();
        $results['boundary_values'] = $this->testOctetBoundaryValues();
        $results['all_fields_validation'] = $this->testAllIPFieldsValidation();
        
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
    $test = new IPFormatValidationTest();
    $results = $test->runAllTests();
    exit(in_array(false, $results, true) ? 1 : 0);
}
