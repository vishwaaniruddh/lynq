<?php
/**
 * LocationParser Utility
 * Parses and extracts city, state, and zone from raw address/location strings.
 */

class LocationParser {
    private static $stateZoneMap = [
        'himachal pradesh' => ['state' => 'Himachal Pradesh', 'zone' => 'North'],
        'punjab' => ['state' => 'Punjab', 'zone' => 'North'],
        'haryana' => ['state' => 'Haryana', 'zone' => 'North'],
        'rajasthan' => ['state' => 'Rajasthan', 'zone' => 'North'],
        'uttar pradesh' => ['state' => 'Uttar Pradesh', 'zone' => 'North'],
        'uttarakhand' => ['state' => 'Uttarakhand', 'zone' => 'North'],
        'uttaranchal' => ['state' => 'Uttarakhand', 'zone' => 'North'],
        'jammu and kashmir' => ['state' => 'Jammu and Kashmir', 'zone' => 'North'],
        'jammu' => ['state' => 'Jammu and Kashmir', 'zone' => 'North'],
        'kashmir' => ['state' => 'Jammu and Kashmir', 'zone' => 'North'],
        'ladakh' => ['state' => 'Ladakh', 'zone' => 'North'],
        'chandigarh' => ['state' => 'Chandigarh', 'zone' => 'North'],
        'delhi' => ['state' => 'Delhi', 'zone' => 'North'],
        'maharashtra' => ['state' => 'Maharashtra', 'zone' => 'West'],
        'gujarat' => ['state' => 'Gujarat', 'zone' => 'West'],
        'goa' => ['state' => 'Goa', 'zone' => 'West'],
        'dadra and nagar haveli' => ['state' => 'Dadra and Nagar Haveli and Daman and Diu', 'zone' => 'West'],
        'daman and diu' => ['state' => 'Dadra and Nagar Haveli and Daman and Diu', 'zone' => 'West'],
        'andhra pradesh' => ['state' => 'Andhra Pradesh', 'zone' => 'South'],
        'karnataka' => ['state' => 'Karnataka', 'zone' => 'South'],
        'kerala' => ['state' => 'Kerala', 'zone' => 'South'],
        'tamil nadu' => ['state' => 'Tamil Nadu', 'zone' => 'South'],
        'telangana' => ['state' => 'Telangana', 'zone' => 'South'],
        'puducherry' => ['state' => 'Puducherry', 'zone' => 'South'],
        'pondicherry' => ['state' => 'Puducherry', 'zone' => 'South'],
        'lakshadweep' => ['state' => 'Lakshadweep', 'zone' => 'South'],
        'andaman and nicobar' => ['state' => 'Andaman and Nicobar Islands', 'zone' => 'South'],
        'bihar' => ['state' => 'Bihar', 'zone' => 'East'],
        'jharkhand' => ['state' => 'Jharkhand', 'zone' => 'East'],
        'odisha' => ['state' => 'Odisha', 'zone' => 'East'],
        'orissa' => ['state' => 'Odisha', 'zone' => 'East'],
        'west bengal' => ['state' => 'West Bengal', 'zone' => 'East'],
        'madhya pradesh' => ['state' => 'Madhya Pradesh', 'zone' => 'Central'],
        'chhattisgarh' => ['state' => 'Chhattisgarh', 'zone' => 'Central'],
        'sikkim' => ['state' => 'Sikkim', 'zone' => 'Northeast'],
        'assam' => ['state' => 'Assam', 'zone' => 'Northeast'],
        'arunachal pradesh' => ['state' => 'Arunachal Pradesh', 'zone' => 'Northeast'],
        'manipur' => ['state' => 'Manipur', 'zone' => 'Northeast'],
        'meghalaya' => ['state' => 'Meghalaya', 'zone' => 'Northeast'],
        'mizoram' => ['state' => 'Mizoram', 'zone' => 'Northeast'],
        'nagaland' => ['state' => 'Nagaland', 'zone' => 'Northeast'],
        'tripura' => ['state' => 'Tripura', 'zone' => 'Northeast']
    ];

    /**
     * Resolve missing city, state, zone from an address/location string
     */
    public static function parseFromAddress($address, $existingState = null, $existingCity = null): array {
        $city = trim($existingCity ?? '');
        $state = trim($existingState ?? '');
        $zone = '';
        $addr = trim($address ?? '');

        // 1. Resolve State & Zone from existing state if valid
        if (!empty($state)) {
            $stLower = strtolower($state);
            if (isset(self::$stateZoneMap[$stLower])) {
                $state = self::$stateZoneMap[$stLower]['state'];
                $zone = self::$stateZoneMap[$stLower]['zone'];
            }
        }

        // 2. If state or zone is missing, scan the address string
        if ((empty($state) || empty($zone)) && !empty($addr)) {
            $addrLower = strtolower($addr);

            // Look for state names in address
            foreach (self::$stateZoneMap as $key => $info) {
                // Whole word boundary match
                if (preg_match('/\b' . preg_quote($key, '/') . '\b/i', $addrLower)) {
                    if (empty($state)) $state = $info['state'];
                    if (empty($zone)) $zone = $info['zone'];
                    break;
                }
            }

            // Check common abbreviations like H.P., HP
            if (empty($state) || empty($zone)) {
                if (preg_match('/\b(H\.?P\.?|HIMACHAL)\b/i', $addr)) {
                    $state = 'Himachal Pradesh';
                    $zone = 'North';
                } elseif (preg_match('/\b(U\.?K\.?|UTTARAKHAND)\b/i', $addr)) {
                    $state = 'Uttarakhand';
                    $zone = 'North';
                } elseif (preg_match('/\b(M\.?H\.?|MAHARASHTRA)\b/i', $addr)) {
                    $state = 'Maharashtra';
                    $zone = 'West';
                } elseif (preg_match('/\b(P\.?B\.?|PUNJAB)\b/i', $addr)) {
                    $state = 'Punjab';
                    $zone = 'North';
                }
            }

            // Check PIN code ranges
            if ((empty($state) || empty($zone)) && preg_match('/\b([1-8][0-9]{5})\b/', $addr, $pinMatches)) {
                $pin = (int)$pinMatches[1];
                if ($pin >= 140001 && $pin <= 160000) {
                    if (empty($state)) $state = 'Punjab';
                    if (empty($zone)) $zone = 'North';
                } elseif ($pin >= 171000 && $pin <= 177999) {
                    if (empty($state)) $state = 'Himachal Pradesh';
                    if (empty($zone)) $zone = 'North';
                } elseif ($pin >= 246000 && $pin <= 263999) {
                    if (empty($state)) $state = 'Uttarakhand';
                    if (empty($zone)) $zone = 'North';
                } elseif ($pin >= 400000 && $pin <= 445000) {
                    if (empty($state)) $state = 'Maharashtra';
                    if (empty($zone)) $zone = 'West';
                } elseif ($pin >= 737000 && $pin <= 737999) {
                    if (empty($state)) $state = 'Sikkim';
                    if (empty($zone)) $zone = 'Northeast';
                }
            }
        }

        // 3. If city is missing, extract from address
        if (empty($city) && !empty($addr)) {
            // Check patterns like District ..., Distt ..., TQ ..., Tehsil ...
            if (preg_match('/(?:District|Distt\.?|Dist\.?)\s*[:\-]?\s*([A-Za-z]+)/i', $addr, $m)) {
                $city = trim($m[1]);
            } elseif (preg_match('/(?:TQ\.?|Tehsil)\s*[:\-]?\s*([A-Za-z]+)/i', $addr, $m)) {
                $city = trim($m[1]);
            } elseif (preg_match('/\(([A-Za-z\s]+)\)/', $addr, $m)) {
                $cand = trim($m[1]);
                if (strlen($cand) < 25 && !preg_match('/road|branch|near|post/i', $cand)) {
                    $city = $cand;
                }
            }

            // Fallback: Check comma-separated tokens against database or token right before state/pin
            if (empty($city)) {
                $tokens = array_map('trim', preg_split('/[,;\-]+/', $addr));
                $tokens = array_filter($tokens, function($t) {
                    return !empty($t) && !preg_match('/^\d+$/', $t) && strlen($t) > 2;
                });
                
                // Try matching tokens in cities table
                try {
                    $db = Database::getInstance()->getConnection();
                    foreach (array_reverse($tokens) as $token) {
                        $cleaned = trim(preg_replace('/\b(near|opp|opposite|market|road|po|p\.o\.|post office|block|vpo)\b/i', '', $token));
                        if (strlen($cleaned) < 3) continue;
                        $stmt = $db->prepare("SELECT name FROM cities WHERE LOWER(TRIM(name)) = LOWER(TRIM(?)) LIMIT 1");
                        $stmt->execute([$cleaned]);
                        if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                            $city = $row['name'];
                            break;
                        }
                    }
                } catch (Exception $e) {}

                // If still empty and we have tokens, pick the one preceding state/pin if reasonable
                if (empty($city) && count($tokens) >= 2) {
                    $tokenList = array_values($tokens);
                    for ($i = count($tokenList) - 1; $i >= 0; $i--) {
                        $candidate = trim(preg_replace('/\b(pin|pin code|district|distt|uttarakhand|punjab|sikkim|maharashtra|\d{6})\b/i', '', $tokenList[$i]));
                        if (strlen($candidate) >= 3 && !preg_match('/\b(near|road|station|post|at|vpo)\b/i', $candidate)) {
                            $city = ucwords(strtolower($candidate));
                            break;
                        }
                    }
                }
            }
        }

        // 4. If city is found but state/zone still missing, check database cities
        if (!empty($city) && (empty($state) || empty($zone))) {
            try {
                $db = Database::getInstance()->getConnection();
                $stmt = $db->prepare("
                    SELECT c.name as city_name, s.name as state_name, z.name as zone_name
                    FROM cities c
                    JOIN states s ON c.state_id = s.id
                    LEFT JOIN zones z ON s.zone_id = z.id
                    WHERE LOWER(TRIM(c.name)) = LOWER(TRIM(?))
                    LIMIT 1
                ");
                $stmt->execute([$city]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row) {
                    if (empty($state)) $state = $row['state_name'];
                    if (empty($zone)) $zone = $row['zone_name'] ?? '';
                }
            } catch (Exception $e) {}
        }

        return [
            'city' => $city,
            'state' => $state,
            'zone' => $zone
        ];
    }

    /**
     * Alias for parseFromAddress
     */
    public static function parse($address, $existingState = null, $existingCity = null): array {
        return self::parseFromAddress($address, $existingState, $existingCity);
    }

    /**
     * Lookup zone from state name
     */
    public static function getZoneForState($state): ?string {
        if (empty($state)) return null;
        $stLower = strtolower(trim($state));
        return self::$stateZoneMap[$stLower]['zone'] ?? null;
    }
}
