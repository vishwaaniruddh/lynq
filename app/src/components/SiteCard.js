import React from 'react';
import { View, Text, StyleSheet, TouchableOpacity } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { colors } from '../theme/colors';
import { StatusBadge } from './StatusBadge';

export const SiteCard = ({ site, onPress }) => {
  return (
    <TouchableOpacity activeOpacity={0.7} onPress={onPress} style={styles.card}>
      <View style={styles.header}>
        <View style={styles.iconTitleRow}>
          <View style={styles.iconBox}>
            <Ionicons name="location-sharp" size={16} color={colors.primary} />
          </View>
          <View style={{ marginLeft: 10, flex: 1 }}>
            <View style={{ flexDirection: 'row', alignItems: 'center', gap: 6 }}>
              <Text style={styles.siteName}>{site.site_name}</Text>
              {site.project_name && (
                <View style={styles.projectBadge}>
                  <Text style={styles.projectBadgeText}>{site.project_name}</Text>
                </View>
              )}
            </View>
            <Text style={styles.bankName} numberOfLines={1}>
              {site.bank_name || 'Bank Not Specified'}
            </Text>
          </View>
        </View>
        <StatusBadge status={site.status || site.feasibility_status} />
      </View>

      <View style={styles.divider} />

      <View style={styles.footer}>
        <View style={styles.locationRow}>
          <Ionicons name="map-outline" size={13} color={colors.textMuted} style={{ marginRight: 4 }} />
          <Text style={styles.locationText} numberOfLines={1}>
            {site.city ? `${site.city}, ${site.state || ''}` : site.address || 'Location specified'}
          </Text>
        </View>

        <View style={styles.viewDetailRow}>
          <Text style={styles.viewDetailText}>Details</Text>
          <Ionicons name="chevron-forward" size={14} color={colors.primary} />
        </View>
      </View>
    </TouchableOpacity>
  );
};

const styles = StyleSheet.create({
  card: {
    backgroundColor: colors.card,
    borderRadius: 16,
    padding: 14,
    marginVertical: 6,
    borderWidth: 1,
    borderColor: colors.borderLight,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.03,
    shadowRadius: 5,
    elevation: 2,
  },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  iconTitleRow: {
    flexDirection: 'row',
    alignItems: 'center',
    flex: 1,
    marginRight: 8,
  },
  iconBox: {
    width: 34,
    height: 34,
    borderRadius: 10,
    backgroundColor: colors.primary + '15',
    alignItems: 'center',
    justifyContent: 'center',
  },
  siteName: {
    fontSize: 14,
    fontWeight: '800',
    color: colors.textPrimary,
  },
  projectBadge: {
    backgroundColor: colors.indigoBg,
    paddingHorizontal: 6,
    paddingVertical: 1,
    borderRadius: 6,
  },
  projectBadgeText: {
    fontSize: 9,
    fontWeight: '700',
    color: colors.indigo,
  },
  bankName: {
    fontSize: 11,
    color: colors.textSecondary,
    marginTop: 1,
    fontWeight: '500',
  },
  divider: {
    height: 1,
    backgroundColor: colors.borderLight,
    marginVertical: 10,
  },
  footer: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  locationRow: {
    flexDirection: 'row',
    alignItems: 'center',
    flex: 1,
    marginRight: 10,
  },
  locationText: {
    fontSize: 11,
    color: colors.textSecondary,
    fontWeight: '500',
  },
  viewDetailRow: {
    flexDirection: 'row',
    alignItems: 'center',
  },
  viewDetailText: {
    fontSize: 11,
    fontWeight: '700',
    color: colors.primary,
    marginRight: 2,
  },
});
