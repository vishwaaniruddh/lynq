<?php
/**
 * MaterialReceipt Model
 * Represents a material receipt confirmation for an installation
 * 
 * Requirements: 2.2
 * - 2.2: Record confirmation with timestamp and engineer ID
 */

require_once __DIR__ . '/BaseModel.php';

class MaterialReceipt extends BaseModel {
    protected $table = 'installation_material_receipts';
    protected $fillable = [
        'installation_id',
        'confirmed_by',
        'confirmed_at',
        'created_at'
    ];
    
    /**
     * Convert material receipt record to array
     * 
     * @param array $record Material receipt record from database
     * @return array Serialized material receipt data
     */
    public static function toArray(array $record): array {
        return [
            'id' => isset($record['id']) ? (int)$record['id'] : null,
            'installation_id' => isset($record['installation_id']) ? (int)$record['installation_id'] : null,
            'confirmed_by' => isset($record['confirmed_by']) ? (int)$record['confirmed_by'] : null,
            'confirmed_at' => $record['confirmed_at'] ?? null,
            'created_at' => $record['created_at'] ?? null
        ];
    }
    
    /**
     * Convert array data to material receipt record format
     * 
     * @param array $data Input data array
     * @return array Material receipt record data
     */
    public static function fromArray(array $data): array {
        $record = [];
        
        // Integer fields
        $intFields = ['id', 'installation_id', 'confirmed_by'];
        foreach ($intFields as $field) {
            if (isset($data[$field]) && $data[$field] !== null && $data[$field] !== '') {
                $record[$field] = (int)$data[$field];
            } elseif (array_key_exists($field, $data)) {
                $record[$field] = null;
            }
        }
        
        // Datetime fields
        $datetimeFields = ['confirmed_at', 'created_at'];
        foreach ($datetimeFields as $field) {
            if (array_key_exists($field, $data)) {
                $record[$field] = $data[$field] !== '' ? $data[$field] : null;
            }
        }
        
        return $record;
    }
    
    /**
     * Find material receipt by installation ID
     * 
     * @param int $installationId Installation ID
     * @return array|null Material receipt record or null
     */
    public function findByInstallationId(int $installationId): ?array {
        $sql = "SELECT * FROM `{$this->table}` WHERE `installation_id` = ?";
        $result = DatabaseConfig::getInstance()->getResults($sql, [$installationId], 'i');
        return !empty($result) ? $result[0] : null;
    }
    
    /**
     * Check if materials have been received for an installation
     * 
     * @param int $installationId Installation ID
     * @return bool True if materials have been received
     */
    public function hasMaterialsReceived(int $installationId): bool {
        $receipt = $this->findByInstallationId($installationId);
        return $receipt !== null;
    }
    
    /**
     * Validate material receipt data
     * 
     * @param array $data Material receipt data to validate
     * @return array Validation result with 'isValid' and 'errors'
     */
    public function validate(array $data): array {
        $errors = [];
        
        // Validate required fields
        $requiredFields = ['installation_id', 'confirmed_by'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || $data[$field] === '' || $data[$field] === null) {
                $errors[] = [
                    'field' => $field,
                    'message' => "The {$field} field is required",
                    'code' => 'REQUIRED_FIELD_MISSING'
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
        
        // Validate confirmed_by is a positive integer
        if (isset($data['confirmed_by']) && $data['confirmed_by'] !== '') {
            if (!is_numeric($data['confirmed_by']) || (int)$data['confirmed_by'] <= 0) {
                $errors[] = [
                    'field' => 'confirmed_by',
                    'message' => 'Confirmed by must be a positive integer',
                    'code' => 'INVALID_CONFIRMED_BY'
                ];
            }
        }
        
        return [
            'isValid' => empty($errors),
            'errors' => $errors
        ];
    }
}
