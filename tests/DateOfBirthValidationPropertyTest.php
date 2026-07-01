<?php
/**
 * Property Test: Date of Birth Past Validation
 * **Feature: user-profile-enhancement, Property 3: Date of Birth Past Validation**
 * **Validates: Requirements 4.2**
 * 
 * Property: *For any* date input as date of birth, the system SHALL accept only dates 
 * that are before the current date and reject future dates.
 */

require_once __DIR__ . '/PropertyTestBase.php';
require_once __DIR__ . '/../services/ProfileService.php';

class DateOfBirthValidationPropertyTest extends PropertyTestBase {
    private $profileService;
    
    public function __construct() {
        parent::__construct();
        $this->profileService = new ProfileService();
    }
    
    /**
     * Generate random past date (valid date of birth)
     */
    private function generatePastDate(): string {
        // Generate date between 1900 and yesterday
        $minTimestamp = strtotime('1900-01-01');
        $maxTimestamp = strtotime('-1 day');
        $randomTimestamp = $this->generateRandomInt($minTimestamp, $maxTimestamp);
        return date('Y-m-d', $randomTimestamp);
    }
    
    /**
     * Generate random future date (invalid date of birth)
     */
    private function generateFutureDate(): string {
        // Generate date between tomorrow and 100 years from now
        $minTimestamp = strtotime('+1 day');
        $maxTimestamp = strtotime('+100 years');
        $randomTimestamp = $this->generateRandomInt($minTimestamp, $maxTimestamp);
        return date('Y-m-d', $randomTimestamp);
    }
    
    /**
     * Generate today's date (invalid - not in the past)
     */
    private function generateTodayDate(): string {
        return date('Y-m-d');
    }
    
    /**
     * Property Test: Past dates are accepted
     */
    public function testPastDatesAccepted(): bool {
        return $this->runPropertyTest(
            'Past dates are accepted as date of birth',
            function() {
                $dateOfBirth = $this->generatePastDate();
                
                $isValid = $this->profileService->isValidDateOfBirth($dateOfBirth);
                
                if (!$isValid) {
                    return [
                        'success' => false,
                        'message' => "Valid past date was rejected",
                        'data' => [
                            'date_of_birth' => $dateOfBirth,
                            'today' => date('Y-m-d')
                        ]
                    ];
                }
                
                return ['success' => true];
            }
        );
    }
    
    /**
     * Property Test: Future dates are rejected
     */
    public function testFutureDatesRejected(): bool {
        return $this->runPropertyTest(
            'Future dates are rejected as date of birth',
            function() {
                $dateOfBirth = $this->generateFutureDate();
                
                $isValid = $this->profileService->isValidDateOfBirth($dateOfBirth);
                
                if ($isValid) {
                    return [
                        'success' => false,
                        'message' => "Future date was accepted",
                        'data' => [
                            'date_of_birth' => $dateOfBirth,
                            'today' => date('Y-m-d')
                        ]
                    ];
                }
                
                return ['success' => true];
            }
        );
    }
    
    /**
     * Property Test: Today's date is rejected
     */
    public function testTodayDateRejected(): bool {
        return $this->runPropertyTest(
            "Today's date is rejected as date of birth",
            function() {
                $dateOfBirth = $this->generateTodayDate();
                
                $isValid = $this->profileService->isValidDateOfBirth($dateOfBirth);
                
                if ($isValid) {
                    return [
                        'success' => false,
                        'message' => "Today's date was accepted",
                        'data' => [
                            'date_of_birth' => $dateOfBirth
                        ]
                    ];
                }
                
                return ['success' => true];
            },
            10 // Fewer iterations since today is always the same
        );
    }
    
    /**
     * Run all property tests
     */
    public function runAllTests(): bool {
        echo "=== Date of Birth Validation Property Tests ===\n\n";
        
        $allPassed = true;
        
        if (!$this->testPastDatesAccepted()) {
            $allPassed = false;
        }
        
        if (!$this->testFutureDatesRejected()) {
            $allPassed = false;
        }
        
        if (!$this->testTodayDateRejected()) {
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
