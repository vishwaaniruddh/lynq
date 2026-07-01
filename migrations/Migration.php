<?php
/**
 * Base Migration Class
 */

abstract class Migration {
    protected $db;
    
    public function __construct() {
        $this->db = DatabaseConfig::getInstance()->getConnection();
    }
    
    /**
     * Run the migration
     */
    abstract public function up();
    
    /**
     * Reverse the migration
     */
    abstract public function down();
    
    /**
     * Execute SQL with error handling
     */
    protected function execute($sql) {
        if (!$this->db->query($sql)) {
            throw new Exception("Migration failed: " . $this->db->error . "\nSQL: " . $sql);
        }
        return true;
    }
    
    /**
     * Check if table exists
     */
    protected function tableExists($tableName) {
        $result = $this->db->query("SHOW TABLES LIKE '$tableName'");
        return $result->num_rows > 0;
    }
    
    /**
     * Check if column exists in table
     */
    protected function columnExists($tableName, $columnName) {
        $result = $this->db->query("SHOW COLUMNS FROM `$tableName` LIKE '$columnName'");
        return $result->num_rows > 0;
    }
}