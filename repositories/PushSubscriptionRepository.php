<?php

require_once __DIR__ . '/BaseRepository.php';

/**
 * Push Subscription Repository
 * Handles database operations for push notification subscriptions
 */
class PushSubscriptionRepository extends BaseRepository {
    
    protected $table = 'push_subscriptions';
    
    /**
     * Get company ID for current user (simplified for testing)
     */
    private function getCompanyId() {
        // For testing purposes, return a default company ID
        // In production, this should get the company ID from the current user session
        return $_SESSION['company_id'] ?? 1;
    }
    
    /**
     * Save push notification subscription
     */
    public function saveSubscription($userId, $subscription) {
        try {
            // Check if subscription already exists
            $existing = $this->getSubscriptionByEndpoint($subscription['endpoint']);
            
            if ($existing) {
                // Update existing subscription
                $sql = "UPDATE {$this->table} SET 
                        user_id = ?, 
                        p256dh_key = ?, 
                        auth_key = ?, 
                        updated_at = NOW() 
                        WHERE endpoint = ? AND company_id = ?";
                
                $stmt = $this->db->executeQuery($sql, [
                    $userId,
                    $subscription['keys']['p256dh'],
                    $subscription['keys']['auth'],
                    $subscription['endpoint'],
                    $this->getCompanyId()
                ], 'isssi');
                
                $stmt->close();
                return true;
            } else {
                // Create new subscription
                $sql = "INSERT INTO {$this->table} 
                        (user_id, endpoint, p256dh_key, auth_key, company_id, created_at, updated_at) 
                        VALUES (?, ?, ?, ?, ?, NOW(), NOW())";
                
                $stmt = $this->db->executeQuery($sql, [
                    $userId,
                    $subscription['endpoint'],
                    $subscription['keys']['p256dh'],
                    $subscription['keys']['auth'],
                    $this->getCompanyId()
                ], 'isssi');
                
                $stmt->close();
                return true;
            }
        } catch (Exception $e) {
            error_log("Save subscription error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Remove push notification subscription
     */
    public function removeSubscription($userId, $endpoint = null) {
        try {
            if ($endpoint) {
                // Remove specific subscription by endpoint
                $sql = "DELETE FROM {$this->table} 
                        WHERE user_id = ? AND endpoint = ? AND company_id = ?";
                $stmt = $this->db->executeQuery($sql, [$userId, $endpoint, $this->getCompanyId()], 'isi');
                $stmt->close();
                return true;
            } else {
                // Remove all subscriptions for user
                $sql = "DELETE FROM {$this->table} 
                        WHERE user_id = ? AND company_id = ?";
                $stmt = $this->db->executeQuery($sql, [$userId, $this->getCompanyId()], 'ii');
                $stmt->close();
                return true;
            }
        } catch (Exception $e) {
            error_log("Remove subscription error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get user's subscription
     */
    public function getSubscription($userId) {
        try {
            $sql = "SELECT * FROM {$this->table} 
                    WHERE user_id = ? AND company_id = ? 
                    ORDER BY updated_at DESC LIMIT 1";
            
            $results = $this->db->getResults($sql, [$userId, $this->getCompanyId()], 'ii');
            
            if (!empty($results)) {
                $row = $results[0];
                return [
                    'endpoint' => $row['endpoint'],
                    'keys' => [
                        'p256dh' => $row['p256dh_key'],
                        'auth' => $row['auth_key']
                    ]
                ];
            }
            
            return null;
        } catch (Exception $e) {
            error_log("Get subscription error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get subscription by endpoint
     */
    public function getSubscriptionByEndpoint($endpoint) {
        try {
            $sql = "SELECT * FROM {$this->table} 
                    WHERE endpoint = ? AND company_id = ?";
            
            $results = $this->db->getResults($sql, [$endpoint, $this->getCompanyId()], 'si');
            
            return !empty($results) ? $results[0] : null;
        } catch (Exception $e) {
            error_log("Get subscription by endpoint error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get all subscriptions
     */
    public function getAllSubscriptions() {
        try {
            $sql = "SELECT * FROM {$this->table} 
                    WHERE company_id = ? 
                    ORDER BY updated_at DESC";
            
            $results = $this->db->getResults($sql, [$this->getCompanyId()], 'i');
            
            $subscriptions = [];
            foreach ($results as $row) {
                $subscriptions[] = [
                    'endpoint' => $row['endpoint'],
                    'keys' => [
                        'p256dh' => $row['p256dh_key'],
                        'auth' => $row['auth_key']
                    ],
                    'user_id' => $row['user_id']
                ];
            }
            
            return $subscriptions;
        } catch (Exception $e) {
            error_log("Get all subscriptions error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get subscriptions for specific users
     */
    public function getSubscriptionsForUsers($userIds) {
        try {
            if (empty($userIds)) {
                return [];
            }
            
            $placeholders = str_repeat('?,', count($userIds) - 1) . '?';
            $sql = "SELECT * FROM {$this->table} 
                    WHERE user_id IN ($placeholders) AND company_id = ? 
                    ORDER BY updated_at DESC";
            
            $params = array_merge($userIds, [$this->getCompanyId()]);
            $types = str_repeat('i', count($userIds)) . 'i';
            
            $results = $this->db->getResults($sql, $params, $types);
            
            $subscriptions = [];
            foreach ($results as $row) {
                $subscriptions[] = [
                    'endpoint' => $row['endpoint'],
                    'keys' => [
                        'p256dh' => $row['p256dh_key'],
                        'auth' => $row['auth_key']
                    ],
                    'user_id' => $row['user_id']
                ];
            }
            
            return $subscriptions;
        } catch (Exception $e) {
            error_log("Get subscriptions for users error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Remove invalid subscription
     */
    public function removeInvalidSubscription($endpoint) {
        try {
            $sql = "DELETE FROM {$this->table} WHERE endpoint = ?";
            $stmt = $this->db->executeQuery($sql, [$endpoint], 's');
            $stmt->close();
            return true;
        } catch (Exception $e) {
            error_log("Remove invalid subscription error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get subscription count for user
     */
    public function getSubscriptionCount($userId) {
        try {
            $sql = "SELECT COUNT(*) as count FROM {$this->table} 
                    WHERE user_id = ? AND company_id = ?";
            
            $results = $this->db->getResults($sql, [$userId, $this->getCompanyId()], 'ii');
            
            return (int) ($results[0]['count'] ?? 0);
        } catch (Exception $e) {
            error_log("Get subscription count error: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Clean up old subscriptions
     */
    public function cleanupOldSubscriptions($days = 30) {
        try {
            $sql = "DELETE FROM {$this->table} 
                    WHERE updated_at < DATE_SUB(NOW(), INTERVAL ? DAY)";
            
            $stmt = $this->db->executeQuery($sql, [$days], 'i');
            $stmt->close();
            return true;
        } catch (Exception $e) {
            error_log("Cleanup old subscriptions error: " . $e->getMessage());
            return false;
        }
    }
}