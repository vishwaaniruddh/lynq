<?php
/**
 * Runner for Settings Categorization Property Test
 */

require_once 'SettingsCategorizationPropertyTest.php';

try {
    $test = new SettingsCategorizationPropertyTest();
    $success = $test->runAllTests();
    exit($success ? 0 : 1);
} catch (Exception $e) {
    echo "Error running settings categorization property test: " . $e->getMessage() . "\n";
    exit(1);
}