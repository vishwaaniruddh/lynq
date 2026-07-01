<?php
/**
 * Site Model
 * Handles site data with validation for coordinates and required fields
 * 
 * Requirements: 7.1, 7.2, 7.3
 */

require_once __DIR__ . '/BaseModel.php';

class Site extends BaseModel {
    protected $table = 'sites';
    protected $fillable = [
        'site_name', 'lho', 'bank_name', 'customer_name', 
        'city', 'state', 'country', 'zone', 'address',
        'latitude', 'longitude', 'company_id', 'status',
        'created_by', 'updated_by'
    ];
    
    /**
     * Required fields for site creation
     */
    protected $requiredFields = ['site_name', 'lho', 'city', 'state', 'country'];
    
    /**
     * Validate site data
     * Returns array with 'isValid' boolean and 'errors' array
     * 
     * Requirements: 7.1, 7.2, 7.3
     * 
     * @param array $data Site data to validate
     * @return array Validation result with 'isValid' and 'errors'
     */
    public function validate(array $data): array {
        $errors = [];
        
        // Validate required fields (Requirement 7.3)
        $requiredErrors = $this->validateRequiredFields($data);
        $errors = array_merge($errors, $requiredErrors);
        
        // Validate coordinates (Requirements 7.1, 7.2)
        $coordinateErrors = $this->validateCoordinates($data);
        $errors = array_merge($errors, $coordinateErrors);
        
        return [
            'isValid' => empty($errors),
            'errors' => $errors
        ];
    }
    
    /**
     * Validate required fields
     * Requirement 7.3
     * 
     * @param array $data Site data
     * @return array Array of validation errors
     */
    public function validateRequiredFields(array $data): array {
        $errors = [];
        
        foreach ($this->requiredFields as $field) {
            if (!isset($data[$field]) || trim((string)$data[$field]) === '') {
                $errors[] = [
                    'field' => $field,
                    'message' => "The {$field} field is required",
                    'code' => 'REQUIRED_FIELD_MISSING'
                ];
            }
        }
        
        return $errors;
    }
    
    /**
     * Validate latitude and longitude coordinates
     * Requirements 7.1, 7.2
     * 
     * @param array $data Site data
     * @return array Array of validation errors
     */
    public function validateCoordinates(array $data): array {
        $errors = [];
        
        // Validate latitude (Requirement 7.1)
        if (isset($data['latitude']) && $data['latitude'] !== null && $data['latitude'] !== '') {
            $latitude = (float)$data['latitude'];
            if ($latitude < -90 || $latitude > 90) {
                $errors[] = [
                    'field' => 'latitude',
                    'message' => 'Latitude must be between -90 and 90 degrees',
                    'code' => 'INVALID_LATITUDE'
                ];
            }
        }
        
        // Validate longitude (Requirement 7.2)
        if (isset($data['longitude']) && $data['longitude'] !== null && $data['longitude'] !== '') {
            $longitude = (float)$data['longitude'];
            if ($longitude < -180 || $longitude > 180) {
                $errors[] = [
                    'field' => 'longitude',
                    'message' => 'Longitude must be between -180 and 180 degrees',
                    'code' => 'INVALID_LONGITUDE'
                ];
            }
        }
        
        return $errors;
    }
    
    /**
     * Check if latitude is valid
     * Requirement 7.1
     * 
     * @param mixed $latitude Latitude value to check
     * @return bool True if valid
     */
    public static function isValidLatitude($latitude): bool {
        if ($latitude === null || $latitude === '') {
            return true; // Null/empty is allowed
        }
        $lat = (float)$latitude;
        return $lat >= -90 && $lat <= 90;
    }
    
    /**
     * Check if longitude is valid
     * Requirement 7.2
     * 
     * @param mixed $longitude Longitude value to check
     * @return bool True if valid
     */
    public static function isValidLongitude($longitude): bool {
        if ($longitude === null || $longitude === '') {
            return true; // Null/empty is allowed
        }
        $lng = (float)$longitude;
        return $lng >= -180 && $lng <= 180;
    }
    
    /**
     * Find sites by company
     * 
     * @param int $companyId Company ID
     * @param array $filters Optional filters
     * @return array Sites
     */
    public function findByCompany(int $companyId, array $filters = []): array {
        $conditions = ['company_id' => $companyId];
        
        if (isset($filters['status'])) {
            $conditions['status'] = $filters['status'];
        }
        
        return $this->findAll($conditions, 'site_name');
    }
    
    /**
     * Find sites by LHO
     * 
     * @param string $lho LHO name
     * @return array Sites
     */
    public function findByLHO(string $lho): array {
        return $this->findAll(['lho' => $lho], 'site_name');
    }
    
    /**
     * Check if site name exists within LHO and company
     * Requirement 1.5
     * 
     * @param string $siteName Site name
     * @param string $lho LHO
     * @param int $companyId Company ID
     * @param int|null $excludeId Site ID to exclude (for updates)
     * @return bool True if duplicate exists
     */
    public function isDuplicate(string $siteName, string $lho, int $companyId, ?int $excludeId = null): bool {
        $sql = "SELECT COUNT(*) as count FROM `{$this->table}` 
                WHERE `site_name` = ? AND `lho` = ? AND `company_id` = ? AND `status` != 'deleted'";
        $params = [$siteName, $lho, $companyId];
        $types = 'ssi';
        
        if ($excludeId !== null) {
            $sql .= " AND `id` != ?";
            $params[] = $excludeId;
            $types .= 'i';
        }
        
        $result = DatabaseConfig::getInstance()->getResults($sql, $params, $types);
        return $result[0]['count'] > 0;
    }
    
    /**
     * Find active sites
     * 
     * @return array Active sites
     */
    public function findActive(): array {
        return $this->findAll(['status' => 'active'], 'site_name');
    }
    
    /**
     * Soft delete site
     * 
     * @param int $id Site ID
     * @param int $userId User performing the delete
     * @return bool Success
     */
    public function softDelete(int $id, int $userId): bool {
        $sql = "UPDATE `{$this->table}` SET `status` = 'deleted', `updated_by` = ?, `updated_at` = NOW() WHERE `id` = ?";
        $stmt = DatabaseConfig::getInstance()->executeQuery($sql, [$userId, $id], 'ii');
        $affectedRows = $stmt->affected_rows;
        $stmt->close();
        
        return $affectedRows > 0;
    }
}
