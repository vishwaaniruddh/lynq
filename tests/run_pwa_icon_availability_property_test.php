<?php
/**
 * Runner for PWA Icon Availability Property Test
 * **Feature: clarity-pwa-conversion, Property 7: Icon Availability and Correctness**
 * **Validates: Requirements 3.1, 3.2**
 */

require_once 'PWAIconAvailabilityPropertyTest.php';

echo "PWA Icon Availability and Correctness Property Test\n";
echo "==================================================\n\n";

try {
    $test = new PWAIconAvailabilityPropertyTest();
    $result = $test->runTests();
    
    if ($result) {
        echo "\n✅ All PWA icon property tests passed!\n";
        echo "PWA icon infrastructure is properly configured.\n";
        exit(0);
    } else {
        echo "\n❌ Some PWA icon property tests failed!\n";
        echo "Please check the icon setup and configuration.\n";
        exit(1);
    }
    
} catch (Exception $e) {
    echo "\n💥 Test execution failed: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
?>