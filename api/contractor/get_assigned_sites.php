<?php
/**
 * Dedicated API Endpoint: Get Assigned Sites by Contractor ID
 * 
 * Endpoint:
 *   GET /api/contractor/get_assigned_sites.php?contractor_id=2
 *   POST /api/contractor/get_assigned_sites.php (body: {"contractor_id": 2})
 * 
 * Parameters:
 *   - contractor_id (required): ID of the contractor company (integer > 0)
 *   - status (optional): Filter by delegation status ('pending', 'accepted', 'rejected')
 *   - search (optional): Search query matching site_name, lho, bank_name, city, or state
 *   - page (optional): Page number (default: 1)
 *   - limit (optional): Items per page (default: 20, max: 500)
 *   - export (optional): Set to 1 to fetch all records without pagination limits
 * 
 * Response:
 *   Returns JSON payload with contractor details, status counts, pagination info, and site lists.
 */

require_once __DIR__ . '/../../config/autoload.php';
require_once __DIR__ . '/../ApiResponse.php';
require_once __DIR__ . '/../../middleware/ApiAuthMiddleware.php';

// Set CORS headers
ApiResponse::setCorsHeaders();
ApiResponse::handlePreflight();

try {
    $db = Database::getInstance()->getConnection();
    
    // Parse Input Parameters from GET, POST, or JSON body
    $input = [];
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $input = $_GET;
    } else {
        $json = json_decode(file_get_contents('php://input'), true);
        $input = is_array($json) ? array_merge($_POST, $json) : $_POST;
    }

    // Extract and validate contractor_id
    $contractorId = isset($input['contractor_id']) ? (int)$input['contractor_id'] : 0;
    if ($contractorId <= 0) {
        ApiResponse::validationError(
            ['contractor_id' => ['contractor_id is required and must be a positive integer']],
            'Missing or invalid contractor_id'
        );
        exit;
    }

    // Optional filters
    $statusFilter = isset($input['status']) ? trim($input['status']) : '';
    $searchQuery = isset($input['search']) ? trim($input['search']) : '';
    $lhoFilter = isset($input['lho']) ? trim($input['lho']) : '';
    
    $page = max(1, (int)($input['page'] ?? 1));
    $limit = min(500, max(1, (int)($input['limit'] ?? 20)));
    $isExport = isset($input['export']) && ($input['export'] == '1' || $input['export'] === 'true');

    // 1. Verify Contractor Company Details
    $stmtComp = $db->prepare("SELECT id, name, type, status, contact_email, contact_phone FROM companies WHERE id = ?");
    $stmtComp->execute([$contractorId]);
    $contractorInfo = $stmtComp->fetch(PDO::FETCH_ASSOC);

    if (!$contractorInfo) {
        ApiResponse::notFound("Contractor with ID {$contractorId} not found");
        exit;
    }

    // 2. Fetch Status Counts for Contractor
    $stmtCounts = $db->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'accepted' THEN 1 ELSE 0 END) as accepted,
            SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
        FROM site_delegations
        WHERE contractor_id = ?
    ");
    $stmtCounts->execute([$contractorId]);
    $rawCounts = $stmtCounts->fetch(PDO::FETCH_ASSOC);
    $counts = [
        'total' => (int)($rawCounts['total'] ?? 0),
        'pending' => (int)($rawCounts['pending'] ?? 0),
        'accepted' => (int)($rawCounts['accepted'] ?? 0),
        'rejected' => (int)($rawCounts['rejected'] ?? 0)
    ];

    // 3. Build Main Query for Assigned Sites
    $whereConditions = ["sd.contractor_id = :contractor_id"];
    $queryParams = [':contractor_id' => $contractorId];

    if (!empty($statusFilter)) {
        $whereConditions[] = "sd.status = :status_filter";
        $queryParams[':status_filter'] = strtolower($statusFilter);
    }

    if (!empty($lhoFilter)) {
        $whereConditions[] = "s.lho = :lho_filter";
        $queryParams[':lho_filter'] = $lhoFilter;
    }

    if (!empty($searchQuery)) {
        $whereConditions[] = "(s.site_name LIKE :search OR s.lho LIKE :search OR s.bank_name LIKE :search OR s.city LIKE :search OR s.state LIKE :search)";
        $queryParams[':search'] = '%' . $searchQuery . '%';
    }

    $whereClause = "WHERE " . implode(" AND ", $whereConditions);

    // Count filtered records
    $countSql = "
        SELECT COUNT(*) 
        FROM site_delegations sd
        INNER JOIN sites s ON sd.site_id = s.id
        $whereClause
    ";
    $stmtCount = $db->prepare($countSql);
    $stmtCount->execute($queryParams);
    $totalFiltered = (int)$stmtCount->fetchColumn();

    // Calculate Pagination
    $totalPages = $isExport ? 1 : max(1, (int)ceil($totalFiltered / $limit));
    $offset = $isExport ? 0 : ($page - 1) * $limit;

    // Fetch Assigned Sites Data
    $dataSql = "
        SELECT 
            sd.id as delegation_id,
            sd.site_id,
            sd.contractor_id,
            sd.delegated_at,
            sd.status as delegation_status,
            sd.rejection_notes,
            sd.responded_at,
            s.site_name,
            s.lho,
            s.bank_name,
            s.customer_name,
            s.city,
            s.state,
            s.country,
            s.zone,
            s.address,
            s.latitude,
            s.longitude,
            s.status as site_status,
            u_del.username as delegated_by_username,
            u_res.username as responded_by_username,
            ea.id as engineer_assignment_id,
            ea.engineer_id,
            ea.status as engineer_assignment_status,
            ea.feasibility_status,
            CONCAT(IFNULL(u_eng.first_name,''), ' ', IFNULL(u_eng.last_name,'')) as engineer_name,
            u_eng.username as engineer_username
        FROM site_delegations sd
        INNER JOIN sites s ON sd.site_id = s.id
        LEFT JOIN users u_del ON sd.delegated_by = u_del.id
        LEFT JOIN users u_res ON sd.responded_by = u_res.id
        LEFT JOIN engineer_assignments ea ON sd.id = ea.delegation_id
        LEFT JOIN users u_eng ON ea.engineer_id = u_eng.id
        $whereClause
        ORDER BY sd.delegated_at DESC
    ";

    if (!$isExport) {
        $dataSql .= " LIMIT " . (int)$limit . " OFFSET " . (int)$offset;
    }

    $stmtData = $db->prepare($dataSql);
    $stmtData->execute($queryParams);
    $sites = $stmtData->fetchAll(PDO::FETCH_ASSOC);

    // Format fields
    $formattedSites = array_map(function($row) {
        return [
            'delegation_id' => (int)$row['delegation_id'],
            'site_id' => (int)$row['site_id'],
            'contractor_id' => (int)$row['contractor_id'],
            'site_name' => $row['site_name'],
            'lho' => $row['lho'],
            'bank_name' => $row['bank_name'],
            'customer_name' => $row['customer_name'],
            'location' => [
                'city' => $row['city'],
                'state' => $row['state'],
                'country' => $row['country'],
                'zone' => $row['zone'],
                'address' => $row['address'],
                'latitude' => $row['latitude'],
                'longitude' => $row['longitude']
            ],
            'site_status' => $row['site_status'],
            'delegation_status' => $row['delegation_status'],
            'delegated_at' => $row['delegated_at'],
            'delegated_by' => $row['delegated_by_username'],
            'rejection_notes' => $row['rejection_notes'],
            'responded_at' => $row['responded_at'],
            'responded_by' => $row['responded_by_username'],
            'engineer' => [
                'assignment_id' => $row['engineer_assignment_id'] ? (int)$row['engineer_assignment_id'] : null,
                'engineer_id' => $row['engineer_id'] ? (int)$row['engineer_id'] : null,
                'engineer_name' => trim($row['engineer_name']) ?: $row['engineer_username'],
                'assignment_status' => $row['engineer_assignment_status'],
                'feasibility_status' => $row['feasibility_status']
            ]
        ];
    }, $sites);

    // Return Success Response
    ApiResponse::success([
        'contractor' => $contractorInfo,
        'counts' => $counts,
        'pagination' => [
            'page' => $isExport ? 1 : $page,
            'limit' => $isExport ? $totalFiltered : $limit,
            'total' => $totalFiltered,
            'total_pages' => $totalPages
        ],
        'data' => $formattedSites
    ], 'Contractor assigned sites retrieved successfully');

} catch (Exception $e) {
    error_log("Get Assigned Sites API Error: " . $e->getMessage());
    ApiResponse::serverError('Failed to retrieve assigned sites: ' . $e->getMessage());
}
