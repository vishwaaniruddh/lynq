import React from 'react';
import { View, Text, StyleSheet, TouchableOpacity } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { COLORS, FONTS, RADIUS, SHADOWS, SPACING } from '../constants/theme';

export const SiteCard = ({
  site,
  onPress,
  onEtaPress,
  onFeasibilityPress,
}) => {
  const getStatusBadge = (status) => {
    switch (status) {
      case 'completed':
        return {
          label: 'Completed',
          bg: COLORS.successLight,
          border: COLORS.successBorder,
          text: COLORS.successForeground,
          icon: 'checkmark-circle-outline',
        };
      case 'in_progress':
        return {
          label: 'In Progress',
          bg: COLORS.warningLight,
          border: COLORS.warningBorder,
          text: COLORS.warningForeground,
          icon: 'timer-outline',
        };
      case 'assigned':
      default:
        return {
          label: 'Assigned',
          bg: '#f4f4f5',
          border: '#e4e4e7',
          text: '#27272a',
          icon: 'paper-plane-outline',
        };
    }
  };

  const getFeasibilityBadge = (status) => {
    switch (status) {
      case 'contractor_rejected':
      case 'adv_rejected':
        return {
          label: status === 'adv_rejected' ? 'ADV Rejected' : 'Rejected by Admin',
          bg: COLORS.dangerLight,
          border: COLORS.dangerBorder,
          text: COLORS.dangerForeground,
          icon: 'alert-circle',
        };
      case 'pending_contractor_review':
        return {
          label: 'Submitted (In Review)',
          bg: '#f3e8ff',
          border: '#d8b4fe',
          text: '#7e22ce',
          icon: 'hourglass',
        };
      case 'feasibility_completed':
      case 'adv_approved':
      case 'contractor_approved':
        return {
          label: 'Feasibility Done',
          bg: COLORS.successLight,
          border: COLORS.successBorder,
          text: COLORS.successForeground,
          icon: 'shield-checkmark',
        };
      case 'ada_submitted':
        return {
          label: 'Arrived (ADA)',
          bg: '#eff6ff',
          border: '#bfdbfe',
          text: '#1d4ed8',
          icon: 'location',
        };
      case 'eta_submitted':
        return {
          label: 'ETA Set',
          bg: '#fffbeb',
          border: '#fde68a',
          text: '#b45309',
          icon: 'time',
        };
      case 'pending_eta':
      default:
        return {
          label: 'Pending ETA',
          bg: '#f4f4f5',
          border: '#e4e4e7',
          text: '#71717a',
          icon: 'hourglass-outline',
        };
    }
  };

  const statusBadge = getStatusBadge(site.status);
  const feasBadge = getFeasibilityBadge(site.feasibility_status);

  const hasEta = Boolean(
    (site.eta && String(site.eta).trim() !== '' && site.eta !== 'null') ||
    (site.eta_datetime && String(site.eta_datetime).trim() !== '' && site.eta_datetime !== 'null') ||
    (site.current_eta && String(site.current_eta).trim() !== '' && site.current_eta !== 'null')
  );

  const hasAda = Boolean(
    site.ada_datetime ||
    (site.feasibility_status && !['pending_eta', 'eta_submitted'].includes(site.feasibility_status))
  );

  const isRejected = Boolean(
    ['contractor_rejected', 'adv_rejected'].includes(site.feasibility_status)
  );

  const isFeasibilityDone = Boolean(
    ['feasibility_completed', 'pending_contractor_review', 'adv_approved', 'contractor_approved'].includes(site.feasibility_status)
  );

  const atmId = site.atm_id || site.site_name || `Site #${site.id || site.site_id}`;
  const bankName = site.bank_name || site.customer_name || 'Bank ATM';
  const locationLine = [site.city, site.state].filter(Boolean).join(', ') || site.address || 'Address pending';

  return (
    <TouchableOpacity
      onPress={() => onPress && onPress(site)}
      activeOpacity={0.88}
      style={[styles.card, SHADOWS.small]}
    >
      {/* Top Header Row */}
      <View style={styles.headerRow}>
        <View style={styles.atmIdentity}>
          <View style={styles.bankIconBox}>
            <Ionicons name="business" size={15} color={COLORS.textPrimary} />
          </View>
          <View style={styles.atmTextContainer}>
            <Text style={styles.atmIdText} numberOfLines={1}>{atmId}</Text>
            <Text style={styles.bankNameText} numberOfLines={1}>{bankName}</Text>
          </View>
        </View>

        <View style={[styles.statusBadge, { backgroundColor: statusBadge.bg, borderColor: statusBadge.border }]}>
          <Ionicons name={statusBadge.icon} size={11} color={statusBadge.text} style={{ marginRight: 3 }} />
          <Text style={[styles.statusBadgeText, { color: statusBadge.text }]}>
            {statusBadge.label}
          </Text>
        </View>
      </View>

      {/* Location Row */}
      <View style={styles.locationRow}>
        <Ionicons name="location-sharp" size={13} color={COLORS.mutedForeground} style={{ marginTop: 1 }} />
        <Text style={styles.locationText} numberOfLines={2}>
          {site.address ? `${site.address}, ${locationLine}` : locationLine}
        </Text>
      </View>

      {/* Workflow Progress Micro-Bar */}
      <View style={styles.flowBar}>
        {/* Step 1: Assigned */}
        <View style={styles.flowStep}>
          <View style={[styles.flowDot, styles.flowDotActive]}>
            <Ionicons name="checkmark" size={9} color="#ffffff" />
          </View>
          <Text style={[styles.flowLabel, styles.flowLabelActive]}>Assigned</Text>
        </View>
        <View style={[styles.flowLine, hasEta && styles.flowLineActive]} />

        {/* Step 2: ETA */}
        <View style={styles.flowStep}>
          <View style={[styles.flowDot, hasEta ? styles.flowDotActive : styles.flowDotPending]}>
            {hasEta ? (
              <Ionicons name="checkmark" size={9} color="#ffffff" />
            ) : (
              <Text style={styles.flowDotNum}>2</Text>
            )}
          </View>
          <Text style={[styles.flowLabel, hasEta && styles.flowLabelActive]}>
            {hasEta ? 'ETA Set' : 'ETA'}
          </Text>
        </View>
        <View style={[styles.flowLine, hasAda && styles.flowLineActive]} />

        {/* Step 3: ADA Arrival */}
        <View style={styles.flowStep}>
          <View style={[styles.flowDot, hasAda ? styles.flowDotActive : styles.flowDotPending]}>
            {hasAda ? (
              <Ionicons name="checkmark" size={9} color="#ffffff" />
            ) : (
              <Text style={styles.flowDotNum}>3</Text>
            )}
          </View>
          <Text style={[styles.flowLabel, hasAda && styles.flowLabelActive]}>
            {hasAda ? 'Arrived' : 'Arrival'}
          </Text>
        </View>
        <View style={[styles.flowLine, isFeasibilityDone && styles.flowLineActive]} />

        {/* Step 4: Feasibility */}
        <View style={styles.flowStep}>
          <View style={[styles.flowDot, isFeasibilityDone ? styles.flowDotActive : styles.flowDotPending]}>
            {isFeasibilityDone ? (
              <Ionicons name="checkmark" size={9} color="#ffffff" />
            ) : (
              <Text style={styles.flowDotNum}>4</Text>
            )}
          </View>
          <Text style={[styles.flowLabel, isFeasibilityDone && styles.flowLabelActive]}>
            Feasibility
          </Text>
        </View>
      </View>

      {/* Meta Timestamps */}
      {(hasEta || hasAda) && (
        <View style={styles.metaRow}>
          {hasEta && (
            <View style={styles.metaChip}>
              <Ionicons name="time-outline" size={11} color={COLORS.textSecondary} />
              <Text style={styles.metaChipText} numberOfLines={1}>
                ETA: {site.eta || site.eta_datetime}
              </Text>
            </View>
          )}
          {hasAda && (
            <View style={[styles.metaChip, styles.metaChipAda]}>
              <Ionicons name="pin" size={11} color="#047857" />
              <Text style={[styles.metaChipText, { color: '#047857', fontWeight: '600' }]}>
                On Site (ADA Logged)
              </Text>
            </View>
          )}
        </View>
      )}

      {/* Card Footer Actions */}
      <View style={styles.footerRow}>
        <View style={[styles.feasBadge, { backgroundColor: feasBadge.bg, borderColor: feasBadge.border }]}>
          <Ionicons name={feasBadge.icon} size={11} color={feasBadge.text} style={{ marginRight: 3 }} />
          <Text style={[styles.feasBadgeText, { color: feasBadge.text }]}>
            {feasBadge.label}
          </Text>
        </View>

        <View style={styles.actionButtons}>
          {onEtaPress && !hasAda && (
            <TouchableOpacity
              onPress={() => onEtaPress(site)}
              style={styles.outlineBtn}
              activeOpacity={0.7}
            >
              <Ionicons name="alarm-outline" size={13} color={COLORS.textPrimary} />
              <Text style={styles.outlineBtnText}>
                {hasEta ? 'Edit ETA' : 'Set ETA'}
              </Text>
            </TouchableOpacity>
          )}

          {onFeasibilityPress && (
            <TouchableOpacity
              onPress={() => onFeasibilityPress(site)}
              style={[
                styles.primaryBtn,
                !hasEta && styles.primaryBtnDisabled,
                hasEta && !hasAda && styles.primaryBtnAda,
                isRejected && styles.primaryBtnRejected,
                isFeasibilityDone && styles.primaryBtnDone,
              ]}
              activeOpacity={0.8}
            >
              <Ionicons
                name={
                  isRejected
                    ? 'construct-outline'
                    : isFeasibilityDone
                    ? 'eye-outline'
                    : hasAda
                    ? 'clipboard-outline'
                    : hasEta
                    ? 'navigate-outline'
                    : 'lock-closed-outline'
                }
                size={13}
                color={!hasEta ? COLORS.mutedForeground : '#ffffff'}
              />
              <Text
                style={[
                  styles.primaryBtnText,
                  !hasEta && { color: COLORS.mutedForeground },
                ]}
              >
                {isRejected
                  ? 'Fix & Reconfigure'
                  : isFeasibilityDone
                  ? 'View Survey'
                  : hasAda
                  ? 'Start Feasibility'
                  : hasEta
                  ? 'Mark Arrival'
                  : 'ETA Required'}
              </Text>
            </TouchableOpacity>
          )}
        </View>
      </View>
    </TouchableOpacity>
  );
};

const styles = StyleSheet.create({
  card: {
    backgroundColor: '#ffffff',
    borderRadius: RADIUS.lg,
    padding: SPACING.md,
    marginVertical: 6,
    marginHorizontal: SPACING.md,
    borderWidth: 1,
    borderColor: COLORS.border,
  },
  headerRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
    marginBottom: 8,
  },
  atmIdentity: {
    flexDirection: 'row',
    alignItems: 'center',
    flex: 1,
    marginRight: 10,
  },
  bankIconBox: {
    width: 32,
    height: 32,
    borderRadius: RADIUS.md,
    backgroundColor: COLORS.secondary,
    borderWidth: 1,
    borderColor: COLORS.border,
    alignItems: 'center',
    justifyContent: 'center',
    marginRight: 10,
  },
  atmTextContainer: {
    flex: 1,
  },
  atmIdText: {
    fontSize: FONTS.subtitle,
    fontWeight: '800',
    color: COLORS.textPrimary,
    letterSpacing: -0.3,
  },
  bankNameText: {
    fontSize: FONTS.tiny,
    fontWeight: '500',
    color: COLORS.mutedForeground,
    marginTop: 1,
  },
  statusBadge: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 8,
    paddingVertical: 3,
    borderRadius: RADIUS.full,
    borderWidth: 1,
  },
  statusBadgeText: {
    fontSize: 10,
    fontWeight: '700',
    letterSpacing: -0.1,
  },
  locationRow: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    gap: 4,
    marginBottom: 10,
  },
  locationText: {
    fontSize: FONTS.small,
    color: COLORS.textSecondary,
    flex: 1,
    lineHeight: 16,
  },
  flowBar: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#f8fafc',
    paddingVertical: 8,
    paddingHorizontal: 10,
    borderRadius: RADIUS.md,
    borderWidth: 1,
    borderColor: '#e2e8f0',
    marginBottom: 10,
  },
  flowStep: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
  },
  flowDot: {
    width: 16,
    height: 16,
    borderRadius: 8,
    alignItems: 'center',
    justifyContent: 'center',
  },
  flowDotActive: {
    backgroundColor: COLORS.primary,
  },
  flowDotPending: {
    backgroundColor: '#e2e8f0',
  },
  flowDotNum: {
    fontSize: 8,
    fontWeight: '700',
    color: '#64748b',
  },
  flowLabel: {
    fontSize: 9,
    fontWeight: '600',
    color: '#94a3b8',
  },
  flowLabelActive: {
    color: COLORS.textPrimary,
    fontWeight: '700',
  },
  flowLine: {
    flex: 1,
    height: 1.5,
    backgroundColor: '#e2e8f0',
    marginHorizontal: 4,
  },
  flowLineActive: {
    backgroundColor: COLORS.primary,
  },
  metaRow: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 6,
    marginBottom: 10,
  },
  metaChip: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
    backgroundColor: COLORS.secondary,
    paddingHorizontal: 8,
    paddingVertical: 3,
    borderRadius: RADIUS.sm,
    borderWidth: 1,
    borderColor: COLORS.border,
  },
  metaChipAda: {
    backgroundColor: '#ecfdf5',
    borderColor: '#a7f3d0',
  },
  metaChipText: {
    fontSize: 10,
    color: COLORS.textSecondary,
    fontWeight: '500',
  },
  footerRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingTop: 8,
    borderTopWidth: 1,
    borderTopColor: '#f4f4f5',
  },
  feasBadge: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 8,
    paddingVertical: 3,
    borderRadius: RADIUS.full,
    borderWidth: 1,
  },
  feasBadgeText: {
    fontSize: 10,
    fontWeight: '600',
  },
  actionButtons: {
    flexDirection: 'row',
    gap: 6,
    alignItems: 'center',
  },
  outlineBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
    paddingHorizontal: 9,
    paddingVertical: 6,
    borderRadius: RADIUS.md,
    borderWidth: 1,
    borderColor: COLORS.border,
    backgroundColor: '#ffffff',
  },
  outlineBtnText: {
    fontSize: 11,
    fontWeight: '600',
    color: COLORS.textPrimary,
  },
  primaryBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: RADIUS.md,
    backgroundColor: COLORS.primary,
  },
  primaryBtnAda: {
    backgroundColor: '#2563eb', // Blue for Mark Arrival
  },
  primaryBtnDone: {
    backgroundColor: '#047857', // Green for completed
  },
  primaryBtnRejected: {
    backgroundColor: COLORS.danger, // Red for rejected reconfigure
  },
  primaryBtnDisabled: {
    backgroundColor: COLORS.secondary,
    borderWidth: 1,
    borderColor: COLORS.border,
  },
  primaryBtnText: {
    fontSize: 11,
    fontWeight: '700',
    color: '#ffffff',
  },
});
