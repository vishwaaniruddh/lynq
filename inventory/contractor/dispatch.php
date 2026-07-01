<?php
/**
 * Contractor Dispatch Page
 * 
 * Allows contractors to dispatch materials to engineers or return to ADV
 * Includes:
 * - Destination selector showing engineers and ADV
 * - Product/quantity selector from contractor's inventory
 * - Inventory validation feedback
 * - Available quantities display
 * - Prevention of dispatching unavailable items
 * 
 * Requirements: 5.1, 5.2, 5.3, 5.4, 5.5
 */

require_once __DIR__ . '/../../config/autoload.php';

$sessionService = new SessionService();
if (!$sessionService->isLoggedIn()) {
    header('Location: ../../views/auth/login.php');
    exit;
}

// Check contractor access
if (!isContractorUser()) {
    $_SESSION['flash_error'] = 'Access denied. Contractor users only.';
    header('Location: ../../dashboard.php');
    exit;
}

$currentUser = $sessionService->getCurrentUser();
$baseUrl = '../..';
$pageTitle = 'Dispatch Materials';
$currentPage = 'contractor_dispatch';
$isLoggedIn = true;
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '../../contractor/dashboard.php'],
    ['label' => 'Inventory'],
    ['label' => 'Dispatch Materials']
];

ob_start();
?>

<div class="max-w-5xl mx-auto">
    <!-- Header -->
    <div class="bg-white rounded-xl shadow-sm mb-6">
        <div class="p-6 border-b">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-semibold text-gray-800">Dispatch Materials</h3>
                    <p class="text-sm text-gray-500">Send materials to engineers or return to ADV warehouse</p>
                </div>
                <a href="stocks.php" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">
                    <i class="fas fa-boxes mr-2"></i>View My Stocks
                </a>
            </div>
        </div>
    </div>

    <!-- Dispatch Form -->
    <div class="bg-white rounded-xl shadow-sm">
        <form id="dispatch-form" class="p-6 space-y-6">
            <!-- Step 1: Destination Selection -->
            <div class="space-y-4">
                <h4 class="text-md font-semibold text-gray-700 flex items-center">
                    <span class="w-6 h-6 bg-primary text-white rounded-full flex items-center justify-center text-sm mr-2">1</span>
                    Select Destination
                </h4>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- Dispatch to Engineer -->
                    <label class="destination-option relative flex items-start p-4 border-2 rounded-xl cursor-pointer hover:border-primary/50 transition" data-type="engineer">
                        <input type="radio" name="destination_type" value="engineer" class="sr-only">
                        <div class="flex items-center">
                            <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mr-4">
                                <i class="fas fa-user-hard-hat text-blue-500 text-xl"></i>
                            </div>
                            <div>
                                <p class="font-medium text-gray-800">Dispatch to Engineer</p>
                                <p class="text-sm text-gray-500">Send materials to field engineers</p>
                            </div>
                        </div>
                        <div class="absolute top-3 right-3 w-5 h-5 border-2 rounded-full destination-check hidden">
                            <i class="fas fa-check text-white text-xs"></i>
                        </div>
                    </label>
                    
                    <!-- Return to ADV -->
                    <label class="destination-option relative flex items-start p-4 border-2 rounded-xl cursor-pointer hover:border-primary/50 transition" data-type="adv">
                        <input type="radio" name="destination_type" value="adv" class="sr-only">
                        <div class="flex items-center">
                            <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center mr-4">
                                <i class="fas fa-warehouse text-green-500 text-xl"></i>
                            </div>
                            <div>
                                <p class="font-medium text-gray-800">Return to ADV</p>
                                <p class="text-sm text-gray-500">Return materials to ADV warehouse</p>
                            </div>
                        </div>
                        <div class="absolute top-3 right-3 w-5 h-5 border-2 rounded-full destination-check hidden">
                            <i class="fas fa-check text-white text-xs"></i>
                        </div>
                    </label>
                </div>
                
                <!-- Engineer Selection (shown when dispatching to engineer) -->
                <div id="engineer-selection" class="hidden mt-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Select Engineer <span class="text-red-500">*</span></label>
                    <select id="engineer-select" class="w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                        <option value="">Loading engineers...</option>
                    </select>
                </div>
                
                <!-- ADV Warehouse Selection (shown when returning to ADV) -->
                <div id="warehouse-selection" class="hidden mt-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Select ADV Warehouse <span class="text-red-500">*</span></label>
                    <select id="warehouse-select" class="w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                        <option value="">Loading warehouses...</option>
                    </select>
                </div>
            </div>
            
            <!-- Step 2: Item Selection -->
            <div class="space-y-4 pt-4 border-t">
                <h4 class="text-md font-semibold text-gray-700 flex items-center">
                    <span class="w-6 h-6 bg-primary text-white rounded-full flex items-center justify-center text-sm mr-2">2</span>
                    Select Items to Dispatch
                </h4>
                
                <!-- Inventory Summary -->
                <div id="inventory-summary" class="bg-gray-50 p-4 rounded-lg">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-sm font-medium text-gray-600">Your Available Inventory</span>
                        <button type="button" onclick="refreshInventory()" class="text-sm text-primary hover:underline">
                            <i class="fas fa-sync-alt mr-1"></i>Refresh
                        </button>
                    </div>
                    <div id="inventory-loading" class="text-center py-4">
                        <i class="fas fa-spinner fa-spin text-primary"></i>
                        <span class="ml-2 text-gray-500">Loading inventory...</span>
                    </div>
                    <div id="inventory-empty" class="hidden text-center py-4 text-gray-500">
                        <i class="fas fa-box-open text-3xl mb-2"></i>
                        <p>No inventory available for dispatch</p>
                    </div>
                    <div id="inventory-list" class="hidden space-y-2 max-h-64 overflow-y-auto"></div>
                </div>
                
                <!-- Selected Items -->
                <div id="selected-items-container" class="hidden">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Selected Items for Dispatch</label>
                    <div id="selected-items" class="border rounded-lg divide-y"></div>
                    
                    <!-- Validation Messages -->
                    <div id="validation-messages" class="hidden mt-3 p-3 bg-red-50 border border-red-200 rounded-lg">
                        <div class="flex items-start">
                            <i class="fas fa-exclamation-triangle text-red-500 mt-0.5 mr-2"></i>
                            <div id="validation-text" class="text-sm text-red-700"></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Step 3: Site & Notes -->
            <div class="space-y-4 pt-4 border-t">
                <h4 class="text-md font-semibold text-gray-700 flex items-center">
                    <span class="w-6 h-6 bg-primary text-white rounded-full flex items-center justify-center text-sm mr-2">3</span>
                    Additional Information
                </h4>
                
                <!-- Site Selection -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Site (Optional)</label>
                    <select id="site-select" class="w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                        <option value="">Select a site (optional)</option>
                    </select>
                    <p class="text-xs text-gray-500 mt-1">Associate this dispatch with a delegated site</p>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Notes (Optional)</label>
                    <textarea id="dispatch-notes" rows="3" placeholder="Add any notes about this dispatch..." 
                        class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"></textarea>
                </div>
            </div>
            
            <!-- Submit Section -->
            <div class="pt-4 border-t flex items-center justify-between">
                <div id="dispatch-summary" class="text-sm text-gray-600">
                    <span id="summary-text">Select destination and items to dispatch</span>
                </div>
                <div class="flex items-center gap-3">
                    <button type="button" onclick="resetForm()" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">
                        <i class="fas fa-undo mr-2"></i>Reset
                    </button>
                    <button type="submit" id="submit-btn" disabled class="px-6 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 transition disabled:opacity-50 disabled:cursor-not-allowed">
                        <i class="fas fa-paper-plane mr-2"></i>Create Dispatch
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Confirmation Modal -->
<div id="confirm-modal" class="hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="fixed inset-0 bg-black/60 backdrop-blur-sm" onclick="closeConfirmModal()"></div>
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-lg w-full relative z-10">
            <div class="flex items-center justify-between p-5 border-b border-gray-100">
                <h3 class="text-lg font-semibold text-gray-800">Confirm Dispatch</h3>
                <button onclick="closeConfirmModal()" class="w-8 h-8 rounded-lg hover:bg-gray-100 flex items-center justify-center text-gray-400 hover:text-gray-600 transition">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="p-5 space-y-4">
                <div class="bg-blue-50 p-4 rounded-lg">
                    <div class="flex items-center">
                        <i class="fas fa-info-circle text-blue-500 text-xl mr-3"></i>
                        <div>
                            <p class="font-medium text-blue-800">Review Dispatch Details</p>
                            <p class="text-sm text-blue-600">Please confirm the details below before submitting</p>
                        </div>
                    </div>
                </div>
                
                <div id="confirm-details" class="space-y-3">
                    <!-- Details will be populated here -->
                </div>
            </div>
            
            <div class="flex justify-end space-x-3 p-5 border-t border-gray-100 bg-gray-50 rounded-b-2xl">
                <button onclick="closeConfirmModal()" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">Cancel</button>
                <button id="confirm-submit-btn" onclick="submitDispatch()" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 transition">
                    <i class="fas fa-check mr-2"></i>Confirm & Dispatch
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Success Modal -->
<div id="success-modal" class="hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="fixed inset-0 bg-black/60 backdrop-blur-sm"></div>
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full relative z-10 text-center p-8">
            <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-check text-green-500 text-3xl"></i>
            </div>
            <h3 class="text-xl font-semibold text-gray-800 mb-2">Dispatch Created!</h3>
            <p id="success-message" class="text-gray-600 mb-4">Your dispatch has been created successfully.</p>
            <p class="text-sm text-gray-500 mb-6">Dispatch Number: <span id="success-dispatch-number" class="font-mono font-medium text-primary"></span></p>
            <div class="flex justify-center gap-3">
                <button onclick="closeSuccessModal(); resetForm();" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">
                    Create Another
                </button>
                <a href="stocks.php" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 transition">
                    View Stocks
                </a>
            </div>
        </div>
    </div>
</div>

<style>
.destination-option.selected {
    border-color: var(--primary-color, #3b82f6);
    background-color: rgba(59, 130, 246, 0.05);
}
.destination-option.selected .destination-check {
    display: flex !important;
    align-items: center;
    justify-content: center;
    background-color: var(--primary-color, #3b82f6);
    border-color: var(--primary-color, #3b82f6);
}
</style>

<!-- Pass company ID from PHP to JavaScript -->
<script>
const COMPANY_ID = <?php echo json_encode($currentUser['company_id']); ?>;
// Get URL parameters
const urlParams = new URLSearchParams(window.location.search);
const URL_SITE_ID = urlParams.get('site_id') ? parseInt(urlParams.get('site_id')) : null;
const URL_DESTINATION = urlParams.get('destination'); // 'engineer' or 'adv'
</script>

<script>
// State management
const state = {
    destinationType: null,
    selectedEngineerId: null,
    selectedWarehouseId: null,
    selectedSiteId: null,
    inventory: [],
    selectedItems: [],
    destinations: { users: [], warehouses: [] },
    sites: [],
    companyId: COMPANY_ID
};

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    loadDestinations();
    loadInventory();
    loadSites();
    setupEventListeners();
    
    // Handle URL parameters for pre-selection
    if (URL_DESTINATION) {
        setTimeout(() => {
            selectDestinationType(URL_DESTINATION);
        }, 100);
    }
});

function setupEventListeners() {
    // Destination type selection
    document.querySelectorAll('.destination-option').forEach(option => {
        option.addEventListener('click', function() {
            selectDestinationType(this.dataset.type);
        });
    });
    
    // Engineer selection
    document.getElementById('engineer-select').addEventListener('change', function() {
        state.selectedEngineerId = this.value ? parseInt(this.value) : null;
        updateSubmitButton();
        updateSummary();
    });
    
    // Warehouse selection
    document.getElementById('warehouse-select').addEventListener('change', function() {
        state.selectedWarehouseId = this.value ? parseInt(this.value) : null;
        updateSubmitButton();
        updateSummary();
    });
    
    // Site selection
    document.getElementById('site-select').addEventListener('change', function() {
        state.selectedSiteId = this.value ? parseInt(this.value) : null;
        updateSummary();
    });
    
    // Form submission
    document.getElementById('dispatch-form').addEventListener('submit', function(e) {
        e.preventDefault();
        showConfirmModal();
    });
}

function selectDestinationType(type) {
    state.destinationType = type;
    
    // Update UI
    document.querySelectorAll('.destination-option').forEach(opt => {
        opt.classList.remove('selected');
    });
    document.querySelector(`.destination-option[data-type="${type}"]`).classList.add('selected');
    
    // Show/hide selection dropdowns
    document.getElementById('engineer-selection').classList.toggle('hidden', type !== 'engineer');
    document.getElementById('warehouse-selection').classList.toggle('hidden', type !== 'adv');
    
    // Reset selections
    if (type === 'engineer') {
        state.selectedWarehouseId = null;
        document.getElementById('warehouse-select').value = '';
    } else {
        state.selectedEngineerId = null;
        document.getElementById('engineer-select').value = '';
    }
    
    updateSubmitButton();
    updateSummary();
}

async function loadDestinations() {
    try {
        // Load valid destinations using the company ID from PHP
        const response = await fetch(`../../api/inventory/dispatch/destinations.php?sender_type=company&sender_id=${state.companyId}`, {
            credentials: 'include'
        });
        const result = await response.json();
        
        if (result.success) {
            state.destinations = result.data;
            populateDestinations();
        } else {
            console.error('Failed to load destinations:', result.message);
            showToast('Failed to load destinations', 'error');
        }
    } catch (error) {
        console.error('Error loading destinations:', error);
        showToast('Failed to load destinations', 'error');
    }
}

function populateDestinations() {
    // Populate engineers dropdown
    const engineerSelect = document.getElementById('engineer-select');
    engineerSelect.innerHTML = '<option value="">Select an engineer</option>';
    
    if (state.destinations.users && state.destinations.users.length > 0) {
        state.destinations.users.forEach(user => {
            const name = `${user.first_name} ${user.last_name}`.trim();
            engineerSelect.innerHTML += `<option value="${user.id}">${name} (${user.email || 'No email'})</option>`;
        });
    } else {
        engineerSelect.innerHTML = '<option value="">No engineers available</option>';
    }
    
    // Populate warehouses dropdown
    const warehouseSelect = document.getElementById('warehouse-select');
    warehouseSelect.innerHTML = '<option value="">Select a warehouse</option>';
    
    if (state.destinations.warehouses && state.destinations.warehouses.length > 0) {
        state.destinations.warehouses.forEach(warehouse => {
            warehouseSelect.innerHTML += `<option value="${warehouse.id}">${warehouse.name}</option>`;
        });
    } else {
        warehouseSelect.innerHTML = '<option value="">No ADV warehouses available</option>';
    }
}

async function loadSites() {
    try {
        // Load delegated sites for contractor
        const response = await fetch(`../../api/delegations/index.php?status=accepted&limit=100`, {
            credentials: 'include'
        });
        const result = await response.json();
        
        if (result.success && result.data && result.data.delegations) {
            state.sites = result.data.delegations;
            populateSites();
        } else {
            console.error('Failed to load sites:', result.message);
        }
    } catch (error) {
        console.error('Error loading sites:', error);
    }
}

function populateSites() {
    const siteSelect = document.getElementById('site-select');
    siteSelect.innerHTML = '<option value="">Select a site (optional)</option>';
    
    if (state.sites && state.sites.length > 0) {
        state.sites.forEach(delegation => {
            const siteName = delegation.site_name || 'Unknown Site';
            const lho = delegation.lho ? ` (${delegation.lho})` : '';
            const city = delegation.city ? ` - ${delegation.city}` : '';
            siteSelect.innerHTML += `<option value="${delegation.site_id}">${siteName}${lho}${city}</option>`;
        });
        
        // Check for URL parameter first, then auto-select if only one site
        if (URL_SITE_ID) {
            const matchingSite = state.sites.find(s => parseInt(s.site_id) === URL_SITE_ID);
            if (matchingSite) {
                siteSelect.value = URL_SITE_ID;
                state.selectedSiteId = URL_SITE_ID;
                updateSummary();
            }
        } else if (state.sites.length === 1) {
            siteSelect.value = state.sites[0].site_id;
            state.selectedSiteId = parseInt(state.sites[0].site_id);
            updateSummary();
        }
    }
}

async function loadInventory() {
    const loadingEl = document.getElementById('inventory-loading');
    const emptyEl = document.getElementById('inventory-empty');
    const listEl = document.getElementById('inventory-list');
    
    loadingEl.classList.remove('hidden');
    emptyEl.classList.add('hidden');
    listEl.classList.add('hidden');
    
    try {
        const response = await fetch(`../../api/inventory/counter/get.php?entity_type=company&entity_id=${state.companyId}`, {
            credentials: 'include'
        });
        const result = await response.json();
        
        loadingEl.classList.add('hidden');
        
        if (result.success && result.data.counters && result.data.counters.length > 0) {
            state.inventory = result.data.counters.filter(c => c.available_quantity > 0);
            
            if (state.inventory.length > 0) {
                renderInventoryList();
                listEl.classList.remove('hidden');
                
                // If site_id is provided via URL, load and auto-select site-specific products
                if (URL_SITE_ID) {
                    await loadSiteSpecificProducts();
                }
            } else {
                emptyEl.classList.remove('hidden');
            }
        } else {
            emptyEl.classList.remove('hidden');
        }
    } catch (error) {
        console.error('Error loading inventory:', error);
        loadingEl.classList.add('hidden');
        emptyEl.classList.remove('hidden');
        emptyEl.innerHTML = '<i class="fas fa-exclamation-circle text-3xl mb-2 text-red-400"></i><p>Failed to load inventory</p>';
    }
}

// Load products received for a specific site and auto-select them
async function loadSiteSpecificProducts() {
    try {
        // Get all accepted pending receives for the company
        const response = await fetch(`../../api/inventory/receive/pending.php?view=company`, {
            credentials: 'include'
        });
        const result = await response.json();
        
        if (result.success && result.data.pending_receives) {
            // Filter receives that have the matching site_id (from accepted dispatches)
            const siteReceives = result.data.pending_receives.filter(pr => {
                const prSiteId = pr.site_id ? parseInt(pr.site_id) : null;
                return prSiteId === URL_SITE_ID && pr.status === 'accepted';
            });
            
            console.log('Site receives found:', siteReceives);
            
            if (siteReceives.length > 0) {
                // Collect all products with quantities from site-specific receives
                const siteProducts = new Map(); // product_id -> total quantity
                
                siteReceives.forEach(receive => {
                    if (receive.items && receive.items.length > 0) {
                        receive.items.forEach(item => {
                            const productId = parseInt(item.product_id);
                            const qty = parseInt(item.received_quantity || item.expected_quantity || 1);
                            if (siteProducts.has(productId)) {
                                siteProducts.set(productId, siteProducts.get(productId) + qty);
                            } else {
                                siteProducts.set(productId, qty);
                            }
                        });
                    }
                });
                
                console.log('Site products:', Array.from(siteProducts.entries()));
                
                // Auto-select products that match site-specific receives
                siteProducts.forEach((quantity, productId) => {
                    const inventoryItem = state.inventory.find(i => parseInt(i.product_id) === productId);
                    if (inventoryItem && inventoryItem.available_quantity > 0) {
                        // Check if not already selected
                        if (!state.selectedItems.some(s => parseInt(s.product_id) === productId)) {
                            // Use the minimum of received quantity and available quantity
                            const selectQty = Math.min(quantity, inventoryItem.available_quantity);
                            state.selectedItems.push({
                                product_id: productId,
                                product_name: inventoryItem.product_name,
                                quantity: selectQty,
                                available_quantity: inventoryItem.available_quantity,
                                is_serializable: inventoryItem.is_serializable || false,
                                serial_numbers: inventoryItem.serial_numbers || []
                            });
                        }
                    }
                });
                
                // Update UI
                if (state.selectedItems.length > 0) {
                    renderInventoryList();
                    renderSelectedItems();
                    updateSubmitButton();
                    updateSummary();
                    showToast(`Auto-selected ${state.selectedItems.length} product(s) received for this site`, 'info');
                }
            } else {
                console.log('No site-specific receives found for site_id:', URL_SITE_ID);
            }
        }
    } catch (error) {
        console.error('Error loading site-specific products:', error);
    }
}

function refreshInventory() {
    loadInventory();
}

function renderInventoryList() {
    const listEl = document.getElementById('inventory-list');
    listEl.innerHTML = '';
    
    state.inventory.forEach(item => {
        const isSelected = state.selectedItems.some(s => s.product_id === item.product_id);
        const selectedItem = state.selectedItems.find(s => s.product_id === item.product_id);
        const remainingQty = isSelected ? item.available_quantity - (selectedItem?.quantity || 0) : item.available_quantity;
        const isOverLimit = isSelected && selectedItem.quantity > item.available_quantity;
        const isSerializable = item.is_serializable && item.serial_numbers && item.serial_numbers.length > 0;
        
        const itemEl = document.createElement('div');
        itemEl.className = `flex flex-col p-3 rounded-lg border ${isOverLimit ? 'bg-red-50 border-red-300' : isSelected ? 'bg-primary/5 border-primary/30' : 'bg-white hover:bg-gray-50'} transition cursor-pointer`;
        
        // Build serial numbers display
        let serialNumbersHtml = '';
        if (isSerializable) {
            const serialList = item.serial_numbers.slice(0, 5).map(sn => 
                `<span class="inline-block px-2 py-0.5 bg-gray-100 text-gray-600 text-xs rounded mr-1 mb-1" title="Status: ${sn.status}">${sn.serial_number}</span>`
            ).join('');
            const moreCount = item.serial_numbers.length > 5 ? `<span class="text-xs text-gray-400">+${item.serial_numbers.length - 5} more</span>` : '';
            serialNumbersHtml = `
                <div class="mt-2 pt-2 border-t border-gray-100">
                    <p class="text-xs text-gray-500 mb-1"><i class="fas fa-barcode mr-1"></i>Serial Numbers:</p>
                    <div class="flex flex-wrap">${serialList}${moreCount}</div>
                </div>
            `;
        }
        
        itemEl.innerHTML = `
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <div class="w-10 h-10 ${isOverLimit ? 'bg-red-100' : 'bg-gray-100'} rounded-lg flex items-center justify-center mr-3">
                        <i class="fas ${isOverLimit ? 'fa-exclamation-triangle text-red-500' : isSerializable ? 'fa-barcode text-gray-400' : 'fa-box text-gray-400'}"></i>
                    </div>
                    <div>
                        <p class="font-medium text-gray-800">${item.product_name || 'Unknown Product'}</p>
                        <p class="text-xs text-gray-500">${item.category_name || 'Uncategorized'}${isSerializable ? ' <span class="text-blue-500">(Serializable)</span>' : ''}</p>
                        ${isOverLimit ? '<p class="text-xs text-red-600 font-medium mt-1"><i class="fas fa-exclamation-circle mr-1"></i>Exceeds available quantity!</p>' : ''}
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <div class="text-right">
                        <p class="text-sm font-medium ${remainingQty > 0 ? 'text-green-600' : remainingQty < 0 ? 'text-red-600' : 'text-gray-400'}">
                            ${remainingQty >= 0 ? remainingQty : 0} available
                        </p>
                        ${isSelected ? `<p class="text-xs ${isOverLimit ? 'text-red-600 font-medium' : 'text-primary'}">${selectedItem.quantity} selected</p>` : ''}
                        <p class="text-xs text-gray-400">Total: ${item.available_quantity}</p>
                    </div>
                    <button type="button" onclick="toggleItemSelection(${item.product_id})" 
                        class="w-8 h-8 rounded-lg ${isSelected ? 'bg-primary text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'} flex items-center justify-center transition">
                        <i class="fas ${isSelected ? 'fa-check' : 'fa-plus'}"></i>
                    </button>
                </div>
            </div>
            ${serialNumbersHtml}
        `;
        listEl.appendChild(itemEl);
    });
}

function toggleItemSelection(productId) {
    const existingIndex = state.selectedItems.findIndex(s => s.product_id === productId);
    
    if (existingIndex >= 0) {
        // Remove item
        state.selectedItems.splice(existingIndex, 1);
    } else {
        // Add item with default quantity of 1
        const inventoryItem = state.inventory.find(i => i.product_id === productId);
        if (inventoryItem && inventoryItem.available_quantity > 0) {
            state.selectedItems.push({
                product_id: productId,
                product_name: inventoryItem.product_name,
                quantity: 1,
                available_quantity: inventoryItem.available_quantity,
                is_serializable: inventoryItem.is_serializable || false,
                serial_numbers: inventoryItem.serial_numbers || []
            });
        }
    }
    
    renderInventoryList();
    renderSelectedItems();
    updateSubmitButton();
    updateSummary();
}

function renderSelectedItems() {
    const container = document.getElementById('selected-items-container');
    const itemsEl = document.getElementById('selected-items');
    
    if (state.selectedItems.length === 0) {
        container.classList.add('hidden');
        return;
    }
    
    container.classList.remove('hidden');
    itemsEl.innerHTML = '';
    
    state.selectedItems.forEach((item, index) => {
        const isOverLimit = item.quantity > item.available_quantity;
        const isSerializable = item.is_serializable && item.serial_numbers && item.serial_numbers.length > 0;
        
        // Build serial numbers display for selected items
        let serialNumbersHtml = '';
        if (isSerializable) {
            const serialList = item.serial_numbers.slice(0, 3).map(sn => 
                `<span class="inline-block px-1.5 py-0.5 bg-blue-50 text-blue-600 text-xs rounded mr-1">${sn.serial_number}</span>`
            ).join('');
            const moreCount = item.serial_numbers.length > 3 ? `<span class="text-xs text-gray-400">+${item.serial_numbers.length - 3} more</span>` : '';
            serialNumbersHtml = `<div class="mt-1 flex flex-wrap items-center"><span class="text-xs text-gray-400 mr-1">S/N:</span>${serialList}${moreCount}</div>`;
        }
        
        const itemEl = document.createElement('div');
        itemEl.className = `flex items-center justify-between p-3 ${isOverLimit ? 'bg-red-50' : ''}`;
        itemEl.innerHTML = `
            <div class="flex items-center flex-1">
                <div class="w-8 h-8 ${isOverLimit ? 'bg-red-100' : 'bg-primary/10'} rounded-lg flex items-center justify-center mr-3">
                    <i class="fas ${isOverLimit ? 'fa-exclamation-triangle text-red-500' : isSerializable ? 'fa-barcode text-primary' : 'fa-box text-primary'} text-sm"></i>
                </div>
                <div class="flex-1">
                    <p class="font-medium text-gray-800">${item.product_name}</p>
                    <p class="text-xs ${isOverLimit ? 'text-red-600 font-medium' : 'text-gray-500'}">
                        ${isOverLimit ? '<i class="fas fa-exclamation-circle mr-1"></i>Only ' : 'Available: '}${item.available_quantity} available
                    </p>
                    ${serialNumbersHtml}
                </div>
            </div>
            <div class="flex items-center gap-3">
                <div class="flex items-center border ${isOverLimit ? 'border-red-300' : ''} rounded-lg">
                    <button type="button" onclick="adjustQuantity(${index}, -1)" 
                        class="w-8 h-8 flex items-center justify-center text-gray-500 hover:bg-gray-100 rounded-l-lg transition">
                        <i class="fas fa-minus text-xs"></i>
                    </button>
                    <input type="number" value="${item.quantity}" min="1" max="${item.available_quantity}" 
                        onchange="setQuantity(${index}, this.value)"
                        class="w-16 text-center border-x py-1 focus:outline-none ${isOverLimit ? 'text-red-600 font-medium' : ''}">
                    <button type="button" onclick="adjustQuantity(${index}, 1)" 
                        class="w-8 h-8 flex items-center justify-center text-gray-500 hover:bg-gray-100 rounded-r-lg transition ${item.quantity >= item.available_quantity ? 'opacity-50 cursor-not-allowed' : ''}"
                        ${item.quantity >= item.available_quantity ? 'disabled' : ''}>
                        <i class="fas fa-plus text-xs"></i>
                    </button>
                </div>
                <button type="button" onclick="removeItem(${index})" 
                    class="w-8 h-8 rounded-lg bg-red-100 text-red-500 hover:bg-red-200 flex items-center justify-center transition">
                    <i class="fas fa-trash text-xs"></i>
                </button>
            </div>
        `;
        itemsEl.appendChild(itemEl);
    });
    
    validateItems();
}

function adjustQuantity(index, delta) {
    const item = state.selectedItems[index];
    const newQty = item.quantity + delta;
    
    if (newQty >= 1 && newQty <= item.available_quantity) {
        item.quantity = newQty;
        renderSelectedItems();
        renderInventoryList();
        updateSummary();
    }
}

function setQuantity(index, value) {
    const item = state.selectedItems[index];
    const newQty = parseInt(value) || 1;
    
    item.quantity = Math.max(1, Math.min(newQty, item.available_quantity));
    renderSelectedItems();
    renderInventoryList();
    updateSummary();
}

function removeItem(index) {
    state.selectedItems.splice(index, 1);
    renderSelectedItems();
    renderInventoryList();
    updateSubmitButton();
    updateSummary();
}

function validateItems() {
    const messagesEl = document.getElementById('validation-messages');
    const textEl = document.getElementById('validation-text');
    const errors = [];
    
    state.selectedItems.forEach(item => {
        if (item.quantity > item.available_quantity) {
            errors.push(`${item.product_name}: Requested ${item.quantity}, but only ${item.available_quantity} available`);
        }
        if (item.quantity < 1) {
            errors.push(`${item.product_name}: Quantity must be at least 1`);
        }
    });
    
    if (errors.length > 0) {
        textEl.innerHTML = errors.join('<br>');
        messagesEl.classList.remove('hidden');
        return false;
    } else {
        messagesEl.classList.add('hidden');
        return true;
    }
}

function updateSubmitButton() {
    const btn = document.getElementById('submit-btn');
    const hasDestination = (state.destinationType === 'engineer' && state.selectedEngineerId) ||
                          (state.destinationType === 'adv' && state.selectedWarehouseId);
    const hasItems = state.selectedItems.length > 0;
    const isValid = validateItems();
    
    btn.disabled = !(hasDestination && hasItems && isValid);
}

function updateSummary() {
    const summaryEl = document.getElementById('summary-text');
    const parts = [];
    
    if (state.destinationType === 'engineer' && state.selectedEngineerId) {
        const engineer = state.destinations.users.find(u => u.id == state.selectedEngineerId);
        if (engineer) {
            parts.push(`To: ${engineer.first_name} ${engineer.last_name}`);
        }
    } else if (state.destinationType === 'adv' && state.selectedWarehouseId) {
        const warehouse = state.destinations.warehouses.find(w => w.id == state.selectedWarehouseId);
        if (warehouse) {
            parts.push(`To: ${warehouse.name}`);
        }
    }
    
    if (state.selectedSiteId) {
        const site = state.sites.find(s => s.site_id == state.selectedSiteId);
        if (site) {
            parts.push(`Site: ${site.site_name}`);
        }
    }
    
    if (state.selectedItems.length > 0) {
        const totalQty = state.selectedItems.reduce((sum, item) => sum + item.quantity, 0);
        parts.push(`${state.selectedItems.length} product(s), ${totalQty} total qty`);
    }
    
    summaryEl.textContent = parts.length > 0 ? parts.join(' • ') : 'Select destination and items to dispatch';
}

function showConfirmModal() {
    const detailsEl = document.getElementById('confirm-details');
    
    // Build destination info
    let destinationHtml = '';
    if (state.destinationType === 'engineer') {
        const engineer = state.destinations.users.find(u => u.id == state.selectedEngineerId);
        destinationHtml = `
            <div class="flex items-center p-3 bg-gray-50 rounded-lg">
                <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center mr-3">
                    <i class="fas fa-user-hard-hat text-blue-500"></i>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Dispatching to Engineer</p>
                    <p class="font-medium text-gray-800">${engineer ? `${engineer.first_name} ${engineer.last_name}` : 'Unknown'}</p>
                </div>
            </div>
        `;
    } else {
        const warehouse = state.destinations.warehouses.find(w => w.id == state.selectedWarehouseId);
        destinationHtml = `
            <div class="flex items-center p-3 bg-gray-50 rounded-lg">
                <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center mr-3">
                    <i class="fas fa-warehouse text-green-500"></i>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Returning to ADV Warehouse</p>
                    <p class="font-medium text-gray-800">${warehouse ? warehouse.name : 'Unknown'}</p>
                </div>
            </div>
        `;
    }
    
    // Build site info if selected
    let siteHtml = '';
    if (state.selectedSiteId) {
        const site = state.sites.find(s => s.site_id == state.selectedSiteId);
        if (site) {
            siteHtml = `
                <div class="flex items-center p-3 bg-purple-50 rounded-lg mt-3">
                    <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center mr-3">
                        <i class="fas fa-map-marker-alt text-purple-500"></i>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Associated Site</p>
                        <p class="font-medium text-gray-800">${site.site_name}${site.lho ? ` (${site.lho})` : ''}</p>
                    </div>
                </div>
            `;
        }
    }
    
    // Build items list
    let itemsHtml = '<div class="border rounded-lg divide-y">';
    state.selectedItems.forEach(item => {
        itemsHtml += `
            <div class="flex items-center justify-between p-3">
                <span class="text-gray-700">${item.product_name}</span>
                <span class="font-medium text-gray-800">${item.quantity} qty</span>
            </div>
        `;
    });
    itemsHtml += '</div>';
    
    detailsEl.innerHTML = destinationHtml + siteHtml + '<div class="mt-3"><p class="text-sm font-medium text-gray-700 mb-2">Items:</p>' + itemsHtml + '</div>';
    
    document.getElementById('confirm-modal').classList.remove('hidden');
}

function closeConfirmModal() {
    document.getElementById('confirm-modal').classList.add('hidden');
}

async function submitDispatch() {
    const btn = document.getElementById('confirm-submit-btn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Processing...';
    
    try {
        // Build dispatch data
        const dispatchData = {
            notes: document.getElementById('dispatch-notes').value || null,
            site_id: state.selectedSiteId || null
        };
        
        if (state.destinationType === 'engineer') {
            dispatchData.to_user_id = state.selectedEngineerId;
        } else {
            dispatchData.to_warehouse_id = state.selectedWarehouseId;
        }
        
        // Build items array
        const items = state.selectedItems.map(item => ({
            product_id: item.product_id,
            quantity: item.quantity
        }));
        
        const response = await fetch('../../api/inventory/dispatch/create.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({
                sender_type: 'company',
                sender_id: state.companyId,
                ...dispatchData,
                items: items
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            closeConfirmModal();
            showSuccessModal(result.data.dispatch?.dispatch_number || 'N/A', result.message);
        } else {
            showToast(result.message || 'Failed to create dispatch', 'error');
        }
    } catch (error) {
        console.error('Error creating dispatch:', error);
        showToast('Failed to create dispatch', 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-check mr-2"></i>Confirm & Dispatch';
    }
}

function showSuccessModal(dispatchNumber, message) {
    document.getElementById('success-dispatch-number').textContent = dispatchNumber;
    document.getElementById('success-message').textContent = message || 'Your dispatch has been created successfully.';
    document.getElementById('success-modal').classList.remove('hidden');
}

function closeSuccessModal() {
    document.getElementById('success-modal').classList.add('hidden');
}

function resetForm() {
    state.destinationType = null;
    state.selectedEngineerId = null;
    state.selectedWarehouseId = null;
    state.selectedSiteId = null;
    state.selectedItems = [];
    
    document.querySelectorAll('.destination-option').forEach(opt => {
        opt.classList.remove('selected');
    });
    document.getElementById('engineer-selection').classList.add('hidden');
    document.getElementById('warehouse-selection').classList.add('hidden');
    document.getElementById('engineer-select').value = '';
    document.getElementById('warehouse-select').value = '';
    document.getElementById('site-select').value = '';
    document.getElementById('dispatch-notes').value = '';
    document.getElementById('selected-items-container').classList.add('hidden');
    document.getElementById('validation-messages').classList.add('hidden');
    
    renderInventoryList();
    updateSubmitButton();
    updateSummary();
}

function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    const bgColor = type === 'success' ? 'bg-green-500' : type === 'error' ? 'bg-red-500' : 'bg-blue-500';
    
    toast.className = `fixed bottom-4 right-4 ${bgColor} text-white px-6 py-3 rounded-lg shadow-lg z-50 transition-opacity duration-300`;
    toast.innerHTML = `
        <div class="flex items-center">
            <i class="fas ${type === 'success' ? 'fa-check-circle' : type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle'} mr-2"></i>
            <span>${message}</span>
        </div>
    `;
    
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.style.opacity = '0';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../../views/layouts/main.php';
?>
