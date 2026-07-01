<?php
/**
 * Notes API - Delete Note
 * 
 * Deletes a note for the current user
 * 
 * Requirements: 7.2, 7.3 - Delete notes with confirmation
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
    
    $db = DatabaseConfig::getInstance();
    
    // Check if note exists and belongs to user
    $checkSql = "SELECT id, title FROM notes WHERE id = ? AND user_id = ?";
    $checkStmt = $db->executeQuery($checkSql, [$noteId, $userId], 'ii');
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows === 0) {
        $checkStmt->close();
        ApiResponse::error('Note not found', 404);
        exit;
    }
    
    $note = $checkResult->fetch_assoc();
    $checkStmt->close();
    
    // Delete note
    $sql = "DELETE FROM notes WHERE id = ? AND user_id = ?";
    $stmt = $db->executeQuery($sql, [$noteId, $userId], 'ii');
    $affectedRows = $stmt->affected_rows;
    $stmt->close();
    
    if ($affectedRows === 0) {
        ApiResponse::error('Failed to delete note', 500);
        exit;
    }
    
    ApiResponse::success([
        'id' => $noteId,
        'title' => $note['title']
    ], 'Note deleted successfully');
    
} catch (Exception $e) {
    error_log("Note delete error: " . $e->getMessage());
    ApiResponse::error('Failed to delete note', 500);
}