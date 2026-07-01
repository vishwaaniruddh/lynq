<?php
/**
 * Notes API - Update Existing Note
 * 
 * Updates an existing note for the current user
 * 
 * Requirements: 4.2, 4.3, 6.3 - Update and auto-save notes
 */

require_once __DIR__ . '/../../config/autoload.php';
require_once __DIR__ . '/../ApiResponse.php';

header('Content-Type: application/json');

try {
    // Check authentication
    $sessionService = new SessionService();
    if (!$sessionService->isLoggedIn()) {
        ApiResponse::error('Authentication required', 401);
        exit;
    }
    
    $currentUser = $sessionService->getCurrentUser();
    $userId = $currentUser['id'];
    
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        ApiResponse::error('Invalid JSON input', 400);
        exit;
    }
    
    // Validate input
    $noteId = intval($input['id'] ?? 0);
    if (!$noteId) {
        ApiResponse::error('Note ID is required', 400);
        exit;
    }
    
    $title = trim($input['title'] ?? '');
    $content = trim($input['content'] ?? '');
    
    // At least title or content must be provided
    if (empty($title) && empty($content)) {
        ApiResponse::error('Title or content is required', 400);
        exit;
    }
    
    // Set default title if empty
    if (empty($title)) {
        $title = 'Untitled Note';
    }
    
    $db = DatabaseConfig::getInstance();
    
    // Check if note exists and belongs to user
    $checkSql = "SELECT id FROM notes WHERE id = ? AND user_id = ?";
    $checkStmt = $db->executeQuery($checkSql, [$noteId, $userId], 'ii');
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows === 0) {
        $checkStmt->close();
        ApiResponse::error('Note not found', 404);
        exit;
    }
    $checkStmt->close();
    
    // Update note
    $sql = "UPDATE notes SET title = ?, content = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND user_id = ?";
    $stmt = $db->executeQuery($sql, [$title, $content, $noteId, $userId], 'ssii');
    $stmt->close();
    
    // Get the updated note
    $sql = "SELECT id, title, content, created_at, updated_at 
            FROM notes 
            WHERE id = ?";
    
    $stmt = $db->executeQuery($sql, [$noteId], 'i');
    $result = $stmt->get_result();
    $note = $result->fetch_assoc();
    $stmt->close();
    
    // Format response
    $noteData = [
        'id' => (int)$note['id'],
        'title' => $note['title'],
        'content' => $note['content'],
        'created_at' => $note['created_at'],
        'updated_at' => $note['updated_at']
    ];
    
    ApiResponse::success($noteData, 'Note updated successfully');
    
} catch (Exception $e) {
    error_log("Note update error: " . $e->getMessage());
    ApiResponse::error('Failed to update note', 500);
}