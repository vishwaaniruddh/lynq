import React, { useState, useEffect, useCallback } from 'react';
import {
  View,
  Text,
  TextInput,
  FlatList,
  StyleSheet,
  SafeAreaView,
  ScrollView,
  TouchableOpacity,
  ActivityIndicator,
  RefreshControl,
  Alert,
} from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { engineerApi } from '../api/engineerApi';
import { Header } from '../components/Header';
import { COLORS, FONTS, RADIUS, SHADOWS, SPACING } from '../constants/theme';

const FEASIBILITY_FILTERS = [
  { id: 'all', label: 'All' },
  { id: 'rejected', label: 'Rejected (Needs Fix)' },
  { id: 'ready', label: 'Ready to Inspect' },
  { id: 'pending_eta', label: 'Pending ETA' },
  { id: 'completed', label: 'Completed' },
];

export const FeasibilityListScreen = ({ navigation }) => {
  const [search, setSearch] = useState('');
  const [selectedFilter, setSelectedFilter] = useState('all');
  const [assignments, setAssignments] = useState([]);
  const [isLoading, setIsLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);

  const fetchFeasibilitySites = async () => {
    try {
      setIsLoading(true);
      const res = await engineerApi.getAssignments({ limit: 50 });
      if (res && res.success && res.data) {
        setAssignments(res.data.assignments || res.data.sites || []);
      }
    } catch (e) {
      console.log('Error fetching feasibility assignments:', e);
    } finally {
      setIsLoading(false);
      setRefreshing(false);
    }
  };

  useEffect(() => {
    fetchFeasibilitySites();
  }, []);

  const onRefresh = useCallback(() => {
    setRefreshing(true);
    fetchFeasibilitySites();
  }, []);

  // Filter list based on feasibility status and search
  const filteredList = assignments.filter((item) => {
    const matchesSearch =
      !search.trim() ||
      (item.atm_id && item.atm_id.toLowerCase().includes(search.toLowerCase())) ||
      (item.site_name && item.site_name.toLowerCase().includes(search.toLowerCase())) ||
      (item.city && item.city.toLowerCase().includes(search.toLowerCase())) ||
      (item.bank_name && item.bank_name.toLowerCase().includes(search.toLowerCase()));

    if (!matchesSearch) return false;

    const hasEta = Boolean(
      (item.eta && String(item.eta).trim() !== '' && item.eta !== 'null') ||
      (item.eta_datetime && String(item.eta_datetime).trim() !== '' && item.eta_datetime !== 'null')
    );
    const hasAda = Boolean(
      item.ada_datetime ||
      (item.feasibility_status && !['pending_eta', 'eta_submitted'].includes(item.feasibility_status))
    );
    const isRejected = ['contractor_rejected', 'adv_rejected'].includes(item.feasibility_status);
    const isCompleted = [
      'feasibility_completed',
      'contractor_approved',
      'adv_approved',
      'pending_contractor_review',
    ].includes(item.feasibility_status);

    if (selectedFilter === 'rejected') {
      return isRejected;
    }
    if (selectedFilter === 'ready') {
      return hasAda && !isCompleted && !isRejected;
    }
    if (selectedFilter === 'pending_eta') {
      return !hasEta;
    }
    if (selectedFilter === 'completed') {
      return isCompleted;
    }

    return true;
  });

  const handleStartInspection = (site) => {
    const hasEta = Boolean(
      (site.eta && String(site.eta).trim() !== '' && site.eta !== 'null') ||
      (site.eta_datetime && String(site.eta_datetime).trim() !== '' && site.eta_datetime !== 'null')
    );
    const hasAda = Boolean(
      site.ada_datetime ||
      (site.feasibility_status && !['pending_eta', 'eta_submitted'].includes(site.feasibility_status))
    );

    if (!hasEta) {
      Alert.alert('ETA Required', 'Please set Visit ETA first before starting feasibility check.', [
        { text: 'Cancel', style: 'cancel' },
        {
          text: 'Set ETA',
          onPress: () =>
            navigation.navigate('SiteDetail', {
              siteId: site.id || site.site_id,
              site,
              openEta: true,
            }),
        },
      ]);
      return;
    }

    if (!hasAda) {
      Alert.alert('Arrival (ADA) Required', 'Please mark your site arrival (ADA) before starting feasibility.', [
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
      ]);
      return;
    }

    navigation.navigate('Feasibility', {
      siteId: site.id || site.site_id,
      site,
      assignmentId: site.assignment_id || site.id || site.site_id,
    });
  };

  const getStepProgress = (item) => {
    const isRejected = ['contractor_rejected', 'adv_rejected'].includes(item.feasibility_status);
    if (isRejected) {
      return {
        label: item.feasibility_status === 'adv_rejected' ? 'ADV Rejected' : 'Rejected by Admin',
        percent: 66,
        step: 'Needs Revision',
        color: COLORS.danger,
        isRejected: true,
      };
    }

    const hasEta = Boolean(
      (item.eta && String(item.eta).trim() !== '' && item.eta !== 'null') ||
      (item.eta_datetime && String(item.eta_datetime).trim() !== '' && item.eta_datetime !== 'null')
    );
    const hasAda = Boolean(
      item.ada_datetime ||
      (item.feasibility_status && !['pending_eta', 'eta_submitted'].includes(item.feasibility_status))
    );
    const isSubmitted = [
      'pending_contractor_review',
      'feasibility_completed',
      'contractor_approved',
      'adv_approved',
    ].includes(item.feasibility_status);

    if (item.feasibility_status === 'pending_contractor_review') {
      return { label: 'Submitted (In Review)', percent: 100, step: 'In Review', color: COLORS.primary };
    }
    if (['feasibility_completed', 'contractor_approved', 'adv_approved'].includes(item.feasibility_status)) {
      return { label: 'Completed', percent: 100, step: 'Approved', color: COLORS.success };
    }
    if (hasAda) return { label: 'Ready for Feasibility', percent: 66, step: 'Step 3 of 3', color: COLORS.primary };
    if (hasEta) return { label: 'Arrival (ADA) Pending', percent: 33, step: 'Step 2 of 3', color: COLORS.warning };
    return { label: 'ETA Required', percent: 10, step: 'Step 1 of 3', color: COLORS.danger };
  };

  return (
    <SafeAreaView style={styles.safeArea}>
      <Header title="Feasibility Checks" subtitle="Site inspection workflow" />

      {/* Search and Filters */}
      <View style={styles.searchHeader}>
        <View style={styles.searchBar}>
          <Ionicons name="search-outline" size={16} color={COLORS.mutedForeground} />
          <TextInput
            style={styles.searchInput}
            placeholder="Search ATM ID, Bank, City..."
            placeholderTextColor={COLORS.textMuted}
            value={search}
            onChangeText={setSearch}
          />
          {search ? (
            <TouchableOpacity onPress={() => setSearch('')}>
              <Ionicons name="close-circle" size={16} color={COLORS.textMuted} />
            </TouchableOpacity>
          ) : null}
        </View>

        {/* Filter Chips */}
        <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={styles.chipsRow}>
          {FEASIBILITY_FILTERS.map((f) => {
            const isActive = selectedFilter === f.id;
            return (
              <TouchableOpacity
                key={f.id}
                style={[
                  styles.chip,
                  isActive && styles.chipActive,
                  f.id === 'rejected' && styles.chipRejectedFilter,
                  isActive && f.id === 'rejected' && styles.chipRejectedFilterActive,
                ]}
                onPress={() => setSelectedFilter(f.id)}
              >
                <Text
                  style={[
                    styles.chipText,
                    isActive && styles.chipTextActive,
                    f.id === 'rejected' && { color: COLORS.dangerForeground },
                    isActive && f.id === 'rejected' && { color: '#ffffff' },
                  ]}
                >
                  {f.label}
                </Text>
              </TouchableOpacity>
            );
          })}
        </ScrollView>
      </View>

      {isLoading && !refreshing ? (
        <View style={styles.loaderBox}>
          <ActivityIndicator size="large" color={COLORS.primary} />
          <Text style={styles.loaderText}>Loading feasibility pipeline...</Text>
        </View>
      ) : (
        <FlatList
          data={filteredList}
          keyExtractor={(item, index) => String(item.id || item.assignment_id || index)}
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
            const progress = getStepProgress(item);

            return (
              <View style={[styles.card, SHADOWS.small, progress.isRejected && { borderColor: COLORS.dangerBorder }]}>
                {/* Header */}
                <View style={styles.cardHeaderRow}>
                  <View style={{ flex: 1 }}>
                    <Text style={styles.atmIdText}>
                      {item.atm_id || item.site_name || `Site #${item.id}`}
                    </Text>
                    <Text style={styles.bankText}>
                      {[item.bank_name, item.customer_name, item.city].filter(Boolean).join(' • ')}
                    </Text>
                  </View>

                  <View style={[styles.statusBadge, { backgroundColor: `${progress.color}15` }]}>
                    <Text style={[styles.statusBadgeText, { color: progress.color }]}>
                      {progress.label}
                    </Text>
                  </View>
                </View>

                {/* Linear Step Bar */}
                <View style={styles.progressBarBg}>
                  <View style={[styles.progressBarFill, { width: `${progress.percent}%`, backgroundColor: progress.color }]} />
                </View>

                {/* Workflow Milestones */}
                <View style={styles.milestonesRow}>
                  <Text style={[styles.milestoneText, progress.isRejected && { color: COLORS.danger, fontWeight: '700' }]}>
                    {progress.step}
                  </Text>
                  <Text style={styles.milestoneText}>
                    {item.ada_datetime ? `Arrived: ${item.ada_datetime.substring(11, 16)}` : item.eta ? `ETA: ${item.eta}` : 'Schedule pending'}
                  </Text>
                </View>

                {/* Actions */}
                <View style={styles.cardFooter}>
                  <TouchableOpacity
                    style={styles.detailLinkBtn}
                    onPress={() => navigation.navigate('SiteDetail', { siteId: item.id || item.site_id, site: item })}
                  >
                    <Text style={styles.detailLinkText}>Site Details</Text>
                  </TouchableOpacity>

                  <TouchableOpacity
                    style={[
                      styles.actionPrimaryBtn,
                      progress.isRejected && { backgroundColor: COLORS.danger },
                    ]}
                    onPress={() => handleStartInspection(item)}
                  >
                    <Ionicons
                      name={progress.isRejected ? "construct-outline" : progress.percent === 100 ? "document-text-outline" : "clipboard-outline"}
                      size={14}
                      color="#fff"
                    />
                    <Text style={styles.actionPrimaryBtnText}>
                      {progress.isRejected
                        ? 'Fix & Reconfigure'
                        : progress.percent === 100
                        ? 'View Details'
                        : 'Start Feasibility'}
                    </Text>
                  </TouchableOpacity>
                </View>
              </View>
            );
          }}
          ListEmptyComponent={
            <View style={styles.emptyContainer}>
              <Ionicons name="clipboard-outline" size={44} color={COLORS.mutedForeground} />
              <Text style={styles.emptyTitle}>No Feasibility Checks</Text>
              <Text style={styles.emptySubtitle}>No assignments match your selected filter.</Text>
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
  cardHeaderRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
    marginBottom: 8,
  },
  atmIdText: {
    fontSize: FONTS.subtitle,
    fontWeight: '700',
    color: COLORS.textPrimary,
    letterSpacing: -0.3,
  },
  bankText: {
    fontSize: FONTS.small,
    color: COLORS.mutedForeground,
    marginTop: 2,
  },
  statusBadge: {
    paddingHorizontal: 8,
    paddingVertical: 3,
    borderRadius: RADIUS.full,
  },
  statusBadgeText: {
    fontSize: 10,
    fontWeight: '700',
  },
  progressBarBg: {
    height: 4,
    backgroundColor: '#f4f4f5',
    borderRadius: 2,
    overflow: 'hidden',
    marginTop: 4,
    marginBottom: 6,
  },
  progressBarFill: {
    height: '100%',
    borderRadius: 2,
  },
  milestonesRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    marginBottom: 10,
  },
  milestoneText: {
    fontSize: 11,
    color: COLORS.mutedForeground,
  },
  cardFooter: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingTop: 8,
    borderTopWidth: 1,
    borderTopColor: '#f4f4f5',
  },
  detailLinkBtn: {
    paddingVertical: 6,
  },
  detailLinkText: {
    fontSize: 12,
    fontWeight: '600',
    color: COLORS.mutedForeground,
  },
  actionPrimaryBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    backgroundColor: COLORS.primary,
    paddingHorizontal: 14,
    paddingVertical: 7,
    borderRadius: RADIUS.md,
  },
  actionPrimaryBtnText: {
    fontSize: 12,
    fontWeight: '700',
    color: '#fff',
  },
  emptyContainer: {
    alignItems: 'center',
    justifyContent: 'center',
    padding: SPACING.xxl,
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
  },
  chipRejectedFilter: {
    backgroundColor: COLORS.dangerLight,
    borderColor: COLORS.dangerBorder,
  },
  chipRejectedFilterActive: {
    backgroundColor: COLORS.danger,
    borderColor: COLORS.danger,
  },
});
