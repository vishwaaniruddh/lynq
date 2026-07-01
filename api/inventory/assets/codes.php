<?php
/**
 * Asset Codes API
 * Generate QR codes and barcodes for assets
 * 
 * GET /api/inventory/assets/codes.php?serial=SERIAL&type=qr|barcode
 */

require_once __DIR__ . '/../../../config/autoload.php';
require_once __DIR__ . '/../../ApiResponse.php';

// Handle CORS
ApiResponse::setCorsHeaders();
ApiResponse::handlePreflight();

// Only allow GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    ApiResponse::methodNotAllowed(['GET']);
}

try {
    $sessionService = new SessionService();
    
    // Check authentication
    if (!$sessionService->isLoggedIn()) {
        ApiResponse::unauthorized('Authentication required');
    }
    
    // Check permission
    if (!can('inventory.assets.view') && !isAdvUser()) {
        ApiResponse::forbidden('You do not have permission to view asset codes');
    }
    
    $serialNumber = $_GET['serial'] ?? '';
    $type = $_GET['type'] ?? 'qr';
    $format = $_GET['format'] ?? 'json';
    
    if (empty($serialNumber)) {
        ApiResponse::validationError(['serial' => 'Serial number is required'], 'Serial number is required');
    }
    
    // Verify asset exists and user has access
    $assetModel = new Asset();
    $asset = $assetModel->findBySerialNumber($serialNumber);
    
    if (!$asset) {
        ApiResponse::notFound('Asset not found');
    }
    
    // Check company access for contractors
    if (!isAdvUser()) {
        $currentUser = $sessionService->getCurrentUser();
        
        // Get warehouse to check company access
        $warehouseModel = new Warehouse();
        $warehouse = $warehouseModel->find($asset['warehouse_id']);
        
        if ($warehouse && $warehouse['company_id'] != $currentUser['company_id']) {
            ApiResponse::forbidden('Access denied to this asset');
        }
    }
    
    switch ($type) {
        case 'qr':
            generateQRCodeResponse($asset, $format);
            break;
            
        case 'barcode':
            generateBarcodeResponse($asset, $format);
            break;
            
        default:
            ApiResponse::validationError(['type' => 'Invalid type. Must be qr or barcode'], 'Invalid code type');
    }
    
} catch (Exception $e) {
    error_log("Asset Codes API Error: " . $e->getMessage());
    ApiResponse::serverError('Failed to generate asset codes');
}

/**
 * Generate QR code response
 */
function generateQRCodeResponse($asset, $format) {
    $assetUrl = getAssetUrl($asset);
    
    if ($format === 'image') {
        // Generate QR code image using a simple library or service
        generateQRCodeImage($assetUrl, $asset['serial_number']);
    } else {
        // Return QR code data
        ApiResponse::success([
            'type' => 'qr',
            'serial_number' => $asset['serial_number'],
            'data' => $assetUrl,
            'size' => '150x150',
            'format' => 'PNG',
            'online_url' => "https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=" . urlencode($assetUrl)
        ], 'QR code data generated successfully');
    }
}

/**
 * Generate barcode response
 */
function generateBarcodeResponse($asset, $format) {
    $serialNumber = $asset['serial_number'];
    
    if ($format === 'image') {
        // Generate barcode image
        generateBarcodeImage($serialNumber);
    } else {
        // Return barcode data
        ApiResponse::success([
            'type' => 'barcode',
            'serial_number' => $serialNumber,
            'data' => $serialNumber,
            'format' => 'CODE128',
            'dimensions' => '200x80'
        ], 'Barcode data generated successfully');
    }
}

/**
 * Get asset URL for QR code
 */
function getAssetUrl($asset) {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $baseUrl = $protocol . '://' . $host;
    
    // Use public asset page (no authentication required)
    return $baseUrl . '/public/asset.php?serial=' . urlencode($asset['serial_number']);
}

/**
 * Generate QR code image (simple implementation)
 */
function generateQRCodeImage($data, $filename) {
    // Use online service for simplicity
    $qrUrl = "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=" . urlencode($data);
    
    // Set headers for image download
    header('Content-Type: image/png');
    header('Content-Disposition: attachment; filename="QR_' . $filename . '.png"');
    
    // Stream the image
    $imageData = file_get_contents($qrUrl);
    if ($imageData !== false) {
        echo $imageData;
    } else {
        // Fallback: create simple image with text
        $image = imagecreate(200, 200);
        $white = imagecolorallocate($image, 255, 255, 255);
        $black = imagecolorallocate($image, 0, 0, 0);
        imagefill($image, 0, 0, $white);
        imagestring($image, 3, 50, 90, 'QR Code', $black);
        imagestring($image, 2, 60, 110, $filename, $black);
        imagepng($image);
        imagedestroy($image);
    }
    exit;
}

/**
 * Generate barcode image (simple implementation)
 */
function generateBarcodeImage($serialNumber) {
    // Set headers for image download
    header('Content-Type: image/png');
    header('Content-Disposition: attachment; filename="Barcode_' . $serialNumber . '.png"');
    
    // Create simple barcode-like image
    $width = 300;
    $height = 100;
    $image = imagecreate($width, $height);
    
    $white = imagecolorallocate($image, 255, 255, 255);
    $black = imagecolorallocate($image, 0, 0, 0);
    
    imagefill($image, 0, 0, $white);
    
    // Draw barcode-like pattern
    $barWidth = 2;
    $x = 20;
    
    for ($i = 0; $i < strlen($serialNumber) * 8; $i++) {
        $barHeight = ($i % 3 === 0) ? 60 : 40;
        $y = 20;
        
        if ($i % 2 === 0) {
            imagefilledrectangle($image, $x, $y, $x + $barWidth, $y + $barHeight, $black);
        }
        
        $x += $barWidth + 1;
    }
    
    // Add text
    imagestring($image, 3, ($width - strlen($serialNumber) * 10) / 2, $height - 25, $serialNumber, $black);
    
    imagepng($image);
    imagedestroy($image);
    exit;
}