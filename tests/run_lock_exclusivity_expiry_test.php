<?php
/**
 * Runner for Lock Exclusivity and Expiry Property Tests
 * 
 * **Feature: ip-configuration-management, Property 8: Lock Exclusivity**
 * **Feature: ip-configuration-management, Property 10: Lock Expiry Handling**
 * **Validates: Requirements 4.2, 4.3, 11.2**
 */

require_once __DIR__ . '/LockExclusivityExpiryTest.php';

echo "===========================================\n";
echo "Lock Exclusivity and Expiry Property Tests\n";
echo "===========================================\n";
echo "Property 8: For any IP_Master with an active lock, the IP\n";
echo "SHALL be excluded from the available IP list for all other users.\n";
echo "Validates: Requirements 4.2, 11.2\n";
echo "\n";
echo "Property 10: For any IP lock that exceeds 20 minutes without\n";
echo "completion, the system SHALL automatically release the lock\n";
echo "and set IP status back to 'available'.\n";
echo "Validates: Requirements 4.3\n";
echo "===========================================\n\n";

$test = new LockExclusivityExpiryTest();
$results = $test->runAllTests();

echo "\n===========================================\n";
if (in_array(false, $results, true)) {
    echo "RESULT: FAILED\n";
    exit(1);
} else {
    echo "RESULT: PASSED\n";
    exit(0);
}
