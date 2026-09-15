import React, { useState, useEffect } from 'react';
import {
  View,
  Text,
  StyleSheet,
  FlatList,
  TouchableOpacity,
  SafeAreaView,
  StatusBar,
} from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { colors } from '../../theme/colors';
import { api } from '../../api/client';
import { StatusBadge } from '../../components/StatusBadge';

export const InstallationListScreen = ({ navigation }) => {
  const [sites, setSites] = useState([]);
  const [refreshing, setRefreshing] = useState(false);

  useEffect(() => {
    loadList();
  }, []);

  const loadList = async () => {
    setRefreshing(true);
    const list = await api.getAssignedSites();
    setSites(list);
    setRefreshing(false);
  };

  return (
    <SafeAreaView style={styles.container}>
      <StatusBar barStyle="dark-content" backgroundColor={colors.background} />

      {/* Header */}
      <View style={styles.header}>
        <Text style={styles.headerTitle}>My Installations</Text>
        <Text style={styles.headerSubtitle}>Manage router configuration, serials, and milestone reports</Text>
      </View>

      <FlatList
        data={sites}
        keyExtractor={item => String(item.id)}
        contentContainerStyle={styles.listContent}
        refreshing={refreshing}
        onRefresh={loadList}
        renderItem={({ item }) => (
          <TouchableOpacity
            style={styles.instCard}
            activeOpacity={0.7}
            onPress={() => navigation.navigate('InstallationUpdate', { siteId: item.id })}
          >
            <View style={styles.cardHeader}>
              <View style={styles.siteInfo}>
                <Text style={styles.siteName}>{item.site_name}</Text>
                <Text style={styles.bankName}>{item.bank_name}</Text>
              </View>
              <StatusBadge status={item.installation_status} />
            </View>

            <View style={styles.divider} />

            {/* Network / Router Quick Details */}
            <View style={styles.routerRow}>
              <View style={styles.routerCol}>
                <Text style={styles.routerLabel}>Router Serial</Text>
                <Text style={styles.routerVal}>{item.router_serial_number || 'N/A'}</Text>
              </View>
              <View style={styles.routerCol}>
                <Text style={styles.routerLabel}>Router IP</Text>
                <Text style={[styles.routerVal, { color: colors.primary }]}>{item.router_ip || 'N/A'}</Text>
              </View>
            </View>

            <View style={styles.cardFooter}>
              <View style={{ flexDirection: 'row', alignItems: 'center' }}>
                <Ionicons name="hardware-chip-outline" size={13} color={colors.textMuted} style={{ marginRight: 4 }} />
                <Text style={styles.dateText}>Mat: {item.material_status || 'Delivered'}</Text>
              </View>

              <View style={styles.actionPill}>
                <Text style={styles.actionPillText}>Update Job</Text>
                <Ionicons name="arrow-forward" size={12} color={colors.purple} style={{ marginLeft: 4 }} />
              </View>
            </View>
          </TouchableOpacity>
        )}
      />
    </SafeAreaView>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: colors.background,
  },
  header: {
    paddingHorizontal: 18,
    paddingTop: 14,
    paddingBottom: 12,
    backgroundColor: colors.card,
    borderBottomWidth: 1,
    borderBottomColor: colors.borderLight,
  },
  headerTitle: {
    fontSize: 20,
    fontWeight: '800',
    color: colors.textPrimary,
  },
  headerSubtitle: {
    fontSize: 12,
    color: colors.textSecondary,
    marginTop: 2,
  },
  listContent: {
    padding: 16,
    paddingBottom: 32,
  },
  instCard: {
    backgroundColor: colors.card,
    borderRadius: 16,
    padding: 16,
    marginBottom: 10,
    borderWidth: 1,
    borderColor: colors.borderLight,
    shadowColor: '#000',
    shadowOpacity: 0.03,
    shadowRadius: 6,
    elevation: 2,
  },
  cardHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  siteInfo: {
    flex: 1,
    marginRight: 10,
  },
  siteName: {
    fontSize: 15,
    fontWeight: '800',
    color: colors.textPrimary,
  },
  bankName: {
    fontSize: 11,
    color: colors.textSecondary,
    marginTop: 2,
    fontWeight: '500',
  },
  divider: {
    height: 1,
    backgroundColor: colors.borderLight,
    marginVertical: 10,
  },
  routerRow: {
    flexDirection: 'row',
    backgroundColor: colors.background,
    padding: 10,
    borderRadius: 12,
    marginBottom: 10,
  },
  routerCol: {
    flex: 1,
  },
  routerLabel: {
    fontSize: 9,
    color: colors.textMuted,
    fontWeight: '700',
    textTransform: 'uppercase',
  },
  routerVal: {
    fontSize: 11,
    fontWeight: '700',
    color: colors.textPrimary,
    marginTop: 2,
    fontFamily: Platform.OS === 'ios' ? 'Courier' : 'monospace',
  },
  cardFooter: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  dateText: {
    fontSize: 11,
    color: colors.textMuted,
    textTransform: 'capitalize',
  },
  actionPill: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: colors.purpleBg,
    paddingHorizontal: 10,
    paddingVertical: 4,
    borderRadius: 10,
  },
  actionPillText: {
    fontSize: 11,
    fontWeight: '700',
    color: colors.purple,
  },
});
