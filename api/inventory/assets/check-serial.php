<?php
/**
 * Inventory API - Check Serial Number Uniqueness
 * GET/POST /api/inventory/assets/check-serial.php
 * 
 * Validates whether serial numbers are unique and not already registered in assets
 * 
 * Request (GET):
 * ?serial_number=ABC123
 * 
 * Request (POST JSON):
 * {
 *   "serial_numbers": ["ABC123", "XYZ789"]
 * }
 * 
 * Response:
 * {
 *   "success": true,
 *   "data": {
 *     "valid": bool,
 *     "existing": ["ABC123"],
 *     "duplicates_in_input": ["ABC123"],
 *     "details": [
 *       { "serial_number": "ABC123", "exists": true, "product_name": "Battery 12V", "status": "in_stock" }
 *     ]
 *   }
 * }
 */

require_once __DIR__ . '/../../../config/autoload.php';
require_once __DIR__ . '/../../ApiResponse.php';
require_once __DIR__ . '/../../../middleware/ApiAuthMiddleware.php';
require_once __DIR__ . '/../../../repositories/AssetRepository.php';

// Handle CORS
ApiResponse::setCorsHeaders();
ApiResponse::handlePreflight();

if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'])) {
    ApiResponse::methodNotAllowed(['GET', 'POST']);
}

try {
    $authMiddleware = new ApiAuthMiddleware();
    $authMiddleware->checkRateLimit();
    $currentUser = $authMiddleware->requireAuth();
    
    $serialNumbers = [];
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $singleSerial = trim($_GET['serial_number'] ?? '');
        if ($singleSerial !== '') {
            $serialNumbers[] = $singleSerial;
        }
    } else {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            $input = $_POST ?? [];
        }
        
        if (!empty($input['serial_numbers']) && is_array($input['serial_numbers'])) {
            $serialNumbers = $input['serial_numbers'];
        } elseif (!empty($input['serial_number'])) {
            $serialNumbers = [$input['serial_number']];
        }
    }
    
    $cleanSerials = [];
    $duplicatesInInput = [];
    $seen = [];
    
    foreach ($serialNumbers as $sn) {
        $trimmed = trim((string)$sn);
        if ($trimmed === '') continue;
        if (isset($seen[$trimmed])) {
            $duplicatesInInput[] = $trimmed;
        } else {
            $seen[$trimmed] = true;
            $cleanSerials[] = $trimmed;
        }
    }
    
    $duplicatesInInput = array_values(array_unique($duplicatesInInput));
    
    if (empty($cleanSerials)) {
        ApiResponse::success([
            'valid' => true,
            'existing' => [],
            'duplicates_in_input' => [],
            'details' => []
        ]);
        exit;
    }
    
    $db = DatabaseConfig::getInstance();
    
    // Query database for matching serial numbers
    $placeholders = str_repeat('?,', count($cleanSerials) - 1) . '?';
    $types = str_repeat('s', count($cleanSerials));
    
    $sql = "SELECT a.serial_number, a.product_id, a.status, p.name as product_name, w.name as warehouse_name
            FROM assets a
            LEFT JOIN products p ON a.product_id = p.id
            LEFT JOIN warehouses w ON a.warehouse_id = w.id
            WHERE a.serial_number IN ($placeholders)";
            
    $existingRows = $db->getResults($sql, $cleanSerials, $types);
    
    $existingMap = [];
    $existingList = [];
    foreach ($existingRows as $row) {
        $snStr = (string)$row['serial_number'];
        $existingMap[strtolower($snStr)] = $row;
        $existingList[] = $snStr;
    }
    
    $existingList = array_values(array_unique(array_map('strval', $existingList)));
    $duplicatesInInput = array_values(array_unique(array_map('strval', $duplicatesInInput)));
    
    $details = [];
    foreach ($cleanSerials as $sn) {
        $key = strtolower($sn);
        if (isset($existingMap[$key])) {
            $row = $existingMap[$key];
            $details[] = [
                'serial_number' => (string)$sn,
                'exists' => true,
                'product_name' => $row['product_name'] ?? 'Unknown Product',
                'warehouse_name' => $row['warehouse_name'] ?? 'Unknown Warehouse',
                'status' => $row['status'] ?? 'in_stock'
            ];
        } else {
            $details[] = [
                'serial_number' => (string)$sn,
                'exists' => false
            ];
        }
    }
    
    $isValid = empty($existingList) && empty($duplicatesInInput);
    
    ApiResponse::success([
        'valid' => $isValid,
        'existing' => $existingList,
        'duplicates_in_input' => $duplicatesInInput,
        'details' => $details
    ], $isValid ? 'All serial numbers are unique and available' : 'Some serial numbers are duplicate or already exist');
    
} catch (Throwable $e) {
    error_log("Serial Check API Error: " . $e->getMessage());
    ApiResponse::serverError('Failed to check serial numbers: ' . $e->getMessage());
}
