import React, { useState, useEffect, useCallback } from 'react';
import {
  View,
  Text,
  TextInput,
  FlatList,
  StyleSheet,
  SafeAreaView,
  TouchableOpacity,
  ActivityIndicator,
  RefreshControl,
  Alert,
} from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { engineerApi } from '../api/engineerApi';
import { SiteCard } from '../components/SiteCard';
import { COLORS, FONTS, RADIUS, SHADOWS, SPACING } from '../constants/theme';

const STATUS_FILTERS = [
  { id: 'all', label: 'All' },
  { id: 'assigned', label: 'Assigned' },
  { id: 'in_progress', label: 'In Progress' },
  { id: 'completed', label: 'Completed' },
];

export const SitesListScreen = ({ route, navigation }) => {
  const [search, setSearch] = useState('');
  const [selectedStatus, setSelectedStatus] = useState(route?.params?.status || 'all');
  const [feasibilityStatus, setFeasibilityStatus] = useState(route?.params?.feasibility_status || '');
  const [sites, setSites] = useState([]);
  const [totalCount, setTotalCount] = useState(0);
  const [statusCounts, setStatusCounts] = useState({
    all: 0,
    assigned: 0,
    in_progress: 0,
    completed: 0,
  });
  const [isLoading, setIsLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [page, setPage] = useState(1);
  const [hasMore, setHasMore] = useState(true);

  // Sync state if route parameters change from dashboard navigation
  useEffect(() => {
    if (route?.params?.status !== undefined) {
      setSelectedStatus(route.params.status);
    }
    if (route?.params?.feasibility_status !== undefined) {
      setFeasibilityStatus(route.params.feasibility_status);
    }
  }, [route?.params?.status, route?.params?.feasibility_status]);

  const fetchSites = async (pageNum = 1, isRefresh = false) => {
    try {
      if (pageNum === 1 && !isRefresh) setIsLoading(true);

      const filters = {
        page: pageNum,
        limit: 20,
      };

      if (search.trim()) filters.search = search.trim();
      if (selectedStatus && selectedStatus !== 'all') filters.status = selectedStatus;
      if (feasibilityStatus) filters.feasibility_status = feasibilityStatus;

      const [res, countsRes] = await Promise.all([
        engineerApi.getAssignments(filters),
        pageNum === 1 ? engineerApi.getCounts() : Promise.resolve(null),
      ]);

      if (res && res.success && res.data) {
        const fetchedList = res.data.assignments || res.data.sites || [];
        setTotalCount(res.data.pagination?.total ?? fetchedList.length);
        if (pageNum === 1) {
          setSites(fetchedList);
        } else {
          setSites((prev) => [...prev, ...fetchedList]);
        }
        setHasMore(fetchedList.length >= 20);
      }

      if (countsRes && countsRes.data) {
        const c = countsRes.data.counts || countsRes.data || {};
        setStatusCounts({
          all: c.total ?? 0,
          assigned: c.assigned ?? 0,
          in_progress: c.in_progress ?? 0,
          completed: c.completed ?? 0,
        });
      }
    } catch (e) {
      console.log('Error fetching sites list:', e);
      if (e.code === 'ECONNABORTED' || (e.message && e.message.includes('timeout'))) {
        Alert.alert('Connection Timeout', 'Server request timed out. Pull down to refresh or check your network connection.');
      } else if (!e.response) {
        Alert.alert('Network Error', 'Cannot reach the server. Make sure your phone is on the same Wi-Fi or check server settings.');
      }
    } finally {
      setIsLoading(false);
      setRefreshing(false);
    }
  };

  useEffect(() => {
    setPage(1);
    fetchSites(1);
  }, [selectedStatus, feasibilityStatus]);

  const onRefresh = useCallback(() => {
    setRefreshing(true);
    setPage(1);
    fetchSites(1, true);
  }, [selectedStatus, search, feasibilityStatus]);

  const handleSearchSubmit = () => {
    setPage(1);
    fetchSites(1);
  };

  const handleClearSearch = () => {
    setSearch('');
    setPage(1);
    fetchSites(1);
  };

  const handleResetFilters = () => {
    setSearch('');
    setSelectedStatus('all');
    setFeasibilityStatus('');
  };

  const handleLoadMore = () => {
    if (!isLoading && hasMore) {
      const nextPage = page + 1;
      setPage(nextPage);
      fetchSites(nextPage);
    }
  };

  const handleSitePress = (site) => {
    navigation.navigate('SiteDetail', { siteId: site.id || site.site_id, site });
  };

  const handleEtaPress = (site) => {
    const hasAda = Boolean(
      site.ada_datetime ||
      (site.feasibility_status && !['pending_eta', 'eta_submitted'].includes(site.feasibility_status))
    );

    if (hasAda) {
      Alert.alert('ETA Locked', 'ETA cannot be edited after Site Arrival (ADA) has already been submitted.');
      return;
    }

    navigation.navigate('SiteDetail', { siteId: site.id || site.site_id, site, openEta: true });
  };

  const handleFeasibilityPress = (site) => {
    const hasEta = Boolean(
      (site.eta && String(site.eta).trim() !== '' && site.eta !== 'null') ||
      (site.eta_datetime && String(site.eta_datetime).trim() !== '' && site.eta_datetime !== 'null') ||
      (site.current_eta && String(site.current_eta).trim() !== '' && site.current_eta !== 'null')
    );

    const hasAda = Boolean(
      site.ada_datetime ||
      (site.feasibility_status && !['pending_eta', 'eta_submitted'].includes(site.feasibility_status))
    );

    if (!hasEta) {
      Alert.alert(
        'ETA Required',
        'Please set your Visit ETA before viewing or starting Feasibility inspection.',
        [
          { text: 'Cancel', style: 'cancel' },
          { text: 'Set ETA', onPress: () => handleEtaPress(site) },
        ]
      );
      return;
    }

    if (!hasAda) {
      Alert.alert(
        'Arrival (ADA) Required',
        'Please mark Site Arrival (ADA) upon reaching the location to unlock Feasibility inspection.',
        [
          { text: 'Cancel', style: 'cancel' },
          {
            text: 'Mark Arrival',
            onPress: () =>
              navigation.navigate('SiteDetail', {
                siteId: site.id || site.site_id,
                site,
                openAda: true,
              }),
          },
        ]
      );
      return;
    }

    navigation.navigate('Feasibility', {
      siteId: site.id || site.site_id,
      site,
      assignmentId: site.assignment_id || site.id || site.site_id,
    });
  };

  const getChipCount = (filterId) => {
    if (filterId === 'all') return statusCounts.all;
    if (filterId === 'assigned') return statusCounts.assigned;
    if (filterId === 'in_progress') return statusCounts.in_progress;
    if (filterId === 'completed') return statusCounts.completed;
    return 0;
  };

  return (
    <SafeAreaView style={styles.safeArea}>
      {/* Top App Header */}
      <View style={styles.topAppHeader}>
        <View>
          <Text style={styles.headerTitle}>Sites Directory</Text>
          <Text style={styles.headerSub}>Active field assignments & status</Text>
        </View>
        <View style={styles.headerTotalBadge}>
          <Text style={styles.headerTotalBadgeText}>{totalCount} Sites</Text>
        </View>
      </View>

      {/* ShadCN Search & Segmented Filter Hub */}
      <View style={styles.filterHub}>
        {/* Search Bar */}
        <View style={styles.searchBar}>
          <Ionicons name="search" size={16} color={COLORS.mutedForeground} />
          <TextInput
            style={styles.searchInput}
            placeholder="Search ATM ID, Bank, City, Address..."
            placeholderTextColor={COLORS.textMuted}
            value={search}
            onChangeText={setSearch}
            onSubmitEditing={handleSearchSubmit}
            returnKeyType="search"
          />
          {search ? (
            <TouchableOpacity onPress={handleClearSearch} hitSlop={{ top: 8, bottom: 8, left: 8, right: 8 }}>
              <Ionicons name="close-circle" size={17} color={COLORS.mutedForeground} />
            </TouchableOpacity>
          ) : null}
        </View>

        {/* Segmented Filter Pills */}
        <View style={styles.segmentedContainer}>
          {STATUS_FILTERS.map((item) => {
            const isActive = selectedStatus === item.id;
            const count = getChipCount(item.id);

            return (
              <TouchableOpacity
                key={item.id}
                style={[styles.segmentBtn, isActive && styles.segmentBtnActive]}
                onPress={() => setSelectedStatus(item.id)}
                activeOpacity={0.7}
              >
                <Text style={[styles.segmentBtnText, isActive && styles.segmentBtnTextActive]}>
                  {item.label}
                </Text>
                {count > 0 && (
                  <View style={[styles.countPill, isActive && styles.countPillActive]}>
                    <Text style={[styles.countPillText, isActive && styles.countPillTextActive]}>
                      {count}
                    </Text>
                  </View>
                )}
              </TouchableOpacity>
            );
          })}
        </View>
      </View>

      {/* Sites Listing */}
      {isLoading && !refreshing ? (
        <View style={styles.loaderBox}>
          <ActivityIndicator size="large" color={COLORS.primary} />
          <Text style={styles.loaderText}>Loading assignments...</Text>
        </View>
      ) : (
        <FlatList
          data={sites}
          keyExtractor={(item, index) => String(item.id || item.assignment_id || index)}
          renderItem={({ item }) => (
            <SiteCard
              site={item}
              onPress={handleSitePress}
              onEtaPress={handleEtaPress}
              onFeasibilityPress={handleFeasibilityPress}
            />
          )}
          contentContainerStyle={styles.listContent}
          refreshControl={
            <RefreshControl
              refreshing={refreshing}
              onRefresh={onRefresh}
              tintColor={COLORS.primary}
              colors={[COLORS.primary]}
            />
          }
          onEndReached={handleLoadMore}
          onEndReachedThreshold={0.3}
          ListEmptyComponent={
            <View style={styles.emptyContainer}>
              <View style={styles.emptyIconBox}>
                <Ionicons name="file-tray-outline" size={36} color={COLORS.mutedForeground} />
              </View>
              <Text style={styles.emptyTitle}>No Assignments Found</Text>
              <Text style={styles.emptySubtitle}>
                {selectedStatus !== 'all' || search
                  ? `No sites found matching "${selectedStatus !== 'all' ? selectedStatus.replace('_', ' ') : ''} ${search}".`
                  : 'You have no active sites assigned to your profile.'}
              </Text>

              {(selectedStatus !== 'all' || search) && (
                <TouchableOpacity style={styles.resetFilterBtn} onPress={handleResetFilters}>
                  <Ionicons name="refresh-outline" size={14} color="#ffffff" style={{ marginRight: 6 }} />
                  <Text style={styles.resetFilterBtnText}>Show All Sites</Text>
                </TouchableOpacity>
              )}
            </View>
          }
        />
      )}
    </SafeAreaView>
  );
};

const styles = StyleSheet.create({
  safeArea: {
    flex: 1,
    backgroundColor: COLORS.background,
  },
  topAppHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingHorizontal: SPACING.md,
    paddingTop: SPACING.md,
    paddingBottom: SPACING.xs,
    backgroundColor: '#ffffff',
  },
  headerTitle: {
    fontSize: FONTS.title,
    fontWeight: '800',
    color: COLORS.textPrimary,
    letterSpacing: -0.4,
  },
  headerSub: {
    fontSize: FONTS.tiny,
    color: COLORS.mutedForeground,
    marginTop: 1,
  },
  headerTotalBadge: {
    backgroundColor: COLORS.secondary,
    paddingHorizontal: 10,
    paddingVertical: 4,
    borderRadius: RADIUS.full,
    borderWidth: 1,
    borderColor: COLORS.border,
  },
  headerTotalBadgeText: {
    fontSize: 11,
    fontWeight: '700',
    color: COLORS.textPrimary,
  },
  filterHub: {
    paddingHorizontal: SPACING.md,
    paddingVertical: SPACING.sm,
    backgroundColor: '#ffffff',
    borderBottomWidth: 1,
    borderBottomColor: COLORS.border,
    gap: 10,
  },
  searchBar: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#f8fafc',
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
  segmentedContainer: {
    flexDirection: 'row',
    backgroundColor: '#f1f5f9',
    borderRadius: RADIUS.md,
    padding: 3,
  },
  segmentBtn: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    paddingVertical: 7,
    borderRadius: RADIUS.sm,
    gap: 4,
  },
  segmentBtnActive: {
    backgroundColor: '#ffffff',
    ...SHADOWS.small,
  },
  segmentBtnText: {
    fontSize: 11,
    fontWeight: '600',
    color: COLORS.mutedForeground,
  },
  segmentBtnTextActive: {
    color: COLORS.textPrimary,
    fontWeight: '800',
  },
  countPill: {
    backgroundColor: '#e2e8f0',
    paddingHorizontal: 5,
    paddingVertical: 1,
    borderRadius: RADIUS.full,
  },
  countPillActive: {
    backgroundColor: COLORS.primary,
  },
  countPillText: {
    fontSize: 9,
    fontWeight: '700',
    color: '#475569',
  },
  countPillTextActive: {
    color: '#ffffff',
  },
  listContent: {
    paddingVertical: SPACING.sm,
    paddingBottom: SPACING.xxl,
  },
  loaderBox: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
  },
  loaderText: {
    marginTop: SPACING.sm,
    color: COLORS.mutedForeground,
    fontSize: FONTS.body,
  },
  emptyContainer: {
    alignItems: 'center',
    justifyContent: 'center',
    padding: SPACING.xxl,
    marginTop: SPACING.lg,
  },
  emptyIconBox: {
    width: 64,
    height: 64,
    borderRadius: 32,
    backgroundColor: '#f1f5f9',
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: SPACING.md,
  },
  emptyTitle: {
    fontSize: FONTS.title,
    fontWeight: '800',
    color: COLORS.textPrimary,
  },
  emptySubtitle: {
    fontSize: FONTS.small,
    color: COLORS.mutedForeground,
    textAlign: 'center',
    marginTop: 4,
    lineHeight: 18,
    maxWidth: 260,
  },
  resetFilterBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: COLORS.primary,
    paddingHorizontal: 16,
    paddingVertical: 9,
    borderRadius: RADIUS.md,
    marginTop: SPACING.md,
  },
  resetFilterBtnText: {
    fontSize: 12,
    fontWeight: '700',
    color: '#ffffff',
  },
});
