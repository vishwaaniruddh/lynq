<?php
/**
 * Property Test: Verification Data Round-Trip (Signature and Stamp)
 * 
 * **Feature: installation-module, Property 16: Verification data round-trip (Signature and Stamp)**
 * **Validates: Requirements 13.1-13.5**
 * 
 * Property: For any valid signature and vendor stamp upload, saving and retrieving 
 * should return the correct image paths linked to the installation record.
 * 
 * This test verifies that:
 * 1. Signature image paths are correctly stored and retrieved
 * 2. Vendor stamp image paths are correctly stored and retrieved
 * 3. Both paths are preserved through serialization round-trip
 * 4. Null values are handled correctly
 * 5. Various path formats are preserved
 */

require_once __DIR__ . '/../config/autoload.php';
require_once __DIR__ . '/../models/Installation.php';

class InstallationVerificationDataRoundTripTest {
    private $testResults = [];
    private $iterations = 100;
    
    /**
     * Run all property tests
     */
    public function runTests(): bool {
        echo "\n=== Installation Verification Data Round-Trip Property Tests ===\n";
        echo "**Feature: installation-module, Property 16: Verification data round-trip**\n";
        echo "**Validates: Requirements 13.1-13.5**\n\n";
        
        $this->runPropertyTest(
            'Signature image path round-trip preserves path',
            [$this, 'testSignatureImageRoundTrip']
        );
        
        $this->runPropertyTest(
            'Vendor stamp path round-trip preserves path',
            [$this, 'testVendorStampRoundTrip']
        );
        
        $this->runPropertyTest(
            'Both verification fields round-trip together',
            [$this, 'testBothVerificationFieldsRoundTrip']
        );
        
        $this->runPropertyTest(
            'Null verification values are preserved',
            [$this, 'testNullVerificationValues']
        );
        
        $this->runPropertyTest(
            'Various path formats are preserved',
            [$this, 'testVariousPathFormats']
        );
        
        $this->runPropertyTest(
            'Data URL signature format is preserved',
            [$this, 'testDataUrlSignatureFormat']
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
     * Property Test: Signature image path round-trip
     * Requirements: 13.1, 13.3
     */
    private function testSignatureImageRoundTrip(): array {
        $signaturePath = $this->generateRandomSignaturePath();
        
        $originalData = array_merge(
            $this->generateBaseInstallationData(),
            ['signature_image' => $signaturePath]
        );
        
        $serialized = Installation::toArray($originalData);
        $deserialized = Installation::fromArray($serialized);
        
        if ($deserialized['signature_image'] !== $signaturePath) {
            return [
                'success' => false,
                'message' => "Signature path mismatch: expected '$signaturePath', got '{$deserialized['signature_image']}'"
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * Property Test: Vendor stamp path round-trip
     * Requirements: 13.2, 13.4, 13.5
     */
    private function testVendorStampRoundTrip(): array {
        $stampPath = $this->generateRandomStampPath();
        
        $originalData = array_merge(
            $this->generateBaseInstallationData(),
            ['vendor_stamp' => $stampPath]
        );
        
        $serialized = Installation::toArray($originalData);
        $deserialized = Installation::fromArray($serialized);
        
        if ($deserialized['vendor_stamp'] !== $stampPath) {
            return [
                'success' => false,
                'message' => "Vendor stamp path mismatch: expected '$stampPath', got '{$deserialized['vendor_stamp']}'"
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * Property Test: Both verification fields round-trip together
     * Requirements: 13.1-13.5
     */
    private function testBothVerificationFieldsRoundTrip(): array {
        $signaturePath = $this->generateRandomSignaturePath();
        $stampPath = $this->generateRandomStampPath();
        
        $originalData = array_merge(
            $this->generateBaseInstallationData(),
            [
                'signature_image' => $signaturePath,
                'vendor_stamp' => $stampPath
            ]
        );
        
        $serialized = Installation::toArray($originalData);
        $deserialized = Installation::fromArray($serialized);
        
        if ($deserialized['signature_image'] !== $signaturePath) {
            return [
                'success' => false,
                'message' => "Signature path mismatch when both fields present"
            ];
        }
        
        if ($deserialized['vendor_stamp'] !== $stampPath) {
            return [
                'success' => false,
                'message' => "Vendor stamp path mismatch when both fields present"
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * Property Test: Null verification values are preserved
     */
    private function testNullVerificationValues(): array {
        // Test with null signature
        $originalData1 = array_merge(
            $this->generateBaseInstallationData(),
            [
                'signature_image' => null,
                'vendor_stamp' => $this->generateRandomStampPath()
            ]
        );
        
        $serialized1 = Installation::toArray($originalData1);
        $deserialized1 = Installation::fromArray($serialized1);
        
        if ($deserialized1['signature_image'] !== null) {
            return [
                'success' => false,
                'message' => "Null signature_image was not preserved"
            ];
        }
        
        // Test with null stamp
        $originalData2 = array_merge(
            $this->generateBaseInstallationData(),
            [
                'signature_image' => $this->generateRandomSignaturePath(),
                'vendor_stamp' => null
            ]
        );
        
        $serialized2 = Installation::toArray($originalData2);
        $deserialized2 = Installation::fromArray($serialized2);
        
        if ($deserialized2['vendor_stamp'] !== null) {
            return [
                'success' => false,
                'message' => "Null vendor_stamp was not preserved"
            ];
        }
        
        // Test with both null
        $originalData3 = array_merge(
            $this->generateBaseInstallationData(),
            [
                'signature_image' => null,
                'vendor_stamp' => null
            ]
        );
        
        $serialized3 = Installation::toArray($originalData3);
        $deserialized3 = Installation::fromArray($serialized3);
        
        if ($deserialized3['signature_image'] !== null || $deserialized3['vendor_stamp'] !== null) {
            return [
                'success' => false,
                'message' => "Both null values were not preserved"
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * Property Test: Various path formats are preserved
     */
    private function testVariousPathFormats(): array {
        $pathFormats = [
            'uploads/installations/1/signature.png',
            'uploads/installations/123/signature_' . $this->generateRandomString(8) . '.png',
            'uploads/signatures/sig_2024_01_01.jpg',
            'uploads/stamps/vendor_stamp.jpeg',
            'uploads/installations/999/stamp_' . $this->generateRandomString(12) . '.png'
        ];
        
        $signaturePath = $pathFormats[array_rand($pathFormats)];
        $stampPath = $pathFormats[array_rand($pathFormats)];
        
        $originalData = array_merge(
            $this->generateBaseInstallationData(),
            [
                'signature_image' => $signaturePath,
                'vendor_stamp' => $stampPath
            ]
        );
        
        $serialized = Installation::toArray($originalData);
        $deserialized = Installation::fromArray($serialized);
        
        if ($deserialized['signature_image'] !== $signaturePath) {
            return [
                'success' => false,
                'message' => "Path format not preserved for signature: '$signaturePath'"
            ];
        }
        
        if ($deserialized['vendor_stamp'] !== $stampPath) {
            return [
                'success' => false,
                'message' => "Path format not preserved for stamp: '$stampPath'"
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * Property Test: Data URL signature format is preserved
     * (For signatures captured via canvas)
     */
    private function testDataUrlSignatureFormat(): array {
        // Generate a mock data URL (base64 encoded PNG)
        $dataUrl = 'data:image/png;base64,' . base64_encode($this->generateRandomString(100));
        
        $originalData = array_merge(
            $this->generateBaseInstallationData(),
            ['signature_image' => $dataUrl]
        );
        
        $serialized = Installation::toArray($originalData);
        $deserialized = Installation::fromArray($serialized);
        
        if ($deserialized['signature_image'] !== $dataUrl) {
            return [
                'success' => false,
                'message' => "Data URL format not preserved for signature"
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
     * Generate random signature path
     */
    private function generateRandomSignaturePath(): string {
        $installationId = rand(1, 1000);
        $filename = 'signature_' . $this->generateRandomString(8) . '.png';
        return "uploads/installations/{$installationId}/{$filename}";
    }
    
    /**
     * Generate random stamp path
     */
    private function generateRandomStampPath(): string {
        $installationId = rand(1, 1000);
        $filename = 'stamp_' . $this->generateRandomString(8) . '.png';
        return "uploads/installations/{$installationId}/{$filename}";
    }
}

// Run tests if executed directly
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    $test = new InstallationVerificationDataRoundTripTest();
    $success = $test->runTests();
    exit($success ? 0 : 1);
}
