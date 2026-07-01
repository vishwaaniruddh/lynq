<?php
/**
 * Property Test for ADV Documentation Icon Navigation Target
 * **Feature: adv-user-documentation, Property 2: Icon Navigation Target**
 * **Validates: Requirements 1.2**
 * 
 * This test verifies that the documentation icon in the header correctly
 * links to the documentation page path /docs/adv.php
 */

require_once 'PropertyTestBase.php';

class AdvDocumentationIconNavigationPropertyTest extends PropertyTestBase {
    
    private $headerFilePath;
    private $headerContent;
    
    public function __construct() {
        parent::__construct();
        $this->headerFilePath = __DIR__ . '/../views/components/header.php';
    }
    
    public function runTests() {
        echo "=== ADV Documentation Icon Navigation Property Tests ===\n\n";
        
        // Load header content once
        if (!$this->loadHeaderContent()) {
            echo "FAILED: Could not load header file\n";
            return false;
        }
        
        $allPassed = true;
        
        $allPassed &= $this->runPropertyTest(
            "Documentation Icon Has Correct Href Target",
            [$this, 'testIconHrefTarget'],
            100
        );
        
        $allPassed &= $this->runPropertyTest(
            "Documentation Icon Has Tooltip Attribute",
            [$this, 'testIconHasTooltip'],
            100
        );
        
        $allPassed &= $this->runPropertyTest(
            "Documentation Icon Is Conditionally Rendered For ADV Users",
            [$this, 'testIconConditionalRendering'],
            100
        );
        
        $allPassed &= $this->runPropertyTest(
            "Documentation Icon Uses Book Icon",
            [$this, 'testIconUsesBookIcon'],
            100
        );
        
        return $allPassed;
    }
    
    private function loadHeaderContent() {
        if (!file_exists($this->headerFilePath)) {
            echo "Header file not found at: {$this->headerFilePath}\n";
            return false;
        }
        
        $this->headerContent = file_get_contents($this->headerFilePath);
        return !empty($this->headerContent);
    }
    
    /**
     * Property: For any rendered documentation icon, the href attribute SHALL point to /docs/adv.php
     */
    public function testIconHrefTarget() {
        try {
            // Check that the documentation link exists with correct href pattern
            // The href should be: $baseUrl . '/docs/adv.php' or '/docs/adv.php'
            $hrefPattern = '/href=["\'][^"\']*\/docs\/adv\.php["\']/i';
            
            $hasCorrectHref = preg_match($hrefPattern, $this->headerContent);
            
            $this->assert(
                $hasCorrectHref === 1,
                "Documentation icon should have href pointing to /docs/adv.php"
            );
            
            // Also verify the specific PHP variable pattern used
            $phpHrefPattern = '/href=["\']<\?php\s+echo\s+\$baseUrl;\s*\?>\/docs\/adv\.php["\']/i';
            $hasDynamicHref = preg_match($phpHrefPattern, $this->headerContent);
            
            $this->assert(
                $hasDynamicHref === 1,
                "Documentation icon href should use \$baseUrl variable for proper path resolution"
            );
            
            return ['success' => true];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Property: Documentation icon SHALL include a tooltip showing "ADV Documentation"
     */
    public function testIconHasTooltip() {
        try {
            // Check for title attribute with "ADV Documentation"
            $titlePattern = '/title=["\']ADV Documentation["\']/i';
            
            $hasTooltip = preg_match($titlePattern, $this->headerContent);
            
            $this->assert(
                $hasTooltip === 1,
                "Documentation icon should have title attribute with 'ADV Documentation'"
            );
            
            return ['success' => true];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Property: Documentation icon SHALL only be rendered for ADV users (conditional check)
     */
    public function testIconConditionalRendering() {
        try {
            // Check that the icon is wrapped in isAdvUser() conditional
            $conditionalPattern = '/if\s*\(\s*isAdvUser\s*\(\s*\)\s*\)/i';
            
            $hasConditional = preg_match($conditionalPattern, $this->headerContent);
            
            $this->assert(
                $hasConditional === 1,
                "Documentation icon should be wrapped in isAdvUser() conditional check"
            );
            
            // Verify the conditional wraps the documentation link
            // Find the position of isAdvUser check and the docs/adv.php link
            $conditionalPos = strpos($this->headerContent, 'isAdvUser()');
            $linkPos = strpos($this->headerContent, '/docs/adv.php');
            
            $this->assert(
                $conditionalPos !== false && $linkPos !== false,
                "Both isAdvUser() check and /docs/adv.php link should exist"
            );
            
            $this->assert(
                $conditionalPos < $linkPos,
                "isAdvUser() check should appear before the documentation link"
            );
            
            return ['success' => true];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Property: Documentation icon SHALL use a book icon (fa-book)
     */
    public function testIconUsesBookIcon() {
        try {
            // Check for Font Awesome book icon class
            $iconPattern = '/fa-book/i';
            
            $hasBookIcon = preg_match($iconPattern, $this->headerContent);
            
            $this->assert(
                $hasBookIcon === 1,
                "Documentation icon should use fa-book icon class"
            );
            
            // Verify it's within an <i> tag with fas class
            $fullIconPattern = '/<i\s+class=["\'][^"\']*fas[^"\']*fa-book[^"\']*["\']/i';
            $hasFullIconMarkup = preg_match($fullIconPattern, $this->headerContent);
            
            $this->assert(
                $hasFullIconMarkup === 1,
                "Documentation icon should be an <i> tag with 'fas fa-book' classes"
            );
            
            return ['success' => true];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    public function cleanupTestData() {
        // No database cleanup needed for this test
        // This test only reads the header file content
    }
}
