<?php
/**
 * Inventory API - Acknowledge Dispatch Receipt
 * POST /api/inventory/dispatch/acknowledge.php
 * 
 * Acknowledges receipt of a dispatch with photo/video proof
 * 
 * Request: multipart/form-data
 * - dispatch_id: int (required)
 * - notes: string (optional)
 * - condition: string (optional) - good, minor_damage, damaged, missing
 * - proof_files[]: files (required) - photos or videos as proof
 * 
 * Response: { success: bool, data: { dispatch_id: int, acknowledgment_status: string } }
 */

require_once __DIR__ . '/../../../config/autoload.php';
require_once __DIR__ . '/../../ApiResponse.php';
require_once __DIR__ . '/../../../middleware/ApiAuthMiddleware.php';
require_once __DIR__ . '/../../../services/DispatchService.php';
require_once __DIR__ . '/../../../services/InventoryAccessService.php';
require_once __DIR__ . '/../../../repositories/DispatchRepository.php';

// Handle CORS
ApiResponse::setCorsHeaders();
ApiResponse::handlePreflight();

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ApiResponse::methodNotAllowed(['POST']);
}

try {
    $authMiddleware = new ApiAuthMiddleware();
    $authMiddleware->checkRateLimit();
    $user = $authMiddleware->requireAuth();
    
    // Check if this is a multipart form or JSON request
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    $isMultipart = strpos($contentType, 'multipart/form-data') !== false;
    
    if ($isMultipart) {
        // Handle multipart form data with file uploads
        $dispatchId = isset($_POST['dispatch_id']) ? (int)$_POST['dispatch_id'] : 0;
        $notes = $_POST['notes'] ?? '';
        $condition = $_POST['condition'] ?? 'good';
    } else {
        // Handle JSON input (backward compatibility)
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            ApiResponse::validationError(['body' => 'Invalid request body']);
        }
        $dispatchId = isset($input['dispatch_id']) ? (int)$input['dispatch_id'] : 0;
        $notes = $input['notes'] ?? '';
        $condition = $input['condition'] ?? 'good';
    }
    
    // Validate required fields
    if (!$dispatchId) {
        ApiResponse::validationError(['dispatch_id' => 'Dispatch ID is required']);
    }
    
    $dispatchService = new DispatchService();
    $inventoryAccessService = new InventoryAccessService();
    
    // Get dispatch details
    $dispatch = $dispatchService->getDispatch($dispatchId);
    
    if (!$dispatch) {
        ApiResponse::notFound('Dispatch not found');
    }
    
    // Check user can acknowledge this dispatch
    $canAcknowledge = false;
    
    if ($dispatch['to_user_id'] == $user['id']) {
        $canAcknowledge = true;
    } elseif ($dispatch['to_company_id'] == $user['company_id'] && $user['company_type'] !== 'ADV') {
        $canAcknowledge = true;
    } elseif ($user['company_type'] === 'ADV' && !empty($dispatch['to_warehouse_id'])) {
        $accessibleWarehouses = $inventoryAccessService->getAccessibleWarehouses($user['id']);
        $accessibleWarehouseIds = array_column($accessibleWarehouses, 'id');
        $canAcknowledge = in_array($dispatch['to_warehouse_id'], $accessibleWarehouseIds);
    }
    
    if (!$canAcknowledge) {
        ApiResponse::forbidden('You do not have permission to acknowledge this dispatch');
    }
    
    // Check dispatch status - allow both delivered and in_transit
    if (!in_array($dispatch['status'], [DispatchRepository::STATUS_DELIVERED, DispatchRepository::STATUS_IN_TRANSIT])) {
        ApiResponse::error('INVALID_STATUS', 'Dispatch must be delivered or in transit before acknowledgment', 400, [
            'current_status' => $dispatch['status']
        ]);
    }
    
    // Check if already acknowledged
    if (!empty($dispatch['acknowledged_at'])) {
        ApiResponse::error('ALREADY_ACKNOWLEDGED', 'Dispatch has already been acknowledged', 400, [
            'acknowledged_at' => $dispatch['acknowledged_at']
        ]);
    }
    
    // Handle file uploads
    $uploadedFiles = [];
    $uploadDir = __DIR__ . '/../../../uploads/dispatch_acknowledgments/' . $dispatchId . '/';
    
    if ($isMultipart && !empty($_FILES)) {
        // Create upload directory if it doesn't exist
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        // Process uploaded files
        $allowedImageTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $allowedVideoTypes = ['video/mp4', 'video/webm', 'video/quicktime', 'video/x-msvideo'];
        $allowedTypes = array_merge($allowedImageTypes, $allowedVideoTypes);
        $maxImageSize = 10 * 1024 * 1024; // 10MB for images
        $maxVideoSize = 50 * 1024 * 1024; // 50MB for videos
        
        // Handle both proof_files[] array and individual files
        $files = [];
        if (isset($_FILES['proof_files'])) {
            // Normalize file array structure
            if (is_array($_FILES['proof_files']['name'])) {
                for ($i = 0; $i < count($_FILES['proof_files']['name']); $i++) {
                    if ($_FILES['proof_files']['error'][$i] === UPLOAD_ERR_OK) {
                        $files[] = [
                            'name' => $_FILES['proof_files']['name'][$i],
                            'type' => $_FILES['proof_files']['type'][$i],
                            'tmp_name' => $_FILES['proof_files']['tmp_name'][$i],
                            'error' => $_FILES['proof_files']['error'][$i],
                            'size' => $_FILES['proof_files']['size'][$i]
                        ];
                    }
                }
            } else {
                if ($_FILES['proof_files']['error'] === UPLOAD_ERR_OK) {
                    $files[] = $_FILES['proof_files'];
                }
            }
        }
        
        foreach ($files as $file) {
            // Validate file type
            if (!in_array($file['type'], $allowedTypes)) {
                continue; // Skip invalid files
            }
            
            // Validate file size
            $isVideo = in_array($file['type'], $allowedVideoTypes);
            $maxSize = $isVideo ? $maxVideoSize : $maxImageSize;
            if ($file['size'] > $maxSize) {
                continue; // Skip oversized files
            }
            
            // Generate unique filename
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = uniqid('proof_') . '_' . time() . '.' . $extension;
            $filepath = $uploadDir . $filename;
            
            if (move_uploaded_file($file['tmp_name'], $filepath)) {
                $uploadedFiles[] = [
                    'filename' => $filename,
                    'original_name' => $file['name'],
                    'type' => $isVideo ? 'video' : 'image',
                    'mime_type' => $file['type'],
                    'size' => $file['size'],
                    'path' => 'uploads/dispatch_acknowledgments/' . $dispatchId . '/' . $filename
                ];
            }
        }
    }
    
    // Acknowledge the dispatch with additional data
    $acknowledgmentData = [
        'notes' => $notes,
        'condition' => $condition,
        'proof_files' => $uploadedFiles,
        'acknowledged_by' => $user['id'],
        'acknowledged_at' => date('Y-m-d H:i:s')
    ];
    
    $result = $dispatchService->acknowledgeReceipt($dispatchId, $user['id'], $acknowledgmentData);
    
    if (!$result['success']) {
        ApiResponse::error($result['code'] ?? 'ACKNOWLEDGE_ERROR', $result['message'], 400);
    }
    
    // Log the acknowledgment
    $authMiddleware->logApiAccess($user['id'], '/api/inventory/dispatch/acknowledge', 'POST', [
        'dispatch_id' => $dispatchId,
        'dispatch_number' => $dispatch['dispatch_number'],
        'files_uploaded' => count($uploadedFiles),
        'condition' => $condition
    ]);
    
    ApiResponse::success([
        'dispatch_id' => $dispatchId,
        'dispatch_number' => $dispatch['dispatch_number'],
        'acknowledgment_status' => 'acknowledged',
        'acknowledged_at' => $acknowledgmentData['acknowledged_at'],
        'files_uploaded' => count($uploadedFiles),
        'condition' => $condition
    ], 'Dispatch acknowledged successfully');
    
} catch (Exception $e) {
    error_log("Inventory Dispatch Acknowledge API Error: " . $e->getMessage());
    ApiResponse::serverError('Failed to acknowledge dispatch: ' . $e->getMessage());
}
