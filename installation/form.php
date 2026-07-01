<?php
/**
 * Installation Form Page
 * 
 * Display pre-populated site information (read-only)
 * Include all sections: Router, Adaptor, LAN Cable, Antenna, GPS, WiFi, SIMs, Verification
 * Include image upload fields with preview
 * Include digital signature capture
 * 
 * Requirements: 4.1, 4.4, 4.5, 5.1-5.3, 6.1-6.5, 7.1-7.3, 8.1-8.3, 9.1-9.3, 10.1-10.3, 11.1-11.3, 12.1-12.4, 13.1-13.5
 * - 4.1: Display "Confirm Materials Received" button for pending_materials status
 * - 4.4: Prevent form access when status is "pending_materials" or earlier
 * - 4.5: Enable form access when status is "materials_received" or later
 */

require_once __DIR__ . '/../config/autoload.php';
require_once __DIR__ . '/../services/InstallationService.php';

$sessionService = new SessionService();
if (!$sessionService->isLoggedIn()) {
    header('Location: ../views/auth/login.php');
    exit;
}

$currentUser = $sessionService->getCurrentUser();
$baseUrl = '..';
$pageTitle = 'Installation Form';
$currentPage = 'installation';
$isLoggedIn = true;

// Get installation ID from query string
$installationId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$installationId) {
    $_SESSION['flash_error'] = 'Installation ID is required.';
    header('Location: index.php');
    exit;
}

// Server-side check for installation existence and form access
// Requirements: 4.4, 4.5
$installationService = new InstallationService();
$installation = $installationService->getInstallation($installationId);

if (!$installation) {
    $_SESSION['flash_error'] = 'Installation not found.';
    header('Location: index.php');
    exit;
}

// Check if form access is allowed based on material receipt status
// Requirement 4.4: Prevent form access when status is "pending_materials" or earlier
// Requirement 4.5: Enable form access when status is "materials_received" or later
$canAccessForm = $installationService->canAccessForm($installationId);
$isPendingMaterials = $installation['status'] === 'pending_materials';

// Pass status info to JavaScript for UI handling
$installationStatus = $installation['status'];

$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '../dashboard.php'],
    ['label' => 'Installation', 'url' => 'index.php'],
    ['label' => 'Form']
];

ob_start();
?>

<!-- Pass server-side status info to JavaScript -->
<script>
    const serverInstallationStatus = '<?php echo htmlspecialchars($installationStatus); ?>';
    const serverCanAccessForm = <?php echo $canAccessForm ? 'true' : 'false'; ?>;
    const serverIsPendingMaterials = <?php echo $isPendingMaterials ? 'true' : 'false'; ?>;
</script>

<div id="installation-form-container">
    <!-- Loading State -->
    <div id="loading-state" class="bg-white rounded-xl shadow-sm p-8 text-center">
        <i class="fas fa-spinner fa-spin text-3xl text-primary mb-4"></i>
        <p class="text-gray-500">Loading installation data...</p>
    </div>
    
    <!-- Material Receipt Modal -->
    <div id="material-receipt-modal" class="hidden fixed inset-0 z-50 overflow-y-auto">
        <div class="fixed inset-0 bg-black/60 backdrop-blur-sm" onclick="closeMaterialReceiptModal()"></div>
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full relative z-10">
                <div class="p-6 border-b">
                    <h3 class="text-lg font-semibold text-gray-800">Confirm Material Receipt</h3>
                    <p class="text-sm text-gray-500 mt-1">Please confirm that you have received all required materials for this installation.</p>
                </div>
                <div class="p-6">
                    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-4">
                        <div class="flex items-start">
                            <i class="fas fa-exclamation-triangle text-yellow-500 mt-0.5 mr-3"></i>
                            <div>
                                <p class="text-sm text-yellow-800 font-medium">Important</p>
                                <p class="text-sm text-yellow-700 mt-1">Once confirmed, you will be able to access and fill out the installation form.</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="flex justify-end space-x-3 p-6 border-t bg-gray-50 rounded-b-2xl">
                    <button onclick="closeMaterialReceiptModal()" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">
                        Cancel
                    </button>
                    <button onclick="confirmMaterialReceipt()" id="confirm-materials-btn" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition">
                        <i class="fas fa-check mr-2"></i>Confirm Receipt
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Form Content (hidden until loaded) -->
    <div id="form-content" class="hidden space-y-6">
        <!-- Header -->
        <div class="bg-white rounded-xl shadow-sm p-6">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div>
                    <h3 class="text-lg font-semibold text-gray-800">Installation Form</h3>
                    <p class="text-sm text-gray-500">Complete all sections to submit the installation</p>
                </div>
                <div class="flex items-center gap-3">
                    <span id="status-badge" class="px-3 py-1 rounded-full text-sm"></span>
                    <button onclick="saveForm()" id="save-btn" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                        <i class="fas fa-save mr-2"></i>Save Draft
                    </button>
                    <button onclick="submitForm()" id="submit-btn" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition">
                        <i class="fas fa-paper-plane mr-2"></i>Submit
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Pending Materials Notice -->
        <div id="pending-materials-notice" class="hidden bg-yellow-50 border border-yellow-200 rounded-xl p-6">
            <div class="flex items-start">
                <div class="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center mr-4">
                    <i class="fas fa-box text-yellow-600 text-xl"></i>
                </div>
                <div class="flex-1">
                    <h4 class="text-lg font-semibold text-yellow-800">Materials Pending</h4>
                    <p class="text-yellow-700 mt-1">You need to confirm material receipt before you can fill out the installation form.</p>
                    <button onclick="openMaterialReceiptModal()" class="mt-4 px-4 py-2 bg-yellow-600 text-white rounded-lg hover:bg-yellow-700 transition">
                        <i class="fas fa-check-circle mr-2"></i>Confirm Materials Received
                    </button>
                </div>
            </div>
        </div>
        
        <form id="installation-form" class="space-y-6">
            <input type="hidden" id="installation-id" value="<?php echo $installationId; ?>">
            
            <!-- Site Information Section (Read-only) -->
            <div class="bg-white rounded-xl shadow-sm">
                <div class="p-4 border-b bg-gray-50 rounded-t-xl">
                    <h4 class="font-semibold text-gray-800"><i class="fas fa-map-marker-alt mr-2 text-primary"></i>Site Information</h4>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">ATM ID</label>
                            <input type="text" id="atm_id" readonly class="w-full px-4 py-2 border rounded-lg bg-gray-100 text-gray-600">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">LHO</label>
                            <input type="text" id="lho" readonly class="w-full px-4 py-2 border rounded-lg bg-gray-100 text-gray-600">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">City</label>
                            <input type="text" id="city" readonly class="w-full px-4 py-2 border rounded-lg bg-gray-100 text-gray-600">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">State</label>
                            <input type="text" id="state" readonly class="w-full px-4 py-2 border rounded-lg bg-gray-100 text-gray-600">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Address</label>
                            <input type="text" id="address" readonly class="w-full px-4 py-2 border rounded-lg bg-gray-100 text-gray-600">
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
                            <label class="block text-sm font-medium text-gray-700 mb-1">Vendor Name <span class="text-red-500">*</span></label>
                            <input type="text" name="vendor_name" id="vendor_name" required class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Engineer Name <span class="text-red-500">*</span></label>
                            <input type="text" name="engineer_name" id="engineer_name" required class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Engineer Number <span class="text-red-500">*</span></label>
                            <input type="text" name="engineer_number" id="engineer_number" required class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
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
                            <label class="block text-sm font-medium text-gray-700 mb-1">Router Serial <span class="text-red-500">*</span></label>
                            <input type="text" name="router_serial" id="router_serial" required class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Router Make <span class="text-red-500">*</span></label>
                            <input type="text" name="router_make" id="router_make" required class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Router Model <span class="text-red-500">*</span></label>
                            <input type="text" name="router_model" id="router_model" required class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Router Fixed <span class="text-red-500">*</span></label>
                            <select name="router_fixed" id="router_fixed" required class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                                <option value="">Select</option>
                                <option value="yes">Yes</option>
                                <option value="no">No</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Router Status <span class="text-red-500">*</span></label>
                            <select name="router_status" id="router_status" required class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                                <option value="">Select</option>
                                <option value="working">Working</option>
                                <option value="notWorking">Not Working</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Router Fixed Remarks</label>
                        <textarea name="router_fixed_remarks" id="router_fixed_remarks" rows="2" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Router Fixed Photos</label>
                        <div class="flex items-center gap-4">
                            <input type="file" id="router_fixed_snaps_input" accept="image/jpeg,image/png" multiple class="hidden" onchange="handleImageUpload(this, 'router_fixed_snaps')">
                            <button type="button" onclick="document.getElementById('router_fixed_snaps_input').click()" class="px-4 py-2 border border-dashed border-gray-300 rounded-lg hover:bg-gray-50 transition">
                                <i class="fas fa-camera mr-2"></i>Upload Photos
                            </button>
                            <div id="router_fixed_snaps_preview" class="flex gap-2 flex-wrap"></div>
                        </div>
                        <input type="hidden" name="router_fixed_snaps" id="router_fixed_snaps">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Router Status Remarks</label>
                        <textarea name="router_status_remarks" id="router_status_remarks" rows="2" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Router Status Photos</label>
                        <div class="flex items-center gap-4">
                            <input type="file" id="router_status_snaps_input" accept="image/jpeg,image/png" multiple class="hidden" onchange="handleImageUpload(this, 'router_status_snaps')">
                            <button type="button" onclick="document.getElementById('router_status_snaps_input').click()" class="px-4 py-2 border border-dashed border-gray-300 rounded-lg hover:bg-gray-50 transition">
                                <i class="fas fa-camera mr-2"></i>Upload Photos
                            </button>
                            <div id="router_status_snaps_preview" class="flex gap-2 flex-wrap"></div>
                        </div>
                        <input type="hidden" name="router_status_snaps" id="router_status_snaps">
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
                            <label class="block text-sm font-medium text-gray-700 mb-1">Adaptor Installed <span class="text-red-500">*</span></label>
                            <select name="adaptor_installed" id="adaptor_installed" required class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                                <option value="">Select</option>
                                <option value="yes">Yes</option>
                                <option value="no">No</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Adaptor Status <span class="text-red-500">*</span></label>
                            <select name="adaptor_status" id="adaptor_status" required class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                                <option value="">Select</option>
                                <option value="working">Working</option>
                                <option value="notWorking">Not Working</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Adaptor Photos</label>
                        <div class="flex items-center gap-4">
                            <input type="file" id="adaptor_snaps_input" accept="image/jpeg,image/png" multiple class="hidden" onchange="handleImageUpload(this, 'adaptor_snaps')">
                            <button type="button" onclick="document.getElementById('adaptor_snaps_input').click()" class="px-4 py-2 border border-dashed border-gray-300 rounded-lg hover:bg-gray-50 transition">
                                <i class="fas fa-camera mr-2"></i>Upload Photos
                            </button>
                            <div id="adaptor_snaps_preview" class="flex gap-2 flex-wrap"></div>
                        </div>
                        <input type="hidden" name="adaptor_snaps" id="adaptor_snaps">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Adaptor Status Remarks</label>
                        <textarea name="adaptor_status_remarks" id="adaptor_status_remarks" rows="2" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Adaptor Status Photos</label>
                        <div class="flex items-center gap-4">
                            <input type="file" id="adaptor_status_snaps_input" accept="image/jpeg,image/png" multiple class="hidden" onchange="handleImageUpload(this, 'adaptor_status_snaps')">
                            <button type="button" onclick="document.getElementById('adaptor_status_snaps_input').click()" class="px-4 py-2 border border-dashed border-gray-300 rounded-lg hover:bg-gray-50 transition">
                                <i class="fas fa-camera mr-2"></i>Upload Photos
                            </button>
                            <div id="adaptor_status_snaps_preview" class="flex gap-2 flex-wrap"></div>
                        </div>
                        <input type="hidden" name="adaptor_status_snaps" id="adaptor_status_snaps">
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
                            <label class="block text-sm font-medium text-gray-700 mb-1">LAN Cable Installed <span class="text-red-500">*</span></label>
                            <select name="lan_cable_installed" id="lan_cable_installed" required class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                                <option value="">Select</option>
                                <option value="yes">Yes</option>
                                <option value="no">No</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">LAN Cable Status <span class="text-red-500">*</span></label>
                            <select name="lan_cable_status" id="lan_cable_status" required class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                                <option value="">Select</option>
                                <option value="working">Working</option>
                                <option value="notWorking">Not Working</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">LAN Cable Install Remarks</label>
                        <textarea name="lan_cable_install_remark" id="lan_cable_install_remark" rows="2" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">LAN Cable Install Photo</label>
                        <div class="flex items-center gap-4">
                            <input type="file" id="lan_cable_install_snap_input" accept="image/jpeg,image/png" class="hidden" onchange="handleImageUpload(this, 'lan_cable_install_snap')">
                            <button type="button" onclick="document.getElementById('lan_cable_install_snap_input').click()" class="px-4 py-2 border border-dashed border-gray-300 rounded-lg hover:bg-gray-50 transition">
                                <i class="fas fa-camera mr-2"></i>Upload Photo
                            </button>
                            <div id="lan_cable_install_snap_preview" class="flex gap-2 flex-wrap"></div>
                        </div>
                        <input type="hidden" name="lan_cable_install_snap" id="lan_cable_install_snap">
                    </div>
                    <div id="lan_cable_not_working_reasons_container" class="hidden">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Not Working Reasons</label>
                        <textarea name="lan_cable_status_not_working_reasons" id="lan_cable_status_not_working_reasons" rows="2" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">LAN Cable Status Remarks</label>
                        <textarea name="lan_cable_status_remark" id="lan_cable_status_remark" rows="2" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">LAN Cable Status Photo</label>
                        <div class="flex items-center gap-4">
                            <input type="file" id="lan_cable_status_snap_input" accept="image/jpeg,image/png" class="hidden" onchange="handleImageUpload(this, 'lan_cable_status_snap')">
                            <button type="button" onclick="document.getElementById('lan_cable_status_snap_input').click()" class="px-4 py-2 border border-dashed border-gray-300 rounded-lg hover:bg-gray-50 transition">
                                <i class="fas fa-camera mr-2"></i>Upload Photo
                            </button>
                            <div id="lan_cable_status_snap_preview" class="flex gap-2 flex-wrap"></div>
                        </div>
                        <input type="hidden" name="lan_cable_status_snap" id="lan_cable_status_snap">
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
                            <label class="block text-sm font-medium text-gray-700 mb-1">Antenna Installed <span class="text-red-500">*</span></label>
                            <select name="antenna_installed" id="antenna_installed" required class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                                <option value="">Select</option>
                                <option value="yes">Yes</option>
                                <option value="no">No</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Antenna Status <span class="text-red-500">*</span></label>
                            <select name="antenna_status" id="antenna_status" required class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                                <option value="">Select</option>
                                <option value="working">Working</option>
                                <option value="notWorking">Not Working</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Antenna Remarks</label>
                        <textarea name="antenna_remarks" id="antenna_remarks" rows="2" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Antenna Photos</label>
                        <div class="flex items-center gap-4">
                            <input type="file" id="antenna_snaps_input" accept="image/jpeg,image/png" multiple class="hidden" onchange="handleImageUpload(this, 'antenna_snaps')">
                            <button type="button" onclick="document.getElementById('antenna_snaps_input').click()" class="px-4 py-2 border border-dashed border-gray-300 rounded-lg hover:bg-gray-50 transition">
                                <i class="fas fa-camera mr-2"></i>Upload Photos
                            </button>
                            <div id="antenna_snaps_preview" class="flex gap-2 flex-wrap"></div>
                        </div>
                        <input type="hidden" name="antenna_snaps" id="antenna_snaps">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Antenna Status Remarks</label>
                        <textarea name="antenna_status_remarks" id="antenna_status_remarks" rows="2" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Antenna Status Photos</label>
                        <div class="flex items-center gap-4">
                            <input type="file" id="antenna_status_snaps_input" accept="image/jpeg,image/png" multiple class="hidden" onchange="handleImageUpload(this, 'antenna_status_snaps')">
                            <button type="button" onclick="document.getElementById('antenna_status_snaps_input').click()" class="px-4 py-2 border border-dashed border-gray-300 rounded-lg hover:bg-gray-50 transition">
                                <i class="fas fa-camera mr-2"></i>Upload Photos
                            </button>
                            <div id="antenna_status_snaps_preview" class="flex gap-2 flex-wrap"></div>
                        </div>
                        <input type="hidden" name="antenna_status_snaps" id="antenna_status_snaps">
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
                            <label class="block text-sm font-medium text-gray-700 mb-1">GPS Installed <span class="text-red-500">*</span></label>
                            <select name="gps_installed" id="gps_installed" required class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                                <option value="">Select</option>
                                <option value="yes">Yes</option>
                                <option value="no">No</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">GPS Status <span class="text-red-500">*</span></label>
                            <select name="gps_status" id="gps_status" required class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                                <option value="">Select</option>
                                <option value="working">Working</option>
                                <option value="notWorking">Not Working</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">GPS Remarks</label>
                        <textarea name="gps_remarks" id="gps_remarks" rows="2" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">GPS Photos</label>
                        <div class="flex items-center gap-4">
                            <input type="file" id="gps_snaps_input" accept="image/jpeg,image/png" multiple class="hidden" onchange="handleImageUpload(this, 'gps_snaps')">
                            <button type="button" onclick="document.getElementById('gps_snaps_input').click()" class="px-4 py-2 border border-dashed border-gray-300 rounded-lg hover:bg-gray-50 transition">
                                <i class="fas fa-camera mr-2"></i>Upload Photos
                            </button>
                            <div id="gps_snaps_preview" class="flex gap-2 flex-wrap"></div>
                        </div>
                        <input type="hidden" name="gps_snaps" id="gps_snaps">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">GPS Status Remarks</label>
                        <textarea name="gps_status_remarks" id="gps_status_remarks" rows="2" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">GPS Status Photos</label>
                        <div class="flex items-center gap-4">
                            <input type="file" id="gps_status_snaps_input" accept="image/jpeg,image/png" multiple class="hidden" onchange="handleImageUpload(this, 'gps_status_snaps')">
                            <button type="button" onclick="document.getElementById('gps_status_snaps_input').click()" class="px-4 py-2 border border-dashed border-gray-300 rounded-lg hover:bg-gray-50 transition">
                                <i class="fas fa-camera mr-2"></i>Upload Photos
                            </button>
                            <div id="gps_status_snaps_preview" class="flex gap-2 flex-wrap"></div>
                        </div>
                        <input type="hidden" name="gps_status_snaps" id="gps_status_snaps">
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
                            <label class="block text-sm font-medium text-gray-700 mb-1">WiFi Installed <span class="text-red-500">*</span></label>
                            <select name="wifi_installed" id="wifi_installed" required class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                                <option value="">Select</option>
                                <option value="yes">Yes</option>
                                <option value="no">No</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">WiFi Status <span class="text-red-500">*</span></label>
                            <select name="wifi_status" id="wifi_status" required class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                                <option value="">Select</option>
                                <option value="working">Working</option>
                                <option value="notWorking">Not Working</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">WiFi Remarks</label>
                        <textarea name="wifi_remarks" id="wifi_remarks" rows="2" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">WiFi Photos</label>
                        <div class="flex items-center gap-4">
                            <input type="file" id="wifi_snaps_input" accept="image/jpeg,image/png" multiple class="hidden" onchange="handleImageUpload(this, 'wifi_snaps')">
                            <button type="button" onclick="document.getElementById('wifi_snaps_input').click()" class="px-4 py-2 border border-dashed border-gray-300 rounded-lg hover:bg-gray-50 transition">
                                <i class="fas fa-camera mr-2"></i>Upload Photos
                            </button>
                            <div id="wifi_snaps_preview" class="flex gap-2 flex-wrap"></div>
                        </div>
                        <input type="hidden" name="wifi_snaps" id="wifi_snaps">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">WiFi Status Remarks</label>
                        <textarea name="wifi_status_remarks" id="wifi_status_remarks" rows="2" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">WiFi Status Photos</label>
                        <div class="flex items-center gap-4">
                            <input type="file" id="wifi_status_snaps_input" accept="image/jpeg,image/png" multiple class="hidden" onchange="handleImageUpload(this, 'wifi_status_snaps')">
                            <button type="button" onclick="document.getElementById('wifi_status_snaps_input').click()" class="px-4 py-2 border border-dashed border-gray-300 rounded-lg hover:bg-gray-50 transition">
                                <i class="fas fa-camera mr-2"></i>Upload Photos
                            </button>
                            <div id="wifi_status_snaps_preview" class="flex gap-2 flex-wrap"></div>
                        </div>
                        <input type="hidden" name="wifi_status_snaps" id="wifi_status_snaps">
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
                            <label class="block text-sm font-medium text-gray-700 mb-1">Airtel SIM Installed <span class="text-red-500">*</span></label>
                            <select name="airtel_sim_installed" id="airtel_sim_installed" required class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                                <option value="">Select</option>
                                <option value="yes">Yes</option>
                                <option value="no">No</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Airtel SIM Status <span class="text-red-500">*</span></label>
                            <select name="airtel_sim_status" id="airtel_sim_status" required class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                                <option value="">Select</option>
                                <option value="working">Working</option>
                                <option value="notWorking">Not Working</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Airtel SIM Remarks</label>
                        <textarea name="airtel_sim_remarks" id="airtel_sim_remarks" rows="2" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Airtel SIM Photos</label>
                        <div class="flex items-center gap-4">
                            <input type="file" id="airtel_sim_snaps_input" accept="image/jpeg,image/png" multiple class="hidden" onchange="handleImageUpload(this, 'airtel_sim_snaps')">
                            <button type="button" onclick="document.getElementById('airtel_sim_snaps_input').click()" class="px-4 py-2 border border-dashed border-gray-300 rounded-lg hover:bg-gray-50 transition">
                                <i class="fas fa-camera mr-2"></i>Upload Photos
                            </button>
                            <div id="airtel_sim_snaps_preview" class="flex gap-2 flex-wrap"></div>
                        </div>
                        <input type="hidden" name="airtel_sim_snaps" id="airtel_sim_snaps">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Airtel SIM Status Remarks</label>
                        <textarea name="airtel_sim_status_remarks" id="airtel_sim_status_remarks" rows="2" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Airtel SIM Status Photos</label>
                        <div class="flex items-center gap-4">
                            <input type="file" id="airtel_sim_status_snaps_input" accept="image/jpeg,image/png" multiple class="hidden" onchange="handleImageUpload(this, 'airtel_sim_status_snaps')">
                            <button type="button" onclick="document.getElementById('airtel_sim_status_snaps_input').click()" class="px-4 py-2 border border-dashed border-gray-300 rounded-lg hover:bg-gray-50 transition">
                                <i class="fas fa-camera mr-2"></i>Upload Photos
                            </button>
                            <div id="airtel_sim_status_snaps_preview" class="flex gap-2 flex-wrap"></div>
                        </div>
                        <input type="hidden" name="airtel_sim_status_snaps" id="airtel_sim_status_snaps">
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
                            <label class="block text-sm font-medium text-gray-700 mb-1">Vodafone SIM Installed <span class="text-red-500">*</span></label>
                            <select name="vodafone_sim_installed" id="vodafone_sim_installed" required class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                                <option value="">Select</option>
                                <option value="yes">Yes</option>
                                <option value="no">No</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Vodafone SIM Status <span class="text-red-500">*</span></label>
                            <select name="vodafone_sim_status" id="vodafone_sim_status" required class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                                <option value="">Select</option>
                                <option value="working">Working</option>
                                <option value="notWorking">Not Working</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Vodafone SIM Remarks</label>
                        <textarea name="vodafone_sim_remarks" id="vodafone_sim_remarks" rows="2" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Vodafone SIM Photos</label>
                        <div class="flex items-center gap-4">
                            <input type="file" id="vodafone_sim_snaps_input" accept="image/jpeg,image/png" multiple class="hidden" onchange="handleImageUpload(this, 'vodafone_sim_snaps')">
                            <button type="button" onclick="document.getElementById('vodafone_sim_snaps_input').click()" class="px-4 py-2 border border-dashed border-gray-300 rounded-lg hover:bg-gray-50 transition">
                                <i class="fas fa-camera mr-2"></i>Upload Photos
                            </button>
                            <div id="vodafone_sim_snaps_preview" class="flex gap-2 flex-wrap"></div>
                        </div>
                        <input type="hidden" name="vodafone_sim_snaps" id="vodafone_sim_snaps">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Vodafone SIM Status Remarks</label>
                        <textarea name="vodafone_sim_status_remarks" id="vodafone_sim_status_remarks" rows="2" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Vodafone SIM Status Photos</label>
                        <div class="flex items-center gap-4">
                            <input type="file" id="vodafone_sim_status_snaps_input" accept="image/jpeg,image/png" multiple class="hidden" onchange="handleImageUpload(this, 'vodafone_sim_status_snaps')">
                            <button type="button" onclick="document.getElementById('vodafone_sim_status_snaps_input').click()" class="px-4 py-2 border border-dashed border-gray-300 rounded-lg hover:bg-gray-50 transition">
                                <i class="fas fa-camera mr-2"></i>Upload Photos
                            </button>
                            <div id="vodafone_sim_status_snaps_preview" class="flex gap-2 flex-wrap"></div>
                        </div>
                        <input type="hidden" name="vodafone_sim_status_snaps" id="vodafone_sim_status_snaps">
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
                            <label class="block text-sm font-medium text-gray-700 mb-1">JIO SIM Installed <span class="text-red-500">*</span></label>
                            <select name="jio_sim_installed" id="jio_sim_installed" required class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                                <option value="">Select</option>
                                <option value="yes">Yes</option>
                                <option value="no">No</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">JIO SIM Status <span class="text-red-500">*</span></label>
                            <select name="jio_sim_status" id="jio_sim_status" required class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                                <option value="">Select</option>
                                <option value="working">Working</option>
                                <option value="notWorking">Not Working</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">JIO SIM Remarks</label>
                        <textarea name="jio_sim_remarks" id="jio_sim_remarks" rows="2" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">JIO SIM Photos</label>
                        <div class="flex items-center gap-4">
                            <input type="file" id="jio_sim_snaps_input" accept="image/jpeg,image/png" multiple class="hidden" onchange="handleImageUpload(this, 'jio_sim_snaps')">
                            <button type="button" onclick="document.getElementById('jio_sim_snaps_input').click()" class="px-4 py-2 border border-dashed border-gray-300 rounded-lg hover:bg-gray-50 transition">
                                <i class="fas fa-camera mr-2"></i>Upload Photos
                            </button>
                            <div id="jio_sim_snaps_preview" class="flex gap-2 flex-wrap"></div>
                        </div>
                        <input type="hidden" name="jio_sim_snaps" id="jio_sim_snaps">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">JIO SIM Status Remarks</label>
                        <textarea name="jio_sim_status_remarks" id="jio_sim_status_remarks" rows="2" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">JIO SIM Status Photos</label>
                        <div class="flex items-center gap-4">
                            <input type="file" id="jio_sim_status_snaps_input" accept="image/jpeg,image/png" multiple class="hidden" onchange="handleImageUpload(this, 'jio_sim_status_snaps')">
                            <button type="button" onclick="document.getElementById('jio_sim_status_snaps_input').click()" class="px-4 py-2 border border-dashed border-gray-300 rounded-lg hover:bg-gray-50 transition">
                                <i class="fas fa-camera mr-2"></i>Upload Photos
                            </button>
                            <div id="jio_sim_status_snaps_preview" class="flex gap-2 flex-wrap"></div>
                        </div>
                        <input type="hidden" name="jio_sim_status_snaps" id="jio_sim_status_snaps">
                    </div>
                </div>
            </div>
            
            <!-- Verification Section -->
            <div class="bg-white rounded-xl shadow-sm">
                <div class="p-4 border-b bg-gray-50 rounded-t-xl">
                    <h4 class="font-semibold text-gray-800"><i class="fas fa-signature mr-2 text-primary"></i>Verification Section</h4>
                </div>
                <div class="p-6 space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Digital Signature <span class="text-red-500">*</span></label>
                            <div class="border-2 border-dashed border-gray-300 rounded-lg p-4">
                                <canvas id="signature-canvas" class="w-full h-40 border rounded bg-white cursor-crosshair"></canvas>
                                <div class="flex justify-between mt-2">
                                    <button type="button" onclick="clearSignature()" class="text-sm text-gray-500 hover:text-gray-700">
                                        <i class="fas fa-eraser mr-1"></i>Clear
                                    </button>
                                    <span class="text-xs text-gray-400">Sign above</span>
                                </div>
                            </div>
                            <input type="hidden" name="signature_image" id="signature_image">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Vendor Stamp</label>
                            <div class="flex items-center gap-4">
                                <input type="file" id="vendor_stamp_input" accept="image/jpeg,image/png" class="hidden" onchange="handleImageUpload(this, 'vendor_stamp')">
                                <button type="button" onclick="document.getElementById('vendor_stamp_input').click()" class="px-4 py-2 border border-dashed border-gray-300 rounded-lg hover:bg-gray-50 transition">
                                    <i class="fas fa-stamp mr-2"></i>Upload Stamp
                                </button>
                                <div id="vendor_stamp_preview" class="flex gap-2 flex-wrap"></div>
                            </div>
                            <input type="hidden" name="vendor_stamp" id="vendor_stamp">
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Form Actions -->
            <div class="bg-white rounded-xl shadow-sm p-6">
                <div class="flex justify-end gap-3">
                    <a href="index.php" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">
                        Cancel
                    </a>
                    <button type="button" onclick="saveForm()" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                        <i class="fas fa-save mr-2"></i>Save Draft
                    </button>
                    <button type="button" onclick="submitForm()" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition">
                        <i class="fas fa-paper-plane mr-2"></i>Submit Installation
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>


<script>
// State
const state = {
    installation: null,
    isLoading: true,
    canEdit: false,
    signatureCanvas: null,
    signatureCtx: null,
    isDrawing: false
};

const installationId = <?php echo $installationId; ?>;
const API_BASE = '../api/installation';

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    // Use server-side status info for initial UI state
    // Requirement 4.1: Display material receipt confirmation if pending_materials
    if (serverIsPendingMaterials) {
        document.getElementById('loading-state').classList.add('hidden');
        document.getElementById('form-content').classList.remove('hidden');
        document.getElementById('pending-materials-notice').classList.remove('hidden');
        disableForm(true);
        state.canEdit = false;
    }
    
    loadInstallation();
    initSignatureCanvas();
    setupEventListeners();
});

// Setup event listeners
function setupEventListeners() {
    // LAN cable status change
    document.getElementById('lan_cable_status').addEventListener('change', function(e) {
        const container = document.getElementById('lan_cable_not_working_reasons_container');
        if (e.target.value === 'notWorking') {
            container.classList.remove('hidden');
        } else {
            container.classList.add('hidden');
        }
    });
}

// Load installation data
async function loadInstallation() {
    try {
        const response = await fetch(`${API_BASE}/get.php?id=${installationId}`, {
            credentials: 'include'
        });
        
        const data = await response.json();
        
        if (data.success) {
            state.installation = data.data;
            populateForm(data.data);
            updateUIState(data.data);
        } else {
            showError(data.message || 'Failed to load installation');
        }
    } catch (error) {
        console.error('Error loading installation:', error);
        showError('Failed to load installation data');
    } finally {
        document.getElementById('loading-state').classList.add('hidden');
    }
}

// Populate form with installation data
function populateForm(data) {
    // Site information (read-only)
    document.getElementById('atm_id').value = data.atm_id || '';
    document.getElementById('lho').value = data.lho || '';
    document.getElementById('city').value = data.city || '';
    document.getElementById('state').value = data.state || '';
    document.getElementById('address').value = data.address || '';
    
    // Vendor/Engineer information
    document.getElementById('vendor_name').value = data.vendor_name || '';
    document.getElementById('engineer_name').value = data.engineer_name || '';
    document.getElementById('engineer_number').value = data.engineer_number || '';
    
    // Router section
    document.getElementById('router_serial').value = data.router_serial || '';
    document.getElementById('router_make').value = data.router_make || '';
    document.getElementById('router_model').value = data.router_model || '';
    document.getElementById('router_fixed').value = data.router_fixed || '';
    document.getElementById('router_status').value = data.router_status || '';
    document.getElementById('router_fixed_remarks').value = data.router_fixed_remarks || '';
    document.getElementById('router_status_remarks').value = data.router_status_remarks || '';
    if (data.router_fixed_snaps) {
        document.getElementById('router_fixed_snaps').value = data.router_fixed_snaps;
        renderImagePreviews('router_fixed_snaps', data.router_fixed_snaps);
    }
    if (data.router_status_snaps) {
        document.getElementById('router_status_snaps').value = data.router_status_snaps;
        renderImagePreviews('router_status_snaps', data.router_status_snaps);
    }
    
    // Adaptor section
    document.getElementById('adaptor_installed').value = data.adaptor_installed || '';
    document.getElementById('adaptor_status').value = data.adaptor_status || '';
    document.getElementById('adaptor_status_remarks').value = data.adaptor_status_remarks || '';
    if (data.adaptor_snaps) {
        document.getElementById('adaptor_snaps').value = data.adaptor_snaps;
        renderImagePreviews('adaptor_snaps', data.adaptor_snaps);
    }
    if (data.adaptor_status_snaps) {
        document.getElementById('adaptor_status_snaps').value = data.adaptor_status_snaps;
        renderImagePreviews('adaptor_status_snaps', data.adaptor_status_snaps);
    }
    
    // LAN Cable section
    document.getElementById('lan_cable_installed').value = data.lan_cable_installed || '';
    document.getElementById('lan_cable_status').value = data.lan_cable_status || '';
    document.getElementById('lan_cable_install_remark').value = data.lan_cable_install_remark || '';
    document.getElementById('lan_cable_status_remark').value = data.lan_cable_status_remark || '';
    document.getElementById('lan_cable_status_not_working_reasons').value = data.lan_cable_status_not_working_reasons || '';
    if (data.lan_cable_status === 'notWorking') {
        document.getElementById('lan_cable_not_working_reasons_container').classList.remove('hidden');
    }
    if (data.lan_cable_install_snap) {
        document.getElementById('lan_cable_install_snap').value = data.lan_cable_install_snap;
        renderImagePreviews('lan_cable_install_snap', data.lan_cable_install_snap);
    }
    if (data.lan_cable_status_snap) {
        document.getElementById('lan_cable_status_snap').value = data.lan_cable_status_snap;
        renderImagePreviews('lan_cable_status_snap', data.lan_cable_status_snap);
    }
    
    // Antenna section
    document.getElementById('antenna_installed').value = data.antenna_installed || '';
    document.getElementById('antenna_status').value = data.antenna_status || '';
    document.getElementById('antenna_remarks').value = data.antenna_remarks || '';
    document.getElementById('antenna_status_remarks').value = data.antenna_status_remarks || '';
    if (data.antenna_snaps) {
        document.getElementById('antenna_snaps').value = data.antenna_snaps;
        renderImagePreviews('antenna_snaps', data.antenna_snaps);
    }
    if (data.antenna_status_snaps) {
        document.getElementById('antenna_status_snaps').value = data.antenna_status_snaps;
        renderImagePreviews('antenna_status_snaps', data.antenna_status_snaps);
    }
    
    // GPS section
    document.getElementById('gps_installed').value = data.gps_installed || '';
    document.getElementById('gps_status').value = data.gps_status || '';
    document.getElementById('gps_remarks').value = data.gps_remarks || '';
    document.getElementById('gps_status_remarks').value = data.gps_status_remarks || '';
    if (data.gps_snaps) {
        document.getElementById('gps_snaps').value = data.gps_snaps;
        renderImagePreviews('gps_snaps', data.gps_snaps);
    }
    if (data.gps_status_snaps) {
        document.getElementById('gps_status_snaps').value = data.gps_status_snaps;
        renderImagePreviews('gps_status_snaps', data.gps_status_snaps);
    }
    
    // WiFi section
    document.getElementById('wifi_installed').value = data.wifi_installed || '';
    document.getElementById('wifi_status').value = data.wifi_status || '';
    document.getElementById('wifi_remarks').value = data.wifi_remarks || '';
    document.getElementById('wifi_status_remarks').value = data.wifi_status_remarks || '';
    if (data.wifi_snaps) {
        document.getElementById('wifi_snaps').value = data.wifi_snaps;
        renderImagePreviews('wifi_snaps', data.wifi_snaps);
    }
    if (data.wifi_status_snaps) {
        document.getElementById('wifi_status_snaps').value = data.wifi_status_snaps;
        renderImagePreviews('wifi_status_snaps', data.wifi_status_snaps);
    }
    
    // Airtel SIM section
    document.getElementById('airtel_sim_installed').value = data.airtel_sim_installed || '';
    document.getElementById('airtel_sim_status').value = data.airtel_sim_status || '';
    document.getElementById('airtel_sim_remarks').value = data.airtel_sim_remarks || '';
    document.getElementById('airtel_sim_status_remarks').value = data.airtel_sim_status_remarks || '';
    if (data.airtel_sim_snaps) {
        document.getElementById('airtel_sim_snaps').value = data.airtel_sim_snaps;
        renderImagePreviews('airtel_sim_snaps', data.airtel_sim_snaps);
    }
    if (data.airtel_sim_status_snaps) {
        document.getElementById('airtel_sim_status_snaps').value = data.airtel_sim_status_snaps;
        renderImagePreviews('airtel_sim_status_snaps', data.airtel_sim_status_snaps);
    }
    
    // Vodafone SIM section
    document.getElementById('vodafone_sim_installed').value = data.vodafone_sim_installed || '';
    document.getElementById('vodafone_sim_status').value = data.vodafone_sim_status || '';
    document.getElementById('vodafone_sim_remarks').value = data.vodafone_sim_remarks || '';
    document.getElementById('vodafone_sim_status_remarks').value = data.vodafone_sim_status_remarks || '';
    if (data.vodafone_sim_snaps) {
        document.getElementById('vodafone_sim_snaps').value = data.vodafone_sim_snaps;
        renderImagePreviews('vodafone_sim_snaps', data.vodafone_sim_snaps);
    }
    if (data.vodafone_sim_status_snaps) {
        document.getElementById('vodafone_sim_status_snaps').value = data.vodafone_sim_status_snaps;
        renderImagePreviews('vodafone_sim_status_snaps', data.vodafone_sim_status_snaps);
    }
    
    // JIO SIM section
    document.getElementById('jio_sim_installed').value = data.jio_sim_installed || '';
    document.getElementById('jio_sim_status').value = data.jio_sim_status || '';
    document.getElementById('jio_sim_remarks').value = data.jio_sim_remarks || '';
    document.getElementById('jio_sim_status_remarks').value = data.jio_sim_status_remarks || '';
    if (data.jio_sim_snaps) {
        document.getElementById('jio_sim_snaps').value = data.jio_sim_snaps;
        renderImagePreviews('jio_sim_snaps', data.jio_sim_snaps);
    }
    if (data.jio_sim_status_snaps) {
        document.getElementById('jio_sim_status_snaps').value = data.jio_sim_status_snaps;
        renderImagePreviews('jio_sim_status_snaps', data.jio_sim_status_snaps);
    }
    
    // Verification section
    if (data.signature_image) {
        document.getElementById('signature_image').value = data.signature_image;
        // Load signature image to canvas if exists
        loadSignatureImage(data.signature_image);
    }
    if (data.vendor_stamp) {
        document.getElementById('vendor_stamp').value = data.vendor_stamp;
        renderImagePreviews('vendor_stamp', data.vendor_stamp);
    }
}

// Update UI state based on installation status
// Requirements: 4.1, 4.4, 4.5
function updateUIState(data) {
    const status = data.status;
    const statusBadge = document.getElementById('status-badge');
    const formContent = document.getElementById('form-content');
    const pendingNotice = document.getElementById('pending-materials-notice');
    const form = document.getElementById('installation-form');
    
    // Update status badge
    statusBadge.textContent = getStatusLabel(status);
    statusBadge.className = `px-3 py-1 rounded-full text-sm ${getStatusClass(status)}`;
    
    // Show form content
    formContent.classList.remove('hidden');
    
    // Handle early workflow statuses (Requirement 4.4)
    // Form access is denied for pending_assignment, pending_eta, pending_ada, pending_materials
    const earlyWorkflowStatuses = ['pending_assignment', 'pending_eta', 'pending_ada', 'pending_materials'];
    
    if (earlyWorkflowStatuses.includes(status)) {
        // Requirement 4.1: Display material receipt confirmation if pending_materials
        if (status === 'pending_materials') {
            pendingNotice.classList.remove('hidden');
        } else {
            // For other early statuses, show appropriate message
            pendingNotice.innerHTML = `
                <div class="flex items-start">
                    <div class="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center mr-4">
                        <i class="fas fa-clock text-yellow-600 text-xl"></i>
                    </div>
                    <div class="flex-1">
                        <h4 class="text-lg font-semibold text-yellow-800">Workflow In Progress</h4>
                        <p class="text-yellow-700 mt-1">The installation form will be available after materials are received. Current status: ${getStatusLabel(status)}</p>
                    </div>
                </div>
            `;
            pendingNotice.classList.remove('hidden');
        }
        disableForm(true);
        state.canEdit = false;
    } else if (['adv_approved'].includes(status)) {
        // Read-only for approved installations (Requirement 15.6)
        disableForm(true);
        state.canEdit = false;
        document.getElementById('save-btn').classList.add('hidden');
        document.getElementById('submit-btn').classList.add('hidden');
    } else if (['submitted', 'pending_contractor_review', 'contractor_approved'].includes(status)) {
        // Read-only for submitted/under review
        disableForm(true);
        state.canEdit = false;
        document.getElementById('save-btn').classList.add('hidden');
        document.getElementById('submit-btn').classList.add('hidden');
    } else {
        // Editable (Requirement 4.5: Form access enabled for materials_received or later)
        state.canEdit = true;
    }
}

// Disable/enable form
function disableForm(disabled) {
    const form = document.getElementById('installation-form');
    const inputs = form.querySelectorAll('input:not([type="hidden"]), select, textarea, button');
    inputs.forEach(input => {
        if (disabled) {
            input.disabled = true;
            input.classList.add('bg-gray-100');
        } else {
            input.disabled = false;
            input.classList.remove('bg-gray-100');
        }
    });
}

// Get status label
function getStatusLabel(status) {
    const labels = {
        pending_assignment: 'Pending Assignment',
        pending_eta: 'Pending ETA',
        pending_ada: 'Pending ADA',
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

// Get status CSS class
function getStatusClass(status) {
    const classes = {
        pending_assignment: 'bg-gray-100 text-gray-700',
        pending_eta: 'bg-gray-100 text-gray-700',
        pending_ada: 'bg-gray-100 text-gray-700',
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

// Material receipt modal functions
function openMaterialReceiptModal() {
    document.getElementById('material-receipt-modal').classList.remove('hidden');
}

function closeMaterialReceiptModal() {
    document.getElementById('material-receipt-modal').classList.add('hidden');
}

async function confirmMaterialReceipt() {
    const btn = document.getElementById('confirm-materials-btn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Confirming...';
    
    try {
        const response = await fetch(`${API_BASE}/confirm-materials.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({ installation_id: installationId })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showToast('Materials confirmed successfully', 'success');
            closeMaterialReceiptModal();
            loadInstallation(); // Reload to update UI
        } else {
            showToast(data.message || 'Failed to confirm materials', 'error');
        }
    } catch (error) {
        console.error('Error confirming materials:', error);
        showToast('Failed to confirm materials', 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-check mr-2"></i>Confirm Receipt';
    }
}

// Save form
async function saveForm() {
    if (!state.canEdit) {
        showToast('Form is not editable', 'error');
        return;
    }
    
    // Save signature to hidden field
    saveSignatureToField();
    
    const formData = getFormData();
    
    try {
        const response = await fetch(`${API_BASE}/save.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({
                installation_id: installationId,
                ...formData
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showToast('Installation saved successfully', 'success');
        } else {
            showToast(data.message || 'Failed to save installation', 'error');
        }
    } catch (error) {
        console.error('Error saving installation:', error);
        showToast('Failed to save installation', 'error');
    }
}

// Submit form
async function submitForm() {
    if (!state.canEdit) {
        showToast('Form is not editable', 'error');
        return;
    }
    
    // Save signature to hidden field
    saveSignatureToField();
    
    // Validate form
    const form = document.getElementById('installation-form');
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }
    
    // Check signature
    if (!document.getElementById('signature_image').value) {
        showToast('Please provide your digital signature', 'error');
        return;
    }
    
    if (!confirm('Are you sure you want to submit this installation? You will not be able to edit it after submission.')) {
        return;
    }
    
    try {
        // First save the form
        const formData = getFormData();
        await fetch(`${API_BASE}/save.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({
                installation_id: installationId,
                ...formData
            })
        });
        
        // Then submit
        const response = await fetch(`${API_BASE}/submit.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({ installation_id: installationId })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showToast('Installation submitted successfully', 'success');
            setTimeout(() => window.location.href = 'index.php', 1500);
        } else {
            showToast(data.message || 'Failed to submit installation', 'error');
        }
    } catch (error) {
        console.error('Error submitting installation:', error);
        showToast('Failed to submit installation', 'error');
    }
}

// Get form data
function getFormData() {
    const form = document.getElementById('installation-form');
    const formData = new FormData(form);
    const data = {};
    
    for (let [key, value] of formData.entries()) {
        data[key] = value;
    }
    
    return data;
}

// Image upload handling
async function handleImageUpload(input, fieldId) {
    const files = input.files;
    if (!files.length) return;
    
    const uploadedPaths = [];
    
    for (let file of files) {
        // Validate file
        if (!['image/jpeg', 'image/png'].includes(file.type)) {
            showToast('Only JPEG and PNG images are allowed', 'error');
            continue;
        }
        
        if (file.size > 5 * 1024 * 1024) {
            showToast('Image size must be less than 5MB', 'error');
            continue;
        }
        
        // Upload file
        const formData = new FormData();
        formData.append('installation_id', installationId);
        formData.append('section', fieldId);
        formData.append('file', file);
        
        try {
            const response = await fetch(`${API_BASE}/upload-image.php`, {
                method: 'POST',
                credentials: 'include',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                uploadedPaths.push(data.data.path);
            } else {
                showToast(data.message || 'Failed to upload image', 'error');
            }
        } catch (error) {
            console.error('Error uploading image:', error);
            showToast('Failed to upload image', 'error');
        }
    }
    
    // Update hidden field with paths
    const currentPaths = document.getElementById(fieldId).value;
    const allPaths = currentPaths ? currentPaths.split(',').concat(uploadedPaths) : uploadedPaths;
    document.getElementById(fieldId).value = allPaths.join(',');
    
    // Render previews
    renderImagePreviews(fieldId, allPaths.join(','));
    
    // Clear input
    input.value = '';
}

// Render image previews
function renderImagePreviews(fieldId, pathsStr) {
    const container = document.getElementById(fieldId + '_preview');
    if (!container || !pathsStr) return;
    
    const paths = pathsStr.split(',').filter(p => p.trim());
    
    container.innerHTML = paths.map((path, index) => `
        <div class="relative group">
            <img src="../${path}" alt="Preview" class="w-16 h-16 object-cover rounded border cursor-pointer" onclick="openLightbox('../${path}')">
            ${state.canEdit ? `
                <button type="button" onclick="removeImage('${fieldId}', ${index})" 
                    class="absolute -top-2 -right-2 w-5 h-5 bg-red-500 text-white rounded-full text-xs opacity-0 group-hover:opacity-100 transition">
                    <i class="fas fa-times"></i>
                </button>
            ` : ''}
        </div>
    `).join('');
}

// Remove image
function removeImage(fieldId, index) {
    const field = document.getElementById(fieldId);
    const paths = field.value.split(',').filter(p => p.trim());
    paths.splice(index, 1);
    field.value = paths.join(',');
    renderImagePreviews(fieldId, field.value);
}

// Lightbox
function openLightbox(src) {
    const modal = document.createElement('div');
    modal.className = 'fixed inset-0 z-50 flex items-center justify-center bg-black/80';
    modal.onclick = () => modal.remove();
    modal.innerHTML = `
        <img src="${src}" class="max-w-[90vw] max-h-[90vh] object-contain">
        <button class="absolute top-4 right-4 text-white text-2xl" onclick="this.parentElement.remove()">
            <i class="fas fa-times"></i>
        </button>
    `;
    document.body.appendChild(modal);
}

// Signature canvas
function initSignatureCanvas() {
    const canvas = document.getElementById('signature-canvas');
    if (!canvas) return;
    
    state.signatureCanvas = canvas;
    state.signatureCtx = canvas.getContext('2d');
    
    // Set canvas size
    canvas.width = canvas.offsetWidth;
    canvas.height = canvas.offsetHeight;
    
    // Drawing events
    canvas.addEventListener('mousedown', startDrawing);
    canvas.addEventListener('mousemove', draw);
    canvas.addEventListener('mouseup', stopDrawing);
    canvas.addEventListener('mouseout', stopDrawing);
    
    // Touch events
    canvas.addEventListener('touchstart', handleTouchStart);
    canvas.addEventListener('touchmove', handleTouchMove);
    canvas.addEventListener('touchend', stopDrawing);
}

function startDrawing(e) {
    state.isDrawing = true;
    state.signatureCtx.beginPath();
    state.signatureCtx.moveTo(e.offsetX, e.offsetY);
}

function draw(e) {
    if (!state.isDrawing) return;
    state.signatureCtx.lineTo(e.offsetX, e.offsetY);
    state.signatureCtx.stroke();
}

function stopDrawing() {
    state.isDrawing = false;
}

function handleTouchStart(e) {
    e.preventDefault();
    const touch = e.touches[0];
    const rect = state.signatureCanvas.getBoundingClientRect();
    startDrawing({ offsetX: touch.clientX - rect.left, offsetY: touch.clientY - rect.top });
}

function handleTouchMove(e) {
    e.preventDefault();
    const touch = e.touches[0];
    const rect = state.signatureCanvas.getBoundingClientRect();
    draw({ offsetX: touch.clientX - rect.left, offsetY: touch.clientY - rect.top });
}

function clearSignature() {
    state.signatureCtx.clearRect(0, 0, state.signatureCanvas.width, state.signatureCanvas.height);
    document.getElementById('signature_image').value = '';
}

function saveSignatureToField() {
    const canvas = state.signatureCanvas;
    if (!canvas) return;
    
    // Check if canvas has content
    const ctx = state.signatureCtx;
    const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
    const hasContent = imageData.data.some((value, index) => index % 4 === 3 && value > 0);
    
    if (hasContent) {
        const dataUrl = canvas.toDataURL('image/png');
        document.getElementById('signature_image').value = dataUrl;
    }
}

function loadSignatureImage(path) {
    if (!path || path.startsWith('data:')) {
        // If it's a data URL, draw it on canvas
        if (path && path.startsWith('data:')) {
            const img = new Image();
            img.onload = function() {
                state.signatureCtx.drawImage(img, 0, 0);
            };
            img.src = path;
        }
        return;
    }
    
    // Load from file path
    const img = new Image();
    img.onload = function() {
        state.signatureCtx.drawImage(img, 0, 0, state.signatureCanvas.width, state.signatureCanvas.height);
    };
    img.src = '../' + path;
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

// Show toast
function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    const bgColor = type === 'success' ? 'bg-green-500' : type === 'error' ? 'bg-red-500' : 'bg-blue-500';
    toast.className = `fixed bottom-4 right-4 ${bgColor} text-white px-6 py-3 rounded-lg shadow-lg z-50`;
    toast.textContent = message;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 3000);
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
