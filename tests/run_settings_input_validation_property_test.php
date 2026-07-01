<?php
/**
 * Runner for Settings Input Validation Property Test
 */

require_once 'SettingsInputValidationPropertyTest.php';

try {
    $test = new SettingsInputValidationPropertyTest();
    $success = $test->runAllTests();
    exit($success ? 0 : 1);
} catch (Exception $e) {
    echo "Error running settings input validation property test: " . $e->getMessage() . "\n";
    exit(1);
}