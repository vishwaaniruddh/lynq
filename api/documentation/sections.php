<?php
/**
 * Documentation Sections API
 * Provides documentation content for ADV users
 * 
 * Requirements: API-based documentation content delivery
 */

require_once __DIR__ . '/../../config/autoload.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$pathParts = explode('/', trim($path, '/'));

try {
    switch ($method) {
        case 'GET':
            if (isset($_GET['section'])) {
                // Get specific section
                $sectionId = $_GET['section'];
                $sectionData = getDocumentationSection($sectionId);
                if ($sectionData) {
                    echo json_encode(['success' => true, 'data' => $sectionData]);
                } else {
                    http_response_code(404);
                    echo json_encode(['error' => 'Section not found']);
                }
            } else {
                // Get all available sections
                $sections = getAllDocumentationSections();
                echo json_encode(['success' => true, 'data' => $sections]);
            }
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error: ' . $e->getMessage()]);
}

/**
 * Get specific documentation section
 */
function getDocumentationSection($sectionId) {
    $sectionFile = __DIR__ . '/../../docs/sections/' . $sectionId . '.php';
    
    if (!file_exists($sectionFile)) {
        return null;
    }
    
    // Security check - only allow alphanumeric and hyphens
    if (!preg_match('/^[a-z0-9-]+$/', $sectionId)) {
        return null;
    }
    
    try {
        $sectionData = include $sectionFile;
        return $sectionData;
    } catch (Exception $e) {
        error_log("Error loading documentation section {$sectionId}: " . $e->getMessage());
        return null;
    }
}

/**
 * Get all available documentation sections
 */
function getAllDocumentationSections() {
    $sectionsDir = __DIR__ . '/../../docs/sections/';
    $sections = [];
    
    if (!is_dir($sectionsDir)) {
        return $sections;
    }
    
    $files = glob($sectionsDir . '*.php');
    
    foreach ($files as $file) {
        $sectionId = basename($file, '.php');
        
        try {
            $sectionData = include $file;
            if (is_array($sectionData) && isset($sectionData['id'], $sectionData['title'], $sectionData['icon'])) {
                $sections[] = [
                    'id' => $sectionData['id'],
                    'title' => $sectionData['title'],
                    'icon' => $sectionData['icon']
                ];
            }
        } catch (Exception $e) {
            error_log("Error loading documentation section {$sectionId}: " . $e->getMessage());
            continue;
        }
    }
    
    // Sort sections by a predefined order
    $sectionOrder = [
        'role-overview',
        'dashboard',
        'masters',
        'users',
        'site-management',
        'delegation-tracking',
        'feasibility-tracking',
        'installation-tracking',
        'ip-configuration',
        'inventory',
        'system-admin'
    ];
    
    usort($sections, function($a, $b) use ($sectionOrder) {
        $aIndex = array_search($a['id'], $sectionOrder);
        $bIndex = array_search($b['id'], $sectionOrder);
        
        if ($aIndex === false) $aIndex = 999;
        if ($bIndex === false) $bIndex = 999;
        
        return $aIndex - $bIndex;
    });
    
    return $sections;
}
?>