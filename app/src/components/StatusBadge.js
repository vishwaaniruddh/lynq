import React from 'react';
import { View, Text, StyleSheet } from 'react-native';
import { colors } from '../theme/colors';

export const StatusBadge = ({ status, type = 'status' }) => {
  let label = status || 'Pending';
  let bg = colors.amberBg;
  let text = colors.amber;

  const s = String(status || '').toLowerCase();

  if (['adv_approved', 'approved', 'completed', 'done', 'accepted', 'active', 'delivered'].includes(s)) {
    bg = colors.emeraldBg;
    text = colors.emerald;
    label = s === 'adv_approved' ? 'ADV Approved' : s.replace(/_/g, ' ');
  } else if (['in_progress', 'working', 'dispatched', 'in_transit'].includes(s)) {
    bg = colors.purpleBg;
    text = colors.purple;
    label = s.replace(/_/g, ' ');
  } else if (['assigned', 'new', 'pending', 'awaiting'].includes(s)) {
    bg = colors.amberBg;
    text = colors.amber;
    label = s === 'assigned' ? 'Assigned' : s.replace(/_/g, ' ');
  } else if (['rejected', 'failed', 'inactive'].includes(s)) {
    bg = colors.errorBg;
    text = colors.error;
    label = s.replace(/_/g, ' ');
  }

  // Capitalize words
  label = label.replace(/\b\w/g, l => l.toUpperCase());

  return (
    <View style={[styles.badge, { backgroundColor: bg }]}>
      <Text style={[styles.badgeText, { color: text }]}>{label}</Text>
    </View>
  );
};

const styles = StyleSheet.create({
  badge: {
    paddingHorizontal: 8,
    paddingVertical: 3,
    borderRadius: 12,
    alignSelf: 'flex-start',
  },
  badgeText: {
    fontSize: 11,
    fontWeight: '700',
    textTransform: 'capitalize',
  },
});
