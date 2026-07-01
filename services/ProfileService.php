<?php
/**
 * Profile Service
 * Handles business logic for user profile management
 * 
 * Requirements: 1.1, 1.2, 2.2, 2.3, 3.2, 4.2, 4.3, 5.2, 6.2, 6.3, 6.4, 7.2, 7.3, 8.1, 9.1, 9.2, 9.3
 */

require_once __DIR__ . '/../config/autoload.php';
require_once __DIR__ . '/../repositories/UserRepository.php';
require_once __DIR__ . '/../repositories/ProfileRevisionRepository.php';

class ProfileService {
    private $db;
    private $userRepository;
    private $revisionRepository;
    
    // Profile fields that can be updated
    private const PROFILE_FIELDS = [
        'first_name', 'last_name', 'contact_number', 'address',
        'date_of_birth', 'sex', 'bio'
    ];
    
    // Allowed sex values
    private const ALLOWED_SEX_VALUES = ['male', 'female', 'other'];
    
    // Allowed image MIME types
    private const ALLOWED_IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/gif'];
    
    // Max file size (2MB)
    private const MAX_FILE_SIZE = 2097152;
    
    // Max bio length
    private const MAX_BIO_LENGTH = 500;
    
    public function __construct() {
        $this->db = DatabaseConfig::getInstance();
        $this->userRepository = new UserRepository();
        $this->revisionRepository = new ProfileRevisionRepository();
    }
    
    /**
     * Get user profile data
     * Requirements: 1.1, 1.2, 2.1, 3.1, 4.1, 5.1, 6.1, 7.1, 9.1
     * 
     * @param int $userId User ID
     * @return array Result with success status and profile data
     */
    public function getProfile(int $userId): array {
        try {
            $this->userRepository->disableCompanyFilter();
            $user = $this->userRepository->find($userId);
            
            if (!$user) {
                return [
                    'success' => false,
                    'message' => 'User not found',
                    'code' => 'NOT_FOUND'
                ];
            }
            
            // Return profile data
            return [
                'success' => true,
                'data' => [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'email' => $user['email'],
                    'first_name' => $user['first_name'] ?? '',
                    'last_name' => $user['last_name'] ?? '',
                    'contact_number' => $user['contact_number'] ?? null,
                    'address' => $user['address'] ?? null,
                    'date_of_birth' => $user['date_of_birth'] ?? null,
                    'sex' => $user['sex'] ?? null,
                    'profile_picture' => $user['profile_picture'] ?? null,
                    'bio' => $user['bio'] ?? null,
                    'company_id' => $user['company_id'],
                    'role_id' => $user['role_id'],
                    'created_at' => $user['created_at'],
                    'updated_at' => $user['updated_at'] ?? null
                ]
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to retrieve profile: ' . $e->getMessage(),
                'code' => 'FETCH_ERROR'
            ];
        }
    }
    
    /**
     * Update user profile
     * Requirements: 2.2, 2.3, 3.2, 4.2, 4.3, 5.2, 7.2, 7.3, 8.1, 9.3
     * 
     * @param int $userId User ID
     * @param array $data Profile data to update
     * @return array Result with success status and updated profile
     */
    public function updateProfile(int $userId, array $data): array {
        try {
            // Get current profile
            $currentProfile = $this->getProfile($userId);
            if (!$currentProfile['success']) {
                return $currentProfile;
            }
            
            $currentData = $currentProfile['data'];
            
            // Validate input data
            $validation = $this->validateProfileData($data);
            if (!$validation['valid']) {
                return [
                    'success' => false,
                    'message' => 'Validation failed',
                    'code' => 'VALIDATION_ERROR',
                    'fields' => $validation['errors']
                ];
            }
            
            // Filter to only allowed profile fields
            $updateData = [];
            $changedFields = [];
            $oldValues = [];
            $newValues = [];
            
            foreach (self::PROFILE_FIELDS as $field) {
                if (array_key_exists($field, $data)) {
                    $newValue = $data[$field];
                    $oldValue = $currentData[$field] ?? null;
                    
                    // Check if value actually changed
                    if ($newValue !== $oldValue) {
                        $updateData[$field] = $newValue;
                        $changedFields[] = $field;
                        $oldValues[$field] = $oldValue;
                        $newValues[$field] = $newValue;
                    }
                }
            }
            
            // If nothing changed, return current profile
            if (empty($updateData)) {
                return [
                    'success' => true,
                    'message' => 'No changes detected',
                    'data' => $currentData
                ];
            }
            
            // Add updated_at timestamp
            $updateData['updated_at'] = date('Y-m-d H:i:s');
            
            // Update user record
            $this->userRepository->disableCompanyFilter();
            $this->userRepository->update($userId, $updateData);
            
            // Create revision record
            $this->createRevision($userId, $changedFields, $oldValues, $newValues);
            
            // Return updated profile
            return $this->getProfile($userId);
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to update profile: ' . $e->getMessage(),
                'code' => 'UPDATE_ERROR'
            ];
        }
    }
    
    /**
     * Validate profile data
     * Requirements: 2.2, 4.2, 7.2
     * 
     * @param array $data Profile data to validate
     * @return array Validation result with 'valid' boolean and 'errors' array
     */
    public function validateProfileData(array $data): array {
        $errors = [];
        
        // Validate contact_number format
        if (isset($data['contact_number']) && $data['contact_number'] !== null && $data['contact_number'] !== '') {
            if (!$this->isValidContactNumber($data['contact_number'])) {
                $errors['contact_number'] = 'Invalid contact number format. Only digits, spaces, dashes, parentheses, and plus sign are allowed.';
            }
        }
        
        // Validate date_of_birth is in the past
        if (isset($data['date_of_birth']) && $data['date_of_birth'] !== null && $data['date_of_birth'] !== '') {
            if (!$this->isValidDateOfBirth($data['date_of_birth'])) {
                $errors['date_of_birth'] = 'Date of birth must be in the past';
            }
        }
        
        // Validate sex value
        if (isset($data['sex']) && $data['sex'] !== null && $data['sex'] !== '') {
            if (!in_array($data['sex'], self::ALLOWED_SEX_VALUES)) {
                $errors['sex'] = 'Invalid sex value. Allowed values: male, female, other';
            }
        }
        
        // Validate bio length
        if (isset($data['bio']) && $data['bio'] !== null) {
            if (!$this->isValidBioLength($data['bio'])) {
                $errors['bio'] = 'Bio must be ' . self::MAX_BIO_LENGTH . ' characters or less';
            }
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
    
    /**
     * Validate contact number format
     * Requirement 2.2
     * 
     * @param string $contactNumber Contact number to validate
     * @return bool True if valid
     */
    public function isValidContactNumber(string $contactNumber): bool {
        // Allow digits, spaces, dashes, parentheses, and plus sign
        return preg_match('/^[\d\s\-\(\)\+]+$/', $contactNumber) === 1;
    }
    
    /**
     * Validate date of birth is in the past
     * Requirement 4.2
     * 
     * @param string $dateOfBirth Date string (Y-m-d format)
     * @return bool True if valid (in the past)
     */
    public function isValidDateOfBirth(string $dateOfBirth): bool {
        $date = DateTime::createFromFormat('Y-m-d', $dateOfBirth);
        if (!$date || $date->format('Y-m-d') !== $dateOfBirth) {
            return false;
        }
        
        $today = new DateTime('today');
        return $date < $today;
    }
    
    /**
     * Validate bio length
     * Requirement 7.2
     * 
     * @param string $bio Bio text to validate
     * @return bool True if valid (within length limit)
     */
    public function isValidBioLength(string $bio): bool {
        return strlen($bio) <= self::MAX_BIO_LENGTH;
    }
    
    /**
     * Upload profile picture
     * Requirements: 6.2, 6.3, 6.4
     * 
     * @param int $userId User ID
     * @param array $file Uploaded file data ($_FILES array element)
     * @return array Result with success status and file path
     */
    public function uploadProfilePicture(int $userId, array $file): array {
        try {
            // Validate file type
            $validation = $this->validateProfilePicture($file);
            if (!$validation['valid']) {
                return [
                    'success' => false,
                    'message' => $validation['error'],
                    'code' => 'VALIDATION_ERROR'
                ];
            }
            
            // Generate unique filename
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'profile_' . $userId . '_' . time() . '.' . $extension;
            $uploadDir = __DIR__ . '/../uploads/profiles/';
            
            // Create directory if it doesn't exist
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $filePath = $uploadDir . $filename;
            $relativePath = 'uploads/profiles/' . $filename;
            
            // Move uploaded file
            if (!move_uploaded_file($file['tmp_name'], $filePath)) {
                return [
                    'success' => false,
                    'message' => 'Failed to save uploaded file',
                    'code' => 'UPLOAD_ERROR'
                ];
            }
            
            // Get current profile to track old picture
            $currentProfile = $this->getProfile($userId);
            $oldPicture = $currentProfile['success'] ? ($currentProfile['data']['profile_picture'] ?? null) : null;
            
            // Update user record with new profile picture path
            $this->userRepository->disableCompanyFilter();
            $this->userRepository->update($userId, [
                'profile_picture' => $relativePath,
                'updated_at' => date('Y-m-d H:i:s')
            ]);
            
            // Create revision record
            $this->createRevision(
                $userId,
                ['profile_picture'],
                ['profile_picture' => $oldPicture],
                ['profile_picture' => $relativePath]
            );
            
            return [
                'success' => true,
                'message' => 'Profile picture uploaded successfully',
                'data' => [
                    'path' => $relativePath
                ]
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to upload profile picture: ' . $e->getMessage(),
                'code' => 'UPLOAD_ERROR'
            ];
        }
    }
    
    /**
     * Validate profile picture file
     * Requirements: 6.2, 6.3
     * 
     * @param array $file Uploaded file data
     * @return array Validation result with 'valid' boolean and 'error' string
     */
    public function validateProfilePicture(array $file): array {
        // Check for upload errors
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return ['valid' => false, 'error' => 'No file uploaded'];
        }
        
        // Validate file type
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);
        
        if (!in_array($mimeType, self::ALLOWED_IMAGE_TYPES)) {
            return [
                'valid' => false,
                'error' => 'Invalid file type. Only JPEG, PNG, and GIF images are allowed.'
            ];
        }
        
        // Validate file size
        if ($file['size'] > self::MAX_FILE_SIZE) {
            return [
                'valid' => false,
                'error' => 'File size must be under 2MB'
            ];
        }
        
        return ['valid' => true, 'error' => null];
    }
    
    /**
     * Get profile revision history
     * Requirement 8.3
     * 
     * @param int $userId User ID
     * @return array Result with success status and revisions data
     */
    public function getRevisionHistory(int $userId): array {
        try {
            $revisions = $this->revisionRepository->findByUserId($userId);
            
            return [
                'success' => true,
                'data' => $revisions
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to retrieve revision history: ' . $e->getMessage(),
                'code' => 'FETCH_ERROR'
            ];
        }
    }
    
    /**
     * Create a profile revision record
     * Requirement 8.1
     * 
     * @param int $userId User ID
     * @param array $changedFields List of changed field names
     * @param array $oldValues Old values keyed by field name
     * @param array $newValues New values keyed by field name
     * @return int Revision ID
     */
    public function createRevision(int $userId, array $changedFields, array $oldValues, array $newValues): int {
        return $this->revisionRepository->createRevision([
            'user_id' => $userId,
            'changed_fields' => $changedFields,
            'old_values' => $oldValues,
            'new_values' => $newValues
        ]);
    }
    
    /**
     * Check if a user can access another user's profile
     * Requirement 9.2
     * 
     * @param int $requestingUserId User making the request
     * @param int $targetUserId User whose profile is being accessed
     * @return bool True if access is allowed
     */
    public function canAccessProfile(int $requestingUserId, int $targetUserId): bool {
        // Users can only access their own profile
        return $requestingUserId === $targetUserId;
    }
}
