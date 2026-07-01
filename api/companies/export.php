<?php
/**
 * Companies API - Export to CSV
 * GET /api/companies/export.php
 */

require_once __DIR__ . '/../../config/autoload.php';
require_once __DIR__ . '/../ApiResponse.php';
require_once __DIR__ . '/../../middleware/ApiAuthMiddleware.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    ApiResponse::methodNotAllowed(['GET']);
}

try {
    $authMiddleware = new ApiAuthMiddleware();
    $user = $authMiddleware->requirePermission('companies.read');
    
    $search = isset($_GET['search']) ? trim($_GET['search']) : null;
    $type = isset($_GET['type']) ? trim($_GET['type']) : null;
    $status = isset($_GET['status']) ? trim($_GET['status']) : null;
    
    $db = Database::getInstance()->getConnection();
    
    $where = [];
    $params = [];
    
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
    
    $sql = "SELECT c.*, 
                   (SELECT COUNT(*) FROM users WHERE company_id = c.id) as user_count
            FROM companies c 
            $whereClause
            ORDER BY c.type DESC, c.name";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $companies = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $filename = 'companies_export_' . date('Y-m-d_His') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    fputcsv($output, ['#', 'Name', 'Type', 'Email', 'Phone', 'Address', 'Users', 'Status', 'Created At']);
    
    $srNo = 1;
    foreach ($companies as $c) {
        fputcsv($output, [
            $srNo++,
            $c['name'] ?? '',
            $c['type'] ?? '',
            $c['email'] ?? '',
            $c['phone'] ?? '',
            $c['address'] ?? '',
            $c['user_count'] ?? 0,
            $c['status'] ?? '',
            $c['created_at'] ?? ''
        ]);
    }
    
    fclose($output);
    exit;
    
} catch (Exception $e) {
    error_log("Companies Export Error: " . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => ['message' => 'Export failed']]);
}
