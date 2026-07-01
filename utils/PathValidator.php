<?php
/**
 * Path Validator Utility
 * Security component for path validation and sanitization
 * Prevents directory traversal attacks and ensures paths stay within allowed root
 * 
 * Requirements: 6.2, 6.5
 * - 6.2: Validate path is within XAMPP_Root to prevent directory traversal attacks
 * - 6.5: Sanitize input to prevent path injection attacks
 */

class PathValidator {
    private string $allowedRoot;
    
    /**
     * Constructor
     * 
     * @param string $allowedRoot The root directory that all paths must be within
     */
    public function __construct(string $allowedRoot) {
        // Normalize the allowed root path
        $this->allowedRoot = $this->normalizePath($allowedRoot);
    }
    
    /**
     * Validate a path is safe and within the allowed root
     * 
     * @param string $path Path to validate
     * @return bool True if path is valid and safe
     */
    public function validate(string $path): bool {
        // Check for traversal attempts first
        if ($this->hasTraversalAttempt($path)) {
            return false;
        }
        
        // Sanitize and check if within root
        $sanitized = $this->sanitize($path);
        return $this->isWithinRoot($sanitized);
    }
    
    /**
     * Sanitize a path by removing dangerous characters and sequences
     * 
     * @param string $path Path to sanitize
     * @return string Sanitized path
     */
    public function sanitize(string $path): string {
        // Remove null bytes
        $path = str_replace("\0", '', $path);
        
        // Normalize directory separators to forward slashes
        $path = str_replace('\\', '/', $path);
        
        // Remove multiple consecutive slashes
        $path = preg_replace('#/+#', '/', $path);
        
        // Remove leading/trailing whitespace
        $path = trim($path);
        
        // Remove any URL encoding that might hide traversal
        $path = urldecode($path);
        
        // Re-normalize after URL decode
        $path = str_replace('\\', '/', $path);
        $path = preg_replace('#/+#', '/', $path);
        
        // Remove leading slash if not a Windows absolute path (e.g., C:/)
        // This handles cases like "/htdocs" which should be treated as relative "htdocs"
        if (strpos($path, '/') === 0 && !preg_match('/^[a-zA-Z]:/', $path)) {
            $path = ltrim($path, '/');
        }
        
        return $path;
    }
    
    /**
     * Check if a path is within the allowed root directory
     * 
     * @param string $path Path to check (can be relative or absolute)
     * @return bool True if path is within root
     */
    public function isWithinRoot(string $path): bool {
        $absolutePath = $this->getAbsolutePath($path);
        
        if ($absolutePath === false) {
            return false;
        }
        
        // Normalize both paths for comparison
        $normalizedPath = $this->normalizePath($absolutePath);
        $normalizedRoot = $this->allowedRoot;
        
        // Check if the path starts with the root
        return strpos($normalizedPath, $normalizedRoot) === 0;
    }
    
    /**
     * Detect directory traversal attempts in a path
     * 
     * @param string $path Path to check
     * @return bool True if traversal attempt detected
     */
    public function hasTraversalAttempt(string $path): bool {
        // Decode URL encoding first to catch encoded attacks
        $decodedPath = urldecode($path);
        
        // Check for various traversal patterns
        $traversalPatterns = [
            '..',           // Basic traversal
            '../',          // Unix-style traversal
            '..\\',         // Windows-style traversal
            '..%2f',        // URL encoded forward slash
            '..%5c',        // URL encoded backslash
            '%2e%2e',       // URL encoded dots
            '..../',        // Double dot variations
            '....\\',
            '...//',        // Triple dot variations
        ];
        
        $lowerPath = strtolower($decodedPath);
        
        foreach ($traversalPatterns as $pattern) {
            if (strpos($lowerPath, strtolower($pattern)) !== false) {
                return true;
            }
        }
        
        // Check for null byte injection
        if (strpos($path, "\0") !== false) {
            return true;
        }
        
        // Check for double URL encoding
        if (preg_match('/%25/', $path)) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Get the absolute path from a relative path
     * 
     * @param string $relativePath Relative path from the allowed root
     * @return string|false Absolute path or false if invalid
     */
    public function getAbsolutePath(string $relativePath): string|false {
        // If already absolute, use it directly
        if ($this->isAbsolutePath($relativePath)) {
            $absolutePath = $relativePath;
        } else {
            // Combine with root
            $absolutePath = $this->allowedRoot . '/' . ltrim($relativePath, '/');
        }
        
        // Normalize the path
        $absolutePath = $this->normalizePath($absolutePath);
        
        // Use realpath if the path exists
        if (file_exists($absolutePath)) {
            $realPath = realpath($absolutePath);
            if ($realPath !== false) {
                return $this->normalizePath($realPath);
            }
        }
        
        // For non-existent paths, resolve manually
        return $this->resolvePathManually($absolutePath);
    }
    
    /**
     * Get the relative path from an absolute path
     * 
     * @param string $absolutePath Absolute path
     * @return string|false Relative path or false if not within root
     */
    public function getRelativePath(string $absolutePath): string|false {
        $normalizedAbsolute = $this->normalizePath($absolutePath);
        $normalizedRoot = $this->allowedRoot;
        
        if (strpos($normalizedAbsolute, $normalizedRoot) !== 0) {
            return false;
        }
        
        $relativePath = substr($normalizedAbsolute, strlen($normalizedRoot));
        return ltrim($relativePath, '/');
    }
    
    /**
     * Get the allowed root directory
     * 
     * @return string The allowed root path
     */
    public function getAllowedRoot(): string {
        return $this->allowedRoot;
    }
    
    /**
     * Validate a filename (not a path)
     * 
     * @param string $filename Filename to validate
     * @return bool True if filename is valid
     */
    public function validateFilename(string $filename): bool {
        // Check for empty filename
        if (empty(trim($filename))) {
            return false;
        }
        
        // Check for path separators in filename
        if (strpos($filename, '/') !== false || strpos($filename, '\\') !== false) {
            return false;
        }
        
        // Check for traversal in filename
        if ($filename === '.' || $filename === '..') {
            return false;
        }
        
        // Check for null bytes
        if (strpos($filename, "\0") !== false) {
            return false;
        }
        
        // Check for invalid characters (Windows restrictions)
        $invalidChars = ['<', '>', ':', '"', '|', '?', '*'];
        foreach ($invalidChars as $char) {
            if (strpos($filename, $char) !== false) {
                return false;
            }
        }
        
        // Check filename length (max 255 characters)
        if (strlen($filename) > 255) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Sanitize a filename
     * 
     * @param string $filename Filename to sanitize
     * @return string Sanitized filename
     */
    public function sanitizeFilename(string $filename): string {
        // Remove path components
        $filename = basename($filename);
        
        // Remove null bytes
        $filename = str_replace("\0", '', $filename);
        
        // Replace invalid characters with underscores
        $invalidChars = ['<', '>', ':', '"', '|', '?', '*', '/', '\\'];
        $filename = str_replace($invalidChars, '_', $filename);
        
        // Remove leading/trailing dots and spaces
        $filename = trim($filename, '. ');
        
        // Limit length
        if (strlen($filename) > 255) {
            $extension = pathinfo($filename, PATHINFO_EXTENSION);
            $name = pathinfo($filename, PATHINFO_FILENAME);
            $maxNameLength = 255 - strlen($extension) - 1;
            $filename = substr($name, 0, $maxNameLength) . '.' . $extension;
        }
        
        return $filename;
    }
    
    /**
     * Normalize a path (convert to consistent format)
     * 
     * @param string $path Path to normalize
     * @return string Normalized path
     */
    private function normalizePath(string $path): string {
        // Convert backslashes to forward slashes
        $path = str_replace('\\', '/', $path);
        
        // Remove multiple consecutive slashes
        $path = preg_replace('#/+#', '/', $path);
        
        // Remove trailing slash (except for root)
        $path = rtrim($path, '/');
        
        // Handle Windows drive letters
        if (preg_match('/^([a-zA-Z]):/', $path, $matches)) {
            $path = strtoupper($matches[1]) . substr($path, 1);
        }
        
        return $path;
    }
    
    /**
     * Check if a path is absolute
     * 
     * @param string $path Path to check
     * @return bool True if absolute
     */
    private function isAbsolutePath(string $path): bool {
        // Windows absolute path (e.g., C:\)
        if (preg_match('/^[a-zA-Z]:/', $path)) {
            return true;
        }
        
        // Unix absolute path
        if (strpos($path, '/') === 0) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Resolve path manually without requiring file existence
     * Handles . and .. components
     * 
     * @param string $path Path to resolve
     * @return string|false Resolved path or false if invalid
     */
    private function resolvePathManually(string $path): string|false {
        $path = $this->normalizePath($path);
        $parts = explode('/', $path);
        $resolved = [];
        
        foreach ($parts as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            
            if ($part === '..') {
                // Traversal attempt - check if we'd go above root
                if (empty($resolved)) {
                    return false;
                }
                array_pop($resolved);
            } else {
                $resolved[] = $part;
            }
        }
        
        // Reconstruct path
        $resolvedPath = implode('/', $resolved);
        
        // Handle Windows drive letter
        if (preg_match('/^([A-Z]):/', $parts[0], $matches)) {
            $resolvedPath = $matches[1] . ':/' . $resolvedPath;
        } elseif (strpos($path, '/') === 0) {
            $resolvedPath = '/' . $resolvedPath;
        }
        
        return $resolvedPath;
    }
}
