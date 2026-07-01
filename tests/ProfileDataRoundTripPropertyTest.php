<?php
/**
 * Property Test: Profile Data Round-Trip
 * **Feature: user-profile-enhancement, Property 1: Profile Data Round-Trip**
 * **Validates: Requirements 1.1, 1.2, 2.3, 3.2, 4.3, 5.2, 7.3, 9.1**
 * 
 * Property: *For any* user with valid profile data (first_name, last_name, contact_number, 
 * address, date_of_birth, sex, bio), updating the profile and then retrieving it 
 * SHALL return the same values that were saved.
 */

require_once __DIR__ . '/PropertyTestBase.php';
require_once __DIR__ . '/../services/ProfileService.php';

class ProfileDataRoundTripPropertyTest extends PropertyTestBase {
    private $profileService;
    private $testUserIds = [];
    
    public function __construct() {
        parent::__construct();
        $this->profileService = new ProfileService();
    }
    
    /**
     * Set up test user
     */
    private function setupTestUser(): int {
        $username = 'test_profile_rt_' . $this->generateRandomString(8);
        $email = $username . '@test.com';
        
        $sql = "INSERT INTO users (username, email, password_hash, first_name, last_name, role_id, status, company_id, created_at) 
                VALUES (?, ?, ?, 'Initial', 'User', 1, 1, 1, NOW())";
        $stmt = $this->executeQuery($sql, [$username, $email, password_hash('test123', PASSWORD_DEFAULT)], 'sss');
        $userId = $this->db->insert_id;
        $stmt->close();
        
        $this->testUserIds[] = $userId;
        return $userId;
    }
    
    /**
     * Clean up test data
     */
    protected function cleanupTestData(): void {
        if (!empty($this->testUserIds)) {
            $ids = implode(',', array_map('intval', $this->testUserIds));
            // Revisions will be deleted by CASCADE
            $sql = "DELETE FROM users WHERE id IN ($ids)";
            $this->db->query($sql);
            $this->testUserIds = [];
        }
    }
    
    /**
     * Generate random first/last name (1-50 chars)
     */
    private function generateRandomName(): string {
        $length = $this->generateRandomInt(1, 50);
        return $this->generateRandomString($length, 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ');
    }
    
    /**
     * Generate random valid contact number
     */
    private function generateRandomContactNumber(): string {
        $formats = [
            '+1 (555) 123-4567',
            '555-123-4567',
            '5551234567',
            '+44 20 7946 0958',
            '(555) 123 4567'
        ];
        
        // Generate random digits and format
        $digits = '';
        for ($i = 0; $i < $this->generateRandomInt(7, 15); $i++) {
            $digits .= $this->generateRandomInt(0, 9);
        }
        
        // Randomly add formatting
        if ($this->generateRandomBool()) {
            return '+' . $digits;
        }
        return $digits;
    }
    
    /**
     * Generate random address (0-200 chars)
     */
    private function generateRandomAddress(): string {
        $length = $this->generateRandomInt(0, 200);
        if ($length === 0) return '';
        return $this->generateRandomString($length);
    }
    
    /**
     * Generate random past date of birth
     */
    private function generateRandomDateOfBirth(): string {
        // Generate date between 1950 and yesterday
        $minTimestamp = strtotime('1950-01-01');
        $maxTimestamp = strtotime('-1 day');
        $randomTimestamp = $this->generateRandomInt($minTimestamp, $maxTimestamp);
        return date('Y-m-d', $randomTimestamp);
    }
    
    /**
     * Generate random sex value
     */
    private function generateRandomSex(): string {
        return $this->generateRandomChoice(['male', 'female', 'other']);
    }
    
    /**
     * Generate random bio (0-500 chars)
     */
    private function generateRandomBio(): string {
        $length = $this->generateRandomInt(0, 500);
        if ($length === 0) return '';
        return $this->generateRandomString($length);
    }
    
    /**
     * Property Test: Profile data round-trip
     */
    public function testProfileDataRoundTrip(): bool {
        return $this->runPropertyTest(
            'Profile Data Round-Trip',
            function() {
                // Setup: Create test user
                $userId = $this->setupTestUser();
                
                // Generate random profile data
                $profileData = [
                    'first_name' => $this->generateRandomName(),
                    'last_name' => $this->generateRandomName(),
                    'contact_number' => $this->generateRandomContactNumber(),
                    'address' => $this->generateRandomAddress(),
                    'date_of_birth' => $this->generateRandomDateOfBirth(),
                    'sex' => $this->generateRandomSex(),
                    'bio' => $this->generateRandomBio()
                ];
                
                // Update profile
                $updateResult = $this->profileService->updateProfile($userId, $profileData);
                
                if (!$updateResult['success']) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => "Failed to update profile: " . ($updateResult['message'] ?? 'Unknown error'),
                        'data' => $profileData
                    ];
                }
                
                // Retrieve profile
                $getResult = $this->profileService->getProfile($userId);
                
                if (!$getResult['success']) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => "Failed to retrieve profile: " . ($getResult['message'] ?? 'Unknown error'),
                        'data' => ['user_id' => $userId]
                    ];
                }
                
                $retrievedProfile = $getResult['data'];
                
                // Verify each field matches
                foreach ($profileData as $field => $expectedValue) {
                    $actualValue = $retrievedProfile[$field] ?? null;
                    
                    if ($actualValue !== $expectedValue) {
                        $this->cleanupTestData();
                        return [
                            'success' => false,
                            'message' => "Field '$field' mismatch after round-trip",
                            'data' => [
                                'field' => $field,
                                'expected' => $expectedValue,
                                'actual' => $actualValue
                            ]
                        ];
                    }
                }
                
                // Cleanup
                $this->cleanupTestData();
                
                return ['success' => true];
            }
        );
    }
    
    /**
     * Run all property tests
     */
    public function runAllTests(): bool {
        echo "=== Profile Data Round-Trip Property Tests ===\n\n";
        
        $allPassed = true;
        
        if (!$this->testProfileDataRoundTrip()) {
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
