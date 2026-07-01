<?php
/**
 * Database Configuration for ADV CRM Users Module
 * Uses dedicated clarity_db database
 */

class DatabaseConfig {
    private static $instance = null;
    private $connection;
    
    // Database configuration for clarity_db
    private $host = "localhost";
    private $user = "root";
    private $pass = "";
    private $dbname = "if0_40845939_clarity_db";
    
    private function __construct() {
        $this->connection = $this->createConnection();
    }
    
    private function createConnection() {
        $con = new mysqli($this->host, $this->user, $this->pass, $this->dbname);
        
        if ($con->connect_error) {
            throw new Exception("Database connection failed: " . $con->connect_error);
        }
        
        // Set charset to utf8mb4 for proper Unicode support
        $con->set_charset("utf8mb4");
        
        return $con;
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->connection;
    }
    
    /**
     * Execute a prepared statement safely
     */
    public function executeQuery($sql, $params = [], $types = '') {
        $stmt = $this->connection->prepare($sql);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $this->connection->error);
        }
        
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        
        $result = $stmt->execute();
        if (!$result) {
            throw new Exception("Execute failed: " . $stmt->error);
        }
        
        return $stmt;
    }
    
    /**
     * Get results from a prepared statement
     */
    public function getResults($sql, $params = [], $types = '') {
        $stmt = $this->executeQuery($sql, $params, $types);
        $result = $stmt->get_result();
        $data = [];
        
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        
        $stmt->close();
        return $data;
    }
}

/**
 * PDO-style Database wrapper for easier use in views
 */
class Database {
    private static $instance = null;
    private $pdo;
       
    private $host = "localhost";
    private $user = "root";
    private $pass = "";
    private $dbname = "if0_40845939_clarity_db";
    
    private function __construct() {
        try {
            $dsn = "mysql:host={$this->host};dbname={$this->dbname};charset=utf8mb4";
            $this->pdo = new PDO($dsn, $this->user, $this->pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]);
        } catch (PDOException $e) {
            throw new Exception("Database connection failed: " . $e->getMessage());
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->pdo;
    }
}