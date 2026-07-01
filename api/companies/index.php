<?php
/**
 * Companies API - List Companies
 * GET /api/companies/index.php
 */

require_once __DIR__ . '/../../config/autoload.php';
require_once __DIR__ . '/../ApiResponse.php';
require_once __DIR__ . '/../../middleware/ApiAuthMiddleware.php';

ApiResponse::setCorsHeaders();
ApiResponse::handlePreflight();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    ApiResponse::methodNotAllowed(['GET']);
}

try {
    $authMiddleware = new ApiAuthMiddleware();
    $authMiddleware->checkRateLimit();
    $user = $authMiddleware->requirePermission('companies.read');
    
    $search = isset($_GET['search']) ? trim($_GET['search']) : null;
    $type = isset($_GET['type']) ? trim($_GET['type']) : null;
    $status = isset($_GET['status']) ? trim($_GET['status']) : null;
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = min(100, max(1, (int)($_GET['limit'] ?? 20)));
    $offset = ($page - 1) * $limit;
    
    $db = Database::getInstance()->getConnection();
    
    // Build query
    $where = [];
    $params = [];
    
    // ADV users see all, contractors see only their company
    if (!isAdvUser($user['id'])) {
        $where[] = "c.id = ?";
        $params[] = $user['company_id'];
    }
    
    if ($search) {
        $where[] = "(c.name LIKE ? OR c.email LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    if ($type) {
        $where[] = "c.type = ?";
        $params[] = $type;
    }
    
    if ($status) {
        $where[] = "c.status = ?";
        $params[] = $status;
    }
    
    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
    
    // Get total count
    $countSql = "SELECT COUNT(*) as total FROM companies c $whereClause";
    $stmt = $db->prepare($countSql);
    $stmt->execute($params);
    $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Get companies with pagination
    $sql = "SELECT c.*, 
                   (SELECT COUNT(*) FROM users WHERE company_id = c.id) as user_count
            FROM companies c 
            $whereClause
            ORDER BY c.type DESC, c.name
            LIMIT $limit OFFSET $offset";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $companies = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    ApiResponse::success([
        'companies' => $companies,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => (int)$total,
            'total_pages' => ceil($total / $limit)
        ]
    ], 'Companies retrieved successfully');
    
} catch (Exception $e) {
    error_log("Companies API Error: " . $e->getMessage());
    ApiResponse::serverError('Failed to retrieve companies');
}
