<?php
/**
 * Site Serializer Utility
 * Handles serialization and deserialization of Site objects to/from JSON and Excel formats
 * 
 * Requirements: 7.4, 7.5
 * - 7.4: WHEN site data is serialized for storage or export THEN the System SHALL produce valid JSON or Excel format
 * - 7.5: WHEN serialized site data is parsed THEN the System SHALL reconstruct the original site object with all fields intact
 */

require_once __DIR__ . '/../config/autoload.php';

class SiteSerializer {
    
    /**
     * All site fields that should be serialized
     */
    private static $allFields = [
        'id', 'site_name', 'lho', 'bank_name', 'customer_name',
        'city', 'state', 'country', 'zone', 'address',
        'latitude', 'longitude', 'company_id', 'status',
        'created_at', 'created_by', 'updated_at', 'updated_by'
    ];
    
    /**
     * Fields that should be treated as numeric
     */
    private static $numericFields = ['id', 'company_id', 'created_by', 'updated_by'];
    
    /**
     * Fields that should be treated as float
     */
    private static $floatFields = ['latitude', 'longitude'];
    
    /**
     * Excel column mapping for export/import
     */
    private static $excelColumnMapping = [
        'A' => 'id',
        'B' => 'site_name',
        'C' => 'lho',
        'D' => 'bank_name',
        'E' => 'customer_name',
        'F' => 'city',
        'G' => 'state',
        'H' => 'country',
        'I' => 'zone',
        'J' => 'address',
        'K' => 'latitude',
        'L' => 'longitude',
        'M' => 'company_id',
        'N' => 'status',
        'O' => 'created_at',
        'P' => 'created_by',
        'Q' => 'updated_at',
        'R' => 'updated_by'
    ];
    
    /**
     * Serialize a site array to JSON string
     * 
     * Requirement 7.4: Produce valid JSON format
     * 
     * @param array $site Site data array
     * @return string JSON string representation
     */
    public static function toJson(array $site): string {
        // Normalize the site data before serialization
        $normalized = self::normalizeSiteData($site);
        
        // Encode to JSON with proper options
        $json = json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        
        if ($json === false) {
            throw new InvalidArgumentException('Failed to encode site data to JSON: ' . json_last_error_msg());
        }
        
        return $json;
    }
    
    /**
     * Deserialize a JSON string to site array
     * 
     * Requirement 7.5: Reconstruct original site object with all fields intact
     * 
     * @param string $json JSON string
     * @return array Site data array
     */
    public static function fromJson(string $json): array {
        if (empty(trim($json))) {
            throw new InvalidArgumentException('JSON string cannot be empty');
        }
        
        $data = json_decode($json, true);
        
        if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidArgumentException('Invalid JSON: ' . json_last_error_msg());
        }
        
        // Normalize the deserialized data
        return self::normalizeSiteData($data);
    }
    
    /**
     * Convert a site array to an Excel row array
     * 
     * Requirement 7.4: Produce valid Excel format
     * 
     * @param array $site Site data array
     * @return array Indexed array suitable for Excel row
     */
    public static function toExcelRow(array $site): array {
        $row = [];
        
        // Build row in column order
        foreach (self::$excelColumnMapping as $column => $field) {
            $value = $site[$field] ?? null;
            
            // Convert values appropriately for Excel
            if ($value === null) {
                $row[] = '';
            } elseif (in_array($field, self::$floatFields)) {
                // Keep float precision for coordinates
                $row[] = $value !== '' ? (float)$value : '';
            } elseif (in_array($field, self::$numericFields)) {
                $row[] = $value !== '' ? (int)$value : '';
            } else {
                $row[] = (string)$value;
            }
        }
        
        return $row;
    }
    
    /**
     * Convert an Excel row array to a site array
     * 
     * Requirement 7.5: Reconstruct original site object with all fields intact
     * 
     * @param array $row Indexed array from Excel row
     * @return array Site data array
     */
    public static function fromExcelRow(array $row): array {
        $site = [];
        $columnIndex = 0;
        
        foreach (self::$excelColumnMapping as $column => $field) {
            $value = $row[$columnIndex] ?? null;
            
            // Convert values appropriately
            if ($value === '' || $value === null) {
                $site[$field] = null;
            } elseif (in_array($field, self::$floatFields)) {
                $site[$field] = (float)$value;
            } elseif (in_array($field, self::$numericFields)) {
                $site[$field] = (int)$value;
            } else {
                $site[$field] = (string)$value;
            }
            
            $columnIndex++;
        }
        
        return $site;
    }
    
    /**
     * Get Excel headers for site export
     * 
     * @return array Array of header names
     */
    public static function getExcelHeaders(): array {
        return [
            'ID', 'Site Name', 'LHO', 'Bank Name', 'Customer Name',
            'City', 'State', 'Country', 'Zone', 'Address',
            'Latitude', 'Longitude', 'Company ID', 'Status',
            'Created At', 'Created By', 'Updated At', 'Updated By'
        ];
    }
    
    /**
     * Get the Excel column mapping
     * 
     * @return array Column to field mapping
     */
    public static function getExcelColumnMapping(): array {
        return self::$excelColumnMapping;
    }
    
    /**
     * Get all serializable fields
     * 
     * @return array List of field names
     */
    public static function getAllFields(): array {
        return self::$allFields;
    }
    
    /**
     * Normalize site data for consistent serialization
     * 
     * @param array $site Site data array
     * @return array Normalized site data
     */
    private static function normalizeSiteData(array $site): array {
        $normalized = [];
        
        foreach (self::$allFields as $field) {
            if (!array_key_exists($field, $site)) {
                $normalized[$field] = null;
                continue;
            }
            
            $value = $site[$field];
            
            // Handle null values
            if ($value === null || $value === '') {
                $normalized[$field] = null;
                continue;
            }
            
            // Type conversion based on field type
            if (in_array($field, self::$floatFields)) {
                $normalized[$field] = (float)$value;
            } elseif (in_array($field, self::$numericFields)) {
                $normalized[$field] = (int)$value;
            } else {
                $normalized[$field] = (string)$value;
            }
        }
        
        return $normalized;
    }
    
    /**
     * Validate that two site arrays are equivalent after serialization round-trip
     * 
     * @param array $original Original site data
     * @param array $deserialized Deserialized site data
     * @return array Validation result with 'isEqual' and 'differences'
     */
    public static function compareForEquality(array $original, array $deserialized): array {
        $differences = [];
        
        // Normalize both for comparison
        $normalizedOriginal = self::normalizeSiteData($original);
        $normalizedDeserialized = self::normalizeSiteData($deserialized);
        
        foreach (self::$allFields as $field) {
            $origValue = $normalizedOriginal[$field];
            $deserValue = $normalizedDeserialized[$field];
            
            // Handle float comparison with tolerance
            if (in_array($field, self::$floatFields)) {
                if ($origValue === null && $deserValue === null) {
                    continue;
                }
                if ($origValue === null || $deserValue === null) {
                    $differences[$field] = [
                        'original' => $origValue,
                        'deserialized' => $deserValue
                    ];
                    continue;
                }
                // Use tolerance for float comparison
                if (abs((float)$origValue - (float)$deserValue) > 0.000001) {
                    $differences[$field] = [
                        'original' => $origValue,
                        'deserialized' => $deserValue
                    ];
                }
            } else {
                // Direct comparison for other types
                if ($origValue !== $deserValue) {
                    $differences[$field] = [
                        'original' => $origValue,
                        'deserialized' => $deserValue
                    ];
                }
            }
        }
        
        return [
            'isEqual' => empty($differences),
            'differences' => $differences
        ];
    }
}
