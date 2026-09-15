<?php
require_once __DIR__ . '/../config/autoload.php';

$db = DatabaseConfig::getInstance();
$conn = $db->getConnection();

// 1. Ensure clean field_key for Bank Name in custom_form_fields
$conn->query("UPDATE custom_form_fields SET field_key = 'bank_name' WHERE form_id = 1 AND field_label = 'Bank Name'");

// 2. Load JSON data
$jsonFile = __DIR__ . '/master_sites_data.json';
if (!file_exists($jsonFile)) {
    die("JSON data file not found: {$jsonFile}\n");
}
$rawJson = file_get_contents($jsonFile);
$rawJson = preg_replace('/^\xEF\xBB\xBF/', '', $rawJson);
$rows = json_decode($rawJson, true);
if (!$rows || !is_array($rows)) {
    die("Failed to decode JSON data. JSON error: " . json_last_error_msg() . "\n");
}

echo "Loaded " . count($rows) . " site records to import...\n";

// Fetch known Banks for matching
$bankRes = $conn->query("SELECT name FROM banks");
$knownBanks = [];
while ($bRow = $bankRes->fetch_assoc()) {
    $knownBanks[] = $bRow['name'];
}

function matchBankName($rawName, $knownBanks) {
    $trimmed = trim($rawName);
    foreach ($knownBanks as $kb) {
        if (strcasecmp($kb, $trimmed) === 0) {
            return $kb;
        }
    }
    // Fuzzy matching
    $bestMatch = $trimmed;
    $highestSim = 0;
    foreach ($knownBanks as $kb) {
        similar_text(strtolower($kb), strtolower($trimmed), $percent);
        if ($percent > $highestSim && $percent > 65) {
            $highestSim = $percent;
            $bestMatch = $kb;
        }
    }
    return $bestMatch;
}

function extractPhoneNumbers($rawStr) {
    if (empty($rawStr)) return [];
    $parts = preg_split('/[\/,\n|]+/', $rawStr);
    $numbers = [];
    foreach ($parts as $p) {
        $clean = preg_replace('/[^0-9]/', '', $p);
        if (strlen($clean) >= 10) {
            $numbers[] = substr($clean, -10);
        } elseif (strlen($clean) > 0) {
            $numbers[] = $clean;
        }
    }
    return array_values(array_unique($numbers));
}

function deriveCity($districtOrAddress, $state) {
    $str = $districtOrAddress;
    $candidates = [
        'Faridkot', 'Beed', 'Pithoragarh', 'Almora', 'Uttarkashi', 
        'Nainital', 'Pauri Garhwal', 'Chamoli', 'Amritsar', 
        'Gurdaspur', 'Hoshiarpur', 'Mansa', 'Patiala', 'Jalandhar',
        'Ashti', 'Ambajogai', 'Kotdwar', 'Ajnala', 'Batala', 'Phillaur'
    ];
    foreach ($candidates as $c) {
        if (stripos($str, $c) !== false) {
            return $c;
        }
    }
    return !empty($state) ? $state : 'Other';
}

$imported = 0;

foreach ($rows as $item) {
    $xtranetId = trim($item['xtranet_id'] ?? '');
    $rawBankName = trim($item['bank_name'] ?? '');
    $bankManager = trim($item['bank_manager'] ?? '');
    $rawContact = trim($item['contact_number'] ?? '');
    $branchId = trim($item['branch_id'] ?? '');
    $branchName = trim($item['branch_name'] ?? '');
    $branchAddress = trim($item['branch_address'] ?? '');
    $state = trim($item['state'] ?? '');
    $country = trim($item['country'] ?? '') ?: 'India';

    if (empty($xtranetId) && empty($rawBankName)) {
        continue;
    }

    $bankName = matchBankName($rawBankName, $knownBanks);
    $phoneList = extractPhoneNumbers($rawContact);
    $city = deriveCity($branchAddress . ' ' . $rawBankName . ' ' . $branchName, $state);

    $customFields = [
        'form_id' => 1,
        'form_name' => 'XTPL Site Addition Form',
        'form_code' => 'FORM_SITE_ADD_XTPL_01',
        'fields' => [
            'xtranet_id' => [
                'label' => 'Xtranet ID',
                'type' => 'text',
                'value' => $xtranetId,
                'section' => 'General Information'
            ],
            'bank_name' => [
                'label' => 'Bank Name',
                'type' => 'select',
                'value' => $bankName,
                'section' => 'Bank Information'
            ],
            'bank_manager' => [
                'label' => 'Bank Manager',
                'type' => 'text',
                'value' => $bankManager,
                'section' => 'Bank Information'
            ],
            'contact_number' => [
                'label' => 'Contact Number',
                'type' => 'phone',
                'value' => $phoneList,
                'section' => 'Bank Information'
            ],
            'bank_branch_id' => [
                'label' => 'Bank Branch ID',
                'type' => 'text',
                'value' => $branchId,
                'section' => 'Bank Information'
            ],
            'branch_name' => [
                'label' => 'Branch Name',
                'type' => 'text',
                'value' => $branchName,
                'section' => 'Bank Information'
            ],
            'branch_address' => [
                'label' => 'Branch Address',
                'type' => 'textarea',
                'value' => $branchAddress,
                'section' => 'Bank Information'
            ]
        ]
    ];

    $customJson = json_encode($customFields, JSON_UNESCAPED_UNICODE);

    $stmt = $conn->prepare("INSERT INTO `sites` 
        (`site_name`, `project_id`, `bank_name`, `customer_name`, `city`, `state`, `country`, `address`, `custom_fields_json`, `company_id`, `status`, `created_by`) 
        VALUES (?, 1, ?, 'Hitachi', ?, ?, ?, ?, ?, 1, 'active', 2326)");
    
    $siteName = $xtranetId;
    $stmt->bind_param("sssssss", $siteName, $bankName, $city, $state, $country, $branchAddress, $customJson);
    
    if ($stmt->execute()) {
        $newId = $stmt->insert_id;
        $imported++;
        echo "✔ [{$imported}] Inserted ID: {$newId} | {$siteName} | {$bankName} | {$city}, {$state} | Phones: " . implode(', ', $phoneList) . "\n";
    } else {
        echo "✖ Failed to insert {$siteName}: " . $stmt->error . "\n";
    }
    $stmt->close();
}

echo "\n=========================================\n";
echo "Successfully uploaded {$imported} site records into 'sites' table!\n";
