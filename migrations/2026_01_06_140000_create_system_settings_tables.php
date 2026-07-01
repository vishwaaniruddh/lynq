<?php
/**
 * Create System Settings Tables Migration
 * Creates tables for the System Settings Module including:
 * - system_settings: Core settings storage with validation rules
 * - settings_audit: Audit trail for all setting changes
 */

require_once __DIR__ . '/Migration.php';

class CreateSystemSettingsTables extends Migration {
    
    public function up() {
        $this->createSystemSettingsTable();
        $this->createSettingsAuditTable();
        $this->seedDefaultSettings();
    }
    
    public function down() {
        $this->execute("DROP TABLE IF EXISTS `settings_audit`");
        $this->execute("DROP TABLE IF EXISTS `system_settings`");
    }
    
    private function createSystemSettingsTable() {
        $sql = "CREATE TABLE IF NOT EXISTS `system_settings` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `category` VARCHAR(50) NOT NULL,
            `setting_key` VARCHAR(100) NOT NULL UNIQUE,
            `setting_value` TEXT,
            `default_value` TEXT,
            `data_type` ENUM('string', 'integer', 'boolean', 'json') DEFAULT 'string',
            `description` TEXT,
            `validation_rules` JSON,
            `is_required` BOOLEAN DEFAULT FALSE,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX `idx_category` (`category`),
            INDEX `idx_key` (`setting_key`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->execute($sql);
    }
    
    private function createSettingsAuditTable() {
        $sql = "CREATE TABLE IF NOT EXISTS `settings_audit` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `setting_id` INT NOT NULL,
            `user_id` INT NOT NULL,
            `old_value` TEXT,
            `new_value` TEXT,
            `action` ENUM('CREATE', 'UPDATE', 'DELETE', 'RESET') NOT NULL,
            `timestamp` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `ip_address` VARCHAR(45),
            `user_agent` TEXT,
            `session_id` VARCHAR(128),
            `request_method` VARCHAR(10),
            `request_uri` TEXT,
            `integrity_hash` VARCHAR(64),
            FOREIGN KEY (`setting_id`) REFERENCES `system_settings`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE RESTRICT,
            INDEX `idx_setting_timestamp` (`setting_id`, `timestamp`),
            INDEX `idx_user_timestamp` (`user_id`, `timestamp`),
            INDEX `idx_action` (`action`),
            INDEX `idx_integrity` (`integrity_hash`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->execute($sql);
    }
    
    private function seedDefaultSettings() {
        $defaultSettings = [
            // General Category
            [
                'category' => 'General',
                'setting_key' => 'site_name',
                'setting_value' => 'ADV CRM System',
                'default_value' => 'ADV CRM System',
                'data_type' => 'string',
                'description' => 'The name of the application displayed in the interface',
                'validation_rules' => '{"required": true, "min_length": 3, "max_length": 100}',
                'is_required' => true
            ],
            [
                'category' => 'General',
                'setting_key' => 'default_timezone',
                'setting_value' => 'UTC',
                'default_value' => 'UTC',
                'data_type' => 'string',
                'description' => 'Default timezone for the application',
                'validation_rules' => '{"required": true, "allowed_values": ["UTC", "America/New_York", "America/Chicago", "America/Denver", "America/Los_Angeles", "Europe/London", "Europe/Paris", "Asia/Tokyo", "Asia/Kolkata"]}',
                'is_required' => true
            ],
            [
                'category' => 'General',
                'setting_key' => 'default_language',
                'setting_value' => 'en',
                'default_value' => 'en',
                'data_type' => 'string',
                'description' => 'Default language for the application interface',
                'validation_rules' => '{"required": true, "allowed_values": ["en", "es", "fr", "de", "it", "pt", "ja", "zh", "hi"]}',
                'is_required' => true
            ],
            [
                'category' => 'General',
                'setting_key' => 'date_format',
                'setting_value' => 'Y-m-d',
                'default_value' => 'Y-m-d',
                'data_type' => 'string',
                'description' => 'Default date format for displaying dates',
                'validation_rules' => '{"required": true, "allowed_values": ["Y-m-d", "m/d/Y", "d/m/Y", "d-m-Y", "M j, Y"]}',
                'is_required' => true
            ],
            [
                'category' => 'General',
                'setting_key' => 'items_per_page',
                'setting_value' => '25',
                'default_value' => '25',
                'data_type' => 'integer',
                'description' => 'Default number of items to display per page in lists',
                'validation_rules' => '{"required": true, "min_value": 10, "max_value": 100}',
                'is_required' => true
            ],
            
            // Security Category
            [
                'category' => 'Security',
                'setting_key' => 'session_timeout',
                'setting_value' => '3600',
                'default_value' => '3600',
                'data_type' => 'integer',
                'description' => 'Session timeout in seconds (default: 1 hour)',
                'validation_rules' => '{"required": true, "min_value": 300, "max_value": 86400}',
                'is_required' => true
            ],
            [
                'category' => 'Security',
                'setting_key' => 'password_min_length',
                'setting_value' => '8',
                'default_value' => '8',
                'data_type' => 'integer',
                'description' => 'Minimum password length requirement',
                'validation_rules' => '{"required": true, "min_value": 6, "max_value": 32}',
                'is_required' => true
            ],
            [
                'category' => 'Security',
                'setting_key' => 'password_require_special',
                'setting_value' => 'true',
                'default_value' => 'true',
                'data_type' => 'boolean',
                'description' => 'Require special characters in passwords',
                'validation_rules' => '{"required": true}',
                'is_required' => true
            ],
            [
                'category' => 'Security',
                'setting_key' => 'max_login_attempts',
                'setting_value' => '5',
                'default_value' => '5',
                'data_type' => 'integer',
                'description' => 'Maximum failed login attempts before account lockout',
                'validation_rules' => '{"required": true, "min_value": 3, "max_value": 10}',
                'is_required' => true
            ],
            [
                'category' => 'Security',
                'setting_key' => 'lockout_duration',
                'setting_value' => '900',
                'default_value' => '900',
                'data_type' => 'integer',
                'description' => 'Account lockout duration in seconds (default: 15 minutes)',
                'validation_rules' => '{"required": true, "min_value": 300, "max_value": 3600}',
                'is_required' => true
            ],
            
            // Email Category
            [
                'category' => 'Email',
                'setting_key' => 'smtp_host',
                'setting_value' => '',
                'default_value' => '',
                'data_type' => 'string',
                'description' => 'SMTP server hostname',
                'validation_rules' => '{"max_length": 255}',
                'is_required' => false
            ],
            [
                'category' => 'Email',
                'setting_key' => 'smtp_port',
                'setting_value' => '587',
                'default_value' => '587',
                'data_type' => 'integer',
                'description' => 'SMTP server port',
                'validation_rules' => '{"min_value": 1, "max_value": 65535}',
                'is_required' => false
            ],
            [
                'category' => 'Email',
                'setting_key' => 'smtp_username',
                'setting_value' => '',
                'default_value' => '',
                'data_type' => 'string',
                'description' => 'SMTP authentication username',
                'validation_rules' => '{"max_length": 255}',
                'is_required' => false
            ],
            [
                'category' => 'Email',
                'setting_key' => 'smtp_encryption',
                'setting_value' => 'tls',
                'default_value' => 'tls',
                'data_type' => 'string',
                'description' => 'SMTP encryption method',
                'validation_rules' => '{"allowed_values": ["none", "ssl", "tls"]}',
                'is_required' => false
            ],
            [
                'category' => 'Email',
                'setting_key' => 'from_email',
                'setting_value' => 'noreply@advcrm.local',
                'default_value' => 'noreply@advcrm.local',
                'data_type' => 'string',
                'description' => 'Default from email address',
                'validation_rules' => '{"pattern": "^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\\.[a-zA-Z]{2,}$", "max_length": 255}',
                'is_required' => false
            ],
            [
                'category' => 'Email',
                'setting_key' => 'from_name',
                'setting_value' => 'ADV CRM System',
                'default_value' => 'ADV CRM System',
                'data_type' => 'string',
                'description' => 'Default from name for emails',
                'validation_rules' => '{"max_length": 100}',
                'is_required' => false
            ],
            
            // Backup Category
            [
                'category' => 'Backup',
                'setting_key' => 'backup_enabled',
                'setting_value' => 'true',
                'default_value' => 'true',
                'data_type' => 'boolean',
                'description' => 'Enable automatic database backups',
                'validation_rules' => '{"required": true}',
                'is_required' => true
            ],
            [
                'category' => 'Backup',
                'setting_key' => 'backup_frequency',
                'setting_value' => 'daily',
                'default_value' => 'daily',
                'data_type' => 'string',
                'description' => 'Backup frequency schedule',
                'validation_rules' => '{"required": true, "allowed_values": ["hourly", "daily", "weekly", "monthly"]}',
                'is_required' => true
            ],
            [
                'category' => 'Backup',
                'setting_key' => 'backup_retention_days',
                'setting_value' => '30',
                'default_value' => '30',
                'data_type' => 'integer',
                'description' => 'Number of days to retain backup files',
                'validation_rules' => '{"required": true, "min_value": 7, "max_value": 365}',
                'is_required' => true
            ],
            [
                'category' => 'Backup',
                'setting_key' => 'backup_path',
                'setting_value' => './backups/',
                'default_value' => './backups/',
                'data_type' => 'string',
                'description' => 'Directory path for storing backup files',
                'validation_rules' => '{"required": true, "max_length": 255}',
                'is_required' => true
            ],
            
            // Logging Category
            [
                'category' => 'Logging',
                'setting_key' => 'log_level',
                'setting_value' => 'INFO',
                'default_value' => 'INFO',
                'data_type' => 'string',
                'description' => 'Minimum log level to record',
                'validation_rules' => '{"required": true, "allowed_values": ["DEBUG", "INFO", "WARNING", "ERROR", "CRITICAL"]}',
                'is_required' => true
            ],
            [
                'category' => 'Logging',
                'setting_key' => 'log_retention_days',
                'setting_value' => '90',
                'default_value' => '90',
                'data_type' => 'integer',
                'description' => 'Number of days to retain log files',
                'validation_rules' => '{"required": true, "min_value": 7, "max_value": 365}',
                'is_required' => true
            ],
            [
                'category' => 'Logging',
                'setting_key' => 'audit_enabled',
                'setting_value' => 'true',
                'default_value' => 'true',
                'data_type' => 'boolean',
                'description' => 'Enable audit logging for sensitive operations',
                'validation_rules' => '{"required": true}',
                'is_required' => true
            ],
            [
                'category' => 'Logging',
                'setting_key' => 'audit_retention_days',
                'setting_value' => '365',
                'default_value' => '365',
                'data_type' => 'integer',
                'description' => 'Number of days to retain audit log entries',
                'validation_rules' => '{"required": true, "min_value": 90, "max_value": 2555}',
                'is_required' => true
            ],
            
            // Performance Category
            [
                'category' => 'Performance',
                'setting_key' => 'cache_enabled',
                'setting_value' => 'true',
                'default_value' => 'true',
                'data_type' => 'boolean',
                'description' => 'Enable application caching',
                'validation_rules' => '{"required": true}',
                'is_required' => true
            ],
            [
                'category' => 'Performance',
                'setting_key' => 'cache_ttl',
                'setting_value' => '3600',
                'default_value' => '3600',
                'data_type' => 'integer',
                'description' => 'Cache time-to-live in seconds (default: 1 hour)',
                'validation_rules' => '{"required": true, "min_value": 300, "max_value": 86400}',
                'is_required' => true
            ],
            [
                'category' => 'Performance',
                'setting_key' => 'query_timeout',
                'setting_value' => '30',
                'default_value' => '30',
                'data_type' => 'integer',
                'description' => 'Database query timeout in seconds',
                'validation_rules' => '{"required": true, "min_value": 5, "max_value": 300}',
                'is_required' => true
            ],
            [
                'category' => 'Performance',
                'setting_key' => 'max_upload_size',
                'setting_value' => '10485760',
                'default_value' => '10485760',
                'data_type' => 'integer',
                'description' => 'Maximum file upload size in bytes (default: 10MB)',
                'validation_rules' => '{"required": true, "min_value": 1048576, "max_value": 104857600}',
                'is_required' => true
            ]
        ];
        
        foreach ($defaultSettings as $setting) {
            $stmt = $this->db->prepare("
                INSERT IGNORE INTO `system_settings` 
                (`category`, `setting_key`, `setting_value`, `default_value`, `data_type`, `description`, `validation_rules`, `is_required`) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->bind_param(
                'sssssssi',
                $setting['category'],
                $setting['setting_key'],
                $setting['setting_value'],
                $setting['default_value'],
                $setting['data_type'],
                $setting['description'],
                $setting['validation_rules'],
                $setting['is_required']
            );
            
            if (!$stmt->execute()) {
                throw new Exception("Failed to insert default setting '{$setting['setting_key']}': " . $stmt->error);
            }
            
            $stmt->close();
        }
    }
}