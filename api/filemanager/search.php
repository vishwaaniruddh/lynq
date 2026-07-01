<?php
/**
 * File Manager API - Search Files
 * GET /api/filemanager/search.php
 * 
 * Searches for files matching a term in the current directory and subdirectories
 * 
 * Query Parameters:
 * - path: Base directory path relative to XAMPP root (optional, defaults to root)
 * - term: Search term to match against file names (required)
 * - limit: Maximum number of results (optional, defaults to 100)
 * 
 * Response: { success: bool, data: { searchTerm: string, basePath: string, results: [], totalFound: int, limitReached: bool } }
 * 
 * Requirements: 7.1, 6.1
 * - 7.1: Search for files matching the term in current directory and subdirectories
 * - 6.1: Verify user has ADV company type and system.manage permission
 */

require_once __DIR__ . '/../../config/autoload.php';
require_once __DIR__ . '/../ApiResponse.php';
require_once __DIR__ . '/../../middleware/ApiAuthMiddleware.php';
require_once __DIR__ . '/../../middleware/FileManagerMiddleware.php';
require_once __DIR__ . '/../../services/FileManagerService.php';

// Handle CORS
ApiResponse::setCorsHeaders();
ApiResponse::handlePreflight();

// Only allow GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    ApiResponse::methodNotAllowed(['GET']);
}

try {
    // Authentication and rate limiting
    $authMiddleware = new ApiAuthMiddleware();
    $authMiddleware->checkRateLimit();
    
    // File Manager access check (ADV + system.manage)
    $fileManagerMiddleware = new FileManagerMiddleware();
    $user = $fileManagerMiddleware->validateApiAccess();
    
    // Get search parameters
    $basePath = isset($_GET['path']) ? trim($_GET['path']) : '';
    $searchTerm = isset($_GET['term']) ? trim($_GET['term']) : '';
    $maxResults = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
    
    // Validate search term
    if (empty($searchTerm)) {
        ApiResponse::error(
            'INVALID_SEARCH_TERM',
            'Search term is required',
            400
        );
    }
    
    // Validate max results (between 1 and 500)
    if ($maxResults < 1) {
        $maxResults = 1;
    } elseif ($maxResults > 500) {
        $maxResults = 500;
    }
    
    // Initialize FileManagerService
    $fileManagerService = new FileManagerService();
    
    // Search for files
    $result = $fileManagerService->searchFiles($basePath, $searchTerm, $maxResults);
    
    if (!$result['success']) {
        ApiResponse::error(
            $result['code'] ?? 'SEARCH_FAILED',
            $result['error'] ?? 'Failed to search files',
            400
        );
    }
    
    // Log API access
    $authMiddleware->logApiAccess($user['id'], '/api/filemanager/search', 'GET', [
        'path' => $basePath,
        'term' => $searchTerm,
        'limit' => $maxResults
    ]);
    
    // Log file operation for audit trail (Requirement 6.4)
    $fileManagerService->logOperation(
        FileManagerService::ACTION_FILE_SEARCH,
        $basePath ?: '/',
        $user['id'],
        [
            'action' => 'search_files',
            'search_term' => $searchTerm,
            'results_count' => $result['data']['totalFound']
        ]
    );
    
    // Build response message
    $totalFound = $result['data']['totalFound'];
    $message = $totalFound === 0 
        ? 'No results found' 
        : "Found {$totalFound} result(s)";
    
    if ($result['data']['limitReached']) {
        $message .= " (limit reached)";
    }
    
    ApiResponse::success($result['data'], $message);
    
} catch (Exception $e) {
    error_log("File Manager Search API Error: " . $e->getMessage());
    ApiResponse::serverError('Failed to search files');
}
