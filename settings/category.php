<?php
/**
 * Settings Category Page
 * Display and manage settings for a specific category
 */

require_once __DIR__ . '/../config/autoload.php';

// Initialize services
$sessionService = new SessionService();
$authService = new AuthenticationService();
$settingsService = new SettingsService();

// Check authentication
if (!$sessionService->isLoggedIn()) {
    header('Location: ../index.php');
    exit;
}

// Check permission - ADV only
if (!can('system.manage') || !isAdvUser()) {
    header('Location: ../dashboard.php?error=access_denied');
    exit;
}

// Get current user
$currentUserId = $sessionService->getCurrentUserId();
$userModel = new User();
$currentUser = $userModel->findWithRelations($currentUserId);

if (!$currentUser) {
    header('Location: ../logout.php');
    exit;
}

// Get category from URL
$category = $_GET['category'] ?? '';
if (empty($category)) {
    header('Location: index.php');
    exit;
}

// Get settings for this category
try {
    $allSettings = $settingsService->getSettingsByCategory();
    $categorySettings = $allSettings[$category] ?? [];
} catch (Exception $e) {
    error_log("Error loading settings: " . $e->getMessage());
    $categorySettings = [];
}

// If category doesn't exist, redirect back
if (empty($categorySettings)) {
    header('Location: index.php?error=category_not_found');
    exit;
}

// Page setup
$pageTitle = ucfirst($category) . ' Settings - ADV CRM';
$currentPage = 'settings';
$baseUrl = '..';
$isLoggedIn = true;
$menuService = new MenuService();

ob_start();
?>

<style>
/* Toggle switch styles */
input[type="checkbox"]:checked + div {
    background-color: #3B82F6 !important;
}

input[type="checkbox"]:checked + div .dot {
    transform: translateX(100%);
}

.bg-blue-500 {
    background-color: #3B82F6 !important;
}

/* Setting item hover effects */
.setting-item {
    transition: all 0.2s ease;
}

.setting-item:hover {
    border-color: #D1D5DB;
}

/* Validation states */
.border-yellow-300 {
    border-color: #FCD34D !important;
}

.bg-yellow-50 {
    background-color: #FFFBEB !important;
}

.border-blue-300 {
    border-color: #93C5FD !important;
}

.bg-blue-50 {
    background-color: #EFF6FF !important;
}

.border-green-300 {
    border-color: #86EFAC !important;
}

.bg-green-50 {
    background-color: #F0FDF4 !important;
}

.border-red-300 {
    border-color: #FCA5A5 !important;
}

.bg-red-50 {
    background-color: #FEF2F2 !important;
}

/* Modal animations */
#confirmation-modal, #audit-modal {
    animation: fadeIn 0.2s ease;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

/* Loading spinner */
.fa-spinner {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}
</style>

<div class="max-w-4xl mx-auto">
    <!-- Page Header -->
    <div class="mb-8">
        <div class="flex items-center mb-4">
            <a href="index.php" class="text-gray-500 hover:text-gray-700 mr-4">
                <i class="fas fa-arrow-left"></i>
            </a>
            <div class="flex items-center">
                <div class="w-10 h-10 rounded-lg <?php echo getCategoryIconBg($category); ?> flex items-center justify-center mr-3">
                    <i class="<?php echo getCategoryIcon($category); ?> text-lg"></i>
                </div>
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 capitalize"><?php echo htmlspecialchars($category); ?> Settings</h1>
                    <p class="text-gray-600"><?php echo getCategoryDescription($category); ?></p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Alert Messages -->
    <div id="alert-container" class="mb-6 hidden">
        <div id="alert-message" class="p-4 rounded-lg border"></div>
    </div>
    
    <!-- Settings List -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
        <div class="p-6">
            <div class="space-y-6">
                <?php foreach ($categorySettings as $setting): ?>
                    <div class="setting-item border border-gray-100 rounded-lg p-4">
                        <div class="flex items-start justify-between">
                            <div class="flex-1 mr-4">
                                <div class="flex items-center mb-2">
                                    <label class="font-medium text-gray-900" for="setting-<?php echo htmlspecialchars($setting['setting_key']); ?>">
                                        <?php echo htmlspecialchars($setting['display_name'] ?? ucwords(str_replace('_', ' ', $setting['setting_key']))); ?>
                                    </label>
                                    <?php if (($setting['setting_value'] ?? '') !== ($setting['default_value'] ?? '')): ?>
                                        <span class="ml-2 px-2 py-1 text-xs bg-blue-100 text-blue-800 rounded-full">Modified</span>
                                    <?php endif; ?>
                                </div>
                                <?php if (!empty($setting['description'])): ?>
                                    <p class="text-sm text-gray-600 mb-3"><?php echo htmlspecialchars($setting['description']); ?></p>
                                <?php endif; ?>
                                
                                <!-- Setting Input -->
                                <div class="setting-input-container">
                                    <?php echo renderSettingInput($setting); ?>
                                </div>
                                
                                <!-- Validation Message -->
                                <div class="validation-message mt-2 hidden">
                                    <p class="text-sm text-red-600"></p>
                                </div>
                            </div>
                            
                            <!-- Actions -->
                            <div class="flex items-center space-x-2">
                                <?php if (($setting['setting_value'] ?? '') !== ($setting['default_value'] ?? '')): ?>
                                    <button 
                                        class="px-3 py-1 text-sm text-gray-600 hover:text-gray-800 border border-gray-300 rounded hover:bg-gray-50 transition"
                                        onclick="resetSetting('<?php echo htmlspecialchars($setting['setting_key']); ?>')"
                                        title="Reset to default"
                                    >
                                        <i class="fas fa-undo text-xs mr-1"></i>
                                        Reset
                                    </button>
                                <?php endif; ?>
                                <button 
                                    class="px-3 py-1 text-sm text-blue-600 hover:text-blue-800 border border-blue-300 rounded hover:bg-blue-50 transition"
                                    onclick="showAuditHistory('<?php echo htmlspecialchars($setting['setting_key']); ?>')"
                                    title="View change history"
                                >
                                    <i class="fas fa-history text-xs mr-1"></i>
                                    History
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- Confirmation Modal -->
<div id="confirmation-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-lg max-w-md w-full p-6">
            <div class="flex items-center mb-4">
                <div class="w-10 h-10 rounded-full bg-yellow-100 flex items-center justify-center mr-3">
                    <i class="fas fa-exclamation-triangle text-yellow-600"></i>
                </div>
                <h3 class="text-lg font-semibold text-gray-900">Confirm Action</h3>
            </div>
            <p id="confirmation-message" class="text-gray-600 mb-6"></p>
            <div class="flex justify-end space-x-3">
                <button 
                    id="cancel-btn"
                    class="px-4 py-2 text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 transition"
                    onclick="hideConfirmationModal()"
                >
                    Cancel
                </button>
                <button 
                    id="confirm-btn"
                    class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition"
                >
                    Confirm
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Audit History Modal -->
<div id="audit-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-lg max-w-4xl w-full max-h-[90vh] overflow-hidden">
            <div class="flex items-center justify-between p-6 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">Setting Change History</h3>
                <button 
                    class="text-gray-400 hover:text-gray-600 transition"
                    onclick="hideAuditModal()"
                >
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div id="audit-content" class="p-6 overflow-y-auto max-h-[70vh]">
                <!-- Audit history will be loaded here -->
            </div>
        </div>
    </div>
</div>

<script>
// Settings management JavaScript
let pendingChanges = new Map();
let validationTimeouts = new Map();

// Handle setting value changes
function handleSettingChange(key, value, inputElement) {
    // Handle checkbox values
    if (inputElement.type === 'checkbox') {
        value = inputElement.checked ? '1' : '0';
    }
    
    // Clear previous validation timeout
    if (validationTimeouts.has(key)) {
        clearTimeout(validationTimeouts.get(key));
    }
    
    // Store pending change
    pendingChanges.set(key, value);
    
    // Add visual indicator for unsaved changes
    const container = inputElement.closest('.setting-item');
    container.classList.add('border-yellow-300', 'bg-yellow-50');
    
    // Debounced validation and save
    const timeout = setTimeout(() => {
        validateAndSaveSetting(key, value, inputElement);
    }, 1000);
    
    validationTimeouts.set(key, timeout);
}

// Validate and save setting
async function validateAndSaveSetting(key, value, inputElement) {
    const container = inputElement.closest('.setting-item');
    const validationDiv = container.querySelector('.validation-message');
    
    try {
        // Show loading state
        container.classList.remove('border-yellow-300', 'bg-yellow-50');
        container.classList.add('border-blue-300', 'bg-blue-50');
        
        // Validate and save
        const response = await fetch(`../api/settings/update.php`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': getCSRFToken()
            },
            body: JSON.stringify({ key: key, value: value })
        });
        
        const result = await response.json();
        
        if (result.success) {
            // Success state
            container.classList.remove('border-blue-300', 'bg-blue-50');
            container.classList.add('border-green-300', 'bg-green-50');
            validationDiv.classList.add('hidden');
            
            // Remove pending change
            pendingChanges.delete(key);
            
            // Show success message
            showAlert('Setting updated successfully', 'success');
            
            // Reset visual state after delay
            setTimeout(() => {
                container.classList.remove('border-green-300', 'bg-green-50');
            }, 2000);
            
        } else {
            // Validation error
            container.classList.remove('border-blue-300', 'bg-blue-50');
            container.classList.add('border-red-300', 'bg-red-50');
            
            validationDiv.classList.remove('hidden');
            validationDiv.querySelector('p').textContent = result.message || 'Invalid value';
            
            showAlert(result.message || 'Validation failed', 'error');
        }
        
    } catch (error) {
        // Network or other error
        container.classList.remove('border-blue-300', 'bg-blue-50');
        container.classList.add('border-red-300', 'bg-red-50');
        
        validationDiv.classList.remove('hidden');
        validationDiv.querySelector('p').textContent = 'Failed to save setting';
        
        showAlert('Failed to save setting', 'error');
        console.error('Setting save error:', error);
    }
}

// Reset setting to default
function resetSetting(key) {
    showConfirmationModal(
        `Are you sure you want to reset this setting to its default value? This action cannot be undone.`,
        async () => {
            try {
                const response = await fetch(`../api/settings/reset.php`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': getCSRFToken()
                    },
                    body: JSON.stringify({ key: key, confirmed: true })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showAlert('Setting reset to default value', 'success');
                    // Reload page to reflect changes
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showAlert(result.message || 'Failed to reset setting', 'error');
                }
                
            } catch (error) {
                showAlert('Failed to reset setting', 'error');
                console.error('Setting reset error:', error);
            }
        }
    );
}

// Show audit history
async function showAuditHistory(key) {
    const modal = document.getElementById('audit-modal');
    const content = document.getElementById('audit-content');
    
    // Show loading
    content.innerHTML = '<div class="text-center py-8"><i class="fas fa-spinner fa-spin text-2xl text-gray-400"></i></div>';
    modal.classList.remove('hidden');
    
    try {
        const response = await fetch(`../api/settings/audit.php?key=${encodeURIComponent(key)}`);
        const result = await response.json();
        
        if (result.success && result.data.audit_entries && result.data.audit_entries.length > 0) {
            content.innerHTML = result.data.audit_entries.map(entry => {
                // Format user name
                let userName = 'System';
                if (entry.username) {
                    userName = entry.username;
                    if (entry.first_name || entry.last_name) {
                        const fullName = `${entry.first_name || ''} ${entry.last_name || ''}`.trim();
                        if (fullName) {
                            userName = `${fullName} (${entry.username})`;
                        }
                    }
                }
                
                return `
                <div class="border-b border-gray-200 pb-4 mb-4 last:border-b-0">
                    <div class="flex items-center justify-between mb-2">
                        <span class="font-medium text-gray-900">${userName}</span>
                        <span class="text-sm text-gray-500">${formatDateTime(entry.timestamp)}</span>
                    </div>
                    <div class="text-sm text-gray-600">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <span class="font-medium">Previous:</span> 
                                <code class="bg-gray-100 px-2 py-1 rounded">${entry.old_value || 'null'}</code>
                            </div>
                            <div>
                                <span class="font-medium">New:</span> 
                                <code class="bg-gray-100 px-2 py-1 rounded">${entry.new_value || 'null'}</code>
                            </div>
                        </div>
                        ${entry.ip_address ? `<div class="mt-2"><span class="font-medium">IP:</span> ${entry.ip_address}</div>` : ''}
                        ${entry.user_id ? `<div class="mt-1"><span class="font-medium">User ID:</span> ${entry.user_id}</div>` : ''}
                    </div>
                </div>
            `;
            }).join('');
        } else {
            content.innerHTML = '<div class="text-center py-8 text-gray-500">No change history found</div>';
        }
        
    } catch (error) {
        content.innerHTML = '<div class="text-center py-8 text-red-500">Failed to load audit history</div>';
        console.error('Audit history error:', error);
    }
}

// Utility functions
function showAlert(message, type = 'info') {
    const container = document.getElementById('alert-container');
    const messageDiv = document.getElementById('alert-message');
    
    const colors = {
        success: 'bg-green-50 border-green-200 text-green-800',
        error: 'bg-red-50 border-red-200 text-red-800',
        warning: 'bg-yellow-50 border-yellow-200 text-yellow-800',
        info: 'bg-blue-50 border-blue-200 text-blue-800'
    };
    
    messageDiv.className = `p-4 rounded-lg border ${colors[type] || colors.info}`;
    messageDiv.textContent = message;
    container.classList.remove('hidden');
    
    // Auto-hide after 5 seconds
    setTimeout(() => {
        container.classList.add('hidden');
    }, 5000);
}

function showConfirmationModal(message, onConfirm) {
    const modal = document.getElementById('confirmation-modal');
    const messageEl = document.getElementById('confirmation-message');
    const confirmBtn = document.getElementById('confirm-btn');
    
    messageEl.textContent = message;
    modal.classList.remove('hidden');
    
    // Remove previous event listeners
    const newConfirmBtn = confirmBtn.cloneNode(true);
    confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);
    
    // Add new event listener
    newConfirmBtn.addEventListener('click', () => {
        hideConfirmationModal();
        onConfirm();
    });
}

function hideConfirmationModal() {
    document.getElementById('confirmation-modal').classList.add('hidden');
}

function hideAuditModal() {
    document.getElementById('audit-modal').classList.add('hidden');
}

function getCSRFToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
}

function formatDateTime(dateString) {
    return new Date(dateString).toLocaleString();
}

// Initialize page
document.addEventListener('DOMContentLoaded', function() {
    // Add CSRF token to meta if not present
    if (!document.querySelector('meta[name="csrf-token"]')) {
        const meta = document.createElement('meta');
        meta.name = 'csrf-token';
        meta.content = '<?php echo $_SESSION["csrf_token"] ?? ""; ?>';
        document.head.appendChild(meta);
    }
    
    // Initialize toggle switches
    const checkboxes = document.querySelectorAll('input[type="checkbox"]');
    checkboxes.forEach(checkbox => {
        updateToggleSwitch(checkbox);
        checkbox.addEventListener('change', function() {
            updateToggleSwitch(this);
        });
    });
});

// Update toggle switch visual state
function updateToggleSwitch(checkbox) {
    const toggle = checkbox.nextElementSibling;
    if (toggle) {
        if (checkbox.checked) {
            toggle.classList.add('bg-blue-500');
            toggle.classList.remove('bg-gray-300');
        } else {
            toggle.classList.add('bg-gray-300');
            toggle.classList.remove('bg-blue-500');
        }
    }
}
</script>

<?php
// Helper functions for rendering
function getCategoryIcon($category) {
    $icons = [
        'general' => 'fas fa-cog text-gray-600',
        'security' => 'fas fa-shield-alt text-red-500',
        'email' => 'fas fa-envelope text-blue-500',
        'backup' => 'fas fa-database text-green-500',
        'logging' => 'fas fa-file-alt text-yellow-600',
        'performance' => 'fas fa-tachometer-alt text-purple-500'
    ];
    return $icons[strtolower($category)] ?? 'fas fa-cog text-gray-600';
}

function getCategoryIconBg($category) {
    $backgrounds = [
        'general' => 'bg-gray-100',
        'security' => 'bg-red-100',
        'email' => 'bg-blue-100',
        'backup' => 'bg-green-100',
        'logging' => 'bg-yellow-100',
        'performance' => 'bg-purple-100'
    ];
    return $backgrounds[strtolower($category)] ?? 'bg-gray-100';
}

function getCategoryDescription($category) {
    $descriptions = [
        'general' => 'Basic system configuration',
        'security' => 'Security and authentication settings',
        'email' => 'Email and notification configuration',
        'backup' => 'Database backup settings',
        'logging' => 'System logging configuration',
        'performance' => 'Performance and optimization settings'
    ];
    return $descriptions[strtolower($category)] ?? 'System settings';
}

function renderSettingInput($setting) {
    $key = htmlspecialchars($setting['setting_key']);
    $value = htmlspecialchars($setting['setting_value'] ?? $setting['default_value'] ?? '');
    $type = $setting['data_type'] ?? 'string';
    $constraints = json_decode($setting['validation_rules'] ?? '{}', true) ?: [];
    
    $inputId = "setting-{$key}";
    $onChangeAttr = "onchange=\"handleSettingChange('{$key}', this.value, this)\"";
    
    switch ($type) {
        case 'boolean':
            $checked = $value === '1' || $value === 'true' ? 'checked' : '';
            return "<label class=\"flex items-center cursor-pointer\">
                        <input type=\"checkbox\" id=\"{$inputId}\" {$checked} onchange=\"handleSettingChange('{$key}', this.value, this)\" class=\"sr-only\">
                        <div class=\"relative\">
                            <div class=\"block bg-gray-300 w-14 h-8 rounded-full\"></div>
                            <div class=\"dot absolute left-1 top-1 bg-white w-6 h-6 rounded-full transition\"></div>
                        </div>
                    </label>";
                    
        case 'integer':
            $min = isset($constraints['min_value']) ? "min=\"{$constraints['min_value']}\"" : '';
            $max = isset($constraints['max_value']) ? "max=\"{$constraints['max_value']}\"" : '';
            return "<input type=\"number\" id=\"{$inputId}\" value=\"{$value}\" {$min} {$max} {$onChangeAttr} 
                           class=\"w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500\">";
                           
        case 'json':
            return "<textarea id=\"{$inputId}\" rows=\"4\" {$onChangeAttr}
                             class=\"w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 font-mono text-sm\"
                             placeholder=\"Enter valid JSON\">{$value}</textarea>";
                             
        case 'text':
            return "<textarea id=\"{$inputId}\" rows=\"3\" {$onChangeAttr}
                             class=\"w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500\">{$value}</textarea>";
                             
        default: // string
            if (isset($constraints['allowed_values']) && is_array($constraints['allowed_values'])) {
                $options = '';
                foreach ($constraints['allowed_values'] as $option) {
                    $selected = $option === $value ? 'selected' : '';
                    $options .= "<option value=\"" . htmlspecialchars($option) . "\" {$selected}>" . htmlspecialchars($option) . "</option>";
                }
                return "<select id=\"{$inputId}\" {$onChangeAttr} 
                               class=\"w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500\">
                            {$options}
                        </select>";
            } else {
                $maxLength = isset($constraints['max_length']) ? "maxlength=\"{$constraints['max_length']}\"" : '';
                return "<input type=\"text\" id=\"{$inputId}\" value=\"{$value}\" {$maxLength} {$onChangeAttr}
                               class=\"w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500\">";
            }
    }
}
?>

<?php
$content = ob_get_clean();
include __DIR__ . '/../views/layouts/base.php';
?>