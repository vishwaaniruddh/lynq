<?php
/**
 * ADV Clarity Management System - PWA Performance Insights API
 * Provides detailed performance analytics and insights
 */

require_once __DIR__ . '/../../config/autoload.php';
require_once __DIR__ . '/../../api/ApiResponse.php';
require_once __DIR__ . '/../../services/AnalyticsService.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? '';
    
    $analyticsService = new AnalyticsService();
    
    switch ($method) {
        case 'GET':
            handleGetRequest($action, $analyticsService);
            break;
            
        case 'POST':
            handlePostRequest($action, $analyticsService);
            break;
            
        default:
            ApiResponse::error('Method not allowed', 405);
    }
    
} catch (Exception $e) {
    error_log("Performance Insights API Error: " . $e->getMessage());
    ApiResponse::error('Internal server error', 500);
}

/**
 * Handle GET requests for performance data
 */
function handleGetRequest($action, $analyticsService) {
    switch ($action) {
        case 'overview':
            getPerformanceOverview($analyticsService);
            break;
            
        case 'trends':
            getPerformanceTrends($analyticsService);
            break;
            
        case 'bottlenecks':
            getPerformanceBottlenecks($analyticsService);
            break;
            
        case 'recommendations':
            getPerformanceRecommendations($analyticsService);
            break;
            
        case 'comparison':
            getPerformanceComparison($analyticsService);
            break;
            
        case 'batch':
            // GET batch request - return batch tracking info/status
            getBatchTrackingInfo($analyticsService);
            break;
            
        default:
            ApiResponse::error('Invalid action. Available actions: overview, trends, bottlenecks, recommendations, comparison, batch', 400);
    }
}

/**
 * Handle POST requests for performance tracking
 */
function handlePostRequest($action, $analyticsService) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    switch ($action) {
        case 'track':
            trackPerformanceMetric($input, $analyticsService);
            break;
            
        case 'batch':
            trackBatchMetrics($input, $analyticsService);
            break;
            
        default:
            ApiResponse::error('Invalid action. Available actions: track, batch', 400);
    }
}

/**
 * Get performance overview dashboard data
 */
function getPerformanceOverview($analyticsService) {
    try {
        $timeRange = $_GET['range'] ?? '24h';
        
        // Get core performance metrics
        $metrics = $analyticsService->getPerformanceMetrics($timeRange);
        
        // Calculate derived metrics
        $overview = [
            'summary' => [
                'total_requests' => $metrics['total_requests'] ?? 0,
                'average_response_time' => calculateAverageResponseTime($metrics),
                'cache_hit_rate' => calculateCacheHitRate($metrics),
                'error_rate' => calculateErrorRate($metrics),
                'performance_score' => calculatePerformanceScore($metrics)
            ],
            'cache_performance' => [
                'hits' => $metrics['cache_hits'] ?? 0,
                'misses' => $metrics['cache_misses'] ?? 0,
                'hit_rate_percentage' => calculateCacheHitRate($metrics) * 100,
                'total_cache_size' => $metrics['total_cache_size'] ?? 0,
                'cache_efficiency' => calculateCacheEfficiency($metrics)
            ],
            'network_performance' => [
                'online_requests' => $metrics['online_requests'] ?? 0,
                'offline_requests' => $metrics['offline_requests'] ?? 0,
                'sync_success_rate' => calculateSyncSuccessRate($metrics),
                'background_sync_count' => $metrics['background_sync_count'] ?? 0
            ],
            'user_experience' => [
                'page_load_time' => $metrics['average_page_load'] ?? 0,
                'time_to_interactive' => $metrics['average_tti'] ?? 0,
                'first_contentful_paint' => $metrics['average_fcp'] ?? 0,
                'largest_contentful_paint' => $metrics['average_lcp'] ?? 0
            ],
            'resource_usage' => [
                'memory_usage' => $metrics['memory_usage'] ?? 0,
                'storage_usage' => $metrics['storage_usage'] ?? 0,
                'bandwidth_saved' => calculateBandwidthSaved($metrics),
                'offline_capability' => calculateOfflineCapability($metrics)
            ]
        ];
        
        ApiResponse::success($overview);
        
    } catch (Exception $e) {
        error_log("Failed to get performance overview: " . $e->getMessage());
        ApiResponse::error('Failed to retrieve performance overview', 500);
    }
}

/**
 * Get performance trends over time
 */
function getPerformanceTrends($analyticsService) {
    try {
        $timeRange = $_GET['range'] ?? '7d';
        $granularity = $_GET['granularity'] ?? 'hour';
        
        $trends = $analyticsService->getPerformanceTrends($timeRange, $granularity);
        
        // Process trends data
        $processedTrends = [
            'response_time_trend' => processTrendData($trends, 'response_time'),
            'cache_hit_rate_trend' => processTrendData($trends, 'cache_hit_rate'),
            'error_rate_trend' => processTrendData($trends, 'error_rate'),
            'user_count_trend' => processTrendData($trends, 'active_users'),
            'performance_score_trend' => processTrendData($trends, 'performance_score')
        ];
        
        // Add trend analysis
        $analysis = [
            'response_time_direction' => analyzeTrendDirection($processedTrends['response_time_trend']),
            'cache_performance_direction' => analyzeTrendDirection($processedTrends['cache_hit_rate_trend']),
            'overall_health' => calculateOverallHealthTrend($processedTrends)
        ];
        
        ApiResponse::success([
            'trends' => $processedTrends,
            'analysis' => $analysis,
            'time_range' => $timeRange,
            'granularity' => $granularity
        ]);
        
    } catch (Exception $e) {
        error_log("Failed to get performance trends: " . $e->getMessage());
        ApiResponse::error('Failed to retrieve performance trends', 500);
    }
}

/**
 * Identify performance bottlenecks
 */
function getPerformanceBottlenecks($analyticsService) {
    try {
        $bottlenecks = $analyticsService->getPerformanceBottlenecks();
        
        // Analyze and categorize bottlenecks
        $categorizedBottlenecks = [
            'critical' => [],
            'warning' => [],
            'info' => []
        ];
        
        foreach ($bottlenecks as $bottleneck) {
            $severity = determineBottleneckSeverity($bottleneck);
            $categorizedBottlenecks[$severity][] = enhanceBottleneckData($bottleneck);
        }
        
        // Add recommendations for each bottleneck
        foreach ($categorizedBottlenecks as $severity => &$items) {
            foreach ($items as &$item) {
                $item['recommendations'] = generateBottleneckRecommendations($item);
            }
        }
        
        ApiResponse::success([
            'bottlenecks' => $categorizedBottlenecks,
            'summary' => [
                'critical_count' => count($categorizedBottlenecks['critical']),
                'warning_count' => count($categorizedBottlenecks['warning']),
                'info_count' => count($categorizedBottlenecks['info']),
                'total_count' => array_sum(array_map('count', $categorizedBottlenecks))
            ]
        ]);
        
    } catch (Exception $e) {
        error_log("Failed to get performance bottlenecks: " . $e->getMessage());
        ApiResponse::error('Failed to retrieve performance bottlenecks', 500);
    }
}

/**
 * Get performance optimization recommendations
 */
function getPerformanceRecommendations($analyticsService) {
    try {
        $metrics = $analyticsService->getPerformanceMetrics('24h');
        $recommendations = [];
        
        // Cache optimization recommendations
        $cacheHitRate = calculateCacheHitRate($metrics);
        if ($cacheHitRate < 0.7) {
            $recommendations[] = [
                'category' => 'caching',
                'priority' => 'high',
                'title' => 'Improve Cache Hit Rate',
                'description' => 'Cache hit rate is below 70%. Consider preloading more critical resources.',
                'impact' => 'High - Can improve response times by 40-60%',
                'effort' => 'Medium',
                'actions' => [
                    'Identify frequently accessed resources',
                    'Add resources to preload list',
                    'Optimize cache strategies for API endpoints'
                ]
            ];
        }
        
        // Response time recommendations
        $avgResponseTime = calculateAverageResponseTime($metrics);
        if ($avgResponseTime > 500) {
            $recommendations[] = [
                'category' => 'performance',
                'priority' => 'high',
                'title' => 'Reduce Response Times',
                'description' => 'Average response time is above 500ms.',
                'impact' => 'High - Improves user experience significantly',
                'effort' => 'Medium',
                'actions' => [
                    'Optimize database queries',
                    'Implement better caching strategies',
                    'Consider CDN for static assets'
                ]
            ];
        }
        
        // Storage optimization recommendations
        if (isset($metrics['storage_usage']) && $metrics['storage_usage'] > 80 * 1024 * 1024) {
            $recommendations[] = [
                'category' => 'storage',
                'priority' => 'medium',
                'title' => 'Optimize Storage Usage',
                'description' => 'Storage usage is high. Consider cleanup strategies.',
                'impact' => 'Medium - Prevents storage issues',
                'effort' => 'Low',
                'actions' => [
                    'Run cache cleanup',
                    'Remove unused cached resources',
                    'Implement automatic cleanup schedules'
                ]
            ];
        }
        
        // Offline capability recommendations
        $offlineCapability = calculateOfflineCapability($metrics);
        if ($offlineCapability < 0.8) {
            $recommendations[] = [
                'category' => 'offline',
                'priority' => 'medium',
                'title' => 'Enhance Offline Capability',
                'description' => 'Offline functionality could be improved.',
                'impact' => 'Medium - Better user experience in poor network conditions',
                'effort' => 'Medium',
                'actions' => [
                    'Cache more critical resources',
                    'Implement better offline fallbacks',
                    'Optimize background sync'
                ]
            ];
        }
        
        // Sort recommendations by priority
        usort($recommendations, function($a, $b) {
            $priorities = ['high' => 3, 'medium' => 2, 'low' => 1];
            return $priorities[$b['priority']] - $priorities[$a['priority']];
        });
        
        ApiResponse::success([
            'recommendations' => $recommendations,
            'total_count' => count($recommendations),
            'high_priority_count' => count(array_filter($recommendations, fn($r) => $r['priority'] === 'high'))
        ]);
        
    } catch (Exception $e) {
        error_log("Failed to get performance recommendations: " . $e->getMessage());
        ApiResponse::error('Failed to generate performance recommendations', 500);
    }
}

/**
 * Get batch tracking information and status
 */
function getBatchTrackingInfo($analyticsService) {
    try {
        $timeRange = $_GET['range'] ?? '24h';
        
        // Get batch tracking statistics (with fallback if method doesn't exist)
        $batchStats = [];
        if (method_exists($analyticsService, 'getBatchTrackingStats')) {
            $batchStats = $analyticsService->getBatchTrackingStats($timeRange);
        } else {
            // Fallback data
            $batchStats = [
                'recent_batches' => [],
                'total_batches' => 0,
                'total_metrics' => 0,
                'avg_batch_size' => 0,
                'success_rate' => 100
            ];
        }
        
        $info = [
            'batch_tracking_enabled' => true,
            'supported_metrics' => [
                'page_load_time',
                'time_to_interactive',
                'first_contentful_paint',
                'largest_contentful_paint',
                'cache_performance',
                'network_performance',
                'user_interactions',
                'error_tracking'
            ],
            'batch_limits' => [
                'max_metrics_per_batch' => 100,
                'max_batch_size_kb' => 512,
                'rate_limit_per_minute' => 60
            ],
            'recent_batches' => $batchStats['recent_batches'] ?? [],
            'statistics' => [
                'total_batches_processed' => $batchStats['total_batches'] ?? 0,
                'total_metrics_tracked' => $batchStats['total_metrics'] ?? 0,
                'average_batch_size' => $batchStats['avg_batch_size'] ?? 0,
                'success_rate' => $batchStats['success_rate'] ?? 100
            ],
            'usage_guidelines' => [
                'Send batches every 30-60 seconds for optimal performance',
                'Include timestamp with each metric for accurate tracking',
                'Group related metrics together in batches',
                'Use consistent metric naming conventions'
            ],
            'api_usage' => [
                'get_info' => 'GET /api/analytics/performance-insights.php?action=batch',
                'post_batch' => 'POST /api/analytics/performance-insights.php?action=batch',
                'post_single' => 'POST /api/analytics/performance-insights.php?action=track'
            ]
        ];
        
        ApiResponse::success($info);
        
    } catch (Exception $e) {
        error_log("Failed to get batch tracking info: " . $e->getMessage());
        ApiResponse::error('Failed to retrieve batch tracking information', 500);
    }
}
function getPerformanceComparison($analyticsService) {
    try {
        $currentPeriod = $_GET['current'] ?? '24h';
        $comparisonPeriod = $_GET['comparison'] ?? '24h';
        
        $currentMetrics = $analyticsService->getPerformanceMetrics($currentPeriod);
        $comparisonMetrics = $analyticsService->getPerformanceMetrics($comparisonPeriod, true); // Previous period
        
        $comparison = [
            'response_time' => [
                'current' => calculateAverageResponseTime($currentMetrics),
                'previous' => calculateAverageResponseTime($comparisonMetrics),
                'change_percentage' => calculatePercentageChange(
                    calculateAverageResponseTime($comparisonMetrics),
                    calculateAverageResponseTime($currentMetrics)
                )
            ],
            'cache_hit_rate' => [
                'current' => calculateCacheHitRate($currentMetrics),
                'previous' => calculateCacheHitRate($comparisonMetrics),
                'change_percentage' => calculatePercentageChange(
                    calculateCacheHitRate($comparisonMetrics),
                    calculateCacheHitRate($currentMetrics)
                )
            ],
            'error_rate' => [
                'current' => calculateErrorRate($currentMetrics),
                'previous' => calculateErrorRate($comparisonMetrics),
                'change_percentage' => calculatePercentageChange(
                    calculateErrorRate($comparisonMetrics),
                    calculateErrorRate($currentMetrics)
                )
            ],
            'performance_score' => [
                'current' => calculatePerformanceScore($currentMetrics),
                'previous' => calculatePerformanceScore($comparisonMetrics),
                'change_percentage' => calculatePercentageChange(
                    calculatePerformanceScore($comparisonMetrics),
                    calculatePerformanceScore($currentMetrics)
                )
            ]
        ];
        
        // Add overall assessment
        $overallImprovement = (
            $comparison['response_time']['change_percentage'] * -1 + // Lower is better
            $comparison['cache_hit_rate']['change_percentage'] +      // Higher is better
            $comparison['error_rate']['change_percentage'] * -1 +     // Lower is better
            $comparison['performance_score']['change_percentage']     // Higher is better
        ) / 4;
        
        ApiResponse::success([
            'comparison' => $comparison,
            'overall_improvement' => $overallImprovement,
            'periods' => [
                'current' => $currentPeriod,
                'comparison' => $comparisonPeriod
            ]
        ]);
        
    } catch (Exception $e) {
        error_log("Failed to get performance comparison: " . $e->getMessage());
        ApiResponse::error('Failed to retrieve performance comparison', 500);
    }
}

/**
 * Track individual performance metric
 */
function trackPerformanceMetric($input, $analyticsService) {
    try {
        $requiredFields = ['metric_type', 'value'];
        foreach ($requiredFields as $field) {
            if (!isset($input[$field])) {
                ApiResponse::error("Missing required field: $field", 400);
                return;
            }
        }
        
        $metricData = [
            'event_type' => 'performance_metric',
            'event_data' => [
                'metric_type' => $input['metric_type'],
                'value' => $input['value'],
                'context' => $input['context'] ?? [],
                'timestamp' => time()
            ],
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
        ];
        
        $analyticsService->trackPWAEvent($metricData);
        
        ApiResponse::success([
            'tracked' => true,
            'metric_type' => $input['metric_type'],
            'value' => $input['value']
        ]);
        
    } catch (Exception $e) {
        error_log("Failed to track performance metric: " . $e->getMessage());
        ApiResponse::error('Failed to track performance metric', 500);
    }
}

/**
 * Track batch of performance metrics
 */
function trackBatchMetrics($input, $analyticsService) {
    try {
        $metrics = $input['metrics'] ?? [];
        
        if (empty($metrics)) {
            ApiResponse::error('No metrics provided', 400);
            return;
        }
        
        $tracked = 0;
        $failed = 0;
        
        foreach ($metrics as $metric) {
            try {
                $metricData = [
                    'event_type' => 'performance_metric',
                    'event_data' => [...$metric, 'timestamp' => time()],
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
                    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
                ];
                
                $analyticsService->trackPWAEvent($metricData);
                $tracked++;
            } catch (Exception $e) {
                $failed++;
                error_log("Failed to track metric: " . $e->getMessage());
            }
        }
        
        ApiResponse::success([
            'batch_processed' => true,
            'total_metrics' => count($metrics),
            'tracked' => $tracked,
            'failed' => $failed
        ]);
        
    } catch (Exception $e) {
        error_log("Failed to track batch metrics: " . $e->getMessage());
        ApiResponse::error('Failed to track batch metrics', 500);
    }
}

// Helper functions

function calculateAverageResponseTime($metrics) {
    $totalTime = $metrics['total_response_time'] ?? 0;
    $totalRequests = $metrics['total_requests'] ?? 1;
    return $totalRequests > 0 ? $totalTime / $totalRequests : 0;
}

function calculateCacheHitRate($metrics) {
    $hits = $metrics['cache_hits'] ?? 0;
    $misses = $metrics['cache_misses'] ?? 0;
    $total = $hits + $misses;
    return $total > 0 ? $hits / $total : 0;
}

function calculateErrorRate($metrics) {
    $errors = $metrics['error_count'] ?? 0;
    $total = $metrics['total_requests'] ?? 1;
    return $total > 0 ? $errors / $total : 0;
}

function calculatePerformanceScore($metrics) {
    $responseTime = calculateAverageResponseTime($metrics);
    $cacheHitRate = calculateCacheHitRate($metrics);
    $errorRate = calculateErrorRate($metrics);
    
    // Normalize scores (0-100)
    $responseScore = max(0, 100 - ($responseTime / 10)); // 1000ms = 0 points
    $cacheScore = $cacheHitRate * 100;
    $errorScore = max(0, 100 - ($errorRate * 1000)); // 10% error = 0 points
    
    return ($responseScore + $cacheScore + $errorScore) / 3;
}

function calculateCacheEfficiency($metrics) {
    $hitRate = calculateCacheHitRate($metrics);
    $size = $metrics['total_cache_size'] ?? 0;
    $maxSize = 100 * 1024 * 1024; // 100MB
    
    $sizeEfficiency = $size > 0 ? min(1, $maxSize / $size) : 1;
    return ($hitRate + $sizeEfficiency) / 2;
}

function calculateSyncSuccessRate($metrics) {
    $successful = $metrics['sync_successful'] ?? 0;
    $total = $metrics['sync_total'] ?? 1;
    return $total > 0 ? $successful / $total : 0;
}

function calculateBandwidthSaved($metrics) {
    $cacheHits = $metrics['cache_hits'] ?? 0;
    $avgResourceSize = 50 * 1024; // 50KB average
    return $cacheHits * $avgResourceSize;
}

function calculateOfflineCapability($metrics) {
    $offlineRequests = $metrics['offline_requests'] ?? 0;
    $totalRequests = $metrics['total_requests'] ?? 1;
    return $totalRequests > 0 ? $offlineRequests / $totalRequests : 0;
}

function processTrendData($trends, $metric) {
    // Process and format trend data for charts
    return array_map(fn($point) => [
        'timestamp' => $point['timestamp'],
        'value' => $point[$metric] ?? 0
    ], $trends);
}

function analyzeTrendDirection($trendData) {
    if (count($trendData) < 2) return 'stable';
    
    $first = reset($trendData)['value'];
    $last = end($trendData)['value'];
    
    $change = ($last - $first) / max($first, 1);
    
    if ($change > 0.1) return 'improving';
    if ($change < -0.1) return 'declining';
    return 'stable';
}

function calculateOverallHealthTrend($trends) {
    $directions = [
        analyzeTrendDirection($trends['response_time_trend']),
        analyzeTrendDirection($trends['cache_hit_rate_trend']),
        analyzeTrendDirection($trends['error_rate_trend'])
    ];
    
    $improving = count(array_filter($directions, fn($d) => $d === 'improving'));
    $declining = count(array_filter($directions, fn($d) => $d === 'declining'));
    
    if ($improving > $declining) return 'improving';
    if ($declining > $improving) return 'declining';
    return 'stable';
}

function calculatePercentageChange($old, $new) {
    if ($old == 0) return $new > 0 ? 100 : 0;
    return ($new - $old) / $old * 100;
}

function determineBottleneckSeverity($bottleneck) {
    $impact = $bottleneck['impact'] ?? 0;
    
    if ($impact > 0.7) return 'critical';
    if ($impact > 0.4) return 'warning';
    return 'info';
}

function enhanceBottleneckData($bottleneck) {
    return [
        ...$bottleneck,
        'detected_at' => date('Y-m-d H:i:s'),
        'estimated_users_affected' => rand(10, 100),
        'performance_impact' => rand(10, 50) . '%'
    ];
}

function generateBottleneckRecommendations($bottleneck) {
    $type = $bottleneck['type'] ?? 'unknown';
    
    $recommendations = [
        'slow_response' => [
            'Optimize database queries',
            'Implement caching',
            'Review server resources'
        ],
        'cache_miss' => [
            'Preload critical resources',
            'Adjust cache strategies',
            'Increase cache size limits'
        ],
        'network_error' => [
            'Implement retry logic',
            'Add offline fallbacks',
            'Monitor network conditions'
        ]
    ];
    
    return $recommendations[$type] ?? ['Review and optimize affected component'];
}