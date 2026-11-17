<?php
/**
 * Import Site Data for Project XTPL from Desktop/SITE DATA.xlsx
 * Date: 2026-11-13
 */

require_once __DIR__ . '/../config/autoload.php';
require_once __DIR__ . '/../utils/SimpleXLSX.php';

$filePath = 'C:/Users/clam35242/Desktop/SITE DATA.xlsx';

if (!file_exists($filePath)) {
    die("Error: File not found at $filePath\n");
}

$xlsx = SimpleXLSX::parse($filePath);
if (!$xlsx) {
    die("Error: Unable to parse Excel file: " . SimpleXLSX::parseError() . "\n");
}

$rows = array_slice($xlsx->rows(), 1);
echo "Loaded " . count($rows) . " site records from Excel...\n\n";

$bankNameMapping = [
    'The Faridkot Central Cooperative Bank Ltd., Faridkot' => 'The Faridkot Central Cooperative Bank Ltd., Faridkot',
    'The Beed District Central Co-operative Bank Ltd., Beed' => 'The Beed District Central Co-operative Bank Ltd., Beed',
    'PITHORAGARH ZILA SAHAKARI BANK LTD., PITHORAGARH' => 'Pithoragarh Zila Sahakari Bank Ltd., Pithoragarh',
    'The PITHORAGARH ZILA SAHKARI BANK LTD. PITHORAGARH' => 'Pithoragarh Zila Sahakari Bank Ltd., Pithoragarh',
    'The Jalandhar Central Cooperative Bank Ltd. Jalandhar' => 'The Jalandhar Central Cooperative Bank Ltd., Jalandhar',
    'The jalandhar central cooperative bank Ltd. Jalandhar' => 'The Jalandhar Central Cooperative Bank Ltd., Jalandhar',
    'Uttarakashi  Zila Sahakari Bank Ltd.' => 'Uttarkashi Zila Sahakari Bank Ltd., Uttarkashi',
    'The Uttarkashi Zila Sahkari Bank Ltd.' => 'Uttarkashi Zila Sahakari Bank Ltd., Uttarkashi',
    'NAINITAL DISTRICT COOPERATIVE BANK LTD' => 'Nainital District Cooperative Bank Ltd., Nainital',
    'ZILA SAHKARI BANK LTD. GARHWAL, KOTDWAR' => 'Zila Sahkari Bank Ltd., Garhwal, Kotdwar',
    'ZILA SAHKARI BANK LTD., GARHWAL, KOTDWAR' => 'Zila Sahkari Bank Ltd., Garhwal, Kotdwar',
    'ZILA SAHKARI BANK LTD.GARHWAL(KOTDWAR)' => 'Zila Sahkari Bank Ltd., Garhwal, Kotdwar',
    'THE HIMACHAL PRADESH STATE CO-OPERATIVE BANK LTD.' => 'The Himachal Pradesh State Co-operative Bank Ltd.',
    'Chamoli Zila Sahakari Bank Ltd.' => 'Chamoli Zila Sahakari Bank Ltd., Chamoli',
    'Chamoli Zila Sahkari Bank Ltd.' => 'Chamoli Zila Sahakari Bank Ltd., Chamoli',
    'The Amritsar Central Cooperative Bank Ltd., Amritsar' => 'The Amritsar Central Cooperative Bank Ltd., Amritsar',
    'The Gurdaspur Central Cooperative Bank Ltd., Gurdaspur' => 'The Gurdaspur Central Cooperative Bank Ltd., Gurdaspur',
    'The Hoshiarpur Central Cooperative Bank Ltd., Hoshiarpur' => 'The Hoshiarpur Central Cooperative Bank Ltd., Hoshiarpur',
    'The Mansa Central Cooperative Bank Ltd., Mansa' => 'The Mansa Central Cooperative Bank Ltd., Mansa',
    'Patiala central cooperative bank Ltd. Patiala' => 'The Patiala Central Cooperative Bank Ltd., Patiala',
    'Jogindra Central Cooperative Bank Ltd;' => 'Jogindra Central Cooperative Bank Ltd.',
    'Sikkim State Cooperative Bank' => 'Sikkim State Cooperative Bank Ltd.',
    'The Almora Zila Sahkari Bank Ltd. Almora' => 'The Almora Zila Sahkari Bank Ltd., Almora',
    'TEHRI GARHWAL ZILA SAHKARI BANK LTD.TEHRI' => 'Tehri Garhwal Zila Sahkari Bank Ltd., Tehri',
    'District Cooperative Bank Ltd. Dehradun' => 'District Cooperative Bank Ltd., Dehradun'
];

function resolveLocation($xtr, $rawBank, $branch, $address) {
    $search = strtolower($address . ' ' . $rawBank . ' ' . $branch);
    
    if ($xtr === 'XTPL0031') return ['city' => 'Bilaspur', 'state' => 'Himachal Pradesh'];
    if ($xtr === 'XTPL0040') return ['city' => 'Chamba', 'state' => 'Himachal Pradesh'];
    if ($xtr === 'XTPL0043') return ['city' => 'Kinnaur', 'state' => 'Himachal Pradesh'];
    if ($xtr === 'XTPL0044') return ['city' => 'Mandi', 'state' => 'Himachal Pradesh'];
    if ($xtr === 'XTPL0062') return ['city' => 'Sirmaur', 'state' => 'Himachal Pradesh'];
    if ($xtr === 'XTPL0065') return ['city' => 'West Sikkim', 'state' => 'Sikkim'];

    if (strpos($search, 'faridkot') !== false) return ['city' => 'Faridkot', 'state' => 'Punjab'];
    if (strpos($search, 'jalandhar') !== false || strpos($search, 'shahkot') !== false || strpos($search, 'lohian') !== false || strpos($search, 'phillaur') !== false || strpos($search, 'mansoorpur') !== false) return ['city' => 'Jalandhar', 'state' => 'Punjab'];
    if (strpos($search, 'amritsar') !== false || strpos($search, 'ajnala') !== false) return ['city' => 'Amritsar', 'state' => 'Punjab'];
    if (strpos($search, 'gurdaspur') !== false || strpos($search, 'batala') !== false) return ['city' => 'Gurdaspur', 'state' => 'Punjab'];
    if (strpos($search, 'hoshiarpur') !== false) return ['city' => 'Hoshiarpur', 'state' => 'Punjab'];
    if (strpos($search, 'mansa') !== false || strpos($search, 'boha') !== false) return ['city' => 'Mansa', 'state' => 'Punjab'];
    if (strpos($search, 'patiala') !== false || strpos($search, 'nabha') !== false) return ['city' => 'Patiala', 'state' => 'Punjab'];

    if (strpos($search, 'beed') !== false || strpos($search, 'ashti') !== false || strpos($search, 'ambajogai') !== false) return ['city' => 'Beed', 'state' => 'Maharashtra'];

    if (strpos($search, 'pithoragarh') !== false) return ['city' => 'Pithoragarh', 'state' => 'Uttarakhand'];
    if (strpos($search, 'almora') !== false) return ['city' => 'Almora', 'state' => 'Uttarakhand'];
    if (strpos($search, 'uttarkashi') !== false) return ['city' => 'Uttarkashi', 'state' => 'Uttarakhand'];
    if (strpos($search, 'nainital') !== false || strpos($search, 'dhari') !== false) return ['city' => 'Nainital', 'state' => 'Uttarakhand'];
    if (strpos($search, 'pauri') !== false || strpos($search, 'garhwal') !== false || strpos($search, 'kotdwar') !== false || strpos($search, 'rikhnikhal') !== false || strpos($search, 'tripalisain') !== false || strpos($search, 'chelusain') !== false || strpos($search, 'kandi') !== false) return ['city' => 'Pauri Garhwal', 'state' => 'Uttarakhand'];
    if (strpos($search, 'chamoli') !== false || strpos($search, 'dewal') !== false || strpos($search, 'deval') !== false) return ['city' => 'Chamoli', 'state' => 'Uttarakhand'];
    if (strpos($search, 'bageshwar') !== false) return ['city' => 'Bageshwar', 'state' => 'Uttarakhand'];
    if (strpos($search, 'tehri') !== false || strpos($search, 'srikot') !== false || strpos($search, 'garkhet') !== false) return ['city' => 'Tehri Garhwal', 'state' => 'Uttarakhand'];
    if (strpos($search, 'rudraprayag') !== false || strpos($search, 'basukedar') !== false) return ['city' => 'Rudraprayag', 'state' => 'Uttarakhand'];
    if (strpos($search, 'dehradun') !== false || strpos($search, 'kalsi') !== false) return ['city' => 'Dehradun', 'state' => 'Uttarakhand'];

    if (strpos($search, 'sirmour') !== false || strpos($search, 'sirmaur') !== false || strpos($search, 'shillai') !== false || strpos($search, 'rajgarh') !== false) return ['city' => 'Sirmaur', 'state' => 'Himachal Pradesh'];
    if (strpos($search, 'bilaspur') !== false || strpos($search, 'naina devi') !== false || strpos($search, 'ghumarwin') !== false || strpos($search, 'jhandutta') !== false) return ['city' => 'Bilaspur', 'state' => 'Himachal Pradesh'];
    if (strpos($search, 'chamba') !== false || strpos($search, 'bharmaur') !== false) return ['city' => 'Chamba', 'state' => 'Himachal Pradesh'];
    if (strpos($search, 'kinnaur') !== false || strpos($search, 'pooh') !== false) return ['city' => 'Kinnaur', 'state' => 'Himachal Pradesh'];
    if (strpos($search, 'solan') !== false || strpos($search, 'kandaghat') !== false) return ['city' => 'Solan', 'state' => 'Himachal Pradesh'];
    if (strpos($search, 'mandi') !== false || strpos($search, 'sundernagar') !== false || strpos($search, 'thunag') !== false) return ['city' => 'Mandi', 'state' => 'Himachal Pradesh'];
    if (strpos($search, 'shimla') !== false || strpos($search, 'chopal') !== false || strpos($search, 'rohru') !== false || strpos($search, 'theog') !== false || strpos($search, 'jubbal') !== false || strpos($search, 'kotkhai') !== false || strpos($search, 'tikkar') !== false || strpos($search, 'suni') !== false || strpos($search, 'junga') !== false || strpos($search, 'kupvi') !== false) return ['city' => 'Shimla', 'state' => 'Himachal Pradesh'];

    return null;
}

$db = DatabaseConfig::getInstance();
$conn = $db->getConnection();

$conn->begin_transaction();

$imported = 0;
$stmt = $conn->prepare("INSERT INTO `sites` 
    (`site_name`, `project_id`, `bank_name`, `customer_name`, `city`, `state`, `country`, `address`, `custom_fields_json`, `company_id`, `status`, `created_by`, `created_at`) 
    VALUES (?, 1, ?, 'Hitachi', ?, ?, 'India', ?, ?, 1, 'active', 2326, NOW())");

if (!$stmt) {
    die("Prepare failed: " . $conn->error . "\n");
}

foreach ($rows as $idx => $r) {
    $xtr = trim($r[1] ?? '');
    $rawBank = trim($r[2] ?? '');
    $branch = trim($r[3] ?? '');
    $addr = trim($r[4] ?? '');
    $bid = trim($r[5] ?? '');

    $rawBankClean = trim(str_replace("\xC2\xA0", " ", $rawBank));
    $stdBank = $bankNameMapping[$rawBankClean] ?? ($bankNameMapping[$rawBank] ?? $rawBank);
    $loc = resolveLocation($xtr, $rawBank, $branch, $addr);

    $city = $loc['city'] ?? 'Other';
    $state = $loc['state'] ?? 'Other';

    $customFields = [
        'form_id' => 1,
        'form_name' => 'XTPL Site Addition Form',
        'form_code' => 'FORM_SITE_ADD_XTPL_01',
        'fields' => [
            'xtranet_id' => [
                'label' => 'Xtranet ID',
                'type' => 'text',
                'value' => $xtr,
                'section' => 'General Information'
            ],
            'bank_name' => [
                'label' => 'Bank Name',
                'type' => 'select',
                'value' => $stdBank,
                'section' => 'Bank Information'
            ],
            'bank_manager' => [
                'label' => 'Bank Manager',
                'type' => 'text',
                'value' => '',
                'section' => 'Bank Information'
            ],
            'contact_number' => [
                'label' => 'Contact Number',
                'type' => 'phone',
                'value' => [],
                'section' => 'Bank Information'
            ],
            'bank_branch_id' => [
                'label' => 'Bank Branch ID',
                'type' => 'text',
                'value' => $bid,
                'section' => 'Bank Information'
            ],
            'branch_name' => [
                'label' => 'Branch Name',
                'type' => 'text',
                'value' => $branch,
                'section' => 'Bank Information'
            ],
            'branch_address' => [
                'label' => 'Branch Address',
                'type' => 'textarea',
                'value' => $addr,
                'section' => 'Bank Information'
            ]
        ]
    ];

    $customJson = json_encode($customFields, JSON_UNESCAPED_UNICODE);

    $stmt->bind_param("ssssss", $xtr, $stdBank, $city, $state, $addr, $customJson);

    if ($stmt->execute()) {
        $imported++;
        $newId = $stmt->insert_id;
        echo sprintf("[%02d/79] ✔ Inserted ID %d: %s | %s | Branch: %s (ID: %s) | %s, %s\n", 
            $imported, $newId, $xtr, $stdBank, $branch, $bid, $city, $state);
    } else {
        $conn->rollback();
        die(sprintf("✖ Insert failed at row %d (%s): %s\n", $idx + 2, $xtr, $stmt->error));
    }
}

$stmt->close();
$conn->commit();

echo "\n=======================================================\n";
echo "SUCCESS: All {$imported} sites imported into Project XTPL!\n";

