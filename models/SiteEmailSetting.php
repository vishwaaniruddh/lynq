<?php
/**
 * SiteEmailSetting Model
 * Manages email recipient configurations (TO, CC) across:
 * 1) Individual sites
 * 2) Sites by state
 * 3) Sites by cities
 * 4) Sites by zone
 */

require_once __DIR__ . '/BaseModel.php';
require_once __DIR__ . '/../utils/LocationParser.php';

class SiteEmailSetting extends BaseModel {
    protected $table = 'site_email_settings';
    protected $primaryKey = 'id';
    protected $fillable = [
        'type',
        'target_id',
        'target_name',
        'to_emails',
        'cc_emails',
        'status',
        'remarks',
        'created_by',
        'updated_by'
    ];

    /**
     * Parse and clean a comma / newline / space-separated list of emails
     */
    public static function parseEmails($emailStr): array {
        if (empty($emailStr)) {
            return [];
        }

        // Replace newlines, semicolons, tabs with commas
        $normalized = str_replace(["\r\n", "\r", "\n", ";", "\t", " "], ',', trim($emailStr));
        $rawList = explode(',', $normalized);
        $clean = [];

        foreach ($rawList as $item) {
            $item = trim($item);
            if (!empty($item) && filter_var($item, FILTER_VALIDATE_EMAIL)) {
                $itemLower = strtolower($item);
                if (!in_array($itemLower, $clean, true)) {
                    $clean[] = $itemLower;
                }
            }
        }

        return $clean;
    }

    /**
     * Validate an email list string. Returns array of invalid email strings if any.
     */
    public static function findInvalidEmails($emailStr): array {
        if (empty($emailStr)) {
            return [];
        }

        $normalized = str_replace(["\r\n", "\r", "\n", ";", "\t", " "], ',', trim($emailStr));
        $rawList = explode(',', $normalized);
        $invalid = [];

        foreach ($rawList as $item) {
            $item = trim($item);
            if (!empty($item) && !filter_var($item, FILTER_VALIDATE_EMAIL)) {
                $invalid[] = $item;
            }
        }

        return $invalid;
    }

    /**
     * Normalize emails to comma-separated string
     */
    public static function formatEmailsForStorage($emailStr): string {
        $parsed = self::parseEmails($emailStr);
        return implode(', ', $parsed);
    }

    /**
     * Get records by entity type ('site', 'state', 'city', 'zone')
     */
    public function getByType($type) {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT * FROM `{$this->table}` WHERE `type` = ? ORDER BY `target_name` ASC");
        $stmt->execute([$type]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get counts for each type
     */
    public function getCounts(): array {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->query("
            SELECT `type`, COUNT(*) as total,
                   SUM(CASE WHEN `status` = 'active' THEN 1 ELSE 0 END) as active_count
            FROM `{$this->table}`
            GROUP BY `type`
        ");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $counts = [
            'site' => ['total' => 0, 'active' => 0],
            'state' => ['total' => 0, 'active' => 0],
            'city' => ['total' => 0, 'active' => 0],
            'zone' => ['total' => 0, 'active' => 0],
            'all' => ['total' => 0, 'active' => 0]
        ];

        foreach ($rows as $row) {
            $t = $row['type'];
            if (isset($counts[$t])) {
                $counts[$t]['total'] = (int)$row['total'];
                $counts[$t]['active'] = (int)$row['active_count'];
                $counts['all']['total'] += (int)$row['total'];
                $counts['all']['active'] += (int)$row['active_count'];
            }
        }

        return $counts;
    }

    /**
     * Find existing setting for type and target_name / target_id
     */
    public function findExisting($type, $targetName, $targetId = null, $excludeId = null) {
        $db = Database::getInstance()->getConnection();
        $sql = "SELECT * FROM `{$this->table}` WHERE `type` = ? AND (LOWER(TRIM(`target_name`)) = LOWER(TRIM(?))";
        $params = [$type, $targetName];

        if ($targetId) {
            $sql .= " OR `target_id` = ?";
            $params[] = $targetId;
        }
        $sql .= ")";

        if ($excludeId) {
            $sql .= " AND `id` != ?";
            $params[] = $excludeId;
        }

        $sql .= " LIMIT 1";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Create new email setting
     */
    public function createSetting(array $data) {
        $db = Database::getInstance()->getConnection();
        
        $type = trim($data['type'] ?? '');
        $targetName = trim($data['target_name'] ?? '');
        $targetId = !empty($data['target_id']) ? (int)$data['target_id'] : null;
        $toEmails = self::formatEmailsForStorage($data['to_emails'] ?? '');
        $ccEmails = self::formatEmailsForStorage($data['cc_emails'] ?? '');
        $status = in_array($data['status'] ?? '', ['active', 'inactive'], true) ? $data['status'] : 'active';
        $remarks = trim($data['remarks'] ?? '');
        $createdBy = !empty($data['created_by']) ? (int)$data['created_by'] : null;

        if (empty($type) || !in_array($type, ['site', 'state', 'city', 'zone'], true)) {
            throw new InvalidArgumentException("Invalid configuration type. Must be site, state, city, or zone.");
        }

        if (empty($targetName)) {
            throw new InvalidArgumentException("Target name cannot be empty.");
        }

        if (empty($toEmails)) {
            throw new InvalidArgumentException("At least one valid 'TO' email address is required.");
        }

        $stmt = $db->prepare("
            INSERT INTO `{$this->table}` 
            (`type`, `target_id`, `target_name`, `to_emails`, `cc_emails`, `status`, `remarks`, `created_by`, `updated_by`)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $type,
            $targetId,
            $targetName,
            $toEmails,
            $ccEmails,
            $status,
            $remarks,
            $createdBy,
            $createdBy
        ]);

        return (int)$db->lastInsertId();
    }

    /**
     * Update existing email setting
     */
    public function updateSetting($id, array $data) {
        $db = Database::getInstance()->getConnection();
        
        $type = trim($data['type'] ?? '');
        $targetName = trim($data['target_name'] ?? '');
        $targetId = !empty($data['target_id']) ? (int)$data['target_id'] : null;
        $toEmails = self::formatEmailsForStorage($data['to_emails'] ?? '');
        $ccEmails = self::formatEmailsForStorage($data['cc_emails'] ?? '');
        $status = in_array($data['status'] ?? '', ['active', 'inactive'], true) ? $data['status'] : 'active';
        $remarks = trim($data['remarks'] ?? '');
        $updatedBy = !empty($data['updated_by']) ? (int)$data['updated_by'] : null;

        if (empty($type) || !in_array($type, ['site', 'state', 'city', 'zone'], true)) {
            throw new InvalidArgumentException("Invalid configuration type.");
        }

        if (empty($targetName)) {
            throw new InvalidArgumentException("Target name cannot be empty.");
        }

        if (empty($toEmails)) {
            throw new InvalidArgumentException("At least one valid 'TO' email address is required.");
        }

        $stmt = $db->prepare("
            UPDATE `{$this->table}` 
            SET `type` = ?, `target_id` = ?, `target_name` = ?, 
                `to_emails` = ?, `cc_emails` = ?, `status` = ?, 
                `remarks` = ?, `updated_by` = ?, `updated_at` = CURRENT_TIMESTAMP
            WHERE `id` = ?
        ");

        return $stmt->execute([
            $type,
            $targetId,
            $targetName,
            $toEmails,
            $ccEmails,
            $status,
            $remarks,
            $updatedBy,
            $id
        ]);
    }

    /**
     * Delete email setting
     */
    public function deleteSetting($id): bool {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("DELETE FROM `{$this->table}` WHERE `id` = ?");
        return $stmt->execute([(int)$id]);
    }

    /**
     * Toggle active/inactive status
     */
    public function toggleStatus($id): string {
        $db = Database::getInstance()->getConnection();
        $setting = $this->find($id);
        if (!$setting) {
            throw new Exception("Email setting not found.");
        }

        $newStatus = ($setting['status'] === 'active') ? 'inactive' : 'active';
        $stmt = $db->prepare("UPDATE `{$this->table}` SET `status` = ?, `updated_at` = CURRENT_TIMESTAMP WHERE `id` = ?");
        $stmt->execute([$newStatus, $id]);

        return $newStatus;
    }

    /**
     * Resolve effective email recipients for a given site:
     * Combines:
     * 1) Individual Site TO/CC
     * 2) City TO/CC
     * 3) State TO/CC
     * 4) Zone TO/CC
     */
    public function getEffectiveEmailsForSite($siteIdentifier): array {
        $db = Database::getInstance()->getConnection();

        // 1. Fetch site
        if (is_numeric($siteIdentifier)) {
            $stmt = $db->prepare("SELECT * FROM sites WHERE id = ? LIMIT 1");
            $stmt->execute([(int)$siteIdentifier]);
        } else {
            $stmt = $db->prepare("SELECT * FROM sites WHERE site_name = ? LIMIT 1");
            $stmt->execute([trim($siteIdentifier)]);
        }
        $site = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$site) {
            return [
                'found' => false,
                'error' => 'Site not found.'
            ];
        }

        $siteId = $site['id'];
        $siteName = $site['site_name'];
        $city = trim($site['city'] ?? '');
        $state = trim($site['state'] ?? '');
        $zone = trim($site['zone'] ?? '');
        $address = trim($site['address'] ?? '');

        // If city, state, or zone is missing, parse/extract from address/location
        if ((empty($city) || empty($state) || empty($zone)) && !empty($address)) {
            $parsed = LocationParser::parseFromAddress($address, $state, $city);
            if (empty($city) && !empty($parsed['city'])) $city = $parsed['city'];
            if (empty($state) && !empty($parsed['state'])) $state = $parsed['state'];
            if (empty($zone) && !empty($parsed['zone'])) $zone = $parsed['zone'];
        }

        // If zone is still empty in sites table, resolve from states -> zones
        if (empty($zone) && !empty($state)) {
            $stmtZone = $db->prepare("
                SELECT z.name as zone_name 
                FROM states s 
                LEFT JOIN zones z ON s.zone_id = z.id 
                WHERE LOWER(TRIM(s.name)) = LOWER(TRIM(?))
                LIMIT 1
            ");
            $stmtZone->execute([$state]);
            $zoneRow = $stmtZone->fetch(PDO::FETCH_ASSOC);
            if ($zoneRow && !empty($zoneRow['zone_name'])) {
                $zone = $zoneRow['zone_name'];
            }
        }

        // 2. Fetch all matching active settings
        // Level 1: Site
        $stmtSite = $db->prepare("
            SELECT * FROM `{$this->table}` 
            WHERE `type` = 'site' AND `status` = 'active' 
              AND (`target_id` = ? OR LOWER(TRIM(`target_name`)) = LOWER(TRIM(?)))
        ");
        $stmtSite->execute([$siteId, $siteName]);
        $siteSettings = $stmtSite->fetchAll(PDO::FETCH_ASSOC);

        // Level 2: City
        $citySettings = [];
        if (!empty($city)) {
            $stmtCity = $db->prepare("
                SELECT * FROM `{$this->table}` 
                WHERE `type` = 'city' AND `status` = 'active' 
                  AND LOWER(TRIM(`target_name`)) = LOWER(TRIM(?))
            ");
            $stmtCity->execute([$city]);
            $citySettings = $stmtCity->fetchAll(PDO::FETCH_ASSOC);
        }

        // Level 3: State
        $stateSettings = [];
        if (!empty($state)) {
            $stmtState = $db->prepare("
                SELECT * FROM `{$this->table}` 
                WHERE `type` = 'state' AND `status` = 'active' 
                  AND LOWER(TRIM(`target_name`)) = LOWER(TRIM(?))
            ");
            $stmtState->execute([$state]);
            $stateSettings = $stmtState->fetchAll(PDO::FETCH_ASSOC);
        }

        // Level 4: Zone
        $zoneSettings = [];
        if (!empty($zone)) {
            $stmtZoneSetting = $db->prepare("
                SELECT * FROM `{$this->table}` 
                WHERE `type` = 'zone' AND `status` = 'active' 
                  AND LOWER(TRIM(`target_name`)) = LOWER(TRIM(?))
            ");
            $stmtZoneSetting->execute([$zone]);
            $zoneSettings = $stmtZoneSetting->fetchAll(PDO::FETCH_ASSOC);
        }

        // Aggregate
        $extractEmails = function($settingsList) {
            $to = [];
            $cc = [];
            foreach ($settingsList as $row) {
                $to = array_merge($to, SiteEmailSetting::parseEmails($row['to_emails']));
                $cc = array_merge($cc, SiteEmailSetting::parseEmails($row['cc_emails']));
            }
            return [
                'to' => array_values(array_unique($to)),
                'cc' => array_values(array_unique($cc))
            ];
        };

        $siteEmails = $extractEmails($siteSettings);
        $cityEmails = $extractEmails($citySettings);
        $stateEmails = $extractEmails($stateSettings);
        $zoneEmails = $extractEmails($zoneSettings);

        $combinedTo = array_values(array_unique(array_merge(
            $siteEmails['to'],
            $cityEmails['to'],
            $stateEmails['to'],
            $zoneEmails['to']
        )));

        $combinedCc = array_values(array_unique(array_merge(
            $siteEmails['cc'],
            $cityEmails['cc'],
            $stateEmails['cc'],
            $zoneEmails['cc']
        )));

        // Remove any emails from CC that are already in TO
        $combinedCc = array_values(array_diff($combinedCc, $combinedTo));

        // Highest priority active rule according to weight (site > city > state > zone)
        $highestPriorityLevel = null;
        $overrideTo = [];
        $overrideCc = [];
        if (!empty($siteEmails['to'])) {
            $highestPriorityLevel = 'site';
            $overrideTo = $siteEmails['to'];
            $overrideCc = $siteEmails['cc'];
        } elseif (!empty($cityEmails['to'])) {
            $highestPriorityLevel = 'city';
            $overrideTo = $cityEmails['to'];
            $overrideCc = $cityEmails['cc'];
        } elseif (!empty($stateEmails['to'])) {
            $highestPriorityLevel = 'state';
            $overrideTo = $stateEmails['to'];
            $overrideCc = $stateEmails['cc'];
        } elseif (!empty($zoneEmails['to'])) {
            $highestPriorityLevel = 'zone';
            $overrideTo = $zoneEmails['to'];
            $overrideCc = $zoneEmails['cc'];
        }

        return [
            'found' => true,
            'site' => [
                'id' => $site['id'],
                'site_name' => $site['site_name'],
                'city' => $city,
                'state' => $state,
                'zone' => $zone,
                'bank_name' => $site['bank_name'] ?? '',
                'customer_name' => $site['customer_name'] ?? ''
            ],
            'highest_priority_level' => $highestPriorityLevel,
            'override_recipients' => [
                'level' => $highestPriorityLevel,
                'to' => $overrideTo,
                'cc' => $overrideCc,
                'to_string' => implode(', ', $overrideTo),
                'cc_string' => implode(', ', $overrideCc)
            ],
            'levels' => [
                'site' => [
                    'label' => 'Individual Site',
                    'weight' => 1,
                    'weight_label' => 'Weight 1 (Highest)',
                    'target' => $siteName,
                    'configured' => !empty($siteSettings),
                    'to' => $siteEmails['to'],
                    'cc' => $siteEmails['cc']
                ],
                'city' => [
                    'label' => 'City',
                    'weight' => 2,
                    'weight_label' => 'Weight 2',
                    'target' => $city,
                    'configured' => !empty($citySettings),
                    'to' => $cityEmails['to'],
                    'cc' => $cityEmails['cc']
                ],
                'state' => [
                    'label' => 'State',
                    'weight' => 3,
                    'weight_label' => 'Weight 3',
                    'target' => $state,
                    'configured' => !empty($stateSettings),
                    'to' => $stateEmails['to'],
                    'cc' => $stateEmails['cc']
                ],
                'zone' => [
                    'label' => 'Zone',
                    'weight' => 4,
                    'weight_label' => 'Weight 4 (Broadest)',
                    'target' => $zone,
                    'configured' => !empty($zoneSettings),
                    'to' => $zoneEmails['to'],
                    'cc' => $zoneEmails['cc']
                ]
            ],
            'effective_recipients' => [
                'to' => $combinedTo,
                'cc' => $combinedCc,
                'to_string' => implode(', ', $combinedTo),
                'cc_string' => implode(', ', $combinedCc)
            ]
        ];
    }
}
