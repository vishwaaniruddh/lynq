<?php
/**
 * Notes API - List User Notes
 * 
 * Returns all notes for the current user with pagination and search support
 * 
 * Requirements: 5.1, 9.1 - List notes with search functionality
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
    
    // Get query parameters
    $search = $_GET['search'] ?? '';
    $page = max(1, intval($_GET['page'] ?? 1));
    $limit = min(100, max(1, intval($_GET['limit'] ?? 50)));
    $offset = ($page - 1) * $limit;
    
    $db = DatabaseConfig::getInstance();
    
    // Build search conditions
    $whereConditions = ['user_id = ?'];
    $params = [$userId];
    $types = 'i';
    
    if (!empty($search)) {
        $whereConditions[] = '(title LIKE ? OR content LIKE ?)';
        $searchTerm = '%' . $search . '%';
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $types .= 'ss';
    }
    
    $whereClause = implode(' AND ', $whereConditions);
    
    // Get total count
    $countSql = "SELECT COUNT(*) as total FROM notes WHERE $whereClause";
    $countStmt = $db->executeQuery($countSql, $params, $types);
    $totalCount = $countStmt->get_result()->fetch_assoc()['total'];
    $countStmt->close();
    
    // Get notes
    $sql = "SELECT id, title, content, created_at, updated_at 
            FROM notes 
            WHERE $whereClause 
            ORDER BY updated_at DESC 
            LIMIT ? OFFSET ?";
    
    $params[] = $limit;
    $params[] = $offset;
    $types .= 'ii';
    
    $stmt = $db->executeQuery($sql, $params, $types);
    $result = $stmt->get_result();
    
    $notes = [];
    while ($row = $result->fetch_assoc()) {
        $notes[] = [
            'id' => (int)$row['id'],
            'title' => $row['title'],
            'content' => $row['content'],
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at']
        ];
    }
    
    $stmt->close();
    
    // Calculate pagination info
    $totalPages = ceil($totalCount / $limit);
    
    ApiResponse::success([
        'notes' => $notes,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $totalCount,
            'total_pages' => $totalPages,
            'has_next' => $page < $totalPages,
            'has_prev' => $page > 1
        ],
        'search' => $search
    ], 'Notes retrieved successfully');
    
} catch (Exception $e) {
    error_log("Notes list error: " . $e->getMessage());
    ApiResponse::error('Failed to retrieve notes', 500);
}