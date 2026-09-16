<?php
/**
 * Migration / Seed script to create or update Installation Custom Form schema
 * Mapped to XTPL project (ID 1) and Global fallback.
 */

require_once __DIR__ . '/../config/autoload.php';

$db = DatabaseConfig::getInstance()->getConnection();

echo "Starting Installation Custom Form seeding...\n";

// 1. Check if XTPL project exists
$projRes = $db->query("SELECT id FROM projects WHERE id = 1 LIMIT 1");
$hasXtpl = $projRes && $projRes->num_rows > 0;
$projectId = $hasXtpl ? 1 : null;

// 2. Check or create custom form entry
$checkForm = $db->query("SELECT id FROM custom_forms WHERE purpose = 'installation' AND (project_id = 1 OR project_id IS NULL) ORDER BY project_id DESC LIMIT 1");
$formId = null;

if ($checkForm && $checkForm->num_rows > 0) {
    $row = $checkForm->fetch_assoc();
    $formId = (int)$row['id'];
    echo "Existing Installation Form found (ID: {$formId}). Updating...\n";
    $updateStmt = $db->prepare("UPDATE custom_forms SET form_name = ?, form_code = ?, status = 'active', updated_at = NOW() WHERE id = ?");
    $name = 'Installation Checklist Form';
    $code = 'FORM_INSTALLATION_XTPL_01';
    $updateStmt->bind_param('ssi', $name, $code, $formId);
    $updateStmt->execute();
} else {
    echo "Creating new Installation Form in custom_forms...\n";
    $insertStmt = $db->prepare("INSERT INTO custom_forms (form_code, form_name, purpose, project_id, version, status, is_default, created_by, created_at, updated_at) VALUES (?, ?, 'installation', ?, 1, 'active', 1, 1, NOW(), NOW())");
    $name = 'Installation Checklist Form';
    $code = 'FORM_INSTALLATION_XTPL_01';
    $insertStmt->bind_param('ssi', $code, $name, $projectId);
    $insertStmt->execute();
    $formId = (int)$db->insert_id;
    echo "Created Form ID: {$formId}\n";
}

// 3. Clear existing fields for this form to ensure clean sync
$db->query("DELETE FROM custom_form_fields WHERE form_id = " . intval($formId));

// 4. Define all fields matching installation/form.php
$fields = [
    // Section 1: Vendor & Engineer Information
    [
        'section_title' => 'Vendor & Engineer Information',
        'field_key' => 'vendor_name',
        'field_label' => 'Vendor Name',
        'field_type' => 'text',
        'placeholder' => 'Enter Vendor Name',
        'is_required' => 1,
        'grid_width' => 4,
        'options_json' => null,
    ],
    [
        'section_title' => 'Vendor & Engineer Information',
        'field_key' => 'engineer_name',
        'field_label' => 'Engineer Name',
        'field_type' => 'text',
        'placeholder' => 'Enter Engineer Name',
        'is_required' => 1,
        'grid_width' => 4,
        'options_json' => null,
    ],
    [
        'section_title' => 'Vendor & Engineer Information',
        'field_key' => 'engineer_number',
        'field_label' => 'Engineer Phone Number',
        'field_type' => 'phone',
        'placeholder' => 'Enter 10-digit mobile number',
        'is_required' => 1,
        'grid_width' => 4,
        'options_json' => null,
    ],

    // Section 2: Router Section
    [
        'section_title' => 'Router Section',
        'field_key' => 'router_serial',
        'field_label' => 'Router Serial Number',
        'field_type' => 'text',
        'placeholder' => 'e.g. RTR-987654321',
        'is_required' => 1,
        'grid_width' => 4,
        'options_json' => null,
    ],
    [
        'section_title' => 'Router Section',
        'field_key' => 'router_make',
        'field_label' => 'Router Make',
        'field_type' => 'text',
        'placeholder' => 'e.g. Teltonika / Advantech',
        'is_required' => 1,
        'grid_width' => 4,
        'options_json' => null,
    ],
    [
        'section_title' => 'Router Section',
        'field_key' => 'router_model',
        'field_label' => 'Router Model',
        'field_type' => 'text',
        'placeholder' => 'e.g. RUT950 / Dual SIM Standard',
        'is_required' => 1,
        'grid_width' => 4,
        'options_json' => null,
    ],
    [
        'section_title' => 'Router Section',
        'field_key' => 'router_fixed',
        'field_label' => 'Router Fixed',
        'field_type' => 'select',
        'placeholder' => 'Select Status',
        'is_required' => 1,
        'grid_width' => 6,
        'options_json' => json_encode([
            ['label' => 'Yes', 'value' => 'yes'],
            ['label' => 'No', 'value' => 'no'],
        ]),
    ],
    [
        'section_title' => 'Router Section',
        'field_key' => 'router_status',
        'field_label' => 'Router Status',
        'field_type' => 'select',
        'placeholder' => 'Select Working Status',
        'is_required' => 1,
        'grid_width' => 6,
        'options_json' => json_encode([
            ['label' => 'Working', 'value' => 'working'],
            ['label' => 'Not Working', 'value' => 'notWorking'],
        ]),
    ],
    [
        'section_title' => 'Router Section',
        'field_key' => 'router_fixed_remarks',
        'field_label' => 'Router Fixed Remarks',
        'field_type' => 'textarea',
        'placeholder' => 'Remarks regarding router mounting...',
        'is_required' => 0,
        'grid_width' => 12,
        'options_json' => null,
    ],
    [
        'section_title' => 'Router Section',
        'field_key' => 'router_fixed_snaps',
        'field_label' => 'Router Fixed Photos',
        'field_type' => 'file',
        'placeholder' => null,
        'is_required' => 0,
        'grid_width' => 6,
        'options_json' => null,
    ],
    [
        'section_title' => 'Router Section',
        'field_key' => 'router_status_remarks',
        'field_label' => 'Router Status Remarks',
        'field_type' => 'textarea',
        'placeholder' => 'Remarks regarding router operational status...',
        'is_required' => 0,
        'grid_width' => 12,
        'options_json' => null,
    ],
    [
        'section_title' => 'Router Section',
        'field_key' => 'router_status_snaps',
        'field_label' => 'Router Status Photos',
        'field_type' => 'file',
        'placeholder' => null,
        'is_required' => 0,
        'grid_width' => 6,
        'options_json' => null,
    ],

    // Section 3: Adaptor Section
    [
        'section_title' => 'Adaptor Section',
        'field_key' => 'adaptor_installed',
        'field_label' => 'Adaptor Installed',
        'field_type' => 'select',
        'placeholder' => 'Select Status',
        'is_required' => 1,
        'grid_width' => 6,
        'options_json' => json_encode([
            ['label' => 'Yes', 'value' => 'yes'],
            ['label' => 'No', 'value' => 'no'],
        ]),
    ],
    [
        'section_title' => 'Adaptor Section',
        'field_key' => 'adaptor_status',
        'field_label' => 'Adaptor Status',
        'field_type' => 'select',
        'placeholder' => 'Select Working Status',
        'is_required' => 1,
        'grid_width' => 6,
        'options_json' => json_encode([
            ['label' => 'Working', 'value' => 'working'],
            ['label' => 'Not Working', 'value' => 'notWorking'],
        ]),
    ],
    [
        'section_title' => 'Adaptor Section',
        'field_key' => 'adaptor_snaps',
        'field_label' => 'Adaptor Photos',
        'field_type' => 'file',
        'placeholder' => null,
        'is_required' => 0,
        'grid_width' => 6,
        'options_json' => null,
    ],
    [
        'section_title' => 'Adaptor Section',
        'field_key' => 'adaptor_status_remarks',
        'field_label' => 'Adaptor Status Remarks',
        'field_type' => 'textarea',
        'placeholder' => 'Remarks regarding adaptor...',
        'is_required' => 0,
        'grid_width' => 12,
        'options_json' => null,
    ],
    [
        'section_title' => 'Adaptor Section',
        'field_key' => 'adaptor_status_snaps',
        'field_label' => 'Adaptor Status Photos',
        'field_type' => 'file',
        'placeholder' => null,
        'is_required' => 0,
        'grid_width' => 6,
        'options_json' => null,
    ],

    // Section 4: LAN Cable Section
    [
        'section_title' => 'LAN Cable Section',
        'field_key' => 'lan_cable_installed',
        'field_label' => 'LAN Cable Installed',
        'field_type' => 'select',
        'placeholder' => 'Select Status',
        'is_required' => 1,
        'grid_width' => 6,
        'options_json' => json_encode([
            ['label' => 'Yes', 'value' => 'yes'],
            ['label' => 'No', 'value' => 'no'],
        ]),
    ],
    [
        'section_title' => 'LAN Cable Section',
        'field_key' => 'lan_cable_status',
        'field_label' => 'LAN Cable Status',
        'field_type' => 'select',
        'placeholder' => 'Select Working Status',
        'is_required' => 1,
        'grid_width' => 6,
        'options_json' => json_encode([
            ['label' => 'Working', 'value' => 'working'],
            ['label' => 'Not Working', 'value' => 'notWorking'],
        ]),
    ],
    [
        'section_title' => 'LAN Cable Section',
        'field_key' => 'lan_cable_install_remark',
        'field_label' => 'LAN Cable Install Remarks',
        'field_type' => 'textarea',
        'placeholder' => 'Remarks on LAN routing & crimping...',
        'is_required' => 0,
        'grid_width' => 12,
        'options_json' => null,
    ],
    [
        'section_title' => 'LAN Cable Section',
        'field_key' => 'lan_cable_install_snap',
        'field_label' => 'LAN Cable Install Photo',
        'field_type' => 'file',
        'placeholder' => null,
        'is_required' => 0,
        'grid_width' => 6,
        'options_json' => null,
    ],
    [
        'section_title' => 'LAN Cable Section',
        'field_key' => 'lan_cable_status_not_working_reasons',
        'field_label' => 'Not Working Reasons',
        'field_type' => 'textarea',
        'placeholder' => 'Explain why LAN connectivity failed...',
        'is_required' => 0,
        'grid_width' => 12,
        'options_json' => null,
    ],
    [
        'section_title' => 'LAN Cable Section',
        'field_key' => 'lan_cable_status_remark',
        'field_label' => 'LAN Cable Status Remarks',
        'field_type' => 'textarea',
        'placeholder' => 'Status notes...',
        'is_required' => 0,
        'grid_width' => 12,
        'options_json' => null,
    ],
    [
        'section_title' => 'LAN Cable Section',
        'field_key' => 'lan_cable_status_snap',
        'field_label' => 'LAN Cable Status Photo',
        'field_type' => 'file',
        'placeholder' => null,
        'is_required' => 0,
        'grid_width' => 6,
        'options_json' => null,
    ],

    // Section 5: Antenna Section
    [
        'section_title' => 'Antenna Section',
        'field_key' => 'antenna_installed',
        'field_label' => 'Antenna Installed',
        'field_type' => 'select',
        'placeholder' => 'Select Status',
        'is_required' => 1,
        'grid_width' => 6,
        'options_json' => json_encode([
            ['label' => 'Yes', 'value' => 'yes'],
            ['label' => 'No', 'value' => 'no'],
        ]),
    ],
    [
        'section_title' => 'Antenna Section',
        'field_key' => 'antenna_status',
        'field_label' => 'Antenna Status',
        'field_type' => 'select',
        'placeholder' => 'Select Working Status',
        'is_required' => 1,
        'grid_width' => 6,
        'options_json' => json_encode([
            ['label' => 'Working', 'value' => 'working'],
            ['label' => 'Not Working', 'value' => 'notWorking'],
        ]),
    ],
    [
        'section_title' => 'Antenna Section',
        'field_key' => 'antenna_remarks',
        'field_label' => 'Antenna Remarks',
        'field_type' => 'textarea',
        'placeholder' => 'Antenna mounting & signal observations...',
        'is_required' => 0,
        'grid_width' => 12,
        'options_json' => null,
    ],
    [
        'section_title' => 'Antenna Section',
        'field_key' => 'antenna_snaps',
        'field_label' => 'Antenna Photos',
        'field_type' => 'file',
        'placeholder' => null,
        'is_required' => 0,
        'grid_width' => 6,
        'options_json' => null,
    ],
    [
        'section_title' => 'Antenna Section',
        'field_key' => 'antenna_status_remarks',
        'field_label' => 'Antenna Status Remarks',
        'field_type' => 'textarea',
        'placeholder' => 'Status notes...',
        'is_required' => 0,
        'grid_width' => 12,
        'options_json' => null,
    ],
    [
        'section_title' => 'Antenna Section',
        'field_key' => 'antenna_status_snaps',
        'field_label' => 'Antenna Status Photos',
        'field_type' => 'file',
        'placeholder' => null,
        'is_required' => 0,
        'grid_width' => 6,
        'options_json' => null,
    ],

    // Section 6: GPS & Wi-Fi Section
    [
        'section_title' => 'GPS & Wi-Fi Section',
        'field_key' => 'gps_installed',
        'field_label' => 'GPS Installed',
        'field_type' => 'select',
        'placeholder' => 'Select Status',
        'is_required' => 1,
        'grid_width' => 6,
        'options_json' => json_encode([
            ['label' => 'Yes', 'value' => 'yes'],
            ['label' => 'No', 'value' => 'no'],
        ]),
    ],
    [
        'section_title' => 'GPS & Wi-Fi Section',
        'field_key' => 'gps_status',
        'field_label' => 'GPS Status',
        'field_type' => 'select',
        'placeholder' => 'Select Working Status',
        'is_required' => 1,
        'grid_width' => 6,
        'options_json' => json_encode([
            ['label' => 'Working', 'value' => 'working'],
            ['label' => 'Not Working', 'value' => 'notWorking'],
        ]),
    ],
    [
        'section_title' => 'GPS & Wi-Fi Section',
        'field_key' => 'gps_remarks',
        'field_label' => 'GPS Remarks',
        'field_type' => 'textarea',
        'placeholder' => 'GPS notes...',
        'is_required' => 0,
        'grid_width' => 12,
        'options_json' => null,
    ],
    [
        'section_title' => 'GPS & Wi-Fi Section',
        'field_key' => 'gps_snaps',
        'field_label' => 'GPS Photos',
        'field_type' => 'file',
        'placeholder' => null,
        'is_required' => 0,
        'grid_width' => 6,
        'options_json' => null,
    ],
    [
        'section_title' => 'GPS & Wi-Fi Section',
        'field_key' => 'gps_status_remarks',
        'field_label' => 'GPS Status Remarks',
        'field_type' => 'textarea',
        'placeholder' => 'Status notes...',
        'is_required' => 0,
        'grid_width' => 12,
        'options_json' => null,
    ],
    [
        'section_title' => 'GPS & Wi-Fi Section',
        'field_key' => 'gps_status_snaps',
        'field_label' => 'GPS Status Photos',
        'field_type' => 'file',
        'placeholder' => null,
        'is_required' => 0,
        'grid_width' => 6,
        'options_json' => null,
    ],
    [
        'section_title' => 'GPS & Wi-Fi Section',
        'field_key' => 'wifi_installed',
        'field_label' => 'WiFi Installed',
        'field_type' => 'select',
        'placeholder' => 'Select Status',
        'is_required' => 1,
        'grid_width' => 6,
        'options_json' => json_encode([
            ['label' => 'Yes', 'value' => 'yes'],
            ['label' => 'No', 'value' => 'no'],
        ]),
    ],
    [
        'section_title' => 'GPS & Wi-Fi Section',
        'field_key' => 'wifi_status',
        'field_label' => 'WiFi Status',
        'field_type' => 'select',
        'placeholder' => 'Select Working Status',
        'is_required' => 1,
        'grid_width' => 6,
        'options_json' => json_encode([
            ['label' => 'Working', 'value' => 'working'],
            ['label' => 'Not Working', 'value' => 'notWorking'],
        ]),
    ],
    [
        'section_title' => 'GPS & Wi-Fi Section',
        'field_key' => 'wifi_remarks',
        'field_label' => 'WiFi Remarks',
        'field_type' => 'textarea',
        'placeholder' => 'WiFi notes...',
        'is_required' => 0,
        'grid_width' => 12,
        'options_json' => null,
    ],
    [
        'section_title' => 'GPS & Wi-Fi Section',
        'field_key' => 'wifi_snaps',
        'field_label' => 'WiFi Photos',
        'field_type' => 'file',
        'placeholder' => null,
        'is_required' => 0,
        'grid_width' => 6,
        'options_json' => null,
    ],
    [
        'section_title' => 'GPS & Wi-Fi Section',
        'field_key' => 'wifi_status_remarks',
        'field_label' => 'WiFi Status Remarks',
        'field_type' => 'textarea',
        'placeholder' => 'Status notes...',
        'is_required' => 0,
        'grid_width' => 12,
        'options_json' => null,
    ],
    [
        'section_title' => 'GPS & Wi-Fi Section',
        'field_key' => 'wifi_status_snaps',
        'field_label' => 'WiFi Status Photos',
        'field_type' => 'file',
        'placeholder' => null,
        'is_required' => 0,
        'grid_width' => 6,
        'options_json' => null,
    ],

    // Section 7: Airtel SIM Section
    [
        'section_title' => 'Airtel SIM Section',
        'field_key' => 'airtel_sim_installed',
        'field_label' => 'Airtel SIM Installed',
        'field_type' => 'select',
        'placeholder' => 'Select Status',
        'is_required' => 1,
        'grid_width' => 6,
        'options_json' => json_encode([
            ['label' => 'Yes', 'value' => 'yes'],
            ['label' => 'No', 'value' => 'no'],
        ]),
    ],
    [
        'section_title' => 'Airtel SIM Section',
        'field_key' => 'airtel_sim_status',
        'field_label' => 'Airtel SIM Status',
        'field_type' => 'select',
        'placeholder' => 'Select Working Status',
        'is_required' => 1,
        'grid_width' => 6,
        'options_json' => json_encode([
            ['label' => 'Working', 'value' => 'working'],
            ['label' => 'Not Working', 'value' => 'notWorking'],
        ]),
    ],
    [
        'section_title' => 'Airtel SIM Section',
        'field_key' => 'airtel_sim_remarks',
        'field_label' => 'Airtel SIM Remarks',
        'field_type' => 'textarea',
        'placeholder' => 'Airtel SIM signal & performance...',
        'is_required' => 0,
        'grid_width' => 12,
        'options_json' => null,
    ],
    [
        'section_title' => 'Airtel SIM Section',
        'field_key' => 'airtel_sim_snaps',
        'field_label' => 'Airtel SIM Photos',
        'field_type' => 'file',
        'placeholder' => null,
        'is_required' => 0,
        'grid_width' => 6,
        'options_json' => null,
    ],
    [
        'section_title' => 'Airtel SIM Section',
        'field_key' => 'airtel_sim_status_remarks',
        'field_label' => 'Airtel SIM Status Remarks',
        'field_type' => 'textarea',
        'placeholder' => 'Status notes...',
        'is_required' => 0,
        'grid_width' => 12,
        'options_json' => null,
    ],
    [
        'section_title' => 'Airtel SIM Section',
        'field_key' => 'airtel_sim_status_snaps',
        'field_label' => 'Airtel SIM Status Photos',
        'field_type' => 'file',
        'placeholder' => null,
        'is_required' => 0,
        'grid_width' => 6,
        'options_json' => null,
    ],

    // Section 8: Vodafone SIM Section
    [
        'section_title' => 'Vodafone SIM Section',
        'field_key' => 'vodafone_sim_installed',
        'field_label' => 'Vodafone SIM Installed',
        'field_type' => 'select',
        'placeholder' => 'Select Status',
        'is_required' => 1,
        'grid_width' => 6,
        'options_json' => json_encode([
            ['label' => 'Yes', 'value' => 'yes'],
            ['label' => 'No', 'value' => 'no'],
        ]),
    ],
    [
        'section_title' => 'Vodafone SIM Section',
        'field_key' => 'vodafone_sim_status',
        'field_label' => 'Vodafone SIM Status',
        'field_type' => 'select',
        'placeholder' => 'Select Working Status',
        'is_required' => 1,
        'grid_width' => 6,
        'options_json' => json_encode([
            ['label' => 'Working', 'value' => 'working'],
            ['label' => 'Not Working', 'value' => 'notWorking'],
        ]),
    ],
    [
        'section_title' => 'Vodafone SIM Section',
        'field_key' => 'vodafone_sim_remarks',
        'field_label' => 'Vodafone SIM Remarks',
        'field_type' => 'textarea',
        'placeholder' => 'Vodafone SIM signal & performance...',
        'is_required' => 0,
        'grid_width' => 12,
        'options_json' => null,
    ],
    [
        'section_title' => 'Vodafone SIM Section',
        'field_key' => 'vodafone_sim_snaps',
        'field_label' => 'Vodafone SIM Photos',
        'field_type' => 'file',
        'placeholder' => null,
        'is_required' => 0,
        'grid_width' => 6,
        'options_json' => null,
    ],
    [
        'section_title' => 'Vodafone SIM Section',
        'field_key' => 'vodafone_sim_status_remarks',
        'field_label' => 'Vodafone SIM Status Remarks',
        'field_type' => 'textarea',
        'placeholder' => 'Status notes...',
        'is_required' => 0,
        'grid_width' => 12,
        'options_json' => null,
    ],
    [
        'section_title' => 'Vodafone SIM Section',
        'field_key' => 'vodafone_sim_status_snaps',
        'field_label' => 'Vodafone SIM Status Photos',
        'field_type' => 'file',
        'placeholder' => null,
        'is_required' => 0,
        'grid_width' => 6,
        'options_json' => null,
    ],

    // Section 9: JIO SIM Section
    [
        'section_title' => 'JIO SIM Section',
        'field_key' => 'jio_sim_installed',
        'field_label' => 'JIO SIM Installed',
        'field_type' => 'select',
        'placeholder' => 'Select Status',
        'is_required' => 1,
        'grid_width' => 6,
        'options_json' => json_encode([
            ['label' => 'Yes', 'value' => 'yes'],
            ['label' => 'No', 'value' => 'no'],
        ]),
    ],
    [
        'section_title' => 'JIO SIM Section',
        'field_key' => 'jio_sim_status',
        'field_label' => 'JIO SIM Status',
        'field_type' => 'select',
        'placeholder' => 'Select Working Status',
        'is_required' => 1,
        'grid_width' => 6,
        'options_json' => json_encode([
            ['label' => 'Working', 'value' => 'working'],
            ['label' => 'Not Working', 'value' => 'notWorking'],
        ]),
    ],
    [
        'section_title' => 'JIO SIM Section',
        'field_key' => 'jio_sim_remarks',
        'field_label' => 'JIO SIM Remarks',
        'field_type' => 'textarea',
        'placeholder' => 'JIO SIM signal & performance...',
        'is_required' => 0,
        'grid_width' => 12,
        'options_json' => null,
    ],
    [
        'section_title' => 'JIO SIM Section',
        'field_key' => 'jio_sim_snaps',
        'field_label' => 'JIO SIM Photos',
        'field_type' => 'file',
        'placeholder' => null,
        'is_required' => 0,
        'grid_width' => 6,
        'options_json' => null,
    ],
    [
        'section_title' => 'JIO SIM Section',
        'field_key' => 'jio_sim_status_remarks',
        'field_label' => 'JIO SIM Status Remarks',
        'field_type' => 'textarea',
        'placeholder' => 'Status notes...',
        'is_required' => 0,
        'grid_width' => 12,
        'options_json' => null,
    ],
    [
        'section_title' => 'JIO SIM Section',
        'field_key' => 'jio_sim_status_snaps',
        'field_label' => 'JIO SIM Status Photos',
        'field_type' => 'file',
        'placeholder' => null,
        'is_required' => 0,
        'grid_width' => 6,
        'options_json' => null,
    ],

    // Section 10: Verification & Sign-off
    [
        'section_title' => 'Verification & Sign-off',
        'field_key' => 'signature_image',
        'field_label' => 'Digital Signature',
        'field_type' => 'file',
        'placeholder' => null,
        'is_required' => 1,
        'grid_width' => 6,
        'options_json' => null,
    ],
    [
        'section_title' => 'Verification & Sign-off',
        'field_key' => 'vendor_stamp',
        'field_label' => 'Vendor Stamp Photo',
        'field_type' => 'file',
        'placeholder' => null,
        'is_required' => 0,
        'grid_width' => 6,
        'options_json' => null,
    ],
];

// 5. Insert fields into custom_form_fields
$insertFieldStmt = $db->prepare("INSERT INTO custom_form_fields 
    (form_id, section_title, field_key, field_label, field_type, placeholder, is_required, options_json, grid_width, sort_order, status, created_at, updated_at) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW(), NOW())");

$sort = 1;
foreach ($fields as $f) {
    $placeholder = $f['placeholder'] ?? null;
    $optJson = $f['options_json'] ?? null;
    $req = (int)$f['is_required'];
    $grid = (int)$f['grid_width'];
    
    $insertFieldStmt->bind_param(
        'isssssssii',
        $formId,
        $f['section_title'],
        $f['field_key'],
        $f['field_label'],
        $f['field_type'],
        $placeholder,
        $req,
        $optJson,
        $grid,
        $sort
    );
    $insertFieldStmt->execute();
    $sort++;
}

echo "Successfully seeded " . count($fields) . " installation fields for Form ID: {$formId}!\n";
