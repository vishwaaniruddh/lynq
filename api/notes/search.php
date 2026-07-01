<?php
/**
 * Notes API - Search Notes
 * GET /api/notes/search.php?q={term}
 * 
 * Searches notes by title or content (case-insensitive)
 * Returns filtered notes matching the search term
 * 
 * Query Parameters:
 * - q: Search term (required)
 * 
 * Response: { success: bool, data: array of matching notes }
 * 
 * **Validates: Requirements 9.1**
 */

require_once __DIR__ . '/../../config/autoload.php';
require_once __DIR__ . '/../ApiResponse.php';
require_once __DIR__ . '/../../middleware/ApiAuthMiddleware.php';
require_once __DIR__ . '/../../services/NoteService.php';

// Handle CORS
ApiResponse::setCorsHeaders();
ApiResponse::handlePreflight();

// Only allow GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    ApiResponse::methodNotAllowed(['GET']);
}

try {
    $authMiddleware = new ApiAuthMiddleware();
    
    // Check rate limiting
    $authMiddleware->checkRateLimit();
    
    // Require authentication
    $currentUser = $authMiddleware->requireAuth();
    
    // Get search term (empty string returns all notes)
    $searchTerm = isset($_GET['q']) ? trim($_GET['q']) : '';
    
    $noteService = new NoteService();
    
    // Search notes (Requirement 9.1 - filter by title or content)
    $result = $noteService->searchNotes($currentUser['id'], $searchTerm);
    
    if (!$result['success']) {
        ApiResponse::serverError($result['message']);
    }
    
    // Log API access
    $authMiddleware->logApiAccess($currentUser['id'], '/api/notes/search', 'GET', ['q' => $searchTerm]);
    
    ApiResponse::success($result['data'], 'Search completed successfully');
    
} catch (Exception $e) {
    error_log("Notes API Error: " . $e->getMessage());
    ApiResponse::serverError('Failed to search notes');
}
