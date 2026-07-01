<?php
/**
 * InstallationCheckpoint Model
 * Represents section-wise approval status for installation reviews
 * 
 * Requirements: 12.1-12.7, 13.1-13.6
 * - 12.1-12.7: Contractor review with section-wise approve/reject
 * - 13.1-13.6: ADV final approval with section-wise options
 */

require_once __DIR__ . '/BaseModel.php';
require_once __DIR__ . '/../config/InstallationSections.php';

class InstallationCheckpoint extends BaseModel {
    protected $table = 'installation_checkpoints';
    protected $fillable = [
        'installation_id',
        'section',
        'contractor_status',
        'contractor_reviewer_id',
        'contractor_reviewed_at',
        'adv_status',
        'adv_reviewer_id',
        'adv_reviewed_at',
        'created_at',
        'updated_at'
    ];
    
    // Status constants
    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';
    
    // Reviewer level constants
    const LEVEL_CONTRACTOR = 'contractor';
    const LEVEL_ADV = 'adv';
    
    /**
     * Get all valid statuses
     * 
     * @return array List of valid status values
     */
    public static function getStatuses(): array {
        return [
            self::STATUS_PENDING,
            self::STATUS_APPROVED,
            self::STATUS_REJECTED
        ];
    }
    
    /**
     * Check if a status is valid
     * 
     * @param string $status Status to check
     * @return bool True if valid
     */
    public static function isValidStatus(string $status): bool {
        return in_array($status, self::getStatuses());
    }
    
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
     * Get status label for display
     * 
     * @param string $status Status value
     * @return string Human-readable label
     */
    public static function getStatusLabel(string $status): string {
        return match($status) {
            self::STATUS_PENDING => 'Pending',
            self::STATUS_APPROVED => 'Approved',
            self::STATUS_REJECTED => 'Rejected',
            default => ucfirst($status)
        };
    }
    
    /**
     * Convert checkpoint record to array
     * 
     * @param array $record Checkpoint record from database
     * @return array Serialized checkpoint data
     */
    public static function toArray(array $record): array {
        return [
            'id' => isset($record['id']) ? (int)$record['id'] : null,
            'installation_id' => isset($record['installation_id']) ? (int)$record['installation_id'] : null,
            'section' => $record['section'] ?? null,
            'contractor_status' => $record['contractor_status'] ?? self::STATUS_PENDING,
            'contractor_reviewer_id' => isset($record['contractor_reviewer_id']) ? (int)$record['contractor_reviewer_id'] : null,
            'contractor_reviewed_at' => $record['contractor_reviewed_at'] ?? null,
            'adv_status' => $record['adv_status'] ?? self::STATUS_PENDING,
            'adv_reviewer_id' => isset($record['adv_reviewer_id']) ? (int)$record['adv_reviewer_id'] : null,
            'adv_reviewed_at' => $record['adv_reviewed_at'] ?? null,
            'created_at' => $record['created_at'] ?? null,
            'updated_at' => $record['updated_at'] ?? null
        ];
    }
    
    /**
     * Convert array data to checkpoint record format
     * 
     * @param array $data Input data array
     * @return array Checkpoint record data
     */
    public static function fromArray(array $data): array {
        $record = [];
        
        // Integer fields
        $intFields = ['id', 'installation_id', 'contractor_reviewer_id', 'adv_reviewer_id'];
        foreach ($intFields as $field) {
            if (isset($data[$field]) && $data[$field] !== null && $data[$field] !== '') {
                $record[$field] = (int)$data[$field];
            } elseif (array_key_exists($field, $data)) {
                $record[$field] = null;
            }
        }
        
        // String fields
        if (array_key_exists('section', $data)) {
            $record['section'] = $data['section'] !== '' ? $data['section'] : null;
        }
        
        // Status fields with defaults
        if (array_key_exists('contractor_status', $data)) {
            $record['contractor_status'] = $data['contractor_status'] !== '' ? $data['contractor_status'] : self::STATUS_PENDING;
        }
        
        if (array_key_exists('adv_status', $data)) {
            $record['adv_status'] = $data['adv_status'] !== '' ? $data['adv_status'] : self::STATUS_PENDING;
        }
        
        // Datetime fields
        $datetimeFields = ['contractor_reviewed_at', 'adv_reviewed_at', 'created_at', 'updated_at'];
        foreach ($datetimeFields as $field) {
            if (array_key_exists($field, $data)) {
                $record[$field] = $data[$field] !== '' ? $data[$field] : null;
            }
        }
        
        return $record;
    }
    
    /**
     * Validate checkpoint data
     * 
     * @param array $data Checkpoint data to validate
     * @return array Validation result with 'isValid' and 'errors'
     */
    public function validate(array $data): array {
        $errors = [];
        
        // Validate required fields
        $requiredFields = ['installation_id', 'section'];
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
        
        // Validate contractor_status if provided
        if (isset($data['contractor_status']) && $data['contractor_status'] !== '') {
            if (!self::isValidStatus($data['contractor_status'])) {
                $errors[] = [
                    'field' => 'contractor_status',
                    'message' => 'Invalid contractor status value',
                    'code' => 'INVALID_STATUS'
                ];
            }
        }
        
        // Validate adv_status if provided
        if (isset($data['adv_status']) && $data['adv_status'] !== '') {
            if (!self::isValidStatus($data['adv_status'])) {
                $errors[] = [
                    'field' => 'adv_status',
                    'message' => 'Invalid ADV status value',
                    'code' => 'INVALID_STATUS'
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
        
        return [
            'isValid' => empty($errors),
            'errors' => $errors
        ];
    }
    
    /**
     * Check if section is approved at contractor level
     * 
     * @param array $checkpoint Checkpoint record
     * @return bool True if approved
     */
    public static function isContractorApproved(array $checkpoint): bool {
        return ($checkpoint['contractor_status'] ?? '') === self::STATUS_APPROVED;
    }
    
    /**
     * Check if section is rejected at contractor level
     * 
     * @param array $checkpoint Checkpoint record
     * @return bool True if rejected
     */
    public static function isContractorRejected(array $checkpoint): bool {
        return ($checkpoint['contractor_status'] ?? '') === self::STATUS_REJECTED;
    }
    
    /**
     * Check if section is approved at ADV level
     * 
     * @param array $checkpoint Checkpoint record
     * @return bool True if approved
     */
    public static function isAdvApproved(array $checkpoint): bool {
        return ($checkpoint['adv_status'] ?? '') === self::STATUS_APPROVED;
    }
    
    /**
     * Check if section is rejected at ADV level
     * 
     * @param array $checkpoint Checkpoint record
     * @return bool True if rejected
     */
    public static function isAdvRejected(array $checkpoint): bool {
        return ($checkpoint['adv_status'] ?? '') === self::STATUS_REJECTED;
    }
    
    /**
     * Check if section is pending at contractor level
     * 
     * @param array $checkpoint Checkpoint record
     * @return bool True if pending
     */
    public static function isContractorPending(array $checkpoint): bool {
        return ($checkpoint['contractor_status'] ?? self::STATUS_PENDING) === self::STATUS_PENDING;
    }
    
    /**
     * Check if section is pending at ADV level
     * 
     * @param array $checkpoint Checkpoint record
     * @return bool True if pending
     */
    public static function isAdvPending(array $checkpoint): bool {
        return ($checkpoint['adv_status'] ?? self::STATUS_PENDING) === self::STATUS_PENDING;
    }
}
