<?php
/**
 * PWA Icon Generator
 * Generates all required PWA icon sizes from the existing logo
 */

// Required icon sizes for PWA compliance
$iconSizes = [
    72, 96, 128, 144, 152, 192, 384, 512
];

// Maskable icon sizes (with safe zone for Android)
$maskableSizes = [192, 512];

$sourceImage = __DIR__ . '/../logo.png';
$iconsDir = __DIR__;

if (!file_exists($sourceImage)) {
    die("Source logo not found at: $sourceImage\n");
}

// Check if GD extension is available
if (!extension_loaded('gd')) {
    die("GD extension is required for image processing\n");
}

// Get source image info
$imageInfo = getimagesize($sourceImage);
if (!$imageInfo) {
    die("Invalid source image format\n");
}

$sourceWidth = $imageInfo[0];
$sourceHeight = $imageInfo[1];
$sourceType = $imageInfo[2];

// Create source image resource
switch ($sourceType) {
    case IMAGETYPE_PNG:
        $sourceImg = imagecreatefrompng($sourceImage);
        break;
    case IMAGETYPE_JPEG:
        $sourceImg = imagecreatefromjpeg($sourceImage);
        break;
    case IMAGETYPE_GIF:
        $sourceImg = imagecreatefromgif($sourceImage);
        break;
    default:
        die("Unsupported image format\n");
}

if (!$sourceImg) {
    die("Failed to create image resource\n");
}

// Enable alpha blending for transparency
imagealphablending($sourceImg, false);
imagesavealpha($sourceImg, true);

echo "Generating PWA icons from logo.png...\n";

// Generate regular icons
foreach ($iconSizes as $size) {
    $newImg = imagecreatetruecolor($size, $size);
    
    // Preserve transparency
    imagealphablending($newImg, false);
    imagesavealpha($newImg, true);
    $transparent = imagecolorallocatealpha($newImg, 0, 0, 0, 127);
    imagefill($newImg, 0, 0, $transparent);
    
    // Resize image
    imagecopyresampled(
        $newImg, $sourceImg,
        0, 0, 0, 0,
        $size, $size,
        $sourceWidth, $sourceHeight
    );
    
    $filename = "icon-{$size}.png";
    $filepath = "$iconsDir/$filename";
    
    if (imagepng($newImg, $filepath, 9)) {
        echo "Generated: $filename ({$size}x{$size})\n";
    } else {
        echo "Failed to generate: $filename\n";
    }
    
    imagedestroy($newImg);
}

// Generate maskable icons (with padding for safe zone)
foreach ($maskableSizes as $size) {
    $newImg = imagecreatetruecolor($size, $size);
    
    // Preserve transparency
    imagealphablending($newImg, false);
    imagesavealpha($newImg, true);
    $transparent = imagecolorallocatealpha($newImg, 0, 0, 0, 127);
    imagefill($newImg, 0, 0, $transparent);
    
    // Calculate padding for maskable icon (20% safe zone)
    $padding = $size * 0.1; // 10% padding on each side
    $iconSize = $size - ($padding * 2);
    
    // Resize image with padding
    imagecopyresampled(
        $newImg, $sourceImg,
        $padding, $padding, 0, 0,
        $iconSize, $iconSize,
        $sourceWidth, $sourceHeight
    );
    
    $filename = "icon-{$size}-maskable.png";
    $filepath = "$iconsDir/$filename";
    
    if (imagepng($newImg, $filepath, 9)) {
        echo "Generated: $filename ({$size}x{$size} maskable)\n";
    } else {
        echo "Failed to generate: $filename\n";
    }
    
    imagedestroy($newImg);
}

imagedestroy($sourceImg);

echo "\nIcon generation complete!\n";
echo "Generated " . (count($iconSizes) + count($maskableSizes)) . " icon files.\n";

// Verify all icons were created
echo "\nVerifying generated icons:\n";
$allGenerated = true;

foreach ($iconSizes as $size) {
    $filename = "icon-{$size}.png";
    if (file_exists("$iconsDir/$filename")) {
        $filesize = filesize("$iconsDir/$filename");
        echo "✓ $filename ({$filesize} bytes)\n";
    } else {
        echo "✗ $filename (missing)\n";
        $allGenerated = false;
    }
}

foreach ($maskableSizes as $size) {
    $filename = "icon-{$size}-maskable.png";
    if (file_exists("$iconsDir/$filename")) {
        $filesize = filesize("$iconsDir/$filename");
        echo "✓ $filename ({$filesize} bytes)\n";
    } else {
        echo "✗ $filename (missing)\n";
        $allGenerated = false;
    }
}

if ($allGenerated) {
    echo "\n✅ All icons generated successfully!\n";
} else {
    echo "\n❌ Some icons failed to generate.\n";
    exit(1);
}
?>