<?php
/**
 * Settings Audit Repository
 * Provides data access for settings audit trail with filtering and export capabilities
 */

require_once __DIR__ . '/BaseRepository.php';

class SettingsAuditRepository extends BaseRepository {
    protected $table = 'settings_audit';
    protected $companyIdColumn = null; // Audit logs are global
    protected $applyCompanyFilter = false; // Disable company filtering for audit logs
    
    /**
     * Get audit trail for a specific setting with filtering options
     */
    public function getAuditTrail($settingId, $filters = [], $limit = 50, $offset = 0) {
        $sql = "SELECT sa.*, ss.setting_key, ss.category, u.username, u.first_name, u.last_name
                FROM `{$this->table}` sa
                LEFT JOIN system_settings ss ON sa.setting_id = ss.id
                LEFT JOIN users u ON sa.user_id = u.id
                WHERE sa.setting_id = ?";
        
        $params = [$settingId];
        $types = 'i';
        
        // Apply filters
        if (!empty($filters['start_date'])) {
            $sql .= " AND sa.timestamp >= ?";
            $params[] = $filters['start_date'];
            $types .= 's';
        }
        
        if (!empty($filters['end_date'])) {
            $sql .= " AND sa.timestamp <= ?";
            $params[] = $filters['end_date'];
            $types .= 's';
        }
        
        if (!empty($filters['user_id'])) {
            $sql .= " AND sa.user_id = ?";
            $params[] = $filters['user_id'];
            $types .= 'i';
        }
        
        if (!empty($filters['action'])) {
            $sql .= " AND sa.action = ?";
            $params[] = $filters['action'];
            $types .= 's';
        }
        
        if (!empty($filters['ip_address'])) {
            $sql .= " AND sa.ip_address = ?";
            $params[] = $filters['ip_address'];
            $types .= 's';
        }
        
        if (!empty($filters['session_id'])) {
            $sql .= " AND sa.session_id = ?";
            $params[] = $filters['session_id'];
            $types .= 's';
        }
        
        // Chronological ordering (most recent first)
        $sql .= " ORDER BY sa.timestamp DESC";
        
        // Add pagination
        if ($limit > 0) {
            $sql .= " LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;
            $types .= 'ii';
        }
        
        return $this->db->getResults($sql, $params, $types);
    }
    
    /**
     * Get audit trail for all settings with filtering options
     */
    public function getAllAuditTrail($filters = [], $limit = 100, $offset = 0) {
        $sql = "SELECT sa.*, ss.setting_key, ss.category, u.username, u.first_name, u.last_name
                FROM `{$this->table}` sa
                LEFT JOIN system_settings ss ON sa.setting_id = ss.id
                LEFT JOIN users u ON sa.user_id = u.id
                WHERE 1=1";
        
        $params = [];
        $types = '';
        
        // Apply filters
        if (!empty($filters['start_date'])) {
            $sql .= " AND sa.timestamp >= ?";
            $params[] = $filters['start_date'];
            $types .= 's';
        }
        
        if (!empty($filters['end_date'])) {
            $sql .= " AND sa.timestamp <= ?";
            $params[] = $filters['end_date'];
            $types .= 's';
        }
        
        if (!empty($filters['user_id'])) {
            $sql .= " AND sa.user_id = ?";
            $params[] = $filters['user_id'];
            $types .= 'i';
        }
        
        if (!empty($filters['category'])) {
            $sql .= " AND ss.category = ?";
            $params[] = $filters['category'];
            $types .= 's';
        }
        
        if (!empty($filters['action'])) {
            $sql .= " AND sa.action = ?";
            $params[] = $filters['action'];
            $types .= 's';
        }
        
        if (!empty($filters['setting_key'])) {
            $sql .= " AND ss.setting_key = ?";
            $params[] = $filters['setting_key'];
            $types .= 's';
        }
        
        if (!empty($filters['ip_address'])) {
            $sql .= " AND sa.ip_address = ?";
            $params[] = $filters['ip_address'];
            $types .= 's';
        }
        
        if (!empty($filters['session_id'])) {
            $sql .= " AND sa.session_id = ?";
            $params[] = $filters['session_id'];
            $types .= 's';
        }
        
        // Chronological ordering (most recent first)
        $sql .= " ORDER BY sa.timestamp DESC";
        
        // Add pagination
        if ($limit > 0) {
            $sql .= " LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;
            $types .= 'ii';
        }
        
        return $this->db->getResults($sql, $params, $types);
    }
    
    /**
     * Get audit trail count for pagination
     */
    public function getAuditTrailCount($settingId = null, $filters = []) {
        if ($settingId) {
            $sql = "SELECT COUNT(*) as count FROM `{$this->table}` sa
                    LEFT JOIN system_settings ss ON sa.setting_id = ss.id
                    WHERE sa.setting_id = ?";
            $params = [$settingId];
            $types = 'i';
        } else {
            $sql = "SELECT COUNT(*) as count FROM `{$this->table}` sa
                    LEFT JOIN system_settings ss ON sa.setting_id = ss.id
                    WHERE 1=1";
            $params = [];
            $types = '';
        }
        
        // Apply filters
        if (!empty($filters['start_date'])) {
            $sql .= " AND sa.timestamp >= ?";
            $params[] = $filters['start_date'];
            $types .= 's';
        }
        
        if (!empty($filters['end_date'])) {
            $sql .= " AND sa.timestamp <= ?";
            $params[] = $filters['end_date'];
            $types .= 's';
        }
        
        if (!empty($filters['user_id'])) {
            $sql .= " AND sa.user_id = ?";
            $params[] = $filters['user_id'];
            $types .= 'i';
        }
        
        if (!empty($filters['category'])) {
            $sql .= " AND ss.category = ?";
            $params[] = $filters['category'];
            $types .= 's';
        }
        
        if (!empty($filters['action'])) {
            $sql .= " AND sa.action = ?";
            $params[] = $filters['action'];
            $types .= 's';
        }
        
        if (!empty($filters['setting_key'])) {
            $sql .= " AND ss.setting_key = ?";
            $params[] = $filters['setting_key'];
            $types .= 's';
        }
        
        if (!empty($filters['ip_address'])) {
            $sql .= " AND sa.ip_address = ?";
            $params[] = $filters['ip_address'];
            $types .= 's';
        }
        
        if (!empty($filters['session_id'])) {
            $sql .= " AND sa.session_id = ?";
            $params[] = $filters['session_id'];
            $types .= 's';
        }
        
        $result = $this->db->getResults($sql, $params, $types);
        return $result[0]['count'] ?? 0;
    }
    
    /**
     * Export audit data in structured format
     */
    public function exportAuditData($format = 'json', $filters = []) {
        // Get all audit data without pagination for export
        $auditData = $this->getAllAuditTrail($filters, 0, 0); // 0 limit means no limit
        
        switch (strtolower($format)) {
            case 'csv':
                return $this->exportToCsv($auditData);
            case 'json':
            default:
                return $this->exportToJson($auditData);
        }
    }
    
    /**
     * Export audit data to JSON format
     */
    private function exportToJson($auditData) {
        $exportData = [
            'export_timestamp' => date('Y-m-d H:i:s'),
            'total_records' => count($auditData),
            'audit_entries' => []
        ];
        
        foreach ($auditData as $entry) {
            $exportData['audit_entries'][] = [
                'id' => $entry['id'],
                'setting_key' => $entry['setting_key'],
                'category' => $entry['category'],
                'old_value' => $entry['old_value'],
                'new_value' => $entry['new_value'],
                'action' => $entry['action'],
                'timestamp' => $entry['timestamp'],
                'user' => [
                    'id' => $entry['user_id'],
                    'username' => $entry['username'],
                    'name' => trim($entry['first_name'] . ' ' . $entry['last_name'])
                ],
                'ip_address' => $entry['ip_address'],
                'user_agent' => $entry['user_agent'],
                'session_id' => $entry['session_id'] ?? null,
                'request_method' => $entry['request_method'] ?? null,
                'request_uri' => $entry['request_uri'] ?? null,
                'integrity_hash' => $entry['integrity_hash'] ?? null,
                'integrity_status' => !empty($entry['integrity_hash']) ? 'PROTECTED' : 'LEGACY'
            ];
        }
        
        return json_encode($exportData, JSON_PRETTY_PRINT);
    }
    
    /**
     * Export audit data to CSV format
     */
    private function exportToCsv($auditData) {
        $csvData = [];
        
        // CSV headers
        $csvData[] = [
            'ID',
            'Setting Key',
            'Category',
            'Old Value',
            'New Value',
            'Action',
            'Timestamp',
            'User ID',
            'Username',
            'User Name',
            'IP Address',
            'User Agent',
            'Session ID',
            'Request Method',
            'Request URI',
            'Integrity Hash',
            'Integrity Status'
        ];
        
        // CSV data rows
        foreach ($auditData as $entry) {
            $csvData[] = [
                $entry['id'],
                $entry['setting_key'],
                $entry['category'],
                $entry['old_value'],
                $entry['new_value'],
                $entry['action'],
                $entry['timestamp'],
                $entry['user_id'],
                $entry['username'],
                trim($entry['first_name'] . ' ' . $entry['last_name']),
                $entry['ip_address'],
                $entry['user_agent'],
                $entry['session_id'] ?? '',
                $entry['request_method'] ?? '',
                $entry['request_uri'] ?? '',
                $entry['integrity_hash'] ?? '',
                !empty($entry['integrity_hash']) ? 'PROTECTED' : 'LEGACY'
            ];
        }
        
        // Convert to CSV string
        $output = '';
        foreach ($csvData as $row) {
            $output .= '"' . implode('","', array_map('str_replace', array_fill(0, count($row), '"'), array_fill(0, count($row), '""'), $row)) . '"' . "\n";
        }
        
        return $output;
    }
    
    /**
     * Get audit statistics
     */
    public function getAuditStatistics($filters = []) {
        $sql = "SELECT 
                    COUNT(*) as total_changes,
                    COUNT(DISTINCT sa.setting_id) as settings_modified,
                    COUNT(DISTINCT sa.user_id) as users_involved,
                    sa.action,
                    COUNT(*) as action_count
                FROM `{$this->table}` sa
                LEFT JOIN system_settings ss ON sa.setting_id = ss.id
                WHERE 1=1";
        
        $params = [];
        $types = '';
        
        // Apply filters
        if (!empty($filters['start_date'])) {
            $sql .= " AND sa.timestamp >= ?";
            $params[] = $filters['start_date'];
            $types .= 's';
        }
        
        if (!empty($filters['end_date'])) {
            $sql .= " AND sa.timestamp <= ?";
            $params[] = $filters['end_date'];
            $types .= 's';
        }
        
        if (!empty($filters['category'])) {
            $sql .= " AND ss.category = ?";
            $params[] = $filters['category'];
            $types .= 's';
        }
        
        $sql .= " GROUP BY sa.action";
        
        return $this->db->getResults($sql, $params, $types);
    }
    
    /**
     * Get recent audit activity
     */
    public function getRecentActivity($limit = 10) {
        $sql = "SELECT sa.*, ss.setting_key, ss.category, u.username, u.first_name, u.last_name
                FROM `{$this->table}` sa
                LEFT JOIN system_settings ss ON sa.setting_id = ss.id
                LEFT JOIN users u ON sa.user_id = u.id
                ORDER BY sa.timestamp DESC
                LIMIT ?";
        
        return $this->db->getResults($sql, [$limit], 'i');
    }
    
    /**
     * Verify integrity of audit entries
     */
    public function verifyAuditIntegrity($auditIds = []) {
        $settingsAuditModel = new SettingsAudit();
        
        if (empty($auditIds)) {
            // Verify all entries if no specific IDs provided
            $sql = "SELECT * FROM `{$this->table}` ORDER BY timestamp DESC LIMIT 1000";
            $auditEntries = $this->db->getResults($sql);
        } else {
            // Verify specific entries
            $placeholders = str_repeat('?,', count($auditIds) - 1) . '?';
            $sql = "SELECT * FROM `{$this->table}` WHERE id IN ({$placeholders})";
            $types = str_repeat('i', count($auditIds));
            $auditEntries = $this->db->getResults($sql, $auditIds, $types);
        }
        
        return $settingsAuditModel->batchVerifyIntegrity($auditEntries);
    }
    
    /**
     * Get comprehensive audit report with integrity status
     */
    public function getAuditIntegrityReport($filters = []) {
        $auditData = $this->getAllAuditTrail($filters, 0, 0); // Get all matching entries
        $settingsAuditModel = new SettingsAudit();
        
        $integrityResults = $settingsAuditModel->batchVerifyIntegrity($auditData);
        
        return [
            'total_entries' => count($auditData),
            'integrity_status' => $integrityResults,
            'entries_with_issues' => array_filter($auditData, function($entry) use ($integrityResults) {
                return !$integrityResults['details'][$entry['id']]['valid'];
            }),
            'report_timestamp' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * Get audit trail with enhanced metadata for forensic analysis
     */
    public function getForensicAuditTrail($filters = [], $limit = 100, $offset = 0) {
        $sql = "SELECT sa.*, ss.setting_key, ss.category, ss.description,
                       u.username, u.first_name, u.last_name, u.email,
                       CASE 
                           WHEN sa.integrity_hash IS NOT NULL THEN 'PROTECTED'
                           ELSE 'LEGACY'
                       END as integrity_status
                FROM `{$this->table}` sa
                LEFT JOIN system_settings ss ON sa.setting_id = ss.id
                LEFT JOIN users u ON sa.user_id = u.id
                WHERE 1=1";
        
        $params = [];
        $types = '';
        
        // Apply all standard filters
        if (!empty($filters['start_date'])) {
            $sql .= " AND sa.timestamp >= ?";
            $params[] = $filters['start_date'];
            $types .= 's';
        }
        
        if (!empty($filters['end_date'])) {
            $sql .= " AND sa.timestamp <= ?";
            $params[] = $filters['end_date'];
            $types .= 's';
        }
        
        if (!empty($filters['user_id'])) {
            $sql .= " AND sa.user_id = ?";
            $params[] = $filters['user_id'];
            $types .= 'i';
        }
        
        if (!empty($filters['category'])) {
            $sql .= " AND ss.category = ?";
            $params[] = $filters['category'];
            $types .= 's';
        }
        
        if (!empty($filters['action'])) {
            $sql .= " AND sa.action = ?";
            $params[] = $filters['action'];
            $types .= 's';
        }
        
        if (!empty($filters['setting_key'])) {
            $sql .= " AND ss.setting_key = ?";
            $params[] = $filters['setting_key'];
            $types .= 's';
        }
        
        if (!empty($filters['ip_address'])) {
            $sql .= " AND sa.ip_address = ?";
            $params[] = $filters['ip_address'];
            $types .= 's';
        }
        
        if (!empty($filters['session_id'])) {
            $sql .= " AND sa.session_id = ?";
            $params[] = $filters['session_id'];
            $types .= 's';
        }
        
        // Filter by integrity status if requested
        if (!empty($filters['integrity_status'])) {
            if ($filters['integrity_status'] === 'PROTECTED') {
                $sql .= " AND sa.integrity_hash IS NOT NULL";
            } elseif ($filters['integrity_status'] === 'LEGACY') {
                $sql .= " AND sa.integrity_hash IS NULL";
            }
        }
        
        // Chronological ordering (most recent first)
        $sql .= " ORDER BY sa.timestamp DESC";
        
        // Add pagination
        if ($limit > 0) {
            $sql .= " LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;
            $types .= 'ii';
        }
        
        return $this->db->getResults($sql, $params, $types);
    }
}