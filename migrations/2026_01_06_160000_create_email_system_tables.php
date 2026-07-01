<?php
/**
 * Email System Tables Migration
 * Creates all tables required for the email management system
 */

require_once __DIR__ . '/Migration.php';

class CreateEmailSystemTables extends Migration {
    
    public function up() {
        // Create email_configurations table
        $this->createEmailConfigurationsTable();
        
        // Create email_templates table
        $this->createEmailTemplatesTable();
        
        // Create email_triggers table
        $this->createEmailTriggersTable();
        
        // Create email_queue table
        $this->createEmailQueueTable();
        
        // Create email_logs table
        $this->createEmailLogsTable();
        
        // Create email_placeholders table
        $this->createEmailPlaceholdersTable();
        
        // Add indexes for performance
        $this->addIndexes();
        
        echo "Email system tables created successfully.\n";
    }
    
    public function down() {
        $tables = [
            'email_logs',
            'email_queue', 
            'email_triggers',
            'email_templates',
            'email_configurations',
            'email_placeholders'
        ];
        
        foreach ($tables as $table) {
            if ($this->tableExists($table)) {
                $this->execute("DROP TABLE `$table`");
                echo "Dropped table: $table\n";
            }
        }
    }
    
    private function createEmailConfigurationsTable() {
        if ($this->tableExists('email_configurations')) {
            echo "Table email_configurations already exists, skipping.\n";
            return;
        }
        
        $sql = "CREATE TABLE `email_configurations` (
            `id` INT PRIMARY KEY AUTO_INCREMENT,
            `name` VARCHAR(100) NOT NULL,
            `type` ENUM('smtp', 'imap') NOT NULL,
            `host` VARCHAR(255) NOT NULL,
            `port` INT NOT NULL,
            `username` VARCHAR(255) NOT NULL,
            `password_encrypted` TEXT NOT NULL,
            `encryption` ENUM('none', 'ssl', 'tls') DEFAULT 'tls',
            `is_default` BOOLEAN DEFAULT FALSE,
            `is_active` BOOLEAN DEFAULT TRUE,
            `company_id` INT NOT NULL,
            `created_by` INT NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT,
            INDEX `idx_company_type` (`company_id`, `type`),
            INDEX `idx_active_default` (`is_active`, `is_default`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->execute($sql);
        echo "Created table: email_configurations\n";
    }
    
    private function createEmailTemplatesTable() {
        if ($this->tableExists('email_templates')) {
            echo "Table email_templates already exists, skipping.\n";
            return;
        }
        
        $sql = "CREATE TABLE `email_templates` (
            `id` INT PRIMARY KEY AUTO_INCREMENT,
            `name` VARCHAR(100) NOT NULL,
            `subject` VARCHAR(255) NOT NULL,
            `body_text` TEXT,
            `body_html` TEXT,
            `module_name` VARCHAR(50) NOT NULL,
            `event_type` VARCHAR(50) NOT NULL,
            `placeholders` JSON,
            `is_active` BOOLEAN DEFAULT TRUE,
            `company_id` INT NOT NULL,
            `created_by` INT NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT,
            UNIQUE KEY `unique_template` (`module_name`, `event_type`, `company_id`),
            INDEX `idx_module_event` (`module_name`, `event_type`),
            INDEX `idx_company_active` (`company_id`, `is_active`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->execute($sql);
        echo "Created table: email_templates\n";
    }
    
    private function createEmailTriggersTable() {
        if ($this->tableExists('email_triggers')) {
            echo "Table email_triggers already exists, skipping.\n";
            return;
        }
        
        $sql = "CREATE TABLE `email_triggers` (
            `id` INT PRIMARY KEY AUTO_INCREMENT,
            `name` VARCHAR(100) NOT NULL,
            `module_name` VARCHAR(50) NOT NULL,
            `event_type` VARCHAR(50) NOT NULL,
            `template_id` INT NOT NULL,
            `recipient_rules` JSON NOT NULL,
            `conditions` JSON,
            `is_active` BOOLEAN DEFAULT TRUE,
            `company_id` INT NOT NULL,
            `created_by` INT NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (`template_id`) REFERENCES `email_templates`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT,
            INDEX `idx_module_event` (`module_name`, `event_type`),
            INDEX `idx_template_active` (`template_id`, `is_active`),
            INDEX `idx_company_active` (`company_id`, `is_active`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->execute($sql);
        echo "Created table: email_triggers\n";
    }
    
    private function createEmailQueueTable() {
        if ($this->tableExists('email_queue')) {
            echo "Table email_queue already exists, skipping.\n";
            return;
        }
        
        $sql = "CREATE TABLE `email_queue` (
            `id` INT PRIMARY KEY AUTO_INCREMENT,
            `to_email` VARCHAR(255) NOT NULL,
            `cc_email` TEXT,
            `bcc_email` TEXT,
            `subject` VARCHAR(255) NOT NULL,
            `body_text` TEXT,
            `body_html` TEXT,
            `template_id` INT,
            `trigger_id` INT,
            `priority` ENUM('low', 'normal', 'high') DEFAULT 'normal',
            `status` ENUM('pending', 'processing', 'sent', 'failed') DEFAULT 'pending',
            `attempts` INT DEFAULT 0,
            `max_attempts` INT DEFAULT 3,
            `error_message` TEXT,
            `scheduled_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `sent_at` TIMESTAMP NULL,
            `company_id` INT NOT NULL,
            `created_by` INT,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`template_id`) REFERENCES `email_templates`(`id`) ON DELETE SET NULL,
            FOREIGN KEY (`trigger_id`) REFERENCES `email_triggers`(`id`) ON DELETE SET NULL,
            FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
            INDEX `idx_status_priority` (`status`, `priority`),
            INDEX `idx_scheduled_status` (`scheduled_at`, `status`),
            INDEX `idx_company_status` (`company_id`, `status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->execute($sql);
        echo "Created table: email_queue\n";
    }
    
    private function createEmailLogsTable() {
        if ($this->tableExists('email_logs')) {
            echo "Table email_logs already exists, skipping.\n";
            return;
        }
        
        $sql = "CREATE TABLE `email_logs` (
            `id` INT PRIMARY KEY AUTO_INCREMENT,
            `queue_id` INT,
            `to_email` VARCHAR(255) NOT NULL,
            `subject` VARCHAR(255) NOT NULL,
            `status` ENUM('sent', 'failed', 'bounced') NOT NULL,
            `delivery_status` VARCHAR(100),
            `error_message` TEXT,
            `template_id` INT,
            `trigger_id` INT,
            `company_id` INT NOT NULL,
            `user_id` INT,
            `ip_address` VARCHAR(45),
            `user_agent` TEXT,
            `sent_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`queue_id`) REFERENCES `email_queue`(`id`) ON DELETE SET NULL,
            FOREIGN KEY (`template_id`) REFERENCES `email_templates`(`id`) ON DELETE SET NULL,
            FOREIGN KEY (`trigger_id`) REFERENCES `email_triggers`(`id`) ON DELETE SET NULL,
            FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
            INDEX `idx_company_status` (`company_id`, `status`),
            INDEX `idx_sent_at` (`sent_at`),
            INDEX `idx_template_trigger` (`template_id`, `trigger_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->execute($sql);
        echo "Created table: email_logs\n";
    }
    
    private function createEmailPlaceholdersTable() {
        if ($this->tableExists('email_placeholders')) {
            echo "Table email_placeholders already exists, skipping.\n";
            return;
        }
        
        $sql = "CREATE TABLE `email_placeholders` (
            `id` INT PRIMARY KEY AUTO_INCREMENT,
            `module_name` VARCHAR(50) NOT NULL,
            `placeholder_key` VARCHAR(100) NOT NULL,
            `placeholder_label` VARCHAR(100) NOT NULL,
            `data_source` VARCHAR(100) NOT NULL,
            `data_path` VARCHAR(255) NOT NULL,
            `data_type` ENUM('string', 'number', 'date', 'boolean') DEFAULT 'string',
            `is_active` BOOLEAN DEFAULT TRUE,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY `unique_placeholder` (`module_name`, `placeholder_key`),
            INDEX `idx_module_active` (`module_name`, `is_active`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->execute($sql);
        echo "Created table: email_placeholders\n";
    }
    
    private function addIndexes() {
        // Additional performance indexes
        $indexes = [
            "CREATE INDEX `idx_email_queue_processing` ON `email_queue` (`status`, `attempts`, `scheduled_at`)",
            "CREATE INDEX `idx_email_logs_audit` ON `email_logs` (`company_id`, `sent_at`, `status`)",
            "CREATE INDEX `idx_email_templates_lookup` ON `email_templates` (`module_name`, `event_type`, `is_active`)"
        ];
        
        foreach ($indexes as $index) {
            try {
                $this->execute($index);
            } catch (Exception $e) {
                // Index might already exist, continue
                echo "Index creation skipped (may already exist): " . $e->getMessage() . "\n";
            }
        }
    }
}