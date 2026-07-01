<?php
/**
 * Email Preview API Endpoints
 * Handles email template preview functionality
 * 
 * Requirements: 3.4
 * - 3.4: Preview API endpoints with real-time validation
 */

require_once __DIR__ . '/../../config/autoload.php';
require_once __DIR__ . '/../../services/EmailPreviewService.php';
require_once __DIR__ . '/../ApiResponse.php';

// Set JSON content type
header('Content-Type: application/json');

// Handle CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    exit(0);
}

try {
    $previewService = new EmailPreviewService();
    $method = $_SERVER['REQUEST_METHOD'];
    $path = $_SERVER['REQUEST_URI'];
    
    // Parse path to get action
    $pathParts = explode('/', trim(parse_url($path, PHP_URL_PATH), '/'));
    $action = end($pathParts);
    
    switch ($method) {
        case 'GET':
            handleGetRequest($previewService, $action);
            break;
            
        case 'POST':
            handlePostRequest($previewService, $action);
            break;
            
        default:
            ApiResponse::error('Method not allowed', 405);
            break;
    }
    
} catch (Exception $e) {
    error_log("Email Preview API Error: " . $e->getMessage());
    ApiResponse::error('Internal server error: ' . $e->getMessage(), 500);
}

/**
 * Handle GET requests
 */
function handleGetRequest($previewService, $action) {
    switch ($action) {
        case 'template':
            previewTemplate($previewService);
            break;
            
        case 'placeholders':
            getPlaceholders($previewService);
            break;
            
        case 'sample-data':
            getSampleData($previewService);
            break;
            
        default:
            ApiResponse::error('Invalid action', 400);
            break;
    }
}

/**
 * Handle POST requests
 */
function handlePostRequest($previewService, $action) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        ApiResponse::error('Invalid JSON input', 400);
        return;
    }
    
    switch ($action) {
        case 'content':
            previewContent($previewService, $input);
            break;
            
        case 'validate':
            validatePlaceholders($previewService, $input);
            break;
            
        case 'batch':
            batchPreview($previewService, $input);
            break;
            
        default:
            ApiResponse::error('Invalid action', 400);
            break;
    }
}

/**
 * Preview existing template
 * GET /api/email/preview/template?id=123&entity_id=456
 */
function previewTemplate($previewService) {
    $templateId = $_GET['id'] ?? null;
    $entityId = $_GET['entity_id'] ?? null;
    
    if (!$templateId) {
        ApiResponse::error('Template ID is required', 400);
        return;
    }
    
    if (!is_numeric($templateId)) {
        ApiResponse::error('Invalid template ID', 400);
        return;
    }
    
    try {
        $customData = null;
        
        // If custom data is provided in query params
        if (isset($_GET['custom_data'])) {
            $customData = json_decode($_GET['custom_data'], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                ApiResponse::error('Invalid custom data JSON', 400);
                return;
            }
        }
        
        $preview = $previewService->generateTemplatePreview(
            (int)$templateId, 
            $customData, 
            $entityId ? (int)$entityId : null
        );
        
        $stats = $previewService->getPreviewStatistics($preview);
        
        ApiResponse::success([
            'preview' => $preview,
            'statistics' => $stats
        ]);
        
    } catch (Exception $e) {
        ApiResponse::error($e->getMessage(), 400);
    }
}

/**
 * Preview custom content
 * POST /api/email/preview/content
 * Body: {
 *   "subject": "Email subject with {placeholder}",
 *   "body_text": "Text body with {placeholder}",
 *   "body_html": "<p>HTML body with {placeholder}</p>",
 *   "module_name": "sites",
 *   "custom_data": {...}
 * }
 */
function previewContent($previewService, $input) {
    $subject = $input['subject'] ?? '';
    $bodyText = $input['body_text'] ?? null;
    $bodyHtml = $input['body_html'] ?? null;
    $moduleName = $input['module_name'] ?? '';
    $customData = $input['custom_data'] ?? null;
    
    if (empty($subject)) {
        ApiResponse::error('Subject is required', 400);
        return;
    }
    
    if (empty($moduleName)) {
        ApiResponse::error('Module name is required', 400);
        return;
    }
    
    try {
        $preview = $previewService->generateContentPreview(
            $subject,
            $bodyText,
            $bodyHtml,
            $moduleName,
            $customData
        );
        
        $stats = $previewService->getPreviewStatistics($preview);
        
        ApiResponse::success([
            'preview' => $preview,
            'statistics' => $stats
        ]);
        
    } catch (Exception $e) {
        ApiResponse::error($e->getMessage(), 400);
    }
}

/**
 * Validate placeholders in content
 * POST /api/email/preview/validate
 * Body: {
 *   "content": "Content with {placeholders}",
 *   "module_name": "sites"
 * }
 */
function validatePlaceholders($previewService, $input) {
    $content = $input['content'] ?? '';
    $moduleName = $input['module_name'] ?? '';
    
    if (empty($content)) {
        ApiResponse::error('Content is required', 400);
        return;
    }
    
    if (empty($moduleName)) {
        ApiResponse::error('Module name is required', 400);
        return;
    }
    
    try {
        $validation = $previewService->validateTemplatePlaceholders($content, $moduleName);
        
        ApiResponse::success([
            'validation' => $validation,
            'is_valid' => $validation['valid'],
            'error_count' => count($validation['errors']),
            'placeholder_count' => count($validation['placeholders'])
        ]);
        
    } catch (Exception $e) {
        ApiResponse::error($e->getMessage(), 400);
    }
}

/**
 * Get available placeholders for module
 * GET /api/email/preview/placeholders?module=sites
 */
function getPlaceholders($previewService) {
    $moduleName = $_GET['module'] ?? '';
    
    if (empty($moduleName)) {
        ApiResponse::error('Module name is required', 400);
        return;
    }
    
    try {
        $placeholders = $previewService->getAvailablePlaceholders($moduleName);
        
        ApiResponse::success([
            'module_name' => $moduleName,
            'placeholders' => $placeholders,
            'count' => count($placeholders)
        ]);
        
    } catch (Exception $e) {
        ApiResponse::error($e->getMessage(), 400);
    }
}

/**
 * Get sample data for module
 * GET /api/email/preview/sample-data?module=sites&entity_id=123
 */
function getSampleData($previewService) {
    $moduleName = $_GET['module'] ?? '';
    $entityId = $_GET['entity_id'] ?? null;
    
    if (empty($moduleName)) {
        ApiResponse::error('Module name is required', 400);
        return;
    }
    
    try {
        $sampleData = $previewService->generateSampleDataForModule(
            $moduleName, 
            $entityId ? (int)$entityId : null
        );
        
        ApiResponse::success([
            'module_name' => $moduleName,
            'entity_id' => $entityId,
            'sample_data' => $sampleData,
            'data_keys' => array_keys($sampleData)
        ]);
        
    } catch (Exception $e) {
        ApiResponse::error($e->getMessage(), 400);
    }
}

/**
 * Batch preview multiple templates
 * POST /api/email/preview/batch
 * Body: {
 *   "template_ids": [1, 2, 3],
 *   "custom_data": {...}
 * }
 */
function batchPreview($previewService, $input) {
    $templateIds = $input['template_ids'] ?? [];
    $customData = $input['custom_data'] ?? null;
    
    if (empty($templateIds) || !is_array($templateIds)) {
        ApiResponse::error('Template IDs array is required', 400);
        return;
    }
    
    // Validate template IDs
    foreach ($templateIds as $id) {
        if (!is_numeric($id)) {
            ApiResponse::error('All template IDs must be numeric', 400);
            return;
        }
    }
    
    // Limit batch size
    if (count($templateIds) > 10) {
        ApiResponse::error('Maximum 10 templates allowed in batch preview', 400);
        return;
    }
    
    try {
        $previews = $previewService->batchPreviewTemplates($templateIds, $customData);
        
        $summary = [
            'total_templates' => count($templateIds),
            'successful_previews' => 0,
            'failed_previews' => 0
        ];
        
        foreach ($previews as $preview) {
            if (isset($preview['error'])) {
                $summary['failed_previews']++;
            } else {
                $summary['successful_previews']++;
            }
        }
        
        ApiResponse::success([
            'previews' => $previews,
            'summary' => $summary
        ]);
        
    } catch (Exception $e) {
        ApiResponse::error($e->getMessage(), 400);
    }
}