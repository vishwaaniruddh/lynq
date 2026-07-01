<?php
/**
 * Receive Service
 * Handles pending receive operations for inventory items
 * 
 * Requirements: 2.1, 3.1, 4.1
 * - 2.1: Display dispatch in contractor's/engineer's pending receives list
 * - 3.1: Increment recipient's inventory counter when materials are accepted
 * - 4.1: Restore sender's inventory counter when materials are rejected
 */

require_once __DIR__ . '/../config/autoload.php';
require_once __DIR__ . '/../repositories/PendingReceiveRepository.php';
require_once __DIR__ . '/../repositories/PendingReceiveItemRepository.php';
require_once __DIR__ . '/../repositories/DispatchRepository.php';
require_once __DIR__ . '/../repositories/DispatchItemRepository.php';
require_once __DIR__ . '/../repositories/AssetRepository.php';
require_once __DIR__ . '/../repositories/DiscrepancyRepository.php';
require_once __DIR__ . '/../repositories/DispatchChainRepository.php';
require_once __DIR__ . '/../repositories/InventoryNotificationRepository.php';
require_once __DIR__ . '/../repositories/InventoryAuditLogRepository.php';
require_once __DIR__ . '/../repositories/UserRepository.php';
require_once __DIR__ . '/InventoryCounterService.php';

class ReceiveService {
    private $db;
    private $conn;
    private $pendingReceiveRepository;
    private $pendingReceiveItemRepository;
    private $dispatchRepository;
    private $dispatchItemRepository;
    private $assetRepository;
    private $discrepancyRepository;
    private $dispatchChainRepository;
    private $notificationRepository;
    private $auditLogRepository;
    private $userRepository;
    private $inventoryCounterService;
    
    /**
     * Constructor - inject dependencies
     * Requirements: 2.1, 3.1, 4.1
     */
    public function __construct() {
        $this->db = DatabaseConfig::getInstance();
        $this->conn = $this->db->getConnection();
        $this->pendingReceiveRepository = new PendingReceiveRepository();
        $this->pendingReceiveItemRepository = new PendingReceiveItemRepository();
        $this->dispatchRepository = new DispatchRepository();
        $this->dispatchRepository->disableCompanyFilter();
        $this->dispatchItemRepository = new DispatchItemRepository();
        $this->assetRepository = new AssetRepository();
        $this->discrepancyRepository = new DiscrepancyRepository();
        $this->dispatchChainRepository = new DispatchChainRepository();
        $this->notificationRepository = new InventoryNotificationRepository();
        $this->auditLogRepository = new InventoryAuditLogRepository();
        $this->userRepository = new UserRepository();
        $this->inventoryCounterService = new InventoryCounterService();
    }


    /**
     * Get pending receives for an entity
     * Returns pending receives with dispatch details, item details, and sender information
     * 
     * Requirements: 2.1, 2.2, 2.3
     * - Display dispatch in contractor's pending receives list when items dispatched to contractor
     * - Display dispatch in engineer's pending receives list when items dispatched to engineer
     * - Show dispatch details including sender, items, quantities, and dispatch date
     * 
     * @param string $entityType Entity type (warehouse, company, user)
     * @param int $entityId Entity ID
     * @param string|null $status Optional status filter
     * @return array Result with pending receives
     */
    public function getPendingReceives(string $entityType, int $entityId, ?string $status = null): array {
        // Validate entity type
        if (!PendingReceiveRepository::isValidRecipientType($entityType)) {
            return [
                'success' => false,
                'message' => "Invalid entity type: $entityType",
                'code' => 'INVALID_ENTITY_TYPE',
                'data' => []
            ];
        }
        
        // Validate status if provided
        if ($status !== null && !PendingReceiveRepository::isValidStatus($status)) {
            return [
                'success' => false,
                'message' => "Invalid status: $status",
                'code' => 'INVALID_STATUS',
                'data' => []
            ];
        }
        
        try {
            // Get pending receives for the entity
            $pendingReceives = $this->pendingReceiveRepository->findByRecipient($entityType, $entityId, $status);
            
            // Enrich each pending receive with items
            foreach ($pendingReceives as &$pendingReceive) {
                // Get items for this pending receive
                $pendingReceive['items'] = $this->pendingReceiveItemRepository->findByPendingReceive($pendingReceive['id']);
                
                // Calculate totals
                $totalExpected = 0;
                $totalReceived = 0;
                foreach ($pendingReceive['items'] as $item) {
                    $totalExpected += $item['expected_quantity'];
                    $totalReceived += $item['received_quantity'];
                }
                $pendingReceive['total_expected_quantity'] = $totalExpected;
                $pendingReceive['total_received_quantity'] = $totalReceived;
                
                // Mark as overdue if applicable
                $pendingReceive['is_overdue'] = $pendingReceive['status'] === PendingReceiveRepository::STATUS_PENDING 
                    && $pendingReceive['days_pending'] > PendingReceiveRepository::DEFAULT_OVERDUE_DAYS;
            }
            
            return [
                'success' => true,
                'message' => 'Pending receives retrieved successfully',
                'data' => [
                    'pending_receives' => $pendingReceives,
                    'count' => count($pendingReceives)
                ]
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to retrieve pending receives: ' . $e->getMessage(),
                'code' => 'GET_PENDING_RECEIVES_ERROR',
                'data' => []
            ];
        }
    }


    /**
     * Accept a pending receive
     * Validates pending receive exists and is in pending status
     * Increments recipient's inventory counter
     * Updates dispatch and pending receive status to accepted
     * Updates asset current_holder for serializable items
     * Creates dispatch chain entry
     * Sends acceptance notification to sender
     * 
     * Requirements: 3.1, 3.2, 3.3, 3.4, 3.5
     * - Increment contractor's inventory counter when materials are accepted
     * - Increment engineer's inventory counter when materials are accepted
     * - Update dispatch status to "delivered" and acknowledgment status to "acknowledged"
     * - Record acceptance timestamp and accepting user
     * - Update asset's current holder to accepting entity for serializable items
     * 
     * @param int $pendingReceiveId Pending receive ID
     * @param int $userId User ID accepting the receive
     * @return array Result with success status
     */
    public function acceptReceive(int $pendingReceiveId, int $userId): array {
        // Get pending receive with details
        $pendingReceive = $this->pendingReceiveRepository->findWithItems($pendingReceiveId);
        
        if (!$pendingReceive) {
            return [
                'success' => false,
                'message' => 'Pending receive not found',
                'code' => 'PENDING_RECEIVE_NOT_FOUND'
            ];
        }
        
        // Validate status is pending
        if ($pendingReceive['status'] !== PendingReceiveRepository::STATUS_PENDING) {
            return [
                'success' => false,
                'message' => "Cannot accept pending receive with status: {$pendingReceive['status']}",
                'code' => 'INVALID_STATUS'
            ];
        }
        
        try {
            $this->conn->begin_transaction();
            
            $dispatchId = $pendingReceive['dispatch_id'];
            $recipientType = $pendingReceive['recipient_type'];
            $recipientId = $pendingReceive['recipient_id'];
            
            // Get dispatch details for sender info
            $dispatch = $this->dispatchRepository->findWithDetails($dispatchId);
            if (!$dispatch) {
                $this->conn->rollback();
                return [
                    'success' => false,
                    'message' => 'Dispatch not found',
                    'code' => 'DISPATCH_NOT_FOUND'
                ];
            }
            
            // Get dispatch items
            $dispatchItems = $this->dispatchItemRepository->findAllByDispatchWithDetails($dispatchId);
            
            // Process each item
            foreach ($dispatchItems as $item) {
                $productId = $item['product_id'];
                $quantity = $item['quantity'];
                $assetId = $item['asset_id'];
                
                // Increment recipient's inventory counter
                $incrementResult = $this->inventoryCounterService->incrementCounter(
                    $recipientType,
                    $recipientId,
                    $productId,
                    $quantity,
                    $userId,
                    'receive_accepted'
                );
                
                if (!$incrementResult['success']) {
                    $this->conn->rollback();
                    return $incrementResult;
                }
                
                // Update asset current_holder for serializable items
                if ($assetId) {
                    $updateData = [
                        'current_holder_type' => $recipientType,
                        'current_holder_id' => $recipientId,
                        'updated_by' => $userId
                    ];
                    
                    // If recipient is a warehouse, set status to in_stock and update warehouse_id
                    if ($recipientType === 'warehouse') {
                        $updateData['status'] = AssetRepository::STATUS_IN_STOCK;
                        $updateData['warehouse_id'] = $recipientId;
                    } else {
                        // For company/user recipients, set status to assigned and clear warehouse_id
                        $updateData['status'] = AssetRepository::STATUS_ASSIGNED;
                        $updateData['warehouse_id'] = null;
                    }
                    
                    $this->assetRepository->update($assetId, $updateData);
                }
                
                // Create dispatch chain entry with accepted status
                $this->dispatchChainRepository->addToChain([
                    'asset_id' => $assetId,
                    'product_id' => $productId,
                    'dispatch_id' => $dispatchId,
                    'from_entity_type' => $this->getSenderType($dispatch),
                    'from_entity_id' => $this->getSenderId($dispatch),
                    'to_entity_type' => $recipientType,
                    'to_entity_id' => $recipientId,
                    'quantity' => $quantity,
                    'dispatch_date' => $dispatch['dispatch_date'],
                    'acceptance_date' => date('Y-m-d H:i:s'),
                    'status' => DispatchChainRepository::STATUS_ACCEPTED
                ]);
            }
            
            // Accept all pending receive items
            $this->pendingReceiveItemRepository->acceptAllForPendingReceive($pendingReceiveId);
            
            // Update pending receive status to accepted
            $this->pendingReceiveRepository->accept($pendingReceiveId, $userId);
            
            // Update dispatch status to delivered and acknowledgment status
            $this->dispatchRepository->update($dispatchId, [
                'status' => DispatchRepository::STATUS_DELIVERED,
                'acknowledgment_status' => DispatchRepository::ACK_ACKNOWLEDGED,
                'acknowledged_at' => date('Y-m-d H:i:s'),
                'acknowledged_by' => $userId
            ]);
            
            // Send acceptance notification to sender
            $this->sendAcceptanceNotification($dispatch, $pendingReceive, $userId);
            
            // Log audit entry
            $this->logAuditEntry(
                'receive_accepted',
                'pending_receive',
                $pendingReceiveId,
                $userId,
                $this->getSenderType($dispatch),
                $this->getSenderId($dispatch),
                $recipientType,
                $recipientId,
                [
                    'dispatch_id' => $dispatchId,
                    'dispatch_number' => $dispatch['dispatch_number'],
                    'item_count' => count($dispatchItems)
                ]
            );
            
            $this->conn->commit();
            
            return [
                'success' => true,
                'message' => 'Pending receive accepted successfully',
                'data' => [
                    'pending_receive_id' => $pendingReceiveId,
                    'dispatch_id' => $dispatchId,
                    'status' => PendingReceiveRepository::STATUS_ACCEPTED,
                    'accepted_at' => date('Y-m-d H:i:s')
                ]
            ];
            
        } catch (Exception $e) {
            $this->conn->rollback();
            return [
                'success' => false,
                'message' => 'Failed to accept pending receive: ' . $e->getMessage(),
                'code' => 'ACCEPT_ERROR'
            ];
        }
    }


    /**
     * Reject a pending receive
     * Validates rejection reason is provided
     * Restores sender's inventory counter
     * Updates asset status back to in_stock for serializable items
     * Updates dispatch status to rejected
     * Creates dispatch chain entry with rejected status
     * Sends rejection notification to sender
     * 
     * Requirements: 4.1, 4.2, 4.3, 4.4, 4.5
     * - Restore items to sender's inventory counter when contractor rejects
     * - Restore items to sender's inventory counter when engineer rejects
     * - Require rejection reason when materials are rejected
     * - Update dispatch status to "rejected" and record the reason
     * - Update asset status back to "in_stock" at sender's location for serializable items
     * 
     * @param int $pendingReceiveId Pending receive ID
     * @param int $userId User ID rejecting the receive
     * @param string $reason Rejection reason (required)
     * @return array Result with success status
     */
    public function rejectReceive(int $pendingReceiveId, int $userId, string $reason): array {
        // Validate rejection reason is provided
        if (empty(trim($reason))) {
            return [
                'success' => false,
                'message' => 'Rejection reason is required',
                'code' => 'REASON_REQUIRED'
            ];
        }
        
        // Get pending receive with details
        $pendingReceive = $this->pendingReceiveRepository->findWithItems($pendingReceiveId);
        
        if (!$pendingReceive) {
            return [
                'success' => false,
                'message' => 'Pending receive not found',
                'code' => 'PENDING_RECEIVE_NOT_FOUND'
            ];
        }
        
        // Validate status is pending
        if ($pendingReceive['status'] !== PendingReceiveRepository::STATUS_PENDING) {
            return [
                'success' => false,
                'message' => "Cannot reject pending receive with status: {$pendingReceive['status']}",
                'code' => 'INVALID_STATUS'
            ];
        }
        
        try {
            $this->conn->begin_transaction();
            
            $dispatchId = $pendingReceive['dispatch_id'];
            $recipientType = $pendingReceive['recipient_type'];
            $recipientId = $pendingReceive['recipient_id'];
            
            // Get dispatch details for sender info
            $dispatch = $this->dispatchRepository->findWithDetails($dispatchId);
            if (!$dispatch) {
                $this->conn->rollback();
                return [
                    'success' => false,
                    'message' => 'Dispatch not found',
                    'code' => 'DISPATCH_NOT_FOUND'
                ];
            }
            
            $senderType = $this->getSenderType($dispatch);
            $senderId = $this->getSenderId($dispatch);
            
            // Get dispatch items
            $dispatchItems = $this->dispatchItemRepository->findAllByDispatchWithDetails($dispatchId);
            
            // Process each item - restore to sender's inventory
            foreach ($dispatchItems as $item) {
                $productId = $item['product_id'];
                $quantity = $item['quantity'];
                $assetId = $item['asset_id'];
                
                // Restore sender's inventory counter
                $incrementResult = $this->inventoryCounterService->incrementCounter(
                    $senderType,
                    $senderId,
                    $productId,
                    $quantity,
                    $userId,
                    'receive_rejected'
                );
                
                if (!$incrementResult['success']) {
                    $this->conn->rollback();
                    return $incrementResult;
                }
                
                // Update asset status back to in_stock at sender's location for serializable items
                if ($assetId) {
                    $restoreData = [
                        'current_holder_type' => $senderType,
                        'current_holder_id' => $senderId,
                        'updated_by' => $userId
                    ];
                    
                    // If sender is a warehouse, restore warehouse_id and set in_stock
                    if ($senderType === 'warehouse') {
                        $restoreData['status'] = AssetRepository::STATUS_IN_STOCK;
                        $restoreData['warehouse_id'] = $senderId;
                    } else {
                        // For company/user senders, set status to assigned
                        $restoreData['status'] = AssetRepository::STATUS_ASSIGNED;
                        $restoreData['warehouse_id'] = null;
                    }
                    
                    $this->assetRepository->update($assetId, $restoreData);
                }
                
                // Create dispatch chain entry with rejected status
                $this->dispatchChainRepository->addToChain([
                    'asset_id' => $assetId,
                    'product_id' => $productId,
                    'dispatch_id' => $dispatchId,
                    'from_entity_type' => $senderType,
                    'from_entity_id' => $senderId,
                    'to_entity_type' => $recipientType,
                    'to_entity_id' => $recipientId,
                    'quantity' => $quantity,
                    'dispatch_date' => $dispatch['dispatch_date'],
                    'acceptance_date' => date('Y-m-d H:i:s'),
                    'status' => DispatchChainRepository::STATUS_REJECTED
                ]);
            }
            
            // Reject all pending receive items
            $this->pendingReceiveItemRepository->rejectAllForPendingReceive($pendingReceiveId, $reason);
            
            // Update pending receive status to rejected
            $this->pendingReceiveRepository->reject($pendingReceiveId, $userId, $reason);
            
            // Update dispatch status to rejected (using cancelled as closest status)
            $this->dispatchRepository->update($dispatchId, [
                'status' => DispatchRepository::STATUS_CANCELLED,
                'notes' => ($dispatch['notes'] ? $dispatch['notes'] . "\n" : '') . "Rejected: $reason"
            ]);
            
            // Send rejection notification to sender
            $this->sendRejectionNotification($dispatch, $pendingReceive, $userId, $reason);
            
            // Log audit entry
            $this->logAuditEntry(
                'receive_rejected',
                'pending_receive',
                $pendingReceiveId,
                $userId,
                $senderType,
                $senderId,
                $recipientType,
                $recipientId,
                [
                    'dispatch_id' => $dispatchId,
                    'dispatch_number' => $dispatch['dispatch_number'],
                    'rejection_reason' => $reason,
                    'item_count' => count($dispatchItems)
                ]
            );
            
            $this->conn->commit();
            
            return [
                'success' => true,
                'message' => 'Pending receive rejected successfully',
                'data' => [
                    'pending_receive_id' => $pendingReceiveId,
                    'dispatch_id' => $dispatchId,
                    'status' => PendingReceiveRepository::STATUS_REJECTED,
                    'rejection_reason' => $reason,
                    'rejected_at' => date('Y-m-d H:i:s')
                ]
            ];
            
        } catch (Exception $e) {
            $this->conn->rollback();
            return [
                'success' => false,
                'message' => 'Failed to reject pending receive: ' . $e->getMessage(),
                'code' => 'REJECT_ERROR'
            ];
        }
    }


    /**
     * Partially accept a pending receive
     * Accepts specified quantities per item
     * Creates discrepancy records for differences
     * Updates recipient inventory for accepted quantities only
     * Sends discrepancy notification to sender
     * 
     * Requirements: 10.1, 10.2, 10.3, 10.4, 10.5
     * - Allow specifying quantities actually received for non-serializable items
     * - Update recipient's inventory only for accepted quantities
     * - Create discrepancy record for the difference
     * - Notify sender of the discrepancy
     * - Require selection of specific serial numbers received for serializable items
     * 
     * @param int $pendingReceiveId Pending receive ID
     * @param int $userId User ID accepting
     * @param array $acceptedItems Array of items with received quantities
     *              Format: [['dispatch_item_id' => int, 'received_quantity' => int, 'notes' => string|null], ...]
     * @param string|null $notes Optional notes for the partial acceptance
     * @return array Result with success status
     */
    public function partialAccept(int $pendingReceiveId, int $userId, array $acceptedItems, ?string $notes = null): array {
        // Validate accepted items array
        if (empty($acceptedItems)) {
            return [
                'success' => false,
                'message' => 'Accepted items array is required',
                'code' => 'NO_ITEMS'
            ];
        }
        
        // Get pending receive with details
        $pendingReceive = $this->pendingReceiveRepository->findWithItems($pendingReceiveId);
        
        if (!$pendingReceive) {
            return [
                'success' => false,
                'message' => 'Pending receive not found',
                'code' => 'PENDING_RECEIVE_NOT_FOUND'
            ];
        }
        
        // Validate status is pending
        if ($pendingReceive['status'] !== PendingReceiveRepository::STATUS_PENDING) {
            return [
                'success' => false,
                'message' => "Cannot partially accept pending receive with status: {$pendingReceive['status']}",
                'code' => 'INVALID_STATUS'
            ];
        }
        
        try {
            $this->conn->begin_transaction();
            
            $dispatchId = $pendingReceive['dispatch_id'];
            $recipientType = $pendingReceive['recipient_type'];
            $recipientId = $pendingReceive['recipient_id'];
            
            // Get dispatch details
            $dispatch = $this->dispatchRepository->findWithDetails($dispatchId);
            if (!$dispatch) {
                $this->conn->rollback();
                return [
                    'success' => false,
                    'message' => 'Dispatch not found',
                    'code' => 'DISPATCH_NOT_FOUND'
                ];
            }
            
            $senderType = $this->getSenderType($dispatch);
            $senderId = $this->getSenderId($dispatch);
            
            // Get dispatch items indexed by ID
            $dispatchItems = $this->dispatchItemRepository->findAllByDispatchWithDetails($dispatchId);
            $dispatchItemsById = [];
            foreach ($dispatchItems as $item) {
                $dispatchItemsById[$item['id']] = $item;
            }
            
            // Get pending receive items indexed by dispatch_item_id
            $pendingReceiveItems = $pendingReceive['items'];
            $pendingReceiveItemsByDispatchItemId = [];
            foreach ($pendingReceiveItems as $item) {
                $pendingReceiveItemsByDispatchItemId[$item['dispatch_item_id']] = $item;
            }
            
            $discrepancies = [];
            $totalAccepted = 0;
            $totalExpected = 0;
            
            // Process each accepted item
            foreach ($acceptedItems as $acceptedItem) {
                if (!isset($acceptedItem['dispatch_item_id']) || !isset($acceptedItem['received_quantity'])) {
                    $this->conn->rollback();
                    return [
                        'success' => false,
                        'message' => 'Each accepted item must have dispatch_item_id and received_quantity',
                        'code' => 'INVALID_ITEM_FORMAT'
                    ];
                }
                
                $dispatchItemId = $acceptedItem['dispatch_item_id'];
                $receivedQuantity = (int)$acceptedItem['received_quantity'];
                $itemNotes = $acceptedItem['notes'] ?? null;
                
                // Validate dispatch item exists
                if (!isset($dispatchItemsById[$dispatchItemId])) {
                    $this->conn->rollback();
                    return [
                        'success' => false,
                        'message' => "Dispatch item not found: $dispatchItemId",
                        'code' => 'DISPATCH_ITEM_NOT_FOUND'
                    ];
                }
                
                $dispatchItem = $dispatchItemsById[$dispatchItemId];
                $expectedQuantity = $dispatchItem['quantity'];
                $productId = $dispatchItem['product_id'];
                $assetId = $dispatchItem['asset_id'];
                
                $totalExpected += $expectedQuantity;
                $totalAccepted += $receivedQuantity;
                
                // Validate received quantity
                if ($receivedQuantity < 0) {
                    $this->conn->rollback();
                    return [
                        'success' => false,
                        'message' => 'Received quantity cannot be negative',
                        'code' => 'INVALID_QUANTITY'
                    ];
                }
                
                if ($receivedQuantity > $expectedQuantity) {
                    $this->conn->rollback();
                    return [
                        'success' => false,
                        'message' => "Received quantity ($receivedQuantity) cannot exceed expected quantity ($expectedQuantity)",
                        'code' => 'QUANTITY_EXCEEDS_EXPECTED'
                    ];
                }
                
                // Update pending receive item
                if (isset($pendingReceiveItemsByDispatchItemId[$dispatchItemId])) {
                    $pendingReceiveItem = $pendingReceiveItemsByDispatchItemId[$dispatchItemId];
                    $this->pendingReceiveItemRepository->updateReceivedQuantity(
                        $pendingReceiveItem['id'],
                        $receivedQuantity,
                        $itemNotes
                    );
                }
                
                // Increment recipient's inventory counter for accepted quantity only
                if ($receivedQuantity > 0) {
                    $incrementResult = $this->inventoryCounterService->incrementCounter(
                        $recipientType,
                        $recipientId,
                        $productId,
                        $receivedQuantity,
                        $userId,
                        'partial_receive_accepted'
                    );
                    
                    if (!$incrementResult['success']) {
                        $this->conn->rollback();
                        return $incrementResult;
                    }
                    
                    // Update asset current_holder for serializable items if accepted
                    if ($assetId) {
                        $updateData = [
                            'current_holder_type' => $recipientType,
                            'current_holder_id' => $recipientId,
                            'updated_by' => $userId
                        ];
                        
                        // If recipient is a warehouse, set status to in_stock and update warehouse_id
                        if ($recipientType === 'warehouse') {
                            $updateData['status'] = AssetRepository::STATUS_IN_STOCK;
                            $updateData['warehouse_id'] = $recipientId;
                        } else {
                            // For company/user recipients, set status to assigned and clear warehouse_id
                            $updateData['status'] = AssetRepository::STATUS_ASSIGNED;
                            $updateData['warehouse_id'] = null;
                        }
                        
                        $this->assetRepository->update($assetId, $updateData);
                    }
                }
                
                // Create discrepancy record if quantities differ
                if ($receivedQuantity != $expectedQuantity) {
                    $discrepancy = $this->discrepancyRepository->createFromPartialAcceptance(
                        $dispatchId,
                        $pendingReceiveId,
                        $productId,
                        $expectedQuantity,
                        $receivedQuantity,
                        $assetId,
                        $itemNotes
                    );
                    $discrepancies[] = $discrepancy;
                    
                    // Restore the difference to sender's inventory
                    $difference = $expectedQuantity - $receivedQuantity;
                    if ($difference > 0) {
                        $restoreResult = $this->inventoryCounterService->incrementCounter(
                            $senderType,
                            $senderId,
                            $productId,
                            $difference,
                            $userId,
                            'partial_receive_discrepancy'
                        );
                        
                        if (!$restoreResult['success']) {
                            $this->conn->rollback();
                            return $restoreResult;
                        }
                        
                        // If serializable item was not received, restore to sender
                        if ($assetId && $receivedQuantity == 0) {
                            $restoreData = [
                                'current_holder_type' => $senderType,
                                'current_holder_id' => $senderId,
                                'updated_by' => $userId
                            ];
                            
                            // If sender is a warehouse, restore warehouse_id and set in_stock
                            if ($senderType === 'warehouse') {
                                $restoreData['status'] = AssetRepository::STATUS_IN_STOCK;
                                $restoreData['warehouse_id'] = $senderId;
                            } else {
                                // For company/user senders, set status to assigned
                                $restoreData['status'] = AssetRepository::STATUS_ASSIGNED;
                                $restoreData['warehouse_id'] = null;
                            }
                            
                            $this->assetRepository->update($assetId, $restoreData);
                        }
                    }
                }
                
                // Create dispatch chain entry
                $chainStatus = $receivedQuantity > 0 
                    ? DispatchChainRepository::STATUS_ACCEPTED 
                    : DispatchChainRepository::STATUS_REJECTED;
                    
                $this->dispatchChainRepository->addToChain([
                    'asset_id' => $assetId,
                    'product_id' => $productId,
                    'dispatch_id' => $dispatchId,
                    'from_entity_type' => $senderType,
                    'from_entity_id' => $senderId,
                    'to_entity_type' => $recipientType,
                    'to_entity_id' => $recipientId,
                    'quantity' => $receivedQuantity,
                    'dispatch_date' => $dispatch['dispatch_date'],
                    'acceptance_date' => date('Y-m-d H:i:s'),
                    'status' => $chainStatus
                ]);
            }
            
            // Update pending receive status to partial
            $this->pendingReceiveRepository->partialAccept($pendingReceiveId, $userId);
            
            // Update dispatch status
            $this->dispatchRepository->update($dispatchId, [
                'status' => DispatchRepository::STATUS_DELIVERED,
                'acknowledgment_status' => DispatchRepository::ACK_ACKNOWLEDGED,
                'acknowledged_at' => date('Y-m-d H:i:s'),
                'acknowledged_by' => $userId,
                'notes' => ($dispatch['notes'] ? $dispatch['notes'] . "\n" : '') . 
                    "Partial acceptance: $totalAccepted of $totalExpected items received" .
                    ($notes ? ". Notes: $notes" : '')
            ]);
            
            // Send discrepancy notification to sender
            if (!empty($discrepancies)) {
                $this->sendDiscrepancyNotification($dispatch, $pendingReceive, $userId, $discrepancies);
            }
            
            // Log audit entry
            $this->logAuditEntry(
                'receive_partial_accepted',
                'pending_receive',
                $pendingReceiveId,
                $userId,
                $senderType,
                $senderId,
                $recipientType,
                $recipientId,
                [
                    'dispatch_id' => $dispatchId,
                    'dispatch_number' => $dispatch['dispatch_number'],
                    'total_expected' => $totalExpected,
                    'total_accepted' => $totalAccepted,
                    'discrepancy_count' => count($discrepancies),
                    'notes' => $notes
                ]
            );
            
            $this->conn->commit();
            
            return [
                'success' => true,
                'message' => 'Pending receive partially accepted',
                'data' => [
                    'pending_receive_id' => $pendingReceiveId,
                    'dispatch_id' => $dispatchId,
                    'status' => PendingReceiveRepository::STATUS_PARTIAL,
                    'total_expected' => $totalExpected,
                    'total_accepted' => $totalAccepted,
                    'discrepancies' => $discrepancies,
                    'accepted_at' => date('Y-m-d H:i:s')
                ]
            ];
            
        } catch (Exception $e) {
            $this->conn->rollback();
            return [
                'success' => false,
                'message' => 'Failed to partially accept pending receive: ' . $e->getMessage(),
                'code' => 'PARTIAL_ACCEPT_ERROR'
            ];
        }
    }


    /**
     * Bulk accept multiple pending receives
     * Processes multiple pending receives in a single transaction
     * Rolls back all on any failure
     * Returns summary of processed items
     * 
     * Requirements: 12.3, 12.4, 12.5
     * - Process multiple pending receives in a single operation
     * - Provide summary of all processed items when bulk operations complete
     * - Rollback all changes and report failure when bulk operations fail mid-transaction
     * 
     * @param array $pendingReceiveIds Array of pending receive IDs to accept
     * @param int $userId User ID accepting
     * @return array Result with success status and summary
     */
    public function bulkAccept(array $pendingReceiveIds, int $userId): array {
        if (empty($pendingReceiveIds)) {
            return [
                'success' => false,
                'message' => 'No pending receive IDs provided',
                'code' => 'NO_IDS'
            ];
        }
        
        // Validate all pending receives before processing
        $validationErrors = [];
        $pendingReceives = [];
        
        foreach ($pendingReceiveIds as $id) {
            $pendingReceive = $this->pendingReceiveRepository->findWithItems($id);
            
            if (!$pendingReceive) {
                $validationErrors[] = [
                    'id' => $id,
                    'error' => 'Pending receive not found'
                ];
                continue;
            }
            
            if ($pendingReceive['status'] !== PendingReceiveRepository::STATUS_PENDING) {
                $validationErrors[] = [
                    'id' => $id,
                    'error' => "Invalid status: {$pendingReceive['status']}"
                ];
                continue;
            }
            
            $pendingReceives[$id] = $pendingReceive;
        }
        
        // If any validation errors, return without processing
        if (!empty($validationErrors)) {
            return [
                'success' => false,
                'message' => 'Validation failed for one or more pending receives',
                'code' => 'VALIDATION_FAILED',
                'data' => [
                    'errors' => $validationErrors
                ]
            ];
        }
        
        try {
            $this->conn->begin_transaction();
            
            $processedItems = [];
            $totalItemsAccepted = 0;
            
            foreach ($pendingReceives as $id => $pendingReceive) {
                $dispatchId = $pendingReceive['dispatch_id'];
                $recipientType = $pendingReceive['recipient_type'];
                $recipientId = $pendingReceive['recipient_id'];
                
                // Get dispatch details
                $dispatch = $this->dispatchRepository->findWithDetails($dispatchId);
                if (!$dispatch) {
                    $this->conn->rollback();
                    return [
                        'success' => false,
                        'message' => "Dispatch not found for pending receive $id",
                        'code' => 'DISPATCH_NOT_FOUND'
                    ];
                }
                
                // Get dispatch items
                $dispatchItems = $this->dispatchItemRepository->findAllByDispatchWithDetails($dispatchId);
                
                // Process each item
                foreach ($dispatchItems as $item) {
                    $productId = $item['product_id'];
                    $quantity = $item['quantity'];
                    $assetId = $item['asset_id'];
                    
                    // Increment recipient's inventory counter
                    $incrementResult = $this->inventoryCounterService->incrementCounter(
                        $recipientType,
                        $recipientId,
                        $productId,
                        $quantity,
                        $userId,
                        'bulk_receive_accepted'
                    );
                    
                    if (!$incrementResult['success']) {
                        $this->conn->rollback();
                        return [
                            'success' => false,
                            'message' => "Failed to increment counter for pending receive $id: " . $incrementResult['message'],
                            'code' => 'INCREMENT_FAILED'
                        ];
                    }
                    
                    // Update asset current_holder for serializable items
                    if ($assetId) {
                        $updateData = [
                            'current_holder_type' => $recipientType,
                            'current_holder_id' => $recipientId,
                            'updated_by' => $userId
                        ];
                        
                        // If recipient is a warehouse, set status to in_stock and update warehouse_id
                        if ($recipientType === 'warehouse') {
                            $updateData['status'] = AssetRepository::STATUS_IN_STOCK;
                            $updateData['warehouse_id'] = $recipientId;
                        } else {
                            // For company/user recipients, set status to assigned and clear warehouse_id
                            $updateData['status'] = AssetRepository::STATUS_ASSIGNED;
                            $updateData['warehouse_id'] = null;
                        }
                        
                        $this->assetRepository->update($assetId, $updateData);
                    }
                    
                    // Create dispatch chain entry
                    $this->dispatchChainRepository->addToChain([
                        'asset_id' => $assetId,
                        'product_id' => $productId,
                        'dispatch_id' => $dispatchId,
                        'from_entity_type' => $this->getSenderType($dispatch),
                        'from_entity_id' => $this->getSenderId($dispatch),
                        'to_entity_type' => $recipientType,
                        'to_entity_id' => $recipientId,
                        'quantity' => $quantity,
                        'dispatch_date' => $dispatch['dispatch_date'],
                        'acceptance_date' => date('Y-m-d H:i:s'),
                        'status' => DispatchChainRepository::STATUS_ACCEPTED
                    ]);
                    
                    $totalItemsAccepted++;
                }
                
                // Accept all pending receive items
                $this->pendingReceiveItemRepository->acceptAllForPendingReceive($id);
                
                // Update pending receive status
                $this->pendingReceiveRepository->accept($id, $userId);
                
                // Update dispatch status
                $this->dispatchRepository->update($dispatchId, [
                    'status' => DispatchRepository::STATUS_DELIVERED,
                    'acknowledgment_status' => DispatchRepository::ACK_ACKNOWLEDGED,
                    'acknowledged_at' => date('Y-m-d H:i:s'),
                    'acknowledged_by' => $userId
                ]);
                
                // Send acceptance notification
                $this->sendAcceptanceNotification($dispatch, $pendingReceive, $userId);
                
                $processedItems[] = [
                    'pending_receive_id' => $id,
                    'dispatch_id' => $dispatchId,
                    'dispatch_number' => $dispatch['dispatch_number'],
                    'item_count' => count($dispatchItems),
                    'status' => PendingReceiveRepository::STATUS_ACCEPTED
                ];
            }
            
            // Log audit entry for bulk operation
            $this->logAuditEntry(
                'bulk_receive_accepted',
                'pending_receive',
                0, // No single entity ID for bulk
                $userId,
                null, null, null, null,
                [
                    'pending_receive_ids' => $pendingReceiveIds,
                    'total_receives_processed' => count($processedItems),
                    'total_items_accepted' => $totalItemsAccepted
                ]
            );
            
            $this->conn->commit();
            
            return [
                'success' => true,
                'message' => 'Bulk accept completed successfully',
                'data' => [
                    'processed' => $processedItems,
                    'total_receives' => count($processedItems),
                    'total_items' => $totalItemsAccepted
                ]
            ];
            
        } catch (Exception $e) {
            $this->conn->rollback();
            return [
                'success' => false,
                'message' => 'Bulk accept failed: ' . $e->getMessage(),
                'code' => 'BULK_ACCEPT_ERROR'
            ];
        }
    }


    /**
     * Get overdue pending receives
     * Returns pending receives older than threshold with days overdue
     * 
     * Requirement: 2.5
     * - Highlight pending receives older than configurable threshold as overdue
     * 
     * @param int $thresholdDays Number of days after which a pending receive is considered overdue
     * @param string|null $recipientType Optional filter by recipient type
     * @param int|null $recipientId Optional filter by recipient ID
     * @return array Result with overdue pending receives
     */
    public function getOverdueReceives(int $thresholdDays = PendingReceiveRepository::DEFAULT_OVERDUE_DAYS, ?string $recipientType = null, ?int $recipientId = null): array {
        // Validate threshold
        if ($thresholdDays < 0) {
            return [
                'success' => false,
                'message' => 'Threshold days cannot be negative',
                'code' => 'INVALID_THRESHOLD',
                'data' => []
            ];
        }
        
        // Validate recipient type if provided
        if ($recipientType !== null && !PendingReceiveRepository::isValidRecipientType($recipientType)) {
            return [
                'success' => false,
                'message' => "Invalid recipient type: $recipientType",
                'code' => 'INVALID_RECIPIENT_TYPE',
                'data' => []
            ];
        }
        
        try {
            $overdueReceives = $this->pendingReceiveRepository->findOverdue($thresholdDays, $recipientType, $recipientId);
            
            // Enrich each overdue receive with items
            foreach ($overdueReceives as &$receive) {
                $receive['items'] = $this->pendingReceiveItemRepository->findByPendingReceive($receive['id']);
                
                // Calculate totals
                $totalExpected = 0;
                foreach ($receive['items'] as $item) {
                    $totalExpected += $item['expected_quantity'];
                }
                $receive['total_expected_quantity'] = $totalExpected;
                $receive['days_overdue'] = $receive['days_pending'] - $thresholdDays;
            }
            
            return [
                'success' => true,
                'message' => 'Overdue receives retrieved successfully',
                'data' => [
                    'overdue_receives' => $overdueReceives,
                    'count' => count($overdueReceives),
                    'threshold_days' => $thresholdDays
                ]
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to retrieve overdue receives: ' . $e->getMessage(),
                'code' => 'GET_OVERDUE_ERROR',
                'data' => []
            ];
        }
    }

    /**
     * Get sender type from dispatch
     * 
     * @param array $dispatch Dispatch data
     * @return string Sender type
     */
    private function getSenderType(array $dispatch): string {
        // Check for sender_type column first (new schema)
        if (!empty($dispatch['sender_type'])) {
            return $dispatch['sender_type'];
        }
        
        // Fall back to inferring from warehouse/company
        if (!empty($dispatch['from_warehouse_id'])) {
            return 'warehouse';
        }
        if (!empty($dispatch['from_company_id'])) {
            return 'company';
        }
        
        return 'warehouse'; // Default
    }

    /**
     * Get sender ID from dispatch
     * 
     * @param array $dispatch Dispatch data
     * @return int|null Sender ID
     */
    private function getSenderId(array $dispatch): ?int {
        // Check for sender_id column first (new schema)
        if (!empty($dispatch['sender_id'])) {
            return (int)$dispatch['sender_id'];
        }
        
        // Fall back to inferring from warehouse/company
        if (!empty($dispatch['from_warehouse_id'])) {
            return (int)$dispatch['from_warehouse_id'];
        }
        if (!empty($dispatch['from_company_id'])) {
            return (int)$dispatch['from_company_id'];
        }
        
        return null;
    }

    /**
     * Send acceptance notification to sender
     * 
     * @param array $dispatch Dispatch data
     * @param array $pendingReceive Pending receive data
     * @param int $userId User who accepted
     */
    private function sendAcceptanceNotification(array $dispatch, array $pendingReceive, int $userId): void {
        try {
            // Get recipient name
            $recipientName = $this->getRecipientName($pendingReceive['recipient_type'], $pendingReceive['recipient_id']);
            
            // Get sender user ID to notify
            $senderUserId = $this->getSenderUserId($dispatch);
            
            if ($senderUserId) {
                $this->notificationRepository->createAcceptanceNotification(
                    $senderUserId,
                    $dispatch['id'],
                    $pendingReceive['id'],
                    $recipientName
                );
            }
        } catch (Exception $e) {
            error_log("Failed to send acceptance notification: " . $e->getMessage());
        }
    }

    /**
     * Send rejection notification to sender
     * 
     * @param array $dispatch Dispatch data
     * @param array $pendingReceive Pending receive data
     * @param int $userId User who rejected
     * @param string $reason Rejection reason
     */
    private function sendRejectionNotification(array $dispatch, array $pendingReceive, int $userId, string $reason): void {
        try {
            // Get recipient name
            $recipientName = $this->getRecipientName($pendingReceive['recipient_type'], $pendingReceive['recipient_id']);
            
            // Get sender user ID to notify
            $senderUserId = $this->getSenderUserId($dispatch);
            
            if ($senderUserId) {
                $this->notificationRepository->createRejectionNotification(
                    $senderUserId,
                    $dispatch['id'],
                    $pendingReceive['id'],
                    $recipientName,
                    $reason
                );
            }
        } catch (Exception $e) {
            error_log("Failed to send rejection notification: " . $e->getMessage());
        }
    }

    /**
     * Send discrepancy notification to sender
     * 
     * @param array $dispatch Dispatch data
     * @param array $pendingReceive Pending receive data
     * @param int $userId User who reported discrepancy
     * @param array $discrepancies Array of discrepancy records
     */
    private function sendDiscrepancyNotification(array $dispatch, array $pendingReceive, int $userId, array $discrepancies): void {
        try {
            // Get recipient name
            $recipientName = $this->getRecipientName($pendingReceive['recipient_type'], $pendingReceive['recipient_id']);
            
            // Get sender user ID to notify
            $senderUserId = $this->getSenderUserId($dispatch);
            
            if ($senderUserId) {
                // Build discrepancy details
                $discrepancyDetails = count($discrepancies) . " item(s) with quantity discrepancies";
                
                $this->notificationRepository->createDiscrepancyNotification(
                    $senderUserId,
                    $dispatch['id'],
                    $pendingReceive['id'],
                    $recipientName,
                    $discrepancyDetails
                );
            }
        } catch (Exception $e) {
            error_log("Failed to send discrepancy notification: " . $e->getMessage());
        }
    }

    /**
     * Get recipient name based on type and ID
     * 
     * @param string $recipientType Recipient type
     * @param int $recipientId Recipient ID
     * @return string Recipient name
     */
    private function getRecipientName(string $recipientType, int $recipientId): string {
        try {
            switch ($recipientType) {
                case 'user':
                    $user = $this->userRepository->find($recipientId);
                    return $user ? trim($user['first_name'] . ' ' . $user['last_name']) : 'Unknown User';
                    
                case 'company':
                    $sql = "SELECT name FROM companies WHERE id = ?";
                    $result = $this->db->getResults($sql, [$recipientId], 'i');
                    return !empty($result) ? $result[0]['name'] : 'Unknown Company';
                    
                case 'warehouse':
                    $sql = "SELECT name FROM warehouses WHERE id = ?";
                    $result = $this->db->getResults($sql, [$recipientId], 'i');
                    return !empty($result) ? $result[0]['name'] : 'Unknown Warehouse';
                    
                default:
                    return 'Unknown';
            }
        } catch (Exception $e) {
            return 'Unknown';
        }
    }

    /**
     * Get sender user ID from dispatch for notifications
     * 
     * @param array $dispatch Dispatch data
     * @return int|null User ID to notify
     */
    private function getSenderUserId(array $dispatch): ?int {
        // If dispatch was created by a user, notify them
        if (!empty($dispatch['created_by'])) {
            return (int)$dispatch['created_by'];
        }
        
        return null;
    }

    /**
     * Log audit entry
     * 
     * @param string $actionType Action type
     * @param string $entityType Entity type
     * @param int $entityId Entity ID
     * @param int|null $userId User ID
     * @param string|null $fromLocationType From location type
     * @param int|null $fromLocationId From location ID
     * @param string|null $toLocationType To location type
     * @param int|null $toLocationId To location ID
     * @param array|null $details Additional details
     */
    private function logAuditEntry(
        string $actionType,
        string $entityType,
        int $entityId,
        ?int $userId,
        ?string $fromLocationType,
        ?int $fromLocationId,
        ?string $toLocationType,
        ?int $toLocationId,
        ?array $details = null
    ): void {
        try {
            $this->auditLogRepository->create([
                'action_type' => $actionType,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'user_id' => $userId ?? 0,
                'from_location_type' => $fromLocationType,
                'from_location_id' => $fromLocationId,
                'to_location_type' => $toLocationType,
                'to_location_id' => $toLocationId,
                'new_values' => $details ? json_encode($details) : null,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'CLI'
            ]);
        } catch (Exception $e) {
            error_log("Failed to log audit entry: " . $e->getMessage());
        }
    }
}
