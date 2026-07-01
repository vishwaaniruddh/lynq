<?php

require_once __DIR__ . '/Migration.php';

/**
 * Create push_subscriptions table for PWA push notifications
 */
class CreatePushSubscriptionsTable extends Migration {
    
    public function up() {
        $sql = "
        CREATE TABLE IF NOT EXISTS push_subscriptions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            company_id INT NOT NULL,
            endpoint TEXT NOT NULL,
            p256dh_key TEXT NOT NULL,
            auth_key VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            INDEX idx_user_company (user_id, company_id),
            INDEX idx_endpoint_hash (endpoint(255)),
            INDEX idx_updated_at (updated_at),
            
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
        
        return $this->executeQuery($sql);
    }
    
    public function down() {
        $sql = "DROP TABLE IF EXISTS push_subscriptions";
        return $this->executeQuery($sql);
    }
    
    public function getDescription() {
        return "Create push_subscriptions table for PWA push notifications";
    }
}