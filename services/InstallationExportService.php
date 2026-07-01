<?php
/**
 * Installation Export Service
 * Handles export of installation data to Excel/CSV/JSON formats
 * 
 * Requirements: 16.4
 * - 16.4: Generate an Excel file containing all installation records with complete details
 * 
 * **Feature: installation-module, Property 29: Installation export round-trip**
 * **Validates: Requirements 16.4**
 */

require_once __DIR__ . '/../config/autoload.php';
require_once __DIR__ . '/../repositories/InstallationRepository.php';
require_once __DIR__ . '/../models/Installation.php';

class InstallationExportService {
    private $db;
    private $installationRepository;
    
    // Export format constants
    const FORMAT_CSV = 'csv';
    const FORMAT_EXCEL = 'xlsx';
    const FORMAT_JSON = 'json';
    
    public function __construct() {
        $this->db = DatabaseConfig::getInstance();
        $this->installationRepository = new InstallationRepository();
    }
    
    /**
     * Export installation data to specified format
     * Requirement 16.4: Generate Excel file with all installation data
     * 
     * @param array $filters Optional filters (status, date_from, date_to, company_id)
     * @param string $format Export format (csv, xlsx, json)
     * @return array Result with export data or file content
     */
    public function exportToExcel(array $filters = [], string $format = self::FORMAT_CSV): array {
        // Validate format
        if (!$this->isValidFormat($format)) {
            return [
                'success' => false,
                'message' => "Invalid export format: $format",
                'code' => 'INVALID_FORMAT'
            ];
        }
        
        try {
            // Get data for export
            $data = $this->getExportData($filters);
            
            if (empty($data['records'])) {
                return [
                    'success' => true,
                    'message' => 'No data to export',
                    'data' => [
                        'records' => [],
                        'count' => 0
                    ]
                ];
            }
            
            // Format data for export
            $formattedData = $this->formatForExport($data['records']);
            
            // Generate export based on format
            switch ($format) {
                case self::FORMAT_CSV:
                    $result = $this->generateCsv($formattedData);
                    break;
                case self::FORMAT_JSON:
                    $result = $this->generateJson($formattedData);
                    break;
                case self::FORMAT_EXCEL:
                    $result = $this->generateExcel($formattedData);
                    break;
                default:
                    $result = $this->generateCsv($formattedData);
            }
            
            return [
                'success' => true,
                'message' => 'Export generated successfully',
                'data' => array_merge($result, [
                    'count' => count($formattedData),
                    'format' => $format,
                    'generated_at' => date('Y-m-d H:i:s')
                ])
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Export failed: ' . $e->getMessage(),
                'code' => 'EXPORT_ERROR'
            ];
        }
    }
    
    /**
     * Get installation data for export
     * Requirement 16.4: Get all installation records with complete details
     * 
     * @param array $filters Optional filters
     * @return array Data records
     */
    public function getExportData(array $filters = []): array {
        $records = $this->installationRepository->findAllForExport($filters);
        return ['records' => $records];
    }

    
    /**
     * Format installation records for export
     * Ensures round-trip consistency by including all necessary fields
     * 
     * @param array $records Raw installation records
     * @return array Formatted records
     */
    public function formatForExport(array $records): array {
        return array_map(function($record) {
            return $this->formatInstallationRecord($record);
        }, $records);
    }
    
    /**
     * Format a single installation record for export
     * Requirement 16.4: Include all installation data fields
     * 
     * @param array $record Raw installation record
     * @return array Formatted record
     */
    private function formatInstallationRecord(array $record): array {
        return [
            // Core identifiers
            'id' => $record['id'] ?? null,
            'site_id' => $record['site_id'] ?? null,
            'feasibility_id' => $record['feasibility_id'] ?? null,
            'initiated_by' => $record['initiated_by'] ?? null,
            'initiated_at' => $record['initiated_at'] ?? null,
            
            // Site Information
            'atm_id' => $record['atm_id'] ?? '',
            'atm_id_2' => $record['atm_id_2'] ?? '',
            'atm_id_3' => $record['atm_id_3'] ?? '',
            'address' => $record['address'] ?? '',
            'city' => $record['city'] ?? '',
            'location' => $record['location'] ?? '',
            'lho' => $record['lho'] ?? '',
            'state' => $record['state'] ?? '',
            'atm_working_1' => $record['atm_working_1'] ?? '',
            'atm_working_2' => $record['atm_working_2'] ?? '',
            'atm_working_3' => $record['atm_working_3'] ?? '',
            
            // Vendor/Engineer Information
            'vendor_name' => $record['vendor_name'] ?? '',
            'engineer_name' => $record['engineer_name'] ?? '',
            'engineer_number' => $record['engineer_number'] ?? '',
            
            // Router Section
            'router_serial' => $record['router_serial'] ?? '',
            'router_make' => $record['router_make'] ?? '',
            'router_model' => $record['router_model'] ?? '',
            'router_fixed' => $record['router_fixed'] ?? '',
            'router_fixed_remarks' => $record['router_fixed_remarks'] ?? '',
            'router_fixed_snaps' => $record['router_fixed_snaps'] ?? '',
            'router_status' => $record['router_status'] ?? '',
            'router_status_remarks' => $record['router_status_remarks'] ?? '',
            'router_status_snaps' => $record['router_status_snaps'] ?? '',
            
            // Adaptor Section
            'adaptor_installed' => $record['adaptor_installed'] ?? '',
            'adaptor_snaps' => $record['adaptor_snaps'] ?? '',
            'adaptor_status' => $record['adaptor_status'] ?? '',
            'adaptor_status_remarks' => $record['adaptor_status_remarks'] ?? '',
            'adaptor_status_snaps' => $record['adaptor_status_snaps'] ?? '',
            
            // LAN Cable Section
            'lan_cable_installed' => $record['lan_cable_installed'] ?? '',
            'lan_cable_install_remark' => $record['lan_cable_install_remark'] ?? '',
            'lan_cable_install_snap' => $record['lan_cable_install_snap'] ?? '',
            'lan_cable_status' => $record['lan_cable_status'] ?? '',
            'lan_cable_status_not_working_reasons' => $record['lan_cable_status_not_working_reasons'] ?? '',
            'lan_cable_status_remark' => $record['lan_cable_status_remark'] ?? '',
            'lan_cable_status_snap' => $record['lan_cable_status_snap'] ?? '',
            
            // Antenna Section
            'antenna_installed' => $record['antenna_installed'] ?? '',
            'antenna_remarks' => $record['antenna_remarks'] ?? '',
            'antenna_snaps' => $record['antenna_snaps'] ?? '',
            'antenna_status' => $record['antenna_status'] ?? '',
            'antenna_status_remarks' => $record['antenna_status_remarks'] ?? '',
            'antenna_status_snaps' => $record['antenna_status_snaps'] ?? '',
            
            // GPS Section
            'gps_installed' => $record['gps_installed'] ?? '',
            'gps_remarks' => $record['gps_remarks'] ?? '',
            'gps_snaps' => $record['gps_snaps'] ?? '',
            'gps_status' => $record['gps_status'] ?? '',
            'gps_status_remarks' => $record['gps_status_remarks'] ?? '',
            'gps_status_snaps' => $record['gps_status_snaps'] ?? '',
            
            // WiFi Section
            'wifi_installed' => $record['wifi_installed'] ?? '',
            'wifi_remarks' => $record['wifi_remarks'] ?? '',
            'wifi_snaps' => $record['wifi_snaps'] ?? '',
            'wifi_status' => $record['wifi_status'] ?? '',
            'wifi_status_remarks' => $record['wifi_status_remarks'] ?? '',
            'wifi_status_snaps' => $record['wifi_status_snaps'] ?? '',
            
            // Airtel SIM Section
            'airtel_sim_installed' => $record['airtel_sim_installed'] ?? '',
            'airtel_sim_remarks' => $record['airtel_sim_remarks'] ?? '',
            'airtel_sim_snaps' => $record['airtel_sim_snaps'] ?? '',
            'airtel_sim_status' => $record['airtel_sim_status'] ?? '',
            'airtel_sim_status_remarks' => $record['airtel_sim_status_remarks'] ?? '',
            'airtel_sim_status_snaps' => $record['airtel_sim_status_snaps'] ?? '',
            
            // Vodafone SIM Section
            'vodafone_sim_installed' => $record['vodafone_sim_installed'] ?? '',
            'vodafone_sim_remarks' => $record['vodafone_sim_remarks'] ?? '',
            'vodafone_sim_snaps' => $record['vodafone_sim_snaps'] ?? '',
            'vodafone_sim_status' => $record['vodafone_sim_status'] ?? '',
            'vodafone_sim_status_remarks' => $record['vodafone_sim_status_remarks'] ?? '',
            'vodafone_sim_status_snaps' => $record['vodafone_sim_status_snaps'] ?? '',
            
            // JIO SIM Section
            'jio_sim_installed' => $record['jio_sim_installed'] ?? '',
            'jio_sim_remarks' => $record['jio_sim_remarks'] ?? '',
            'jio_sim_snaps' => $record['jio_sim_snaps'] ?? '',
            'jio_sim_status' => $record['jio_sim_status'] ?? '',
            'jio_sim_status_remarks' => $record['jio_sim_status_remarks'] ?? '',
            'jio_sim_status_snaps' => $record['jio_sim_status_snaps'] ?? '',
            
            // Verification Section
            'signature_image' => $record['signature_image'] ?? '',
            'vendor_stamp' => $record['vendor_stamp'] ?? '',
            
            // Status
            'status' => $record['status'] ?? '',
            
            // Audit fields
            'created_by' => $record['created_by'] ?? null,
            'created_at' => $record['created_at'] ?? null,
            'updated_at' => $record['updated_at'] ?? null,
            'submitted_by' => $record['submitted_by'] ?? null,
            'submitted_at' => $record['submitted_at'] ?? null,
            
            // Related data (if available from join)
            'site_name' => $record['site_name'] ?? '',
            'initiated_by_name' => $record['initiated_by_name'] ?? '',
            'submitted_by_name' => $record['submitted_by_name'] ?? ''
        ];
    }

    
    /**
     * Generate CSV export
     * 
     * @param array $data Formatted data
     * @return array Result with CSV content
     */
    private function generateCsv(array $data): array {
        if (empty($data)) {
            return ['content' => '', 'filename' => ''];
        }
        
        $csvContent = $this->arrayToCsv($data);
        
        return [
            'content' => $csvContent,
            'filename' => 'installations_export_' . date('Y-m-d_His') . '.csv',
            'mime_type' => 'text/csv'
        ];
    }
    
    /**
     * Convert array to CSV string
     * 
     * @param array $data Data array
     * @return string CSV content
     */
    private function arrayToCsv(array $data): string {
        if (empty($data)) {
            return '';
        }
        
        $output = fopen('php://temp', 'r+');
        
        // Write header row
        $headers = array_keys($data[0]);
        fputcsv($output, $headers);
        
        // Write data rows
        foreach ($data as $row) {
            // Convert arrays/objects to JSON strings for CSV
            $csvRow = array_map(function($value) {
                if (is_array($value) || is_object($value)) {
                    return json_encode($value);
                }
                return $value;
            }, $row);
            fputcsv($output, $csvRow);
        }
        
        rewind($output);
        $csvContent = stream_get_contents($output);
        fclose($output);
        
        return $csvContent;
    }
    
    /**
     * Generate JSON export
     * Ensures round-trip consistency for Property 29
     * 
     * @param array $data Formatted data
     * @return array Result with JSON content
     */
    private function generateJson(array $data): array {
        $exportData = [
            'export_type' => 'installations',
            'export_date' => date('Y-m-d H:i:s'),
            'record_count' => count($data),
            'data' => $data
        ];
        
        return [
            'content' => json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            'filename' => 'installations_export_' . date('Y-m-d_His') . '.json',
            'mime_type' => 'application/json'
        ];
    }
    
    /**
     * Generate Excel export (CSV format compatible with Excel)
     * For full Excel support, PHPSpreadsheet would be needed
     * 
     * @param array $data Formatted data
     * @return array Result with Excel content
     */
    private function generateExcel(array $data): array {
        // Generate CSV content (can be opened in Excel)
        $csvResult = $this->generateCsv($data);
        
        return [
            'content' => $csvResult['content'],
            'filename' => 'installations_export_' . date('Y-m-d_His') . '.csv',
            'mime_type' => 'text/csv',
            'note' => 'CSV format compatible with Excel'
        ];
    }
    
    /**
     * Parse imported data for re-import validation
     * Requirement 16.4: Validate against original schema for round-trip consistency
     * 
     * @param string $content Import content
     * @param string $format Import format
     * @return array Validation result
     */
    public function parseImport(string $content, string $format): array {
        try {
            switch ($format) {
                case self::FORMAT_JSON:
                    return $this->parseJsonImport($content);
                case self::FORMAT_CSV:
                    return $this->parseCsvImport($content);
                default:
                    return [
                        'success' => false,
                        'message' => "Unsupported import format: $format",
                        'code' => 'UNSUPPORTED_FORMAT'
                    ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Parse error: ' . $e->getMessage(),
                'code' => 'PARSE_ERROR'
            ];
        }
    }
    
    /**
     * Parse JSON import
     * 
     * @param string $content JSON content
     * @return array Parse result
     */
    private function parseJsonImport(string $content): array {
        $data = json_decode($content, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return [
                'success' => false,
                'message' => 'Invalid JSON: ' . json_last_error_msg(),
                'code' => 'INVALID_JSON'
            ];
        }
        
        if (!isset($data['data'])) {
            return [
                'success' => false,
                'message' => 'Missing data field in JSON',
                'code' => 'MISSING_DATA'
            ];
        }
        
        // Validate records against schema
        $validationResult = $this->validateRecords($data['data']);
        
        return [
            'success' => $validationResult['valid'],
            'message' => $validationResult['valid'] ? 'Import validated successfully' : 'Validation failed',
            'data' => [
                'records' => $data['data'],
                'valid_count' => $validationResult['valid_count'],
                'invalid_count' => $validationResult['invalid_count'],
                'errors' => $validationResult['errors']
            ]
        ];
    }
    
    /**
     * Parse CSV import
     * 
     * @param string $content CSV content
     * @return array Parse result
     */
    private function parseCsvImport(string $content): array {
        $lines = explode("\n", trim($content));
        
        if (count($lines) < 2) {
            return [
                'success' => false,
                'message' => 'CSV must have header row and at least one data row',
                'code' => 'INSUFFICIENT_DATA'
            ];
        }
        
        $headers = str_getcsv($lines[0]);
        $records = [];
        
        for ($i = 1; $i < count($lines); $i++) {
            if (empty(trim($lines[$i]))) continue;
            
            $values = str_getcsv($lines[$i]);
            if (count($values) !== count($headers)) {
                continue; // Skip malformed rows
            }
            
            $record = array_combine($headers, $values);
            $records[] = $record;
        }
        
        // Validate records against schema
        $validationResult = $this->validateRecords($records);
        
        return [
            'success' => $validationResult['valid'],
            'message' => $validationResult['valid'] ? 'Import validated successfully' : 'Validation failed',
            'data' => [
                'records' => $records,
                'valid_count' => $validationResult['valid_count'],
                'invalid_count' => $validationResult['invalid_count'],
                'errors' => $validationResult['errors']
            ]
        ];
    }
    
    /**
     * Validate records against installation schema
     * 
     * @param array $records Records to validate
     * @return array Validation result
     */
    private function validateRecords(array $records): array {
        $requiredFields = $this->getRequiredFields();
        $validCount = 0;
        $invalidCount = 0;
        $errors = [];
        
        foreach ($records as $index => $record) {
            $recordErrors = [];
            
            foreach ($requiredFields as $field) {
                if (!isset($record[$field]) || $record[$field] === '') {
                    $recordErrors[] = "Missing required field: $field";
                }
            }
            
            if (empty($recordErrors)) {
                $validCount++;
            } else {
                $invalidCount++;
                $errors[$index] = $recordErrors;
            }
        }
        
        return [
            'valid' => $invalidCount === 0,
            'valid_count' => $validCount,
            'invalid_count' => $invalidCount,
            'errors' => $errors
        ];
    }
    
    /**
     * Get required fields for installation export validation
     * 
     * @return array Required field names
     */
    private function getRequiredFields(): array {
        return ['id', 'site_id', 'atm_id'];
    }
    
    /**
     * Check if format is valid
     * 
     * @param string $format Format to check
     * @return bool True if valid
     */
    private function isValidFormat(string $format): bool {
        return in_array($format, [
            self::FORMAT_CSV,
            self::FORMAT_EXCEL,
            self::FORMAT_JSON
        ]);
    }
    
    /**
     * Get available export formats
     * 
     * @return array Available formats
     */
    public static function getFormats(): array {
        return [
            self::FORMAT_CSV,
            self::FORMAT_EXCEL,
            self::FORMAT_JSON
        ];
    }
}
