<?php
/**
 * Get Engineers API
 * GET /api/users/engineers.php
 * 
 * Returns list of engineers in the current user's company
 */

require_once __DIR__ . '/../../config/autoload.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    $sessionService = new SessionService();
    $db = DatabaseConfig::getInstance();
    
    $userId = $sessionService->getCurrentUserId();
    if (!$userId) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Authentication required']);
        exit;
    }
    
    // Get user's company
    $userSql = "SELECT company_id FROM users WHERE id = ?";
    $userResult = $db->getResults($userSql, [$userId], 'i');
    $companyId = $userResult[0]['company_id'] ?? null;
    
    if (!$companyId) {
        echo json_encode(['success' => true, 'data' => []]);
        exit;
    }
    
    // Get engineers in the company (users with engineer role)
    // Engineers are identified by:
    // 1. Role name containing 'engineer' or 'technician'
    // 2. Role level <= 50 (field-level roles, not admin/manager)
    // 3. Excluding the current user (contractor admin)
    $sql = "SELECT 
                u.id,
                u.first_name,
                u.last_name,
                CONCAT(u.first_name, ' ', u.last_name) as name,
                u.email,
                u.phone,
                r.name as role_name,
                r.level as role_level
            FROM users u
            LEFT JOIN roles r ON u.role_id = r.id
            WHERE u.company_id = ?
            AND u.status = 1
            AND u.id != ?
            AND (
                LOWER(r.name) LIKE '%engineer%' 
                OR LOWER(r.name) LIKE '%technician%'
                OR LOWER(r.name) LIKE '%field%'
                OR (r.level IS NOT NULL AND r.level <= 50 AND r.level > 0)
            )
            ORDER BY u.first_name, u.last_name";
    
    $engineers = $db->getResults($sql, [$companyId, $userId], 'ii');
    
    echo json_encode([
        'success' => true,
        'data' => $engineers,
        'count' => count($engineers)
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to load engineers: ' . $e->getMessage()]);
}
