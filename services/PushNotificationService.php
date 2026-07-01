<?php

/**
 * Push Notification Service
 * Handles push notification subscriptions and sending notifications
 */

require_once __DIR__ . '/../repositories/PushSubscriptionRepository.php';

class PushNotificationService {
    private $subscriptionRepository;
    private $vapidPublicKey;
    private $vapidPrivateKey;
    private $vapidSubject;
    
    public function __construct() {
        $this->subscriptionRepository = new PushSubscriptionRepository();
        
        // Load VAPID keys from environment or config
        $this->vapidPublicKey = $_ENV['VAPID_PUBLIC_KEY'] ?? 'BEl62iUYgUivxIkv69yViEuiBIa40HI0DLLuxN-RgKBli2wlOTat6KWuDx-cFHrdc4xJqRoAXvfTNZjCEkDDHkI';
        $this->vapidPrivateKey = $_ENV['VAPID_PRIVATE_KEY'] ?? 'UUxI4O8-FbRouAevSmBQ6o18hgE4nSG3qwvJTfKc-ls';
        $this->vapidSubject = $_ENV['VAPID_SUBJECT'] ?? 'mailto:admin@advclarity.com';
    }
    
    /**
     * Subscribe user to push notifications
     */
    public function subscribe($userId, $subscription) {
        try {
            return $this->subscriptionRepository->saveSubscription($userId, $subscription);
        } catch (Exception $e) {
            error_log("Push subscription error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Unsubscribe user from push notifications
     */
    public function unsubscribe($userId, $endpoint = null) {
        try {
            return $this->subscriptionRepository->removeSubscription($userId, $endpoint);
        } catch (Exception $e) {
            error_log("Push unsubscription error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get user's subscription
     */
    public function getSubscription($userId) {
        try {
            return $this->subscriptionRepository->getSubscription($userId);
        } catch (Exception $e) {
            error_log("Get subscription error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get VAPID public key
     */
    public function getVapidPublicKey() {
        return $this->vapidPublicKey;
    }
    
    /**
     * Send push notification
     */
    public function sendNotification($notification, $recipients = 'all') {
        $sent = 0;
        $failed = 0;
        
        try {
            // Get subscriptions based on recipients
            $subscriptions = $this->getSubscriptionsForRecipients($recipients);
            
            foreach ($subscriptions as $subscription) {
                if ($this->sendToSubscription($subscription, $notification)) {
                    $sent++;
                } else {
                    $failed++;
                }
            }
            
            return [
                'success' => true,
                'sent' => $sent,
                'failed' => $failed
            ];
            
        } catch (Exception $e) {
            error_log("Send notification error: " . $e->getMessage());
            return [
                'success' => false,
                'sent' => $sent,
                'failed' => $failed,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Send notification to specific subscription
     */
    private function sendToSubscription($subscription, $notification) {
        try {
            // Prepare the payload
            $payload = json_encode($notification);
            
            // Create JWT token for authentication
            $jwt = $this->createJWT();
            
            // Prepare headers
            $headers = [
                'Authorization: WebPush ' . $jwt,
                'TTL: 86400', // 24 hours
                'Content-Type: application/octet-stream',
                'Content-Encoding: aes128gcm'
            ];
            
            // If using FCM (Google), add specific headers
            if (strpos($subscription['endpoint'], 'fcm.googleapis.com') !== false) {
                $headers[] = 'Authorization: key=' . ($_ENV['FCM_SERVER_KEY'] ?? '');
            }
            
            // Send the notification
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $subscription['endpoint'],
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_TIMEOUT => 30
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            // Check if successful
            if ($httpCode >= 200 && $httpCode < 300) {
                return true;
            } else {
                error_log("Push notification failed: HTTP $httpCode - $response");
                
                // If subscription is invalid, remove it
                if ($httpCode === 410 || $httpCode === 404) {
                    $this->subscriptionRepository->removeInvalidSubscription($subscription['endpoint']);
                }
                
                return false;
            }
            
        } catch (Exception $e) {
            error_log("Send to subscription error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get subscriptions for recipients
     */
    private function getSubscriptionsForRecipients($recipients) {
        if ($recipients === 'all') {
            return $this->subscriptionRepository->getAllSubscriptions();
        } elseif (is_array($recipients)) {
            return $this->subscriptionRepository->getSubscriptionsForUsers($recipients);
        } elseif (is_numeric($recipients)) {
            $subscription = $this->subscriptionRepository->getSubscription($recipients);
            return $subscription ? [$subscription] : [];
        }
        
        return [];
    }
    
    /**
     * Create JWT token for WebPush authentication
     */
    private function createJWT() {
        $header = json_encode(['typ' => 'JWT', 'alg' => 'ES256']);
        $payload = json_encode([
            'aud' => 'https://fcm.googleapis.com',
            'exp' => time() + 3600, // 1 hour
            'sub' => $this->vapidSubject
        ]);
        
        $headerEncoded = $this->base64UrlEncode($header);
        $payloadEncoded = $this->base64UrlEncode($payload);
        
        $signature = $this->sign($headerEncoded . '.' . $payloadEncoded);
        $signatureEncoded = $this->base64UrlEncode($signature);
        
        return $headerEncoded . '.' . $payloadEncoded . '.' . $signatureEncoded;
    }
    
    /**
     * Base64 URL encode
     */
    private function base64UrlEncode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
    
    /**
     * Sign data with VAPID private key
     */
    private function sign($data) {
        // This is a simplified implementation
        // In production, you should use a proper ECDSA signing library
        return hash_hmac('sha256', $data, $this->vapidPrivateKey, true);
    }
    
    /**
     * Send notification to specific user
     */
    public function sendToUser($userId, $notification) {
        return $this->sendNotification($notification, $userId);
    }
    
    /**
     * Send notification to multiple users
     */
    public function sendToUsers($userIds, $notification) {
        return $this->sendNotification($notification, $userIds);
    }
    
    /**
     * Send system notification (to all users)
     */
    public function sendSystemNotification($title, $body, $data = []) {
        $notification = [
            'title' => $title,
            'body' => $body,
            'icon' => '/assets/icons/icon-192.png',
            'badge' => '/assets/icons/icon-72.png',
            'data' => $data,
            'requireInteraction' => true
        ];
        
        return $this->sendNotification($notification, 'all');
    }
}