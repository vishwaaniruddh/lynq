import React from 'react';
import { View, Text, StyleSheet, TouchableOpacity } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { COLORS, FONTS, RADIUS, SHADOWS, SPACING } from '../constants/theme';

export const StatCard = ({
  title,
  count,
  icon,
  iconColor,
  iconBg,
  subtitle,
  onPress,
  isActive = false,
}) => {
  return (
    <TouchableOpacity
      onPress={onPress}
      disabled={!onPress}
      activeOpacity={0.8}
      style={[
        styles.card,
        isActive && styles.cardActive,
        SHADOWS.small,
      ]}
    >
      <View style={styles.topRow}>
        <Text style={styles.titleText}>{title}</Text>
        <View style={[styles.iconBox, { backgroundColor: iconBg || COLORS.secondary }]}>
          <Ionicons
            name={icon}
            size={16}
            color={iconColor || COLORS.textPrimary}
          />
        </View>
      </View>

      <Text style={styles.countText}>{count ?? 0}</Text>

      {subtitle ? (
        <Text style={styles.subtitleText}>{subtitle}</Text>
      ) : null}
    </TouchableOpacity>
  );
};

const styles = StyleSheet.create({
  card: {
    backgroundColor: '#ffffff',
    borderRadius: RADIUS.lg,
    padding: SPACING.md,
    borderWidth: 1,
    borderColor: COLORS.border,
    flex: 1,
    minWidth: '47%',
  },
  cardActive: {
    borderColor: COLORS.primary,
    borderWidth: 1.5,
  },
  topRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginBottom: 6,
  },
  titleText: {
    fontSize: FONTS.small,
    fontWeight: '600',
    color: COLORS.mutedForeground,
    letterSpacing: -0.2,
  },
  iconBox: {
    width: 28,
    height: 28,
    borderRadius: RADIUS.sm,
    alignItems: 'center',
    justifyContent: 'center',
  },
  countText: {
    fontSize: FONTS.header,
    fontWeight: '800',
    color: COLORS.textPrimary,
    letterSpacing: -0.5,
  },
  subtitleText: {
    fontSize: 11,
    color: COLORS.mutedForeground,
    marginTop: 4,
  },
});
