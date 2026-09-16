import React, { useState } from 'react';
import {
  View,
  Text,
  StyleSheet,
  SafeAreaView,
  ScrollView,
  TouchableOpacity,
  Alert,
  Switch,
} from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { useAuth } from '../context/AuthContext';
import { Header } from '../components/Header';
import { CustomButton } from '../components/CustomButton';
import { COLORS, FONTS, RADIUS, SHADOWS, SPACING } from '../constants/theme';

export const ProfileScreen = ({ navigation }) => {
  const { user, logout } = useAuth();
  const [notificationsEnabled, setNotificationsEnabled] = useState(true);
  const [offlineSyncEnabled, setOfflineSyncEnabled] = useState(true);

  const handleLogout = () => {
    Alert.alert(
      'Sign Out',
      'Are you sure you want to sign out of your account?',
      [
        { text: 'Cancel', style: 'cancel' },
        {
          text: 'Sign Out',
          style: 'destructive',
          onPress: () => logout(),
        },
      ]
    );
  };

  const handleSupportPress = () => {
    Alert.alert(
      'Support & Helpdesk',
      'For technical or site escalations, contact the NOC Operations Team at support@lynqops.com or dial +91 1800-LYNQ-OPS.',
      [{ text: 'OK' }]
    );
  };

  const handlePrivacyPress = () => {
    Alert.alert(
      'Privacy Policy',
      'LYNQ Field Operations complies with enterprise data governance standards. Location data is only recorded during site arrival (ADA) timestamps.',
      [{ text: 'Close' }]
    );
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
      <Header
        title="Engineer Profile"
        subtitle="Account & preferences"
        showBack={true}
        onBack={() => {
          if (navigation && navigation.canGoBack && navigation.canGoBack()) {
            navigation.goBack();
          } else if (navigation && navigation.navigate) {
            navigation.navigate('Main');
          }
        }}
      />

      <ScrollView contentContainerStyle={styles.scrollContent}>
        {/* Profile Identity Card (ShadCN style) */}
        <View style={[styles.card, SHADOWS.small, styles.profileCard]}>
          <View style={styles.avatarCircle}>
            <Text style={styles.avatarText}>{userInitials}</Text>
          </View>
          <Text style={styles.userName}>{userName}</Text>
          <Text style={styles.userEmail}>{user?.email || 'engineer@lynq.com'}</Text>

          <View style={styles.rolePill}>
            <Ionicons name="shield-checkmark" size={12} color={COLORS.textPrimary} />
            <Text style={styles.roleText}>{user?.role || 'Field Engineer'}</Text>
          </View>
        </View>

        {/* Organization Information */}
        <View style={[styles.card, SHADOWS.small]}>
          <Text style={styles.cardSectionTitle}>ORGANIZATION DETAILS</Text>

          <View style={styles.infoRow}>
            <Text style={styles.infoLabel}>Company Partner</Text>
            <Text style={styles.infoVal}>{user?.company || 'Contractor Partner'}</Text>
          </View>

          <View style={styles.infoRow}>
            <Text style={styles.infoLabel}>Role Category</Text>
            <Text style={styles.infoVal}>{user?.company_type || 'CONTRACTOR'}</Text>
          </View>

          <View style={styles.infoRow}>
            <Text style={styles.infoLabel}>Engineer User ID</Text>
            <Text style={styles.infoVal}>#{user?.id || '47886'}</Text>
          </View>
        </View>

        {/* App Preferences */}
        <View style={[styles.card, SHADOWS.small]}>
          <Text style={styles.cardSectionTitle}>APP PREFERENCES</Text>

          <View style={styles.switchRow}>
            <View style={{ flex: 1 }}>
              <Text style={styles.settingTitle}>Push Notifications</Text>
              <Text style={styles.settingDesc}>Alerts for newly assigned sites and ETAs</Text>
            </View>
            <Switch
              value={notificationsEnabled}
              onValueChange={setNotificationsEnabled}
              trackColor={{ false: '#e4e4e7', true: COLORS.primary }}
              thumbColor="#ffffff"
            />
          </View>

          <View style={[styles.switchRow, { borderBottomWidth: 0 }]}>
            <View style={{ flex: 1 }}>
              <Text style={styles.settingTitle}>Offline Draft Auto-Sync</Text>
              <Text style={styles.settingDesc}>Keep feasibility inspection drafts in local cache</Text>
            </View>
            <Switch
              value={offlineSyncEnabled}
              onValueChange={setOfflineSyncEnabled}
              trackColor={{ false: '#e4e4e7', true: COLORS.primary }}
              thumbColor="#ffffff"
            />
          </View>
        </View>

        {/* Support & Legal */}
        <View style={[styles.card, SHADOWS.small]}>
          <Text style={styles.cardSectionTitle}>SUPPORT & POLICIES</Text>

          <TouchableOpacity
            style={styles.menuRow}
            onPress={handleSupportPress}
            activeOpacity={0.7}
          >
            <View style={[styles.menuIconBox, { backgroundColor: COLORS.secondary }]}>
              <Ionicons name="headset-outline" size={16} color={COLORS.textPrimary} />
            </View>
            <Text style={styles.menuText}>Helpdesk & Technical Support</Text>
            <Ionicons name="chevron-forward" size={16} color={COLORS.mutedForeground} />
          </TouchableOpacity>

          <TouchableOpacity
            style={[styles.menuRow, { borderBottomWidth: 0 }]}
            onPress={handlePrivacyPress}
            activeOpacity={0.7}
          >
            <View style={[styles.menuIconBox, { backgroundColor: COLORS.secondary }]}>
              <Ionicons name="shield-outline" size={16} color={COLORS.textPrimary} />
            </View>
            <Text style={styles.menuText}>Privacy & Data Policy</Text>
            <Ionicons name="chevron-forward" size={16} color={COLORS.mutedForeground} />
          </TouchableOpacity>
        </View>

        {/* Build Information */}
        <View style={styles.versionBox}>
          <Text style={styles.versionText}>LYNQ Engineer v2.0</Text>
          <Text style={styles.versionSub}>Production Release • Enterprise Mobile</Text>
        </View>

        {/* Sign Out Button */}
        <CustomButton
          title="Sign Out"
          variant="destructive"
          icon="log-out-outline"
          onPress={handleLogout}
          style={styles.logoutBtn}
        />
      </ScrollView>
    </SafeAreaView>
  );
};

const styles = StyleSheet.create({
  safeArea: {
    flex: 1,
    backgroundColor: COLORS.background,
  },
  scrollContent: {
    padding: SPACING.md,
    paddingBottom: SPACING.xxl,
  },
  card: {
    backgroundColor: '#ffffff',
    borderRadius: RADIUS.lg,
    padding: SPACING.md,
    borderWidth: 1,
    borderColor: COLORS.border,
    marginBottom: SPACING.md,
  },
  profileCard: {
    alignItems: 'center',
    paddingVertical: SPACING.lg,
  },
  avatarCircle: {
    width: 64,
    height: 64,
    borderRadius: RADIUS.full,
    backgroundColor: COLORS.primary,
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: SPACING.sm,
  },
  avatarText: {
    color: '#ffffff',
    fontSize: 20,
    fontWeight: '800',
  },
  userName: {
    fontSize: FONTS.title,
    fontWeight: '800',
    color: COLORS.textPrimary,
    letterSpacing: -0.3,
  },
  userEmail: {
    fontSize: FONTS.small,
    color: COLORS.mutedForeground,
    marginTop: 2,
  },
  rolePill: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: COLORS.secondary,
    paddingHorizontal: 10,
    paddingVertical: 4,
    borderRadius: RADIUS.full,
    marginTop: SPACING.sm,
    gap: 4,
    borderWidth: 1,
    borderColor: COLORS.border,
  },
  roleText: {
    fontSize: 11,
    fontWeight: '600',
    color: COLORS.textPrimary,
  },
  cardSectionTitle: {
    fontSize: 10,
    fontWeight: '700',
    color: COLORS.mutedForeground,
    letterSpacing: 0.8,
    marginBottom: SPACING.sm,
  },
  infoRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    paddingVertical: 8,
    borderBottomWidth: 1,
    borderBottomColor: '#f4f4f5',
  },
  infoLabel: {
    fontSize: FONTS.body,
    color: COLORS.mutedForeground,
  },
  infoVal: {
    fontSize: FONTS.body,
    fontWeight: '600',
    color: COLORS.textPrimary,
  },
  switchRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingVertical: 10,
    borderBottomWidth: 1,
    borderBottomColor: '#f4f4f5',
  },
  settingTitle: {
    fontSize: FONTS.body,
    fontWeight: '600',
    color: COLORS.textPrimary,
  },
  settingDesc: {
    fontSize: 11,
    color: COLORS.mutedForeground,
    marginTop: 1,
  },
  menuRow: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingVertical: 8,
    borderBottomWidth: 1,
    borderBottomColor: '#f4f4f5',
    gap: 10,
  },
  menuIconBox: {
    width: 30,
    height: 30,
    borderRadius: RADIUS.sm,
    alignItems: 'center',
    justifyContent: 'center',
  },
  menuText: {
    fontSize: FONTS.body,
    fontWeight: '600',
    color: COLORS.textPrimary,
    flex: 1,
  },
  versionBox: {
    alignItems: 'center',
    marginVertical: SPACING.md,
  },
  versionText: {
    fontSize: 11,
    fontWeight: '600',
    color: COLORS.mutedForeground,
  },
  versionSub: {
    fontSize: 10,
    color: COLORS.textMuted,
    marginTop: 1,
  },
  logoutBtn: {
    marginTop: SPACING.xs,
  },
});
