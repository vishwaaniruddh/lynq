import React, { useState, useEffect, useCallback } from 'react';
import {
  View,
  Text,
  StyleSheet,
  SafeAreaView,
  ScrollView,
  RefreshControl,
  TouchableOpacity,
  ActivityIndicator,
  Alert,
} from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { useAuth } from '../context/AuthContext';
import { engineerApi } from '../api/engineerApi';
import { getApiBaseUrl } from '../api/client';
import { StatCard } from '../components/StatCard';
import { SiteCard } from '../components/SiteCard';
import { COLORS, FONTS, SPACING, RADIUS, SHADOWS } from '../constants/theme';
import { DEFAULT_ENVIRONMENTS } from '../constants/config';

export const DashboardScreen = ({ navigation }) => {
  const { user, updateServerUrl } = useAuth();
  const [counts, setCounts] = useState({
    total: 0,
    assigned: 0,
    in_progress: 0,
    completed: 0,
    feasibility_pending: 0,
  });
  const [recentSites, setRecentSites] = useState([]);
  const [isLoading, setIsLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);

  const [fetchError, setFetchError] = useState(null);

  const fetchDashboardData = async () => {
    try {
      setFetchError(null);
      const [sitesRes, countsRes] = await Promise.all([
        engineerApi.getAssignments({ limit: 5 }),
        engineerApi.getCounts(),
      ]);

      const statusCounts = countsRes?.data?.counts || countsRes?.data || {};
      const feasCounts = sitesRes?.data?.counts || {};
      const paginationTotal = sitesRes?.data?.pagination?.total;

      const totalCount = statusCounts.total ?? (paginationTotal ?? 0);
      const assignedCount = statusCounts.assigned ?? 0;
      const inProgressCount = statusCounts.in_progress ?? 0;
      const completedCount = statusCounts.completed ?? 0;
      const feasPendingCount = feasCounts.pending_eta ?? assignedCount;

      setCounts({
        total: totalCount,
        assigned: assignedCount,
        in_progress: inProgressCount,
        completed: completedCount,
        feasibility_pending: feasPendingCount,
      });

      if (sitesRes && sitesRes.success && sitesRes.data) {
        setRecentSites(sitesRes.data.assignments || sitesRes.data.sites || []);
      }
    } catch (e) {
      console.log('Error fetching dashboard data:', e);
      if (e.code === 'ECONNABORTED' || (e.message && e.message.includes('timeout'))) {
        setFetchError('Server request timed out. Pull down to retry or check your network.');
      } else if (!e.response) {
        setFetchError('Cannot reach the server. Make sure you are on the same Wi-Fi or switch to Production server.');
      } else {
        setFetchError(e.response?.data?.message || e.message || 'Failed to load data');
      }
    } finally {
      setIsLoading(false);
      setRefreshing(false);
    }
  };

  useEffect(() => {
    fetchDashboardData();
  }, []);

  const onRefresh = useCallback(() => {
    setRefreshing(true);
    fetchDashboardData();
  }, []);

  const handleSitePress = (site) => {
    navigation.navigate('SiteDetail', { siteId: site.id || site.site_id, site });
  };

  const handleEtaPress = (site) => {
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

  const userName = user?.first_name
    ? `${user.first_name} ${user.last_name || ''}`.trim()
    : user?.username || 'Engineer';

  const userInitials = userName
    .split(' ')
    .map((n) => n[0])
    .join('')
    .substring(0, 2)
    .toUpperCase();

  return (
    <SafeAreaView style={styles.safeArea}>
      {/* ShadCN Top Header Bar */}
      <View style={styles.topBar}>
        <View style={styles.userInfoCol}>
          <Text style={styles.welcomeText}>Engineer Portal</Text>
          <Text style={styles.nameText}>{userName}</Text>
        </View>

        <TouchableOpacity
          style={styles.avatarButton}
          onPress={() => navigation.navigate('ProfileTab')}
          activeOpacity={0.8}
        >
          <Text style={styles.avatarText}>{userInitials}</Text>
          <View style={styles.onlineBadge} />
        </TouchableOpacity>
      </View>

      <ScrollView
        contentContainerStyle={styles.scrollContent}
        refreshControl={
          <RefreshControl
            refreshing={refreshing}
            onRefresh={onRefresh}
            tintColor={COLORS.primary}
            colors={[COLORS.primary]}
          />
        }
      >
        {isLoading && !refreshing ? (
          <View style={styles.loaderBox}>
            <ActivityIndicator size="large" color={COLORS.primary} />
            <Text style={styles.loaderText}>Loading dashboard...</Text>
          </View>
        ) : (
          <>
            {/* Connection Error Banner */}
            {fetchError ? (
              <View style={styles.errorBanner}>
                <Ionicons name="cloud-offline-outline" size={20} color={COLORS.dangerForeground} />
                <Text style={styles.errorBannerText}>{fetchError}</Text>
                <Text style={[styles.errorBannerText, { fontSize: 10, opacity: 0.7 }]}>Server: {getApiBaseUrl()}</Text>
                <View style={{ flexDirection: 'row', gap: 10, marginTop: 4 }}>
                  <TouchableOpacity
                    style={[styles.retryBtn, { backgroundColor: COLORS.primary }]}
                    onPress={async () => {
                      // Reset to default local Wi-Fi URL
                      const defaultUrl = DEFAULT_ENVIRONMENTS[0].url;
                      await updateServerUrl(defaultUrl);
                      setIsLoading(true);
                      fetchDashboardData();
                    }}
                  >
                    <Text style={styles.retryBtnText}>Reset Server</Text>
                  </TouchableOpacity>
                  <TouchableOpacity
                    style={styles.retryBtn}
                    onPress={() => {
                      setIsLoading(true);
                      fetchDashboardData();
                    }}
                  >
                    <Text style={styles.retryBtnText}>Retry</Text>
                  </TouchableOpacity>
                </View>
              </View>
            ) : null}
            {/* Quick Hub Navigation Cards (ShadCN style) */}
            <View style={styles.hubContainer}>
              <Text style={styles.sectionHeader}>WORKSPACE MODULES</Text>
              <View style={styles.hubGrid}>
                <TouchableOpacity
                  style={[styles.hubCard, SHADOWS.small]}
                  onPress={() => navigation.navigate('SitesTab')}
                  activeOpacity={0.8}
                >
                  <View style={[styles.hubIconBox, { backgroundColor: COLORS.secondary }]}>
                    <Ionicons name="business" size={18} color={COLORS.textPrimary} />
                  </View>
                  <View style={{ flex: 1 }}>
                    <Text style={styles.hubTitle}>Sites</Text>
                    <Text style={styles.hubDesc}>Assigned ATM locations</Text>
                  </View>
                  <Ionicons name="chevron-forward" size={16} color={COLORS.mutedForeground} />
                </TouchableOpacity>

                <TouchableOpacity
                  style={[styles.hubCard, SHADOWS.small]}
                  onPress={() => navigation.navigate('FeasibilityTab')}
                  activeOpacity={0.8}
                >
                  <View style={[styles.hubIconBox, { backgroundColor: COLORS.secondary }]}>
                    <Ionicons name="clipboard" size={18} color={COLORS.textPrimary} />
                  </View>
                  <View style={{ flex: 1 }}>
                    <Text style={styles.hubTitle}>Feasibility</Text>
                    <Text style={styles.hubDesc}>Inspection & survey checks</Text>
                  </View>
                  <Ionicons name="chevron-forward" size={16} color={COLORS.mutedForeground} />
                </TouchableOpacity>

                <TouchableOpacity
                  style={[styles.hubCard, SHADOWS.small]}
                  onPress={() => navigation.navigate('InstallationTab')}
                  activeOpacity={0.8}
                >
                  <View style={[styles.hubIconBox, { backgroundColor: COLORS.secondary }]}>
                    <Ionicons name="construct" size={18} color={COLORS.textPrimary} />
                  </View>
                  <View style={{ flex: 1 }}>
                    <Text style={styles.hubTitle}>Installation</Text>
                    <Text style={styles.hubDesc}>Hardware setups & QA</Text>
                  </View>
                  <Ionicons name="chevron-forward" size={16} color={COLORS.mutedForeground} />
                </TouchableOpacity>

                <TouchableOpacity
                  style={[styles.hubCard, SHADOWS.small]}
                  onPress={() => navigation.navigate('InventoryTab')}
                  activeOpacity={0.8}
                >
                  <View style={[styles.hubIconBox, { backgroundColor: COLORS.secondary }]}>
                    <Ionicons name="cube" size={18} color={COLORS.textPrimary} />
                  </View>
                  <View style={{ flex: 1 }}>
                    <Text style={styles.hubTitle}>Inventory</Text>
                    <Text style={styles.hubDesc}>Stock & spare parts</Text>
                  </View>
                  <Ionicons name="chevron-forward" size={16} color={COLORS.mutedForeground} />
                </TouchableOpacity>
              </View>
            </View>

            {/* Metrics Overview Grid */}
            <View style={styles.metricsContainer}>
              <Text style={styles.sectionHeader}>ASSIGNMENT METRICS</Text>
              <View style={styles.statsGrid}>
                <StatCard
                  title="Total Sites"
                  count={counts.total || 0}
                  icon="layers-outline"
                  subtitle="Active assignments"
                  onPress={() => navigation.navigate('SitesTab', { status: 'all' })}
                />
                <StatCard
                  title="Assigned"
                  count={counts.assigned || 0}
                  icon="time-outline"
                  subtitle="Awaiting visit"
                  onPress={() => navigation.navigate('SitesTab', { status: 'assigned' })}
                />
                <StatCard
                  title="In Progress"
                  count={counts.in_progress || 0}
                  icon="navigate-outline"
                  subtitle="Site visits active"
                  onPress={() => navigation.navigate('SitesTab', { status: 'in_progress' })}
                />
                <StatCard
                  title="Completed"
                  count={counts.completed || 0}
                  icon="checkmark-done-circle-outline"
                  subtitle="Fully submitted"
                  onPress={() => navigation.navigate('SitesTab', { status: 'completed' })}
                />
              </View>
            </View>

            {/* Recent Assignments Header */}
            <View style={styles.sectionHeaderRow}>
              <Text style={styles.sectionHeader}>RECENT ASSIGNMENTS</Text>
              <TouchableOpacity onPress={() => navigation.navigate('SitesTab', { status: 'all' })}>
                <Text style={styles.viewAllText}>View All ({counts.total || 0})</Text>
              </TouchableOpacity>
            </View>

            {/* Recent Sites List */}
            {recentSites.length === 0 ? (
              <View style={styles.emptyBox}>
                <Ionicons name="folder-open-outline" size={36} color={COLORS.mutedForeground} />
                <Text style={styles.emptyTitle}>No assignments found</Text>
                <Text style={styles.emptySubtitle}>You currently have no active site assignments.</Text>
              </View>
            ) : (
              recentSites.map((site, index) => (
                <SiteCard
                  key={site.id || site.assignment_id || index}
                  site={site}
                  onPress={handleSitePress}
                  onEtaPress={handleEtaPress}
                  onFeasibilityPress={handleFeasibilityPress}
                />
              ))
            )}
          </>
        )}
      </ScrollView>
    </SafeAreaView>
  );
};

const styles = StyleSheet.create({
  safeArea: {
    flex: 1,
    backgroundColor: COLORS.background,
  },
  topBar: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingHorizontal: SPACING.md,
    paddingVertical: SPACING.md,
    backgroundColor: '#ffffff',
    borderBottomWidth: 1,
    borderBottomColor: COLORS.border,
  },
  userInfoCol: {
    flex: 1,
  },
  welcomeText: {
    fontSize: FONTS.small,
    color: COLORS.mutedForeground,
    fontWeight: '500',
    letterSpacing: -0.2,
  },
  nameText: {
    fontSize: FONTS.title,
    fontWeight: '800',
    color: COLORS.textPrimary,
    letterSpacing: -0.4,
    marginTop: 1,
  },
  avatarButton: {
    width: 38,
    height: 38,
    borderRadius: RADIUS.full,
    backgroundColor: COLORS.primary,
    alignItems: 'center',
    justifyContent: 'center',
    position: 'relative',
  },
  avatarText: {
    color: COLORS.primaryForeground,
    fontWeight: '700',
    fontSize: 13,
  },
  onlineBadge: {
    position: 'absolute',
    bottom: 0,
    right: 0,
    width: 10,
    height: 10,
    borderRadius: 5,
    backgroundColor: COLORS.success,
    borderWidth: 2,
    borderColor: '#ffffff',
  },
  scrollContent: {
    paddingBottom: SPACING.xxl,
  },
  loaderBox: {
    padding: SPACING.xxl,
    alignItems: 'center',
  },
  loaderText: {
    marginTop: SPACING.sm,
    color: COLORS.mutedForeground,
    fontSize: FONTS.body,
  },
  sectionHeader: {
    fontSize: 11,
    fontWeight: '700',
    color: COLORS.mutedForeground,
    letterSpacing: 0.8,
    textTransform: 'uppercase',
    marginBottom: SPACING.xs + 2,
  },
  hubContainer: {
    paddingHorizontal: SPACING.md,
    paddingTop: SPACING.md,
  },
  hubGrid: {
    gap: 8,
  },
  hubCard: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#ffffff',
    borderRadius: RADIUS.lg,
    padding: SPACING.md - 2,
    borderWidth: 1,
    borderColor: COLORS.border,
    gap: 12,
  },
  hubIconBox: {
    width: 36,
    height: 36,
    borderRadius: RADIUS.md,
    alignItems: 'center',
    justifyContent: 'center',
  },
  hubTitle: {
    fontSize: FONTS.body,
    fontWeight: '700',
    color: COLORS.textPrimary,
    letterSpacing: -0.2,
  },
  hubDesc: {
    fontSize: 11,
    color: COLORS.mutedForeground,
    marginTop: 1,
  },
  metricsContainer: {
    paddingHorizontal: SPACING.md,
    paddingTop: SPACING.lg,
  },
  statsGrid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 10,
  },
  sectionHeaderRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingHorizontal: SPACING.md,
    paddingTop: SPACING.lg,
    paddingBottom: SPACING.xs,
  },
  viewAllText: {
    fontSize: FONTS.small,
    fontWeight: '600',
    color: COLORS.primary,
  },
  emptyBox: {
    padding: SPACING.xl,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: '#ffffff',
    marginHorizontal: SPACING.md,
    borderRadius: RADIUS.lg,
    marginTop: SPACING.sm,
    borderWidth: 1,
    borderColor: COLORS.border,
  },
  emptyTitle: {
    fontSize: FONTS.body,
    fontWeight: '700',
    color: COLORS.textPrimary,
    marginTop: SPACING.xs,
  },
  emptySubtitle: {
    fontSize: FONTS.small,
    color: COLORS.mutedForeground,
    marginTop: 2,
    textAlign: 'center',
  },
  errorBanner: {
    flexDirection: 'column',
    alignItems: 'center',
    backgroundColor: COLORS.dangerLight,
    borderWidth: 1,
    borderColor: COLORS.dangerBorder,
    padding: SPACING.md,
    borderRadius: RADIUS.lg,
    marginHorizontal: SPACING.md,
    marginBottom: SPACING.md,
    gap: 8,
  },
  errorBannerText: {
    color: COLORS.dangerForeground,
    fontSize: FONTS.small,
    fontWeight: '600',
    textAlign: 'center',
    lineHeight: 18,
  },
  retryBtn: {
    backgroundColor: COLORS.danger,
    paddingHorizontal: SPACING.lg,
    paddingVertical: SPACING.sm,
    borderRadius: RADIUS.md,
    marginTop: 4,
  },
  retryBtnText: {
    color: '#ffffff',
    fontSize: FONTS.small,
    fontWeight: '700',
  },
});
