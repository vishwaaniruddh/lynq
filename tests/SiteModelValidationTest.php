<?php
/**
 * Property Test for Site Model Validation
 * **Feature: site-management-delegation, Property 14: Coordinate validation**
 * **Feature: site-management-delegation, Property 15: Required field validation**
 * **Validates: Requirements 7.1, 7.2, 7.3**
 * 
 * Property 14: For any site data submission, latitude values outside [-90, 90] 
 * or longitude values outside [-180, 180] should be rejected with appropriate validation errors.
 * 
 * Property 15: For any site data submission with any required field (site_name, lho, city, state, country) 
 * empty or missing, the submission should be rejected with a specific error message identifying the missing field.
 */

require_once 'PropertyTestBase.php';
require_once __DIR__ . '/../models/Site.php';

class SiteModelValidationTest extends PropertyTestBase {
    
    private $siteModel;
    
    public function __construct() {
        parent::__construct();
        $this->siteModel = new Site();
    }
    
    public function runTests() {
        echo "=== Site Model Validation Property Tests ===\n\n";
        
        $allPassed = true;
        
        // Property 14: Coordinate validation tests
        $allPassed &= $this->runPropertyTest(
            "Property 14: Valid latitude values are accepted",
            [$this, 'testValidLatitudeAccepted']
        );
        
        $allPassed &= $this->runPropertyTest(
            "Property 14: Invalid latitude values are rejected",
            [$this, 'testInvalidLatitudeRejected']
        );
        
        $allPassed &= $this->runPropertyTest(
            "Property 14: Valid longitude values are accepted",
            [$this, 'testValidLongitudeAccepted']
        );
        
        $allPassed &= $this->runPropertyTest(
            "Property 14: Invalid longitude values are rejected",
            [$this, 'testInvalidLongitudeRejected']
        );
        
        // Property 15: Required field validation tests
        $allPassed &= $this->runPropertyTest(
            "Property 15: Missing required fields are rejected",
            [$this, 'testMissingRequiredFieldsRejected']
        );
        
        $allPassed &= $this->runPropertyTest(
            "Property 15: Empty required fields are rejected",
            [$this, 'testEmptyRequiredFieldsRejected']
        );
        
        $allPassed &= $this->runPropertyTest(
            "Property 15: Whitespace-only required fields are rejected",
            [$this, 'testWhitespaceRequiredFieldsRejected']
        );
        
        $allPassed &= $this->runPropertyTest(
            "Property 15: Valid required fields are accepted",
            [$this, 'testValidRequiredFieldsAccepted']
        );
        
        return $allPassed;
    }
    
    /**
     * Generate valid site data for testing
     */
    private function generateValidSiteData(): array {
        return [
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
        ];
    }
    
    /**
     * Generate random valid latitude (-90 to 90)
     */
    private function generateRandomLatitude(): float {
        return (rand(-9000000, 9000000) / 100000);
    }
    
    /**
     * Generate random valid longitude (-180 to 180)
     */
    private function generateRandomLongitude(): float {
        return (rand(-18000000, 18000000) / 100000);
    }
    
    /**
     * Generate random invalid latitude (outside -90 to 90)
     */
    private function generateInvalidLatitude(): float {
        // Generate value outside valid range
        if ($this->generateRandomBool()) {
            return 90 + rand(1, 1000) / 10; // > 90
        } else {
            return -90 - rand(1, 1000) / 10; // < -90
        }
    }
    
    /**
     * Generate random invalid longitude (outside -180 to 180)
     */
    private function generateInvalidLongitude(): float {
        // Generate value outside valid range
        if ($this->generateRandomBool()) {
            return 180 + rand(1, 1000) / 10; // > 180
        } else {
            return -180 - rand(1, 1000) / 10; // < -180
        }
    }
    
    /**
     * Property 14: Valid latitude values are accepted
     * **Feature: site-management-delegation, Property 14: Coordinate validation**
     * **Validates: Requirements 7.1**
     */
    public function testValidLatitudeAccepted() {
        try {
            $data = $this->generateValidSiteData();
            $latitude = $this->generateRandomLatitude();
            $data['latitude'] = $latitude;
            
            $result = $this->siteModel->validateCoordinates($data);
            
            // Check no latitude errors
            $latitudeErrors = array_filter($result, function($error) {
                return $error['field'] === 'latitude';
            });
            
            $this->assert(
                empty($latitudeErrors),
                "Valid latitude $latitude should not produce errors"
            );
            
            // Also test static method
            $this->assert(
                Site::isValidLatitude($latitude),
                "isValidLatitude should return true for $latitude"
            );
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => ['latitude' => $latitude ?? null]
            ];
        }
    }
    
    /**
     * Property 14: Invalid latitude values are rejected
     * **Feature: site-management-delegation, Property 14: Coordinate validation**
     * **Validates: Requirements 7.1**
     */
    public function testInvalidLatitudeRejected() {
        try {
            $data = $this->generateValidSiteData();
            $invalidLatitude = $this->generateInvalidLatitude();
            $data['latitude'] = $invalidLatitude;
            
            $result = $this->siteModel->validateCoordinates($data);
            
            // Check for latitude error
            $latitudeErrors = array_filter($result, function($error) {
                return $error['field'] === 'latitude';
            });
            
            $this->assert(
                !empty($latitudeErrors),
                "Invalid latitude $invalidLatitude should produce an error"
            );
            
            // Check error code
            $error = reset($latitudeErrors);
            $this->assert(
                $error['code'] === 'INVALID_LATITUDE',
                "Error code should be INVALID_LATITUDE"
            );
            
            // Also test static method
            $this->assert(
                !Site::isValidLatitude($invalidLatitude),
                "isValidLatitude should return false for $invalidLatitude"
            );
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => ['invalidLatitude' => $invalidLatitude ?? null]
            ];
        }
    }
    
    /**
     * Property 14: Valid longitude values are accepted
     * **Feature: site-management-delegation, Property 14: Coordinate validation**
     * **Validates: Requirements 7.2**
     */
    public function testValidLongitudeAccepted() {
        try {
            $data = $this->generateValidSiteData();
            $longitude = $this->generateRandomLongitude();
            $data['longitude'] = $longitude;
            
            $result = $this->siteModel->validateCoordinates($data);
            
            // Check no longitude errors
            $longitudeErrors = array_filter($result, function($error) {
                return $error['field'] === 'longitude';
            });
            
            $this->assert(
                empty($longitudeErrors),
                "Valid longitude $longitude should not produce errors"
            );
            
            // Also test static method
            $this->assert(
                Site::isValidLongitude($longitude),
                "isValidLongitude should return true for $longitude"
            );
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => ['longitude' => $longitude ?? null]
            ];
        }
    }
    
    /**
     * Property 14: Invalid longitude values are rejected
     * **Feature: site-management-delegation, Property 14: Coordinate validation**
     * **Validates: Requirements 7.2**
     */
    public function testInvalidLongitudeRejected() {
        try {
            $data = $this->generateValidSiteData();
            $invalidLongitude = $this->generateInvalidLongitude();
            $data['longitude'] = $invalidLongitude;
            
            $result = $this->siteModel->validateCoordinates($data);
            
            // Check for longitude error
            $longitudeErrors = array_filter($result, function($error) {
                return $error['field'] === 'longitude';
            });
            
            $this->assert(
                !empty($longitudeErrors),
                "Invalid longitude $invalidLongitude should produce an error"
            );
            
            // Check error code
            $error = reset($longitudeErrors);
            $this->assert(
                $error['code'] === 'INVALID_LONGITUDE',
                "Error code should be INVALID_LONGITUDE"
            );
            
            // Also test static method
            $this->assert(
                !Site::isValidLongitude($invalidLongitude),
                "isValidLongitude should return false for $invalidLongitude"
            );
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => ['invalidLongitude' => $invalidLongitude ?? null]
            ];
        }
    }
    
    /**
     * Property 15: Missing required fields are rejected
     * **Feature: site-management-delegation, Property 15: Required field validation**
     * **Validates: Requirements 7.3**
     */
    public function testMissingRequiredFieldsRejected() {
        try {
            $requiredFields = ['site_name', 'lho', 'city', 'state', 'country'];
            $fieldToRemove = $this->generateRandomChoice($requiredFields);
            
            $data = $this->generateValidSiteData();
            unset($data[$fieldToRemove]);
            
            $result = $this->siteModel->validate($data);
            
            $this->assert(
                !$result['isValid'],
                "Data missing '$fieldToRemove' should be invalid"
            );
            
            // Check for specific field error
            $fieldErrors = array_filter($result['errors'], function($error) use ($fieldToRemove) {
                return $error['field'] === $fieldToRemove;
            });
            
            $this->assert(
                !empty($fieldErrors),
                "Should have error for missing field '$fieldToRemove'"
            );
            
            $error = reset($fieldErrors);
            $this->assert(
                $error['code'] === 'REQUIRED_FIELD_MISSING',
                "Error code should be REQUIRED_FIELD_MISSING"
            );
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => ['fieldToRemove' => $fieldToRemove ?? null]
            ];
        }
    }
    
    /**
     * Property 15: Empty required fields are rejected
     * **Feature: site-management-delegation, Property 15: Required field validation**
     * **Validates: Requirements 7.3**
     */
    public function testEmptyRequiredFieldsRejected() {
        try {
            $requiredFields = ['site_name', 'lho', 'city', 'state', 'country'];
            $fieldToEmpty = $this->generateRandomChoice($requiredFields);
            
            $data = $this->generateValidSiteData();
            $data[$fieldToEmpty] = '';
            
            $result = $this->siteModel->validate($data);
            
            $this->assert(
                !$result['isValid'],
                "Data with empty '$fieldToEmpty' should be invalid"
            );
            
            // Check for specific field error
            $fieldErrors = array_filter($result['errors'], function($error) use ($fieldToEmpty) {
                return $error['field'] === $fieldToEmpty;
            });
            
            $this->assert(
                !empty($fieldErrors),
                "Should have error for empty field '$fieldToEmpty'"
            );
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => ['fieldToEmpty' => $fieldToEmpty ?? null]
            ];
        }
    }
    
    /**
     * Property 15: Whitespace-only required fields are rejected
     * **Feature: site-management-delegation, Property 15: Required field validation**
     * **Validates: Requirements 7.3**
     */
    public function testWhitespaceRequiredFieldsRejected() {
        try {
            $requiredFields = ['site_name', 'lho', 'city', 'state', 'country'];
            $fieldToWhitespace = $this->generateRandomChoice($requiredFields);
            
            // Generate random whitespace string
            $whitespaceChars = [' ', "\t", "\n", "\r"];
            $whitespace = '';
            $length = rand(1, 5);
            for ($i = 0; $i < $length; $i++) {
                $whitespace .= $this->generateRandomChoice($whitespaceChars);
            }
            
            $data = $this->generateValidSiteData();
            $data[$fieldToWhitespace] = $whitespace;
            
            $result = $this->siteModel->validate($data);
            
            $this->assert(
                !$result['isValid'],
                "Data with whitespace-only '$fieldToWhitespace' should be invalid"
            );
            
            // Check for specific field error
            $fieldErrors = array_filter($result['errors'], function($error) use ($fieldToWhitespace) {
                return $error['field'] === $fieldToWhitespace;
            });
            
            $this->assert(
                !empty($fieldErrors),
                "Should have error for whitespace-only field '$fieldToWhitespace'"
            );
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => ['fieldToWhitespace' => $fieldToWhitespace ?? null]
            ];
        }
    }
    
    /**
     * Property 15: Valid required fields are accepted
     * **Feature: site-management-delegation, Property 15: Required field validation**
     * **Validates: Requirements 7.3**
     */
    public function testValidRequiredFieldsAccepted() {
        try {
            $data = $this->generateValidSiteData();
            
            $result = $this->siteModel->validate($data);
            
            $this->assert(
                $result['isValid'],
                "Valid site data should pass validation"
            );
            
            $this->assert(
                empty($result['errors']),
                "Valid site data should have no errors"
            );
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => $data ?? null
            ];
        }
    }
}
