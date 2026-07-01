<?php
/**
 * Bulk Upload Log Service
 * Handles logging of bulk uploads and generating downloadable Excel files
 */

require_once __DIR__ . '/../config/autoload.php';
require_once __DIR__ . '/../repositories/BulkUploadLogRepository.php';

class BulkUploadLogService {
    private $logRepository;
    private $uploadDir;
    
    public function __construct() {
        $this->logRepository = new BulkUploadLogRepository();
        $this->uploadDir = __DIR__ . '/../uploads/bulk_logs';
        
        // Ensure upload directory exists
        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0755, true);
        }
    }
    
    /**
     * Log a bulk upload operation and generate downloadable files
     */
    public function logUpload(array $params): array {
        $uploadType = $params['upload_type'];
        $originalFilename = $params['original_filename'];
        $totalRows = $params['total_rows'];
        $successCount = $params['success_count'];
        $errorCount = $params['error_count'];
        $successRecords = $params['success_records'] ?? [];
        $errorRecords = $params['error_records'] ?? [];
        $uploadedBy = $params['uploaded_by'];
        $companyId = $params['company_id'] ?? null;
        $columnHeaders = $params['column_headers'] ?? [];
        
        // Generate unique file prefix
        $timestamp = date('Ymd_His');
        $filePrefix = "{$uploadType}_{$timestamp}_{$uploadedBy}";
        
        // Generate success file if there are success records
        $successFile = null;
        if (!empty($successRecords)) {
            $successFile = $this->generateCsvFile(
                $successRecords,
                $columnHeaders,
                "{$filePrefix}_success.csv"
            );
        }
        
        // Generate error file if there are error records
        $errorFile = null;
        if (!empty($errorRecords)) {
            $errorFile = $this->generateErrorCsvFile(
                $errorRecords,
                $columnHeaders,
                "{$filePrefix}_errors.csv"
            );
        }
        
        // Create log entry
        $log = $this->logRepository->create([
            'upload_type' => $uploadType,
            'original_filename' => $originalFilename,
            'total_rows' => $totalRows,
            'success_count' => $successCount,
            'error_count' => $errorCount,
            'success_file' => $successFile,
            'error_file' => $errorFile,
            'success_data' => !empty($successRecords) ? json_encode($successRecords) : null,
            'error_data' => !empty($errorRecords) ? json_encode($errorRecords) : null,
            'uploaded_by' => $uploadedBy,
            'company_id' => $companyId
        ]);
        
        return $log;
    }
    
    /**
     * Generate CSV file for success records
     */
    private function generateCsvFile(array $records, array $headers, string $filename): string {
        $filepath = $this->uploadDir . '/' . $filename;
        
        $fp = fopen($filepath, 'w');
        
        // Add BOM for Excel UTF-8 compatibility
        fprintf($fp, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Write headers
        if (!empty($headers)) {
            fputcsv($fp, $headers);
        } elseif (!empty($records)) {
            fputcsv($fp, array_keys($records[0]));
        }
        
        // Write data rows
        foreach ($records as $record) {
            // Remove internal fields
            unset($record['_row_number']);
            fputcsv($fp, array_values($record));
        }
        
        fclose($fp);
        
        return $filename;
    }
    
    /**
     * Generate CSV file for error records with error messages
     */
    private function generateErrorCsvFile(array $errorRecords, array $headers, string $filename): string {
        $filepath = $this->uploadDir . '/' . $filename;
        
        $fp = fopen($filepath, 'w');
        
        // Add BOM for Excel UTF-8 compatibility
        fprintf($fp, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Write headers with error column
        $errorHeaders = array_merge(['Row Number'], $headers, ['Error Message']);
        fputcsv($fp, $errorHeaders);
        
        // Write error rows
        foreach ($errorRecords as $record) {
            $rowNum = $record['_row_number'] ?? '';
            $errors = $record['_errors'] ?? [];
            
            // Format error messages
            $errorMessages = [];
            foreach ($errors as $error) {
                if (is_array($error)) {
                    $errorMessages[] = $error['message'] ?? json_encode($error);
                } else {
                    $errorMessages[] = $error;
                }
            }
            $errorText = implode('; ', $errorMessages);
            
            // Remove internal fields
            unset($record['_row_number'], $record['_errors']);
            
            // Build row with row number, data, and errors
            $row = array_merge([$rowNum], array_values($record), [$errorText]);
            fputcsv($fp, $row);
        }
        
        fclose($fp);
        
        return $filename;
    }
    
    /**
     * Get upload log by ID
     */
    public function getLog(int $id): ?array {
        return $this->logRepository->findById($id);
    }
    
    /**
     * Get upload logs with filters
     */
    public function getLogs(array $filters = []): array {
        return $this->logRepository->findAll($filters);
    }
    
    /**
     * Get file path for download
     */
    public function getFilePath(string $filename): ?string {
        $filepath = $this->uploadDir . '/' . $filename;
        if (file_exists($filepath)) {
            return $filepath;
        }
        return null;
    }
    
    /**
     * Check if file exists
     */
    public function fileExists(string $filename): bool {
        return file_exists($this->uploadDir . '/' . $filename);
    }
}
