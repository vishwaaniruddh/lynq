import React, { useState, useEffect } from 'react';
import {
  View,
  Text,
  StyleSheet,
  FlatList,
  TouchableOpacity,
  SafeAreaView,
  StatusBar,
  Alert,
} from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { colors } from '../../theme/colors';
import { api } from '../../api/client';
import { StatusBadge } from '../../components/StatusBadge';

export const PendingReceivesScreen = ({ navigation }) => {
  const [items, setItems] = useState([]);
  const [refreshing, setRefreshing] = useState(false);

  useEffect(() => {
    loadList();
  }, []);

  const loadList = async () => {
    setRefreshing(true);
    const list = await api.getPendingReceives();
    setItems(list);
    setRefreshing(false);
  };

  const handleAcknowledge = async (item) => {
    Alert.alert(
      'Confirm Receipt',
      `Have you received package "${item.manifest_number}" via ${item.courier_name}?`,
      [
        { text: 'Cancel', style: 'cancel' },
        {
          text: 'Yes, Received',
          onPress: async () => {
            const res = await api.acknowledgeMaterial(item.id);
            if (res.success) {
              Alert.alert('Success', 'Material receipt acknowledged!');
              loadList();
            }
          },
        },
      ]
    );
  };

  return (
    <SafeAreaView style={styles.container}>
      <StatusBar barStyle="dark-content" backgroundColor={colors.background} />

      {/* Header */}
      <View style={styles.topBar}>
        <TouchableOpacity style={styles.backBtn} onPress={() => navigation.goBack()}>
          <Ionicons name="arrow-back" size={20} color={colors.textPrimary} />
        </TouchableOpacity>
        <Text style={styles.topBarTitle}>Pending Receives</Text>
        <View style={{ width: 24 }} />
      </View>

      <FlatList
        data={items}
        keyExtractor={item => String(item.id)}
        contentContainerStyle={styles.listContent}
        refreshing={refreshing}
        onRefresh={loadList}
        renderItem={({ item }) => (
          <View style={styles.card}>
            <View style={styles.cardHeader}>
              <View style={styles.siteInfo}>
                <Text style={styles.siteName}>{item.site_name}</Text>
                <Text style={styles.manifestNo}>Manifest: {item.manifest_number}</Text>
              </View>
              <StatusBadge status={item.status} />
            </View>

            <View style={styles.divider} />

            <Text style={styles.matName}>{item.material_name}</Text>

            <View style={styles.courierBox}>
              <View style={styles.courierRow}>
                <Ionicons name="car-outline" size={14} color={colors.textMuted} style={{ marginRight: 6 }} />
                <Text style={styles.courierLabel}>Courier: </Text>
                <Text style={styles.courierVal}>{item.courier_name}</Text>
              </View>

              <View style={styles.courierRow}>
                <Ionicons name="barcode-outline" size={14} color={colors.textMuted} style={{ marginRight: 6 }} />
                <Text style={styles.courierLabel}>AWB / Tracking: </Text>
                <Text style={styles.trackingVal}>{item.tracking_number}</Text>
              </View>
            </View>

            {item.status !== 'delivered' && (
              <TouchableOpacity style={styles.ackBtn} onPress={() => handleAcknowledge(item)}>
                <Ionicons name="checkmark-done" size={16} color={colors.white} style={{ marginRight: 6 }} />
                <Text style={styles.ackBtnText}>Acknowledge Package Receipt</Text>
              </TouchableOpacity>
            )}
          </View>
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
  listContent: {
    padding: 16,
    paddingBottom: 32,
  },
  card: {
    backgroundColor: colors.card,
    borderRadius: 16,
    padding: 16,
    marginBottom: 12,
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
    marginRight: 8,
  },
  siteName: {
    fontSize: 15,
    fontWeight: '800',
    color: colors.textPrimary,
  },
  manifestNo: {
    fontSize: 11,
    color: colors.textSecondary,
    marginTop: 2,
    fontWeight: '600',
    fontFamily: Platform.OS === 'ios' ? 'Courier' : 'monospace',
  },
  divider: {
    height: 1,
    backgroundColor: colors.borderLight,
    marginVertical: 10,
  },
  matName: {
    fontSize: 13,
    fontWeight: '700',
    color: colors.textPrimary,
    marginBottom: 10,
  },
  courierBox: {
    backgroundColor: colors.background,
    padding: 10,
    borderRadius: 10,
    gap: 4,
    marginBottom: 12,
  },
  courierRow: {
    flexDirection: 'row',
    alignItems: 'center',
  },
  courierLabel: {
    fontSize: 11,
    color: colors.textMuted,
  },
  courierVal: {
    fontSize: 11,
    fontWeight: '700',
    color: colors.textPrimary,
  },
  trackingVal: {
    fontSize: 11,
    fontWeight: '700',
    color: colors.primary,
    fontFamily: Platform.OS === 'ios' ? 'Courier' : 'monospace',
  },
  ackBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: colors.emerald,
    paddingVertical: 10,
    borderRadius: 10,
  },
  ackBtnText: {
    color: colors.white,
    fontSize: 12,
    fontWeight: '700',
  },
});
