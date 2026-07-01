<?php
/**
 * Installation Sections Enum Class
 * Defines all installation form sections for section-wise approval workflow
 * 
 * Requirements: 12.1, 14.2
 * - 12.1: Display review panel with approve/reject options for each section
 * - 14.2: Highlight rejected sections with visual indicators
 */

class InstallationSections {
    // Router Section
    const ROUTER_FIXED = 'router_fixed';
    const ROUTER_STATUS = 'router_status';
    
    // Adaptor Section
    const ADAPTOR = 'adaptor';
    const ADAPTOR_STATUS = 'adaptor_status';
    
    // LAN Cable Section
    const LAN_CABLE_INSTALL = 'lan_cable_install';
    const LAN_CABLE_STATUS = 'lan_cable_status';
    
    // Antenna Section
    const ANTENNA = 'antenna';
    const ANTENNA_STATUS = 'antenna_status';
    
    // GPS Section
    const GPS = 'gps';
    const GPS_STATUS = 'gps_status';
    
    // WiFi Section
    const WIFI = 'wifi';
    const WIFI_STATUS = 'wifi_status';
    
    // SIM Sections
    const AIRTEL_SIM = 'airtel_sim';
    const AIRTEL_SIM_STATUS = 'airtel_sim_status';
    const VODAFONE_SIM = 'vodafone_sim';
    const VODAFONE_SIM_STATUS = 'vodafone_sim_status';
    const JIO_SIM = 'jio_sim';
    const JIO_SIM_STATUS = 'jio_sim_status';
    
    // Verification Section
    const VERIFICATION = 'verification';
    
    /**
     * Get all section constants
     * 
     * @return array List of all section identifiers
     */
    public static function getAll(): array {
        return [
            self::ROUTER_FIXED,
            self::ROUTER_STATUS,
            self::ADAPTOR,
            self::ADAPTOR_STATUS,
            self::LAN_CABLE_INSTALL,
            self::LAN_CABLE_STATUS,
            self::ANTENNA,
            self::ANTENNA_STATUS,
            self::GPS,
            self::GPS_STATUS,
            self::WIFI,
            self::WIFI_STATUS,
            self::AIRTEL_SIM,
            self::AIRTEL_SIM_STATUS,
            self::VODAFONE_SIM,
            self::VODAFONE_SIM_STATUS,
            self::JIO_SIM,
            self::JIO_SIM_STATUS,
            self::VERIFICATION
        ];
    }
    
    /**
     * Get human-readable label for a section
     * 
     * @param string $section Section identifier
     * @return string Human-readable label
     */
    public static function getLabel(string $section): string {
        return match($section) {
            self::ROUTER_FIXED => 'Router Fixed',
            self::ROUTER_STATUS => 'Router Status',
            self::ADAPTOR => 'Adaptor Installed',
            self::ADAPTOR_STATUS => 'Adaptor Status',
            self::LAN_CABLE_INSTALL => 'LAN Cable Installed',
            self::LAN_CABLE_STATUS => 'LAN Cable Status',
            self::ANTENNA => 'Antenna Installed',
            self::ANTENNA_STATUS => 'Antenna Status',
            self::GPS => 'GPS Installed',
            self::GPS_STATUS => 'GPS Status',
            self::WIFI => 'WiFi Installed',
            self::WIFI_STATUS => 'WiFi Status',
            self::AIRTEL_SIM => 'Airtel SIM Installed',
            self::AIRTEL_SIM_STATUS => 'Airtel SIM Status',
            self::VODAFONE_SIM => 'Vodafone SIM Installed',
            self::VODAFONE_SIM_STATUS => 'Vodafone SIM Status',
            self::JIO_SIM => 'JIO SIM Installed',
            self::JIO_SIM_STATUS => 'JIO SIM Status',
            self::VERIFICATION => 'Verification (Signature & Stamp)',
            default => ucfirst(str_replace('_', ' ', $section))
        };
    }
    
    /**
     * Check if a section identifier is valid
     * 
     * @param string $section Section identifier to check
     * @return bool True if valid
     */
    public static function isValid(string $section): bool {
        return in_array($section, self::getAll());
    }
    
    /**
     * Get sections grouped by category
     * 
     * @return array Sections grouped by category
     */
    public static function getGrouped(): array {
        return [
            'Router' => [
                self::ROUTER_FIXED,
                self::ROUTER_STATUS
            ],
            'Adaptor' => [
                self::ADAPTOR,
                self::ADAPTOR_STATUS
            ],
            'LAN Cable' => [
                self::LAN_CABLE_INSTALL,
                self::LAN_CABLE_STATUS
            ],
            'Antenna' => [
                self::ANTENNA,
                self::ANTENNA_STATUS
            ],
            'GPS' => [
                self::GPS,
                self::GPS_STATUS
            ],
            'WiFi' => [
                self::WIFI,
                self::WIFI_STATUS
            ],
            'Airtel SIM' => [
                self::AIRTEL_SIM,
                self::AIRTEL_SIM_STATUS
            ],
            'Vodafone SIM' => [
                self::VODAFONE_SIM,
                self::VODAFONE_SIM_STATUS
            ],
            'JIO SIM' => [
                self::JIO_SIM,
                self::JIO_SIM_STATUS
            ],
            'Verification' => [
                self::VERIFICATION
            ]
        ];
    }
    
    /**
     * Get the database field prefix for a section
     * Maps section identifiers to their corresponding database field prefixes
     * 
     * @param string $section Section identifier
     * @return string Database field prefix
     */
    public static function getFieldPrefix(string $section): string {
        return match($section) {
            self::ROUTER_FIXED => 'router_fixed',
            self::ROUTER_STATUS => 'router_status',
            self::ADAPTOR => 'adaptor',
            self::ADAPTOR_STATUS => 'adaptor_status',
            self::LAN_CABLE_INSTALL => 'lan_cable_install',
            self::LAN_CABLE_STATUS => 'lan_cable_status',
            self::ANTENNA => 'antenna',
            self::ANTENNA_STATUS => 'antenna_status',
            self::GPS => 'gps',
            self::GPS_STATUS => 'gps_status',
            self::WIFI => 'wifi',
            self::WIFI_STATUS => 'wifi_status',
            self::AIRTEL_SIM => 'airtel_sim',
            self::AIRTEL_SIM_STATUS => 'airtel_sim_status',
            self::VODAFONE_SIM => 'vodafone_sim',
            self::VODAFONE_SIM_STATUS => 'vodafone_sim_status',
            self::JIO_SIM => 'jio_sim',
            self::JIO_SIM_STATUS => 'jio_sim_status',
            self::VERIFICATION => 'verification',
            default => $section
        };
    }
    
    /**
     * Get the image field name for a section
     * 
     * @param string $section Section identifier
     * @return string|null Image field name or null if section has no image
     */
    public static function getImageField(string $section): ?string {
        return match($section) {
            self::ROUTER_FIXED => 'router_fixed_snaps',
            self::ROUTER_STATUS => 'router_status_snaps',
            self::ADAPTOR => 'adaptor_snaps',
            self::ADAPTOR_STATUS => 'adaptor_status_snaps',
            self::LAN_CABLE_INSTALL => 'lan_cable_install_snap',
            self::LAN_CABLE_STATUS => 'lan_cable_status_snap',
            self::ANTENNA => 'antenna_snaps',
            self::ANTENNA_STATUS => 'antenna_status_snaps',
            self::GPS => 'gps_snaps',
            self::GPS_STATUS => 'gps_status_snaps',
            self::WIFI => 'wifi_snaps',
            self::WIFI_STATUS => 'wifi_status_snaps',
            self::AIRTEL_SIM => 'airtel_sim_snaps',
            self::AIRTEL_SIM_STATUS => 'airtel_sim_status_snaps',
            self::VODAFONE_SIM => 'vodafone_sim_snaps',
            self::VODAFONE_SIM_STATUS => 'vodafone_sim_status_snaps',
            self::JIO_SIM => 'jio_sim_snaps',
            self::JIO_SIM_STATUS => 'jio_sim_status_snaps',
            self::VERIFICATION => 'vendor_stamp',
            default => null
        };
    }
    
    /**
     * Get all sections with their labels as key-value pairs
     * Useful for dropdowns and select inputs
     * 
     * @return array Section identifier => label mapping
     */
    public static function getAllWithLabels(): array {
        $result = [];
        foreach (self::getAll() as $section) {
            $result[$section] = self::getLabel($section);
        }
        return $result;
    }
}
