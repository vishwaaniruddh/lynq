<?php
/**
 * Feasibility Export API Endpoint
 * GET /api/feasibility/export.php - Export feasibility data to Excel
 * 
 * Query Parameters:
 * - status: Filter by feasibility status (optional)
 * - search: Search term (optional)
 * - contractor_id: Filter by contractor (optional)
 * - engineer_id: Filter by engineer (optional)
 * - date_from: Filter by date range start (optional)
 * - date_to: Filter by date range end (optional)
 * - format: Export format (xlsx, csv) - default: xlsx
 * 
 * **Validates: Requirements 8.4**
 */

require_once __DIR__ . '/../../config/autoload.php';
require_once __DIR__ . '/../ApiResponse.php';
require_once __DIR__ . '/../../middleware/ApiAuthMiddleware.php';
require_once __DIR__ . '/../../services/FeasibilityService.php';

// Handle CORS
ApiResponse::setCorsHeaders();
ApiResponse::handlePreflight();

try {
    $authMiddleware = new ApiAuthMiddleware();
    
    // Check rate limiting
    $authMiddleware->checkRateLimit();
    
    // Require authenticated user
    $user = $authMiddleware->requireAuth();
    
    // Verify user has permission to export feasibility data
    if (!canExportFeasibilityData($user)) {
        ApiResponse::forbidden('Access denied. You do not have permission to export feasibility data.');
    }
    
    $feasibilityService = new FeasibilityService();
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        handleExportRequest($feasibilityService, $authMiddleware, $user);
    } else {
        ApiResponse::methodNotAllowed(['GET']);
    }
    
} catch (Exception $e) {
    error_log("Feasibility Export API Error: " . $e->getMessage());
    ApiResponse::serverError('Failed to process request');
}

/**
 * Check if user can export feasibility data
 * 
 * @param array $user User data
 * @return bool True if user has permission
 */
function canExportFeasibilityData($user) {
    // System admin can export all
    if (isset($user['is_system_admin']) && $user['is_system_admin']) {
        return true;
    }
    
    // Check role-based permissions
    $roleId = $user['role_id'] ?? 0;
    
    // Admin roles can export
    if ($roleId <= 2) {
        return true;
    }
    
    // ADV users can export
    if (isset($user['user_type']) && $user['user_type'] === 'adv') {
        return true;
    }
    
    // Contractors can export their own data
    if (isset($user['user_type']) && $user['user_type'] === 'contractor') {
        return true;
    }
    
    return false;
}

/**
 * Handle export request
 * Requirements: 8.4
 */
function handleExportRequest($feasibilityService, $authMiddleware, $user) {
    // Parse filter parameters
    $filters = [];
    
    // Status filter
    if (isset($_GET['status']) && $_GET['status'] !== '') {
        $validStatuses = ['pending_eta', 'eta_submitted', 'ada_submitted', 'feasibility_completed'];
        if (in_array($_GET['status'], $validStatuses)) {
            $filters['status'] = $_GET['status'];
        }
    }
    
    // Search filter
    if (isset($_GET['search']) && trim($_GET['search']) !== '') {
        $filters['search'] = trim($_GET['search']);
    }
    
    // Contractor filter
    if (isset($_GET['contractor_id']) && (int)$_GET['contractor_id'] > 0) {
        $filters['contractor_id'] = (int)$_GET['contractor_id'];
    }
    
    // Engineer filter
    if (isset($_GET['engineer_id']) && (int)$_GET['engineer_id'] > 0) {
        $filters['engineer_id'] = (int)$_GET['engineer_id'];
    }
    
    // Date range filters
    if (isset($_GET['date_from']) && $_GET['date_from'] !== '') {
        $filters['date_from'] = $_GET['date_from'];
    }
    
    if (isset($_GET['date_to']) && $_GET['date_to'] !== '') {
        $filters['date_to'] = $_GET['date_to'];
    }
    
    // Apply user-based restrictions
    if (isset($user['user_type']) && $user['user_type'] === 'contractor') {
        $filters['contractor_id'] = $user['id'];
    }
    
    // Get export format
    $format = isset($_GET['format']) && $_GET['format'] === 'csv' ? 'csv' : 'xlsx';
    
    // Get export data (Requirement 8.4)
    $data = $feasibilityService->exportFeasibilityData($filters);
    
    $authMiddleware->logApiAccess($user['id'], '/api/feasibility/export', 'GET', [
        'filters' => $filters,
        'format' => $format,
        'record_count' => count($data)
    ]);
    
    if ($format === 'csv') {
        exportAsCSV($data);
    } else {
        exportAsExcel($data);
    }
}

/**
 * Export data as CSV
 * 
 * @param array $data Export data
 */
function exportAsCSV($data) {
    $filename = 'feasibility_export_' . date('Y-m-d_His') . '.csv';
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    $output = fopen('php://output', 'w');
    
    // Add BOM for Excel UTF-8 compatibility
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Write headers
    $headers = getExportHeaders();
    fputcsv($output, $headers);
    
    // Write data rows
    foreach ($data as $row) {
        $csvRow = formatExportRow($row);
        fputcsv($output, $csvRow);
    }
    
    fclose($output);
    exit;
}

/**
 * Export data as Excel (XLSX)
 * Uses simple XML-based approach for compatibility
 * 
 * @param array $data Export data
 */
function exportAsExcel($data) {
    $filename = 'feasibility_export_' . date('Y-m-d_His') . '.xlsx';
    
    // Check if PHPExcel or PhpSpreadsheet is available
    $phpExcelPath = __DIR__ . '/../../PHPExcel/PHPExcel-1.8/Classes/PHPExcel.php';
    
    if (file_exists($phpExcelPath)) {
        require_once $phpExcelPath;
        exportWithPHPExcel($data, $filename);
    } else {
        // Fallback to CSV if PHPExcel not available
        exportAsCSV($data);
    }
}

/**
 * Export using PHPExcel library
 * 
 * @param array $data Export data
 * @param string $filename Output filename
 */
function exportWithPHPExcel($data, $filename) {
    $excel = new PHPExcel();
    $sheet = $excel->getActiveSheet();
    $sheet->setTitle('Feasibility Data');
    
    // Write headers
    $headers = getExportHeaders();
    $col = 'A';
    foreach ($headers as $header) {
        $sheet->setCellValue($col . '1', $header);
        $sheet->getStyle($col . '1')->getFont()->setBold(true);
        $col++;
    }
    
    // Write data rows
    $rowNum = 2;
    foreach ($data as $row) {
        $csvRow = formatExportRow($row);
        $col = 'A';
        foreach ($csvRow as $value) {
            $sheet->setCellValue($col . $rowNum, $value);
            $col++;
        }
        $rowNum++;
    }
    
    // Auto-size columns
    $col = 'A';
    for ($i = 0; $i < count($headers); $i++) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
        $col++;
    }
    
    // Output file
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    
    $writer = PHPExcel_IOFactory::createWriter($excel, 'Excel2007');
    $writer->save('php://output');
    exit;
}

/**
 * Get export column headers
 * 
 * @return array Column headers
 */
function getExportHeaders() {
    return [
        'Site Name',
        'LHO',
        'Address',
        'City',
        'State',
        'Bank',
        'Customer',
        'Contractor',
        'Engineer',
        'Feasibility Status',
        'ETA Date/Time',
        'ADA Date/Time',
        'ADA Latitude',
        'ADA Longitude',
        'No. of ATMs',
        'ATM 1 ID',
        'ATM 1 Status',
        'ATM 2 ID',
        'ATM 2 Status',
        'ATM 3 ID',
        'ATM 3 Status',
        'Operator',
        'Signal Status',
        'Operator 2',
        'Signal Status 2',
        'Backroom Network Remark',
        'UPS Available',
        'No. of UPS',
        'UPS Battery Backup',
        'UPS 1 Working',
        'UPS 2 Working',
        'UPS 3 Working',
        'Power Socket Availability',
        'Power Socket Availability UPS',
        'Earthing',
        'Earthing Voltage',
        'Power Fluctuation EN',
        'Power Fluctuation PE',
        'Power Fluctuation PN',
        'Frequent Power Cut',
        'Power Cut From',
        'Power Cut To',
        'Power Cut Remark',
        'EM Lock Available',
        'EM Lock Password',
        'Password Received',
        'Backroom Key Name',
        'Backroom Key Number',
        'Backroom Key Status',
        'Antenna Routing Detail',
        'Router Antenna Position',
        'Router Position',
        'Nearest Shop Name',
        'Nearest Shop Number',
        'Nearest Shop Distance',
        'Backroom Disturbing Material',
        'Backroom Disturbing Material Remark',
        'Remarks',
        'Created At'
    ];
}

/**
 * Format a data row for export
 * 
 * @param array $row Data row
 * @return array Formatted row values
 */
function formatExportRow($row) {
    return [
        $row['site_name'] ?? '',
        $row['lho'] ?? '',
        $row['address'] ?? '',
        $row['city'] ?? '',
        $row['state'] ?? '',
        $row['bank_name'] ?? '',
        $row['customer_name'] ?? '',
        $row['contractor_name'] ?? '',
        $row['engineer_name'] ?? '',
        formatStatus($row['feasibility_status'] ?? ''),
        $row['eta_datetime'] ?? '',
        $row['ada_datetime'] ?? '',
        $row['ada_latitude'] ?? '',
        $row['ada_longitude'] ?? '',
        $row['no_of_atm'] ?? '',
        $row['atm_id_1'] ?? '',
        $row['atm_1_status'] ?? '',
        $row['atm_id_2'] ?? '',
        $row['atm_2_status'] ?? '',
        $row['atm_id_3'] ?? '',
        $row['atm_3_status'] ?? '',
        $row['operator'] ?? '',
        $row['signal_status'] ?? '',
        $row['operator_2'] ?? '',
        $row['signal_status_2'] ?? '',
        $row['backroom_network_remark'] ?? '',
        $row['ups_available'] ?? '',
        $row['no_of_ups'] ?? '',
        $row['ups_battery_backup'] ?? '',
        $row['ups_working_1'] ?? '',
        $row['ups_working_2'] ?? '',
        $row['ups_working_3'] ?? '',
        $row['power_socket_availability'] ?? '',
        $row['power_socket_availability_ups'] ?? '',
        $row['earthing'] ?? '',
        $row['earthing_voltage'] ?? '',
        $row['power_fluctuation_en'] ?? '',
        $row['power_fluctuation_pe'] ?? '',
        $row['power_fluctuation_pn'] ?? '',
        $row['frequent_power_cut'] ?? '',
        $row['frequent_power_cut_from'] ?? '',
        $row['frequent_power_cut_to'] ?? '',
        $row['frequent_power_cut_remark'] ?? '',
        $row['em_lock_available'] ?? '',
        $row['em_lock_password'] ?? '',
        $row['password_received'] ?? '',
        $row['backroom_key_name'] ?? '',
        $row['backroom_key_number'] ?? '',
        $row['backroom_key_status'] ?? '',
        $row['antenna_routing_detail'] ?? '',
        $row['router_antenna_position'] ?? '',
        $row['router_position'] ?? '',
        $row['nearest_shop_name'] ?? '',
        $row['nearest_shop_number'] ?? '',
        $row['nearest_shop_distance'] ?? '',
        $row['backroom_disturbing_material'] ?? '',
        $row['backroom_disturbing_material_remark'] ?? '',
        $row['remarks'] ?? '',
        $row['created_at'] ?? ''
    ];
}

/**
 * Format status for display
 * 
 * @param string $status Status value
 * @return string Formatted status
 */
function formatStatus($status) {
    $statusMap = [
        'pending_eta' => 'Pending ETA',
        'eta_submitted' => 'ETA Submitted',
        'ada_submitted' => 'ADA Submitted',
        'feasibility_completed' => 'Completed'
    ];
    
    return $statusMap[$status] ?? $status;
}
