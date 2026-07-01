<?php
/**
 * Public Asset Details Page
 * 
 * Displays asset information without requiring authentication
 * Accessible via QR code scanning
 * 
 * URL: /public/asset.php?serial=SERIAL_NUMBER
 */

require_once __DIR__ . '/../config/autoload.php';

// Get serial number from URL
$serialNumber = $_GET['serial'] ?? '';

if (empty($serialNumber)) {
    $error = 'Serial number is required';
    $asset = null;
} else {
    // Get asset details
    try {
        $assetModel = new Asset();
        $asset = $assetModel->findBySerialNumber($serialNumber);
        
        if (!$asset) {
            $error = 'Asset not found';
        } else {
            // Get additional details
            $productModel = new Product();
            $warehouseModel = new Warehouse();
            $companyModel = new Company();
            
            $product = $productModel->find($asset['product_id']);
            $warehouse = $warehouseModel->find($asset['warehouse_id']);
            
            // Get company through warehouse relationship
            $company = null;
            if ($warehouse && !empty($warehouse['company_id'])) {
                $company = $companyModel->find($warehouse['company_id']);
            }
            
            // Merge details
            $asset['product_name'] = $product['name'] ?? 'Unknown Product';
            $asset['product_category'] = $product['category'] ?? 'Unknown Category';
            $asset['product_description'] = $product['description'] ?? '';
            $asset['warehouse_name'] = $warehouse['name'] ?? 'Unknown Warehouse';
            $asset['warehouse_location'] = $warehouse['location'] ?? '';
            $asset['company_name'] = $company['name'] ?? 'Unknown Company';
            
            $error = null;
        }
    } catch (Exception $e) {
        error_log("Public asset view error: " . $e->getMessage());
        $error = 'Unable to load asset details';
        $asset = null;
    }
}

// Status configuration
$statusConfig = [
    'in_stock' => ['label' => 'In Stock', 'color' => 'bg-green-100 text-green-700 border-green-200', 'icon' => 'fa-warehouse'],
    'dispatched' => ['label' => 'Dispatched', 'color' => 'bg-blue-100 text-blue-700 border-blue-200', 'icon' => 'fa-truck'],
    'assigned' => ['label' => 'Assigned', 'color' => 'bg-purple-100 text-purple-700 border-purple-200', 'icon' => 'fa-user-check'],
    'in_use' => ['label' => 'In Use', 'color' => 'bg-indigo-100 text-indigo-700 border-indigo-200', 'icon' => 'fa-tools'],
    'returned' => ['label' => 'Returned', 'color' => 'bg-teal-100 text-teal-700 border-teal-200', 'icon' => 'fa-undo'],
    'under_repair' => ['label' => 'Under Repair', 'color' => 'bg-yellow-100 text-yellow-700 border-yellow-200', 'icon' => 'fa-wrench'],
    'scrapped' => ['label' => 'Scrapped', 'color' => 'bg-red-100 text-red-700 border-red-200', 'icon' => 'fa-trash'],
    'lost' => ['label' => 'Lost', 'color' => 'bg-gray-100 text-gray-700 border-gray-200', 'icon' => 'fa-question-circle']
];

$conditionConfig = [
    'working' => ['label' => 'Working', 'color' => 'bg-green-100 text-green-700 border-green-200'],
    'not_working' => ['label' => 'Not Working', 'color' => 'bg-red-100 text-red-700 border-red-200']
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $asset ? 'Asset: ' . htmlspecialchars($asset['serial_number']) : 'Asset Details'; ?> - ADV CRM</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- QR Code Library -->
    <script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js"></script>
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="../assets/favicon.ico">
    
    <style>
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
        }
        
        .card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .card-hover {
            transition: all 0.3s ease;
        }
        
        .card-hover:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }
        
        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 8px 16px;
            border-radius: 50px;
            font-size: 14px;
            font-weight: 500;
            border: 1px solid;
        }
        
        .qr-container {
            background: white;
            padding: 16px;
            border-radius: 12px;
            border: 2px solid #e5e7eb;
            display: inline-block;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .info-grid {
            display: grid;
            gap: 16px;
        }
        
        @media (min-width: 768px) {
            .info-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        .fade-in {
            animation: fadeIn 0.6s ease-out;
        }
        
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>
<body class="min-h-screen py-8 px-4">
    <div class="max-w-4xl mx-auto">
        
        <?php if ($error): ?>
            <!-- Error State -->
            <div class="card p-8 text-center fade-in">
                <div class="w-20 h-20 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-exclamation-triangle text-3xl text-red-600"></i>
                </div>
                <h2 class="text-2xl font-bold text-gray-800 mb-2">Asset Not Found</h2>
                <p class="text-gray-600 mb-4"><?php echo htmlspecialchars($error); ?></p>
                <?php if (!empty($serialNumber)): ?>
                    <p class="text-sm text-gray-500 font-mono bg-gray-100 px-3 py-2 rounded-lg inline-block">
                        Serial: <?php echo htmlspecialchars($serialNumber); ?>
                    </p>
                <?php endif; ?>
            </div>
            
        <?php else: ?>
            <!-- Asset Details -->
            <div class="space-y-6">
                
                <!-- Header Card -->
                <div class="card p-6 text-center fade-in">
                    <div class="flex items-center justify-center mb-4">
                        <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-barcode text-2xl text-blue-600"></i>
                        </div>
                    </div>
                    <h1 class="text-3xl font-bold text-gray-800 mb-2"><?php echo htmlspecialchars($asset['product_name']); ?></h1>
                    <div class="bg-blue-50 px-4 py-2 rounded-lg inline-block mb-4">
                        <span class="text-lg font-mono text-blue-700"><?php echo htmlspecialchars($asset['serial_number']); ?></span>
                    </div>
                    
                    <!-- QR Code Display -->
                    <div class="mt-4">
                        <div class="qr-container">
                            <canvas id="qr-display" width="120" height="120"></canvas>
                        </div>
                        <p class="text-xs text-gray-500 mt-2">
                            <i class="fas fa-qrcode mr-1"></i>
                            Scanned QR Code
                        </p>
                    </div>
                </div>
                
                <!-- Status Card -->
                <div class="card p-6 text-center fade-in">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Current Status</h3>
                    <div class="flex flex-wrap justify-center gap-4">
                        <?php 
                        $status = $statusConfig[$asset['status']] ?? ['label' => $asset['status'], 'color' => 'bg-gray-100 text-gray-700 border-gray-200', 'icon' => 'fa-circle'];
                        $condition = $conditionConfig[$asset['working_condition']] ?? ['label' => $asset['working_condition'] ?: 'Unknown', 'color' => 'bg-gray-100 text-gray-700 border-gray-200'];
                        ?>
                        <div class="status-badge <?php echo $status['color']; ?>">
                            <i class="fas <?php echo $status['icon']; ?> mr-2"></i>
                            <?php echo htmlspecialchars($status['label']); ?>
                        </div>
                        <div class="status-badge <?php echo $condition['color']; ?>">
                            <i class="fas fa-cog mr-2"></i>
                            <?php echo htmlspecialchars($condition['label']); ?>
                        </div>
                    </div>
                </div>
                
                <!-- Information Cards -->
                <div class="info-grid">
                    
                    <!-- Product Information -->
                    <div class="card card-hover p-6 fade-in">
                        <div class="flex items-center mb-4">
                            <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center mr-3">
                                <i class="fas fa-box text-blue-600"></i>
                            </div>
                            <h3 class="text-lg font-semibold text-gray-800">Product Details</h3>
                        </div>
                        <div class="space-y-3">
                            <div>
                                <p class="text-sm text-gray-500">Product Name</p>
                                <p class="font-medium text-gray-800"><?php echo htmlspecialchars($asset['product_name']); ?></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">Category</p>
                                <p class="font-medium text-gray-800"><?php echo htmlspecialchars($asset['product_category']); ?></p>
                            </div>
                            <?php if (!empty($asset['product_description'])): ?>
                            <div>
                                <p class="text-sm text-gray-500">Description</p>
                                <p class="text-sm text-gray-700"><?php echo htmlspecialchars($asset['product_description']); ?></p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Location Information -->
                    <div class="card card-hover p-6 fade-in">
                        <div class="flex items-center mb-4">
                            <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center mr-3">
                                <i class="fas fa-map-marker-alt text-green-600"></i>
                            </div>
                            <h3 class="text-lg font-semibold text-gray-800">Location Details</h3>
                        </div>
                        <div class="space-y-3">
                            <div>
                                <p class="text-sm text-gray-500">Company</p>
                                <p class="font-medium text-gray-800"><?php echo htmlspecialchars($asset['company_name']); ?></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">Warehouse</p>
                                <p class="font-medium text-gray-800"><?php echo htmlspecialchars($asset['warehouse_name']); ?></p>
                            </div>
                            <?php if (!empty($asset['warehouse_location'])): ?>
                            <div>
                                <p class="text-sm text-gray-500">Location</p>
                                <p class="text-sm text-gray-700"><?php echo htmlspecialchars($asset['warehouse_location']); ?></p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                </div>
                
                <!-- Additional Information -->
                <div class="card p-6 fade-in">
                    <div class="flex items-center mb-4">
                        <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center mr-3">
                            <i class="fas fa-info-circle text-purple-600"></i>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-800">Additional Information</h3>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <p class="text-sm text-gray-500">Created Date</p>
                            <p class="font-medium text-gray-800">
                                <?php echo date('M d, Y', strtotime($asset['created_at'])); ?>
                            </p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Last Updated</p>
                            <p class="font-medium text-gray-800">
                                <?php echo date('M d, Y', strtotime($asset['updated_at'])); ?>
                            </p>
                        </div>
                        <?php if (!empty($asset['warranty_expiry'])): ?>
                        <div>
                            <p class="text-sm text-gray-500">Warranty Expiry</p>
                            <p class="font-medium text-gray-800">
                                <?php echo date('M d, Y', strtotime($asset['warranty_expiry'])); ?>
                            </p>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (!empty($asset['notes'])): ?>
                    <div class="mt-4 pt-4 border-t border-gray-200">
                        <p class="text-sm text-gray-500 mb-2">Notes</p>
                        <p class="text-sm text-gray-700 bg-gray-50 p-3 rounded-lg"><?php echo nl2br(htmlspecialchars($asset['notes'])); ?></p>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- System Access -->
                <div class="card p-6 text-center fade-in">
                    <h3 class="text-lg font-semibold text-gray-800 mb-2">Need Full Access?</h3>
                    <p class="text-gray-600 mb-4">Access the complete asset management system for more details and actions.</p>
                    <a href="../inventory/assets.php?serial=<?php echo urlencode($asset['serial_number']); ?>" 
                       class="inline-flex items-center px-6 py-3 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 transition-colors">
                        <i class="fas fa-sign-in-alt mr-2"></i>
                        Access Full System
                    </a>
                    <p class="text-xs text-gray-500 mt-2">
                        <i class="fas fa-lock mr-1"></i>
                        Requires authentication
                    </p>
                </div>
                
            </div>
        <?php endif; ?>
        
        <!-- Footer -->
        <div class="text-center mt-8">
            <div class="card p-4 fade-in">
                <p class="text-sm text-gray-600">
                    <i class="fas fa-shield-alt mr-1"></i>
                    Powered by ADV CRM
                </p>
                <p class="text-xs text-gray-500 mt-1">
                    Scanned on <?php echo date('M d, Y \a\t g:i A'); ?>
                </p>
            </div>
        </div>
        
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Generate QR code display
            <?php if ($asset): ?>
            const qrCanvas = document.getElementById('qr-display');
            if (qrCanvas && typeof QRCode !== 'undefined') {
                const currentUrl = window.location.href;
                QRCode.toCanvas(qrCanvas, currentUrl, {
                    width: 120,
                    height: 120,
                    margin: 2,
                    color: {
                        dark: '#1e40af',
                        light: '#ffffff'
                    }
                }, function (error) {
                    if (error) {
                        console.error('QR Code display error:', error);
                        // Fallback: show text
                        const ctx = qrCanvas.getContext('2d');
                        ctx.font = '12px Arial';
                        ctx.fillText('QR Code', 40, 60);
                    }
                });
            }
            <?php endif; ?>
        });
    </script>
</body>
</html>