<?php
/**
 * File Manager Service
 * Core service handling all file operations with security validation
 * 
 * Requirements: 1.1, 6.2
 * - 1.1: Display contents of XAMPP_Root directory
 * - 6.2: Validate path is within XAMPP_Root to prevent directory traversal attacks
 */

require_once __DIR__ . '/../config/autoload.php';
require_once __DIR__ . '/../utils/PathValidator.php';
require_once __DIR__ . '/InventoryAuditService.php';

class FileManagerService {
    private string $xamppRoot;
    private PathValidator $pathValidator;
    private InventoryAuditService $auditService;
    
    // File operation action types for audit logging
    const ACTION_FILE_CREATE = 'file_create';
    const ACTION_FILE_READ = 'file_read';
    const ACTION_FILE_WRITE = 'file_write';
    const ACTION_FILE_DELETE = 'file_delete';
    const ACTION_FILE_RENAME = 'file_rename';
    const ACTION_FILE_UPLOAD = 'file_upload';
    const ACTION_FILE_DOWNLOAD = 'file_download';
    const ACTION_FILE_SEARCH = 'file_search';
    const ACTION_DIR_CREATE = 'directory_create';
    const ACTION_DIR_DELETE = 'directory_delete';
    const ACTION_DIR_LIST = 'directory_list';
    
    // Entity type for audit logging
    const ENTITY_FILE = 'file';
    
    // Maximum file size for viewing (5MB)
    const MAX_VIEW_SIZE = 5 * 1024 * 1024;
    
    // Maximum upload size (50MB)
    const MAX_UPLOAD_SIZE = 50 * 1024 * 1024;
    
    // Editable file extensions
    private array $editableExtensions = [
        'php', 'js', 'css', 'html', 'htm', 'json', 'xml', 'sql', 
        'txt', 'md', 'yml', 'yaml', 'ini', 'conf', 'htaccess', 'env',
        'sh', 'bat', 'ps1', 'log', 'csv'
    ];
    
    // Syntax highlighting language mapping
    private array $languageMap = [
        'php' => 'php',
        'js' => 'javascript',
        'css' => 'css',
        'html' => 'html',
        'htm' => 'html',
        'json' => 'json',
        'xml' => 'xml',
        'sql' => 'sql',
        'md' => 'markdown',
        'yml' => 'yaml',
        'yaml' => 'yaml',
        'sh' => 'bash',
        'bat' => 'batch',
        'ps1' => 'powershell',
        'ini' => 'ini',
        'conf' => 'nginx',
        'txt' => 'plaintext',
        'log' => 'plaintext',
        'csv' => 'plaintext',
        'htaccess' => 'apache',
        'env' => 'plaintext'
    ];
    
    // MIME type mapping for downloads
    private array $mimeTypes = [
        'php' => 'application/x-php',
        'js' => 'application/javascript',
        'css' => 'text/css',
        'html' => 'text/html',
        'htm' => 'text/html',
        'json' => 'application/json',
        'xml' => 'application/xml',
        'sql' => 'application/sql',
        'txt' => 'text/plain',
        'md' => 'text/markdown',
        'yml' => 'text/yaml',
        'yaml' => 'text/yaml',
        'pdf' => 'application/pdf',
        'zip' => 'application/zip',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'svg' => 'image/svg+xml',
        'ico' => 'image/x-icon',
        'csv' => 'text/csv',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
    ];
    
    // Icon mapping for file types
    private array $iconMap = [
        'directory' => 'fa-folder',
        'php' => 'fa-file-code',
        'js' => 'fa-file-code',
        'css' => 'fa-file-code',
        'html' => 'fa-file-code',
        'htm' => 'fa-file-code',
        'json' => 'fa-file-code',
        'xml' => 'fa-file-code',
        'sql' => 'fa-database',
        'txt' => 'fa-file-alt',
        'md' => 'fa-file-alt',
        'pdf' => 'fa-file-pdf',
        'zip' => 'fa-file-archive',
        'rar' => 'fa-file-archive',
        '7z' => 'fa-file-archive',
        'png' => 'fa-file-image',
        'jpg' => 'fa-file-image',
        'jpeg' => 'fa-file-image',
        'gif' => 'fa-file-image',
        'svg' => 'fa-file-image',
        'xls' => 'fa-file-excel',
        'xlsx' => 'fa-file-excel',
        'csv' => 'fa-file-excel',
        'doc' => 'fa-file-word',
        'docx' => 'fa-file-word',
        'default' => 'fa-file'
    ];
    
    /**
     * Constructor
     * Initializes the service with XAMPP root configuration
     */
    public function __construct() {
        // Default XAMPP root for Windows
        $this->xamppRoot = $this->detectXamppRoot();
        $this->pathValidator = new PathValidator($this->xamppRoot);
        $this->auditService = new InventoryAuditService();
    }

    
    /**
     * Detect XAMPP root directory
     * 
     * @return string XAMPP root path
     */
    private function detectXamppRoot(): string {
        // Check common XAMPP locations
        $possibleRoots = [
            '/home/vol6_7/infinityfree.com/if0_40845939/htdocs',
            '/home/vol6_7/infinityfree.com/if0_40845939/htdocs',
            '/opt/lampp',
            '/Applications/XAMPP'
        ];
        
        foreach ($possibleRoots as $root) {
            if (is_dir($root)) {
                return $root;
            }
        }
        
        // Fallback: try to detect from current script location
        $docRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
        if (!empty($docRoot)) {
            // Go up from htdocs to xampp root
            $parentDir = dirname($docRoot);
            if (is_dir($parentDir)) {
                return $parentDir;
            }
        }
        
        // Default fallback
        return 'C:/xampp';
    }
    
    /**
     * Get the XAMPP root directory
     * 
     * @return string XAMPP root path
     */
    public function getXamppRoot(): string {
        return $this->xamppRoot;
    }
    
    /**
     * Get the PathValidator instance
     * 
     * @return PathValidator
     */
    public function getPathValidator(): PathValidator {
        return $this->pathValidator;
    }
    
    // ==================== Directory Operations ====================
    
    /**
     * List contents of a directory
     * 
     * @param string $path Relative path from XAMPP root
     * @return array Result with directory contents
     */
    public function listDirectory(string $path): array {
        // Validate path
        if (!$this->pathValidator->validate($path)) {
            return [
                'success' => false,
                'error' => 'Invalid or unsafe path',
                'code' => 'PATH_INVALID'
            ];
        }
        
        $absolutePath = $this->pathValidator->getAbsolutePath($path);
        
        if (!is_dir($absolutePath)) {
            return [
                'success' => false,
                'error' => 'Directory not found',
                'code' => 'PATH_NOT_FOUND'
            ];
        }
        
        $items = [];
        $entries = scandir($absolutePath);
        
        foreach ($entries as $entry) {
            // Skip . and ..
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            
            $itemPath = $absolutePath . '/' . $entry;
            $items[] = $this->buildDirectoryItem($entry, $itemPath, $path);
        }
        
        // Sort: directories first, then files, both alphabetically
        usort($items, function($a, $b) {
            if ($a['type'] === $b['type']) {
                return strcasecmp($a['name'], $b['name']);
            }
            return $a['type'] === 'directory' ? -1 : 1;
        });
        
        return [
            'success' => true,
            'data' => [
                'path' => $path,
                'items' => $items,
                'breadcrumbs' => $this->getBreadcrumbs($path)
            ]
        ];
    }
    
    /**
     * Create a new directory
     * 
     * Requirements: 3.3, 3.4, 3.5, 6.4
     * - 3.3: Display form requesting folder name
     * - 3.4: Create folder in current directory
     * - 3.5: Display error if folder with same name exists
     * - 6.4: Log operation to audit trail
     * 
     * @param string $path Parent directory path
     * @param string $name New directory name
     * @param int|null $userId User ID for audit logging (optional)
     * @return array Result
     */
    public function createDirectory(string $path, string $name, ?int $userId = null): array {
        // Validate parent path
        if (!$this->pathValidator->validate($path)) {
            return [
                'success' => false,
                'error' => 'Invalid parent path',
                'code' => 'PATH_INVALID'
            ];
        }
        
        // Validate directory name
        if (!$this->pathValidator->validateFilename($name)) {
            return [
                'success' => false,
                'error' => 'Invalid directory name',
                'code' => 'INVALID_NAME'
            ];
        }
        
        $parentPath = $this->pathValidator->getAbsolutePath($path);
        
        // Check if parent directory exists
        if (!is_dir($parentPath)) {
            return [
                'success' => false,
                'error' => 'Parent directory not found',
                'code' => 'PATH_NOT_FOUND'
            ];
        }
        
        $newDirPath = $parentPath . '/' . $name;
        $relativePath = $path . '/' . $name;
        
        // Check if already exists
        if (file_exists($newDirPath)) {
            return [
                'success' => false,
                'error' => 'A file or folder with this name already exists',
                'code' => 'FILE_EXISTS'
            ];
        }
        
        // Create directory
        if (!mkdir($newDirPath, 0755, true)) {
            return [
                'success' => false,
                'error' => 'Failed to create directory',
                'code' => 'WRITE_FAILED'
            ];
        }
        
        // Log operation to audit trail if userId provided
        if ($userId !== null) {
            $this->logOperation(
                self::ACTION_DIR_CREATE,
                $relativePath,
                $userId,
                [
                    'name' => $name,
                    'parent_path' => $path
                ]
            );
        }
        
        return [
            'success' => true,
            'message' => 'Directory created successfully',
            'data' => [
                'path' => $relativePath,
                'name' => $name
            ]
        ];
    }
    
    // ==================== File Operations ====================
    
    /**
     * Read file content
     * 
     * @param string $path File path relative to XAMPP root
     * @return array Result with file content
     */
    public function readFile(string $path): array {
        // Validate path
        if (!$this->pathValidator->validate($path)) {
            return [
                'success' => false,
                'error' => 'Invalid or unsafe path',
                'code' => 'PATH_INVALID'
            ];
        }
        
        $absolutePath = $this->pathValidator->getAbsolutePath($path);
        
        if (!file_exists($absolutePath)) {
            return [
                'success' => false,
                'error' => 'File not found',
                'code' => 'PATH_NOT_FOUND'
            ];
        }
        
        if (!is_file($absolutePath)) {
            return [
                'success' => false,
                'error' => 'Path is not a file',
                'code' => 'NOT_A_FILE'
            ];
        }
        
        $fileInfo = $this->getFileInfo($path);
        $size = filesize($absolutePath);
        $isLargeFile = $size > self::MAX_VIEW_SIZE;
        $isTruncated = false;
        
        // Read content
        if ($isLargeFile) {
            // Read only first 5MB for large files
            $content = file_get_contents($absolutePath, false, null, 0, self::MAX_VIEW_SIZE);
            $isTruncated = true;
        } else {
            $content = file_get_contents($absolutePath);
        }
        
        if ($content === false) {
            return [
                'success' => false,
                'error' => 'Failed to read file',
                'code' => 'READ_FAILED'
            ];
        }
        
        return [
            'success' => true,
            'data' => [
                'path' => $path,
                'name' => basename($path),
                'content' => $content,
                'size' => $size,
                'sizeFormatted' => $this->formatFileSize($size),
                'modified' => filemtime($absolutePath),
                'modifiedFormatted' => date('Y-m-d H:i:s', filemtime($absolutePath)),
                'language' => $this->getLanguageForSyntaxHighlight(basename($path)),
                'isTruncated' => $isTruncated,
                'isLargeFile' => $isLargeFile
            ]
        ];
    }
    
    /**
     * Write content to a file
     * 
     * Requirements: 4.2, 4.3, 4.4, 6.4
     * - 4.2: Write updated content to file
     * - 4.3: Create backup of original file before overwriting
     * - 4.4: Display success confirmation message
     * - 6.4: Log operation to audit trail
     * 
     * @param string $path File path relative to XAMPP root
     * @param string $content Content to write
     * @param int|null $userId User ID for audit logging (optional)
     * @return array Result
     */
    public function writeFile(string $path, string $content, ?int $userId = null): array {
        // Validate path
        if (!$this->pathValidator->validate($path)) {
            return [
                'success' => false,
                'error' => 'Invalid or unsafe path',
                'code' => 'PATH_INVALID'
            ];
        }
        
        $absolutePath = $this->pathValidator->getAbsolutePath($path);
        
        if (!file_exists($absolutePath)) {
            return [
                'success' => false,
                'error' => 'File not found',
                'code' => 'PATH_NOT_FOUND'
            ];
        }
        
        if (!is_file($absolutePath)) {
            return [
                'success' => false,
                'error' => 'Path is not a file',
                'code' => 'NOT_A_FILE'
            ];
        }
        
        // Get original file size for audit logging
        $originalSize = filesize($absolutePath);
        
        // Create backup before overwriting (Requirement 4.3)
        $backupPath = $absolutePath . '.bak.' . date('YmdHis');
        $backupCreated = copy($absolutePath, $backupPath);
        if (!$backupCreated) {
            // Log warning but continue
            error_log("FileManagerService: Failed to create backup for $path");
        }
        
        // Write new content (Requirement 4.2)
        if (file_put_contents($absolutePath, $content) === false) {
            return [
                'success' => false,
                'error' => 'Failed to write file',
                'code' => 'WRITE_FAILED'
            ];
        }
        
        // Log operation to audit trail if userId provided (Requirement 6.4)
        if ($userId !== null) {
            $this->logOperation(
                self::ACTION_FILE_WRITE,
                $path,
                $userId,
                [
                    'original_size' => $originalSize,
                    'new_size' => strlen($content),
                    'backup_created' => $backupCreated,
                    'backup_path' => $backupCreated ? $this->pathValidator->getRelativePath($backupPath) : null
                ]
            );
        }
        
        return [
            'success' => true,
            'message' => 'File saved successfully',
            'data' => [
                'path' => $path,
                'backupPath' => $backupCreated ? $this->pathValidator->getRelativePath($backupPath) : null,
                'backupCreated' => $backupCreated
            ]
        ];
    }
    
    /**
     * Create a new file
     * 
     * Requirements: 3.1, 3.2, 3.5, 6.4
     * - 3.1: Display form requesting file name and initial content
     * - 3.2: Create file in current directory
     * - 3.5: Display error if file with same name exists
     * - 6.4: Log operation to audit trail
     * 
     * @param string $path Parent directory path
     * @param string $name New file name
     * @param string $content Initial content
     * @param int|null $userId User ID for audit logging (optional)
     * @return array Result
     */
    public function createFile(string $path, string $name, string $content = '', ?int $userId = null): array {
        // Validate parent path
        if (!$this->pathValidator->validate($path)) {
            return [
                'success' => false,
                'error' => 'Invalid parent path',
                'code' => 'PATH_INVALID'
            ];
        }
        
        // Validate file name
        if (!$this->pathValidator->validateFilename($name)) {
            return [
                'success' => false,
                'error' => 'Invalid file name',
                'code' => 'INVALID_NAME'
            ];
        }
        
        $parentPath = $this->pathValidator->getAbsolutePath($path);
        
        // Check if parent directory exists
        if (!is_dir($parentPath)) {
            return [
                'success' => false,
                'error' => 'Parent directory not found',
                'code' => 'PATH_NOT_FOUND'
            ];
        }
        
        $newFilePath = $parentPath . '/' . $name;
        $relativePath = $path . '/' . $name;
        
        // Check if already exists
        if (file_exists($newFilePath)) {
            return [
                'success' => false,
                'error' => 'A file or folder with this name already exists',
                'code' => 'FILE_EXISTS'
            ];
        }
        
        // Create file
        if (file_put_contents($newFilePath, $content) === false) {
            return [
                'success' => false,
                'error' => 'Failed to create file',
                'code' => 'WRITE_FAILED'
            ];
        }
        
        // Log operation to audit trail if userId provided
        if ($userId !== null) {
            $this->logOperation(
                self::ACTION_FILE_CREATE,
                $relativePath,
                $userId,
                [
                    'name' => $name,
                    'parent_path' => $path,
                    'content_length' => strlen($content)
                ]
            );
        }
        
        return [
            'success' => true,
            'message' => 'File created successfully',
            'data' => [
                'path' => $relativePath,
                'name' => $name
            ]
        ];
    }
    
    /**
     * Delete a file
     * 
     * Requirements: 5.2, 6.4
     * - 5.2: Remove file from file system when user confirms deletion
     * - 6.4: Log operation to audit trail with user ID, action, and file path
     * 
     * @param string $path File path relative to XAMPP root
     * @param int|null $userId User ID for audit logging (optional)
     * @return array Result
     */
    public function deleteFile(string $path, ?int $userId = null): array {
        // Validate path
        if (!$this->pathValidator->validate($path)) {
            return [
                'success' => false,
                'error' => 'Invalid or unsafe path',
                'code' => 'PATH_INVALID'
            ];
        }
        
        $absolutePath = $this->pathValidator->getAbsolutePath($path);
        
        if (!file_exists($absolutePath)) {
            return [
                'success' => false,
                'error' => 'File not found',
                'code' => 'PATH_NOT_FOUND'
            ];
        }
        
        if (!is_file($absolutePath)) {
            return [
                'success' => false,
                'error' => 'Path is not a file',
                'code' => 'NOT_A_FILE'
            ];
        }
        
        // Get file info before deletion for audit logging
        $fileName = basename($absolutePath);
        $fileSize = filesize($absolutePath);
        
        if (!unlink($absolutePath)) {
            return [
                'success' => false,
                'error' => 'Failed to delete file',
                'code' => 'DELETE_FAILED'
            ];
        }
        
        // Log operation to audit trail if userId provided (Requirement 6.4)
        if ($userId !== null) {
            $this->logOperation(
                self::ACTION_FILE_DELETE,
                $path,
                $userId,
                [
                    'name' => $fileName,
                    'size' => $fileSize
                ]
            );
        }
        
        return [
            'success' => true,
            'message' => 'File deleted successfully',
            'data' => ['path' => $path]
        ];
    }
    
    /**
     * Delete a directory recursively
     * 
     * Requirements: 5.4, 6.4
     * - 5.4: Recursively remove folder and all its contents when user confirms deletion
     * - 6.4: Log operation to audit trail with user ID, action, and file path
     * 
     * @param string $path Directory path relative to XAMPP root
     * @param int|null $userId User ID for audit logging (optional)
     * @return array Result
     */
    public function deleteDirectory(string $path, ?int $userId = null): array {
        // Validate path
        if (!$this->pathValidator->validate($path)) {
            return [
                'success' => false,
                'error' => 'Invalid or unsafe path',
                'code' => 'PATH_INVALID'
            ];
        }
        
        $absolutePath = $this->pathValidator->getAbsolutePath($path);
        
        if (!file_exists($absolutePath)) {
            return [
                'success' => false,
                'error' => 'Directory not found',
                'code' => 'PATH_NOT_FOUND'
            ];
        }
        
        if (!is_dir($absolutePath)) {
            return [
                'success' => false,
                'error' => 'Path is not a directory',
                'code' => 'NOT_A_DIRECTORY'
            ];
        }
        
        // Prevent deleting root
        if ($absolutePath === $this->xamppRoot) {
            return [
                'success' => false,
                'error' => 'Cannot delete root directory',
                'code' => 'ACCESS_DENIED'
            ];
        }
        
        // Get directory info before deletion for audit logging
        $dirName = basename($absolutePath);
        $itemCount = $this->countDirectoryItems($absolutePath);
        
        if (!$this->deleteDirectoryRecursive($absolutePath)) {
            return [
                'success' => false,
                'error' => 'Failed to delete directory',
                'code' => 'DELETE_FAILED'
            ];
        }
        
        // Log operation to audit trail if userId provided (Requirement 6.4)
        if ($userId !== null) {
            $this->logOperation(
                self::ACTION_DIR_DELETE,
                $path,
                $userId,
                [
                    'name' => $dirName,
                    'items_deleted' => $itemCount
                ]
            );
        }
        
        return [
            'success' => true,
            'message' => 'Directory deleted successfully',
            'data' => ['path' => $path]
        ];
    }
    
    /**
     * Rename a file or directory
     * 
     * Requirements: 10.2, 10.3, 10.4
     * - 10.2: Rename file or folder when user submits valid new name
     * - 10.3: Display error if file or folder with new name already exists
     * - 10.4: Log rename action to audit trail
     * 
     * @param string $path Current path relative to XAMPP root
     * @param string $newName New name
     * @param int|null $userId User ID for audit logging (optional)
     * @return array Result
     */
    public function renameItem(string $path, string $newName, ?int $userId = null): array {
        // Validate path
        if (!$this->pathValidator->validate($path)) {
            return [
                'success' => false,
                'error' => 'Invalid or unsafe path',
                'code' => 'PATH_INVALID'
            ];
        }
        
        // Validate new name
        if (!$this->pathValidator->validateFilename($newName)) {
            return [
                'success' => false,
                'error' => 'Invalid new name',
                'code' => 'INVALID_NAME'
            ];
        }
        
        $absolutePath = $this->pathValidator->getAbsolutePath($path);
        
        if (!file_exists($absolutePath)) {
            return [
                'success' => false,
                'error' => 'File or directory not found',
                'code' => 'PATH_NOT_FOUND'
            ];
        }
        
        // Get item info before rename for audit logging
        $oldName = basename($absolutePath);
        $isDirectory = is_dir($absolutePath);
        
        $parentDir = dirname($absolutePath);
        $newAbsolutePath = $parentDir . '/' . $newName;
        
        // Check if new name already exists (Requirement 10.3)
        if (file_exists($newAbsolutePath)) {
            return [
                'success' => false,
                'error' => 'A file or folder with this name already exists',
                'code' => 'FILE_EXISTS'
            ];
        }
        
        // Perform rename operation (Requirement 10.2)
        if (!rename($absolutePath, $newAbsolutePath)) {
            return [
                'success' => false,
                'error' => 'Failed to rename',
                'code' => 'RENAME_FAILED'
            ];
        }
        
        $parentRelativePath = $this->pathValidator->getRelativePath($parentDir);
        $newRelativePath = $parentRelativePath . '/' . $newName;
        
        // Log operation to audit trail if userId provided (Requirement 10.4)
        if ($userId !== null) {
            $this->logOperation(
                self::ACTION_FILE_RENAME,
                $path,
                $userId,
                [
                    'old_name' => $oldName,
                    'new_name' => $newName,
                    'new_path' => $newRelativePath,
                    'type' => $isDirectory ? 'directory' : 'file'
                ]
            );
        }
        
        return [
            'success' => true,
            'message' => 'Renamed successfully',
            'data' => [
                'oldPath' => $path,
                'newPath' => $newRelativePath,
                'oldName' => $oldName,
                'newName' => $newName
            ]
        ];
    }

    
    // ==================== Upload/Download Operations ====================
    
    /**
     * Upload a file
     * 
     * Requirements: 9.2, 9.3, 9.4, 9.5
     * - 9.2: Save file to current directory when user confirms upload
     * - 9.3: Reject upload if file exceeds maximum allowed size (50MB)
     * - 9.4: Prompt user to confirm overwrite or rename if file with same name exists
     * - 9.5: Log upload action to audit trail
     * 
     * @param string $targetPath Target directory path
     * @param array $uploadedFile $_FILES array element
     * @param int|null $userId User ID for audit logging (optional)
     * @param bool $overwrite Whether to overwrite existing file (default false)
     * @return array Result
     */
    public function uploadFile(string $targetPath, array $uploadedFile, ?int $userId = null, bool $overwrite = false): array {
        // Validate target path
        if (!$this->pathValidator->validate($targetPath)) {
            return [
                'success' => false,
                'error' => 'Invalid target path',
                'code' => 'PATH_INVALID'
            ];
        }
        
        $absoluteTargetPath = $this->pathValidator->getAbsolutePath($targetPath);
        
        // Check if target directory exists
        if (!is_dir($absoluteTargetPath)) {
            return [
                'success' => false,
                'error' => 'Target directory not found',
                'code' => 'PATH_NOT_FOUND'
            ];
        }
        
        // Check for upload errors
        if ($uploadedFile['error'] !== UPLOAD_ERR_OK) {
            return [
                'success' => false,
                'error' => $this->getUploadErrorMessage($uploadedFile['error']),
                'code' => 'UPLOAD_FAILED'
            ];
        }
        
        // Check file size (Requirement 9.3)
        if ($uploadedFile['size'] > self::MAX_UPLOAD_SIZE) {
            return [
                'success' => false,
                'error' => 'File exceeds maximum allowed size (50MB)',
                'code' => 'FILE_TOO_LARGE'
            ];
        }
        
        // Sanitize filename
        $filename = $this->pathValidator->sanitizeFilename($uploadedFile['name']);
        
        if (empty($filename)) {
            return [
                'success' => false,
                'error' => 'Invalid filename',
                'code' => 'INVALID_NAME'
            ];
        }
        
        $destinationPath = $absoluteTargetPath . '/' . $filename;
        $relativePath = $targetPath . '/' . $filename;
        
        // Check if file already exists (Requirement 9.4)
        if (file_exists($destinationPath)) {
            if (!$overwrite) {
                return [
                    'success' => false,
                    'error' => 'A file with this name already exists',
                    'code' => 'FILE_EXISTS',
                    'data' => ['filename' => $filename, 'requiresConfirmation' => true]
                ];
            }
            // If overwrite is true, create backup before overwriting
            $backupPath = $destinationPath . '.bak.' . date('YmdHis');
            copy($destinationPath, $backupPath);
        }
        
        // Move uploaded file (Requirement 9.2)
        if (!move_uploaded_file($uploadedFile['tmp_name'], $destinationPath)) {
            return [
                'success' => false,
                'error' => 'Failed to save uploaded file',
                'code' => 'UPLOAD_FAILED'
            ];
        }
        
        // Log operation to audit trail if userId provided (Requirement 9.5)
        if ($userId !== null) {
            $this->logOperation(
                self::ACTION_FILE_UPLOAD,
                $relativePath,
                $userId,
                [
                    'filename' => $filename,
                    'size' => $uploadedFile['size'],
                    'original_name' => $uploadedFile['name'],
                    'target_path' => $targetPath,
                    'overwritten' => $overwrite && file_exists($destinationPath)
                ]
            );
        }
        
        return [
            'success' => true,
            'message' => 'File uploaded successfully',
            'data' => [
                'path' => $targetPath . '/' . $filename,
                'name' => $filename,
                'size' => $uploadedFile['size'],
                'sizeFormatted' => $this->formatFileSize($uploadedFile['size'])
            ]
        ];
    }
    
    /**
     * Download a file (streams to browser)
     * 
     * Requirements: 8.1, 8.2, 8.3
     * - 8.1: Initiate file download to user's browser
     * - 8.2: Set appropriate content-type headers based on file extension
     * - 8.3: Log download action to audit trail
     * 
     * @param string $path File path relative to XAMPP root
     * @param int|null $userId User ID for audit logging (optional)
     * @return array Result array with success status (for validation), or streams file on success
     */
    public function downloadFile(string $path, ?int $userId = null): array {
        // Validate path
        if (!$this->pathValidator->validate($path)) {
            return [
                'success' => false,
                'error' => 'Invalid or unsafe path',
                'code' => 'PATH_INVALID'
            ];
        }
        
        $absolutePath = $this->pathValidator->getAbsolutePath($path);
        
        if (!file_exists($absolutePath)) {
            return [
                'success' => false,
                'error' => 'File not found',
                'code' => 'PATH_NOT_FOUND'
            ];
        }
        
        if (!is_file($absolutePath)) {
            return [
                'success' => false,
                'error' => 'Path is not a file',
                'code' => 'NOT_A_FILE'
            ];
        }
        
        $filename = basename($absolutePath);
        $mimeType = $this->getMimeType($filename);
        $fileSize = filesize($absolutePath);
        
        // Log operation to audit trail if userId provided (Requirement 8.3)
        if ($userId !== null) {
            $this->logOperation(
                self::ACTION_FILE_DOWNLOAD,
                $path,
                $userId,
                [
                    'filename' => $filename,
                    'size' => $fileSize,
                    'mime_type' => $mimeType
                ]
            );
        }
        
        // Set headers for download (Requirement 8.2)
        header('Content-Type: ' . $mimeType);
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . $fileSize);
        header('Cache-Control: no-cache, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        // Clear output buffer to prevent any previous output from corrupting the file
        if (ob_get_level()) {
            ob_end_clean();
        }
        
        // Stream file content to browser (Requirement 8.1)
        readfile($absolutePath);
        exit;
    }
    
    // ==================== Search Operations ====================
    
    /**
     * Search for files matching a term
     * 
     * @param string $basePath Base directory to search from
     * @param string $searchTerm Search term
     * @param int $maxResults Maximum results to return
     * @return array Search results
     */
    public function searchFiles(string $basePath, string $searchTerm, int $maxResults = 100): array {
        // Validate base path
        if (!$this->pathValidator->validate($basePath)) {
            return [
                'success' => false,
                'error' => 'Invalid search path',
                'code' => 'PATH_INVALID'
            ];
        }
        
        $absoluteBasePath = $this->pathValidator->getAbsolutePath($basePath);
        
        if (!is_dir($absoluteBasePath)) {
            return [
                'success' => false,
                'error' => 'Directory not found',
                'code' => 'PATH_NOT_FOUND'
            ];
        }
        
        $results = [];
        $this->searchRecursive($absoluteBasePath, $searchTerm, $results, $maxResults);
        
        return [
            'success' => true,
            'data' => [
                'searchTerm' => $searchTerm,
                'basePath' => $basePath,
                'results' => $results,
                'totalFound' => count($results),
                'limitReached' => count($results) >= $maxResults
            ]
        ];
    }
    
    // ==================== Utility Methods ====================
    
    /**
     * Get file information
     * 
     * @param string $path File path relative to XAMPP root
     * @return array File information
     */
    public function getFileInfo(string $path): array {
        if (!$this->pathValidator->validate($path)) {
            return [];
        }
        
        $absolutePath = $this->pathValidator->getAbsolutePath($path);
        
        if (!file_exists($absolutePath)) {
            return [];
        }
        
        $isDir = is_dir($absolutePath);
        $name = basename($absolutePath);
        $extension = $isDir ? '' : strtolower(pathinfo($name, PATHINFO_EXTENSION));
        
        return [
            'name' => $name,
            'path' => $path,
            'type' => $isDir ? 'directory' : 'file',
            'size' => $isDir ? 0 : filesize($absolutePath),
            'sizeFormatted' => $isDir ? '-' : $this->formatFileSize(filesize($absolutePath)),
            'modified' => filemtime($absolutePath),
            'modifiedFormatted' => date('Y-m-d H:i:s', filemtime($absolutePath)),
            'extension' => $extension,
            'icon' => $this->getIconForType($isDir ? 'directory' : $extension),
            'isEditable' => !$isDir && in_array($extension, $this->editableExtensions)
        ];
    }
    
    /**
     * Get breadcrumbs for a path
     * 
     * @param string $path Path relative to XAMPP root
     * @return array Breadcrumb items
     */
    public function getBreadcrumbs(string $path): array {
        $breadcrumbs = [];
        
        // Add root
        $breadcrumbs[] = [
            'label' => 'XAMPP',
            'path' => '',
            'isLast' => empty($path)
        ];
        
        if (empty($path)) {
            return $breadcrumbs;
        }
        
        // Split path into segments
        $segments = array_filter(explode('/', $path));
        $currentPath = '';
        $totalSegments = count($segments);
        $index = 0;
        
        foreach ($segments as $segment) {
            $index++;
            $currentPath .= ($currentPath ? '/' : '') . $segment;
            $breadcrumbs[] = [
                'label' => $segment,
                'path' => $currentPath,
                'isLast' => $index === $totalSegments
            ];
        }
        
        return $breadcrumbs;
    }
    
    /**
     * Get MIME type for a filename
     * 
     * @param string $filename Filename
     * @return string MIME type
     */
    public function getMimeType(string $filename): string {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        return $this->mimeTypes[$extension] ?? 'application/octet-stream';
    }
    
    /**
     * Get syntax highlighting language for a filename
     * 
     * @param string $filename Filename
     * @return string Language identifier
     */
    public function getLanguageForSyntaxHighlight(string $filename): string {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        return $this->languageMap[$extension] ?? 'plaintext';
    }
    
    // ==================== Private Helper Methods ====================
    
    /**
     * Build a DirectoryItem array from file info
     * 
     * @param string $name Item name
     * @param string $absolutePath Absolute path
     * @param string $parentRelativePath Parent relative path
     * @return array DirectoryItem
     */
    private function buildDirectoryItem(string $name, string $absolutePath, string $parentRelativePath): array {
        $isDir = is_dir($absolutePath);
        $extension = $isDir ? '' : strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $size = $isDir ? 0 : filesize($absolutePath);
        $modified = filemtime($absolutePath);
        
        // Build relative path - avoid leading slash when parent is empty (root)
        $relativePath = empty($parentRelativePath) ? $name : $parentRelativePath . '/' . $name;
        
        return [
            'name' => $name,
            'path' => $relativePath,
            'type' => $isDir ? 'directory' : 'file',
            'size' => $size,
            'sizeFormatted' => $isDir ? '-' : $this->formatFileSize($size),
            'modified' => $modified,
            'modifiedFormatted' => date('Y-m-d H:i:s', $modified),
            'extension' => $extension,
            'icon' => $this->getIconForType($isDir ? 'directory' : $extension),
            'isEditable' => !$isDir && in_array($extension, $this->editableExtensions)
        ];
    }
    
    /**
     * Get icon class for file type
     * 
     * @param string $type File type or extension
     * @return string FontAwesome icon class
     */
    private function getIconForType(string $type): string {
        return $this->iconMap[$type] ?? $this->iconMap['default'];
    }
    
    /**
     * Format file size for display
     * 
     * @param int $bytes Size in bytes
     * @return string Formatted size
     */
    private function formatFileSize(int $bytes): string {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $unitIndex = 0;
        
        while ($bytes >= 1024 && $unitIndex < count($units) - 1) {
            $bytes /= 1024;
            $unitIndex++;
        }
        
        return round($bytes, 2) . ' ' . $units[$unitIndex];
    }
    
    /**
     * Delete directory recursively
     * 
     * @param string $dir Directory path
     * @return bool Success
     */
    private function deleteDirectoryRecursive(string $dir): bool {
        if (!is_dir($dir)) {
            return false;
        }
        
        $items = scandir($dir);
        
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            
            $path = $dir . '/' . $item;
            
            if (is_dir($path)) {
                if (!$this->deleteDirectoryRecursive($path)) {
                    return false;
                }
            } else {
                if (!unlink($path)) {
                    return false;
                }
            }
        }
        
        return rmdir($dir);
    }
    
    /**
     * Count items in a directory recursively
     * 
     * @param string $dir Directory path
     * @return int Total count of files and directories
     */
    private function countDirectoryItems(string $dir): int {
        if (!is_dir($dir)) {
            return 0;
        }
        
        $count = 0;
        $items = @scandir($dir);
        
        if ($items === false) {
            return 0;
        }
        
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            
            $count++;
            $path = $dir . '/' . $item;
            
            if (is_dir($path)) {
                $count += $this->countDirectoryItems($path);
            }
        }
        
        return $count;
    }
    
    /**
     * Search recursively for files
     * 
     * @param string $dir Directory to search
     * @param string $searchTerm Search term
     * @param array &$results Results array (by reference)
     * @param int $maxResults Maximum results
     */
    private function searchRecursive(string $dir, string $searchTerm, array &$results, int $maxResults): void {
        if (count($results) >= $maxResults) {
            return;
        }
        
        $items = @scandir($dir);
        if ($items === false) {
            return;
        }
        
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            
            if (count($results) >= $maxResults) {
                return;
            }
            
            $path = $dir . '/' . $item;
            
            // Check if name matches search term (case-insensitive)
            if (stripos($item, $searchTerm) !== false) {
                $relativePath = $this->pathValidator->getRelativePath($path);
                $results[] = [
                    'name' => $item,
                    'path' => $relativePath,
                    'type' => is_dir($path) ? 'directory' : 'file',
                    'directory' => $this->pathValidator->getRelativePath($dir),
                    'modified' => filemtime($path)
                ];
            }
            
            // Recurse into directories
            if (is_dir($path)) {
                $this->searchRecursive($path, $searchTerm, $results, $maxResults);
            }
        }
    }
    
    /**
     * Get upload error message
     * 
     * @param int $errorCode PHP upload error code
     * @return string Error message
     */
    private function getUploadErrorMessage(int $errorCode): string {
        $messages = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds server upload limit',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds form upload limit',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'Upload stopped by extension'
        ];
        
        return $messages[$errorCode] ?? 'Unknown upload error';
    }
    
    /**
     * Log a file operation to audit trail
     * 
     * @param string $action Action type
     * @param string $path File path
     * @param int $userId User ID
     * @param array $details Additional details
     * @return array Audit log result
     */
    public function logOperation(string $action, string $path, int $userId, array $details = []): array {
        return $this->auditService->logAction(
            $action,
            self::ENTITY_FILE,
            0, // Entity ID not applicable for files
            $userId,
            array_merge([
                'notes' => "File operation: $action on $path"
            ], $details, [
                'new_values' => array_merge(['path' => $path], $details)
            ])
        );
    }
}
