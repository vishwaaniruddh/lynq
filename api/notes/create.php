<?php
/**
 * Notes API - Create New Note
 * 
 * Creates a new note for the current user
 * 
 * Requirements: 3.1, 3.3 - Create and save new notes
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
    
    // Insert note
    $sql = "INSERT INTO notes (user_id, title, content) VALUES (?, ?, ?)";
    $stmt = $db->executeQuery($sql, [$userId, $title, $content], 'iss');
    
    $noteId = $db->getConnection()->insert_id;
    $stmt->close();
    
    // Get the created note
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
    
    ApiResponse::success($noteData, 'Note created successfully');
    
} catch (Exception $e) {
    error_log("Note create error: " . $e->getMessage());
    ApiResponse::error('Failed to create note', 500);
}