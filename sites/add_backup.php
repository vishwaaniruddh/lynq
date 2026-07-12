<?php
/**
 * Add Site Page
 * 
 * Standalone page for adding a new site with all fields and validation
 * Includes coordinate picker/map integration
 * 
 * Requirements: 1.1, 7.1, 7.2, 7.3
 */

require_once __DIR__ . '/../config/autoload.php';

$sessionService = new SessionService();
if (!$sessionService->isLoggedIn()) {
    header('Location: ../views/auth/login.php');
    exit;
}

// Check ADV access
if (!isAdvUser()) {
    $_SESSION['flash_error'] = 'Access denied. ADV users only.';
    header('Location: ../dashboard.php');
    exit;
}

$currentUser = $sessionService->getCurrentUser();
$baseUrl = '..';
$pageTitle = 'Add Site';
$currentPage = 'sites';
$isLoggedIn = true;
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '../dashboard.php'],
    ['label' => 'Sites', 'url' => 'index.php'],
    ['label' => 'Add Site']
];

ob_start();
?>

<div class="max-w-4xl mx-auto">
    <div class="bg-white rounded-xl shadow-sm">
        <!-- Header -->
        <div class="p-6 border-b flex items-center justify-between">
            <div>
                <h3 class="text-lg font-semibold text-gray-800">Add New Site</h3>
                <p class="text-sm text-gray-500">Create a new site record for delegation</p>
            </div>
            <a href="index.php" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">
                <i class="fas fa-arrow-left mr-2"></i>Back to Sites
            </a>
        </div>
        
        <form id="site-form" class="p-6 space-y-6">
            <!-- Basic Information -->
            <div class="border-b pb-6">
                <h4 class="text-md font-medium text-gray-700 mb-4">Basic Information</h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="site_name" class="block text-sm font-medium text-gray-700 mb-1">
                            Site Name <span class="text-red-500">*</span>
                        </label>
                        <input type="text" id="site_name" name="site_name" required
                            class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                            placeholder="Enter site name">
                        <p id="site_name-error" class="mt-1 text-sm text-red-500 hidden"></p>
                    </div>
                    <div>
                        <label for="lho" class="block text-sm font-medium text-gray-700 mb-1">
                            LHO <span class="text-red-500">*</span>
                        </label>
                        <input type="text" id="lho" name="lho" required
                            class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                            placeholder="Enter LHO">
                        <p id="lho-error" class="mt-1 text-sm text-red-500 hidden"></p>
                    </div>
                </div>
            </div>

            <!-- Business Information -->
            <div class="border-b pb-6">
                <h4 class="text-md font-medium text-gray-700 mb-4">Business Information</h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="bank_name" class="block text-sm font-medium text-gray-700 mb-1">Bank Name</label>
                        <input type="text" id="bank_name" name="bank_name"
                            class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                            placeholder="Enter bank name">
                    </div>
                    <div>
                        <label for="customer_name" class="block text-sm font-medium text-gray-700 mb-1">Customer Name</label>
                        <input type="text" id="customer_name" name="customer_name"
                            class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                            placeholder="Enter customer name">
                    </div>
                </div>
            </div>
            
            <!-- Location Information -->
            <div class="border-b pb-6">
                <h4 class="text-md font-medium text-gray-700 mb-4">Location Information</h4>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                    <div>
                        <label for="city" class="block text-sm font-medium text-gray-700 mb-1">
                            City <span class="text-red-500">*</span>
                        </label>
                        <input type="text" id="city" name="city" required
                            class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                            placeholder="Enter city">
                        <p id="city-error" class="mt-1 text-sm text-red-500 hidden"></p>
                    </div>
                    <div>
                        <label for="state" class="block text-sm font-medium text-gray-700 mb-1">
                            State <span class="text-red-500">*</span>
                        </label>
                        <input type="text" id="state" name="state" required
                            class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                            placeholder="Enter state">
                        <p id="state-error" class="mt-1 text-sm text-red-500 hidden"></p>
                    </div>
                    <div>
                        <label for="country" class="block text-sm font-medium text-gray-700 mb-1">
                            Country <span class="text-red-500">*</span>
                        </label>
                        <input type="text" id="country" name="country" required
                            class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                            placeholder="Enter country">
                        <p id="country-error" class="mt-1 text-sm text-red-500 hidden"></p>
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="zone" class="block text-sm font-medium text-gray-700 mb-1">Zone</label>
                        <input type="text" id="zone" name="zone"
                            class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                            placeholder="Enter zone">
                    </div>
                    <div>
                        <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                        <select id="status" name="status" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="mt-4">
                    <label for="address" class="block text-sm font-medium text-gray-700 mb-1">Full Address</label>
                    <textarea id="address" name="address" rows="3"
                        class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                        placeholder="Enter complete address"></textarea>
                </div>
            </div>

            <!-- Coordinates -->
            <div class="pb-6">
                <h4 class="text-md font-medium text-gray-700 mb-4">Coordinates</h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label for="latitude" class="block text-sm font-medium text-gray-700 mb-1">
                            Latitude <span class="text-gray-400 text-xs">(-90 to 90)</span>
                        </label>
                        <input type="number" step="any" id="latitude" name="latitude" min="-90" max="90"
                            class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                            placeholder="e.g., 28.6139">
                        <p id="latitude-error" class="mt-1 text-sm text-red-500 hidden"></p>
                    </div>
                    <div>
                        <label for="longitude" class="block text-sm font-medium text-gray-700 mb-1">
                            Longitude <span class="text-gray-400 text-xs">(-180 to 180)</span>
                        </label>
                        <input type="number" step="any" id="longitude" name="longitude" min="-180" max="180"
                            class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                            placeholder="e.g., 77.2090">
                        <p id="longitude-error" class="mt-1 text-sm text-red-500 hidden"></p>
                    </div>
                </div>
                
                <!-- Map Preview -->
                <div id="map-container" class="h-64 bg-gray-100 rounded-lg flex items-center justify-center border">
                    <div id="map-placeholder" class="text-center text-gray-500">
                        <i class="fas fa-map-marked-alt text-4xl mb-2"></i>
                        <p>Enter coordinates to preview location</p>
                        <button type="button" onclick="getCurrentLocation()" class="mt-2 text-primary hover:underline text-sm">
                            <i class="fas fa-crosshairs mr-1"></i>Use my current location
                        </button>
                    </div>
                    <div id="map-preview" class="hidden w-full h-full"></div>
                </div>
                <p class="mt-2 text-xs text-gray-500">
                    <i class="fas fa-info-circle mr-1"></i>
                    Coordinates are optional but help with location tracking and mapping.
                </p>
            </div>
            
            <!-- Form Actions -->
            <div class="flex justify-end space-x-3 pt-4 border-t">
                <a href="index.php" class="px-6 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">
                    Cancel
                </a>
                <button type="submit" id="save-btn" class="px-6 py-2 bg-primary text-white rounded-lg hover:bg-blue-600 transition">
                    <i class="fas fa-save mr-2"></i>Create Site
                </button>
            </div>
        </form>
    </div>
</div>

<script>
const API_URL = '../api/sites/index.php';

document.getElementById('site-form').addEventListener('submit', async function(e) {
    e.preventDefault();
    clearErrors();
    
    const data = {
        action: 'create',
        site_name: document.getElementById('site_name').value.trim(),
        lho: document.getElementById('lho').value.trim(),
        bank_name: document.getElementById('bank_name').value.trim(),
        customer_name: document.getElementById('customer_name').value.trim(),
        city: document.getElementById('city').value.trim(),
        state: document.getElementById('state').value.trim(),
        country: document.getElementById('country').value.trim(),
        zone: document.getElementById('zone').value.trim(),
        address: document.getElementById('address').value.trim(),
        latitude: document.getElementById('latitude').value,
        longitude: document.getElementById('longitude').value,
        status: document.getElementById('status').value
    };
    
    // Client-side validation
    if (!data.site_name) { showFieldError('site_name', 'Site name is required'); return; }
    if (!data.lho) { showFieldError('lho', 'LHO is required'); return; }
    if (!data.city) { showFieldError('city', 'City is required'); return; }
    if (!data.state) { showFieldError('state', 'State is required'); return; }
    if (!data.country) { showFieldError('country', 'Country is required'); return; }
    
    // Validate coordinates
    if (data.latitude !== '' && (parseFloat(data.latitude) < -90 || parseFloat(data.latitude) > 90)) {
        showFieldError('latitude', 'Latitude must be between -90 and 90'); return;
    }
    if (data.longitude !== '' && (parseFloat(data.longitude) < -180 || parseFloat(data.longitude) > 180)) {
        showFieldError('longitude', 'Longitude must be between -180 and 180'); return;
    }
    
    const saveBtn = document.getElementById('save-btn');
    saveBtn.disabled = true;
    saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Creating...';
    
    try {
        const response = await fetch(API_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        
        if (result.success) {
            showSuccess('Site created successfully');
            setTimeout(() => window.location.href = 'index.php', 1500);
        } else {
            if (result.errors) {
                Object.keys(result.errors).forEach(field => {
                    const error = Array.isArray(result.errors[field]) ? result.errors[field][0] : result.errors[field];
                    showFieldError(field, typeof error === 'object' ? error.message : error);
                });
            } else {
                showError(result.error?.message || result.message || 'Failed to create site');
            }
        }
    } catch (error) {
        console.error('Error:', error);
        showError('Failed to create site. Please try again.');
    } finally {
        saveBtn.disabled = false;
        saveBtn.innerHTML = '<i class="fas fa-save mr-2"></i>Create Site';
    }
});

// Get current location
function getCurrentLocation() {
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            function(position) {
                document.getElementById('latitude').value = position.coords.latitude.toFixed(6);
                document.getElementById('longitude').value = position.coords.longitude.toFixed(6);
                updateMapPreview();
            },
            function(error) {
                showError('Unable to get your location: ' + error.message);
            }
        );
    } else {
        showError('Geolocation is not supported by your browser');
    }
}

// Update map preview when coordinates change
document.getElementById('latitude').addEventListener('change', updateMapPreview);
document.getElementById('longitude').addEventListener('change', updateMapPreview);

function updateMapPreview() {
    const lat = document.getElementById('latitude').value;
    const lng = document.getElementById('longitude').value;
    
    if (lat && lng && lat >= -90 && lat <= 90 && lng >= -180 && lng <= 180) {
        const mapUrl = `https://www.openstreetmap.org/export/embed.html?bbox=${lng-0.01},${lat-0.01},${parseFloat(lng)+0.01},${parseFloat(lat)+0.01}&layer=mapnik&marker=${lat},${lng}`;
        document.getElementById('map-placeholder').classList.add('hidden');
        document.getElementById('map-preview').classList.remove('hidden');
        document.getElementById('map-preview').innerHTML = `<iframe src="${mapUrl}" class="w-full h-full rounded-lg"></iframe>`;
    }
}

function showFieldError(field, message) {
    const errorEl = document.getElementById(`${field}-error`);
    if (errorEl) {
        errorEl.textContent = message;
        errorEl.classList.remove('hidden');
    }
}

function clearErrors() {
    document.querySelectorAll('[id$="-error"]').forEach(el => {
        el.textContent = '';
        el.classList.add('hidden');
    });
}

function showError(message) {
    const toast = document.createElement('div');
    toast.className = 'fixed top-4 right-4 z-50 bg-red-500 text-white px-6 py-3 rounded-lg shadow-lg';
    toast.innerHTML = `<i class="fas fa-exclamation-circle mr-2"></i>${message}`;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 5000);
}

function showSuccess(message) {
    const toast = document.createElement('div');
    toast.className = 'fixed top-4 right-4 z-50 bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg';
    toast.innerHTML = `<i class="fas fa-check-circle mr-2"></i>${message}`;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 5000);
}
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../views/layouts/base.php';
?>
