<?php
/**
 * Property Test for Remarks Character Limit
 * **Feature: feasibility-module, Property 12: Remarks character limit**
 * **Validates: Requirements 7.2**
 */

require_once 'PropertyTestBase.php';
require_once __DIR__ . '/../services/FeasibilityService.php';

class RemarksCharacterLimitPropertyTest extends PropertyTestBase {
    
    private $feasibilityService;
    
    // Maximum allowed characters for remarks (Requirement 7.2)
    private const MAX_REMARKS_LENGTH = 2000;
    
    public function __construct() {
        parent::__construct();
        $this->feasibilityService = new FeasibilityService();
    }
    
    public function runTests(): bool {
        echo "=== Remarks Character Limit Property Tests ===\n\n";
        
        $allPassed = true;
        
        // Property 12: Remarks character limit
        $allPassed &= $this->runPropertyTest(
            "Property 12: Remarks character limit",
            [$this, 'testRemarksCharacterLimit']
        );
        
        return $allPassed;
    }
    
    /**
     * Property 12: Remarks character limit
     * **Feature: feasibility-module, Property 12: Remarks character limit**
     * **Validates: Requirements 7.2**
     * 
     * For any remarks text input, text up to 2000 characters should be accepted,
     * and text exceeding 2000 characters should be rejected or truncated.
     */
    public function testRemarksCharacterLimit(): array {
        try {
            // Test 1: Valid remarks within limit
            for ($i = 0; $i < 50; $i++) {
                // Generate random length between 0 and 2000
                $length = rand(0, self::MAX_REMARKS_LENGTH);
                $remarks = $this->generateRandomString($length);
                
                $data = ['remarks' => $remarks];
                $validation = $this->feasibilityService->validateFeasibilityData($data);
                
                // Should not have remarks-related errors
                $hasRemarksError = false;
                if (!empty($validation['errors'])) {
                    foreach ($validation['errors'] as $error) {
                        if ($error['field'] === 'remarks' && $error['code'] === 'MAX_LENGTH_EXCEEDED') {
                            $hasRemarksError = true;
                            break;
                        }
                    }
                }
                
                $this->assert(
                    !$hasRemarksError,
                    "Remarks with {$length} characters should be accepted (within limit)"
                );
            }
            
            // Test 2: Exactly at the limit (2000 characters)
            $exactLimitRemarks = $this->generateRandomString(self::MAX_REMARKS_LENGTH);
            $data = ['remarks' => $exactLimitRemarks];
            $validation = $this->feasibilityService->validateFeasibilityData($data);
            
            $hasRemarksError = false;
            if (!empty($validation['errors'])) {
                foreach ($validation['errors'] as $error) {
                    if ($error['field'] === 'remarks' && $error['code'] === 'MAX_LENGTH_EXCEEDED') {
                        $hasRemarksError = true;
                        break;
                    }
                }
            }
            
            $this->assert(
                !$hasRemarksError,
                "Remarks with exactly 2000 characters should be accepted"
            );
            
            // Test 3: Exceeding the limit
            for ($i = 0; $i < 50; $i++) {
                // Generate random length between 2001 and 5000
                $length = rand(self::MAX_REMARKS_LENGTH + 1, 5000);
                $remarks = $this->generateRandomString($length);
                
                $data = ['remarks' => $remarks];
                $validation = $this->feasibilityService->validateFeasibilityData($data);
                
                // Should have remarks-related error
                $hasRemarksError = false;
                if (!empty($validation['errors'])) {
                    foreach ($validation['errors'] as $error) {
                        if ($error['field'] === 'remarks' && $error['code'] === 'MAX_LENGTH_EXCEEDED') {
                            $hasRemarksError = true;
                            break;
                        }
                    }
                }
                
                $this->assert(
                    $hasRemarksError,
                    "Remarks with {$length} characters should be rejected (exceeds limit)"
                );
            }
            
            // Test 4: Empty remarks should be accepted
            $data = ['remarks' => ''];
            $validation = $this->feasibilityService->validateFeasibilityData($data);
            
            $hasRemarksError = false;
            if (!empty($validation['errors'])) {
                foreach ($validation['errors'] as $error) {
                    if ($error['field'] === 'remarks') {
                        $hasRemarksError = true;
                        break;
                    }
                }
            }
            
            $this->assert(
                !$hasRemarksError,
                "Empty remarks should be accepted"
            );
            
            // Test 5: Null remarks should be accepted
            $data = ['remarks' => null];
            $validation = $this->feasibilityService->validateFeasibilityData($data);
            
            $hasRemarksError = false;
            if (!empty($validation['errors'])) {
                foreach ($validation['errors'] as $error) {
                    if ($error['field'] === 'remarks') {
                        $hasRemarksError = true;
                        break;
                    }
                }
            }
            
            $this->assert(
                !$hasRemarksError,
                "Null remarks should be accepted"
            );
            
            // Test 6: Unicode characters (each character counts as 1)
            $unicodeRemarks = str_repeat('日', 2000); // 2000 Japanese characters
            $data = ['remarks' => $unicodeRemarks];
            $validation = $this->feasibilityService->validateFeasibilityData($data);
            
            $hasRemarksError = false;
            if (!empty($validation['errors'])) {
                foreach ($validation['errors'] as $error) {
                    if ($error['field'] === 'remarks' && $error['code'] === 'MAX_LENGTH_EXCEEDED') {
                        $hasRemarksError = true;
                        break;
                    }
                }
            }
            
            $this->assert(
                !$hasRemarksError,
                "Unicode remarks with exactly 2000 characters should be accepted"
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
     * Clean up test data (no database operations in this test)
     */
    protected function cleanupTestData(): void {
        // No cleanup needed - this test doesn't create database records
    }
}
