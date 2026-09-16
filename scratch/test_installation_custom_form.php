<?php
require_once 'c:/xampp/htdocs/lynq/config/autoload.php';
require_once 'c:/xampp/htdocs/lynq/models/CustomForm.php';

$cf = new CustomForm();
$form = $cf->findFormForProject('installation', 1);
if ($form) {
    echo "SUCCESS: Loaded form '{$form['form_name']}' (ID: {$form['id']}) with " . count($form['fields']) . " fields.\n";
    $sections = [];
    foreach ($form['fields'] as $f) {
        $sec = $f['section_title'] ?? 'General';
        $sections[$sec] = ($sections[$sec] ?? 0) + 1;
    }
    foreach ($sections as $sec => $cnt) {
        echo "  - {$sec}: {$cnt} fields\n";
    }
} else {
    echo "ERROR: Form not found!\n";
}
