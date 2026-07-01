<?php
/**
 * Lightbox Modal Component
 * 
 * Provides a full-size image display modal with smooth animations.
 * 
 * Requirements: 9.2, 9.3
 * - 9.2: Display full-size image in a lightbox modal overlay when thumbnail is clicked
 * - 9.3: Allow closing via close button, clicking outside the image, or pressing Escape key
 */

/**
 * Render the lightbox modal HTML
 * Include this once in the page, typically before the closing </body> tag
 * 
 * @return string HTML for the lightbox modal
 */
function renderLightboxModal(): string {
    return <<<HTML
<!-- Lightbox Modal -->
<div id="image-lightbox" class="lightbox-overlay hidden" onclick="closeLightboxOnOverlay(event)">
    <div class="lightbox-container">
        <!-- Close button -->
        <button type="button" class="lightbox-close" onclick="closeLightbox()" aria-label="Close lightbox">
            <i class="fas fa-times"></i>
        </button>
        
        <!-- Image container -->
        <div class="lightbox-content">
            <img id="lightbox-image" src="" alt="" class="lightbox-image" />
        </div>
        
        <!-- Caption -->
        <div id="lightbox-caption" class="lightbox-caption"></div>
    </div>
</div>
HTML;
}

/**
 * Get CSS styles for the lightbox
 * Include this in the page head or in a style block
 * 
 * @return string CSS styles
 */
function getLightboxStyles(): string {
    return <<<CSS
<style>
/* Lightbox Overlay */
.lightbox-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.9);
    z-index: 9999;
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    visibility: hidden;
    transition: opacity 0.3s ease, visibility 0.3s ease;
}

.lightbox-overlay.active {
    opacity: 1;
    visibility: visible;
}

.lightbox-overlay.hidden {
    display: none;
}

/* Lightbox Container */
.lightbox-container {
    position: relative;
    max-width: 90vw;
    max-height: 90vh;
    display: flex;
    flex-direction: column;
    align-items: center;
}

/* Close Button */
.lightbox-close {
    position: fixed;
    top: 20px;
    right: 20px;
    width: 44px;
    height: 44px;
    background-color: rgba(255, 255, 255, 0.1);
    border: none;
    border-radius: 50%;
    color: white;
    font-size: 20px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background-color 0.2s ease, transform 0.2s ease;
    z-index: 10001;
}

.lightbox-close:hover {
    background-color: rgba(255, 255, 255, 0.2);
    transform: scale(1.1);
}

.lightbox-close:focus {
    outline: 2px solid white;
    outline-offset: 2px;
}

/* Lightbox Content */
.lightbox-content {
    display: flex;
    align-items: center;
    justify-content: center;
    max-width: 100%;
    max-height: calc(90vh - 60px);
}

/* Lightbox Image */
.lightbox-image {
    max-width: 100%;
    max-height: calc(90vh - 60px);
    object-fit: contain;
    border-radius: 4px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
    transform: scale(0.9);
    opacity: 0;
    transition: transform 0.3s ease, opacity 0.3s ease;
}

.lightbox-overlay.active .lightbox-image {
    transform: scale(1);
    opacity: 1;
}

/* Lightbox Caption */
.lightbox-caption {
    color: white;
    text-align: center;
    padding: 15px 20px;
    font-size: 14px;
    max-width: 80%;
    opacity: 0.9;
}

/* Loading state */
.lightbox-image.loading {
    opacity: 0.5;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .lightbox-close {
        top: 10px;
        right: 10px;
        width: 40px;
        height: 40px;
        font-size: 18px;
    }
    
    .lightbox-caption {
        font-size: 12px;
        padding: 10px 15px;
    }
}
</style>
CSS;
}

/**
 * Get JavaScript for the lightbox functionality
 * Include this in the page, typically before the closing </body> tag
 * 
 * @return string JavaScript code
 */
function getLightboxScript(): string {
    return <<<JS
<script>
/**
 * Lightbox functionality
 * Requirements: 9.2, 9.3
 */

// Track if lightbox is open
let lightboxOpen = false;

/**
 * Open the lightbox with the specified image
 * @param {string} imageSrc - URL of the image to display
 * @param {string} caption - Optional caption for the image
 */
function openLightbox(imageSrc, caption = '') {
    const lightbox = document.getElementById('image-lightbox');
    const lightboxImage = document.getElementById('lightbox-image');
    const lightboxCaption = document.getElementById('lightbox-caption');
    
    if (!lightbox || !lightboxImage) return;
    
    // Set image source and caption
    lightboxImage.classList.add('loading');
    lightboxImage.src = imageSrc;
    lightboxImage.alt = caption || 'Full size image';
    lightboxCaption.textContent = caption;
    
    // Show lightbox
    lightbox.classList.remove('hidden');
    
    // Trigger animation after a brief delay
    requestAnimationFrame(() => {
        lightbox.classList.add('active');
    });
    
    // Handle image load
    lightboxImage.onload = function() {
        lightboxImage.classList.remove('loading');
    };
    
    // Handle image error
    lightboxImage.onerror = function() {
        lightboxImage.classList.remove('loading');
        lightboxCaption.textContent = 'Failed to load image';
    };
    
    // Prevent body scroll
    document.body.style.overflow = 'hidden';
    lightboxOpen = true;
    
    // Add keyboard listener
    document.addEventListener('keydown', handleLightboxKeydown);
}

/**
 * Close the lightbox
 */
function closeLightbox() {
    const lightbox = document.getElementById('image-lightbox');
    const lightboxImage = document.getElementById('lightbox-image');
    
    if (!lightbox) return;
    
    // Start fade out animation
    lightbox.classList.remove('active');
    
    // Hide after animation completes
    setTimeout(() => {
        lightbox.classList.add('hidden');
        if (lightboxImage) {
            lightboxImage.src = '';
        }
    }, 300);
    
    // Restore body scroll
    document.body.style.overflow = '';
    lightboxOpen = false;
    
    // Remove keyboard listener
    document.removeEventListener('keydown', handleLightboxKeydown);
}

/**
 * Close lightbox when clicking on overlay (outside the image)
 * @param {Event} event - Click event
 */
function closeLightboxOnOverlay(event) {
    // Only close if clicking directly on the overlay, not on the image or close button
    if (event.target.id === 'image-lightbox' || event.target.classList.contains('lightbox-overlay')) {
        closeLightbox();
    }
}

/**
 * Handle keyboard events for lightbox
 * @param {KeyboardEvent} event - Keyboard event
 */
function handleLightboxKeydown(event) {
    if (event.key === 'Escape' && lightboxOpen) {
        closeLightbox();
    }
}

/**
 * Handle image load error - show placeholder
 * @param {HTMLImageElement} img - The image element that failed to load
 */
function handleImageError(img) {
    // Replace with placeholder
    const container = img.parentElement;
    if (container && container.classList.contains('image-thumbnail-container')) {
        const label = container.dataset.label || '';
        container.innerHTML = \`
            <div class="image-placeholder flex flex-col items-center justify-center bg-gray-100 rounded-lg border border-gray-200" 
                 style="width: 150px; height: 150px;">
                <i class="fas fa-image text-gray-400 text-3xl mb-2"></i>
                <span class="text-xs text-gray-500 text-center px-2">Image not available</span>
            </div>
        \`;
    }
}
</script>
JS;
}

/**
 * Render complete lightbox component (modal + styles + script)
 * Use this for convenience to include everything at once
 * 
 * @return string Complete lightbox HTML, CSS, and JavaScript
 */
function renderCompleteLightbox(): string {
    return getLightboxStyles() . renderLightboxModal() . getLightboxScript();
}
