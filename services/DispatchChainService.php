<?php
/**
 * Dispatch Chain Service
 * Handles dispatch chain traceability for inventory items
 * 
 * Requirements: 9.1, 9.2, 9.3, 9.5
 * - 9.1: Display complete chain of dispatches and receives from origin to current holder
 * - 9.2: Show each transfer with sender, receiver, timestamps, and acceptance status
 * - 9.3: Maintain full history without data loss when item has been re-dispatched multiple times
 * - 9.5: Export item history that can be re-imported without data loss (round-trip consistency)
 */

require_once __DIR__ . '/../config/autoload.php';
require_once __DIR__ . '/../repositories/DispatchChainRepository.php';
require_once __DIR__ . '/../repositories/AssetRepository.php';
require_once __DIR__ . '/../repositories/DispatchRepository.php';
require_once __DIR__ . '/../repositories/ProductRepository.php';

class DispatchChainService {
    private $db;
    private $dispatchChainRepository;
    private $assetRepository;
    private $dispatchRepository;
    private $productRepository;
    
    /**
     * Constructor - inject required repositories
     * Requirements: 9.1, 9.2
     */
    public function __construct() {
        $this->db = DatabaseConfig::getInstance();
        $this->dispatchChainRepository = new DispatchChainRepository();
        $this->assetRepository = new AssetRepository();
        $this->dispatchRepository = new DispatchRepository();
        $this->dispatchRepository->disableCompanyFilter();
        $this->productRepository = new ProductRepository();
    }
    
    /**
     * Get item history for serializable items (by asset ID)
     * Returns complete dispatch chain for asset including all transfers
     * with sender, receiver, timestamps, and status
     * 
     * Requirements: 9.1, 9.2, 9.3
     * - Display complete chain of dispatches and receives from origin to current holder
     * - Show each transfer with sender, receiver, timestamps, and acceptance status
     * - Maintain full history without data loss
     * 
     * @param int $assetId Asset ID
     * @return array Result with item history
     */
    public function getItemHistory(int $assetId): array {
        try {
            // Validate asset exists
            $asset = $this->assetRepository->findWithDetails($assetId);
            if (!$asset) {
                return [
                    'success' => false,
                    'message' => 'Asset not found',
                    'code' => 'ASSET_NOT_FOUND'
                ];
            }
            
            // Get dispatch chain history
            $history = $this->dispatchChainRepository->getItemHistory($assetId);
            
            // Enrich history with additional details
            $enrichedHistory = $this->enrichHistoryEntries($history);
            
            return [
                'success' => true,
                'message' => 'Item history retrieved successfully',
                'data' => [
                    'asset' => [
                        'id' => $asset['id'],
                        'serial_number' => $asset['serial_number'],
                        'product_id' => $asset['product_id'],
                        'product_name' => $asset['product_name'],
                        'current_status' => $asset['status'],
                        'current_holder_type' => $asset['current_holder_type'],
                        'current_holder_id' => $asset['current_holder_id'],
                        'current_holder_name' => $asset['current_holder_name'] ?? null,
                        'source_warehouse_name' => $asset['source_warehouse_name'] ?? null
                    ],
                    'history' => $enrichedHistory,
                    'total_transfers' => count($enrichedHistory)
                ]
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to get item history: ' . $e->getMessage(),
                'code' => 'GET_HISTORY_ERROR'
            ];
        }
    }
    
    /**
     * Get product history for non-serializable items at a specific entity
     * Returns dispatch history for product aggregated by dispatch for quantity tracking
     * 
     * Requirements: 9.1, 9.2
     * - Display dispatch history for product at specific entity
     * - Aggregate by dispatch for quantity tracking
     * 
     * @param int $productId Product ID
     * @param string $entityType Entity type (warehouse, company, user)
     * @param int $entityId Entity ID
     * @return array Result with product history
     */
    public function getProductHistory(int $productId, string $entityType, int $entityId): array {
        try {
            // Validate product exists
            $product = $this->productRepository->find($productId);
            if (!$product) {
                return [
                    'success' => false,
                    'message' => 'Product not found',
                    'code' => 'PRODUCT_NOT_FOUND'
                ];
            }
            
            // Validate entity type
            if (!in_array($entityType, DispatchChainRepository::getEntityTypes())) {
                return [
                    'success' => false,
                    'message' => 'Invalid entity type',
                    'code' => 'INVALID_ENTITY_TYPE'
                ];
            }
            
            // Get product history at entity
            $history = $this->dispatchChainRepository->getProductHistory($productId, $entityType, $entityId);
            
            // Aggregate by dispatch for quantity tracking
            $aggregatedHistory = $this->aggregateProductHistory($history, $entityType, $entityId);
            
            // Calculate summary statistics
            $summary = $this->calculateProductSummary($aggregatedHistory, $entityType, $entityId);
            
            return [
                'success' => true,
                'message' => 'Product history retrieved successfully',
                'data' => [
                    'product' => [
                        'id' => $product['id'],
                        'name' => $product['name'],
                        'is_serializable' => $product['is_serializable'] ?? false
                    ],
                    'entity' => [
                        'type' => $entityType,
                        'id' => $entityId
                    ],
                    'history' => $aggregatedHistory,
                    'summary' => $summary
                ]
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to get product history: ' . $e->getMessage(),
                'code' => 'GET_HISTORY_ERROR'
            ];
        }
    }
    
    /**
     * Build dispatch chain from dispatch ID
     * Includes all related dispatches in chain
     * 
     * Requirements: 9.1, 9.2
     * - Build chain from dispatch ID
     * - Include all related dispatches in chain
     * 
     * @param int $dispatchId Dispatch ID
     * @return array Result with dispatch chain
     */
    public function buildDispatchChain(int $dispatchId): array {
        try {
            // Validate dispatch exists
            $dispatch = $this->dispatchRepository->findWithDetails($dispatchId);
            if (!$dispatch) {
                return [
                    'success' => false,
                    'message' => 'Dispatch not found',
                    'code' => 'DISPATCH_NOT_FOUND'
                ];
            }
            
            // Get chain entries for this dispatch
            $chainEntries = $this->dispatchChainRepository->findByDispatch($dispatchId);
            
            // Get dispatch items
            $dispatchItems = $this->dispatchRepository->getItems($dispatchId);
            
            // Build complete chain including related dispatches
            $chain = [
                'dispatch' => [
                    'id' => $dispatch['id'],
                    'dispatch_number' => $dispatch['dispatch_number'],
                    'dispatch_date' => $dispatch['dispatch_date'],
                    'status' => $dispatch['status'],
                    'acknowledgment_status' => $dispatch['acknowledgment_status'],
                    'from_warehouse_name' => $dispatch['from_warehouse_name'] ?? null,
                    'from_company_name' => $dispatch['from_company_name'] ?? null,
                    'to_company_name' => $dispatch['to_company_name'] ?? null,
                    'to_user_name' => $dispatch['to_user_name'] ?? null,
                    'to_warehouse_name' => $dispatch['to_warehouse_name'] ?? null,
                    'notes' => $dispatch['notes'] ?? null,
                    'created_by_name' => $dispatch['created_by_name'] ?? null
                ],
                'items' => $dispatchItems,
                'chain_entries' => $chainEntries,
                'related_dispatches' => []
            ];
            
            // Find related dispatches (previous and subsequent transfers)
            $relatedDispatches = $this->findRelatedDispatches($dispatchId, $chainEntries);
            $chain['related_dispatches'] = $relatedDispatches;
            
            return [
                'success' => true,
                'message' => 'Dispatch chain built successfully',
                'data' => $chain
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to build dispatch chain: ' . $e->getMessage(),
                'code' => 'BUILD_CHAIN_ERROR'
            ];
        }
    }

    
    /**
     * Export item history to JSON format
     * Includes all chain details for round-trip consistency
     * 
     * Requirement: 9.5
     * - Export item history that can be re-imported without data loss
     * 
     * @param int $assetId Asset ID
     * @param string $format Export format (default: json)
     * @return array Result with exported data
     */
    public function exportHistory(int $assetId, string $format = 'json'): array {
        try {
            // Get complete item history
            $historyResult = $this->getItemHistory($assetId);
            if (!$historyResult['success']) {
                return $historyResult;
            }
            
            // Build export structure
            $exportData = [
                'export_version' => '1.0',
                'export_timestamp' => date('Y-m-d\TH:i:s\Z'),
                'asset' => $historyResult['data']['asset'],
                'history' => $historyResult['data']['history'],
                'metadata' => [
                    'total_transfers' => $historyResult['data']['total_transfers'],
                    'export_format' => $format
                ]
            ];
            
            if ($format === 'json') {
                $exportedContent = json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                
                if ($exportedContent === false) {
                    return [
                        'success' => false,
                        'message' => 'Failed to encode history to JSON',
                        'code' => 'JSON_ENCODE_ERROR'
                    ];
                }
                
                return [
                    'success' => true,
                    'message' => 'History exported successfully',
                    'data' => [
                        'content' => $exportedContent,
                        'format' => $format,
                        'filename' => "asset_{$assetId}_history_" . date('Ymd_His') . '.json'
                    ]
                ];
            }
            
            return [
                'success' => false,
                'message' => "Unsupported export format: $format",
                'code' => 'UNSUPPORTED_FORMAT'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to export history: ' . $e->getMessage(),
                'code' => 'EXPORT_ERROR'
            ];
        }
    }
    
    /**
     * Import item history from JSON format
     * Parses and validates imported data for round-trip consistency
     * 
     * Requirement: 9.5
     * - Parse and validate imported data
     * - Verify round-trip consistency
     * 
     * @param string $data Imported data string
     * @param string $format Import format (default: json)
     * @return array Result with validation status
     */
    public function importHistory(string $data, string $format = 'json'): array {
        try {
            if ($format !== 'json') {
                return [
                    'success' => false,
                    'message' => "Unsupported import format: $format",
                    'code' => 'UNSUPPORTED_FORMAT'
                ];
            }
            
            // Parse JSON data
            $importedData = json_decode($data, true);
            if ($importedData === null && json_last_error() !== JSON_ERROR_NONE) {
                return [
                    'success' => false,
                    'message' => 'Invalid JSON format: ' . json_last_error_msg(),
                    'code' => 'JSON_PARSE_ERROR'
                ];
            }
            
            // Validate required fields
            $validationResult = $this->validateImportedData($importedData);
            if (!$validationResult['success']) {
                return $validationResult;
            }
            
            // Verify round-trip consistency if asset exists
            $assetId = $importedData['asset']['id'] ?? null;
            if ($assetId) {
                $consistencyResult = $this->verifyRoundTripConsistency($assetId, $importedData);
                if (!$consistencyResult['success']) {
                    return $consistencyResult;
                }
            }
            
            return [
                'success' => true,
                'message' => 'History imported and validated successfully',
                'data' => [
                    'asset' => $importedData['asset'],
                    'history_count' => count($importedData['history'] ?? []),
                    'export_version' => $importedData['export_version'] ?? 'unknown',
                    'export_timestamp' => $importedData['export_timestamp'] ?? null,
                    'is_consistent' => true
                ]
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to import history: ' . $e->getMessage(),
                'code' => 'IMPORT_ERROR'
            ];
        }
    }
    
    /**
     * Enrich history entries with additional details
     * 
     * @param array $history Raw history entries
     * @return array Enriched history entries
     */
    private function enrichHistoryEntries(array $history): array {
        $enriched = [];
        
        foreach ($history as $entry) {
            $enrichedEntry = [
                'id' => $entry['id'],
                'sequence_number' => $entry['sequence_number'],
                'dispatch_id' => $entry['dispatch_id'],
                'dispatch_number' => $entry['dispatch_number'] ?? null,
                'from' => [
                    'entity_type' => $entry['from_entity_type'],
                    'entity_id' => $entry['from_entity_id'],
                    'entity_name' => $entry['from_entity_name'] ?? null
                ],
                'to' => [
                    'entity_type' => $entry['to_entity_type'],
                    'entity_id' => $entry['to_entity_id'],
                    'entity_name' => $entry['to_entity_name'] ?? null
                ],
                'quantity' => $entry['quantity'],
                'dispatch_date' => $entry['dispatch_date'],
                'acceptance_date' => $entry['acceptance_date'],
                'status' => $entry['status'],
                'product_name' => $entry['product_name'] ?? null
            ];
            
            $enriched[] = $enrichedEntry;
        }
        
        return $enriched;
    }
    
    /**
     * Aggregate product history by dispatch for quantity tracking
     * 
     * @param array $history Raw history entries
     * @param string $entityType Entity type
     * @param int $entityId Entity ID
     * @return array Aggregated history
     */
    private function aggregateProductHistory(array $history, string $entityType, int $entityId): array {
        $aggregated = [];
        
        foreach ($history as $entry) {
            $dispatchId = $entry['dispatch_id'];
            
            if (!isset($aggregated[$dispatchId])) {
                $aggregated[$dispatchId] = [
                    'dispatch_id' => $dispatchId,
                    'dispatch_number' => $entry['dispatch_number'] ?? null,
                    'dispatch_date' => $entry['dispatch_date'],
                    'status' => $entry['status'],
                    'acceptance_date' => $entry['acceptance_date'],
                    'from' => [
                        'entity_type' => $entry['from_entity_type'],
                        'entity_id' => $entry['from_entity_id'],
                        'entity_name' => $entry['from_entity_name'] ?? null
                    ],
                    'to' => [
                        'entity_type' => $entry['to_entity_type'],
                        'entity_id' => $entry['to_entity_id'],
                        'entity_name' => $entry['to_entity_name'] ?? null
                    ],
                    'total_quantity' => 0,
                    'direction' => $this->determineDirection($entry, $entityType, $entityId)
                ];
            }
            
            $aggregated[$dispatchId]['total_quantity'] += $entry['quantity'];
        }
        
        return array_values($aggregated);
    }
    
    /**
     * Determine direction of transfer relative to entity
     * 
     * @param array $entry History entry
     * @param string $entityType Entity type
     * @param int $entityId Entity ID
     * @return string Direction (incoming, outgoing)
     */
    private function determineDirection(array $entry, string $entityType, int $entityId): string {
        if ($entry['to_entity_type'] === $entityType && $entry['to_entity_id'] == $entityId) {
            return 'incoming';
        }
        return 'outgoing';
    }
    
    /**
     * Calculate product summary statistics
     * 
     * @param array $aggregatedHistory Aggregated history
     * @param string $entityType Entity type
     * @param int $entityId Entity ID
     * @return array Summary statistics
     */
    private function calculateProductSummary(array $aggregatedHistory, string $entityType, int $entityId): array {
        $totalIncoming = 0;
        $totalOutgoing = 0;
        $pendingIncoming = 0;
        $pendingOutgoing = 0;
        
        foreach ($aggregatedHistory as $entry) {
            if ($entry['direction'] === 'incoming') {
                if ($entry['status'] === DispatchChainRepository::STATUS_ACCEPTED) {
                    $totalIncoming += $entry['total_quantity'];
                } elseif ($entry['status'] === DispatchChainRepository::STATUS_DISPATCHED) {
                    $pendingIncoming += $entry['total_quantity'];
                }
            } else {
                if ($entry['status'] === DispatchChainRepository::STATUS_ACCEPTED) {
                    $totalOutgoing += $entry['total_quantity'];
                } elseif ($entry['status'] === DispatchChainRepository::STATUS_DISPATCHED) {
                    $pendingOutgoing += $entry['total_quantity'];
                }
            }
        }
        
        return [
            'total_incoming' => $totalIncoming,
            'total_outgoing' => $totalOutgoing,
            'pending_incoming' => $pendingIncoming,
            'pending_outgoing' => $pendingOutgoing,
            'net_quantity' => $totalIncoming - $totalOutgoing,
            'total_transfers' => count($aggregatedHistory)
        ];
    }

    
    /**
     * Find related dispatches for a given dispatch
     * 
     * @param int $dispatchId Current dispatch ID
     * @param array $chainEntries Chain entries for current dispatch
     * @return array Related dispatches
     */
    private function findRelatedDispatches(int $dispatchId, array $chainEntries): array {
        $relatedDispatches = [];
        $processedDispatchIds = [$dispatchId];
        
        foreach ($chainEntries as $entry) {
            // For serializable items, find other dispatches in the chain
            if (!empty($entry['asset_id'])) {
                $assetHistory = $this->dispatchChainRepository->getItemHistory($entry['asset_id']);
                
                foreach ($assetHistory as $historyEntry) {
                    $relatedDispatchId = $historyEntry['dispatch_id'];
                    
                    if (!in_array($relatedDispatchId, $processedDispatchIds)) {
                        $processedDispatchIds[] = $relatedDispatchId;
                        
                        $relatedDispatch = $this->dispatchRepository->findWithDetails($relatedDispatchId);
                        if ($relatedDispatch) {
                            $relatedDispatches[] = [
                                'id' => $relatedDispatch['id'],
                                'dispatch_number' => $relatedDispatch['dispatch_number'],
                                'dispatch_date' => $relatedDispatch['dispatch_date'],
                                'status' => $relatedDispatch['status'],
                                'relationship' => $historyEntry['sequence_number'] < $entry['sequence_number'] ? 'previous' : 'subsequent'
                            ];
                        }
                    }
                }
            }
        }
        
        // Sort by dispatch date
        usort($relatedDispatches, function($a, $b) {
            return strtotime($a['dispatch_date']) - strtotime($b['dispatch_date']);
        });
        
        return $relatedDispatches;
    }
    
    /**
     * Validate imported data structure
     * 
     * @param array $data Imported data
     * @return array Validation result
     */
    private function validateImportedData(array $data): array {
        // Check required top-level fields
        $requiredFields = ['asset', 'history'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field])) {
                return [
                    'success' => false,
                    'message' => "Missing required field: $field",
                    'code' => 'MISSING_FIELD'
                ];
            }
        }
        
        // Validate asset structure
        $assetRequiredFields = ['id', 'serial_number', 'product_id'];
        foreach ($assetRequiredFields as $field) {
            if (!isset($data['asset'][$field])) {
                return [
                    'success' => false,
                    'message' => "Missing required asset field: $field",
                    'code' => 'MISSING_ASSET_FIELD'
                ];
            }
        }
        
        // Validate history entries
        if (!is_array($data['history'])) {
            return [
                'success' => false,
                'message' => 'History must be an array',
                'code' => 'INVALID_HISTORY_FORMAT'
            ];
        }
        
        foreach ($data['history'] as $index => $entry) {
            $entryRequiredFields = ['dispatch_id', 'from', 'to', 'status'];
            foreach ($entryRequiredFields as $field) {
                if (!isset($entry[$field])) {
                    return [
                        'success' => false,
                        'message' => "History entry $index missing required field: $field",
                        'code' => 'MISSING_HISTORY_FIELD'
                    ];
                }
            }
        }
        
        return ['success' => true];
    }
    
    /**
     * Verify round-trip consistency between imported data and current database state
     * 
     * @param int $assetId Asset ID
     * @param array $importedData Imported data
     * @return array Consistency check result
     */
    private function verifyRoundTripConsistency(int $assetId, array $importedData): array {
        // Get current history from database
        $currentHistoryResult = $this->getItemHistory($assetId);
        
        if (!$currentHistoryResult['success']) {
            // Asset doesn't exist in database - this is valid for new imports
            return ['success' => true, 'is_new_asset' => true];
        }
        
        $currentHistory = $currentHistoryResult['data']['history'];
        $importedHistory = $importedData['history'];
        
        // Compare history counts
        if (count($currentHistory) !== count($importedHistory)) {
            return [
                'success' => false,
                'message' => 'History count mismatch: database has ' . count($currentHistory) . 
                            ' entries, imported has ' . count($importedHistory),
                'code' => 'HISTORY_COUNT_MISMATCH'
            ];
        }
        
        // Compare each history entry
        foreach ($currentHistory as $index => $currentEntry) {
            $importedEntry = $importedHistory[$index] ?? null;
            
            if (!$importedEntry) {
                return [
                    'success' => false,
                    'message' => "Missing history entry at index $index",
                    'code' => 'MISSING_HISTORY_ENTRY'
                ];
            }
            
            // Compare key fields
            if ($currentEntry['dispatch_id'] != $importedEntry['dispatch_id']) {
                return [
                    'success' => false,
                    'message' => "Dispatch ID mismatch at index $index",
                    'code' => 'DISPATCH_ID_MISMATCH'
                ];
            }
            
            if ($currentEntry['status'] !== $importedEntry['status']) {
                return [
                    'success' => false,
                    'message' => "Status mismatch at index $index",
                    'code' => 'STATUS_MISMATCH'
                ];
            }
        }
        
        return ['success' => true, 'is_consistent' => true];
    }
    
    /**
     * Get current holder of an asset from dispatch chain
     * 
     * @param int $assetId Asset ID
     * @return array Result with current holder info
     */
    public function getCurrentHolder(int $assetId): array {
        try {
            $holder = $this->dispatchChainRepository->getCurrentHolder($assetId);
            
            if (!$holder) {
                // Check asset's original location
                $asset = $this->assetRepository->findWithDetails($assetId);
                if ($asset) {
                    return [
                        'success' => true,
                        'message' => 'Asset is at original location',
                        'data' => [
                            'entity_type' => $asset['current_holder_type'] ?? 'warehouse',
                            'entity_id' => $asset['current_holder_id'] ?? $asset['warehouse_id'],
                            'entity_name' => $asset['current_holder_name'] ?? $asset['warehouse_name']
                        ]
                    ];
                }
                
                return [
                    'success' => false,
                    'message' => 'Asset not found',
                    'code' => 'ASSET_NOT_FOUND'
                ];
            }
            
            return [
                'success' => true,
                'message' => 'Current holder retrieved',
                'data' => [
                    'entity_type' => $holder['to_entity_type'],
                    'entity_id' => $holder['to_entity_id'],
                    'entity_name' => $holder['holder_name']
                ]
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to get current holder: ' . $e->getMessage(),
                'code' => 'GET_HOLDER_ERROR'
            ];
        }
    }
    
    /**
     * Get chain depth (number of transfers) for an asset
     * 
     * @param int $assetId Asset ID
     * @return array Result with chain depth
     */
    public function getChainDepth(int $assetId): array {
        try {
            $depth = $this->dispatchChainRepository->getChainDepth($assetId);
            
            return [
                'success' => true,
                'message' => 'Chain depth retrieved',
                'data' => [
                    'asset_id' => $assetId,
                    'depth' => $depth
                ]
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to get chain depth: ' . $e->getMessage(),
                'code' => 'GET_DEPTH_ERROR'
            ];
        }
    }
    
    /**
     * Get dispatch chain summary statistics
     * 
     * @param int|null $productId Optional product filter
     * @return array Result with summary statistics
     */
    public function getChainSummary(?int $productId = null): array {
        try {
            $summary = $this->dispatchChainRepository->getSummary($productId);
            
            return [
                'success' => true,
                'message' => 'Chain summary retrieved',
                'data' => $summary
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to get chain summary: ' . $e->getMessage(),
                'code' => 'GET_SUMMARY_ERROR'
            ];
        }
    }
}
