<?php
/**
 * InstallationSectionRemark Model
 * Represents review remarks/comments for installation sections
 * 
 * Requirements: 12.2, 12.3
 * - 12.2: Record approval with reviewer ID, timestamp, and optional remarks
 * - 12.3: Require rejection reason (minimum 10 characters)
 */

require_once __DIR__ . '/BaseModel.php';
require_once __DIR__ . '/../config/InstallationSections.php';

class InstallationSectionRemark extends BaseModel {
    protected $table = 'installation_section_remarks';
    protected $fillable = [
        'installation_id',
        'section',
        'reviewer_id',
        'reviewer_level',
        'review_type',
        'remark',
        'created_at'
    ];
    
    // Reviewer level constants
    const LEVEL_CONTRACTOR = 'contractor';
    const LEVEL_ADV = 'adv';
    
    // Review type constants
    const TYPE_APPROVAL = 'approval';
    const TYPE_REJECTION = 'rejection';
    
    // Minimum rejection reason length
    const MIN_REJECTION_REASON_LENGTH = 10;
    
    /**
     * Get all valid reviewer levels
     * 
     * @return array List of valid reviewer levels
     */
    public static function getReviewerLevels(): array {
        return [
            self::LEVEL_CONTRACTOR,
            self::LEVEL_ADV
        ];
    }
    
    /**
     * Check if a reviewer level is valid
     * 
     * @param string $level Reviewer level to check
     * @return bool True if valid
     */
    public static function isValidReviewerLevel(string $level): bool {
        return in_array($level, self::getReviewerLevels());
    }
    
    /**
     * Get all valid review types
     * 
     * @return array List of valid review types
     */
    public static function getReviewTypes(): array {
        return [
            self::TYPE_APPROVAL,
            self::TYPE_REJECTION
        ];
    }
    
    /**
     * Check if a review type is valid
     * 
     * @param string $type Review type to check
     * @return bool True if valid
     */
    public static function isValidReviewType(string $type): bool {
        return in_array($type, self::getReviewTypes());
    }
    
    /**
     * Get reviewer level label for display
     * 
     * @param string $level Reviewer level value
     * @return string Human-readable label
     */
    public static function getReviewerLevelLabel(string $level): string {
        return match($level) {
            self::LEVEL_CONTRACTOR => 'Contractor',
            self::LEVEL_ADV => 'ADV',
            default => ucfirst($level)
        };
    }
    
    /**
     * Get review type label for display
     * 
     * @param string $type Review type value
     * @return string Human-readable label
     */
    public static function getReviewTypeLabel(string $type): string {
        return match($type) {
            self::TYPE_APPROVAL => 'Approval',
            self::TYPE_REJECTION => 'Rejection',
            default => ucfirst($type)
        };
    }
    
    /**
     * Convert remark record to array
     * 
     * @param array $record Remark record from database
     * @return array Serialized remark data
     */
    public static function toArray(array $record): array {
        return [
            'id' => isset($record['id']) ? (int)$record['id'] : null,
            'installation_id' => isset($record['installation_id']) ? (int)$record['installation_id'] : null,
            'section' => $record['section'] ?? null,
            'reviewer_id' => isset($record['reviewer_id']) ? (int)$record['reviewer_id'] : null,
            'reviewer_level' => $record['reviewer_level'] ?? null,
            'review_type' => $record['review_type'] ?? null,
            'remark' => $record['remark'] ?? null,
            'created_at' => $record['created_at'] ?? null,
            // Include joined fields if present
            'reviewer_name' => $record['reviewer_name'] ?? null
        ];
    }
    
    /**
     * Convert array data to remark record format
     * 
     * @param array $data Input data array
     * @return array Remark record data
     */
    public static function fromArray(array $data): array {
        $record = [];
        
        // Integer fields
        $intFields = ['id', 'installation_id', 'reviewer_id'];
        foreach ($intFields as $field) {
            if (isset($data[$field]) && $data[$field] !== null && $data[$field] !== '') {
                $record[$field] = (int)$data[$field];
            } elseif (array_key_exists($field, $data)) {
                $record[$field] = null;
            }
        }
        
        // String fields
        $stringFields = ['section', 'reviewer_level', 'review_type', 'remark'];
        foreach ($stringFields as $field) {
            if (array_key_exists($field, $data)) {
                $record[$field] = $data[$field] !== '' ? $data[$field] : null;
            }
        }
        
        // Datetime fields
        if (array_key_exists('created_at', $data)) {
            $record['created_at'] = $data['created_at'] !== '' ? $data['created_at'] : null;
        }
        
        return $record;
    }
    
    /**
     * Validate remark data
     * 
     * @param array $data Remark data to validate
     * @return array Validation result with 'isValid' and 'errors'
     */
    public function validate(array $data): array {
        $errors = [];
        
        // Validate required fields
        $requiredFields = ['installation_id', 'section', 'reviewer_id', 'reviewer_level', 'review_type'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || $data[$field] === '' || $data[$field] === null) {
                $errors[] = [
                    'field' => $field,
                    'message' => "The {$field} field is required",
                    'code' => 'REQUIRED_FIELD_MISSING'
                ];
            }
        }
        
        // Validate section is valid
        if (isset($data['section']) && $data['section'] !== '') {
            if (!InstallationSections::isValid($data['section'])) {
                $errors[] = [
                    'field' => 'section',
                    'message' => 'Invalid section identifier',
                    'code' => 'INVALID_SECTION'
                ];
            }
        }
        
        // Validate reviewer_level if provided
        if (isset($data['reviewer_level']) && $data['reviewer_level'] !== '') {
            if (!self::isValidReviewerLevel($data['reviewer_level'])) {
                $errors[] = [
                    'field' => 'reviewer_level',
                    'message' => 'Invalid reviewer level value',
                    'code' => 'INVALID_REVIEWER_LEVEL'
                ];
            }
        }
        
        // Validate review_type if provided
        if (isset($data['review_type']) && $data['review_type'] !== '') {
            if (!self::isValidReviewType($data['review_type'])) {
                $errors[] = [
                    'field' => 'review_type',
                    'message' => 'Invalid review type value',
                    'code' => 'INVALID_REVIEW_TYPE'
                ];
            }
        }
        
        // Validate rejection reason length (Requirements 12.3)
        if (isset($data['review_type']) && $data['review_type'] === self::TYPE_REJECTION) {
            if (!isset($data['remark']) || $data['remark'] === '' || $data['remark'] === null) {
                $errors[] = [
                    'field' => 'remark',
                    'message' => 'Rejection reason is required',
                    'code' => 'REJECTION_REASON_REQUIRED'
                ];
            } elseif (strlen(trim($data['remark'])) < self::MIN_REJECTION_REASON_LENGTH) {
                $errors[] = [
                    'field' => 'remark',
                    'message' => 'Rejection reason must be at least ' . self::MIN_REJECTION_REASON_LENGTH . ' characters',
                    'code' => 'REJECTION_REASON_TOO_SHORT'
                ];
            }
        }
        
        // Validate installation_id is a positive integer
        if (isset($data['installation_id']) && $data['installation_id'] !== '') {
            if (!is_numeric($data['installation_id']) || (int)$data['installation_id'] <= 0) {
                $errors[] = [
                    'field' => 'installation_id',
                    'message' => 'Installation ID must be a positive integer',
                    'code' => 'INVALID_INSTALLATION_ID'
                ];
            }
        }
        
        // Validate reviewer_id is a positive integer
        if (isset($data['reviewer_id']) && $data['reviewer_id'] !== '') {
            if (!is_numeric($data['reviewer_id']) || (int)$data['reviewer_id'] <= 0) {
                $errors[] = [
                    'field' => 'reviewer_id',
                    'message' => 'Reviewer ID must be a positive integer',
                    'code' => 'INVALID_REVIEWER_ID'
                ];
            }
        }
        
        return [
            'isValid' => empty($errors),
            'errors' => $errors
        ];
    }
    
    /**
     * Check if remark is an approval
     * 
     * @param array $remark Remark record
     * @return bool True if approval
     */
    public static function isApproval(array $remark): bool {
        return ($remark['review_type'] ?? '') === self::TYPE_APPROVAL;
    }
    
    /**
     * Check if remark is a rejection
     * 
     * @param array $remark Remark record
     * @return bool True if rejection
     */
    public static function isRejection(array $remark): bool {
        return ($remark['review_type'] ?? '') === self::TYPE_REJECTION;
    }
    
    /**
     * Check if remark is from contractor level
     * 
     * @param array $remark Remark record
     * @return bool True if from contractor
     */
    public static function isContractorRemark(array $remark): bool {
        return ($remark['reviewer_level'] ?? '') === self::LEVEL_CONTRACTOR;
    }
    
    /**
     * Check if remark is from ADV level
     * 
     * @param array $remark Remark record
     * @return bool True if from ADV
     */
    public static function isAdvRemark(array $remark): bool {
        return ($remark['reviewer_level'] ?? '') === self::LEVEL_ADV;
    }
}
