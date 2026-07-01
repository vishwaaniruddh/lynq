<?php
/**
 * ADV Clarity Management System - Sync Service
 * Handles offline data synchronization and conflict resolution
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/BaseModel.php';

class SyncService {
    private $db;
    
    public function __construct() {
        $this->db = DatabaseConfig::getInstance()->getConnection();
    }
    
    /**
     * Get queue status for user
     */
    public function getQueueStatus($userId, $companyId) {
        try {
            // Get pending sync operations
            $stmt = $this->db->prepare("
                SELECT 
                    COUNT(*) as total_pending,
                    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_count,
                    SUM(CASE WHEN status = 'conflict' THEN 1 ELSE 0 END) as conflict_count,
                    MIN(created_at) as oldest_pending,
                    MAX(updated_at) as last_activity
                FROM sync_queue 
                WHERE user_id = ? AND company_id = ? AND status IN ('pending', 'failed', 'conflict')
            ");
            $stmt->bind_param("ii", $userId, $companyId);
            $stmt->execute();
            $result = $stmt->get_result();
            $status = $result->fetch_assoc();
            
            // Get recent conflicts
            $conflictStmt = $this->db->prepare("
                SELECT id, entity_type, entity_id, conflict_data, created_at
                FROM sync_queue 
                WHERE user_id = ? AND company_id = ? AND status = 'conflict'
                ORDER BY created_at DESC
                LIMIT 10
            ");
            $conflictStmt->bind_param("ii", $userId, $companyId);
            $conflictStmt->execute();
            $conflictResult = $conflictStmt->get_result();
            $conflicts = $conflictResult->fetch_all(MYSQLI_ASSOC);
            
            return [
                'totalPending' => (int)$status['total_pending'],
                'failedCount' => (int)$status['failed_count'],
                'conflictCount' => (int)$status['conflict_count'],
                'oldestPending' => $status['oldest_pending'],
                'lastActivity' => $status['last_activity'],
                'recentConflicts' => $conflicts
            ];
            
        } catch (Exception $e) {
            error_log("Failed to get queue status: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Process queued actions from client
     */
    public function processQueuedActions($userId, $companyId, $actions) {
        $successful = [];
        $failed = [];
        $conflicts = [];
        
        foreach ($actions as $action) {
            try {
                $result = $this->processAction($userId, $companyId, $action);
                
                if ($result['status'] === 'success') {
                    $successful[] = [
                        'id' => $action['id'],
                        'result' => $result['data']
                    ];
                } elseif ($result['status'] === 'conflict') {
                    $conflicts[] = [
                        'id' => $action['id'],
                        'conflictId' => $result['conflictId'],
                        'conflictData' => $result['conflictData']
                    ];
                } else {
                    $failed[] = [
                        'id' => $action['id'],
                        'error' => $result['error']
                    ];
                }
                
            } catch (Exception $e) {
                $failed[] = [
                    'id' => $action['id'],
                    'error' => $e->getMessage()
                ];
            }
        }
        
        return [
            'successful' => $successful,
            'failed' => $failed,
            'conflicts' => $conflicts
        ];
    }
    
    /**
     * Process a single action
     */
    private function processAction($userId, $companyId, $action) {
        // Validate action structure
        if (!isset($action['endpoint']) || !isset($action['method']) || !isset($action['data'])) {
            return ['status' => 'error', 'error' => 'Invalid action structure'];
        }
        
        // Parse endpoint to determine entity type and operation
        $entityInfo = $this->parseEndpoint($action['endpoint']);
        
        if (!$entityInfo) {
            return ['status' => 'error', 'error' => 'Unsupported endpoint'];
        }
        
        // Check for conflicts before processing
        $conflict = $this->checkForConflicts($userId, $companyId, $entityInfo, $action['data']);
        
        if ($conflict) {
            // Store conflict for resolution
            $conflictId = $this->storeConflict($userId, $companyId, $entityInfo, $action, $conflict);
            return [
                'status' => 'conflict',
                'conflictId' => $conflictId,
                'conflictData' => $conflict
            ];
        }
        
        // Process the action
        try {
            $result = $this->executeAction($userId, $companyId, $entityInfo, $action);
            return ['status' => 'success', 'data' => $result];
            
        } catch (Exception $e) {
            return ['status' => 'error', 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Parse endpoint to determine entity type and operation
     */
    private function parseEndpoint($endpoint) {
        // Extract path from full URL
        $path = parse_url($endpoint, PHP_URL_PATH);
        
        // Common API patterns
        $patterns = [
            '/api\/inventory\/dispatch/' => ['type' => 'dispatch', 'module' => 'inventory'],
            '/api\/inventory\/receive/' => ['type' => 'receive', 'module' => 'inventory'],
            '/api\/sites\/update/' => ['type' => 'site', 'module' => 'sites'],
            '/api\/feasibility\/update/' => ['type' => 'feasibility', 'module' => 'feasibility'],
            '/api\/assets\/update/' => ['type' => 'asset', 'module' => 'inventory']
        ];
        
        foreach ($patterns as $pattern => $info) {
            if (preg_match($pattern, $path)) {
                return $info;
            }
        }
        
        return null;
    }
    
    /**
     * Check for data conflicts
     */
    private function checkForConflicts($userId, $companyId, $entityInfo, $data) {
        // Skip conflict checking for new records
        if (!isset($data['id'])) {
            return null;
        }
        
        $entityId = $data['id'];
        $entityType = $entityInfo['type'];
        
        // Get current server version
        $serverData = $this->getServerData($entityType, $entityId, $companyId);
        
        if (!$serverData) {
            return null; // Entity doesn't exist on server
        }
        
        // Check if client version matches server version
        $clientTimestamp = $data['updated_at'] ?? $data['timestamp'] ?? null;
        $serverTimestamp = $serverData['updated_at'];
        
        if ($clientTimestamp && $serverTimestamp && $clientTimestamp < $serverTimestamp) {
            return [
                'type' => 'version_conflict',
                'clientData' => $data,
                'serverData' => $serverData,
                'conflictFields' => $this->identifyConflictFields($data, $serverData)
            ];
        }
        
        return null;
    }
    
    /**
     * Get current server data for entity
     */
    private function getServerData($entityType, $entityId, $companyId) {
        $tables = [
            'dispatch' => 'inventory_dispatches',
            'receive' => 'inventory_receives',
            'site' => 'sites',
            'feasibility' => 'feasibility_studies',
            'asset' => 'assets'
        ];
        
        $table = $tables[$entityType] ?? null;
        if (!$table) {
            return null;
        }
        
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM {$table} 
                WHERE id = ? AND company_id = ?
            ");
            $stmt->bind_param("ii", $entityId, $companyId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            return $result->fetch_assoc();
            
        } catch (Exception $e) {
            error_log("Failed to get server data: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Identify conflicting fields between client and server data
     */
    private function identifyConflictFields($clientData, $serverData) {
        $conflicts = [];
        
        foreach ($clientData as $field => $clientValue) {
            if (isset($serverData[$field]) && $serverData[$field] != $clientValue) {
                $conflicts[] = [
                    'field' => $field,
                    'clientValue' => $clientValue,
                    'serverValue' => $serverData[$field]
                ];
            }
        }
        
        return $conflicts;
    }
    
    /**
     * Store conflict for later resolution
     */
    private function storeConflict($userId, $companyId, $entityInfo, $action, $conflict) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO sync_queue 
                (user_id, company_id, entity_type, entity_id, action_data, conflict_data, status, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, 'conflict', NOW(), NOW())
            ");
            
            $entityId = $action['data']['id'] ?? null;
            $actionJson = json_encode($action);
            $conflictJson = json_encode($conflict);
            
            $stmt->bind_param("iisiss", 
                $userId, $companyId, $entityInfo['type'], $entityId, $actionJson, $conflictJson
            );
            $stmt->execute();
            
            return $this->db->insert_id;
            
        } catch (Exception $e) {
            error_log("Failed to store conflict: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Execute action after conflict resolution
     */
    private function executeAction($userId, $companyId, $entityInfo, $action) {
        // This would integrate with existing service classes
        // For now, return a placeholder response
        
        return [
            'id' => $action['data']['id'] ?? null,
            'message' => 'Action processed successfully',
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * Resolve a conflict
     */
    public function resolveConflict($userId, $companyId, $conflictId, $resolution, $mergeData = null) {
        try {
            // Get conflict details
            $stmt = $this->db->prepare("
                SELECT * FROM sync_queue 
                WHERE id = ? AND user_id = ? AND company_id = ? AND status = 'conflict'
            ");
            $stmt->bind_param("iii", $conflictId, $userId, $companyId);
            $stmt->execute();
            $result = $stmt->get_result();
            $conflict = $result->fetch_assoc();
            
            if (!$conflict) {
                return false;
            }
            
            $actionData = json_decode($conflict['action_data'], true);
            $conflictData = json_decode($conflict['conflict_data'], true);
            
            // Apply resolution strategy
            switch ($resolution) {
                case 'client':
                    // Use client data
                    $finalData = $actionData['data'];
                    break;
                    
                case 'server':
                    // Use server data (no action needed)
                    $finalData = $conflictData['serverData'];
                    break;
                    
                case 'merge':
                    // Use merged data provided by client
                    $finalData = $mergeData ?: $actionData['data'];
                    break;
                    
                default:
                    return false;
            }
            
            // Execute the resolved action
            $entityInfo = ['type' => $conflict['entity_type']];
            $resolvedAction = $actionData;
            $resolvedAction['data'] = $finalData;
            
            $result = $this->executeAction($userId, $companyId, $entityInfo, $resolvedAction);
            
            // Mark conflict as resolved
            $updateStmt = $this->db->prepare("
                UPDATE sync_queue 
                SET status = 'resolved', resolution = ?, resolved_at = NOW(), updated_at = NOW()
                WHERE id = ?
            ");
            $updateStmt->bind_param("si", $resolution, $conflictId);
            $updateStmt->execute();
            
            return true;
            
        } catch (Exception $e) {
            error_log("Failed to resolve conflict: " . $e->getMessage());
            throw $e;
        }
    }
}
?>