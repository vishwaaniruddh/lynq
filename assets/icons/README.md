# PWA Icons Setup

This directory contains all the required icons for Progressive Web App (PWA) compliance.

## Generated Icons

All icons have been generated from the main logo (`assets/logo.png`) and are available in the following sizes:

### Standard Icons
- `icon-72.png` - 72x72 pixels
- `icon-96.png` - 96x96 pixels  
- `icon-128.png` - 128x128 pixels
- `icon-144.png` - 144x144 pixels
- `icon-152.png` - 152x152 pixels
- `icon-192.png` - 192x192 pixels
- `icon-384.png` - 384x384 pixels
- `icon-512.png` - 512x512 pixels

### Maskable Icons (Android Adaptive Icons)
- `icon-192-maskable.png` - 192x192 pixels (maskable)
- `icon-512-maskable.png` - 512x512 pixels (maskable)

## Current Status

✅ **PWA Requirements Met:**
- All required icon sizes (72x72 to 512x512) are available
- Maskable icons for better Android integration
- Proper PNG format with transparency support
- Correct MIME type configuration via .htaccess
- Icons referenced in web app manifest

## Important Notes

⚠️ **Icon Optimization Recommended:**
The current icons are copies of the original logo (1500x490 pixels) which is rectangular. For optimal PWA experience, consider:

1. **Creating square versions** of the logo specifically for app icons
2. **Optimizing file sizes** - current icons are 72KB each, could be reduced
3. **Adding proper padding** for maskable icons (safe zone compliance)
4. **Testing on various devices** to ensure icons display correctly

## Verification

Run the verification script to check icon status:
```bash
php assets/icons/verify_icons.php
```

## Regeneration

If you need to regenerate icons with proper sizing:
1. Create a square version of your logo (recommended: 1024x1024 pixels)
2. Use the `generate_icons.php` script (requires GD extension)
3. Or use external tools like PWA Builder or online icon generators

## MIME Type Configuration

The `.htaccess` file in this directory ensures:
- Proper `image/png` MIME type for all icons
- Appropriate cache headers (1 year cache)
- Security headers to prevent hotlinking
- Content-Type validation

## Web App Manifest Integration

All icons are properly referenced in `/app.webmanifest` with:
- Correct paths and sizes
- Proper purpose attributes (`any` and `maskable`)
- Standard PNG MIME type declarations