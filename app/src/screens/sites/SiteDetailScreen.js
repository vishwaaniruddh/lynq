import React, { useState, useEffect } from 'react';
import {
  View,
  Text,
  StyleSheet,
  ScrollView,
  TouchableOpacity,
  SafeAreaView,
  Linking,
  Alert,
} from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { colors } from '../../theme/colors';
import { api } from '../../api/client';
import { StatusBadge } from '../../components/StatusBadge';

export const SiteDetailScreen = ({ route, navigation }) => {
  const { siteId } = route.params;
  const [site, setSite] = useState(null);

  useEffect(() => {
    loadSite();
  }, [siteId]);

  const loadSite = async () => {
    const data = await api.getSiteDetails(siteId);
    setSite(data);
  };

  if (!site) {
    return (
      <View style={styles.centerContainer}>
        <Text style={styles.loadingText}>Loading site details...</Text>
      </View>
    );
  }

  const handleCall = (number) => {
    Linking.openURL(`tel:${number}`).catch(() => {
      Alert.alert('Unable to place call', `Could not dial number: ${number}`);
    });
  };

  const handleOpenMaps = () => {
    const lat = site.latitude || 30.6769;
    const lng = site.longitude || 74.7583;
    const label = encodeURIComponent(site.site_name);
    const url = `https://www.google.com/maps/search/?api=1&query=${lat},${lng}`;
    Linking.openURL(url);
  };

  return (
    <SafeAreaView style={styles.container}>
      {/* Top Bar */}
      <View style={styles.topBar}>
        <TouchableOpacity style={styles.backBtn} onPress={() => navigation.goBack()}>
          <Ionicons name="arrow-back" size={20} color={colors.textPrimary} />
        </TouchableOpacity>
        <Text style={styles.topBarTitle}>{site.site_name}</Text>
        <StatusBadge status={site.status} />
      </View>

      <ScrollView contentContainerStyle={styles.scrollContent}>
        {/* Project & Bank Banner */}
        <View style={styles.bannerCard}>
          <View style={styles.bannerRow}>
            <View style={styles.siteIconBox}>
              <Ionicons name="business" size={24} color={colors.primary} />
            </View>
            <View style={{ marginLeft: 12, flex: 1 }}>
              <View style={{ flexDirection: 'row', alignItems: 'center', gap: 6 }}>
                <Text style={styles.siteTitle}>{site.site_name}</Text>
                {site.project_name && (
                  <View style={styles.projectBadge}>
                    <Text style={styles.projectBadgeText}>{site.project_name}</Text>
                  </View>
                )}
              </View>
              <Text style={styles.bankName}>{site.bank_name}</Text>
            </View>
          </View>
        </View>

        {/* 1. Essential Information Card */}
        <View style={styles.card}>
          <Text style={styles.cardTitle}>Essential Details</Text>

          <View style={styles.infoRow}>
            <Text style={styles.infoLabel}>Customer</Text>
            <Text style={styles.infoValue}>{site.customer_name || 'Hitachi'}</Text>
          </View>

          <View style={styles.infoRow}>
            <Text style={styles.infoLabel}>Branch Name</Text>
            <Text style={styles.infoValue}>{site.branch_name || 'N/A'}</Text>
          </View>

          <View style={styles.infoRow}>
            <Text style={styles.infoLabel}>Branch ID</Text>
            <Text style={styles.infoValue}>{site.branch_id || 'N/A'}</Text>
          </View>

          <View style={styles.infoRow}>
            <Text style={styles.infoLabel}>Bank Manager</Text>
            <Text style={styles.infoValue}>{site.bank_manager || 'Dalvinder'}</Text>
          </View>
        </View>

        {/* 2. Contact Numbers Card */}
        <View style={styles.card}>
          <Text style={styles.cardTitle}>Site Contacts</Text>
          {(site.contact_numbers || ['7737500004']).map((phone, idx) => (
            <View key={idx} style={styles.phoneRow}>
              <View style={{ flexDirection: 'row', alignItems: 'center' }}>
                <Ionicons name="call-outline" size={16} color={colors.emerald} style={{ marginRight: 8 }} />
                <Text style={styles.phoneText}>{phone}</Text>
              </View>
              <TouchableOpacity style={styles.callBtn} onPress={() => handleCall(phone)}>
                <Ionicons name="call" size={13} color={colors.white} style={{ marginRight: 4 }} />
                <Text style={styles.callBtnText}>Call</Text>
              </TouchableOpacity>
            </View>
          ))}
        </View>

        {/* 3. Location & Address Card */}
        <View style={styles.card}>
          <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' }}>
            <Text style={styles.cardTitle}>Site Location</Text>
            <TouchableOpacity style={styles.mapLink} onPress={handleOpenMaps}>
              <Ionicons name="navigate" size={13} color={colors.primary} style={{ marginRight: 4 }} />
              <Text style={styles.mapLinkText}>Directions</Text>
            </TouchableOpacity>
          </View>

          <Text style={styles.addressText}>{site.address || 'Address details'}</Text>
          <Text style={styles.cityStateText}>{site.city}, {site.state}</Text>
        </View>

        {/* 4. Feasibility Check Card */}
        <View style={styles.card}>
          <View style={styles.cardHeaderRow}>
            <Text style={styles.cardTitle}>Survey / Feasibility</Text>
            <StatusBadge status={site.feasibility_status} />
          </View>
          <Text style={styles.actionCardDesc}>Verify site power, UPS, network signal, and submit survey photos.</Text>
          
          <TouchableOpacity
            style={styles.actionPrimaryBtn}
            onPress={() => navigation.navigate('FeasibilityForm', { siteId: site.id })}
          >
            <Ionicons name="clipboard-outline" size={16} color={colors.white} style={{ marginRight: 6 }} />
            <Text style={styles.actionPrimaryBtnText}>
              {site.feasibility_status === 'adv_approved' ? 'View Feasibility Check' : 'Conduct Feasibility Survey'}
            </Text>
          </TouchableOpacity>
        </View>

        {/* 5. Installation Status Card */}
        <View style={styles.card}>
          <View style={styles.cardHeaderRow}>
            <Text style={styles.cardTitle}>Installation Execution</Text>
            <StatusBadge status={site.installation_status} />
          </View>
          
          <View style={styles.networkInfoGrid}>
            <View style={styles.networkCol}>
              <Text style={styles.networkLabel}>Router S/N</Text>
              <Text style={styles.networkValue}>{site.router_serial_number || 'N/A'}</Text>
            </View>
            <View style={styles.networkCol}>
              <Text style={styles.networkLabel}>Router IP</Text>
              <Text style={[styles.networkValue, { color: colors.primary }]}>{site.router_ip || 'N/A'}</Text>
            </View>
          </View>

          <TouchableOpacity
            style={[styles.actionPrimaryBtn, { backgroundColor: colors.purple }]}
            onPress={() => navigation.navigate('InstallationUpdate', { siteId: site.id })}
          >
            <Ionicons name="construct-outline" size={16} color={colors.white} style={{ marginRight: 6 }} />
            <Text style={styles.actionPrimaryBtnText}>Update Installation Milestones</Text>
          </TouchableOpacity>
        </View>
      </ScrollView>
    </SafeAreaView>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: colors.background,
  },
  centerContainer: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
  },
  loadingText: {
    color: colors.textSecondary,
    fontSize: 13,
  },
  topBar: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: 16,
    paddingVertical: 12,
    backgroundColor: colors.card,
    borderBottomWidth: 1,
    borderBottomColor: colors.borderLight,
  },
  backBtn: {
    padding: 6,
  },
  topBarTitle: {
    fontSize: 16,
    fontWeight: '800',
    color: colors.textPrimary,
  },
  scrollContent: {
    padding: 16,
    paddingBottom: 36,
  },
  bannerCard: {
    backgroundColor: colors.card,
    borderRadius: 20,
    padding: 16,
    marginBottom: 14,
    borderWidth: 1,
    borderColor: colors.borderLight,
  },
  bannerRow: {
    flexDirection: 'row',
    alignItems: 'center',
  },
  siteIconBox: {
    width: 48,
    height: 48,
    borderRadius: 16,
    backgroundColor: colors.primary + '15',
    alignItems: 'center',
    justifyContent: 'center',
  },
  siteTitle: {
    fontSize: 17,
    fontWeight: '800',
    color: colors.textPrimary,
  },
  projectBadge: {
    backgroundColor: colors.indigoBg,
    paddingHorizontal: 6,
    paddingVertical: 1.5,
    borderRadius: 6,
  },
  projectBadgeText: {
    fontSize: 9,
    fontWeight: '800',
    color: colors.indigo,
  },
  bankName: {
    fontSize: 12,
    color: colors.textSecondary,
    marginTop: 2,
    fontWeight: '500',
  },
  card: {
    backgroundColor: colors.card,
    borderRadius: 18,
    padding: 16,
    marginBottom: 12,
    borderWidth: 1,
    borderColor: colors.borderLight,
  },
  cardTitle: {
    fontSize: 13,
    fontWeight: '800',
    color: colors.textPrimary,
    textTransform: 'uppercase',
    letterSpacing: 0.5,
    marginBottom: 10,
  },
  cardHeaderRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginBottom: 6,
  },
  actionCardDesc: {
    fontSize: 12,
    color: colors.textSecondary,
    marginBottom: 12,
  },
  infoRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    paddingVertical: 6,
    borderBottomWidth: 1,
    borderBottomColor: colors.borderLight,
  },
  infoLabel: {
    fontSize: 12,
    color: colors.textMuted,
    fontWeight: '500',
  },
  infoValue: {
    fontSize: 12,
    fontWeight: '700',
    color: colors.textPrimary,
  },
  phoneRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    backgroundColor: colors.background,
    padding: 10,
    borderRadius: 12,
    marginVertical: 4,
  },
  phoneText: {
    fontSize: 13,
    fontWeight: '700',
    color: colors.textPrimary,
    fontFamily: Platform.OS === 'ios' ? 'Courier' : 'monospace',
  },
  callBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: colors.emerald,
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: 8,
  },
  callBtnText: {
    color: colors.white,
    fontSize: 11,
    fontWeight: '700',
  },
  mapLink: {
    flexDirection: 'row',
    alignItems: 'center',
  },
  mapLinkText: {
    fontSize: 11,
    fontWeight: '700',
    color: colors.primary,
  },
  addressText: {
    fontSize: 13,
    color: colors.textPrimary,
    lineHeight: 18,
    fontWeight: '500',
  },
  cityStateText: {
    fontSize: 12,
    color: colors.textSecondary,
    marginTop: 4,
  },
  networkInfoGrid: {
    flexDirection: 'row',
    backgroundColor: colors.background,
    borderRadius: 12,
    padding: 10,
    marginBottom: 12,
  },
  networkCol: {
    flex: 1,
  },
  networkLabel: {
    fontSize: 10,
    color: colors.textMuted,
    fontWeight: '600',
    textTransform: 'uppercase',
  },
  networkValue: {
    fontSize: 12,
    fontWeight: '700',
    color: colors.textPrimary,
    marginTop: 2,
    fontFamily: Platform.OS === 'ios' ? 'Courier' : 'monospace',
  },
  actionPrimaryBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: colors.primary,
    paddingVertical: 12,
    borderRadius: 12,
    shadowColor: '#000',
    shadowOpacity: 0.1,
    shadowRadius: 4,
    elevation: 2,
  },
  actionPrimaryBtnText: {
    color: colors.white,
    fontSize: 13,
    fontWeight: '700',
  },
});
