<?php
/**
 * Property Test: Contact Number Format Validation
 * **Feature: user-profile-enhancement, Property 2: Contact Number Format Validation**
 * **Validates: Requirements 2.2**
 * 
 * Property: *For any* string input as contact number, the system SHALL accept only strings 
 * matching valid phone number patterns (digits, spaces, dashes, parentheses, plus sign) 
 * and reject all other formats.
 */

require_once __DIR__ . '/PropertyTestBase.php';
require_once __DIR__ . '/../services/ProfileService.php';

class ContactNumberValidationPropertyTest extends PropertyTestBase {
    private $profileService;
    
    public function __construct() {
        parent::__construct();
        $this->profileService = new ProfileService();
    }
    
    /**
     * Generate random valid contact number (only allowed chars)
     */
    private function generateValidContactNumber(): string {
        $allowedChars = '0123456789 -()+ ';
        $length = $this->generateRandomInt(1, 20);
        $result = '';
        
        for ($i = 0; $i < $length; $i++) {
            $result .= $allowedChars[$this->generateRandomInt(0, strlen($allowedChars) - 1)];
        }
        
        // Ensure at least one digit
        if (!preg_match('/\d/', $result)) {
            $result .= $this->generateRandomInt(0, 9);
        }
        
        return $result;
    }
    
    /**
     * Generate random invalid contact number (contains invalid chars)
     */
    private function generateInvalidContactNumber(): string {
        $invalidChars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ!@#$%^&*=[]{}|;:\'",.<>?/\\`~';
        $validChars = '0123456789 -()+ ';
        
        // Start with some valid chars
        $length = $this->generateRandomInt(1, 15);
        $result = '';
        
        for ($i = 0; $i < $length; $i++) {
            $result .= $validChars[$this->generateRandomInt(0, strlen($validChars) - 1)];
        }
        
        // Insert at least one invalid character
        $invalidChar = $invalidChars[$this->generateRandomInt(0, strlen($invalidChars) - 1)];
        $insertPos = $this->generateRandomInt(0, strlen($result));
        $result = substr($result, 0, $insertPos) . $invalidChar . substr($result, $insertPos);
        
        return $result;
    }
    
    /**
     * Property Test: Valid contact numbers are accepted
     */
    public function testValidContactNumbersAccepted(): bool {
        return $this->runPropertyTest(
            'Valid contact numbers are accepted',
            function() {
                $contactNumber = $this->generateValidContactNumber();
                
                $isValid = $this->profileService->isValidContactNumber($contactNumber);
                
                if (!$isValid) {
                    return [
                        'success' => false,
                        'message' => "Valid contact number was rejected",
                        'data' => [
                            'contact_number' => $contactNumber,
                            'chars' => str_split($contactNumber)
                        ]
                    ];
                }
                
                return ['success' => true];
            }
        );
    }
    
    /**
     * Property Test: Invalid contact numbers are rejected
     */
    public function testInvalidContactNumbersRejected(): bool {
        return $this->runPropertyTest(
            'Invalid contact numbers are rejected',
            function() {
                $contactNumber = $this->generateInvalidContactNumber();
                
                $isValid = $this->profileService->isValidContactNumber($contactNumber);
                
                if ($isValid) {
                    return [
                        'success' => false,
                        'message' => "Invalid contact number was accepted",
                        'data' => [
                            'contact_number' => $contactNumber
                        ]
                    ];
                }
                
                return ['success' => true];
            }
        );
    }
    
    /**
     * Run all property tests
     */
    public function runAllTests(): bool {
        echo "=== Contact Number Validation Property Tests ===\n\n";
        
        $allPassed = true;
        
        if (!$this->testValidContactNumbersAccepted()) {
            $allPassed = false;
        }
        
        if (!$this->testInvalidContactNumbersRejected()) {
            $allPassed = false;
        }
        
        echo "\n";
        if ($allPassed) {
            echo "All property tests PASSED!\n";
        } else {
            echo "Some property tests FAILED!\n";
        }
        
        return $allPassed;
    }
}
