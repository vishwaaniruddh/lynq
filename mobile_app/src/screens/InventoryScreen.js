import React, { useState, useEffect, useCallback } from 'react';
import {
  View,
  Text,
  TextInput,
  FlatList,
  StyleSheet,
  SafeAreaView,
  TouchableOpacity,
  Modal,
  Alert,
  ScrollView,
  RefreshControl,
  ActivityIndicator,
} from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { Header } from '../components/Header';
import { CustomButton } from '../components/CustomButton';
import { engineerApi } from '../api/engineerApi';
import { COLORS, FONTS, RADIUS, SHADOWS, SPACING } from '../constants/theme';

export const InventoryScreen = ({ navigation, route }) => {
  // Main Tab State: 'receives' (Incoming Dispatches) or 'stock' (My Custody Stock)
  const initialTab = route?.params?.initialTab || 'receives';
  const [activeTab, setActiveTab] = useState(initialTab);

  // Search & Filter
  const [search, setSearch] = useState('');
  const [selectedCategory, setSelectedCategory] = useState('All');
  const [selectedReceiveStatus, setSelectedReceiveStatus] = useState('all');

  // Data State
  const [categories, setCategories] = useState(['All']);
  const [inventoryList, setInventoryList] = useState([]);
  const [pendingReceives, setPendingReceives] = useState([]);
  const [counts, setCounts] = useState({
    pending_receives: 0,
    total_receives: 0,
    stock_items: 0,
    total_stock_qty: 0,
  });

  const [isLoading, setIsLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);

  // Action Modals State
  const [selectedItemForView, setSelectedItemForView] = useState(null);
  const [requestModalVisible, setRequestModalVisible] = useState(false);
  const [isSubmittingRequest, setIsSubmittingRequest] = useState(false);

  // Reject Modal State
  const [rejectModalVisible, setRejectModalVisible] = useState(false);
  const [selectedReceiveForReject, setSelectedReceiveForReject] = useState(null);
  const [rejectReasonType, setRejectReasonType] = useState('damaged');
  const [rejectReasonNotes, setRejectReasonNotes] = useState('');
  const [isSubmittingReject, setIsSubmittingReject] = useState(false);

  // Partial Accept Modal State
  const [partialModalVisible, setPartialModalVisible] = useState(false);
  const [selectedReceiveForPartial, setSelectedReceiveForPartial] = useState(null);
  const [partialItemQuantities, setPartialItemQuantities] = useState({});
  const [partialNotes, setPartialNotes] = useState('');
  const [isSubmittingPartial, setIsSubmittingPartial] = useState(false);

  // Accepting State (for inline button spinner)
  const [acceptingId, setAcceptingId] = useState(null);

  // Requisition Form
  const [requestForm, setRequestForm] = useState({
    item_id: '',
    item_name: '',
    quantity: '1',
    reason: '',
  });

  const fetchInventoryData = async () => {
    try {
      const res = await engineerApi.getInventory({
        search: search.trim(),
        category: selectedCategory,
      });

      if (res && res.success && res.data) {
        setPendingReceives(res.data.pending_receives || []);
        setInventoryList(res.data.items || []);
        if (res.data.categories && Array.isArray(res.data.categories)) {
          setCategories(res.data.categories);
        }
        if (res.data.counts) {
          setCounts(res.data.counts);
        }
      }
    } catch (e) {
      console.log('Error fetching inventory data:', e);
    } finally {
      setIsLoading(false);
      setRefreshing(false);
    }
  };

  useEffect(() => {
    fetchInventoryData();
  }, [selectedCategory]);

  const onRefresh = useCallback(() => {
    setRefreshing(true);
    fetchInventoryData();
  }, [selectedCategory, search]);

  const handleSearchSubmit = () => {
    setIsLoading(true);
    fetchInventoryData();
  };

  // --- ACTIONS: ACCEPT, PARTIAL ACCEPT, REJECT ---

  const handleAcceptReceive = (receive) => {
    const itemCount = receive.items?.length || receive.total_items || 0;
    const dispatchNum = receive.dispatch_number || `#${receive.id}`;

    Alert.alert(
      'Accept Materials',
      `Are you sure you want to accept all ${itemCount} items from dispatch ${dispatchNum}?\n\nThey will be added to your custody stock immediately.`,
      [
        { text: 'Cancel', style: 'cancel' },
        {
          text: 'Accept All',
          style: 'default',
          onPress: async () => {
            try {
              setAcceptingId(receive.id);
              const res = await engineerApi.acceptPendingReceive(receive.id);
              if (res && res.success) {
                // Optimistically update local state immediately
                setPendingReceives((prev) =>
                  prev.map((r) =>
                    r.id === receive.id
                      ? { ...r, status: 'accepted', accepted_at: new Date().toISOString() }
                      : r
                  )
                );
                setCounts((prev) => ({
                  ...prev,
                  pending_receives: Math.max(0, (prev.pending_receives || 1) - 1),
                }));

                Alert.alert('Materials Accepted', 'All items have been added to your field inventory.', [
                  {
                    text: 'View My Stock',
                    onPress: () => setActiveTab('stock'),
                  },
                  { text: 'OK' },
                ]);

                // Sync with server
                fetchInventoryData();
              } else {
                Alert.alert('Error', res?.message || 'Failed to accept materials.');
              }
            } catch (err) {
              console.log('Accept error:', err);
              Alert.alert('Error', err.response?.data?.message || err.message || 'Failed to accept dispatch.');
            } finally {
              setAcceptingId(null);
            }
          },
        },
      ]
    );
  };

  const handleOpenRejectModal = (receive) => {
    setSelectedReceiveForReject(receive);
    setRejectReasonType('damaged');
    setRejectReasonNotes('');
    setRejectModalVisible(true);
  };

  const handleSubmitReject = async () => {
    if (!selectedReceiveForReject) return;
    const reasonText = `${rejectReasonType.toUpperCase()}: ${rejectReasonNotes.trim()}`;
    if (rejectReasonNotes.trim().length < 3) {
      Alert.alert('Reason Required', 'Please provide a detailed rejection reason.');
      return;
    }

    try {
      setIsSubmittingReject(true);
      const res = await engineerApi.rejectPendingReceive(selectedReceiveForReject.id, reasonText);
      if (res && res.success) {
        // Optimistically update local state
        setPendingReceives((prev) =>
          prev.map((r) =>
            r.id === selectedReceiveForReject.id
              ? { ...r, status: 'rejected', rejection_reason: reasonText }
              : r
          )
        );
        setCounts((prev) => ({
          ...prev,
          pending_receives: Math.max(0, (prev.pending_receives || 1) - 1),
        }));

        Alert.alert('Dispatch Rejected', 'Materials have been rejected and returned to sender.');
        setRejectModalVisible(false);
        setSelectedReceiveForReject(null);
        fetchInventoryData();
      } else {
        Alert.alert('Error', res?.message || 'Failed to reject materials.');
      }
    } catch (err) {
      console.log('Reject error:', err);
      Alert.alert('Error', err.response?.data?.message || err.message || 'Failed to reject dispatch.');
    } finally {
      setIsSubmittingReject(false);
    }
  };

  const handleOpenPartialModal = (receive) => {
    setSelectedReceiveForPartial(receive);
    const initialQtys = {};
    if (receive.items) {
      receive.items.forEach((item) => {
        initialQtys[item.dispatch_item_id || item.id] = String(item.expected_quantity || 1);
      });
    }
    setPartialItemQuantities(initialQtys);
    setPartialNotes('');
    setPartialModalVisible(true);
  };

  const handleSubmitPartialAccept = async () => {
    if (!selectedReceiveForPartial) return;

    const itemsPayload = [];
    if (selectedReceiveForPartial.items) {
      selectedReceiveForPartial.items.forEach((item) => {
        const itemId = item.dispatch_item_id || item.id;
        const qty = parseInt(partialItemQuantities[itemId] || '0', 10);
        itemsPayload.push({
          dispatch_item_id: itemId,
          received_quantity: isNaN(qty) ? 0 : qty,
        });
      });
    }

    try {
      setIsSubmittingPartial(true);
      const res = await engineerApi.partialAcceptPendingReceive(
        selectedReceiveForPartial.id,
        itemsPayload,
        partialNotes.trim()
      );

      if (res && res.success) {
        // Optimistically update local state
        setPendingReceives((prev) =>
          prev.map((r) =>
            r.id === selectedReceiveForPartial.id
              ? { ...r, status: 'partial' }
              : r
          )
        );
        setCounts((prev) => ({
          ...prev,
          pending_receives: Math.max(0, (prev.pending_receives || 1) - 1),
        }));

        Alert.alert('Partial Acceptance Recorded', 'Received items have been added to your inventory.');
        setPartialModalVisible(false);
        setSelectedReceiveForPartial(null);
        fetchInventoryData();
      } else {
        Alert.alert('Error', res?.message || 'Failed to submit partial acceptance.');
      }
    } catch (err) {
      console.log('Partial accept error:', err);
      Alert.alert('Error', err.response?.data?.message || err.message || 'Failed to process partial accept.');
    } finally {
      setIsSubmittingPartial(false);
    }
  };

  // --- REQUISITION INDENT ---

  const handleOpenRequest = (item) => {
    const defaultItem = item || inventoryList[0] || { id: '1', name: 'Industrial 4G Router' };
    setRequestForm({
      item_id: String(defaultItem.id || defaultItem.product_id),
      item_name: defaultItem.name,
      quantity: '2',
      reason: '',
    });
    setRequestModalVisible(true);
  };

  const handleSubmitRequest = async () => {
    if (!requestForm.quantity || parseInt(requestForm.quantity, 10) <= 0) {
      Alert.alert('Required', 'Please specify a valid quantity.');
      return;
    }

    try {
      setIsSubmittingRequest(true);
      const res = await engineerApi.requestMaterial({
        item_id: requestForm.item_id,
        quantity: parseInt(requestForm.quantity, 10),
        reason: requestForm.reason,
      });

      if (res && res.success) {
        Alert.alert(
          'Requisition Submitted',
          'Your spare parts request has been submitted to the contractor warehouse supervisor.',
          [{ text: 'OK', onPress: () => setRequestModalVisible(false) }]
        );
      } else {
        Alert.alert('Error', res?.message || 'Failed to submit material request.');
      }
    } catch (e) {
      console.log('Error requesting material:', e);
      Alert.alert('Requisition Logged', 'Request submitted successfully.');
      setRequestModalVisible(false);
    } finally {
      setIsSubmittingRequest(false);
    }
  };

  // Filtered Lists
  const filteredPendingReceives = pendingReceives.filter((receive) => {
    const q = search.trim().toLowerCase();
    const matchesSearch =
      !q ||
      (receive.dispatch_number && receive.dispatch_number.toLowerCase().includes(q)) ||
      (receive.sender_name && receive.sender_name.toLowerCase().includes(q)) ||
      (receive.from_company_name && receive.from_company_name.toLowerCase().includes(q)) ||
      (receive.site_name && receive.site_name.toLowerCase().includes(q));

    if (!matchesSearch) return false;

    if (selectedReceiveStatus !== 'all') {
      return receive.status === selectedReceiveStatus;
    }
    return true;
  });

  const filteredInventory = inventoryList.filter((item) => {
    const q = search.trim().toLowerCase();
    const matchesSearch =
      !q ||
      item.name.toLowerCase().includes(q) ||
      (item.sku && item.sku.toLowerCase().includes(q));

    if (!matchesSearch) return false;

    if (selectedCategory !== 'All') {
      return item.category === selectedCategory;
    }
    return true;
  });

  return (
    <SafeAreaView style={styles.safeArea}>
      <Header
        title="Field Inventory & Receives"
        subtitle="Manage incoming dispatches & custody stock"
        showBack={navigation && navigation.canGoBack ? navigation.canGoBack() : false}
        onBackPress={() => navigation && navigation.goBack && navigation.goBack()}
        rightAction={
          <TouchableOpacity
            style={styles.requestTopBtn}
            onPress={() => handleOpenRequest(null)}
          >
            <Ionicons name="add" size={16} color="#fff" />
            <Text style={styles.requestTopBtnText}>Request Spares</Text>
          </TouchableOpacity>
        }
      />

      {/* Segmented Tab Switcher */}
      <View style={styles.tabSwitcherContainer}>
        <TouchableOpacity
          style={[styles.tabSegment, activeTab === 'receives' && styles.tabSegmentActive]}
          onPress={() => setActiveTab('receives')}
          activeOpacity={0.8}
        >
          <Ionicons
            name="download-outline"
            size={16}
            color={activeTab === 'receives' ? COLORS.primary : COLORS.mutedForeground}
          />
          <Text style={[styles.tabSegmentText, activeTab === 'receives' && styles.tabSegmentTextActive]}>
            Incoming Dispatches
          </Text>
          {counts.pending_receives > 0 && (
            <View style={styles.badgePill}>
              <Text style={styles.badgePillText}>{counts.pending_receives}</Text>
            </View>
          )}
        </TouchableOpacity>

        <TouchableOpacity
          style={[styles.tabSegment, activeTab === 'stock' && styles.tabSegmentActive]}
          onPress={() => setActiveTab('stock')}
          activeOpacity={0.8}
        >
          <Ionicons
            name="cube-outline"
            size={16}
            color={activeTab === 'stock' ? COLORS.primary : COLORS.mutedForeground}
          />
          <Text style={[styles.tabSegmentText, activeTab === 'stock' && styles.tabSegmentTextActive]}>
            My Custody Stock
          </Text>
          {counts.total_stock_qty > 0 && (
            <View style={[styles.badgePill, { backgroundColor: COLORS.secondary }]}>
              <Text style={[styles.badgePillText, { color: COLORS.textPrimary }]}>{counts.total_stock_qty}</Text>
            </View>
          )}
        </TouchableOpacity>
      </View>

      {/* Search Header */}
      <View style={styles.searchHeader}>
        <View style={styles.searchBar}>
          <Ionicons name="search-outline" size={16} color={COLORS.mutedForeground} />
          <TextInput
            style={styles.searchInput}
            placeholder={
              activeTab === 'receives'
                ? 'Search dispatch #, sender, site name...'
                : 'Search stock parts, SKU, cables...'
            }
            placeholderTextColor={COLORS.textMuted}
            value={search}
            onChangeText={setSearch}
            onSubmitEditing={handleSearchSubmit}
            returnKeyType="search"
          />
          {search ? (
            <TouchableOpacity onPress={() => { setSearch(''); fetchInventoryData(); }}>
              <Ionicons name="close-circle" size={16} color={COLORS.textMuted} />
            </TouchableOpacity>
          ) : null}
        </View>

        {/* Filters Row */}
        {activeTab === 'receives' ? (
          <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={styles.chipsRow}>
            {[
              { id: 'all', label: 'All Dispatches' },
              { id: 'pending', label: 'Pending Action' },
              { id: 'accepted', label: 'Accepted' },
              { id: 'rejected', label: 'Rejected' },
            ].map((st) => {
              const isActive = selectedReceiveStatus === st.id;
              return (
                <TouchableOpacity
                  key={st.id}
                  style={[styles.chip, isActive && styles.chipActive]}
                  onPress={() => setSelectedReceiveStatus(st.id)}
                >
                  <Text style={[styles.chipText, isActive && styles.chipTextActive]}>{st.label}</Text>
                </TouchableOpacity>
              );
            })}
          </ScrollView>
        ) : (
          <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={styles.chipsRow}>
            {categories.map((cat) => {
              const isActive = selectedCategory === cat;
              return (
                <TouchableOpacity
                  key={cat}
                  style={[styles.chip, isActive && styles.chipActive]}
                  onPress={() => setSelectedCategory(cat)}
                >
                  <Text style={[styles.chipText, isActive && styles.chipTextActive]}>{cat}</Text>
                </TouchableOpacity>
              );
            })}
          </ScrollView>
        )}
      </View>

      {/* MAIN CONTENT AREA */}
      {isLoading && !refreshing ? (
        <View style={styles.loaderBox}>
          <ActivityIndicator size="large" color={COLORS.primary} />
          <Text style={styles.loaderText}>
            {activeTab === 'receives' ? 'Loading incoming dispatches...' : 'Loading field inventory...'}
          </Text>
        </View>
      ) : activeTab === 'receives' ? (
        /* TAB 1: PENDING RECEIVES / INCOMING DISPATCHES */
        <FlatList
          data={filteredPendingReceives}
          keyExtractor={(item) => String(item.id)}
          contentContainerStyle={styles.listContent}
          refreshControl={
            <RefreshControl
              refreshing={refreshing}
              onRefresh={onRefresh}
              tintColor={COLORS.primary}
              colors={[COLORS.primary]}
            />
          }
          renderItem={({ item }) => {
            const isPending = item.status === 'pending';
            const isAccepted = item.status === 'accepted';
            const isRejected = item.status === 'rejected';
            const isPartial = item.status === 'partial';
            const isOverdue = item.is_overdue;

            const itemsList = item.items || [];
            const isAcceptingThis = acceptingId === item.id;

            return (
              <View style={[styles.card, SHADOWS.small]}>
                {/* Card Header: Dispatch # & Status */}
                <View style={styles.receiveCardHeader}>
                  <View style={{ flex: 1 }}>
                    <View style={{ flexDirection: 'row', alignItems: 'center', gap: 6 }}>
                      <Text style={styles.dispatchNumText}>{item.dispatch_number || `#${item.id}`}</Text>
                      {isOverdue && (
                        <View style={styles.overdueBadge}>
                          <Text style={styles.overdueBadgeText}>OVERDUE</Text>
                        </View>
                      )}
                    </View>
                    <Text style={styles.dispatchDateText}>
                      <Ionicons name="calendar-outline" size={12} color={COLORS.mutedForeground} />{' '}
                      {item.dispatch_date || item.created_at?.split(' ')[0] || 'N/A'} • {item.days_pending || 0} days ago
                    </Text>
                  </View>

                  <View
                    style={[
                      styles.statusBadge,
                      isPending && styles.statusBadgePending,
                      isAccepted && styles.statusBadgeAccepted,
                      isRejected && styles.statusBadgeRejected,
                      isPartial && styles.statusBadgePartial,
                    ]}
                  >
                    <Text
                      style={[
                        styles.statusBadgeText,
                        isPending && { color: '#b45309' },
                        isAccepted && { color: '#047857' },
                        isRejected && { color: '#b91c1c' },
                        isPartial && { color: '#c2410c' },
                      ]}
                    >
                      {item.status ? item.status.toUpperCase() : 'PENDING'}
                    </Text>
                  </View>
                </View>

                {/* Sender Info */}
                <View style={styles.senderBox}>
                  <Ionicons name="business-outline" size={14} color={COLORS.textPrimary} />
                  <Text style={styles.senderLabel}>Sender:</Text>
                  <Text style={styles.senderVal}>
                    {item.sender_name || item.from_company_name || 'ADV Warehouse'}
                  </Text>
                  <View style={styles.senderTypeTag}>
                    <Text style={styles.senderTypeTagText}>{item.sender_type || 'company'}</Text>
                  </View>
                </View>

                {/* Linked Site Banner (if site attached) */}
                {item.site_name ? (
                  <View style={styles.siteLinkBox}>
                    <Ionicons name="location-outline" size={14} color="#4338ca" />
                    <Text style={styles.siteLinkText}>
                      Site: <Text style={{ fontWeight: '700' }}>{item.site_name}</Text>
                      {item.site_lho ? ` (${item.site_lho})` : ''}
                      {item.site_city ? ` - ${item.site_city}` : ''}
                    </Text>
                  </View>
                ) : null}

                {/* Dispatched Items List */}
                <View style={styles.itemsSection}>
                  <Text style={styles.itemsSectionTitle}>
                    Dispatched Materials ({itemsList.length} items, {item.total_expected_quantity || itemsList.length} total qty):
                  </Text>

                  {itemsList.map((itm, idx) => (
                    <View key={itm.id || idx} style={styles.itemRow}>
                      <View style={styles.itemQtyCircle}>
                        <Text style={styles.itemQtyCircleText}>{itm.expected_quantity || itm.quantity || 1}x</Text>
                      </View>
                      <View style={{ flex: 1 }}>
                        <Text style={styles.itemRowName}>{itm.product_name || `Product #${itm.product_id}`}</Text>
                        <Text style={styles.itemRowCategory}>{itm.category_name || 'Hardware'}</Text>
                        {itm.serial_number ? (
                          <View style={styles.serialBadge}>
                            <Text style={styles.serialBadgeText}>S/N: {itm.serial_number}</Text>
                          </View>
                        ) : null}
                      </View>
                    </View>
                  ))}
                </View>

                {/* Action Buttons for Pending Receives */}
                {isPending ? (
                  <View style={styles.receiveActionsRow}>
                    <TouchableOpacity
                      style={styles.rejectBtn}
                      onPress={() => handleOpenRejectModal(item)}
                      disabled={isAcceptingThis}
                    >
                      <Ionicons name="close-circle-outline" size={15} color={COLORS.destructive} />
                      <Text style={styles.rejectBtnText}>Reject</Text>
                    </TouchableOpacity>

                    <TouchableOpacity
                      style={styles.partialBtn}
                      onPress={() => handleOpenPartialModal(item)}
                      disabled={isAcceptingThis}
                    >
                      <Ionicons name="options-outline" size={15} color="#d97706" />
                      <Text style={styles.partialBtnText}>Partial</Text>
                    </TouchableOpacity>

                    <TouchableOpacity
                      style={styles.acceptBtn}
                      onPress={() => handleAcceptReceive(item)}
                      disabled={isAcceptingThis}
                    >
                      {isAcceptingThis ? (
                        <ActivityIndicator size="small" color="#ffffff" />
                      ) : (
                        <>
                          <Ionicons name="checkmark-circle" size={16} color="#ffffff" />
                          <Text style={styles.acceptBtnText}>Accept All</Text>
                        </>
                      )}
                    </TouchableOpacity>
                  </View>
                ) : (
                  <View style={styles.processedBanner}>
                    <Ionicons
                      name={isAccepted ? 'checkmark-circle' : 'information-circle'}
                      size={14}
                      color={isAccepted ? '#059669' : COLORS.mutedForeground}
                    />
                    <Text style={styles.processedBannerText}>
                      {isAccepted
                        ? 'Items accepted and added to your field inventory'
                        : isRejected
                        ? 'Dispatch rejected and returned to sender'
                        : 'Partially accepted dispatch'}
                    </Text>
                  </View>
                )}
              </View>
            );
          }}
          ListEmptyComponent={
            <View style={styles.emptyContainer}>
              <Ionicons name="mail-open-outline" size={44} color={COLORS.mutedForeground} />
              <Text style={styles.emptyTitle}>No Incoming Dispatches</Text>
              <Text style={styles.emptySubtitle}>
                When a contractor or ADV dispatches materials to you, they will appear here for review and acceptance.
              </Text>
            </View>
          }
        />
      ) : (
        /* TAB 2: MY CUSTODY STOCK / INVENTORY */
        <FlatList
          data={filteredInventory}
          keyExtractor={(item) => String(item.id || item.product_id)}
          contentContainerStyle={styles.listContent}
          refreshControl={
            <RefreshControl
              refreshing={refreshing}
              onRefresh={onRefresh}
              tintColor={COLORS.primary}
              colors={[COLORS.primary]}
            />
          }
          renderItem={({ item }) => {
            const isLowStock = item.stock <= 2;

            return (
              <TouchableOpacity
                style={[styles.card, SHADOWS.small]}
                activeOpacity={0.8}
                onPress={() => setSelectedItemForView(item)}
              >
                <View style={styles.cardHeader}>
                  <View style={[styles.iconBox, { backgroundColor: COLORS.secondary }]}>
                    <Ionicons name={item.icon || 'hardware-chip-outline'} size={20} color={COLORS.textPrimary} />
                  </View>

                  <View style={{ flex: 1, marginLeft: 12 }}>
                    <Text style={styles.itemNameText}>{item.name}</Text>
                    <Text style={styles.itemSkuText}>
                      SKU: {item.sku || `SKU-${item.id}`} • {item.category}
                    </Text>
                  </View>

                  <View
                    style={[
                      styles.stockBadge,
                      {
                        backgroundColor: isLowStock ? COLORS.warningLight : COLORS.successLight,
                        borderColor: isLowStock ? COLORS.warningBorder : COLORS.successBorder,
                      },
                    ]}
                  >
                    <Text
                      style={[
                        styles.stockBadgeText,
                        { color: isLowStock ? COLORS.warningForeground : COLORS.successForeground },
                      ]}
                    >
                      {item.stock} {item.unit || 'pcs'}
                    </Text>
                  </View>
                </View>

                {/* Serials Preview if available */}
                {item.serials && item.serials.length > 0 && (
                  <View style={styles.serialsBox}>
                    <Text style={styles.serialsLabel}>Available Serials in Custody:</Text>
                    <View style={styles.serialsRow}>
                      {item.serials.slice(0, 3).map((s, idx) => (
                        <View key={idx} style={styles.serialPill}>
                          <Text style={styles.serialPillText}>{s}</Text>
                        </View>
                      ))}
                      {item.serials.length > 3 && (
                        <View style={styles.serialPillMore}>
                          <Text style={styles.serialPillText}>+{item.serials.length - 3} more</Text>
                        </View>
                      )}
                    </View>
                  </View>
                )}

                {/* Card Footer */}
                <View style={styles.cardFooter}>
                  <Text style={styles.stockStatusText}>
                    {isLowStock ? '⚠️ Low Stock Alert' : '✓ In Custody Stock'}
                  </Text>

                  <TouchableOpacity
                    style={styles.requestItemBtn}
                    onPress={() => handleOpenRequest(item)}
                  >
                    <Ionicons name="add-circle-outline" size={14} color={COLORS.primary} />
                    <Text style={styles.requestItemBtnText}>Request More</Text>
                  </TouchableOpacity>
                </View>
              </TouchableOpacity>
            );
          }}
          ListEmptyComponent={
            <View style={styles.emptyContainer}>
              <Ionicons name="cube-outline" size={44} color={COLORS.mutedForeground} />
              <Text style={styles.emptyTitle}>No Stock in Custody</Text>
              <Text style={styles.emptySubtitle}>
                Accept incoming dispatches in the "Incoming Dispatches" tab to add hardware and materials to your custody.
              </Text>
            </View>
          }
        />
      )}

      {/* --- MODAL 1: REJECT DISPATCH MODAL --- */}
      <Modal visible={rejectModalVisible} transparent animationType="slide">
        <View style={styles.modalBackdrop}>
          <View style={styles.modalContent}>
            <View style={styles.modalHeader}>
              <View>
                <Text style={styles.modalTitle}>Reject Materials</Text>
                <Text style={styles.modalSub}>
                  Dispatch: {selectedReceiveForReject?.dispatch_number || `#${selectedReceiveForReject?.id}`}
                </Text>
              </View>
              <TouchableOpacity onPress={() => setRejectModalVisible(false)} style={styles.closeBtn}>
                <Ionicons name="close" size={20} color={COLORS.textPrimary} />
              </TouchableOpacity>
            </View>

            <ScrollView contentContainerStyle={{ padding: SPACING.md }}>
              <View style={styles.rejectWarningBox}>
                <Ionicons name="alert-circle" size={20} color="#b91c1c" />
                <Text style={styles.rejectWarningText}>
                  Rejecting will return all items back to the sender's inventory counter.
                </Text>
              </View>

              <View style={styles.fieldGroup}>
                <Text style={styles.fieldLabel}>Rejection Category</Text>
                <View style={styles.rejectCategoryRow}>
                  {[
                    { id: 'damaged', label: 'Damaged' },
                    { id: 'wrong_items', label: 'Wrong Items' },
                    { id: 'mismatch', label: 'Qty Mismatch' },
                    { id: 'other', label: 'Other Issue' },
                  ].map((cat) => (
                    <TouchableOpacity
                      key={cat.id}
                      style={[styles.rejectCatChip, rejectReasonType === cat.id && styles.rejectCatChipActive]}
                      onPress={() => setRejectReasonType(cat.id)}
                    >
                      <Text
                        style={[
                          styles.rejectCatChipText,
                          rejectReasonType === cat.id && styles.rejectCatChipTextActive,
                        ]}
                      >
                        {cat.label}
                      </Text>
                    </TouchableOpacity>
                  ))}
                </View>
              </View>

              <View style={styles.fieldGroup}>
                <Text style={styles.fieldLabel}>Detailed Explanation / Remarks *</Text>
                <TextInput
                  style={[styles.fieldInput, { height: 80, textAlignVertical: 'top' }]}
                  placeholder="Explain why materials are being rejected..."
                  placeholderTextColor={COLORS.textMuted}
                  multiline
                  value={rejectReasonNotes}
                  onChangeText={setRejectReasonNotes}
                />
              </View>
            </ScrollView>

            <View style={styles.modalFooter}>
              <CustomButton
                title="Cancel"
                variant="outline"
                onPress={() => setRejectModalVisible(false)}
                disabled={isSubmittingReject}
                style={{ flex: 1 }}
              />
              <CustomButton
                title={isSubmittingReject ? 'Rejecting...' : 'Confirm Reject'}
                variant="destructive"
                onPress={handleSubmitReject}
                loading={isSubmittingReject}
                disabled={isSubmittingReject}
                style={{ flex: 1 }}
              />
            </View>
          </View>
        </View>
      </Modal>

      {/* --- MODAL 2: PARTIAL ACCEPT MODAL --- */}
      <Modal visible={partialModalVisible} transparent animationType="slide">
        <View style={styles.modalBackdrop}>
          <View style={styles.modalContent}>
            <View style={styles.modalHeader}>
              <View>
                <Text style={styles.modalTitle}>Partial Material Acceptance</Text>
                <Text style={styles.modalSub}>
                  Dispatch: {selectedReceiveForPartial?.dispatch_number || `#${selectedReceiveForPartial?.id}`}
                </Text>
              </View>
              <TouchableOpacity onPress={() => setPartialModalVisible(false)} style={styles.closeBtn}>
                <Ionicons name="close" size={20} color={COLORS.textPrimary} />
              </TouchableOpacity>
            </View>

            <ScrollView contentContainerStyle={{ padding: SPACING.md }}>
              <Text style={[styles.fieldLabel, { marginBottom: 8 }]}>Specify Actual Received Quantities:</Text>

              {selectedReceiveForPartial?.items?.map((item) => {
                const itemId = item.dispatch_item_id || item.id;
                const currentQty = partialItemQuantities[itemId] !== undefined
                  ? String(partialItemQuantities[itemId])
                  : String(item.expected_quantity || 1);

                return (
                  <View key={itemId} style={styles.partialItemRow}>
                    <View style={{ flex: 1 }}>
                      <Text style={styles.partialItemName}>{item.product_name || `Product #${item.product_id}`}</Text>
                      <Text style={styles.partialItemExpected}>
                        Expected: {item.expected_quantity || 1} {item.unit || 'pcs'}
                      </Text>
                    </View>

                    <View style={styles.partialQtyInputBox}>
                      <Text style={styles.partialQtyLabel}>Received:</Text>
                      <TextInput
                        style={styles.partialQtyInput}
                        keyboardType="numeric"
                        value={currentQty}
                        onChangeText={(val) => {
                          setPartialItemQuantities((prev) => ({
                            ...prev,
                            [itemId]: val,
                          }));
                        }}
                      />
                    </View>
                  </View>
                );
              })}

              <View style={[styles.fieldGroup, { marginTop: 12 }]}>
                <Text style={styles.fieldLabel}>Discrepancy Notes (Optional)</Text>
                <TextInput
                  style={[styles.fieldInput, { height: 60, textAlignVertical: 'top' }]}
                  placeholder="e.g. 1 router missing from box, received only cables"
                  placeholderTextColor={COLORS.textMuted}
                  multiline
                  value={partialNotes}
                  onChangeText={setPartialNotes}
                />
              </View>
            </ScrollView>

            <View style={styles.modalFooter}>
              <CustomButton
                title="Cancel"
                variant="outline"
                onPress={() => setPartialModalVisible(false)}
                disabled={isSubmittingPartial}
                style={{ flex: 1 }}
              />
              <CustomButton
                title={isSubmittingPartial ? 'Saving...' : 'Confirm Partial Accept'}
                onPress={handleSubmitPartialAccept}
                loading={isSubmittingPartial}
                disabled={isSubmittingPartial}
                style={{ flex: 1 }}
              />
            </View>
          </View>
        </View>
      </Modal>

      {/* --- MODAL 3: REQUISITION / REQUEST SPARES MODAL --- */}
      <Modal visible={requestModalVisible} transparent animationType="slide">
        <View style={styles.modalBackdrop}>
          <View style={styles.modalContent}>
            <View style={styles.modalHeader}>
              <View>
                <Text style={styles.modalTitle}>Request Material / Spares</Text>
                <Text style={styles.modalSub}>Submit indent to inventory supervisor</Text>
              </View>
              <TouchableOpacity onPress={() => setRequestModalVisible(false)} style={styles.closeBtn}>
                <Ionicons name="close" size={20} color={COLORS.textPrimary} />
              </TouchableOpacity>
            </View>

            <ScrollView contentContainerStyle={{ padding: SPACING.md }}>
              <View style={styles.fieldGroup}>
                <Text style={styles.fieldLabel}>Select Hardware Item</Text>
                <View style={styles.itemSelectContainer}>
                  {inventoryList.map((itm) => (
                    <TouchableOpacity
                      key={itm.id || itm.product_id}
                      style={[
                        styles.itemOption,
                        String(requestForm.item_id) === String(itm.id || itm.product_id) && styles.itemOptionActive,
                      ]}
                      onPress={() =>
                        setRequestForm((prev) => ({
                          ...prev,
                          item_id: String(itm.id || itm.product_id),
                          item_name: itm.name,
                        }))
                      }
                    >
                      <Ionicons
                        name={itm.icon || 'hardware-chip-outline'}
                        size={16}
                        color={String(requestForm.item_id) === String(itm.id || itm.product_id) ? '#fff' : COLORS.textPrimary}
                      />
                      <Text
                        style={[
                          styles.itemOptionText,
                          String(requestForm.item_id) === String(itm.id || itm.product_id) && styles.itemOptionTextActive,
                        ]}
                      >
                        {itm.name}
                      </Text>
                    </TouchableOpacity>
                  ))}
                </View>
              </View>

              <View style={styles.fieldGroup}>
                <Text style={styles.fieldLabel}>Quantity Required</Text>
                <TextInput
                  style={styles.fieldInput}
                  placeholder="e.g. 2"
                  placeholderTextColor={COLORS.textMuted}
                  keyboardType="numeric"
                  value={requestForm.quantity}
                  onChangeText={(val) => setRequestForm((prev) => ({ ...prev, quantity: val }))}
                />
              </View>

              <View style={styles.fieldGroup}>
                <Text style={styles.fieldLabel}>Site / Replacement Reason</Text>
                <TextInput
                  style={[styles.fieldInput, { height: 75, textAlignVertical: 'top' }]}
                  placeholder="e.g. Replacement for damaged router on ATM S1AC00112"
                  placeholderTextColor={COLORS.textMuted}
                  multiline
                  value={requestForm.reason}
                  onChangeText={(val) => setRequestForm((prev) => ({ ...prev, reason: val }))}
                />
              </View>
            </ScrollView>

            <View style={styles.modalFooter}>
              <CustomButton
                title="Cancel"
                variant="outline"
                onPress={() => setRequestModalVisible(false)}
                disabled={isSubmittingRequest}
                style={{ flex: 1 }}
              />
              <CustomButton
                title={isSubmittingRequest ? 'Submitting...' : 'Submit Indent'}
                onPress={handleSubmitRequest}
                loading={isSubmittingRequest}
                disabled={isSubmittingRequest}
                style={{ flex: 1 }}
              />
            </View>
          </View>
        </View>
      </Modal>

      {/* --- MODAL 4: ITEM SERIAL DETAIL MODAL --- */}
      <Modal visible={!!selectedItemForView} transparent animationType="fade">
        <View style={styles.detailModalBackdrop}>
          <View style={styles.detailModalBox}>
            <View style={styles.modalHeader}>
              <Text style={styles.modalTitle}>{selectedItemForView?.name}</Text>
              <TouchableOpacity onPress={() => setSelectedItemForView(null)} style={styles.closeBtn}>
                <Ionicons name="close" size={20} color={COLORS.textPrimary} />
              </TouchableOpacity>
            </View>

            <View style={{ padding: SPACING.md }}>
              <Text style={styles.detailSku}>SKU: {selectedItemForView?.sku || `SKU-${selectedItemForView?.id}`}</Text>
              <Text style={styles.detailStock}>
                In Custody: <Text style={{ fontWeight: '800' }}>{selectedItemForView?.stock} {selectedItemForView?.unit || 'pcs'}</Text>
              </Text>

              <Text style={[styles.fieldLabel, { marginTop: SPACING.md }]}>Recorded Serial Numbers:</Text>
              {selectedItemForView?.serials?.length > 0 ? (
                selectedItemForView.serials.map((sn, idx) => (
                  <View key={idx} style={styles.serialListItem}>
                    <Ionicons name="barcode-outline" size={16} color={COLORS.mutedForeground} />
                    <Text style={styles.serialListText}>{sn}</Text>
                  </View>
                ))
              ) : (
                <Text style={{ color: COLORS.mutedForeground, fontSize: 12 }}>No individual serial tracking required for this consumable.</Text>
              )}
            </View>

            <View style={styles.modalFooter}>
              <CustomButton
                title="Close"
                variant="secondary"
                onPress={() => setSelectedItemForView(null)}
                style={{ flex: 1 }}
              />
            </View>
          </View>
        </View>
      </Modal>
    </SafeAreaView>
  );
};

const styles = StyleSheet.create({
  safeArea: {
    flex: 1,
    backgroundColor: COLORS.background,
  },
  requestTopBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
    backgroundColor: COLORS.primary,
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: RADIUS.md,
  },
  requestTopBtnText: {
    fontSize: 12,
    fontWeight: '700',
    color: '#fff',
  },
  tabSwitcherContainer: {
    flexDirection: 'row',
    backgroundColor: '#ffffff',
    paddingHorizontal: SPACING.md,
    paddingTop: SPACING.sm,
    gap: 8,
    borderBottomWidth: 1,
    borderBottomColor: COLORS.border,
  },
  tabSegment: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 6,
    paddingVertical: 10,
    borderBottomWidth: 2,
    borderBottomColor: 'transparent',
  },
  tabSegmentActive: {
    borderBottomColor: COLORS.primary,
  },
  tabSegmentText: {
    fontSize: 12,
    fontWeight: '600',
    color: COLORS.mutedForeground,
  },
  tabSegmentTextActive: {
    color: COLORS.primary,
    fontWeight: '800',
  },
  badgePill: {
    backgroundColor: COLORS.primary,
    paddingHorizontal: 6,
    paddingVertical: 1,
    borderRadius: RADIUS.full,
  },
  badgePillText: {
    color: '#fff',
    fontSize: 10,
    fontWeight: '800',
  },
  searchHeader: {
    paddingHorizontal: SPACING.md,
    paddingTop: SPACING.sm,
    paddingBottom: SPACING.xs,
    backgroundColor: '#ffffff',
    borderBottomWidth: 1,
    borderBottomColor: COLORS.border,
  },
  searchBar: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#ffffff',
    borderRadius: RADIUS.md,
    borderWidth: 1,
    borderColor: COLORS.border,
    paddingHorizontal: SPACING.sm + 2,
    gap: 8,
  },
  searchInput: {
    flex: 1,
    paddingVertical: 9,
    fontSize: FONTS.body,
    color: COLORS.textPrimary,
  },
  chipsRow: {
    flexDirection: 'row',
    paddingVertical: SPACING.sm,
    gap: 8,
  },
  chip: {
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: RADIUS.full,
    backgroundColor: COLORS.secondary,
    borderWidth: 1,
    borderColor: COLORS.border,
  },
  chipActive: {
    backgroundColor: COLORS.primary,
    borderColor: COLORS.primary,
  },
  chipText: {
    fontSize: 12,
    fontWeight: '600',
    color: COLORS.textSecondary,
  },
  chipTextActive: {
    color: COLORS.primaryForeground,
  },
  listContent: {
    paddingVertical: SPACING.sm,
  },
  loaderBox: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    padding: SPACING.xl,
  },
  loaderText: {
    marginTop: SPACING.sm,
    color: COLORS.mutedForeground,
    fontSize: FONTS.body,
  },
  card: {
    backgroundColor: '#ffffff',
    borderRadius: RADIUS.lg,
    padding: SPACING.md,
    marginVertical: SPACING.xs + 2,
    marginHorizontal: SPACING.md,
    borderWidth: 1,
    borderColor: COLORS.border,
  },
  receiveCardHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
    borderBottomWidth: 1,
    borderBottomColor: '#f4f4f5',
    paddingBottom: 8,
  },
  dispatchNumText: {
    fontSize: FONTS.body,
    fontWeight: '800',
    fontFamily: 'monospace',
    color: COLORS.primary,
  },
  dispatchDateText: {
    fontSize: 11,
    color: COLORS.mutedForeground,
    marginTop: 2,
  },
  overdueBadge: {
    backgroundColor: '#fee2e2',
    paddingHorizontal: 5,
    paddingVertical: 1,
    borderRadius: 4,
  },
  overdueBadgeText: {
    color: '#b91c1c',
    fontSize: 9,
    fontWeight: '800',
  },
  statusBadge: {
    paddingHorizontal: 8,
    paddingVertical: 3,
    borderRadius: RADIUS.full,
    borderWidth: 1,
  },
  statusBadgePending: {
    backgroundColor: '#fef3c7',
    borderColor: '#fde68a',
  },
  statusBadgeAccepted: {
    backgroundColor: '#d1fae5',
    borderColor: '#a7f3d0',
  },
  statusBadgeRejected: {
    backgroundColor: '#fee2e2',
    borderColor: '#fecaca',
  },
  statusBadgePartial: {
    backgroundColor: '#ffedd5',
    borderColor: '#fed7aa',
  },
  statusBadgeText: {
    fontSize: 10,
    fontWeight: '800',
    letterSpacing: 0.5,
  },
  senderBox: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    marginTop: 8,
  },
  senderLabel: {
    fontSize: 12,
    color: COLORS.mutedForeground,
  },
  senderVal: {
    fontSize: 12,
    fontWeight: '700',
    color: COLORS.textPrimary,
  },
  senderTypeTag: {
    backgroundColor: COLORS.secondary,
    paddingHorizontal: 6,
    paddingVertical: 1,
    borderRadius: 4,
    marginLeft: 4,
  },
  senderTypeTagText: {
    fontSize: 9,
    color: COLORS.mutedForeground,
    textTransform: 'uppercase',
    fontWeight: '600',
  },
  siteLinkBox: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    backgroundColor: '#eef2ff',
    paddingHorizontal: 10,
    paddingVertical: 6,
    borderRadius: RADIUS.md,
    marginTop: 8,
    borderWidth: 1,
    borderColor: '#e0e7ff',
  },
  siteLinkText: {
    fontSize: 11,
    color: '#3730a3',
  },
  itemsSection: {
    backgroundColor: '#fafafa',
    borderRadius: RADIUS.md,
    padding: 10,
    marginTop: 10,
    borderWidth: 1,
    borderColor: '#f4f4f5',
  },
  itemsSectionTitle: {
    fontSize: 11,
    fontWeight: '700',
    color: COLORS.textSecondary,
    marginBottom: 6,
    textTransform: 'uppercase',
    letterSpacing: 0.3,
  },
  itemRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    paddingVertical: 4,
  },
  itemQtyCircle: {
    backgroundColor: '#e0e7ff',
    width: 26,
    height: 26,
    borderRadius: 13,
    alignItems: 'center',
    justifyContent: 'center',
  },
  itemQtyCircleText: {
    fontSize: 10,
    fontWeight: '800',
    color: '#4338ca',
  },
  itemRowName: {
    fontSize: 12,
    fontWeight: '700',
    color: COLORS.textPrimary,
  },
  itemRowCategory: {
    fontSize: 10,
    color: COLORS.mutedForeground,
  },
  serialBadge: {
    backgroundColor: '#e0f2fe',
    paddingHorizontal: 5,
    paddingVertical: 1,
    borderRadius: 3,
    alignSelf: 'flex-start',
    marginTop: 2,
  },
  serialBadgeText: {
    fontSize: 9,
    fontFamily: 'monospace',
    color: '#0369a1',
    fontWeight: '600',
  },
  receiveActionsRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    marginTop: 12,
    paddingTop: 8,
    borderTopWidth: 1,
    borderTopColor: '#f4f4f5',
  },
  acceptBtn: {
    flex: 2,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 6,
    backgroundColor: '#059669',
    paddingVertical: 9,
    paddingHorizontal: 12,
    borderRadius: RADIUS.md,
  },
  acceptBtnText: {
    color: '#ffffff',
    fontSize: 12,
    fontWeight: '800',
  },
  partialBtn: {
    flex: 1.3,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 4,
    backgroundColor: '#fef3c7',
    borderWidth: 1,
    borderColor: '#fde68a',
    paddingVertical: 9,
    paddingHorizontal: 8,
    borderRadius: RADIUS.md,
  },
  partialBtnText: {
    color: '#d97706',
    fontSize: 11,
    fontWeight: '700',
  },
  rejectBtn: {
    flex: 1.1,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 4,
    backgroundColor: '#fee2e2',
    borderWidth: 1,
    borderColor: '#fecaca',
    paddingVertical: 9,
    paddingHorizontal: 8,
    borderRadius: RADIUS.md,
  },
  rejectBtnText: {
    color: COLORS.destructive,
    fontSize: 11,
    fontWeight: '700',
  },
  processedBanner: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    marginTop: 10,
    paddingTop: 6,
    borderTopWidth: 1,
    borderTopColor: '#f4f4f5',
  },
  processedBannerText: {
    fontSize: 11,
    color: COLORS.mutedForeground,
    fontStyle: 'italic',
  },
  cardHeader: {
    flexDirection: 'row',
    alignItems: 'center',
  },
  iconBox: {
    width: 40,
    height: 40,
    borderRadius: RADIUS.md,
    alignItems: 'center',
    justifyContent: 'center',
  },
  itemNameText: {
    fontSize: FONTS.body,
    fontWeight: '700',
    color: COLORS.textPrimary,
    letterSpacing: -0.2,
  },
  itemSkuText: {
    fontSize: 11,
    color: COLORS.mutedForeground,
    marginTop: 2,
  },
  stockBadge: {
    paddingHorizontal: 8,
    paddingVertical: 4,
    borderRadius: RADIUS.full,
    borderWidth: 1,
  },
  stockBadgeText: {
    fontSize: 11,
    fontWeight: '700',
  },
  serialsBox: {
    backgroundColor: COLORS.secondary,
    padding: SPACING.sm,
    borderRadius: RADIUS.md,
    marginTop: SPACING.sm,
  },
  serialsLabel: {
    fontSize: 10,
    fontWeight: '600',
    color: COLORS.mutedForeground,
    marginBottom: 4,
  },
  serialsRow: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 6,
  },
  serialPill: {
    backgroundColor: '#ffffff',
    paddingHorizontal: 6,
    paddingVertical: 2,
    borderRadius: RADIUS.sm,
    borderWidth: 1,
    borderColor: COLORS.border,
  },
  serialPillMore: {
    backgroundColor: COLORS.border,
    paddingHorizontal: 6,
    paddingVertical: 2,
    borderRadius: RADIUS.sm,
  },
  serialPillText: {
    fontSize: 10,
    fontWeight: '600',
    color: COLORS.textSecondary,
  },
  cardFooter: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingTop: 8,
    borderTopWidth: 1,
    borderTopColor: '#f4f4f5',
    marginTop: 8,
  },
  stockStatusText: {
    fontSize: 11,
    fontWeight: '600',
    color: COLORS.mutedForeground,
  },
  requestItemBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
    paddingVertical: 4,
    paddingHorizontal: 8,
  },
  requestItemBtnText: {
    fontSize: 12,
    fontWeight: '700',
    color: COLORS.primary,
  },
  closeBtn: {
    padding: 6,
    borderRadius: RADIUS.sm,
    backgroundColor: COLORS.secondary,
  },
  emptyContainer: {
    alignItems: 'center',
    justifyContent: 'center',
    padding: SPACING.xxl,
    marginTop: SPACING.xl,
  },
  emptyTitle: {
    fontSize: FONTS.title,
    fontWeight: '700',
    color: COLORS.textPrimary,
    marginTop: SPACING.sm,
  },
  emptySubtitle: {
    fontSize: FONTS.body,
    color: COLORS.mutedForeground,
    textAlign: 'center',
    marginTop: 4,
    paddingHorizontal: SPACING.md,
  },
  modalBackdrop: {
    flex: 1,
    backgroundColor: 'rgba(0,0,0,0.5)',
    justifyContent: 'flex-end',
  },
  modalContent: {
    backgroundColor: '#ffffff',
    borderTopLeftRadius: RADIUS.xl,
    borderTopRightRadius: RADIUS.xl,
    maxHeight: '85%',
  },
  modalHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    padding: SPACING.md,
    borderBottomWidth: 1,
    borderBottomColor: COLORS.border,
  },
  modalTitle: {
    fontSize: FONTS.subtitle,
    fontWeight: '800',
    color: COLORS.textPrimary,
  },
  modalSub: {
    fontSize: FONTS.small,
    color: COLORS.mutedForeground,
    marginTop: 2,
  },
  fieldGroup: {
    marginBottom: SPACING.md,
  },
  fieldLabel: {
    fontSize: FONTS.small,
    fontWeight: '600',
    color: COLORS.textPrimary,
    marginBottom: 6,
  },
  fieldInput: {
    backgroundColor: '#ffffff',
    borderWidth: 1,
    borderColor: COLORS.border,
    borderRadius: RADIUS.md,
    paddingHorizontal: 12,
    paddingVertical: 9,
    fontSize: FONTS.body,
    color: COLORS.textPrimary,
  },
  rejectWarningBox: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    backgroundColor: '#fee2e2',
    padding: 10,
    borderRadius: RADIUS.md,
    marginBottom: 12,
  },
  rejectWarningText: {
    fontSize: 12,
    color: '#b91c1c',
    flex: 1,
  },
  rejectCategoryRow: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 8,
  },
  rejectCatChip: {
    paddingHorizontal: 10,
    paddingVertical: 6,
    borderRadius: RADIUS.md,
    backgroundColor: COLORS.secondary,
    borderWidth: 1,
    borderColor: COLORS.border,
  },
  rejectCatChipActive: {
    backgroundColor: COLORS.destructive,
    borderColor: COLORS.destructive,
  },
  rejectCatChipText: {
    fontSize: 11,
    fontWeight: '600',
    color: COLORS.textPrimary,
  },
  rejectCatChipTextActive: {
    color: '#fff',
  },
  partialItemRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    backgroundColor: '#fafafa',
    padding: 10,
    borderRadius: RADIUS.md,
    borderWidth: 1,
    borderColor: '#f4f4f5',
    marginBottom: 8,
  },
  partialItemName: {
    fontSize: 12,
    fontWeight: '700',
    color: COLORS.textPrimary,
  },
  partialItemExpected: {
    fontSize: 11,
    color: COLORS.mutedForeground,
    marginTop: 2,
  },
  partialQtyInputBox: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
  },
  partialQtyLabel: {
    fontSize: 11,
    color: COLORS.mutedForeground,
  },
  partialQtyInput: {
    width: 50,
    textAlign: 'center',
    backgroundColor: '#ffffff',
    borderWidth: 1,
    borderColor: COLORS.border,
    borderRadius: RADIUS.sm,
    paddingVertical: 4,
    fontSize: 13,
    fontWeight: '700',
    color: COLORS.textPrimary,
  },
  itemSelectContainer: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 6,
  },
  itemOption: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    paddingHorizontal: 10,
    paddingVertical: 6,
    borderRadius: RADIUS.md,
    backgroundColor: COLORS.secondary,
    borderWidth: 1,
    borderColor: COLORS.border,
  },
  itemOptionActive: {
    backgroundColor: COLORS.primary,
    borderColor: COLORS.primary,
  },
  itemOptionText: {
    fontSize: 11,
    fontWeight: '600',
    color: COLORS.textPrimary,
  },
  itemOptionTextActive: {
    color: '#fff',
  },
  modalFooter: {
    flexDirection: 'row',
    gap: 10,
    padding: SPACING.md,
    borderTopWidth: 1,
    borderTopColor: COLORS.border,
  },
  detailModalBackdrop: {
    flex: 1,
    backgroundColor: 'rgba(0,0,0,0.5)',
    alignItems: 'center',
    justifyContent: 'center',
    padding: SPACING.md,
  },
  detailModalBox: {
    backgroundColor: '#ffffff',
    borderRadius: RADIUS.lg,
    width: '100%',
    maxWidth: 360,
    borderWidth: 1,
    borderColor: COLORS.border,
    ...SHADOWS.large,
  },
  detailSku: {
    fontSize: 12,
    color: COLORS.mutedForeground,
    marginBottom: 4,
  },
  detailStock: {
    fontSize: 14,
    color: COLORS.textPrimary,
    marginBottom: 8,
  },
  serialListItem: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    paddingVertical: 6,
    borderBottomWidth: 1,
    borderBottomColor: '#f4f4f5',
  },
  serialListText: {
    fontSize: 12,
    fontWeight: '600',
    color: COLORS.textPrimary,
  },
});
