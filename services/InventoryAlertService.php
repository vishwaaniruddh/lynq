<?php
/**
 * Inventory Alert Service
 * Manages stock alerts including low stock and overdue repair alerts
 * 
 * Requirements: 13.1, 13.3, 13.4
 * - 13.1: Generate low stock alert when product stock falls below defined threshold
 * - 13.3: Display product name, current quantity, threshold, and warehouse location
 * - 13.4: Automatically clear alert when stock is replenished above threshold
 */

require_once __DIR__ . '/../config/autoload.php';
require_once __DIR__ . '/../repositories/StockAlertRepository.php';
require_once __DIR__ . '/../repositories/StockRepository.php';
require_once __DIR__ . '/../repositories/ProductRepository.php';
require_once __DIR__ . '/../repositories/WarehouseRepository.php';
require_once __DIR__ . '/../repositories/AssetRepository.php';

class InventoryAlertService {
    private $db;
    private $alertRepository;
    private $stockRepository;
    private $productRepository;
    private $warehouseRepository;
    private $assetRepository;
    
    // Alert type constants
    const TYPE_LOW_STOCK = 'low_stock';
    const TYPE_OVERDUE_REPAIR = 'overdue_repair';
    
    // Status constants
    const STATUS_ACTIVE = 'active';
    const STATUS_CLEARED = 'cleared';
    
    public function __construct() {
        $this->db = DatabaseConfig::getInstance();
        $this->alertRepository = new StockAlertRepository();
        $this->stockRepository = new StockRepository();
        $this->productRepository = new ProductRepository();
        $this->warehouseRepository = new WarehouseRepository();
        $this->warehouseRepository->disableCompanyFilter();
        $this->assetRepository = new AssetRepository();
    }
    
    /**
     * Check low stock for a specific product in a warehouse
     * Requirement 13.1: Generate low stock alert when product stock falls below defined threshold
     * 
     * @param int $productId Product ID
     * @param int $warehouseId Warehouse ID
     * @return array Result with alert status
     */
    public function checkLowStock(int $productId, int $warehouseId): array {
        // Get product details
        $product = $this->productRepository->find($productId);
        if (!$product) {
            return [
                'success' => false,
                'message' => 'Product not found',
                'code' => 'PRODUCT_NOT_FOUND'
            ];
        }
        
        // Get warehouse details
        $warehouse = $this->warehouseRepository->find($warehouseId);
        if (!$warehouse) {
            return [
                'success' => false,
                'message' => 'Warehouse not found',
                'code' => 'WAREHOUSE_NOT_FOUND'
            ];
        }
        
        // Get threshold (product-specific or default)
        $threshold = $this->getThreshold($productId, $warehouseId);
        
        // Get current stock level
        $currentStock = $this->getCurrentStock($productId, $warehouseId, $product['is_serializable']);
        
        // Check if stock is below threshold
        $isLowStock = $currentStock < $threshold;
        
        if ($isLowStock) {
            // Generate or update alert
            $alertResult = $this->generateAlert(
                $productId,
                $warehouseId,
                self::TYPE_LOW_STOCK,
                $currentStock,
                $threshold
            );
            
            return [
                'success' => true,
                'is_low_stock' => true,
                'current_stock' => $currentStock,
                'threshold' => $threshold,
                'alert' => $alertResult['data'] ?? null,
                'message' => "Low stock alert: {$product['name']} has $currentStock units (threshold: $threshold)"
            ];
        } else {
            // Clear any existing alert
            $this->clearAlertForProductWarehouse($productId, $warehouseId, self::TYPE_LOW_STOCK);
            
            return [
                'success' => true,
                'is_low_stock' => false,
                'current_stock' => $currentStock,
                'threshold' => $threshold,
                'message' => "Stock level OK: {$product['name']} has $currentStock units (threshold: $threshold)"
            ];
        }
    }
    
    /**
     * Check low stock for all products in all warehouses
     * 
     * @return array Results with all alerts generated
     */
    public function checkAllLowStock(): array {
        $results = [
            'success' => true,
            'alerts_generated' => 0,
            'alerts_cleared' => 0,
            'products_checked' => 0,
            'alerts' => []
        ];
        
        try {
            // Get all active products
            $products = $this->productRepository->findAll(['status' => 'active']);
            
            // Get all active warehouses
            $warehouses = $this->warehouseRepository->findAll(['status' => 'active']);
            
            foreach ($products as $product) {
                foreach ($warehouses as $warehouse) {
                    $results['products_checked']++;
                    
                    $checkResult = $this->checkLowStock($product['id'], $warehouse['id']);
                    
                    if ($checkResult['success'] && $checkResult['is_low_stock']) {
                        $results['alerts_generated']++;
                        $results['alerts'][] = [
                            'product_id' => $product['id'],
                            'product_name' => $product['name'],
                            'warehouse_id' => $warehouse['id'],
                            'warehouse_name' => $warehouse['name'],
                            'current_stock' => $checkResult['current_stock'],
                            'threshold' => $checkResult['threshold']
                        ];
                    }
                }
            }
            
            return $results;
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to check low stock: ' . $e->getMessage(),
                'code' => 'CHECK_ERROR'
            ];
        }
    }

    
    /**
     * Generate an alert
     * 
     * @param int $productId Product ID
     * @param int $warehouseId Warehouse ID
     * @param string $alertType Alert type
     * @param int $currentValue Current value
     * @param int $thresholdValue Threshold value
     * @return array Result with alert data
     */
    public function generateAlert(int $productId, int $warehouseId, string $alertType, int $currentValue, int $thresholdValue): array {
        // Validate alert type
        if (!$this->isValidAlertType($alertType)) {
            return [
                'success' => false,
                'message' => "Invalid alert type: $alertType",
                'code' => 'INVALID_ALERT_TYPE'
            ];
        }
        
        try {
            // Check if alert already exists
            $existingAlert = $this->alertRepository->findActiveAlert($productId, $warehouseId, $alertType);
            
            if ($existingAlert) {
                // Update existing alert
                $this->alertRepository->update($existingAlert['id'], [
                    'current_value' => $currentValue,
                    'threshold_value' => $thresholdValue
                ]);
                
                $alert = $this->alertRepository->find($existingAlert['id']);
                
                return [
                    'success' => true,
                    'message' => 'Alert updated',
                    'data' => $alert,
                    'is_new' => false
                ];
            } else {
                // Create new alert
                $alert = $this->alertRepository->create([
                    'product_id' => $productId,
                    'warehouse_id' => $warehouseId,
                    'alert_type' => $alertType,
                    'current_value' => $currentValue,
                    'threshold_value' => $thresholdValue,
                    'status' => self::STATUS_ACTIVE
                ]);
                
                return [
                    'success' => true,
                    'message' => 'Alert generated',
                    'data' => $alert,
                    'is_new' => true
                ];
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to generate alert: ' . $e->getMessage(),
                'code' => 'GENERATE_ERROR'
            ];
        }
    }
    
    /**
     * Clear an alert by ID
     * 
     * @param int $alertId Alert ID
     * @param int|null $clearedBy User ID who cleared the alert
     * @return array Result
     */
    public function clearAlert(int $alertId, ?int $clearedBy = null): array {
        try {
            $alert = $this->alertRepository->find($alertId);
            
            if (!$alert) {
                return [
                    'success' => false,
                    'message' => 'Alert not found',
                    'code' => 'ALERT_NOT_FOUND'
                ];
            }
            
            if ($alert['status'] === self::STATUS_CLEARED) {
                return [
                    'success' => true,
                    'message' => 'Alert already cleared',
                    'data' => $alert
                ];
            }
            
            $this->alertRepository->clearAlert($alertId, $clearedBy);
            
            $updatedAlert = $this->alertRepository->find($alertId);
            
            return [
                'success' => true,
                'message' => 'Alert cleared',
                'data' => $updatedAlert
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to clear alert: ' . $e->getMessage(),
                'code' => 'CLEAR_ERROR'
            ];
        }
    }
    
    /**
     * Clear alert for a specific product and warehouse
     * Requirement 13.4: Automatically clear alert when stock is replenished above threshold
     * 
     * @param int $productId Product ID
     * @param int $warehouseId Warehouse ID
     * @param string $alertType Alert type
     * @param int|null $clearedBy User ID who cleared the alert
     * @return array Result
     */
    public function clearAlertForProductWarehouse(int $productId, int $warehouseId, string $alertType, ?int $clearedBy = null): array {
        try {
            $existingAlert = $this->alertRepository->findActiveAlert($productId, $warehouseId, $alertType);
            
            if (!$existingAlert) {
                return [
                    'success' => true,
                    'message' => 'No active alert found',
                    'data' => null
                ];
            }
            
            return $this->clearAlert($existingAlert['id'], $clearedBy);
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to clear alert: ' . $e->getMessage(),
                'code' => 'CLEAR_ERROR'
            ];
        }
    }
    
    /**
     * Get all active alerts
     * 
     * @return array Active alerts
     */
    public function getActiveAlerts(): array {
        return $this->alertRepository->findAllWithDetails(['status' => self::STATUS_ACTIVE]);
    }
    
    /**
     * Get active alerts by type
     * 
     * @param string $alertType Alert type
     * @return array Active alerts of the specified type
     */
    public function getActiveAlertsByType(string $alertType): array {
        return $this->alertRepository->findActiveByType($alertType);
    }
    
    /**
     * Get active low stock alerts
     * Requirement 13.3: Display product name, current quantity, threshold, and warehouse location
     * 
     * @return array Low stock alerts with details
     */
    public function getLowStockAlerts(): array {
        return $this->getActiveAlertsByType(self::TYPE_LOW_STOCK);
    }
    
    /**
     * Get overdue repair alerts
     * 
     * @return array Overdue repair alerts
     */
    public function getOverdueRepairAlerts(): array {
        return $this->getActiveAlertsByType(self::TYPE_OVERDUE_REPAIR);
    }
    
    /**
     * Get alert with full details
     * 
     * @param int $alertId Alert ID
     * @return array|null Alert with details or null if not found
     */
    public function getAlertDetails(int $alertId): ?array {
        return $this->alertRepository->findWithDetails($alertId);
    }
    
    /**
     * Get alerts by company
     * 
     * @param int $companyId Company ID
     * @return array Alerts for the company
     */
    public function getAlertsByCompany(int $companyId): array {
        return $this->alertRepository->findByCompany($companyId);
    }
    
    /**
     * Count active alerts
     * 
     * @return int Number of active alerts
     */
    public function countActiveAlerts(): int {
        return $this->alertRepository->countActive();
    }
    
    /**
     * Count active alerts by type
     * 
     * @param string $alertType Alert type
     * @return int Number of active alerts of the specified type
     */
    public function countActiveAlertsByType(string $alertType): int {
        return $this->alertRepository->countActiveByType($alertType);
    }

    
    /**
     * Check for overdue repairs and generate alerts
     * 
     * @return array Results with alerts generated
     */
    public function checkOverdueRepairs(): array {
        $results = [
            'success' => true,
            'alerts_generated' => 0,
            'repairs_checked' => 0,
            'alerts' => []
        ];
        
        try {
            // Get repairs that are overdue (expected_return_date < today and status is not completed)
            $sql = "SELECT r.*, a.product_id, a.serial_number, a.warehouse_id
                    FROM repairs r
                    JOIN assets a ON r.asset_id = a.id
                    WHERE r.expected_return_date < CURDATE()
                    AND r.status NOT IN ('completed', 'cancelled')";
            
            $overdueRepairs = $this->db->getResults($sql);
            
            foreach ($overdueRepairs as $repair) {
                $results['repairs_checked']++;
                
                // Generate alert for overdue repair
                $alertResult = $this->generateAlert(
                    $repair['product_id'],
                    $repair['warehouse_id'] ?? 0,
                    self::TYPE_OVERDUE_REPAIR,
                    (int) ((strtotime('now') - strtotime($repair['expected_return_date'])) / 86400), // Days overdue
                    0 // No threshold for overdue repairs
                );
                
                if ($alertResult['success'] && ($alertResult['is_new'] ?? false)) {
                    $results['alerts_generated']++;
                    $results['alerts'][] = [
                        'repair_id' => $repair['id'],
                        'asset_id' => $repair['asset_id'],
                        'serial_number' => $repair['serial_number'],
                        'expected_return_date' => $repair['expected_return_date'],
                        'days_overdue' => $alertResult['data']['current_value'] ?? 0
                    ];
                }
            }
            
            return $results;
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to check overdue repairs: ' . $e->getMessage(),
                'code' => 'CHECK_ERROR'
            ];
        }
    }
    
    /**
     * Get threshold for a product in a warehouse
     * 
     * @param int $productId Product ID
     * @param int $warehouseId Warehouse ID
     * @return int Threshold value
     */
    private function getThreshold(int $productId, int $warehouseId): int {
        // First check for product-warehouse specific threshold
        $sql = "SELECT threshold_quantity FROM stock_thresholds 
                WHERE product_id = ? AND warehouse_id = ?
                LIMIT 1";
        $result = $this->db->getResults($sql, [$productId, $warehouseId], 'ii');
        
        if (!empty($result)) {
            return (int) $result[0]['threshold_quantity'];
        }
        
        // Check for product-level threshold (warehouse_id is NULL)
        $sql = "SELECT threshold_quantity FROM stock_thresholds 
                WHERE product_id = ? AND warehouse_id IS NULL
                LIMIT 1";
        $result = $this->db->getResults($sql, [$productId], 'i');
        
        if (!empty($result)) {
            return (int) $result[0]['threshold_quantity'];
        }
        
        // Fall back to product's default low_stock_threshold
        $product = $this->productRepository->find($productId);
        return (int) ($product['low_stock_threshold'] ?? 0);
    }
    
    /**
     * Get current stock level for a product in a warehouse
     * 
     * @param int $productId Product ID
     * @param int $warehouseId Warehouse ID
     * @param bool $isSerializable Whether the product is serializable
     * @return int Current stock level
     */
    private function getCurrentStock(int $productId, int $warehouseId, bool $isSerializable): int {
        if ($isSerializable) {
            // Count assets in stock at warehouse
            $sql = "SELECT COUNT(*) as count FROM assets 
                    WHERE product_id = ? AND warehouse_id = ? AND status = 'in_stock'";
            $result = $this->db->getResults($sql, [$productId, $warehouseId], 'ii');
            return (int) ($result[0]['count'] ?? 0);
        } else {
            // Get quantity from stock table
            return $this->stockRepository->getAvailableQuantity($productId, $warehouseId);
        }
    }
    
    /**
     * Set threshold for a product in a warehouse
     * 
     * @param int $productId Product ID
     * @param int|null $warehouseId Warehouse ID (null for product-level threshold)
     * @param int $threshold Threshold value
     * @param int|null $createdBy User ID
     * @return array Result
     */
    public function setThreshold(int $productId, ?int $warehouseId, int $threshold, ?int $createdBy = null): array {
        try {
            // Check if threshold already exists
            if ($warehouseId !== null) {
                $sql = "SELECT id FROM stock_thresholds WHERE product_id = ? AND warehouse_id = ?";
                $result = $this->db->getResults($sql, [$productId, $warehouseId], 'ii');
            } else {
                $sql = "SELECT id FROM stock_thresholds WHERE product_id = ? AND warehouse_id IS NULL";
                $result = $this->db->getResults($sql, [$productId], 'i');
            }
            
            if (!empty($result)) {
                // Update existing threshold
                $sql = "UPDATE stock_thresholds SET threshold_quantity = ? WHERE id = ?";
                $this->db->executeQuery($sql, [$threshold, $result[0]['id']], 'ii');
                
                return [
                    'success' => true,
                    'message' => 'Threshold updated',
                    'threshold_id' => $result[0]['id']
                ];
            } else {
                // Create new threshold
                $sql = "INSERT INTO stock_thresholds (product_id, warehouse_id, threshold_quantity, created_by) VALUES (?, ?, ?, ?)";
                $this->db->executeQuery($sql, [$productId, $warehouseId, $threshold, $createdBy], 'iiii');
                
                return [
                    'success' => true,
                    'message' => 'Threshold created',
                    'threshold_id' => $this->db->getConnection()->insert_id
                ];
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to set threshold: ' . $e->getMessage(),
                'code' => 'SET_THRESHOLD_ERROR'
            ];
        }
    }
    
    /**
     * Check if alert type is valid
     * 
     * @param string $alertType Alert type to validate
     * @return bool True if valid
     */
    private function isValidAlertType(string $alertType): bool {
        return in_array($alertType, [self::TYPE_LOW_STOCK, self::TYPE_OVERDUE_REPAIR]);
    }
    
    /**
     * Get all valid alert types
     * 
     * @return array Valid alert types
     */
    public static function getAlertTypes(): array {
        return [self::TYPE_LOW_STOCK, self::TYPE_OVERDUE_REPAIR];
    }
}
