<?php
/**
 * Runner for Editable Sections Restriction Property Test
 * 
 * **Feature: installation-module, Property 27: Editable sections restriction**
 * **Validates: Requirements 16.3**
 */

require_once __DIR__ . '/EditableSectionsRestrictionPropertyTest.php';

$test = new EditableSectionsRestrictionPropertyTest();
$success = $test->runTests();
exit($success ? 0 : 1);
