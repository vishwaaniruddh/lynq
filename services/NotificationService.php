<?php
/**
 * ADV Clarity Management System - Notification Service
 * Handles push notification subscriptions and sending
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/BaseModel.php';

class NotificationService {
    private $db;
    
    public function __construct() {
        $this->db = DatabaseConfig::getInstance()->getConnection();
    }
    
    /**
     * Save push notification subscription
     */
    public function saveSubscription($userId, $companyId, $endpoint, $p256dh, $auth) {
        try {
            // Check if subscription already exists
            $stmt = $this->db->prepare("
                SELECT id FROM push_subscriptions 
                WHERE user_id = ? AND endpoint = ?
            ");
            $stmt->bind_param("is", $userId, $endpoint);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                // Update existing subscription
                $row = $result->fetch_assoc();
                $subscriptionId = $row['id'];
                
                $updateStmt = $this->db->prepare("
                    UPDATE push_subscriptions 
                    SET p256dh_key = ?, auth_key = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $updateStmt->bind_param("ssi", $p256dh, $auth, $subscriptionId);
                $updateStmt->execute();
                
            } else {
                // Create new subscription
                $insertStmt = $this->db->prepare("
                    INSERT INTO push_subscriptions 
                    (user_id, company_id, endpoint, p256dh_key, auth_key, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, NOW(), NOW())
                ");
                $insertStmt->bind_param("iisss", $userId, $companyId, $endpoint, $p256dh, $auth);
                $insertStmt->execute();
                $subscriptionId = $this->db->insert_id;
            }
            
            return $subscriptionId;
            
        } catch (Exception $e) {
            error_log("Failed to save push subscription: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Remove push notification subscription
     */
    public function removeSubscription($userId, $endpoint) {
        try {
            $stmt = $this->db->prepare("
                DELETE FROM push_subscriptions 
                WHERE user_id = ? AND endpoint = ?
            ");
            $stmt->bind_param("is", $userId, $endpoint);
            $stmt->execute();
            
            return $stmt->affected_rows > 0;
            
        } catch (Exception $e) {
            error_log("Failed to remove push subscription: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Get user's push subscriptions
     */
    public function getUserSubscriptions($userId) {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM push_subscriptions 
                WHERE user_id = ? AND active = 1
                ORDER BY created_at DESC
            ");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            return $result->fetch_all(MYSQLI_ASSOC);
            
        } catch (Exception $e) {
            error_log("Failed to get user subscriptions: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Send push notification to user
     */
    public function sendNotificationToUser($userId, $title, $body, $data = []) {
        try {
            $subscriptions = $this->getUserSubscriptions($userId);
            
            if (empty($subscriptions)) {
                return false;
            }
            
            $payload = json_encode([
                'title' => $title,
                'body' => $body,
                'icon' => '/assets/icons/icon-192.png',
                'badge' => '/assets/icons/icon-72.png',
                'data' => $data,
                'tag' => $data['tag'] ?? 'default',
                'requireInteraction' => $data['requireInteraction'] ?? false
            ]);
            
            $sent = 0;
            foreach ($subscriptions as $subscription) {
                if ($this->sendPushMessage($subscription, $payload)) {
                    $sent++;
                }
            }
            
            return $sent > 0;
            
        } catch (Exception $e) {
            error_log("Failed to send notification to user: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Send push message to specific subscription
     */
    private function sendPushMessage($subscription, $payload) {
        // In production, use web-push library or similar
        // This is a placeholder implementation
        
        try {
            // Log the notification for debugging
            error_log("Push notification sent to user {$subscription['user_id']}: $payload");
            
            // Update last_sent timestamp
            $stmt = $this->db->prepare("
                UPDATE push_subscriptions 
                SET last_sent = NOW() 
                WHERE id = ?
            ");
            $stmt->bind_param("i", $subscription['id']);
            $stmt->execute();
            
            return true;
            
        } catch (Exception $e) {
            error_log("Failed to send push message: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send notification to multiple users
     */
    public function sendNotificationToUsers($userIds, $title, $body, $data = []) {
        $sent = 0;
        
        foreach ($userIds as $userId) {
            if ($this->sendNotificationToUser($userId, $title, $body, $data)) {
                $sent++;
            }
        }
        
        return $sent;
    }
    
    /**
     * Send notification to company users
     */
    public function sendNotificationToCompany($companyId, $title, $body, $data = []) {
        try {
            $stmt = $this->db->prepare("
                SELECT DISTINCT user_id FROM push_subscriptions 
                WHERE company_id = ? AND active = 1
            ");
            $stmt->bind_param("i", $companyId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $userIds = [];
            while ($row = $result->fetch_assoc()) {
                $userIds[] = $row['user_id'];
            }
            
            return $this->sendNotificationToUsers($userIds, $title, $body, $data);
            
        } catch (Exception $e) {
            error_log("Failed to send notification to company: " . $e->getMessage());
            throw $e;
        }
    }
}
?>