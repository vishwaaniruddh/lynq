<?php
/**
 * Users API - Export Users to CSV
 * GET /api/users/export.php
 * 
 * Exports users list to CSV format
 */

require_once __DIR__ . '/../../config/autoload.php';
require_once __DIR__ . '/../ApiResponse.php';
require_once __DIR__ . '/../../middleware/ApiAuthMiddleware.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    ApiResponse::methodNotAllowed(['GET']);
}

try {
    $authMiddleware = new ApiAuthMiddleware();
    $user = $authMiddleware->requirePermission('users.read');
    
    $companyId = isset($_GET['company_id']) ? (int)$_GET['company_id'] : null;
    $search = isset($_GET['search']) ? trim($_GET['search']) : null;
    $status = isset($_GET['status']) ? $_GET['status'] : null;
    
    $userRepository = new UserRepository();
    $userRepository->setCurrentUser($user['id']);
    
    // Get users based on filters
    if ($companyId !== null) {
        $authMiddleware->requireCompanyAccess($companyId);
        $users = $userRepository->findByCompanyWithRelations($companyId);
    } elseif ($search !== null && $search !== '') {
        $users = $userRepository->search($search);
    } else {
        $users = $userRepository->findAllWithRelations();
    }
    
    // Apply status filter
    if ($status !== null && $status !== '') {
        $users = array_filter($users, function($u) use ($status) {
            return (string)$u['status'] === $status;
        });
    }
    
    // Set headers for CSV download
    $filename = 'users_export_' . date('Y-m-d_His') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    $output = fopen('php://output', 'w');
    
    // Add BOM for Excel UTF-8 compatibility
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Header row
    fputcsv($output, ['#', 'Username', 'Email', 'Company', 'Company Type', 'Role', 'Status', 'Created At']);
    
    // Data rows
    $srNo = 1;
    foreach ($users as $u) {
        fputcsv($output, [
            $srNo++,
            $u['username'] ?? '',
            $u['email'] ?? '',
            $u['company_name'] ?? '',
            $u['company_type'] ?? '',
            $u['role_name'] ?? '',
            $u['status'] == 1 ? 'Active' : 'Inactive',
            $u['created_at'] ?? ''
        ]);
    }
    
    fclose($output);
    exit;
    
} catch (Exception $e) {
    error_log("Users Export Error: " . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => ['message' => 'Export failed']]);
}
