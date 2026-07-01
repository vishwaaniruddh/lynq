<?php
/**
 * Base Model Class
 */

abstract class BaseModel {
    protected $db;
    protected $table;
    protected $primaryKey = 'id';
    protected $fillable = [];
    protected $hidden = [];
    
    public function __construct() {
        $this->db = DatabaseConfig::getInstance()->getConnection();
    }
    
    /**
     * Find record by ID
     */
    public function find($id) {
        $sql = "SELECT * FROM `{$this->table}` WHERE `{$this->primaryKey}` = ?";
        $result = DatabaseConfig::getInstance()->getResults($sql, [$id], 'i');
        
        return !empty($result) ? $result[0] : null;
    }
    
    /**
     * Find all records
     */
    public function findAll($conditions = [], $orderBy = null, $limit = null) {
        $sql = "SELECT * FROM `{$this->table}`";
        $params = [];
        $types = '';
        
        if (!empty($conditions)) {
            $whereClause = [];
            foreach ($conditions as $field => $value) {
                $whereClause[] = "`$field` = ?";
                $params[] = $value;
                $types .= is_int($value) ? 'i' : 's';
            }
            $sql .= " WHERE " . implode(' AND ', $whereClause);
        }
        
        if ($orderBy) {
            $sql .= " ORDER BY $orderBy";
        }
        
        if ($limit) {
            $sql .= " LIMIT $limit";
        }
        
        return DatabaseConfig::getInstance()->getResults($sql, $params, $types);
    }
    
    /**
     * Create new record
     */
    public function create($data) {
        $data = $this->filterFillable($data);
        
        if (empty($data)) {
            throw new InvalidArgumentException("No valid data provided for creation");
        }
        
        $fields = array_keys($data);
        $placeholders = str_repeat('?,', count($fields) - 1) . '?';
        $values = array_values($data);
        
        $sql = "INSERT INTO `{$this->table}` (`" . implode('`, `', $fields) . "`) VALUES ($placeholders)";
        
        $types = '';
        foreach ($values as $value) {
            $types .= is_int($value) ? 'i' : 's';
        }
        
        $stmt = DatabaseConfig::getInstance()->executeQuery($sql, $values, $types);
        $insertId = $this->db->insert_id;
        $stmt->close();
        
        return $this->find($insertId);
    }
    
    /**
     * Update record
     */
    public function update($id, $data) {
        $data = $this->filterFillable($data);
        
        if (empty($data)) {
            throw new InvalidArgumentException("No valid data provided for update");
        }
        
        $fields = array_keys($data);
        $setClause = implode(' = ?, ', $fields) . ' = ?';
        $values = array_values($data);
        $values[] = $id;
        
        $sql = "UPDATE `{$this->table}` SET $setClause WHERE `{$this->primaryKey}` = ?";
        
        $types = '';
        foreach ($values as $value) {
            $types .= is_int($value) ? 'i' : 's';
        }
        
        $stmt = DatabaseConfig::getInstance()->executeQuery($sql, $values, $types);
        $stmt->close();
        
        return $this->find($id);
    }
    
    /**
     * Delete record
     */
    public function delete($id) {
        $sql = "DELETE FROM `{$this->table}` WHERE `{$this->primaryKey}` = ?";
        $stmt = DatabaseConfig::getInstance()->executeQuery($sql, [$id], 'i');
        $affectedRows = $stmt->affected_rows;
        $stmt->close();
        
        return $affectedRows > 0;
    }
    
    /**
     * Filter data to only include fillable fields
     */
    protected function filterFillable($data) {
        if (empty($this->fillable)) {
            return $data;
        }
        
        return array_intersect_key($data, array_flip($this->fillable));
    }
    
    /**
     * Remove hidden fields from data
     */
    protected function removeHidden($data) {
        if (empty($this->hidden)) {
            return $data;
        }
        
        return array_diff_key($data, array_flip($this->hidden));
    }
}