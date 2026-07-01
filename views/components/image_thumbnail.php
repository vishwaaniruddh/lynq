<?php
/**
 * Image Thumbnail Component
 * 
 * Provides reusable functions for displaying image thumbnails with lightbox support.
 * 
 * Requirements: 9.1, 9.4, 9.5
 * - 9.1: Display uploaded images as visible thumbnail previews (150x150 pixels)
 * - 9.4: Display all thumbnails in a grid layout when multiple images exist
 * - 9.5: Display placeholder icon with "Image not available" text when image fails to load
 */

/**
 * Generate thumbnail HTML for a single image
 * 
 * @param string|null $imagePath Path to the image file
 * @param string $label Label for the image
 * @param string $baseUrl Base URL for image paths (default: '..')
 * @param int $width Thumbnail width in pixels (default: 150)
 * @param int $height Thumbnail height in pixels (default: 150)
 * @return string HTML for the thumbnail
 */
function renderImageThumbnail(?string $imagePath, string $label = '', string $baseUrl = '..', int $width = 150, int $height = 150): string {
    if (empty($imagePath)) {
        return '';
    }
    
    $fullPath = $baseUrl . '/' . htmlspecialchars($imagePath);
    $escapedLabel = htmlspecialchars($label);
    $altText = $escapedLabel ?: 'Feasibility image';
    
    return <<<HTML
<div class="image-thumbnail-container" data-label="{$escapedLabel}">
    <img 
        src="{$fullPath}" 
        alt="{$altText}"
        class="image-thumbnail cursor-pointer rounded-lg border border-gray-200 hover:border-blue-400 hover:shadow-md transition-all object-cover"
        style="width: {$width}px; height: {$height}px;"
        onclick="openLightbox('{$fullPath}', '{$escapedLabel}')"
        onerror="handleImageError(this)"
        loading="lazy"
    />
</div>
HTML;
}

/**
 * Generate thumbnail HTML for multiple images in a grid layout
 * 
 * @param array $images Array of image data with 'path' and 'label' keys
 * @param string $baseUrl Base URL for image paths (default: '..')
 * @param int $width Thumbnail width in pixels (default: 150)
 * @param int $height Thumbnail height in pixels (default: 150)
 * @return string HTML for the thumbnail grid
 */
function renderImageThumbnailGrid(array $images, string $baseUrl = '..', int $width = 150, int $height = 150): string {
    if (empty($images)) {
        return '';
    }
    
    $thumbnails = [];
    foreach ($images as $image) {
        $path = $image['path'] ?? '';
        $label = $image['label'] ?? '';
        if (!empty($path)) {
            $thumbnails[] = renderImageThumbnail($path, $label, $baseUrl, $width, $height);
        }
    }
    
    if (empty($thumbnails)) {
        return '';
    }
    
    $thumbnailsHtml = implode("\n", $thumbnails);
    
    return <<<HTML
<div class="image-thumbnail-grid flex flex-wrap gap-3">
    {$thumbnailsHtml}
</div>
HTML;
}

/**
 * Generate placeholder HTML for missing/broken images
 * 
 * @param string $label Label for the placeholder
 * @param int $width Placeholder width in pixels (default: 150)
 * @param int $height Placeholder height in pixels (default: 150)
 * @return string HTML for the placeholder
 */
function renderImagePlaceholder(string $label = '', int $width = 150, int $height = 150): string {
    $escapedLabel = htmlspecialchars($label);
    
    return <<<HTML
<div class="image-placeholder flex flex-col items-center justify-center bg-gray-100 rounded-lg border border-gray-200" 
     style="width: {$width}px; height: {$height}px;">
    <i class="fas fa-image text-gray-400 text-3xl mb-2"></i>
    <span class="text-xs text-gray-500 text-center px-2">Image not available</span>
</div>
HTML;
}

/**
 * Render a labeled image section with thumbnail
 * 
 * @param string|null $imagePath Path to the image file
 * @param string $label Label for the image section
 * @param string $baseUrl Base URL for image paths (default: '..')
 * @return string HTML for the labeled image section
 */
function renderLabeledImageThumbnail(?string $imagePath, string $label, string $baseUrl = '..'): string {
    $escapedLabel = htmlspecialchars($label);
    
    if (empty($imagePath)) {
        return '';
    }
    
    $thumbnail = renderImageThumbnail($imagePath, $label, $baseUrl);
    
    return <<<HTML
<div class="labeled-image-section">
    <label class="block text-sm font-medium text-gray-700 mb-2">{$escapedLabel}</label>
    {$thumbnail}
</div>
HTML;
}

/**
 * Get CSS styles for image thumbnails
 * Include this in the page head or in a style block
 * 
 * @return string CSS styles
 */
function getImageThumbnailStyles(): string {
    return <<<CSS
<style>
/* Image Thumbnail Styles */
.image-thumbnail-container {
    display: inline-block;
    position: relative;
}

.image-thumbnail {
    transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
}

.image-thumbnail:hover {
    transform: scale(1.02);
}

.image-thumbnail-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
}

.image-placeholder {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
}

.labeled-image-section {
    margin-bottom: 1rem;
}

/* Responsive adjustments */
@media (max-width: 640px) {
    .image-thumbnail-grid {
        justify-content: center;
    }
}
</style>
CSS;
}
