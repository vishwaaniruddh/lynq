<?php
/**
 * Installation View Page
 * 
 * Display all installation data in read-only mode
 * Display images as 300x300 thumbnails in grid layout
 * Include lightbox modal for full-size image viewing
 * 
 * Requirements: 15.1, 15.2, 15.3, 15.4, 15.5
 */

require_once __DIR__ . '/../config/autoload.php';

$sessionService = new SessionService();
if (!$sessionService->isLoggedIn()) {
    header('Location: ../views/auth/login.php');
    exit;
}

$currentUser = $sessionService->getCurrentUser();
$baseUrl = '..';
$pageTitle = 'Installation Details';
$currentPage = 'installation';
$isLoggedIn = true;

// Get installation ID from query string
$installationId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$installationId) {
    $_SESSION['flash_error'] = 'Installation ID is required.';
    header('Location: index.php');
    exit;
}

$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '../dashboard.php'],
    ['label' => 'Installation', 'url' => 'index.php'],
    ['label' => 'View']
];

ob_start();
?>

<div id="installation-view-container">
    <!-- Loading State -->
    <div id="loading-state" class="bg-white rounded-xl shadow-sm p-8 text-center">
        <i class="fas fa-spinner fa-spin text-3xl text-primary mb-4"></i>
        <p class="text-gray-500">Loading installation data...</p>
    </div>
    
    <!-- View Content (hidden until loaded) -->
    <div id="view-content" class="hidden space-y-6">
        <!-- Header -->
        <div class="bg-white rounded-xl shadow-sm p-6">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div>
                    <h3 class="text-lg font-semibold text-gray-800">Installation Details</h3>
                    <p class="text-sm text-gray-500">View installation information and uploaded images</p>
                </div>
                <div class="flex items-center gap-3">
                    <span id="status-badge" class="px-3 py-1 rounded-full text-sm"></span>
                    <a href="index.php" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">
                        <i class="fas fa-arrow-left mr-2"></i>Back to List
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Site Information Section -->
        <div class="bg-white rounded-xl shadow-sm">
            <div class="p-4 border-b bg-gray-50 rounded-t-xl">
                <h4 class="font-semibold text-gray-800"><i class="fas fa-map-marker-alt mr-2 text-primary"></i>Site Information</h4>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-500 mb-1">ATM ID</label>
                        <p id="view-atm_id" class="text-gray-800">-</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-500 mb-1">LHO</label>
                        <p id="view-lho" class="text-gray-800">-</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-500 mb-1">City</label>
                        <p id="view-city" class="text-gray-800">-</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-500 mb-1">State</label>
                        <p id="view-state" class="text-gray-800">-</p>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-500 mb-1">Address</label>
                        <p id="view-address" class="text-gray-800">-</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Vendor/Engineer Information -->
        <div class="bg-white rounded-xl shadow-sm">
            <div class="p-4 border-b bg-gray-50 rounded-t-xl">
                <h4 class="font-semibold text-gray-800"><i class="fas fa-user-tie mr-2 text-primary"></i>Vendor / Engineer Information</h4>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-500 mb-1">Vendor Name</label>
                        <p id="view-vendor_name" class="text-gray-800">-</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-500 mb-1">Engineer Name</label>
                        <p id="view-engineer_name" class="text-gray-800">-</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-500 mb-1">Engineer Number</label>
                        <p id="view-engineer_number" class="text-gray-800">-</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Router Section -->
        <div class="bg-white rounded-xl shadow-sm">
            <div class="p-4 border-b bg-gray-50 rounded-t-xl">
                <h4 class="font-semibold text-gray-800"><i class="fas fa-wifi mr-2 text-primary"></i>Router Section</h4>
            </div>
            <div class="p-6 space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-500 mb-1">Router Serial</label>
                        <p id="view-router_serial" class="text-gray-800">-</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-500 mb-1">Router Make</label>
                        <p id="view-router_make" class="text-gray-800">-</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-500 mb-1">Router Model</label>
                        <p id="view-router_model" class="text-gray-800">-</p>
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-500 mb-1">Router Fixed</label>
                        <p id="view-router_fixed" class="text-gray-800">-</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-500 mb-1">Router Status</label>
                        <p id="view-router_status" class="text-gray-800">-</p>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-500 mb-1">Router Fixed Remarks</label>
                    <p id="view-router_fixed_remarks" class="text-gray-800">-</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-500 mb-2">Router Fixed Photos</label>
                    <div id="view-router_fixed_snaps" class="grid grid-cols-2 md:grid-cols-4 gap-4"></div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-500 mb-1">Router Status Remarks</label>
                    <p id="view-router_status_remarks" class="text-gray-800">-</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-500 mb-2">Router Status Photos</label>
                    <div id="view-router_status_snaps" class="grid grid-cols-2 md:grid-cols-4 gap-4"></div>
                </div>
            </div>
        </div>
        
        <!-- Adaptor Section -->
        <div class="bg-white rounded-xl shadow-sm">
            <div class="p-4 border-b bg-gray-50 rounded-t-xl">
                <h4 class="font-semibold text-gray-800"><i class="fas fa-plug mr-2 text-primary"></i>Adaptor Section</h4>
            </div>
            <div class="p-6 space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-500 mb-1">Adaptor Installed</label>
                        <p id="view-adaptor_installed" class="text-gray-800">-</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-500 mb-1">Adaptor Status</label>
                        <p id="view-adaptor_status" class="text-gray-800">-</p>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-500 mb-2">Adaptor Photos</label>
                    <div id="view-adaptor_snaps" class="grid grid-cols-2 md:grid-cols-4 gap-4"></div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-500 mb-1">Adaptor Status Remarks</label>
                    <p id="view-adaptor_status_remarks" class="text-gray-800">-</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-500 mb-2">Adaptor Status Photos</label>
                    <div id="view-adaptor_status_snaps" class="grid grid-cols-2 md:grid-cols-4 gap-4"></div>
                </div>
            </div>
        </div>
        
        <!-- LAN Cable Section -->
        <div class="bg-white rounded-xl shadow-sm">
            <div class="p-4 border-b bg-gray-50 rounded-t-xl">
                <h4 class="font-semibold text-gray-800"><i class="fas fa-ethernet mr-2 text-primary"></i>LAN Cable Section</h4>
            </div>
            <div class="p-6 space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-500 mb-1">LAN Cable Installed</label>
                        <p id="view-lan_cable_installed" class="text-gray-800">-</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-500 mb-1">LAN Cable Status</label>
                        <p id="view-lan_cable_status" class="text-gray-800">-</p>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-500 mb-1">LAN Cable Install Remarks</label>
                    <p id="view-lan_cable_install_remark" class="text-gray-800">-</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-500 mb-2">LAN Cable Install Photo</label>
                    <div id="view-lan_cable_install_snap" class="grid grid-cols-2 md:grid-cols-4 gap-4"></div>
                </div>
                <div id="view-lan_cable_not_working_container" class="hidden">
                    <label class="block text-sm font-medium text-gray-500 mb-1">Not Working Reasons</label>
                    <p id="view-lan_cable_status_not_working_reasons" class="text-gray-800">-</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-500 mb-1">LAN Cable Status Remarks</label>
                    <p id="view-lan_cable_status_remark" class="text-gray-800">-</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-500 mb-2">LAN Cable Status Photo</label>
                    <div id="view-lan_cable_status_snap" class="grid grid-cols-2 md:grid-cols-4 gap-4"></div>
                </div>
            </div>
        </div>
        
        <!-- Antenna Section -->
        <div class="bg-white rounded-xl shadow-sm">
            <div class="p-4 border-b bg-gray-50 rounded-t-xl">
                <h4 class="font-semibold text-gray-800"><i class="fas fa-broadcast-tower mr-2 text-primary"></i>Antenna Section</h4>
            </div>
            <div class="p-6 space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-500 mb-1">Antenna Installed</label>
                        <p id="view-antenna_installed" class="text-gray-800">-</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-500 mb-1">Antenna Status</label>
                        <p id="view-antenna_status" class="text-gray-800">-</p>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-500 mb-1">Antenna Remarks</label>
                    <p id="view-antenna_remarks" class="text-gray-800">-</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-500 mb-2">Antenna Photos</label>
                    <div id="view-antenna_snaps" class="grid grid-cols-2 md:grid-cols-4 gap-4"></div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-500 mb-1">Antenna Status Remarks</label>
                    <p id="view-antenna_status_remarks" class="text-gray-800">-</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-500 mb-2">Antenna Status Photos</label>
                    <div id="view-antenna_status_snaps" class="grid grid-cols-2 md:grid-cols-4 gap-4"></div>
                </div>
            </div>
        </div>
        
        <!-- GPS Section -->
        <div class="bg-white rounded-xl shadow-sm">
            <div class="p-4 border-b bg-gray-50 rounded-t-xl">
                <h4 class="font-semibold text-gray-800"><i class="fas fa-satellite mr-2 text-primary"></i>GPS Section</h4>
            </div>
            <div class="p-6 space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-500 mb-1">GPS Installed</label>
                        <p id="view-gps_installed" class="text-gray-800">-</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-500 mb-1">GPS Status</label>
                        <p id="view-gps_status" class="text-gray-800">-</p>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-500 mb-1">GPS Remarks</label>
                    <p id="view-gps_remarks" class="text-gray-800">-</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-500 mb-2">GPS Photos</label>
                    <div id="view-gps_snaps" class="grid grid-cols-2 md:grid-cols-4 gap-4"></div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-500 mb-1">GPS Status Remarks</label>
                    <p id="view-gps_status_remarks" class="text-gray-800">-</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-500 mb-2">GPS Status Photos</label>
                    <div id="view-gps_status_snaps" class="grid grid-cols-2 md:grid-cols-4 gap-4"></div>
                </div>
            </div>
        </div>
        
        <!-- WiFi Section -->
        <div class="bg-white rounded-xl shadow-sm">
            <div class="p-4 border-b bg-gray-50 rounded-t-xl">
                <h4 class="font-semibold text-gray-800"><i class="fas fa-wifi mr-2 text-primary"></i>WiFi Section</h4>
            </div>
            <div class="p-6 space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-500 mb-1">WiFi Installed</label>
                        <p id="view-wifi_installed" class="text-gray-800">-</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-500 mb-1">WiFi Status</label>
                        <p id="view-wifi_status" class="text-gray-800">-</p>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-500 mb-1">WiFi Remarks</label>
                    <p id="view-wifi_remarks" class="text-gray-800">-</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-500 mb-2">WiFi Photos</label>
                    <div id="view-wifi_snaps" class="grid grid-cols-2 md:grid-cols-4 gap-4"></div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-500 mb-1">WiFi Status Remarks</label>
                    <p id="view-wifi_status_remarks" class="text-gray-800">-</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-500 mb-2">WiFi Status Photos</label>
                    <div id="view-wifi_status_snaps" class="grid grid-cols-2 md:grid-cols-4 gap-4"></div>
                </div>
            </div>
        </div>

        <!-- Airtel SIM Section -->
        <div class="bg-white rounded-xl shadow-sm">
            <div class="p-4 border-b bg-gray-50 rounded-t-xl">
                <h4 class="font-semibold text-gray-800"><i class="fas fa-sim-card mr-2 text-red-500"></i>Airtel SIM Section</h4>
            </div>
            <div class="p-6 space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-500 mb-1">Airtel SIM Installed</label>
                        <p id="view-airtel_sim_installed" class="text-gray-800">-</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-500 mb-1">Airtel SIM Status</label>
                        <p id="view-airtel_sim_status" class="text-gray-800">-</p>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-500 mb-1">Airtel SIM Remarks</label>
                    <p id="view-airtel_sim_remarks" class="text-gray-800">-</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-500 mb-2">Airtel SIM Photos</label>
                    <div id="view-airtel_sim_snaps" class="grid grid-cols-2 md:grid-cols-4 gap-4"></div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-500 mb-1">Airtel SIM Status Remarks</label>
                    <p id="view-airtel_sim_status_remarks" class="text-gray-800">-</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-500 mb-2">Airtel SIM Status Photos</label>
                    <div id="view-airtel_sim_status_snaps" class="grid grid-cols-2 md:grid-cols-4 gap-4"></div>
                </div>
            </div>
        </div>
        
        <!-- Vodafone SIM Section -->
        <div class="bg-white rounded-xl shadow-sm">
            <div class="p-4 border-b bg-gray-50 rounded-t-xl">
                <h4 class="font-semibold text-gray-800"><i class="fas fa-sim-card mr-2 text-red-600"></i>Vodafone SIM Section</h4>
            </div>
            <div class="p-6 space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-500 mb-1">Vodafone SIM Installed</label>
                        <p id="view-vodafone_sim_installed" class="text-gray-800">-</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-500 mb-1">Vodafone SIM Status</label>
                        <p id="view-vodafone_sim_status" class="text-gray-800">-</p>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-500 mb-1">Vodafone SIM Remarks</label>
                    <p id="view-vodafone_sim_remarks" class="text-gray-800">-</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-500 mb-2">Vodafone SIM Photos</label>
                    <div id="view-vodafone_sim_snaps" class="grid grid-cols-2 md:grid-cols-4 gap-4"></div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-500 mb-1">Vodafone SIM Status Remarks</label>
                    <p id="view-vodafone_sim_status_remarks" class="text-gray-800">-</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-500 mb-2">Vodafone SIM Status Photos</label>
                    <div id="view-vodafone_sim_status_snaps" class="grid grid-cols-2 md:grid-cols-4 gap-4"></div>
                </div>
            </div>
        </div>
        
        <!-- JIO SIM Section -->
        <div class="bg-white rounded-xl shadow-sm">
            <div class="p-4 border-b bg-gray-50 rounded-t-xl">
                <h4 class="font-semibold text-gray-800"><i class="fas fa-sim-card mr-2 text-blue-600"></i>JIO SIM Section</h4>
            </div>
            <div class="p-6 space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-500 mb-1">JIO SIM Installed</label>
                        <p id="view-jio_sim_installed" class="text-gray-800">-</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-500 mb-1">JIO SIM Status</label>
                        <p id="view-jio_sim_status" class="text-gray-800">-</p>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-500 mb-1">JIO SIM Remarks</label>
                    <p id="view-jio_sim_remarks" class="text-gray-800">-</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-500 mb-2">JIO SIM Photos</label>
                    <div id="view-jio_sim_snaps" class="grid grid-cols-2 md:grid-cols-4 gap-4"></div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-500 mb-1">JIO SIM Status Remarks</label>
                    <p id="view-jio_sim_status_remarks" class="text-gray-800">-</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-500 mb-2">JIO SIM Status Photos</label>
                    <div id="view-jio_sim_status_snaps" class="grid grid-cols-2 md:grid-cols-4 gap-4"></div>
                </div>
            </div>
        </div>
        
        <!-- Verification Section -->
        <div class="bg-white rounded-xl shadow-sm">
            <div class="p-4 border-b bg-gray-50 rounded-t-xl">
                <h4 class="font-semibold text-gray-800"><i class="fas fa-signature mr-2 text-primary"></i>Verification Section</h4>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-500 mb-2">Digital Signature</label>
                        <div id="view-signature_image" class="border rounded-lg p-2 bg-gray-50"></div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-500 mb-2">Vendor Stamp</label>
                        <div id="view-vendor_stamp" class="grid grid-cols-2 md:grid-cols-4 gap-4"></div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Audit Information -->
        <div class="bg-white rounded-xl shadow-sm">
            <div class="p-4 border-b bg-gray-50 rounded-t-xl">
                <h4 class="font-semibold text-gray-800"><i class="fas fa-history mr-2 text-primary"></i>Audit Information</h4>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-500 mb-1">Created At</label>
                        <p id="view-created_at" class="text-gray-800">-</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-500 mb-1">Updated At</label>
                        <p id="view-updated_at" class="text-gray-800">-</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-500 mb-1">Submitted At</label>
                        <p id="view-submitted_at" class="text-gray-800">-</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-500 mb-1">Initiated At</label>
                        <p id="view-initiated_at" class="text-gray-800">-</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Lightbox Modal -->
<div id="lightbox-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/80" onclick="closeLightbox(event)">
    <button class="absolute top-4 right-4 text-white text-3xl hover:text-gray-300" onclick="closeLightbox(event)">
        <i class="fas fa-times"></i>
    </button>
    <img id="lightbox-image" src="" class="max-w-[90vw] max-h-[90vh] object-contain" onclick="event.stopPropagation()">
</div>

<script>
const installationId = <?php echo $installationId; ?>;
const API_BASE = '../api/installation';

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    loadInstallation();
    
    // Close lightbox on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeLightbox(e);
        }
    });
});

// Load installation data
async function loadInstallation() {
    try {
        const response = await fetch(`${API_BASE}/get.php?id=${installationId}`, {
            credentials: 'include'
        });
        
        const data = await response.json();
        
        if (data.success) {
            populateView(data.data);
            document.getElementById('loading-state').classList.add('hidden');
            document.getElementById('view-content').classList.remove('hidden');
        } else {
            showError(data.message || 'Failed to load installation');
        }
    } catch (error) {
        console.error('Error loading installation:', error);
        showError('Failed to load installation data');
    }
}

// Populate view with installation data
function populateView(data) {
    // Update status badge
    const statusBadge = document.getElementById('status-badge');
    statusBadge.textContent = getStatusLabel(data.status);
    statusBadge.className = `px-3 py-1 rounded-full text-sm ${getStatusClass(data.status)}`;
    
    // Site information
    setText('view-atm_id', data.atm_id);
    setText('view-lho', data.lho);
    setText('view-city', data.city);
    setText('view-state', data.state);
    setText('view-address', data.address);
    
    // Vendor/Engineer information
    setText('view-vendor_name', data.vendor_name);
    setText('view-engineer_name', data.engineer_name);
    setText('view-engineer_number', data.engineer_number);
    
    // Router section
    setText('view-router_serial', data.router_serial);
    setText('view-router_make', data.router_make);
    setText('view-router_model', data.router_model);
    setText('view-router_fixed', formatYesNo(data.router_fixed));
    setText('view-router_status', formatWorkingStatus(data.router_status));
    setText('view-router_fixed_remarks', data.router_fixed_remarks);
    setText('view-router_status_remarks', data.router_status_remarks);
    renderImages('view-router_fixed_snaps', data.router_fixed_snaps);
    renderImages('view-router_status_snaps', data.router_status_snaps);
    
    // Adaptor section
    setText('view-adaptor_installed', formatYesNo(data.adaptor_installed));
    setText('view-adaptor_status', formatWorkingStatus(data.adaptor_status));
    setText('view-adaptor_status_remarks', data.adaptor_status_remarks);
    renderImages('view-adaptor_snaps', data.adaptor_snaps);
    renderImages('view-adaptor_status_snaps', data.adaptor_status_snaps);
    
    // LAN Cable section
    setText('view-lan_cable_installed', formatYesNo(data.lan_cable_installed));
    setText('view-lan_cable_status', formatWorkingStatus(data.lan_cable_status));
    setText('view-lan_cable_install_remark', data.lan_cable_install_remark);
    setText('view-lan_cable_status_remark', data.lan_cable_status_remark);
    renderImages('view-lan_cable_install_snap', data.lan_cable_install_snap);
    renderImages('view-lan_cable_status_snap', data.lan_cable_status_snap);
    if (data.lan_cable_status === 'notWorking' && data.lan_cable_status_not_working_reasons) {
        document.getElementById('view-lan_cable_not_working_container').classList.remove('hidden');
        setText('view-lan_cable_status_not_working_reasons', data.lan_cable_status_not_working_reasons);
    }
    
    // Antenna section
    setText('view-antenna_installed', formatYesNo(data.antenna_installed));
    setText('view-antenna_status', formatWorkingStatus(data.antenna_status));
    setText('view-antenna_remarks', data.antenna_remarks);
    setText('view-antenna_status_remarks', data.antenna_status_remarks);
    renderImages('view-antenna_snaps', data.antenna_snaps);
    renderImages('view-antenna_status_snaps', data.antenna_status_snaps);
    
    // GPS section
    setText('view-gps_installed', formatYesNo(data.gps_installed));
    setText('view-gps_status', formatWorkingStatus(data.gps_status));
    setText('view-gps_remarks', data.gps_remarks);
    setText('view-gps_status_remarks', data.gps_status_remarks);
    renderImages('view-gps_snaps', data.gps_snaps);
    renderImages('view-gps_status_snaps', data.gps_status_snaps);
    
    // WiFi section
    setText('view-wifi_installed', formatYesNo(data.wifi_installed));
    setText('view-wifi_status', formatWorkingStatus(data.wifi_status));
    setText('view-wifi_remarks', data.wifi_remarks);
    setText('view-wifi_status_remarks', data.wifi_status_remarks);
    renderImages('view-wifi_snaps', data.wifi_snaps);
    renderImages('view-wifi_status_snaps', data.wifi_status_snaps);
    
    // Airtel SIM section
    setText('view-airtel_sim_installed', formatYesNo(data.airtel_sim_installed));
    setText('view-airtel_sim_status', formatWorkingStatus(data.airtel_sim_status));
    setText('view-airtel_sim_remarks', data.airtel_sim_remarks);
    setText('view-airtel_sim_status_remarks', data.airtel_sim_status_remarks);
    renderImages('view-airtel_sim_snaps', data.airtel_sim_snaps);
    renderImages('view-airtel_sim_status_snaps', data.airtel_sim_status_snaps);
    
    // Vodafone SIM section
    setText('view-vodafone_sim_installed', formatYesNo(data.vodafone_sim_installed));
    setText('view-vodafone_sim_status', formatWorkingStatus(data.vodafone_sim_status));
    setText('view-vodafone_sim_remarks', data.vodafone_sim_remarks);
    setText('view-vodafone_sim_status_remarks', data.vodafone_sim_status_remarks);
    renderImages('view-vodafone_sim_snaps', data.vodafone_sim_snaps);
    renderImages('view-vodafone_sim_status_snaps', data.vodafone_sim_status_snaps);
    
    // JIO SIM section
    setText('view-jio_sim_installed', formatYesNo(data.jio_sim_installed));
    setText('view-jio_sim_status', formatWorkingStatus(data.jio_sim_status));
    setText('view-jio_sim_remarks', data.jio_sim_remarks);
    setText('view-jio_sim_status_remarks', data.jio_sim_status_remarks);
    renderImages('view-jio_sim_snaps', data.jio_sim_snaps);
    renderImages('view-jio_sim_status_snaps', data.jio_sim_status_snaps);
    
    // Verification section
    renderSignature('view-signature_image', data.signature_image);
    renderImages('view-vendor_stamp', data.vendor_stamp);
    
    // Audit information
    setText('view-created_at', formatDateTime(data.created_at));
    setText('view-updated_at', formatDateTime(data.updated_at));
    setText('view-submitted_at', formatDateTime(data.submitted_at));
    setText('view-initiated_at', formatDateTime(data.initiated_at));
}

// Helper functions
function setText(elementId, value) {
    const element = document.getElementById(elementId);
    if (element) {
        element.textContent = value || '-';
    }
}

function formatYesNo(value) {
    if (!value) return '-';
    return value === 'yes' ? 'Yes' : 'No';
}

function formatWorkingStatus(value) {
    if (!value) return '-';
    return value === 'working' ? 'Working' : 'Not Working';
}

function formatDateTime(value) {
    if (!value) return '-';
    const date = new Date(value);
    return date.toLocaleString('en-IN', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

function getStatusLabel(status) {
    const labels = {
        pending_materials: 'Pending Materials',
        materials_received: 'Materials Received',
        in_progress: 'In Progress',
        submitted: 'Submitted',
        pending_contractor_review: 'Pending Review',
        contractor_approved: 'Contractor Approved',
        contractor_rejected: 'Contractor Rejected',
        adv_approved: 'ADV Approved',
        adv_rejected: 'ADV Rejected'
    };
    return labels[status] || status;
}

function getStatusClass(status) {
    const classes = {
        pending_materials: 'bg-yellow-100 text-yellow-700',
        materials_received: 'bg-blue-100 text-blue-700',
        in_progress: 'bg-indigo-100 text-indigo-700',
        submitted: 'bg-purple-100 text-purple-700',
        pending_contractor_review: 'bg-orange-100 text-orange-700',
        contractor_approved: 'bg-teal-100 text-teal-700',
        contractor_rejected: 'bg-red-100 text-red-700',
        adv_approved: 'bg-green-100 text-green-700',
        adv_rejected: 'bg-red-100 text-red-700'
    };
    return classes[status] || 'bg-gray-100 text-gray-700';
}

// Render images as 300x300 thumbnails in grid layout (Requirements 15.1, 15.4)
function renderImages(containerId, pathsStr) {
    const container = document.getElementById(containerId);
    if (!container) return;
    
    if (!pathsStr) {
        container.innerHTML = '<p class="text-gray-400 text-sm">No images uploaded</p>';
        return;
    }
    
    const paths = pathsStr.split(',').filter(p => p.trim());
    
    if (paths.length === 0) {
        container.innerHTML = '<p class="text-gray-400 text-sm">No images uploaded</p>';
        return;
    }
    
    container.innerHTML = paths.map(path => {
        const fullPath = path.startsWith('data:') ? path : '../' + path;
        return `
            <div class="relative group cursor-pointer" onclick="openLightbox('${escapeHtml(fullPath)}')">
                <img src="${escapeHtml(fullPath)}" 
                     alt="Installation photo" 
                     class="w-full h-[300px] object-cover rounded-lg border shadow-sm hover:shadow-md transition"
                     onerror="this.parentElement.innerHTML='<div class=\\'w-full h-[300px] bg-gray-100 rounded-lg border flex items-center justify-center\\'><div class=\\'text-center text-gray-400\\'><i class=\\'fas fa-image text-3xl mb-2\\'></i><p class=\\'text-sm\\'>Image not available</p></div></div>'">
                <div class="absolute inset-0 bg-black/0 group-hover:bg-black/20 rounded-lg transition flex items-center justify-center">
                    <i class="fas fa-search-plus text-white text-2xl opacity-0 group-hover:opacity-100 transition"></i>
                </div>
            </div>
        `;
    }).join('');
}

// Render signature image
function renderSignature(containerId, path) {
    const container = document.getElementById(containerId);
    if (!container) return;
    
    if (!path) {
        container.innerHTML = '<p class="text-gray-400 text-sm text-center py-8">No signature provided</p>';
        return;
    }
    
    const fullPath = path.startsWith('data:') ? path : '../' + path;
    container.innerHTML = `
        <img src="${escapeHtml(fullPath)}" 
             alt="Digital Signature" 
             class="max-w-full h-auto max-h-40 mx-auto cursor-pointer hover:opacity-80 transition"
             onclick="openLightbox('${escapeHtml(fullPath)}')"
             onerror="this.parentElement.innerHTML='<p class=\\'text-gray-400 text-sm text-center py-8\\'>Signature not available</p>'">
    `;
}

// Lightbox functions (Requirements 15.2, 15.3)
function openLightbox(src) {
    const modal = document.getElementById('lightbox-modal');
    const image = document.getElementById('lightbox-image');
    image.src = src;
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeLightbox(event) {
    if (event) event.stopPropagation();
    const modal = document.getElementById('lightbox-modal');
    modal.classList.add('hidden');
    document.body.style.overflow = '';
}

// Show error
function showError(message) {
    document.getElementById('loading-state').innerHTML = `
        <div class="text-red-500">
            <i class="fas fa-exclamation-circle text-3xl mb-4"></i>
            <p>${escapeHtml(message)}</p>
            <a href="index.php" class="mt-4 inline-block px-4 py-2 bg-gray-100 rounded-lg hover:bg-gray-200">Back to List</a>
        </div>
    `;
}

// Escape HTML
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../views/layouts/base.php';
?>
