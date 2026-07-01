<?php
/**
 * Location Service
 * Handles business logic for location master module operations (Countries, States, Zones, Cities)
 * 
 * Requirements: 3.2, 3.3, 3.4, 4.2, 4.3, 4.4, 5.2, 5.3, 5.5, 6.2, 6.3
 * - 3.x: Country CRUD operations with referential integrity
 * - 4.x: State CRUD operations with referential integrity
 * - 5.x: Zone CRUD operations with cascade deletion
 * - 6.x: City CRUD operations
 */

require_once __DIR__ . '/../config/autoload.php';
require_once __DIR__ . '/../repositories/LocationRepository.php';
require_once __DIR__ . '/../repositories/LhoManagerRepository.php';

class LocationService {
    private $db;
    private $locationRepository;
    private $lhoManagerRepository;
    
    public function __construct() {
        $this->db = DatabaseConfig::getInstance();
        $this->locationRepository = new LocationRepository();
        $this->lhoManagerRepository = new LhoManagerRepository();
    }
    
    // ==================== COUNTRY OPERATIONS ====================
    
    /**
     * Get all countries with filters
     * 
     * @param array $filters Optional filters: search, status, page, limit, orderBy, orderDir
     * @return array Paginated result with data, total, page, limit, totalPages
     * 
     * Requirements: 3.1
     */
    public function getAllCountries(array $filters = []): array {
        return $this->locationRepository->findAllCountries($filters);
    }
    
    /**
     * Get country by ID
     * 
     * @param int $id Country ID
     * @return array|null Country record or null if not found
     */
    public function getCountryById(int $id): ?array {
        return $this->locationRepository->findCountryById($id);
    }
    
    /**
     * Create a new country record
     * 
     * @param array $data Country data: name, status
     * @param int|null $userId User ID performing the action (for audit)
     * @return array Result with success status and data/errors
     * 
     * Requirements: 3.2, 9.1, 9.2
     */
    public function createCountry(array $data, ?int $userId = null): array {
        // Validate required fields
        $validation = $this->validateCountry($data);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validation['errors'],
                'code' => 'VALIDATION_ERROR'
            ];
        }
        
        // Check uniqueness
        if ($this->locationRepository->findCountryByName(trim($data['name'])) !== null) {
            return [
                'success' => false,
                'message' => 'A country with this name already exists',
                'errors' => ['name' => ['Country name must be unique']],
                'code' => 'DUPLICATE_ERROR'
            ];
        }
        
        try {
            $countryData = [
                'name' => trim($data['name']),
                'status' => $data['status'] ?? 'active'
            ];
            
            if ($userId !== null) {
                $countryData['created_by'] = $userId;
            }
            
            $countryId = $this->locationRepository->createCountry($countryData);
            
            $this->logAction($userId, $countryId, 'country_created', [
                'name' => $countryData['name']
            ]);
            
            $country = $this->locationRepository->findCountryById($countryId);
            
            return [
                'success' => true,
                'message' => 'Country created successfully',
                'data' => $country
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to create country: ' . $e->getMessage(),
                'code' => 'CREATE_ERROR'
            ];
        }
    }
    
    /**
     * Update an existing country record
     * 
     * @param int $id Country ID
     * @param array $data Data to update: name, status
     * @param int|null $userId User ID performing the action (for audit)
     * @return array Result with success status and data/errors
     * 
     * Requirements: 3.3
     */
    public function updateCountry(int $id, array $data, ?int $userId = null): array {
        $existing = $this->locationRepository->findCountryById($id);
        if (!$existing) {
            return [
                'success' => false,
                'message' => 'Country not found',
                'code' => 'NOT_FOUND'
            ];
        }
        
        if (isset($data['name'])) {
            $validation = $this->validateCountry($data, $id);
            if (!$validation['valid']) {
                return [
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validation['errors'],
                    'code' => 'VALIDATION_ERROR'
                ];
            }
            
            // Check uniqueness
            if ($this->locationRepository->findCountryByName(trim($data['name']), $id) !== null) {
                return [
                    'success' => false,
                    'message' => 'A country with this name already exists',
                    'errors' => ['name' => ['Country name must be unique']],
                    'code' => 'DUPLICATE_ERROR'
                ];
            }
        }
        
        try {
            $updateData = [];
            if (isset($data['name'])) {
                $updateData['name'] = trim($data['name']);
            }
            if (isset($data['status'])) {
                $updateData['status'] = $data['status'];
            }
            if ($userId !== null) {
                $updateData['updated_by'] = $userId;
            }
            
            $this->locationRepository->updateCountry($id, $updateData);
            
            $this->logAction($userId, $id, 'country_updated', [
                'changes' => array_keys($updateData)
            ]);
            
            $country = $this->locationRepository->findCountryById($id);
            
            return [
                'success' => true,
                'message' => 'Country updated successfully',
                'data' => $country
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to update country: ' . $e->getMessage(),
                'code' => 'UPDATE_ERROR'
            ];
        }
    }
    
    /**
     * Delete a country record (only if no states exist)
     * 
     * @param int $id Country ID
     * @param int|null $userId User ID performing the action (for audit)
     * @return array Result with success status
     * 
     * Requirements: 3.4, 9.3
     */
    public function deleteCountry(int $id, ?int $userId = null): array {
        $existing = $this->locationRepository->findCountryById($id);
        if (!$existing) {
            return [
                'success' => false,
                'message' => 'Country not found',
                'code' => 'NOT_FOUND'
            ];
        }
        
        // Check for dependent states - Referential integrity
        if ($this->hasStateDependencies($id)) {
            $stateCount = $this->locationRepository->countStatesByCountry($id);
            return [
                'success' => false,
                'message' => "Cannot delete country with $stateCount associated state(s)",
                'code' => 'REFERENTIAL_INTEGRITY_ERROR',
                'dependencies' => ['states' => $stateCount]
            ];
        }
        
        try {
            $this->locationRepository->deleteCountry($id);
            
            $this->logAction($userId, $id, 'country_deleted', [
                'name' => $existing['name']
            ]);
            
            return [
                'success' => true,
                'message' => 'Country deleted successfully'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to delete country: ' . $e->getMessage(),
                'code' => 'DELETE_ERROR'
            ];
        }
    }
    
    /**
     * Check if country has state dependencies
     * 
     * @param int $countryId Country ID
     * @return bool True if country has states
     */
    public function hasStateDependencies(int $countryId): bool {
        return $this->locationRepository->countStatesByCountry($countryId) > 0;
    }
    
    /**
     * Get active countries for dropdowns
     * 
     * @return array Array of active country records
     */
    public function getActiveCountries(): array {
        return $this->locationRepository->findAllActiveCountries();
    }
    
    /**
     * Export countries with filters
     * 
     * @param array $filters Optional filters
     * @return array Array of country records
     */
    public function exportCountries(array $filters = []): array {
        return $this->locationRepository->findAllCountriesForExport($filters);
    }
    
    /**
     * Validate country data
     * 
     * @param array $data Data to validate
     * @param int|null $id Country ID (for updates)
     * @return array Validation result
     */
    private function validateCountry(array $data, ?int $id = null): array {
        $errors = [];
        
        if ($id === null || isset($data['name'])) {
            if (!isset($data['name']) || trim($data['name']) === '') {
                $errors['name'] = ['Country name is required'];
            } elseif (strlen(trim($data['name'])) > 100) {
                $errors['name'] = ['Country name must not exceed 100 characters'];
            }
        }
        
        if (isset($data['status']) && !in_array($data['status'], ['active', 'inactive'], true)) {
            $errors['status'] = ['Status must be active or inactive'];
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    
    // ==================== ZONE OPERATIONS ====================
    
    /**
     * Get all zones with filters
     * 
     * @param array $filters Optional filters: search, status, page, limit, orderBy, orderDir
     * @return array Paginated result with data, total, page, limit, totalPages
     * 
     * Requirements: 5.1
     */
    public function getAllZones(array $filters = []): array {
        return $this->locationRepository->findAllZones($filters);
    }
    
    /**
     * Get zone by ID
     * 
     * @param int $id Zone ID
     * @return array|null Zone record or null if not found
     * 
     * Requirements: 5.4
     */
    public function getZoneById(int $id): ?array {
        return $this->locationRepository->findZoneById($id);
    }
    
    /**
     * Create a new zone record
     * 
     * @param array $data Zone data: name, status
     * @param int|null $userId User ID performing the action (for audit)
     * @return array Result with success status and data/errors
     * 
     * Requirements: 5.2, 9.1, 9.2
     */
    public function createZone(array $data, ?int $userId = null): array {
        $validation = $this->validateZone($data);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validation['errors'],
                'code' => 'VALIDATION_ERROR'
            ];
        }
        
        // Check uniqueness
        if ($this->locationRepository->findZoneByName(trim($data['name'])) !== null) {
            return [
                'success' => false,
                'message' => 'A zone with this name already exists',
                'errors' => ['name' => ['Zone name must be unique']],
                'code' => 'DUPLICATE_ERROR'
            ];
        }
        
        try {
            $zoneData = [
                'name' => trim($data['name']),
                'status' => $data['status'] ?? 'active'
            ];
            
            if ($userId !== null) {
                $zoneData['created_by'] = $userId;
            }
            
            $zoneId = $this->locationRepository->createZone($zoneData);
            
            $this->logAction($userId, $zoneId, 'zone_created', [
                'name' => $zoneData['name']
            ]);
            
            $zone = $this->locationRepository->findZoneById($zoneId);
            
            return [
                'success' => true,
                'message' => 'Zone created successfully',
                'data' => $zone
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to create zone: ' . $e->getMessage(),
                'code' => 'CREATE_ERROR'
            ];
        }
    }
    
    /**
     * Update an existing zone record
     * 
     * @param int $id Zone ID
     * @param array $data Data to update: name, status
     * @param int|null $userId User ID performing the action (for audit)
     * @return array Result with success status and data/errors
     * 
     * Requirements: 5.3
     */
    public function updateZone(int $id, array $data, ?int $userId = null): array {
        $existing = $this->locationRepository->findZoneById($id);
        if (!$existing) {
            return [
                'success' => false,
                'message' => 'Zone not found',
                'code' => 'NOT_FOUND'
            ];
        }
        
        if (isset($data['name'])) {
            $validation = $this->validateZone($data, $id);
            if (!$validation['valid']) {
                return [
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validation['errors'],
                    'code' => 'VALIDATION_ERROR'
                ];
            }
            
            // Check uniqueness
            if ($this->locationRepository->findZoneByName(trim($data['name']), $id) !== null) {
                return [
                    'success' => false,
                    'message' => 'A zone with this name already exists',
                    'errors' => ['name' => ['Zone name must be unique']],
                    'code' => 'DUPLICATE_ERROR'
                ];
            }
        }
        
        try {
            $updateData = [];
            if (isset($data['name'])) {
                $updateData['name'] = trim($data['name']);
            }
            if (isset($data['status'])) {
                $updateData['status'] = $data['status'];
            }
            if ($userId !== null) {
                $updateData['updated_by'] = $userId;
            }
            
            $this->locationRepository->updateZone($id, $updateData);
            
            $this->logAction($userId, $id, 'zone_updated', [
                'changes' => array_keys($updateData)
            ]);
            
            $zone = $this->locationRepository->findZoneById($id);
            
            return [
                'success' => true,
                'message' => 'Zone updated successfully',
                'data' => $zone
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to update zone: ' . $e->getMessage(),
                'code' => 'UPDATE_ERROR'
            ];
        }
    }
    
    /**
     * Delete a zone record (cascade: set zone_id to NULL in states and cities)
     * 
     * @param int $id Zone ID
     * @param int|null $userId User ID performing the action (for audit)
     * @return array Result with success status
     * 
     * Requirements: 5.5
     */
    public function deleteZone(int $id, ?int $userId = null): array {
        $existing = $this->locationRepository->findZoneById($id);
        if (!$existing) {
            return [
                'success' => false,
                'message' => 'Zone not found',
                'code' => 'NOT_FOUND'
            ];
        }
        
        try {
            // Get counts before deletion for logging
            $stateCount = $this->locationRepository->countStatesByZone($id);
            $cityCount = $this->locationRepository->countCitiesByZone($id);
            
            // Delete zone - cascade handled by ON DELETE SET NULL in foreign keys
            $this->locationRepository->deleteZone($id);
            
            $this->logAction($userId, $id, 'zone_deleted', [
                'name' => $existing['name'],
                'cascaded_states' => $stateCount,
                'cascaded_cities' => $cityCount
            ]);
            
            return [
                'success' => true,
                'message' => 'Zone deleted successfully',
                'cascade_info' => [
                    'states_affected' => $stateCount,
                    'cities_affected' => $cityCount
                ]
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to delete zone: ' . $e->getMessage(),
                'code' => 'DELETE_ERROR'
            ];
        }
    }
    
    /**
     * Get active zones for dropdowns
     * 
     * @return array Array of active zone records
     */
    public function getActiveZones(): array {
        return $this->locationRepository->findAllActiveZones();
    }
    
    /**
     * Export zones with filters
     * 
     * @param array $filters Optional filters
     * @return array Array of zone records
     */
    public function exportZones(array $filters = []): array {
        return $this->locationRepository->findAllZonesForExport($filters);
    }
    
    /**
     * Validate zone data
     * 
     * @param array $data Data to validate
     * @param int|null $id Zone ID (for updates)
     * @return array Validation result
     */
    private function validateZone(array $data, ?int $id = null): array {
        $errors = [];
        
        if ($id === null || isset($data['name'])) {
            if (!isset($data['name']) || trim($data['name']) === '') {
                $errors['name'] = ['Zone name is required'];
            } elseif (strlen(trim($data['name'])) > 100) {
                $errors['name'] = ['Zone name must not exceed 100 characters'];
            }
        }
        
        if (isset($data['status']) && !in_array($data['status'], ['active', 'inactive'], true)) {
            $errors['status'] = ['Status must be active or inactive'];
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    
    // ==================== STATE OPERATIONS ====================
    
    /**
     * Get all states with filters
     * 
     * @param array $filters Optional filters: search, status, country_id, zone_id, page, limit, orderBy, orderDir
     * @return array Paginated result with data, total, page, limit, totalPages
     * 
     * Requirements: 4.1
     */
    public function getAllStates(array $filters = []): array {
        return $this->locationRepository->findAllStates($filters);
    }
    
    /**
     * Get state by ID
     * 
     * @param int $id State ID
     * @return array|null State record or null if not found
     */
    public function getStateById(int $id): ?array {
        return $this->locationRepository->findStateById($id);
    }
    
    /**
     * Create a new state record
     * 
     * @param array $data State data: name, country_id, zone_id, status
     * @param int|null $userId User ID performing the action (for audit)
     * @return array Result with success status and data/errors
     * 
     * Requirements: 4.2, 9.1, 9.2
     */
    public function createState(array $data, ?int $userId = null): array {
        $validation = $this->validateState($data);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validation['errors'],
                'code' => 'VALIDATION_ERROR'
            ];
        }
        
        // Verify country exists
        $country = $this->locationRepository->findCountryById((int)$data['country_id']);
        if (!$country) {
            return [
                'success' => false,
                'message' => 'Invalid country selection',
                'errors' => ['country_id' => ['Country not found']],
                'code' => 'VALIDATION_ERROR'
            ];
        }
        
        // Verify zone exists if provided
        if (isset($data['zone_id']) && $data['zone_id'] !== '' && $data['zone_id'] !== null) {
            $zone = $this->locationRepository->findZoneById((int)$data['zone_id']);
            if (!$zone) {
                return [
                    'success' => false,
                    'message' => 'Invalid zone selection',
                    'errors' => ['zone_id' => ['Zone not found']],
                    'code' => 'VALIDATION_ERROR'
                ];
            }
        }
        
        // Check uniqueness within country
        if ($this->locationRepository->findStateByNameAndCountry(trim($data['name']), (int)$data['country_id']) !== null) {
            return [
                'success' => false,
                'message' => 'A state with this name already exists in this country',
                'errors' => ['name' => ['State name must be unique within the country']],
                'code' => 'DUPLICATE_ERROR'
            ];
        }
        
        try {
            $stateData = [
                'name' => trim($data['name']),
                'country_id' => (int)$data['country_id'],
                'status' => $data['status'] ?? 'active'
            ];
            
            if (isset($data['zone_id']) && $data['zone_id'] !== '' && $data['zone_id'] !== null) {
                $stateData['zone_id'] = (int)$data['zone_id'];
            }
            
            if ($userId !== null) {
                $stateData['created_by'] = $userId;
            }
            
            $stateId = $this->locationRepository->createState($stateData);
            
            $this->logAction($userId, $stateId, 'state_created', [
                'name' => $stateData['name'],
                'country_id' => $stateData['country_id']
            ]);
            
            $state = $this->locationRepository->findStateById($stateId);
            
            return [
                'success' => true,
                'message' => 'State created successfully',
                'data' => $state
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to create state: ' . $e->getMessage(),
                'code' => 'CREATE_ERROR'
            ];
        }
    }
    
    /**
     * Update an existing state record
     * 
     * @param int $id State ID
     * @param array $data Data to update: name, country_id, zone_id, status
     * @param int|null $userId User ID performing the action (for audit)
     * @return array Result with success status and data/errors
     * 
     * Requirements: 4.3
     */
    public function updateState(int $id, array $data, ?int $userId = null): array {
        $existing = $this->locationRepository->findStateById($id);
        if (!$existing) {
            return [
                'success' => false,
                'message' => 'State not found',
                'code' => 'NOT_FOUND'
            ];
        }
        
        if (isset($data['name']) || isset($data['country_id'])) {
            $validation = $this->validateState($data, $id);
            if (!$validation['valid']) {
                return [
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validation['errors'],
                    'code' => 'VALIDATION_ERROR'
                ];
            }
        }
        
        // Verify country exists if being changed
        $countryId = isset($data['country_id']) ? (int)$data['country_id'] : (int)$existing['country_id'];
        if (isset($data['country_id'])) {
            $country = $this->locationRepository->findCountryById($countryId);
            if (!$country) {
                return [
                    'success' => false,
                    'message' => 'Invalid country selection',
                    'errors' => ['country_id' => ['Country not found']],
                    'code' => 'VALIDATION_ERROR'
                ];
            }
        }
        
        // Verify zone exists if being changed
        if (isset($data['zone_id']) && $data['zone_id'] !== '' && $data['zone_id'] !== null) {
            $zone = $this->locationRepository->findZoneById((int)$data['zone_id']);
            if (!$zone) {
                return [
                    'success' => false,
                    'message' => 'Invalid zone selection',
                    'errors' => ['zone_id' => ['Zone not found']],
                    'code' => 'VALIDATION_ERROR'
                ];
            }
        }
        
        // Check uniqueness within country if name or country is changing
        if (isset($data['name']) || isset($data['country_id'])) {
            $name = isset($data['name']) ? trim($data['name']) : $existing['name'];
            if ($this->locationRepository->findStateByNameAndCountry($name, $countryId, $id) !== null) {
                return [
                    'success' => false,
                    'message' => 'A state with this name already exists in this country',
                    'errors' => ['name' => ['State name must be unique within the country']],
                    'code' => 'DUPLICATE_ERROR'
                ];
            }
        }
        
        try {
            $updateData = [];
            if (isset($data['name'])) {
                $updateData['name'] = trim($data['name']);
            }
            if (isset($data['country_id'])) {
                $updateData['country_id'] = (int)$data['country_id'];
            }
            if (array_key_exists('zone_id', $data)) {
                $updateData['zone_id'] = $data['zone_id'];
            }
            if (isset($data['status'])) {
                $updateData['status'] = $data['status'];
            }
            if ($userId !== null) {
                $updateData['updated_by'] = $userId;
            }
            
            $this->locationRepository->updateState($id, $updateData);
            
            $this->logAction($userId, $id, 'state_updated', [
                'changes' => array_keys($updateData)
            ]);
            
            $state = $this->locationRepository->findStateById($id);
            
            return [
                'success' => true,
                'message' => 'State updated successfully',
                'data' => $state
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to update state: ' . $e->getMessage(),
                'code' => 'UPDATE_ERROR'
            ];
        }
    }
    
    /**
     * Delete a state record (only if no cities exist)
     * 
     * @param int $id State ID
     * @param int|null $userId User ID performing the action (for audit)
     * @return array Result with success status
     * 
     * Requirements: 4.4, 9.3
     */
    public function deleteState(int $id, ?int $userId = null): array {
        $existing = $this->locationRepository->findStateById($id);
        if (!$existing) {
            return [
                'success' => false,
                'message' => 'State not found',
                'code' => 'NOT_FOUND'
            ];
        }
        
        // Check for dependent cities - Referential integrity
        if ($this->hasCityDependencies($id)) {
            $cityCount = $this->locationRepository->countCitiesByState($id);
            return [
                'success' => false,
                'message' => "Cannot delete state with $cityCount associated city(ies)",
                'code' => 'REFERENTIAL_INTEGRITY_ERROR',
                'dependencies' => ['cities' => $cityCount]
            ];
        }
        
        try {
            $this->locationRepository->deleteState($id);
            
            $this->logAction($userId, $id, 'state_deleted', [
                'name' => $existing['name']
            ]);
            
            return [
                'success' => true,
                'message' => 'State deleted successfully'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to delete state: ' . $e->getMessage(),
                'code' => 'DELETE_ERROR'
            ];
        }
    }
    
    /**
     * Check if state has city dependencies
     * 
     * @param int $stateId State ID
     * @return bool True if state has cities
     */
    public function hasCityDependencies(int $stateId): bool {
        return $this->locationRepository->countCitiesByState($stateId) > 0;
    }
    
    /**
     * Get states by country (for cascading dropdowns)
     * 
     * @param int $countryId Country ID
     * @return array Array of state records
     */
    public function getStatesByCountry(int $countryId): array {
        return $this->locationRepository->getStatesByCountry($countryId);
    }
    
    /**
     * Get active states for dropdowns
     * 
     * @return array Array of active state records
     */
    public function getActiveStates(): array {
        return $this->locationRepository->findAllActiveStates();
    }
    
    /**
     * Export states with filters
     * 
     * @param array $filters Optional filters
     * @return array Array of state records
     */
    public function exportStates(array $filters = []): array {
        return $this->locationRepository->findAllStatesForExport($filters);
    }
    
    /**
     * Validate state data
     * 
     * @param array $data Data to validate
     * @param int|null $id State ID (for updates)
     * @return array Validation result
     */
    private function validateState(array $data, ?int $id = null): array {
        $errors = [];
        
        if ($id === null || isset($data['name'])) {
            if (!isset($data['name']) || trim($data['name']) === '') {
                $errors['name'] = ['State name is required'];
            } elseif (strlen(trim($data['name'])) > 100) {
                $errors['name'] = ['State name must not exceed 100 characters'];
            }
        }
        
        if ($id === null) {
            if (!isset($data['country_id']) || (int)$data['country_id'] <= 0) {
                $errors['country_id'] = ['Country is required'];
            }
        }
        
        if (isset($data['status']) && !in_array($data['status'], ['active', 'inactive'], true)) {
            $errors['status'] = ['Status must be active or inactive'];
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    
    // ==================== CITY OPERATIONS ====================
    
    /**
     * Get all cities with filters
     * 
     * @param array $filters Optional filters: search, status, country_id, state_id, zone_id, page, limit, orderBy, orderDir
     * @return array Paginated result with data, total, page, limit, totalPages
     * 
     * Requirements: 6.1
     */
    public function getAllCities(array $filters = []): array {
        return $this->locationRepository->findAllCities($filters);
    }
    
    /**
     * Get city by ID
     * 
     * @param int $id City ID
     * @return array|null City record or null if not found
     */
    public function getCityById(int $id): ?array {
        return $this->locationRepository->findCityById($id);
    }
    
    /**
     * Create a new city record
     * 
     * @param array $data City data: name, state_id, zone_id, status
     * @param int|null $userId User ID performing the action (for audit)
     * @return array Result with success status and data/errors
     * 
     * Requirements: 6.2, 9.1, 9.2
     */
    public function createCity(array $data, ?int $userId = null): array {
        $validation = $this->validateCity($data);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validation['errors'],
                'code' => 'VALIDATION_ERROR'
            ];
        }
        
        // Verify state exists
        $state = $this->locationRepository->findStateById((int)$data['state_id']);
        if (!$state) {
            return [
                'success' => false,
                'message' => 'Invalid state selection',
                'errors' => ['state_id' => ['State not found']],
                'code' => 'VALIDATION_ERROR'
            ];
        }
        
        // Verify zone exists if provided
        if (isset($data['zone_id']) && $data['zone_id'] !== '' && $data['zone_id'] !== null) {
            $zone = $this->locationRepository->findZoneById((int)$data['zone_id']);
            if (!$zone) {
                return [
                    'success' => false,
                    'message' => 'Invalid zone selection',
                    'errors' => ['zone_id' => ['Zone not found']],
                    'code' => 'VALIDATION_ERROR'
                ];
            }
        }
        
        // Check uniqueness within state
        if ($this->locationRepository->findCityByNameAndState(trim($data['name']), (int)$data['state_id']) !== null) {
            return [
                'success' => false,
                'message' => 'A city with this name already exists in this state',
                'errors' => ['name' => ['City name must be unique within the state']],
                'code' => 'DUPLICATE_ERROR'
            ];
        }
        
        try {
            $cityData = [
                'name' => trim($data['name']),
                'state_id' => (int)$data['state_id'],
                'status' => $data['status'] ?? 'active'
            ];
            
            if (isset($data['zone_id']) && $data['zone_id'] !== '' && $data['zone_id'] !== null) {
                $cityData['zone_id'] = (int)$data['zone_id'];
            }
            
            if ($userId !== null) {
                $cityData['created_by'] = $userId;
            }
            
            $cityId = $this->locationRepository->createCity($cityData);
            
            $this->logAction($userId, $cityId, 'city_created', [
                'name' => $cityData['name'],
                'state_id' => $cityData['state_id']
            ]);
            
            $city = $this->locationRepository->findCityById($cityId);
            
            return [
                'success' => true,
                'message' => 'City created successfully',
                'data' => $city
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to create city: ' . $e->getMessage(),
                'code' => 'CREATE_ERROR'
            ];
        }
    }
    
    /**
     * Update an existing city record
     * 
     * @param int $id City ID
     * @param array $data Data to update: name, state_id, zone_id, status
     * @param int|null $userId User ID performing the action (for audit)
     * @return array Result with success status and data/errors
     * 
     * Requirements: 6.3
     */
    public function updateCity(int $id, array $data, ?int $userId = null): array {
        $existing = $this->locationRepository->findCityById($id);
        if (!$existing) {
            return [
                'success' => false,
                'message' => 'City not found',
                'code' => 'NOT_FOUND'
            ];
        }
        
        if (isset($data['name']) || isset($data['state_id'])) {
            $validation = $this->validateCity($data, $id);
            if (!$validation['valid']) {
                return [
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validation['errors'],
                    'code' => 'VALIDATION_ERROR'
                ];
            }
        }
        
        // Verify state exists if being changed
        $stateId = isset($data['state_id']) ? (int)$data['state_id'] : (int)$existing['state_id'];
        if (isset($data['state_id'])) {
            $state = $this->locationRepository->findStateById($stateId);
            if (!$state) {
                return [
                    'success' => false,
                    'message' => 'Invalid state selection',
                    'errors' => ['state_id' => ['State not found']],
                    'code' => 'VALIDATION_ERROR'
                ];
            }
        }
        
        // Verify zone exists if being changed
        if (isset($data['zone_id']) && $data['zone_id'] !== '' && $data['zone_id'] !== null) {
            $zone = $this->locationRepository->findZoneById((int)$data['zone_id']);
            if (!$zone) {
                return [
                    'success' => false,
                    'message' => 'Invalid zone selection',
                    'errors' => ['zone_id' => ['Zone not found']],
                    'code' => 'VALIDATION_ERROR'
                ];
            }
        }
        
        // Check uniqueness within state if name or state is changing
        if (isset($data['name']) || isset($data['state_id'])) {
            $name = isset($data['name']) ? trim($data['name']) : $existing['name'];
            if ($this->locationRepository->findCityByNameAndState($name, $stateId, $id) !== null) {
                return [
                    'success' => false,
                    'message' => 'A city with this name already exists in this state',
                    'errors' => ['name' => ['City name must be unique within the state']],
                    'code' => 'DUPLICATE_ERROR'
                ];
            }
        }
        
        try {
            $updateData = [];
            if (isset($data['name'])) {
                $updateData['name'] = trim($data['name']);
            }
            if (isset($data['state_id'])) {
                $updateData['state_id'] = (int)$data['state_id'];
            }
            if (array_key_exists('zone_id', $data)) {
                $updateData['zone_id'] = $data['zone_id'];
            }
            if (isset($data['status'])) {
                $updateData['status'] = $data['status'];
            }
            if ($userId !== null) {
                $updateData['updated_by'] = $userId;
            }
            
            $this->locationRepository->updateCity($id, $updateData);
            
            $this->logAction($userId, $id, 'city_updated', [
                'changes' => array_keys($updateData)
            ]);
            
            $city = $this->locationRepository->findCityById($id);
            
            return [
                'success' => true,
                'message' => 'City updated successfully',
                'data' => $city
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to update city: ' . $e->getMessage(),
                'code' => 'UPDATE_ERROR'
            ];
        }
    }
    
    /**
     * Soft delete a city record (set status to inactive)
     * 
     * @param int $id City ID
     * @param int|null $userId User ID performing the action (for audit)
     * @return array Result with success status
     * 
     * Requirements: 6.5
     */
    public function deleteCity(int $id, ?int $userId = null): array {
        $existing = $this->locationRepository->findCityById($id);
        if (!$existing) {
            return [
                'success' => false,
                'message' => 'City not found',
                'code' => 'NOT_FOUND'
            ];
        }
        
        try {
            $this->locationRepository->softDeleteCity($id, $userId);
            
            $this->logAction($userId, $id, 'city_deleted', [
                'name' => $existing['name']
            ]);
            
            return [
                'success' => true,
                'message' => 'City deleted successfully'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to delete city: ' . $e->getMessage(),
                'code' => 'DELETE_ERROR'
            ];
        }
    }
    
    /**
     * Get cities by state (for cascading dropdowns)
     * 
     * @param int $stateId State ID
     * @return array Array of city records
     */
    public function getCitiesByState(int $stateId): array {
        return $this->locationRepository->getCitiesByState($stateId);
    }
    
    /**
     * Get active cities for dropdowns
     * 
     * @return array Array of active city records
     */
    public function getActiveCities(): array {
        return $this->locationRepository->findAllActiveCities();
    }
    
    /**
     * Export cities with filters
     * 
     * @param array $filters Optional filters
     * @return array Array of city records
     */
    public function exportCities(array $filters = []): array {
        return $this->locationRepository->findAllCitiesForExport($filters);
    }
    
    /**
     * Validate city data
     * 
     * @param array $data Data to validate
     * @param int|null $id City ID (for updates)
     * @return array Validation result
     */
    private function validateCity(array $data, ?int $id = null): array {
        $errors = [];
        
        if ($id === null || isset($data['name'])) {
            if (!isset($data['name']) || trim($data['name']) === '') {
                $errors['name'] = ['City name is required'];
            } elseif (strlen(trim($data['name'])) > 100) {
                $errors['name'] = ['City name must not exceed 100 characters'];
            }
        }
        
        if ($id === null) {
            if (!isset($data['state_id']) || (int)$data['state_id'] <= 0) {
                $errors['state_id'] = ['State is required'];
            }
        }
        
        if (isset($data['status']) && !in_array($data['status'], ['active', 'inactive'], true)) {
            $errors['status'] = ['Status must be active or inactive'];
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
    
    // ==================== LHO (Local Head Office) OPERATIONS ====================
    
    /**
     * Get all LHOs with pagination
     * 
     * @param array $filters Filter options
     * @return array Paginated LHO records
     */
    public function getAllLhos(array $filters = []): array {
        return $this->locationRepository->findAllLhos($filters);
    }
    
    /**
     * Get LHO by ID
     * 
     * @param int $id LHO ID
     * @return array|null LHO record or null
     */
    public function getLhoById(int $id): ?array {
        return $this->locationRepository->findLhoById($id);
    }
    
    /**
     * Create a new LHO
     * 
     * @param array $data LHO data
     * @param int|null $userId User creating the record
     * @return array Result with success status
     */
    public function createLho(array $data, ?int $userId = null): array {
        // Validate
        $validation = $this->validateLho($data);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'code' => 'VALIDATION_ERROR',
                'message' => 'Validation failed',
                'errors' => $validation['errors']
            ];
        }
        
        // Check for duplicate
        $existing = $this->locationRepository->findLhoByName(trim($data['lho_name']));
        if ($existing) {
            return [
                'success' => false,
                'code' => 'DUPLICATE_ERROR',
                'message' => 'An LHO with this name already exists',
                'errors' => ['lho_name' => ['LHO name must be unique']]
            ];
        }
        
        try {
            $data['created_by'] = $userId;
            $id = $this->locationRepository->createLho($data);
            
            $this->logAction($userId, $id, 'lho_created', [
                'lho_name' => $data['lho_name'],
                'status' => $data['status'] ?? 'active'
            ]);
            
            return [
                'success' => true,
                'message' => 'LHO created successfully',
                'data' => ['id' => $id, 'lho' => $this->getLhoById($id)]
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'code' => 'CREATE_ERROR',
                'message' => 'Failed to create LHO: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Update an LHO
     * 
     * @param int $id LHO ID
     * @param array $data Update data
     * @param int|null $userId User updating the record
     * @return array Result with success status
     */
    public function updateLho(int $id, array $data, ?int $userId = null): array {
        $existing = $this->locationRepository->findLhoById($id);
        if (!$existing) {
            return [
                'success' => false,
                'code' => 'NOT_FOUND',
                'message' => 'LHO not found'
            ];
        }
        
        // Validate
        $validation = $this->validateLho($data, $id);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'code' => 'VALIDATION_ERROR',
                'message' => 'Validation failed',
                'errors' => $validation['errors']
            ];
        }
        
        // Check for duplicate name
        if (isset($data['lho_name'])) {
            $duplicate = $this->locationRepository->findLhoByName(trim($data['lho_name']), $id);
            if ($duplicate) {
                return [
                    'success' => false,
                    'code' => 'DUPLICATE_ERROR',
                    'message' => 'An LHO with this name already exists',
                    'errors' => ['lho_name' => ['LHO name must be unique']]
                ];
            }
        }
        
        try {
            $data['updated_by'] = $userId;
            $this->locationRepository->updateLho($id, $data);
            
            $this->logAction($userId, $id, 'lho_updated', [
                'changes' => array_keys($data)
            ]);
            
            return [
                'success' => true,
                'message' => 'LHO updated successfully',
                'data' => ['lho' => $this->getLhoById($id)]
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'code' => 'UPDATE_ERROR',
                'message' => 'Failed to update LHO: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Delete an LHO
     * 
     * @param int $id LHO ID
     * @param int|null $userId User deleting the record
     * @return array Result with success status
     */
    public function deleteLho(int $id, ?int $userId = null): array {
        $existing = $this->locationRepository->findLhoById($id);
        if (!$existing) {
            return [
                'success' => false,
                'code' => 'NOT_FOUND',
                'message' => 'LHO not found'
            ];
        }
        
        try {
            $this->locationRepository->deleteLho($id);
            
            $this->logAction($userId, $id, 'lho_deleted', [
                'lho_name' => $existing['lho_name']
            ]);
            
            return [
                'success' => true,
                'message' => 'LHO deleted successfully'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'code' => 'DELETE_ERROR',
                'message' => 'Failed to delete LHO: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Get active LHOs for dropdowns
     * 
     * @return array Array of active LHO records
     */
    public function getActiveLhos(): array {
        return $this->locationRepository->findAllActiveLhos();
    }
    
    /**
     * Export LHOs with filters
     * 
     * @param array $filters Optional filters
     * @return array Array of LHO records
     */
    public function exportLhos(array $filters = []): array {
        return $this->locationRepository->findAllLhosForExport($filters);
    }
    
    /**
     * Export LHOs with manager data
     * 
     * @param array $filters Optional filters including 'manager_id'
     * @return array Array of LHO records with manager names
     * 
     * Requirements: 2.4
     */
    public function exportLhosWithManagers(array $filters = []): array {
        // Get base LHO data
        $lhos = $this->locationRepository->findAllLhosForExport($filters);
        
        // If manager_id filter is set, filter LHOs by manager
        if (isset($filters['manager_id']) && (int)$filters['manager_id'] > 0) {
            $managerId = (int)$filters['manager_id'];
            $managedLhoIds = array_column(
                $this->lhoManagerRepository->getLhosByUserId($managerId),
                'lho_id'
            );
            
            // Filter the data to only include LHOs managed by this user
            $lhos = array_filter($lhos, function($lho) use ($managedLhoIds) {
                return in_array((int)$lho['id'], $managedLhoIds);
            });
            
            // Re-index array
            $lhos = array_values($lhos);
        }
        
        // Add manager names to each LHO
        foreach ($lhos as &$lho) {
            $managers = $this->lhoManagerRepository->getManagersByLhoId((int)$lho['id']);
            $lho['managers'] = implode(', ', array_column($managers, 'manager_name'));
            $lho['manager_count'] = count($managers);
        }
        
        // If search term is provided, also include LHOs matching by manager name
        if (isset($filters['search']) && trim($filters['search']) !== '') {
            $searchTerm = strtolower(trim($filters['search']));
            
            // Get all LHOs that have managers matching the search term
            $sql = "SELECT DISTINCT lm.lho_id 
                    FROM lho_managers lm
                    INNER JOIN users u ON lm.user_id = u.id
                    WHERE LOWER(CONCAT(u.first_name, ' ', u.last_name)) LIKE ?";
            $matchingLhoIds = array_column(
                $this->db->getResults($sql, ['%' . $searchTerm . '%'], 's'),
                'lho_id'
            );
            
            // Add LHOs that match by manager name but weren't in the original results
            if (!empty($matchingLhoIds)) {
                $existingIds = array_column($lhos, 'id');
                $newLhoIds = array_diff($matchingLhoIds, $existingIds);
                
                foreach ($newLhoIds as $lhoId) {
                    $lho = $this->locationRepository->findLhoById((int)$lhoId);
                    if ($lho) {
                        // Apply status filter if set
                        if (isset($filters['status']) && $lho['status'] !== $filters['status']) {
                            continue;
                        }
                        
                        // Add manager data
                        $managers = $this->lhoManagerRepository->getManagersByLhoId((int)$lho['id']);
                        $lho['managers'] = implode(', ', array_column($managers, 'manager_name'));
                        $lho['manager_count'] = count($managers);
                        
                        $lhos[] = $lho;
                    }
                }
            }
        }
        
        return $lhos;
    }
    
    /**
     * Validate LHO data
     * 
     * @param array $data Data to validate
     * @param int|null $id LHO ID (for updates)
     * @return array Validation result
     */
    private function validateLho(array $data, ?int $id = null): array {
        $errors = [];
        
        if ($id === null || isset($data['lho_name'])) {
            if (!isset($data['lho_name']) || trim($data['lho_name']) === '') {
                $errors['lho_name'] = ['LHO name is required'];
            } elseif (strlen(trim($data['lho_name'])) > 255) {
                $errors['lho_name'] = ['LHO name must not exceed 255 characters'];
            }
        }
        
        if (isset($data['status']) && !in_array($data['status'], ['active', 'inactive'], true)) {
            $errors['status'] = ['Status must be active or inactive'];
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
    
    // ==================== LHO MANAGER OPERATIONS ====================
    
    /**
     * Get all active ADV users for manager dropdown
     * Returns users who belong to ADV company type and are active
     * 
     * @return array Array of ADV users with id, first_name, last_name, email
     * 
     * Requirements: 1.1
     */
    public function getActiveAdvUsers(): array {
        $sql = "SELECT u.id, u.first_name, u.last_name, u.email
                FROM users u
                INNER JOIN companies c ON u.company_id = c.id
                WHERE c.type = 'ADV' AND u.status = 1
                ORDER BY u.first_name, u.last_name";
        
        return $this->db->getResults($sql, [], '');
    }
    
    /**
     * Validate manager assignments
     * Validates that each user ID exists, is active, and belongs to an ADV company
     * 
     * @param array $userIds Array of user IDs to validate
     * @return array Validation result with 'valid' boolean and 'errors' array
     * 
     * Requirements: 4.3, 4.4
     */
    public function validateManagerAssignments(array $userIds): array {
        $errors = [];
        $validIds = [];
        
        if (empty($userIds)) {
            return [
                'valid' => true,
                'errors' => [],
                'valid_ids' => []
            ];
        }
        
        foreach ($userIds as $userId) {
            $userId = (int)$userId;
            
            if ($userId <= 0) {
                $errors[] = [
                    'user_id' => $userId,
                    'code' => 'INVALID_MANAGER',
                    'message' => "User ID {$userId} is not a valid manager"
                ];
                continue;
            }
            
            // Check if user exists, is active, and belongs to ADV company
            $sql = "SELECT u.id, u.status, c.type as company_type
                    FROM users u
                    INNER JOIN companies c ON u.company_id = c.id
                    WHERE u.id = ?";
            
            $result = $this->db->getResults($sql, [$userId], 'i');
            
            if (empty($result)) {
                $errors[] = [
                    'user_id' => $userId,
                    'code' => 'INVALID_MANAGER',
                    'message' => "User ID {$userId} is not a valid manager"
                ];
                continue;
            }
            
            $user = $result[0];
            
            // Check if user is active (status = 1)
            if ((int)$user['status'] !== 1) {
                $errors[] = [
                    'user_id' => $userId,
                    'code' => 'INACTIVE_USER',
                    'message' => "User ID {$userId} is inactive"
                ];
                continue;
            }
            
            // Check if user belongs to ADV company
            if (strtoupper($user['company_type']) !== 'ADV') {
                $errors[] = [
                    'user_id' => $userId,
                    'code' => 'NON_ADV_USER',
                    'message' => "User ID {$userId} is not an ADV user"
                ];
                continue;
            }
            
            $validIds[] = $userId;
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'valid_ids' => $validIds
        ];
    }
    
    /**
     * Sync LHO managers
     * Validates manager assignments and syncs them to the database
     * 
     * @param int $lhoId LHO ID
     * @param array $userIds Array of user IDs to assign as managers
     * @param int $actingUserId User performing the action
     * @return array Result with success status and data/errors
     * 
     * Requirements: 1.3, 1.4
     */
    public function syncLhoManagers(int $lhoId, array $userIds, int $actingUserId): array {
        // Verify LHO exists
        $lho = $this->locationRepository->findLhoById($lhoId);
        if (!$lho) {
            return [
                'success' => false,
                'code' => 'NOT_FOUND',
                'message' => 'LHO not found'
            ];
        }
        
        // Validate manager assignments
        $validation = $this->validateManagerAssignments($userIds);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'code' => 'VALIDATION_ERROR',
                'message' => 'Invalid manager assignments',
                'errors' => $validation['errors']
            ];
        }
        
        try {
            // Sync managers via repository
            $this->lhoManagerRepository->syncManagers($lhoId, $validation['valid_ids'], $actingUserId);
            
            // Log the action
            $this->logAction($actingUserId, $lhoId, 'lho_managers_synced', [
                'lho_name' => $lho['lho_name'],
                'manager_count' => count($validation['valid_ids']),
                'manager_ids' => $validation['valid_ids']
            ]);
            
            // Get updated manager list
            $managers = $this->lhoManagerRepository->getManagersByLhoId($lhoId);
            
            return [
                'success' => true,
                'message' => 'Managers updated successfully',
                'data' => [
                    'lho_id' => $lhoId,
                    'managers' => $managers,
                    'manager_count' => count($managers)
                ]
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'code' => 'SYNC_ERROR',
                'message' => 'Failed to sync managers: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Get all LHOs with manager data
     * Extends getAllLhos to include manager information
     * Supports searching by manager names and filtering by manager_id
     * 
     * @param array $filters Filter options including 'include_managers', 'manager_id', and 'search'
     * @return array Paginated LHO records with manager data
     * 
     * Requirements: 2.1, 2.2, 5.3
     */
    public function getAllLhosWithManagers(array $filters = []): array {
        // Get base LHO data
        $result = $this->locationRepository->findAllLhos($filters);
        
        // If manager_id filter is set, filter LHOs by manager
        if (isset($filters['manager_id']) && (int)$filters['manager_id'] > 0) {
            $managerId = (int)$filters['manager_id'];
            $managedLhoIds = array_column(
                $this->lhoManagerRepository->getLhosByUserId($managerId),
                'lho_id'
            );
            
            // Filter the data to only include LHOs managed by this user
            $result['data'] = array_filter($result['data'], function($lho) use ($managedLhoIds) {
                return in_array((int)$lho['id'], $managedLhoIds);
            });
            
            // Re-index array and update total
            $result['data'] = array_values($result['data']);
            $result['total'] = count($result['data']);
        }
        
        // Add manager data to each LHO
        foreach ($result['data'] as &$lho) {
            $managers = $this->lhoManagerRepository->getManagersByLhoId((int)$lho['id']);
            $lho['managers'] = $managers;
            $lho['manager_names'] = array_column($managers, 'manager_name');
            $lho['manager_ids'] = array_map('intval', array_column($managers, 'user_id'));
        }
        
        // If search term is provided, also search by manager names
        if (isset($filters['search']) && trim($filters['search']) !== '') {
            $searchTerm = strtolower(trim($filters['search']));
            
            // Get all LHOs that have managers matching the search term
            $sql = "SELECT DISTINCT lm.lho_id 
                    FROM lho_managers lm
                    INNER JOIN users u ON lm.user_id = u.id
                    WHERE LOWER(CONCAT(u.first_name, ' ', u.last_name)) LIKE ?";
            $matchingLhoIds = array_column(
                $this->db->getResults($sql, ['%' . $searchTerm . '%'], 's'),
                'lho_id'
            );
            
            // Add LHOs that match by manager name but weren't in the original results
            if (!empty($matchingLhoIds)) {
                $existingIds = array_column($result['data'], 'id');
                $newLhoIds = array_diff($matchingLhoIds, $existingIds);
                
                foreach ($newLhoIds as $lhoId) {
                    $lho = $this->locationRepository->findLhoById((int)$lhoId);
                    if ($lho) {
                        // Apply status filter if set
                        if (isset($filters['status']) && $lho['status'] !== $filters['status']) {
                            continue;
                        }
                        
                        // Add manager data
                        $managers = $this->lhoManagerRepository->getManagersByLhoId((int)$lho['id']);
                        $lho['managers'] = $managers;
                        $lho['manager_names'] = array_column($managers, 'manager_name');
                        $lho['manager_ids'] = array_map('intval', array_column($managers, 'user_id'));
                        
                        $result['data'][] = $lho;
                    }
                }
                
                // Update total count
                $result['total'] = count($result['data']);
            }
        }
        
        return $result;
    }
    
    /**
     * Get LHOs managed by a specific user
     * 
     * @param int $userId User ID
     * @return array Array of LHO records managed by the user
     * 
     * Requirements: 3.1, 3.2
     */
    public function getLhosByManager(int $userId): array {
        return $this->lhoManagerRepository->getLhosByUserId($userId);
    }
    
    /**
     * Get LHO with manager data by ID
     * 
     * @param int $id LHO ID
     * @return array|null LHO record with manager data or null
     * 
     * Requirements: 2.1, 2.2
     */
    public function getLhoWithManagers(int $id): ?array {
        $lho = $this->locationRepository->findLhoById($id);
        
        if (!$lho) {
            return null;
        }
        
        // Add manager data
        $managers = $this->lhoManagerRepository->getManagersByLhoId($id);
        $lho['managers'] = $managers;
        $lho['manager_names'] = array_column($managers, 'manager_name');
        $lho['manager_ids'] = array_map('intval', array_column($managers, 'user_id'));
        
        return $lho;
    }
    
    // ==================== AUDIT LOGGING ====================
    
    /**
     * Log action for audit trail
     * 
     * @param int|null $userId User performing the action
     * @param int $entityId Entity ID
     * @param string $action Action type
     * @param array $details Additional details
     */
    private function logAction(?int $userId, int $entityId, string $action, array $details): void {
        try {
            $sql = "INSERT INTO user_audit_log (user_id, action, details, performed_by, ip_address) 
                    VALUES (?, ?, ?, ?, ?)";
            
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'CLI';
            $details['entity_id'] = $entityId;
            
            $stmt = $this->db->executeQuery($sql, [
                $userId ?? 0,
                $action,
                json_encode($details),
                $userId ?? 0,
                $ipAddress
            ], 'issis');
            $stmt->close();
        } catch (Exception $e) {
            error_log("Failed to log location action: " . $e->getMessage());
        }
    }
}
