<?php
/**
 * Project Service
 * Handles business logic for Project Master module
 */

require_once __DIR__ . '/../config/autoload.php';
require_once __DIR__ . '/../repositories/ProjectRepository.php';

class ProjectService {
    private $db;
    private $projectRepository;
    
    public function __construct() {
        $this->db = DatabaseConfig::getInstance();
        $this->projectRepository = new ProjectRepository();
    }
    
    /**
     * Get all projects with filters
     */
    public function getAll(array $filters = []): array {
        return $this->projectRepository->findAllWithFilters($filters);
    }
    
    /**
     * Get project by ID
     */
    public function getById(int $id): ?array {
        return $this->projectRepository->findById($id);
    }
    
    /**
     * Create project
     */
    public function create(array $data, ?int $userId = null): array {
        $validation = $this->validate($data);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validation['errors'],
                'code' => 'VALIDATION_ERROR'
            ];
        }
        
        if ($this->projectRepository->nameExists(trim($data['name']))) {
            return [
                'success' => false,
                'message' => 'A project with this name already exists',
                'errors' => ['name' => ['Project name must be unique']],
                'code' => 'DUPLICATE_ERROR'
            ];
        }
        
        try {
            $projectData = [
                'name' => trim($data['name']),
                'code' => !empty($data['code']) ? trim($data['code']) : null,
                'description' => !empty($data['description']) ? trim($data['description']) : null,
                'status' => isset($data['status']) ? (int)$data['status'] : 1
            ];
            
            if ($userId !== null) {
                $projectData['created_by'] = $userId;
            }
            
            $projectId = $this->projectRepository->createProject($projectData);
            $project = $this->projectRepository->findById($projectId);
            
            return [
                'success' => true,
                'message' => 'Project created successfully',
                'data' => $project
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'code' => 'CREATE_ERROR'
            ];
        }
    }
    
    /**
     * Update project
     */
    public function update(int $id, array $data, ?int $userId = null): array {
        $existing = $this->projectRepository->findById($id);
        if (!$existing) {
            return [
                'success' => false,
                'message' => 'Project not found',
                'code' => 'NOT_FOUND'
            ];
        }
        
        $validation = $this->validate($data, $id);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validation['errors'],
                'code' => 'VALIDATION_ERROR'
            ];
        }
        
        if (isset($data['name']) && trim($data['name']) !== $existing['name']) {
            if ($this->projectRepository->nameExists(trim($data['name']), $id)) {
                return [
                    'success' => false,
                    'message' => 'A project with this name already exists',
                    'errors' => ['name' => ['Project name must be unique']],
                    'code' => 'DUPLICATE_ERROR'
                ];
            }
        }
        
        try {
            $projectData = [];
            if (isset($data['name'])) $projectData['name'] = trim($data['name']);
            if (array_key_exists('code', $data)) $projectData['code'] = !empty($data['code']) ? trim($data['code']) : null;
            if (array_key_exists('description', $data)) $projectData['description'] = $data['description'];
            if (isset($data['status'])) $projectData['status'] = (int)$data['status'];
            if ($userId !== null) $projectData['updated_by'] = $userId;
            
            $this->projectRepository->updateProject($id, $projectData);
            $updated = $this->projectRepository->findById($id);
            
            return [
                'success' => true,
                'message' => 'Project updated successfully',
                'data' => $updated
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'code' => 'UPDATE_ERROR'
            ];
        }
    }
    
    /**
     * Soft delete project
     */
    public function delete(int $id, ?int $userId = null): array {
        $existing = $this->projectRepository->findById($id);
        if (!$existing) {
            return [
                'success' => false,
                'message' => 'Project not found',
                'code' => 'NOT_FOUND'
            ];
        }
        
        try {
            $this->projectRepository->softDelete($id, $userId);
            return [
                'success' => true,
                'message' => 'Project deleted successfully (soft deleted)'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'code' => 'DELETE_ERROR'
            ];
        }
    }
    
    /**
     * Export projects
     */
    public function export(array $filters = []): array {
        return $this->projectRepository->exportAll($filters);
    }
    
    /**
     * Validate project data
     */
    public function validate(array $data, ?int $id = null): array {
        $errors = [];
        
        if (!isset($data['name']) || trim($data['name']) === '') {
            $errors['name'] = ['Project Name is required'];
        } elseif (strlen(trim($data['name'])) > 255) {
            $errors['name'] = ['Project Name must not exceed 255 characters'];
        }
        
        if (isset($data['code']) && strlen(trim($data['code'])) > 50) {
            $errors['code'] = ['Project Code must not exceed 50 characters'];
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
}
