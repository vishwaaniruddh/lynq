import React, { useState, useEffect } from 'react';
import {
  View,
  Text,
  StyleSheet,
  ScrollView,
  RefreshControl,
  TouchableOpacity,
  SafeAreaView,
  StatusBar,
} from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { colors } from '../../theme/colors';
import { useAuth } from '../../context/AuthContext';
import { api } from '../../api/client';
import { KpiCard } from '../../components/KpiCard';
import { SiteCard } from '../../components/SiteCard';

export const DashboardScreen = ({ navigation }) => {
  const { user } = useAuth();
  const [summary, setSummary] = useState({
    kpi: { total: 0, new: 0, inProgress: 0, completed: 0 },
    recentSites: [],
  });
  const [refreshing, setRefreshing] = useState(false);

  useEffect(() => {
    loadData();
  }, []);

  const loadData = async () => {
    setRefreshing(true);
    const data = await api.getDashboardSummary();
    setSummary(data);
    setRefreshing(false);
  };

  const displayName = user?.name || user?.username || 'Engineer';
  const initialLetter = displayName.charAt(0).toUpperCase();

  return (
    <SafeAreaView style={styles.container}>
      <StatusBar barStyle="dark-content" backgroundColor={colors.background} />

      {/* Top Header Bar */}
      <View style={styles.topHeader}>
        <View style={styles.logoRow}>
          <Text style={styles.appLogo}>LYNQ</Text>
          <View style={styles.headerTag}>
            <Text style={styles.headerTagText}>Engineer Portal</Text>
          </View>
        </View>

        <View style={styles.headerActions}>
          <TouchableOpacity style={styles.iconButton} onPress={() => navigation.navigate('PendingReceives')}>
            <Ionicons name="notifications-outline" size={20} color={colors.textPrimary} />
            <View style={styles.notifDot} />
          </TouchableOpacity>
          <TouchableOpacity style={styles.avatarCircle} onPress={() => navigation.navigate('Profile')}>
            <Text style={styles.avatarLetter}>{initialLetter}</Text>
          </TouchableOpacity>
        </View>
      </View>

      <ScrollView
        contentContainerStyle={styles.scrollContent}
        refreshControl={<RefreshControl refreshing={refreshing} onRefresh={loadData} colors={[colors.primary]} />}
      >
        {/* Welcome Banner matching web UI */}
        <View style={styles.welcomeBanner}>
          <View style={styles.welcomeTextContainer}>
            <Text style={styles.welcomeTitle}>Welcome, {user?.username || 'eng'}!</Text>
            <Text style={styles.welcomeSubtitle}>Here's an overview of your assigned sites</Text>
          </View>
          <View style={styles.hardhatCircle}>
            <Ionicons name="construct" size={24} color={colors.primary} />
          </View>
        </View>

        {/* 4 Summary KPI Cards */}
        <View style={styles.kpiGrid}>
          <View style={styles.kpiRow}>
            <KpiCard
              title="Total Sites"
              count={summary.kpi.total}
              subtitle="Assigned to you"
              iconName="location-sharp"
              iconBg="#eff6ff"
              countColor={colors.primary}
              onPress={() => navigation.navigate('Sites', { filter: 'all' })}
            />
            <KpiCard
              title="New"
              count={summary.kpi.new}
              subtitle="Awaiting action"
              iconName="time-sharp"
              iconBg={colors.amberBg}
              countColor={colors.amber}
              onPress={() => navigation.navigate('Sites', { filter: 'new' })}
            />
          </View>

          <View style={styles.kpiRow}>
            <KpiCard
              title="In Progress"
              count={summary.kpi.inProgress}
              subtitle="Working on"
              iconName="sync-sharp"
              iconBg={colors.purpleBg}
              countColor={colors.purple}
              onPress={() => navigation.navigate('Sites', { filter: 'in_progress' })}
            />
            <KpiCard
              title="Completed"
              count={summary.kpi.completed}
              subtitle="Finished"
              iconName="checkmark-circle-sharp"
              iconBg={colors.emeraldBg}
              countColor={colors.emerald}
              onPress={() => navigation.navigate('Sites', { filter: 'completed' })}
            />
          </View>
        </View>

        {/* Engineer Profile Card matching Web UI */}
        <View style={styles.profileCard}>
          <View style={styles.profileHeader}>
            <View style={styles.profileAvatarBox}>
              <Text style={styles.profileAvatarText}>{initialLetter}</Text>
            </View>
            <View style={{ marginLeft: 14, flex: 1 }}>
              <Text style={styles.profileName}>{displayName}</Text>
              <Text style={styles.profileEmail}>{user?.email || 'eng@eng.com'}</Text>
              <View style={styles.rolePill}>
                <Ionicons name="briefcase" size={10} color={colors.purple} style={{ marginRight: 4 }} />
                <Text style={styles.rolePillText}>Field Engineer</Text>
              </View>
            </View>
          </View>

          <View style={styles.profileDivider} />

          <View style={styles.profileDetailsRow}>
            <View>
              <Text style={styles.profileMetaLabel}>Company</Text>
              <Text style={styles.profileMetaValue}>{user?.company_name || 'Cleared Secured Services'}</Text>
            </View>
            <View style={{ alignItems: 'flex-end' }}>
              <Text style={styles.profileMetaLabel}>Active Sites</Text>
              <Text style={styles.profileMetaValue}>{summary.kpi.total} Assigned</Text>
            </View>
          </View>
        </View>

        {/* Recent Assignments Header */}
        <View style={styles.sectionHeader}>
          <Text style={styles.sectionTitle}>Recent Assignments</Text>
          <TouchableOpacity onPress={() => navigation.navigate('Sites')}>
            <Text style={styles.viewAllText}>View All</Text>
          </TouchableOpacity>
        </View>

        {/* Recent Sites List */}
        {summary.recentSites.map(site => (
          <SiteCard
            key={site.id}
            site={site}
            onPress={() => navigation.navigate('SiteDetail', { siteId: site.id })}
          />
        ))}
      </ScrollView>
    </SafeAreaView>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: colors.background,
  },
  topHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: 18,
    paddingVertical: 12,
    backgroundColor: colors.card,
    borderBottomWidth: 1,
    borderBottomColor: colors.borderLight,
  },
  logoRow: {
    flexDirection: 'row',
    alignItems: 'center',
  },
  appLogo: {
    fontSize: 20,
    fontWeight: '900',
    color: colors.slate,
    letterSpacing: 1.5,
  },
  headerTag: {
    marginLeft: 8,
    backgroundColor: colors.primary + '15',
    paddingHorizontal: 8,
    paddingVertical: 2,
    borderRadius: 8,
  },
  headerTagText: {
    fontSize: 10,
    fontWeight: '700',
    color: colors.primary,
  },
  headerActions: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
  },
  iconButton: {
    width: 36,
    height: 36,
    borderRadius: 18,
    backgroundColor: colors.background,
    alignItems: 'center',
    justifyContent: 'center',
  },
  notifDot: {
    position: 'absolute',
    top: 8,
    right: 8,
    width: 7,
    height: 7,
    borderRadius: 4,
    backgroundColor: colors.primary,
  },
  avatarCircle: {
    width: 36,
    height: 36,
    borderRadius: 18,
    backgroundColor: colors.purple,
    alignItems: 'center',
    justifyContent: 'center',
  },
  avatarLetter: {
    color: colors.white,
    fontWeight: '800',
    fontSize: 14,
  },
  scrollContent: {
    padding: 16,
    paddingBottom: 32,
  },
  welcomeBanner: {
    backgroundColor: colors.primary,
    borderRadius: 20,
    padding: 18,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginBottom: 16,
    shadowColor: colors.primary,
    shadowOffset: { width: 0, height: 6 },
    shadowOpacity: 0.25,
    shadowRadius: 10,
    elevation: 4,
  },
  welcomeTextContainer: {
    flex: 1,
    paddingRight: 10,
  },
  welcomeTitle: {
    fontSize: 18,
    fontWeight: '800',
    color: colors.white,
  },
  welcomeSubtitle: {
    fontSize: 12,
    color: 'rgba(255,255,255,0.85)',
    marginTop: 4,
  },
  hardhatCircle: {
    width: 44,
    height: 44,
    borderRadius: 22,
    backgroundColor: colors.white,
    alignItems: 'center',
    justifyContent: 'center',
    shadowColor: '#000',
    shadowOpacity: 0.1,
    shadowRadius: 4,
    elevation: 2,
  },
  kpiGrid: {
    marginBottom: 16,
  },
  kpiRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
  },
  profileCard: {
    backgroundColor: colors.card,
    borderRadius: 20,
    padding: 16,
    marginBottom: 20,
    borderWidth: 1,
    borderColor: colors.borderLight,
    shadowColor: '#000',
    shadowOpacity: 0.03,
    shadowRadius: 6,
    elevation: 2,
  },
  profileHeader: {
    flexDirection: 'row',
    alignItems: 'center',
  },
  profileAvatarBox: {
    width: 52,
    height: 52,
    borderRadius: 16,
    backgroundColor: colors.purple,
    alignItems: 'center',
    justifyContent: 'center',
  },
  profileAvatarText: {
    color: colors.white,
    fontSize: 22,
    fontWeight: '900',
  },
  profileName: {
    fontSize: 15,
    fontWeight: '800',
    color: colors.textPrimary,
  },
  profileEmail: {
    fontSize: 12,
    color: colors.textSecondary,
    marginTop: 1,
  },
  rolePill: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: colors.purpleBg,
    paddingHorizontal: 8,
    paddingVertical: 2,
    borderRadius: 8,
    alignSelf: 'flex-start',
    marginTop: 4,
  },
  rolePillText: {
    fontSize: 10,
    fontWeight: '700',
    color: colors.purple,
  },
  profileDivider: {
    height: 1,
    backgroundColor: colors.borderLight,
    marginVertical: 12,
  },
  profileDetailsRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  profileMetaLabel: {
    fontSize: 11,
    color: colors.textMuted,
    fontWeight: '500',
  },
  profileMetaValue: {
    fontSize: 12,
    fontWeight: '700',
    color: colors.textPrimary,
    marginTop: 2,
  },
  sectionHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginBottom: 10,
  },
  sectionTitle: {
    fontSize: 15,
    fontWeight: '800',
    color: colors.textPrimary,
  },
  viewAllText: {
    fontSize: 12,
    fontWeight: '700',
    color: colors.primary,
  },
});
