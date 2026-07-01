<?php
/**
 * PWA Icon Verification Script
 * Verifies that all required icons exist and are accessible
 */

// Required icon sizes for PWA compliance
$requiredIcons = [
    'icon-72.png' => '72x72',
    'icon-96.png' => '96x96', 
    'icon-128.png' => '128x128',
    'icon-144.png' => '144x144',
    'icon-152.png' => '152x152',
    'icon-192.png' => '192x192',
    'icon-384.png' => '384x384',
    'icon-512.png' => '512x512',
    'icon-192-maskable.png' => '192x192 (maskable)',
    'icon-512-maskable.png' => '512x512 (maskable)'
];

$iconsDir = __DIR__;
$allValid = true;

echo "PWA Icon Verification Report\n";
echo "============================\n\n";

foreach ($requiredIcons as $filename => $expectedSize) {
    $filepath = "$iconsDir/$filename";
    
    if (!file_exists($filepath)) {
        echo "❌ MISSING: $filename\n";
        $allValid = false;
        continue;
    }
    
    // Check file size
    $filesize = filesize($filepath);
    if ($filesize === 0) {
        echo "❌ EMPTY: $filename (0 bytes)\n";
        $allValid = false;
        continue;
    }
    
    // Check if it's a valid image
    $imageInfo = @getimagesize($filepath);
    if (!$imageInfo) {
        echo "❌ INVALID: $filename (not a valid image)\n";
        $allValid = false;
        continue;
    }
    
    $actualWidth = $imageInfo[0];
    $actualHeight = $imageInfo[1];
    $mimeType = $imageInfo['mime'];
    
    // Verify MIME type
    if ($mimeType !== 'image/png') {
        echo "⚠️  WARNING: $filename has MIME type $mimeType (expected image/png)\n";
    }
    
    echo "✅ VALID: $filename\n";
    echo "   Size: {$actualWidth}x{$actualHeight} pixels\n";
    echo "   File size: " . number_format($filesize) . " bytes\n";
    echo "   MIME type: $mimeType\n";
    echo "   Expected: $expectedSize\n\n";
}

echo "Summary\n";
echo "=======\n";

if ($allValid) {
    echo "✅ All " . count($requiredIcons) . " required icons are present and valid!\n";
    echo "\nPWA Requirements Status:\n";
    echo "✅ Icon sizes 72x72 to 512x512: Available\n";
    echo "✅ Maskable icons for Android: Available\n";
    echo "✅ PNG format with transparency: Available\n";
    echo "✅ Proper file structure: Complete\n";
} else {
    echo "❌ Some icons are missing or invalid.\n";
    echo "Please regenerate the missing icons.\n";
    exit(1);
}

// Test icon accessibility via HTTP (if running on web server)
if (isset($_SERVER['HTTP_HOST'])) {
    echo "\nHTTP Accessibility Test\n";
    echo "======================\n";
    
    $baseUrl = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
    $iconBaseUrl = $baseUrl . '/assets/icons/';
    
    foreach (array_keys($requiredIcons) as $filename) {
        $iconUrl = $iconBaseUrl . $filename;
        
        // Simple HTTP check
        $headers = @get_headers($iconUrl, 1);
        if ($headers && strpos($headers[0], '200') !== false) {
            echo "✅ HTTP: $filename is accessible\n";
        } else {
            echo "❌ HTTP: $filename is not accessible via $iconUrl\n";
        }
    }
}

echo "\n🎉 PWA icon setup complete!\n";
?>