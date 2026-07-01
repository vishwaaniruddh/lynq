<?php
/**
 * Property Test for Site Serialization
 * **Feature: site-management-delegation, Property 16: Site serialization round-trip**
 * **Validates: Requirements 7.4, 7.5**
 * 
 * Property 16: For any valid site object, serializing to JSON and then deserializing 
 * should produce an equivalent site object with all fields intact.
 */

require_once 'PropertyTestBase.php';
require_once __DIR__ . '/../utils/SiteSerializer.php';

class SiteSerializationPropertyTest extends PropertyTestBase {
    
    public function __construct() {
        parent::__construct();
    }
    
    public function runTests(): bool {
        echo "=== Site Serialization Property Tests ===\n\n";
        
        $allPassed = true;
        
        // Property 16: Site serialization round-trip (JSON)
        $allPassed &= $this->runPropertyTest(
            "Property 16: JSON serialization round-trip preserves all fields",
            [$this, 'testJsonRoundTrip']
        );
        
        // Property 16: Site serialization round-trip (Excel)
        $allPassed &= $this->runPropertyTest(
            "Property 16: Excel row serialization round-trip preserves all fields",
            [$this, 'testExcelRowRoundTrip']
        );
        
        // Additional edge case tests
        $allPassed &= $this->runPropertyTest(
            "Property 16: JSON round-trip with null optional fields",
            [$this, 'testJsonRoundTripWithNulls']
        );
        
        $allPassed &= $this->runPropertyTest(
            "Property 16: Excel round-trip with boundary coordinates",
            [$this, 'testExcelRoundTripBoundaryCoordinates']
        );
        
        return $allPassed;
    }
    
    /**
     * Generate valid site data for testing
     */
    private function generateValidSiteData(): array {
        return [
            'id' => $this->generateRandomInt(1, 100000),
            'site_name' => 'Site_' . $this->generateRandomString(10),
            'lho' => 'LHO_' . $this->generateRandomString(5),
            'bank_name' => 'Bank_' . $this->generateRandomString(8),
            'customer_name' => 'Customer_' . $this->generateRandomString(8),
            'city' => 'City_' . $this->generateRandomString(6),
            'state' => 'State_' . $this->generateRandomString(6),
            'country' => 'Country_' . $this->generateRandomString(6),
            'zone' => 'Zone_' . $this->generateRandomString(4),
            'address' => 'Address ' . $this->generateRandomString(20),
            'latitude' => $this->generateRandomLatitude(),
            'longitude' => $this->generateRandomLongitude(),
            'company_id' => $this->generateRandomInt(1, 1000),
            'status' => $this->generateRandomChoice(['active', 'inactive', 'deleted']),
            'created_at' => date('Y-m-d H:i:s', time() - rand(0, 86400 * 365)),
            'created_by' => $this->generateRandomInt(1, 100),
            'updated_at' => $this->generateRandomBool() ? date('Y-m-d H:i:s') : null,
            'updated_by' => $this->generateRandomBool() ? $this->generateRandomInt(1, 100) : null
        ];
    }
    
    /**
     * Generate random valid latitude (-90 to 90)
     */
    private function generateRandomLatitude(): float {
        return round(rand(-9000000, 9000000) / 100000, 6);
    }
    
    /**
     * Generate random valid longitude (-180 to 180)
     */
    private function generateRandomLongitude(): float {
        return round(rand(-18000000, 18000000) / 100000, 6);
    }
    
    /**
     * Property 16: JSON serialization round-trip preserves all fields
     * **Feature: site-management-delegation, Property 16: Site serialization round-trip**
     * **Validates: Requirements 7.4, 7.5**
     */
    public function testJsonRoundTrip(): array {
        try {
            $originalSite = $this->generateValidSiteData();
            
            // Serialize to JSON
            $json = SiteSerializer::toJson($originalSite);
            
            // Verify JSON is valid
            $this->assert(
                !empty($json),
                "JSON output should not be empty"
            );
            
            $this->assert(
                json_decode($json) !== null,
                "JSON output should be valid JSON"
            );
            
            // Deserialize back
            $deserializedSite = SiteSerializer::fromJson($json);
            
            // Compare using the built-in comparison method
            $comparison = SiteSerializer::compareForEquality($originalSite, $deserializedSite);
            
            $this->assert(
                $comparison['isEqual'],
                "Round-trip should preserve all fields. Differences: " . json_encode($comparison['differences'])
            );
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => $originalSite ?? null
            ];
        }
    }
    
    /**
     * Property 16: Excel row serialization round-trip preserves all fields
     * **Feature: site-management-delegation, Property 16: Site serialization round-trip**
     * **Validates: Requirements 7.4, 7.5**
     */
    public function testExcelRowRoundTrip(): array {
        try {
            $originalSite = $this->generateValidSiteData();
            
            // Serialize to Excel row
            $excelRow = SiteSerializer::toExcelRow($originalSite);
            
            // Verify row has correct number of columns
            $expectedColumns = count(SiteSerializer::getExcelColumnMapping());
            $this->assert(
                count($excelRow) === $expectedColumns,
                "Excel row should have $expectedColumns columns, got " . count($excelRow)
            );
            
            // Deserialize back
            $deserializedSite = SiteSerializer::fromExcelRow($excelRow);
            
            // Compare using the built-in comparison method
            $comparison = SiteSerializer::compareForEquality($originalSite, $deserializedSite);
            
            $this->assert(
                $comparison['isEqual'],
                "Excel round-trip should preserve all fields. Differences: " . json_encode($comparison['differences'])
            );
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => $originalSite ?? null
            ];
        }
    }
    
    /**
     * Property 16: JSON round-trip with null optional fields
     * **Feature: site-management-delegation, Property 16: Site serialization round-trip**
     * **Validates: Requirements 7.4, 7.5**
     */
    public function testJsonRoundTripWithNulls(): array {
        try {
            // Generate site with some null optional fields
            $originalSite = [
                'id' => $this->generateRandomInt(1, 100000),
                'site_name' => 'Site_' . $this->generateRandomString(10),
                'lho' => 'LHO_' . $this->generateRandomString(5),
                'bank_name' => null,  // Optional field
                'customer_name' => null,  // Optional field
                'city' => 'City_' . $this->generateRandomString(6),
                'state' => 'State_' . $this->generateRandomString(6),
                'country' => 'Country_' . $this->generateRandomString(6),
                'zone' => null,  // Optional field
                'address' => null,  // Optional field
                'latitude' => null,  // Optional field
                'longitude' => null,  // Optional field
                'company_id' => $this->generateRandomInt(1, 1000),
                'status' => 'active',
                'created_at' => date('Y-m-d H:i:s'),
                'created_by' => $this->generateRandomInt(1, 100),
                'updated_at' => null,
                'updated_by' => null
            ];
            
            // Serialize to JSON
            $json = SiteSerializer::toJson($originalSite);
            
            // Deserialize back
            $deserializedSite = SiteSerializer::fromJson($json);
            
            // Compare
            $comparison = SiteSerializer::compareForEquality($originalSite, $deserializedSite);
            
            $this->assert(
                $comparison['isEqual'],
                "Round-trip with nulls should preserve all fields. Differences: " . json_encode($comparison['differences'])
            );
            
            // Verify null fields are still null
            $this->assert(
                $deserializedSite['bank_name'] === null,
                "Null bank_name should remain null"
            );
            
            $this->assert(
                $deserializedSite['latitude'] === null,
                "Null latitude should remain null"
            );
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => $originalSite ?? null
            ];
        }
    }
    
    /**
     * Property 16: Excel round-trip with boundary coordinates
     * **Feature: site-management-delegation, Property 16: Site serialization round-trip**
     * **Validates: Requirements 7.4, 7.5**
     */
    public function testExcelRoundTripBoundaryCoordinates(): array {
        try {
            // Test with boundary coordinate values
            $boundaryLatitudes = [-90.0, -45.0, 0.0, 45.0, 90.0];
            $boundaryLongitudes = [-180.0, -90.0, 0.0, 90.0, 180.0];
            
            $latitude = $this->generateRandomChoice($boundaryLatitudes);
            $longitude = $this->generateRandomChoice($boundaryLongitudes);
            
            $originalSite = [
                'id' => $this->generateRandomInt(1, 100000),
                'site_name' => 'Site_' . $this->generateRandomString(10),
                'lho' => 'LHO_' . $this->generateRandomString(5),
                'bank_name' => 'Bank_' . $this->generateRandomString(8),
                'customer_name' => 'Customer_' . $this->generateRandomString(8),
                'city' => 'City_' . $this->generateRandomString(6),
                'state' => 'State_' . $this->generateRandomString(6),
                'country' => 'Country_' . $this->generateRandomString(6),
                'zone' => 'Zone_' . $this->generateRandomString(4),
                'address' => 'Address ' . $this->generateRandomString(20),
                'latitude' => $latitude,
                'longitude' => $longitude,
                'company_id' => $this->generateRandomInt(1, 1000),
                'status' => 'active',
                'created_at' => date('Y-m-d H:i:s'),
                'created_by' => $this->generateRandomInt(1, 100),
                'updated_at' => null,
                'updated_by' => null
            ];
            
            // Serialize to Excel row
            $excelRow = SiteSerializer::toExcelRow($originalSite);
            
            // Deserialize back
            $deserializedSite = SiteSerializer::fromExcelRow($excelRow);
            
            // Compare
            $comparison = SiteSerializer::compareForEquality($originalSite, $deserializedSite);
            
            $this->assert(
                $comparison['isEqual'],
                "Excel round-trip with boundary coordinates should preserve all fields. " .
                "Lat: $latitude, Lng: $longitude. Differences: " . json_encode($comparison['differences'])
            );
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => $originalSite ?? null
            ];
        }
    }
}
