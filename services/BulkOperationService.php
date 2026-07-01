<?php
/**
 * Bulk Operation Service
 * Provides common bulk operation functionality for Excel import/export
 * 
 * Requirements: 1.2, 1.3, 2.3, 5.3
 * - 1.2: Validate each row and create records for valid entries
 * - 1.3: Reject invalid rows with detailed error messages
 * - 2.3: Process bulk delegation Excel files
 * - 5.3: Process bulk engineer assignment Excel files
 */

require_once __DIR__ . '/../config/autoload.php';

// Include PhpSpreadsheet library if available (only needed for Excel import/export)
$vendorAutoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($vendorAutoload)) {
    require_once $vendorAutoload;
}

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Result class for bulk operations
 */
class BulkOperationResult {
    public int $totalRows = 0;
    public int $successCount = 0;
    public int $errorCount = 0;
    public array $errors = [];      // [rowNumber => errorMessage]
    public array $createdIds = [];
    public bool $success = false;
    public string $message = '';
    
    public function toArray(): array {
        return [
            'success' => $this->success,
            'message' => $this->message,
            'totalRows' => $this->totalRows,
            'successCount' => $this->successCount,
            'errorCount' => $this->errorCount,
            'errors' => $this->errors,
            'createdIds' => $this->createdIds
        ];
    }
}

class BulkOperationService {
    private $db;
    private $uploadDir;
    
    public function __construct() {
        $this->db = DatabaseConfig::getInstance();
        // Set upload directory relative to the clarity folder
        $this->uploadDir = realpath(__DIR__ . '/../../PHPExcel') ?: __DIR__ . '/../../PHPExcel';
    }
    
    /**
     * Parse an Excel or CSV file and return data as array
     * 
     * @param string $filePath Path to the Excel/CSV file
     * @param array $columnMapping Mapping of column letters to field names
     *                             e.g., ['A' => 'site_name', 'B' => 'lho', ...]
     * @param int $headerRow Row number containing headers (default 1)
     * @param int $dataStartRow Row number where data starts (default 2)
     * @return array Result with 'success', 'data', 'errors'
     * 
     * Requirements: 1.2, 2.3, 5.3
     */
    public function parseExcelFile(string $filePath, array $columnMapping, int $headerRow = 1, int $dataStartRow = 2): array {
        // Check if file exists
        if (!file_exists($filePath)) {
            return [
                'success' => false,
                'message' => "File not found: {$filePath}",
                'data' => [],
                'errors' => []
            ];
        }
        
        // Detect file type by reading first bytes or extension
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        
        // For CSV files, use native PHP parsing (no ZipArchive needed)
        if ($ext === 'csv' || $this->isCSVFile($filePath)) {
            return $this->parseCSVFile($filePath, $columnMapping, $headerRow, $dataStartRow);
        }
        
        // Check if ZipArchive is available for xlsx files
        if (!class_exists('ZipArchive')) {
            return [
                'success' => false,
                'message' => 'Cannot process Excel files: PHP ZipArchive extension is not enabled. Please use CSV format instead.',
                'data' => [],
                'errors' => []
            ];
        }
        
        try {
            // Load the spreadsheet using PhpSpreadsheet
            $spreadsheet = IOFactory::load($filePath);
            $sheet = $spreadsheet->getActiveSheet();
            $highestRow = $sheet->getHighestRow();
            
            $data = [];
            
            // Parse each data row
            for ($row = $dataStartRow; $row <= $highestRow; $row++) {
                $rowData = [];
                $hasData = false;
                
                foreach ($columnMapping as $column => $fieldName) {
                    $cellValue = $sheet->getCell("{$column}{$row}")->getValue();
                    
                    // Trim string values
                    if (is_string($cellValue)) {
                        $cellValue = trim($cellValue);
                    }
                    
                    // Check if row has any data
                    if ($cellValue !== null && $cellValue !== '') {
                        $hasData = true;
                    }
                    
                    $rowData[$fieldName] = $cellValue;
                }
                
                // Only include rows that have data
                if ($hasData) {
                    $rowData['_row_number'] = $row;
                    $data[] = $rowData;
                }
            }
            
            return [
                'success' => true,
                'message' => 'File parsed successfully',
                'data' => $data,
                'totalRows' => count($data),
                'errors' => []
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error parsing file: ' . $e->getMessage(),
                'data' => [],
                'errors' => [['row' => 0, 'message' => $e->getMessage()]]
            ];
        }
    }
    
    /**
     * Validate bulk data using a callback validator
     * 
     * @param array $data Array of data rows to validate
     * @param callable $validator Callback function that takes a row and returns validation result
     *                           Should return ['isValid' => bool, 'errors' => array]
     * @return array Result with 'validRows', 'invalidRows', 'errors'
     * 
     * Requirements: 1.2, 1.3
     */
    public function validateBulkData(array $data, callable $validator): array {
        $validRows = [];
        $invalidRows = [];
        $errors = [];
        
        foreach ($data as $index => $row) {
            $rowNumber = $row['_row_number'] ?? ($index + 2); // Default to index + 2 (assuming header is row 1)
            
            $validationResult = $validator($row);
            
            if ($validationResult['isValid']) {
                $validRows[] = $row;
            } else {
                $invalidRows[] = $row;
                $errors[$rowNumber] = $validationResult['errors'];
            }
        }
        
        return [
            'validRows' => $validRows,
            'invalidRows' => $invalidRows,
            'validCount' => count($validRows),
            'invalidCount' => count($invalidRows),
            'errors' => $errors
        ];
    }
    
    /**
     * Generate an Excel export file from data
     * 
     * @param array $data Array of data rows to export
     * @param array $headers Array of header names in order
     * @param array $columnMapping Mapping of field names to column letters (optional)
     * @param string $filename Output filename (without extension)
     * @return string Path to generated file or empty string on failure
     * 
     * Requirements: 3.4
     */
    public function generateExcelExport(array $data, array $headers, array $columnMapping = [], string $filename = 'export'): string {
        try {
            // Create new Spreadsheet object
            $spreadsheet = new Spreadsheet();
            
            // Set document properties
            $spreadsheet->getProperties()
                ->setCreator("Clarity CRM")
                ->setLastModifiedBy("Clarity CRM")
                ->setTitle("Export")
                ->setSubject("Data Export")
                ->setDescription("Exported data from Clarity CRM");
            
            $sheet = $spreadsheet->getActiveSheet();
            
            // Write headers
            $col = 'A';
            foreach ($headers as $header) {
                $sheet->setCellValue("{$col}1", $header);
                // Style header row
                $sheet->getStyle("{$col}1")->getFont()->setBold(true);
                $col++;
            }
            
            // If no column mapping provided, create one from headers
            if (empty($columnMapping)) {
                $col = 'A';
                foreach ($headers as $header) {
                    // Convert header to field name (lowercase, replace spaces with underscores)
                    $fieldName = strtolower(str_replace(' ', '_', $header));
                    $columnMapping[$fieldName] = $col;
                    $col++;
                }
            }
            
            // Write data rows
            $row = 2;
            foreach ($data as $rowData) {
                foreach ($columnMapping as $fieldName => $column) {
                    $value = $rowData[$fieldName] ?? '';
                    $sheet->setCellValue("{$column}{$row}", $value);
                }
                $row++;
            }
            
            // Auto-size columns
            $col = 'A';
            foreach ($headers as $header) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
                $col++;
            }
            
            // Generate unique filename
            $timestamp = date('Y-m-d_His');
            $outputPath = "{$this->uploadDir}/{$filename}_{$timestamp}.xlsx";
            
            // Save file
            $writer = new Xlsx($spreadsheet);
            $writer->save($outputPath);
            
            return $outputPath;
            
        } catch (Exception $e) {
            error_log("Error generating Excel export: " . $e->getMessage());
            return '';
        }
    }
    
    /**
     * Generate an error report Excel file
     * 
     * @param array $errors Array of errors [rowNumber => errorMessages]
     * @param string $filename Output filename (without extension)
     * @return string Path to generated file or empty string on failure
     * 
     * Requirements: 1.3
     */
    public function generateErrorReport(array $errors, string $filename = 'error_report'): string {
        try {
            // Create new Spreadsheet object
            $spreadsheet = new Spreadsheet();
            
            $sheet = $spreadsheet->getActiveSheet();
            
            // Write headers
            $sheet->setCellValue('A1', 'Row Number');
            $sheet->setCellValue('B1', 'Error Message');
            $sheet->getStyle('A1:B1')->getFont()->setBold(true);
            
            // Write error data
            $row = 2;
            foreach ($errors as $rowNumber => $errorMessages) {
                if (is_array($errorMessages)) {
                    foreach ($errorMessages as $error) {
                        $message = is_array($error) ? ($error['message'] ?? json_encode($error)) : $error;
                        $sheet->setCellValue("A{$row}", $rowNumber);
                        $sheet->setCellValue("B{$row}", $message);
                        $row++;
                    }
                } else {
                    $sheet->setCellValue("A{$row}", $rowNumber);
                    $sheet->setCellValue("B{$row}", $errorMessages);
                    $row++;
                }
            }
            
            // Auto-size columns
            $sheet->getColumnDimension('A')->setAutoSize(true);
            $sheet->getColumnDimension('B')->setAutoSize(true);
            
            // Generate unique filename
            $timestamp = date('Y-m-d_His');
            $outputPath = "{$this->uploadDir}/{$filename}_{$timestamp}.xlsx";
            
            // Save file
            $writer = new Xlsx($spreadsheet);
            $writer->save($outputPath);
            
            return $outputPath;
            
        } catch (Exception $e) {
            error_log("Error generating error report: " . $e->getMessage());
            return '';
        }
    }
    
    /**
     * Handle file upload and move to upload directory
     * 
     * @param array $fileData $_FILES array element
     * @return array Result with 'success', 'path', 'message'
     */
    public function handleFileUpload(array $fileData): array {
        // Check for upload errors
        if ($fileData['error'] !== UPLOAD_ERR_OK) {
            $errorMessages = [
                UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize',
                UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE',
                UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
                UPLOAD_ERR_NO_FILE => 'No file was uploaded',
                UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
                UPLOAD_ERR_EXTENSION => 'File upload stopped by extension'
            ];
            
            return [
                'success' => false,
                'path' => '',
                'message' => $errorMessages[$fileData['error']] ?? 'Unknown upload error'
            ];
        }
        
        // Validate file extension
        $allowedExtensions = ['xlsx', 'xls', 'csv'];
        $extension = strtolower(pathinfo($fileData['name'], PATHINFO_EXTENSION));
        
        if (!in_array($extension, $allowedExtensions)) {
            return [
                'success' => false,
                'path' => '',
                'message' => 'Invalid file type. Allowed types: ' . implode(', ', $allowedExtensions)
            ];
        }
        
        // Generate unique filename
        $timestamp = date('Y-m-d_His');
        $newFilename = pathinfo($fileData['name'], PATHINFO_FILENAME) . '_' . $timestamp . '.' . $extension;
        $targetPath = "{$this->uploadDir}/{$newFilename}";
        
        // Move uploaded file
        if (!move_uploaded_file($fileData['tmp_name'], $targetPath)) {
            return [
                'success' => false,
                'path' => '',
                'message' => 'Failed to move uploaded file'
            ];
        }
        
        return [
            'success' => true,
            'path' => $targetPath,
            'message' => 'File uploaded successfully'
        ];
    }
    
    /**
     * Get column mapping for site bulk upload
     * 
     * @return array Column mapping for sites
     */
    public function getSiteColumnMapping(): array {
        return [
            'A' => 'site_name',
            'B' => 'lho',
            'C' => 'bank_name',
            'D' => 'customer_name',
            'E' => 'city',
            'F' => 'state',
            'G' => 'country',
            'H' => 'zone',
            'I' => 'address',
            'J' => 'latitude',
            'K' => 'longitude'
        ];
    }
    
    /**
     * Get column mapping for delegation bulk upload
     * 
     * @return array Column mapping for delegations
     */
    public function getDelegationColumnMapping(): array {
        return [
            'A' => 'site_id',
            'B' => 'contractor_id'
        ];
    }
    
    /**
     * Get column mapping for engineer assignment bulk upload
     * 
     * @return array Column mapping for engineer assignments
     */
    public function getEngineerAssignmentColumnMapping(): array {
        return [
            'A' => 'site_id',
            'B' => 'engineer_id'
        ];
    }
    
    /**
     * Get headers for site export
     * 
     * @return array Headers for site export
     */
    public function getSiteExportHeaders(): array {
        return [
            'ID', 'Site Name', 'LHO', 'Bank Name', 'Customer Name',
            'City', 'State', 'Country', 'Zone', 'Address',
            'Latitude', 'Longitude', 'Status', 'Created At', 'Created By'
        ];
    }
    
    /**
     * Get headers for delegation export
     * 
     * @return array Headers for delegation export
     */
    public function getDelegationExportHeaders(): array {
        return [
            'ID', 'Site ID', 'Site Name', 'Contractor ID', 'Contractor Name',
            'Status', 'Delegated By', 'Delegated At', 'Responded By', 'Responded At',
            'Rejection Notes'
        ];
    }
    
    /**
     * Get headers for engineer assignment export
     * 
     * @return array Headers for engineer assignment export
     */
    public function getEngineerAssignmentExportHeaders(): array {
        return [
            'ID', 'Site ID', 'Site Name', 'Engineer ID', 'Engineer Name',
            'Status', 'Assigned By', 'Assigned At'
        ];
    }
    
    /**
     * Clean up old uploaded files (older than specified days)
     * 
     * @param int $daysOld Number of days after which to delete files
     * @return int Number of files deleted
     */
    public function cleanupOldFiles(int $daysOld = 7): int {
        $deleted = 0;
        $cutoffTime = time() - $daysOld * 24 * 60 * 60;
        
        $files = glob("{$this->uploadDir}/*.{xlsx,xls,csv}", GLOB_BRACE);
        
        foreach ($files as $file) {
            if (filemtime($file) < $cutoffTime) {
                if (unlink($file)) {
                    $deleted++;
                }
            }
        }
        
        return $deleted;
    }
    
    /**
     * Check if a file is a CSV file by reading its content
     * 
     * @param string $filePath Path to the file
     * @return bool True if file appears to be CSV
     */
    private function isCSVFile(string $filePath): bool {
        // Check extension first
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        if ($ext === 'csv') {
            return true;
        }
        
        // Check file content - CSV files are plain text
        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            return false;
        }
        
        // Read first 512 bytes to check if it's text
        $content = fread($handle, 512);
        fclose($handle);
        
        if ($content === false) {
            return false;
        }
        
        // Check for binary content (xlsx files start with PK signature)
        if (substr($content, 0, 2) === 'PK') {
            return false; // This is a ZIP-based file (xlsx)
        }
        
        // Check if content is mostly printable ASCII
        $printable = 0;
        $total = strlen($content);
        for ($i = 0; $i < $total; $i++) {
            $ord = ord($content[$i]);
            // Printable ASCII, tab, newline, carriage return
            if (($ord >= 32 && $ord <= 126) || $ord === 9 || $ord === 10 || $ord === 13) {
                $printable++;
            }
        }
        
        // If more than 90% is printable, likely CSV
        return ($printable / $total) > 0.9;
    }
    
    /**
     * Parse a CSV file and return data as array
     * 
     * @param string $filePath Path to the CSV file
     * @param array $columnMapping Mapping of column letters to field names
     * @param int $headerRow Row number containing headers (default 1)
     * @param int $dataStartRow Row number where data starts (default 2)
     * @return array Result with 'success', 'data', 'errors'
     */
    private function parseCSVFile(string $filePath, array $columnMapping, int $headerRow = 1, int $dataStartRow = 2): array {
        try {
            $handle = fopen($filePath, 'r');
            if ($handle === false) {
                return [
                    'success' => false,
                    'message' => 'Could not open CSV file',
                    'data' => [],
                    'errors' => []
                ];
            }
            
            // Convert column letters to indices (A=0, B=1, etc.)
            $columnIndices = [];
            foreach ($columnMapping as $column => $fieldName) {
                $columnIndices[$this->columnLetterToIndex($column)] = $fieldName;
            }
            
            $data = [];
            $rowNumber = 0;
            
            while (($row = fgetcsv($handle)) !== false) {
                $rowNumber++;
                
                // Skip header rows
                if ($rowNumber < $dataStartRow) {
                    continue;
                }
                
                $rowData = [];
                $hasData = false;
                
                foreach ($columnIndices as $index => $fieldName) {
                    $cellValue = isset($row[$index]) ? trim($row[$index]) : '';
                    
                    if ($cellValue !== '') {
                        $hasData = true;
                    }
                    
                    $rowData[$fieldName] = $cellValue;
                }
                
                // Only include rows that have data
                if ($hasData) {
                    $rowData['_row_number'] = $rowNumber;
                    $data[] = $rowData;
                }
            }
            
            fclose($handle);
            
            return [
                'success' => true,
                'message' => 'CSV file parsed successfully',
                'data' => $data,
                'totalRows' => count($data),
                'errors' => []
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error parsing CSV file: ' . $e->getMessage(),
                'data' => [],
                'errors' => [['row' => 0, 'message' => $e->getMessage()]]
            ];
        }
    }
    
    /**
     * Convert Excel column letter to zero-based index
     * 
     * @param string $column Column letter (A, B, ..., Z, AA, AB, etc.)
     * @return int Zero-based column index
     */
    private function columnLetterToIndex(string $column): int {
        $column = strtoupper($column);
        $length = strlen($column);
        $index = 0;
        
        for ($i = 0; $i < $length; $i++) {
            $index = $index * 26 + (ord($column[$i]) - ord('A') + 1);
        }
        
        return $index - 1; // Convert to zero-based
    }
}
