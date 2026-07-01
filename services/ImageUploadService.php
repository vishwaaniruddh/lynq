<?php
/**
 * Image Upload Service
 * Handles image upload operations for feasibility checks
 * 
 * Requirements: 6.2, 6.3, 6.5
 * - 6.2: Validate file type (JPEG, PNG) and size (max 5MB)
 * - 6.3: Store image with unique filename linked to feasibility record
 * - 6.5: Store all uploaded image paths in feasibility record
 */

require_once __DIR__ . '/../config/autoload.php';

class ImageUploadService {
    private $db;
    private $uploadDir;
    private $maxFileSize;
    private $allowedTypes;
    private $allowedMimeTypes;
    
    public function __construct() {
        $this->db = DatabaseConfig::getInstance();
        $this->uploadDir = __DIR__ . '/../uploads/feasibility/';
        $this->maxFileSize = 5 * 1024 * 1024; // 5MB in bytes
        $this->allowedTypes = ['jpg', 'jpeg', 'png'];
        $this->allowedMimeTypes = ['image/jpeg', 'image/png'];
        
        // Ensure upload directory exists
        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0755, true);
        }
    }
    
    /**
     * Upload an image for a feasibility check
     * 
     * @param array $file File data from $_FILES
     * @param string $category Image category (e.g., 'backroom_network', 'ups_available')
     * @param int $feasibilityId Feasibility check ID
     * @return array Result with success status and file path or errors
     * 
     * Requirements: 6.2, 6.3
     */
    public function uploadImage(array $file, string $category, int $feasibilityId): array {
        // Validate the image
        $validation = $this->validateImage($file);
        if (!$validation['isValid']) {
            return [
                'success' => false,
                'message' => $validation['message'],
                'errors' => $validation['errors'],
                'code' => 'VALIDATION_ERROR'
            ];
        }
        
        try {
            // Generate unique filename
            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $uniqueFilename = $this->generateUniqueFilename($feasibilityId, $category, $extension);
            
            // Create subdirectory for feasibility ID
            $subDir = $this->uploadDir . $feasibilityId . '/';
            if (!is_dir($subDir)) {
                mkdir($subDir, 0755, true);
            }
            
            $targetPath = $subDir . $uniqueFilename;
            $relativePath = 'uploads/feasibility/' . $feasibilityId . '/' . $uniqueFilename;
            
            // Move uploaded file
            if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
                return [
                    'success' => false,
                    'message' => 'Failed to save uploaded file',
                    'code' => 'UPLOAD_ERROR'
                ];
            }
            
            // Log audit
            $this->logAction($feasibilityId, 'image_uploaded', [
                'category' => $category,
                'filename' => $uniqueFilename,
                'original_name' => $file['name'],
                'size' => $file['size']
            ]);
            
            return [
                'success' => true,
                'message' => 'Image uploaded successfully',
                'data' => [
                    'filename' => $uniqueFilename,
                    'path' => $relativePath,
                    'category' => $category,
                    'size' => $file['size']
                ]
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to upload image: ' . $e->getMessage(),
                'code' => 'UPLOAD_ERROR'
            ];
        }
    }
    
    /**
     * Validate an image file
     * 
     * @param array $file File data from $_FILES
     * @return array Validation result with isValid, message, and errors
     * 
     * Requirements: 6.2
     */
    public function validateImage(array $file): array {
        $errors = [];
        
        // Check if file was uploaded
        if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
            $errors[] = [
                'field' => 'file',
                'message' => 'No file was uploaded',
                'code' => 'NO_FILE'
            ];
            return [
                'isValid' => false,
                'message' => 'No file was uploaded',
                'errors' => $errors
            ];
        }
        
        // Check for upload errors
        if (isset($file['error']) && $file['error'] !== UPLOAD_ERR_OK) {
            $errorMessage = $this->getUploadErrorMessage($file['error']);
            $errors[] = [
                'field' => 'file',
                'message' => $errorMessage,
                'code' => 'UPLOAD_ERROR'
            ];
            return [
                'isValid' => false,
                'message' => $errorMessage,
                'errors' => $errors
            ];
        }
        
        // Validate file size (max 5MB)
        if ($file['size'] > $this->maxFileSize) {
            $maxSizeMB = $this->maxFileSize / (1024 * 1024);
            $errors[] = [
                'field' => 'file',
                'message' => "File size exceeds maximum allowed size of {$maxSizeMB}MB",
                'code' => 'FILE_TOO_LARGE'
            ];
        }
        
        // Validate file extension
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $this->allowedTypes)) {
            $errors[] = [
                'field' => 'file',
                'message' => 'Invalid file type. Only JPEG and PNG images are allowed',
                'code' => 'INVALID_FILE_TYPE'
            ];
        }
        
        // Validate MIME type by checking file content
        if (is_uploaded_file($file['tmp_name'])) {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->file($file['tmp_name']);
            
            if (!in_array($mimeType, $this->allowedMimeTypes)) {
                $errors[] = [
                    'field' => 'file',
                    'message' => 'Invalid file content. Only JPEG and PNG images are allowed',
                    'code' => 'INVALID_MIME_TYPE'
                ];
            }
        }
        
        // Verify it's actually an image
        if (is_uploaded_file($file['tmp_name'])) {
            $imageInfo = @getimagesize($file['tmp_name']);
            if ($imageInfo === false) {
                $errors[] = [
                    'field' => 'file',
                    'message' => 'File is not a valid image',
                    'code' => 'INVALID_IMAGE'
                ];
            }
        }
        
        if (!empty($errors)) {
            return [
                'isValid' => false,
                'message' => $errors[0]['message'],
                'errors' => $errors
            ];
        }
        
        return [
            'isValid' => true,
            'message' => 'Valid image file',
            'errors' => []
        ];
    }
    
    /**
     * Delete an image
     * 
     * @param string $filePath Relative file path
     * @return bool True if deleted successfully
     */
    public function deleteImage(string $filePath): bool {
        $fullPath = __DIR__ . '/../' . $filePath;
        
        if (file_exists($fullPath)) {
            return unlink($fullPath);
        }
        
        return false;
    }
    
    /**
     * Get all images for a feasibility check
     * 
     * @param int $feasibilityId Feasibility check ID
     * @return array List of image files
     */
    public function getImagesByFeasibility(int $feasibilityId): array {
        $images = [];
        $dir = $this->uploadDir . $feasibilityId . '/';
        
        if (is_dir($dir)) {
            $files = scandir($dir);
            foreach ($files as $file) {
                if ($file !== '.' && $file !== '..') {
                    $images[] = [
                        'filename' => $file,
                        'path' => 'uploads/feasibility/' . $feasibilityId . '/' . $file,
                        'size' => filesize($dir . $file),
                        'modified' => filemtime($dir . $file)
                    ];
                }
            }
        }
        
        return $images;
    }
    
    /**
     * Generate a unique filename for storage
     * 
     * @param int $feasibilityId Feasibility check ID
     * @param string $category Image category
     * @param string $extension File extension
     * @return string Unique filename
     * 
     * Requirements: 6.3
     */
    private function generateUniqueFilename(int $feasibilityId, string $category, string $extension): string {
        $timestamp = date('YmdHis');
        $random = bin2hex(random_bytes(4));
        return "{$category}_{$timestamp}_{$random}.{$extension}";
    }
    
    /**
     * Get human-readable upload error message
     * 
     * @param int $errorCode PHP upload error code
     * @return string Error message
     */
    private function getUploadErrorMessage(int $errorCode): string {
        switch ($errorCode) {
            case UPLOAD_ERR_INI_SIZE:
                return 'File exceeds the upload_max_filesize directive in php.ini';
            case UPLOAD_ERR_FORM_SIZE:
                return 'File exceeds the MAX_FILE_SIZE directive in the HTML form';
            case UPLOAD_ERR_PARTIAL:
                return 'File was only partially uploaded';
            case UPLOAD_ERR_NO_FILE:
                return 'No file was uploaded';
            case UPLOAD_ERR_NO_TMP_DIR:
                return 'Missing a temporary folder';
            case UPLOAD_ERR_CANT_WRITE:
                return 'Failed to write file to disk';
            case UPLOAD_ERR_EXTENSION:
                return 'A PHP extension stopped the file upload';
            default:
                return 'Unknown upload error';
        }
    }
    
    /**
     * Log action for audit trail
     * 
     * @param int $feasibilityId Feasibility check ID
     * @param string $action Action type
     * @param array $details Additional details
     */
    private function logAction(int $feasibilityId, string $action, array $details): void {
        try {
            $sql = "INSERT INTO user_audit_log (user_id, action, details, performed_by, ip_address) 
                    VALUES (?, ?, ?, ?, ?)";
            
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'CLI';
            $details['feasibility_id'] = $feasibilityId;
            $details['entity_type'] = 'feasibility_image';
            
            // Get user ID from session if available
            $userId = $_SESSION['user_id'] ?? 0;
            
            $stmt = $this->db->executeQuery($sql, [
                $userId,
                $action,
                json_encode($details),
                $userId,
                $ipAddress
            ], 'issis');
            $stmt->close();
        } catch (Exception $e) {
            error_log("Failed to log image upload action: " . $e->getMessage());
        }
    }
    
    /**
     * Get maximum allowed file size in bytes
     * 
     * @return int Max file size in bytes
     */
    public function getMaxFileSize(): int {
        return $this->maxFileSize;
    }
    
    /**
     * Get allowed file types
     * 
     * @return array Allowed file extensions
     */
    public function getAllowedTypes(): array {
        return $this->allowedTypes;
    }
    
    /**
     * Get allowed MIME types
     * 
     * @return array Allowed MIME types
     */
    public function getAllowedMimeTypes(): array {
        return $this->allowedMimeTypes;
    }
}
