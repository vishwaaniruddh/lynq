<?php
/**
 * Configuration Report Service
 * Provides report generation for IP Configuration Management
 * 
 * Requirements: 8.1, 8.2, 8.3
 * - 8.1: Generate configuration report with router serial, IP details, date, user
 * - 8.2: Generate IP usage report with all IPs, status, and bound router
 * - 8.3: Generate pending configuration report with unconfigured routers
 */

require_once __DIR__ . '/../config/autoload.php';
require_once __DIR__ . '/../repositories/IPMasterRepository.php';
require_once __DIR__ . '/../repositories/RouterIPBindingRepository.php';
require_once __DIR__ . '/../repositories/ConfigurationAuditLogRepository.php';
require_once __DIR__ . '/../models/IPMaster.php';
require_once __DIR__ . '/../models/RouterIPBinding.php';

class ConfigurationReportService {
    private $db;
    private $ipMasterRepository;
    private $bindingRepository;
    private $auditLogRepository;
    
    public function __construct() {
        $this->db = DatabaseConfig::getInstance();
        $this->ipMasterRepository = new IPMasterRepository();
        $this->bindingRepository = new RouterIPBindingRepository();
        $this->auditLogRepository = new ConfigurationAuditLogRepository();
    }
    
    /**
     * Get configuration report - all configurations with details
     * 
     * @param array $filters Optional filters: date_from, date_to, configured_by, search
     * @return array Configuration report data
     * 
     * Requirements: 8.1
     */
    public function getConfigurationReport(array $filters = []): array {
        $whereClause = ["b.status = ?"];
        $params = [RouterIPBinding::STATUS_ACTIVE];
        $types = 's';
        
        // Date range filter
        if (!empty($filters['date_from'])) {
            $whereClause[] = "b.configured_at >= ?";
            $params[] = $filters['date_from'] . ' 00:00:00';
            $types .= 's';
        }
        
        if (!empty($filters['date_to'])) {
            $whereClause[] = "b.configured_at <= ?";
            $params[] = $filters['date_to'] . ' 23:59:59';
            $types .= 's';
        }
        
        // Configured by filter
        if (!empty($filters['configured_by'])) {
            $whereClause[] = "b.configured_by = ?";
            $params[] = (int)$filters['configured_by'];
            $types .= 'i';
        }
        
        // Search filter
        if (!empty($filters['search'])) {
            $searchTerm = '%' . $filters['search'] . '%';
            $whereClause[] = "(b.router_serial_number LIKE ? OR m.network_ip LIKE ? OR m.router_ip LIKE ? OR m.site_ip LIKE ?)";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $types .= 'ssss';
        }
        
        $whereSQL = implode(' AND ', $whereClause);
        
        $sql = "SELECT 
                    b.id as binding_id,
                    b.router_serial_number,
                    b.configured_at,
                    b.notes,
                    m.id as ip_master_id,
                    m.network_ip,
                    m.router_ip,
                    m.site_ip,
                    m.subnet_mask,
                    u.id as configured_by_id,
                    u.username as configured_by_username,
                    u.first_name as configured_by_first_name,
                    u.last_name as configured_by_last_name
                FROM router_ip_bindings b
                JOIN ip_master m ON b.ip_master_id = m.id
                LEFT JOIN users u ON b.configured_by = u.id
                WHERE $whereSQL
                ORDER BY b.configured_at DESC";
        
        $results = $this->db->getResults($sql, $params, $types);
        
        return [
            'data' => $results,
            'total' => count($results),
            'filters_applied' => $filters,
            'generated_at' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * Get IP usage report - all IPs with status and bindings
     * 
     * @param array $filters Optional filters: status, search
     * @return array IP usage report data
     * 
     * Requirements: 8.2
     */
    public function getIPUsageReport(array $filters = []): array {
        $whereClause = [];
        $params = [];
        $types = '';
        
        // Status filter
        if (!empty($filters['status'])) {
            $whereClause[] = "m.status = ?";
            $params[] = $filters['status'];
            $types .= 's';
        }
        
        // Search filter
        if (!empty($filters['search'])) {
            $searchTerm = '%' . $filters['search'] . '%';
            $whereClause[] = "(m.network_ip LIKE ? OR m.router_ip LIKE ? OR m.site_ip LIKE ? OR m.subnet_mask LIKE ?)";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $types .= 'ssss';
        }
        
        $whereSQL = !empty($whereClause) ? 'WHERE ' . implode(' AND ', $whereClause) : '';
        
        $sql = "SELECT 
                    m.id as ip_master_id,
                    m.network_ip,
                    m.router_ip,
                    m.site_ip,
                    m.subnet_mask,
                    m.status,
                    m.created_at as ip_created_at,
                    b.id as binding_id,
                    b.router_serial_number,
                    b.configured_at,
                    b.notes as binding_notes,
                    u.id as configured_by_id,
                    u.username as configured_by_username
                FROM ip_master m
                LEFT JOIN router_ip_bindings b ON m.id = b.ip_master_id AND b.status = ?
                LEFT JOIN users u ON b.configured_by = u.id
                $whereSQL
                ORDER BY m.id ASC";
        
        // Add the binding status parameter at the beginning
        array_unshift($params, RouterIPBinding::STATUS_ACTIVE);
        $types = 's' . $types;
        
        $results = $this->db->getResults($sql, $params, $types);
        
        // Calculate summary statistics
        $summary = [
            'total' => count($results),
            'available' => 0,
            'locked' => 0,
            'configured' => 0
        ];
        
        foreach ($results as $row) {
            switch ($row['status']) {
                case IPMaster::STATUS_AVAILABLE:
                    $summary['available']++;
                    break;
                case IPMaster::STATUS_LOCKED:
                    $summary['locked']++;
                    break;
                case IPMaster::STATUS_CONFIGURED:
                    $summary['configured']++;
                    break;
            }
        }
        
        return [
            'data' => $results,
            'summary' => $summary,
            'filters_applied' => $filters,
            'generated_at' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * Get pending configuration report - unconfigured routers
     * 
     * @param array $filters Optional filters: search
     * @return array Pending configuration report data
     * 
     * Requirements: 8.3
     */
    public function getPendingReport(array $filters = []): array {
        // Get all routers from inventory that don't have active bindings
        $whereClause = [];
        $params = [];
        $types = '';
        
        // Search filter
        if (!empty($filters['search'])) {
            $searchTerm = '%' . $filters['search'] . '%';
            $whereClause[] = "(a.serial_number LIKE ? OR p.name LIKE ?)";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $types .= 'ss';
        }
        
        $whereSQL = !empty($whereClause) ? 'AND ' . implode(' AND ', $whereClause) : '';
        
        // Query routers from inventory that are not configured
        $sql = "SELECT 
                    a.id as asset_id,
                    a.serial_number,
                    a.status as asset_status,
                    a.created_at as asset_created_at,
                    p.id as product_id,
                    p.name as product_name,
                    w.id as warehouse_id,
                    w.name as warehouse_name,
                    l.id as lock_id,
                    l.locked_by,
                    l.locked_at,
                    l.expires_at,
                    lu.username as locked_by_username
                FROM assets a
                JOIN products p ON a.product_id = p.id
                JOIN product_categories pc ON p.category_id = pc.id
                LEFT JOIN warehouses w ON a.warehouse_id = w.id
                LEFT JOIN router_ip_bindings b ON a.serial_number = b.router_serial_number AND b.status = ?
                LEFT JOIN ip_locks l ON a.serial_number = l.router_serial_number AND l.status = ? AND l.expires_at > NOW()
                LEFT JOIN users lu ON l.locked_by = lu.id
                WHERE (pc.name LIKE '%router%' OR pc.name LIKE '%Router%' OR p.name LIKE '%router%' OR p.name LIKE '%Router%')
                AND b.id IS NULL
                $whereSQL
                ORDER BY a.serial_number ASC";
        
        // Add binding status parameter at the beginning
        array_unshift($params, RouterIPBinding::STATUS_ACTIVE);
        array_splice($params, 1, 0, 'active'); // Insert lock status after binding status
        $types = 'ss' . $types;
        
        $results = $this->db->getResults($sql, $params, $types);
        
        // If no results from inventory, try to get from configuration data
        if (empty($results)) {
            $results = $this->getPendingFromConfigurationData($filters);
        }
        
        // Calculate summary
        $summary = [
            'total_pending' => count($results),
            'in_progress' => 0,
            'waiting' => 0
        ];
        
        foreach ($results as $row) {
            if (!empty($row['lock_id'])) {
                $summary['in_progress']++;
            } else {
                $summary['waiting']++;
            }
        }
        
        return [
            'data' => $results,
            'summary' => $summary,
            'filters_applied' => $filters,
            'generated_at' => date('Y-m-d H:i:s')
        ];
    }

    
    /**
     * Get pending routers from configuration data (fallback)
     * 
     * @param array $filters Optional filters
     * @return array Pending routers
     */
    private function getPendingFromConfigurationData(array $filters = []): array {
        // This is a fallback when inventory data is not available
        // Returns routers that have been in locks but never completed configuration
        $whereClause = [];
        $params = [];
        $types = '';
        
        if (!empty($filters['search'])) {
            $searchTerm = '%' . $filters['search'] . '%';
            $whereClause[] = "l.router_serial_number LIKE ?";
            $params[] = $searchTerm;
            $types .= 's';
        }
        
        $whereSQL = !empty($whereClause) ? 'AND ' . implode(' AND ', $whereClause) : '';
        
        $sql = "SELECT DISTINCT
                    l.router_serial_number as serial_number,
                    NULL as asset_id,
                    NULL as asset_status,
                    MIN(l.locked_at) as first_seen,
                    al.id as lock_id,
                    al.locked_by,
                    al.locked_at,
                    al.expires_at,
                    u.username as locked_by_username
                FROM ip_locks l
                LEFT JOIN router_ip_bindings b ON l.router_serial_number = b.router_serial_number AND b.status = ?
                LEFT JOIN ip_locks al ON l.router_serial_number = al.router_serial_number AND al.status = ? AND al.expires_at > NOW()
                LEFT JOIN users u ON al.locked_by = u.id
                WHERE b.id IS NULL
                $whereSQL
                GROUP BY l.router_serial_number, al.id, al.locked_by, al.locked_at, al.expires_at, u.username
                ORDER BY l.router_serial_number ASC";
        
        array_unshift($params, RouterIPBinding::STATUS_ACTIVE);
        array_splice($params, 1, 0, 'active');
        $types = 'ss' . $types;
        
        return $this->db->getResults($sql, $params, $types);
    }
    
    /**
     * Export configuration report to CSV format
     * 
     * @param array $data Report data
     * @return string CSV content
     * 
     * Requirements: 8.4
     */
    public function exportConfigurationReportToCSV(array $data): string {
        $output = fopen('php://temp', 'r+');
        
        // Write header
        fputcsv($output, [
            'Binding ID',
            'Router Serial Number',
            'Network IP',
            'Router IP',
            'Site IP',
            'Subnet Mask',
            'Configured At',
            'Configured By',
            'Notes'
        ]);
        
        // Write data rows
        foreach ($data['data'] as $row) {
            fputcsv($output, [
                $row['binding_id'],
                $row['router_serial_number'],
                $row['network_ip'],
                $row['router_ip'],
                $row['site_ip'],
                $row['subnet_mask'],
                $row['configured_at'],
                $row['configured_by_username'] ?? '',
                $row['notes'] ?? ''
            ]);
        }
        
        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);
        
        return $csv;
    }
    
    /**
     * Export IP usage report to CSV format
     * 
     * @param array $data Report data
     * @return string CSV content
     * 
     * Requirements: 8.4
     */
    public function exportIPUsageReportToCSV(array $data): string {
        $output = fopen('php://temp', 'r+');
        
        // Write header
        fputcsv($output, [
            'IP Master ID',
            'Network IP',
            'Router IP',
            'Site IP',
            'Subnet Mask',
            'Status',
            'Created At',
            'Bound Router Serial',
            'Configured At',
            'Configured By'
        ]);
        
        // Write data rows
        foreach ($data['data'] as $row) {
            fputcsv($output, [
                $row['ip_master_id'],
                $row['network_ip'],
                $row['router_ip'],
                $row['site_ip'],
                $row['subnet_mask'],
                $row['status'],
                $row['ip_created_at'],
                $row['router_serial_number'] ?? '',
                $row['configured_at'] ?? '',
                $row['configured_by_username'] ?? ''
            ]);
        }
        
        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);
        
        return $csv;
    }
    
    /**
     * Export pending report to CSV format
     * 
     * @param array $data Report data
     * @return string CSV content
     * 
     * Requirements: 8.4
     */
    public function exportPendingReportToCSV(array $data): string {
        $output = fopen('php://temp', 'r+');
        
        // Write header
        fputcsv($output, [
            'Serial Number',
            'Product Name',
            'Warehouse',
            'Asset Status',
            'Configuration Status',
            'Locked By',
            'Lock Expires At'
        ]);
        
        // Write data rows
        foreach ($data['data'] as $row) {
            $configStatus = !empty($row['lock_id']) ? 'In Progress' : 'Waiting';
            fputcsv($output, [
                $row['serial_number'],
                $row['product_name'] ?? '',
                $row['warehouse_name'] ?? '',
                $row['asset_status'] ?? '',
                $configStatus,
                $row['locked_by_username'] ?? '',
                $row['expires_at'] ?? ''
            ]);
        }
        
        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);
        
        return $csv;
    }
    
    /**
     * Parse CSV content for import
     * 
     * @param string $csvContent CSV content
     * @param string $reportType Report type (configurations, ip_usage, pending)
     * @return array Parsed data
     * 
     * Requirements: 8.4
     */
    public function parseCSV(string $csvContent, string $reportType): array {
        $lines = explode("\n", trim($csvContent));
        
        if (count($lines) < 2) {
            return [
                'success' => false,
                'message' => 'CSV file is empty or has no data rows',
                'records' => []
            ];
        }
        
        // Parse header
        $header = str_getcsv(array_shift($lines));
        
        // Parse data rows
        $records = [];
        foreach ($lines as $lineNum => $line) {
            if (empty(trim($line))) {
                continue;
            }
            
            $values = str_getcsv($line);
            
            if (count($values) !== count($header)) {
                continue; // Skip malformed rows
            }
            
            $record = array_combine($header, $values);
            $records[] = $this->normalizeRecord($record, $reportType);
        }
        
        return [
            'success' => true,
            'message' => 'CSV parsed successfully',
            'records' => $records,
            'total' => count($records)
        ];
    }
    
    /**
     * Normalize a parsed record based on report type
     * 
     * @param array $record Raw record
     * @param string $reportType Report type
     * @return array Normalized record
     */
    private function normalizeRecord(array $record, string $reportType): array {
        switch ($reportType) {
            case 'configurations':
                return [
                    'binding_id' => $record['Binding ID'] ?? null,
                    'router_serial_number' => $record['Router Serial Number'] ?? null,
                    'network_ip' => $record['Network IP'] ?? null,
                    'router_ip' => $record['Router IP'] ?? null,
                    'site_ip' => $record['Site IP'] ?? null,
                    'subnet_mask' => $record['Subnet Mask'] ?? null,
                    'configured_at' => $record['Configured At'] ?? null,
                    'configured_by_username' => $record['Configured By'] ?? null,
                    'notes' => $record['Notes'] ?? null
                ];
                
            case 'ip_usage':
                return [
                    'ip_master_id' => $record['IP Master ID'] ?? null,
                    'network_ip' => $record['Network IP'] ?? null,
                    'router_ip' => $record['Router IP'] ?? null,
                    'site_ip' => $record['Site IP'] ?? null,
                    'subnet_mask' => $record['Subnet Mask'] ?? null,
                    'status' => $record['Status'] ?? null,
                    'ip_created_at' => $record['Created At'] ?? null,
                    'router_serial_number' => $record['Bound Router Serial'] ?? null,
                    'configured_at' => $record['Configured At'] ?? null,
                    'configured_by_username' => $record['Configured By'] ?? null
                ];
                
            case 'pending':
                return [
                    'serial_number' => $record['Serial Number'] ?? null,
                    'product_name' => $record['Product Name'] ?? null,
                    'warehouse_name' => $record['Warehouse'] ?? null,
                    'asset_status' => $record['Asset Status'] ?? null,
                    'configuration_status' => $record['Configuration Status'] ?? null,
                    'locked_by_username' => $record['Locked By'] ?? null,
                    'expires_at' => $record['Lock Expires At'] ?? null
                ];
                
            default:
                return $record;
        }
    }
    
    /**
     * Get configuration history for a specific router
     * 
     * @param string $routerSerialNumber Router serial number
     * @return array Configuration history
     */
    public function getRouterConfigurationHistory(string $routerSerialNumber): array {
        $sql = "SELECT 
                    b.id as binding_id,
                    b.router_serial_number,
                    b.configured_at,
                    b.notes,
                    b.status as binding_status,
                    b.unbound_at,
                    b.unbind_reason,
                    m.id as ip_master_id,
                    m.network_ip,
                    m.router_ip,
                    m.site_ip,
                    m.subnet_mask,
                    u1.username as configured_by_username,
                    u2.username as unbound_by_username
                FROM router_ip_bindings b
                JOIN ip_master m ON b.ip_master_id = m.id
                LEFT JOIN users u1 ON b.configured_by = u1.id
                LEFT JOIN users u2 ON b.unbound_by = u2.id
                WHERE b.router_serial_number = ?
                ORDER BY b.configured_at DESC";
        
        return $this->db->getResults($sql, [$routerSerialNumber], 's');
    }
    
    /**
     * Get IP configuration history
     * 
     * @param int $ipMasterId IP Master ID
     * @return array Configuration history
     */
    public function getIPConfigurationHistory(int $ipMasterId): array {
        $sql = "SELECT 
                    b.id as binding_id,
                    b.router_serial_number,
                    b.configured_at,
                    b.notes,
                    b.status as binding_status,
                    b.unbound_at,
                    b.unbind_reason,
                    m.id as ip_master_id,
                    m.network_ip,
                    m.router_ip,
                    m.site_ip,
                    m.subnet_mask,
                    u1.username as configured_by_username,
                    u2.username as unbound_by_username
                FROM router_ip_bindings b
                JOIN ip_master m ON b.ip_master_id = m.id
                LEFT JOIN users u1 ON b.configured_by = u1.id
                LEFT JOIN users u2 ON b.unbound_by = u2.id
                WHERE b.ip_master_id = ?
                ORDER BY b.configured_at DESC";
        
        return $this->db->getResults($sql, [$ipMasterId], 'i');
    }
}
