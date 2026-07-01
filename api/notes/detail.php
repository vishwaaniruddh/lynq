<?php
/**
 * Notes API - Get Note Details
 * 
 * Returns details of a specific note for the current user
 * 
 * Requirements: 6.1 - Load note for editing
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
    
    // Get note ID
    $noteId = intval($_GET['id'] ?? 0);
    if (!$noteId) {
        ApiResponse::error('Note ID is required', 400);
        exit;
    }
    
    $db = DatabaseConfig::getInstance();
    
    // Get note (ensure it belongs to current user)
    $sql = "SELECT id, title, content, created_at, updated_at 
            FROM notes 
            WHERE id = ? AND user_id = ?";
    
    $stmt = $db->executeQuery($sql, [$noteId, $userId], 'ii');
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $stmt->close();
        ApiResponse::error('Note not found', 404);
        exit;
    }
    
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
    
    ApiResponse::success($noteData, 'Note retrieved successfully');
    
} catch (Exception $e) {
    error_log("Note detail error: " . $e->getMessage());
    ApiResponse::error('Failed to retrieve note', 500);
}