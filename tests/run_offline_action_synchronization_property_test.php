<?php

require_once __DIR__ . '/OfflineActionSynchronizationPropertyTest.php';

echo "Running Offline Action Synchronization Property Test...\n";
echo "=================================================\n\n";

try {
    $test = new OfflineActionSynchronizationPropertyTest();
    $test->runAllTests();
    echo "\n✅ All Offline Action Synchronization Property Tests passed!\n";
} catch (Exception $e) {
    echo "\n❌ Offline Action Synchronization Property Test failed: " . $e->getMessage() . "\n";
    exit(1);
}