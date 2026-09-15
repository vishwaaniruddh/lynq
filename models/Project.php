<?php
/**
 * Project Model
 * Represents a Project record in Masters
 */

require_once __DIR__ . '/BaseModel.php';

class Project extends BaseModel {
    protected $table = 'projects';
    protected $fillable = [
        'name', 'code', 'description', 'status',
        'created_by', 'updated_by', 'created_at', 'updated_at', 'deleted_at'
    ];
    
    // Status constants
    const STATUS_ACTIVE = 1;
    const STATUS_INACTIVE = 0;
    
    /**
     * Check if project exists and is active
     */
    public function isActive(int $id): bool {
        $project = $this->find($id);
        return $project && (int)$project['status'] === self::STATUS_ACTIVE && empty($project['deleted_at']);
    }
}
