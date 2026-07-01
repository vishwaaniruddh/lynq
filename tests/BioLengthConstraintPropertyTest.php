<?php
/**
 * Property Test: Bio Length Constraint
 * **Feature: user-profile-enhancement, Property 6: Bio Length Constraint**
 * **Validates: Requirements 7.2**
 * 
 * Property: *For any* bio text input, the system SHALL accept only text with 500 characters 
 * or fewer and reject or truncate longer text.
 */

require_once __DIR__ . '/PropertyTestBase.php';
require_once __DIR__ . '/../services/ProfileService.php';

class BioLengthConstraintPropertyTest extends PropertyTestBase {
    private $profileService;
    private const MAX_BIO_LENGTH = 500;
    
    public function __construct() {
        parent::__construct();
        $this->profileService = new ProfileService();
    }
    
    /**
     * Generate random bio within length limit (0-500 chars)
     */
    private function generateValidBio(): string {
        $length = $this->generateRandomInt(0, self::MAX_BIO_LENGTH);
        return $this->generateRandomString($length);
    }
    
    /**
     * Generate random bio exceeding length limit (501+ chars)
     */
    private function generateInvalidBio(): string {
        $length = $this->generateRandomInt(self::MAX_BIO_LENGTH + 1, self::MAX_BIO_LENGTH + 500);
        return $this->generateRandomString($length);
    }
    
    /**
     * Property Test: Bio within limit is accepted
     */
    public function testValidBioAccepted(): bool {
        return $this->runPropertyTest(
            'Bio within 500 characters is accepted',
            function() {
                $bio = $this->generateValidBio();
                
                $isValid = $this->profileService->isValidBioLength($bio);
                
                if (!$isValid) {
                    return [
                        'success' => false,
                        'message' => "Valid bio was rejected",
                        'data' => [
                            'bio_length' => strlen($bio),
                            'max_allowed' => self::MAX_BIO_LENGTH
                        ]
                    ];
                }
                
                return ['success' => true];
            }
        );
    }
    
    /**
     * Property Test: Bio exceeding limit is rejected
     */
    public function testInvalidBioRejected(): bool {
        return $this->runPropertyTest(
            'Bio exceeding 500 characters is rejected',
            function() {
                $bio = $this->generateInvalidBio();
                
                $isValid = $this->profileService->isValidBioLength($bio);
                
                if ($isValid) {
                    return [
                        'success' => false,
                        'message' => "Invalid bio was accepted",
                        'data' => [
                            'bio_length' => strlen($bio),
                            'max_allowed' => self::MAX_BIO_LENGTH
                        ]
                    ];
                }
                
                return ['success' => true];
            }
        );
    }
    
    /**
     * Property Test: Bio at exactly 500 characters is accepted
     */
    public function testBioAtExactLimitAccepted(): bool {
        return $this->runPropertyTest(
            'Bio at exactly 500 characters is accepted',
            function() {
                $bio = $this->generateRandomString(self::MAX_BIO_LENGTH);
                
                $isValid = $this->profileService->isValidBioLength($bio);
                
                if (!$isValid) {
                    return [
                        'success' => false,
                        'message' => "Bio at exact limit was rejected",
                        'data' => [
                            'bio_length' => strlen($bio),
                            'max_allowed' => self::MAX_BIO_LENGTH
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
        echo "=== Bio Length Constraint Property Tests ===\n\n";
        
        $allPassed = true;
        
        if (!$this->testValidBioAccepted()) {
            $allPassed = false;
        }
        
        if (!$this->testInvalidBioRejected()) {
            $allPassed = false;
        }
        
        if (!$this->testBioAtExactLimitAccepted()) {
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
