<?php
/**
 * Migration Runner
 */

class MigrationRunner {
    private $db;
    private $migrationsPath;
    
    public function __construct() {
        $this->db = DatabaseConfig::getInstance()->getConnection();
        $this->migrationsPath = __DIR__ . '/';
        $this->createMigrationsTable();
    }
    
    /**
     * Create migrations tracking table
     */
    private function createMigrationsTable() {
        $sql = "CREATE TABLE IF NOT EXISTS migrations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            migration VARCHAR(255) NOT NULL,
            executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_migration (migration)
        )";
        
        if (!$this->db->query($sql)) {
            throw new Exception("Failed to create migrations table: " . $this->db->error);
        }
    }
    
    /**
     * Run all pending migrations
     */
    public function runMigrations() {
        $migrations = $this->getPendingMigrations();
        
        foreach ($migrations as $migration) {
            echo "Running migration: $migration\n";
            $this->runMigration($migration);
            $this->markAsExecuted($migration);
            echo "Completed migration: $migration\n";
        }
        
        if (empty($migrations)) {
            echo "No pending migrations found.\n";
        }
    }
    
    /**
     * Get list of pending migrations
     */
    private function getPendingMigrations() {
        $allMigrations = $this->getAllMigrationFiles();
        $executedMigrations = $this->getExecutedMigrations();
        
        return array_diff($allMigrations, $executedMigrations);
    }
    
    /**
     * Get all migration files
     */
    private function getAllMigrationFiles() {
        $files = glob($this->migrationsPath . '*_*.php');
        $migrations = [];
        
        foreach ($files as $file) {
            $filename = basename($file, '.php');
            if (preg_match('/^\d{4}_\d{2}_\d{2}_\d{6}_/', $filename)) {
                $migrations[] = $filename;
            }
        }
        
        sort($migrations);
        return $migrations;
    }
    
    /**
     * Get executed migrations from database
     */
    private function getExecutedMigrations() {
        $result = $this->db->query("SELECT migration FROM migrations ORDER BY migration");
        $executed = [];
        
        while ($row = $result->fetch_assoc()) {
            $executed[] = $row['migration'];
        }
        
        return $executed;
    }
    
    /**
     * Run a specific migration
     */
    private function runMigration($migrationName) {
        $file = $this->migrationsPath . $migrationName . '.php';
        
        if (!file_exists($file)) {
            throw new Exception("Migration file not found: $file");
        }
        
        require_once $file;
        
        // Extract class name from filename
        $className = $this->getClassNameFromFile($migrationName);
        
        // Try different class name variations
        $classVariations = [
            $className,
            $className . 'Migration',
            str_replace('_', '', ucwords($className, '_'))
        ];
        
        $foundClass = null;
        foreach ($classVariations as $variation) {
            if (class_exists($variation)) {
                $foundClass = $variation;
                break;
            }
        }
        
        if (!$foundClass) {
            throw new Exception("Migration class not found. Tried: " . implode(', ', $classVariations));
        }
        
        $migration = new $foundClass();
        $migration->up();
    }
    
    /**
     * Extract class name from migration filename
     */
    private function getClassNameFromFile($filename) {
        // Remove timestamp prefix and convert to class name
        $parts = explode('_', $filename);
        if (count($parts) >= 5) {
            $nameParts = array_slice($parts, 4);
            return implode('', array_map('ucfirst', $nameParts));
        }
        
        throw new Exception("Invalid migration filename format: $filename");
    }
    
    /**
     * Mark migration as executed
     */
    private function markAsExecuted($migration) {
        $stmt = $this->db->prepare("INSERT INTO migrations (migration) VALUES (?)");
        $stmt->bind_param('s', $migration);
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to mark migration as executed: " . $stmt->error);
        }
        
        $stmt->close();
    }
}