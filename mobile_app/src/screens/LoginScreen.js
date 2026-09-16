import React, { useState } from 'react';
import {
  View,
  Text,
  TextInput,
  TouchableOpacity,
  StyleSheet,
  SafeAreaView,
  KeyboardAvoidingView,
  Platform,
  ScrollView,
  Alert,
  Modal,
  ActivityIndicator,
} from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { useAuth } from '../context/AuthContext';
import { CustomButton } from '../components/CustomButton';
import { DEFAULT_ENVIRONMENTS } from '../constants/config';
import { COLORS, FONTS, RADIUS, SHADOWS, SPACING } from '../constants/theme';

export const LoginScreen = () => {
  const { login, isLoading, error, serverUrl, updateServerUrl } = useAuth();
  const [username, setUsername] = useState('vikaspal@gmail.com');
  const [password, setPassword] = useState('rootroot');
  const [showPassword, setShowPassword] = useState(false);
  const [showServerModal, setShowServerModal] = useState(false);
  const [customUrlInput, setCustomUrlInput] = useState(serverUrl || '');

  const handleSaveServerUrl = async (urlToSave) => {
    if (!urlToSave.trim()) return;
    await updateServerUrl(urlToSave.trim());
    setShowServerModal(false);
    Alert.alert('Server Updated', `Connected server set to: ${urlToSave.trim()}`);
  };

  const handleLogin = async () => {
    if (!username.trim() || !password.trim()) {
      Alert.alert('Required', 'Please enter your username/email and password.');
      return;
    }
    const result = await login(username.trim(), password.trim());
    if (!result.success && result.error) {
      Alert.alert('Login Failed', result.error);
    }
  };

  const handleForgotPassword = () => {
    Alert.alert(
      'Forgot Password',
      'Please contact your company admin or contractor supervisor to reset your portal password.'
    );
  };

  return (
    <SafeAreaView style={styles.safeArea}>
      <KeyboardAvoidingView
        behavior={Platform.OS === 'ios' ? 'padding' : 'height'}
        style={styles.keyboardView}
      >
        <ScrollView
          contentContainerStyle={styles.scrollContent}
          keyboardShouldPersistTaps="handled"
        >
          {/* Header & Logo */}
          <View style={styles.brandHeader}>
            <View style={styles.logoBadge}>
              <Ionicons name="hardware-chip" size={32} color="#ffffff" />
            </View>
            <Text style={styles.brandTitle}>LYNQ</Text>
            <Text style={styles.brandSubtitle}>Engineer Operations Portal</Text>
          </View>

          {/* Form Card (ShadCN style) */}
          <View style={[styles.card, SHADOWS.medium]}>
            <Text style={styles.cardHeader}>Sign in to your account</Text>
            <Text style={styles.cardSub}>Enter your engineer credentials to access field operations</Text>

            {/* Loading / Connecting Banner */}
            {isLoading && (
              <View style={styles.loadingBanner}>
                <ActivityIndicator size="small" color={COLORS.primary} style={{ marginRight: 8 }} />
                <Text style={styles.loadingBannerText}>Authenticating with server...</Text>
              </View>
            )}

            {/* Error Banner */}
            {error ? (
              <View style={styles.errorBox}>
                <Ionicons name="alert-circle" size={16} color={COLORS.danger} />
                <Text style={styles.errorText}>{error}</Text>
              </View>
            ) : null}

            {/* Username / Email Input */}
            <View style={styles.inputGroup}>
              <Text style={styles.inputLabel}>Username or Email</Text>
              <View style={styles.inputContainer}>
                <Ionicons
                  name="mail-outline"
                  size={18}
                  color={COLORS.mutedForeground}
                  style={styles.inputIcon}
                />
                <TextInput
                  style={styles.textInput}
                  placeholder="name@example.com"
                  placeholderTextColor={COLORS.textMuted}
                  value={username}
                  onChangeText={setUsername}
                  autoCapitalize="none"
                  keyboardType="email-address"
                />
              </View>
            </View>

            {/* Password Input */}
            <View style={styles.inputGroup}>
              <View style={styles.passwordLabelRow}>
                <Text style={styles.inputLabel}>Password</Text>
                <TouchableOpacity onPress={handleForgotPassword}>
                  <Text style={styles.forgotText}>Forgot password?</Text>
                </TouchableOpacity>
              </View>
              <View style={styles.inputContainer}>
                <Ionicons
                  name="lock-closed-outline"
                  size={18}
                  color={COLORS.mutedForeground}
                  style={styles.inputIcon}
                />
                <TextInput
                  style={styles.textInput}
                  placeholder="••••••••"
                  placeholderTextColor={COLORS.textMuted}
                  value={password}
                  onChangeText={setPassword}
                  secureTextEntry={!showPassword}
                  autoCapitalize="none"
                />
                <TouchableOpacity
                  onPress={() => setShowPassword(!showPassword)}
                  style={styles.eyeBtn}
                >
                  <Ionicons
                    name={showPassword ? 'eye-off-outline' : 'eye-outline'}
                    size={18}
                    color={COLORS.mutedForeground}
                  />
                </TouchableOpacity>
              </View>
            </View>

            {/* Sign In Button */}
            <CustomButton
              title="Sign In with Email"
              onPress={handleLogin}
              loading={isLoading}
              icon="log-in-outline"
              size="lg"
              style={styles.loginBtn}
            />
          </View>

          {/* Server Config Button */}
          <TouchableOpacity
            onPress={() => {
              setCustomUrlInput(serverUrl);
              setShowServerModal(true);
            }}
            style={styles.serverSettingsBtn}
          >
            <Ionicons name="server-outline" size={13} color={COLORS.mutedForeground} />
            <Text style={styles.serverSettingsBtnText} numberOfLines={1}>
              Server: {serverUrl}
            </Text>
            <Ionicons name="chevron-forward" size={12} color={COLORS.mutedForeground} />
          </TouchableOpacity>

          {/* Footer Terms Notice */}
          <View style={styles.footerNote}>
            <Text style={styles.footerNoteText}>
              Authorized personnel only. All access and GPS actions are logged for audit compliance.
            </Text>
          </View>
        </ScrollView>
      </KeyboardAvoidingView>

      {/* Server URL Config Modal */}
      <Modal visible={showServerModal} transparent animationType="fade">
        <View style={styles.modalBackdrop}>
          <View style={styles.modalBox}>
            <View style={styles.modalHeader}>
              <Text style={styles.modalTitle}>Backend Server Settings</Text>
              <TouchableOpacity onPress={() => setShowServerModal(false)}>
                <Ionicons name="close" size={20} color={COLORS.textPrimary} />
              </TouchableOpacity>
            </View>

            <Text style={styles.modalSub}>Select or enter your server IP where Apache / XAMPP is running:</Text>

            {/* Quick Environments */}
            <View style={styles.envList}>
              {DEFAULT_ENVIRONMENTS.filter(env => env.url && !env.placeholder).map((env) => {
                const isSelected = serverUrl === env.url;
                return (
                  <TouchableOpacity
                    key={env.id}
                    style={[styles.envItem, isSelected && styles.envItemActive]}
                    onPress={() => handleSaveServerUrl(env.url)}
                  >
                    <View style={{ flex: 1 }}>
                      <Text style={[styles.envLabel, isSelected && styles.envLabelActive]}>{env.label}</Text>
                      <Text style={styles.envUrl}>{env.url}</Text>
                    </View>
                    {isSelected && <Ionicons name="checkmark-circle" size={18} color={COLORS.primary} />}
                  </TouchableOpacity>
                );
              })}
            </View>

            {/* Custom Input */}
            <View style={{ marginTop: 12 }}>
              <Text style={styles.inputLabel}>Custom URL</Text>
              <TextInput
                style={styles.textInputCustom}
                value={customUrlInput}
                onChangeText={setCustomUrlInput}
                placeholder="http://192.168.0.3/lynq"
                autoCapitalize="none"
              />
              <CustomButton
                title="Apply Custom Server URL"
                onPress={() => handleSaveServerUrl(customUrlInput)}
                size="md"
                style={{ marginTop: 8 }}
              />
            </View>
          </View>
        </View>
      </Modal>
    </SafeAreaView>
  );
};

const styles = StyleSheet.create({
  safeArea: {
    flex: 1,
    backgroundColor: COLORS.background,
  },
  keyboardView: {
    flex: 1,
  },
  scrollContent: {
    flexGrow: 1,
    justifyContent: 'center',
    padding: SPACING.lg,
  },
  brandHeader: {
    alignItems: 'center',
    marginBottom: SPACING.xl,
  },
  logoBadge: {
    width: 58,
    height: 58,
    borderRadius: RADIUS.lg,
    backgroundColor: COLORS.primary,
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: SPACING.sm,
  },
  brandTitle: {
    fontSize: FONTS.header,
    fontWeight: '900',
    color: COLORS.textPrimary,
    letterSpacing: 2,
  },
  brandSubtitle: {
    fontSize: FONTS.small,
    color: COLORS.mutedForeground,
    marginTop: 2,
    fontWeight: '500',
  },
  card: {
    backgroundColor: '#ffffff',
    borderRadius: RADIUS.xl,
    padding: SPACING.lg,
    borderWidth: 1,
    borderColor: COLORS.border,
  },
  cardHeader: {
    fontSize: FONTS.title,
    fontWeight: '800',
    color: COLORS.textPrimary,
    letterSpacing: -0.3,
  },
  cardSub: {
    fontSize: FONTS.small,
    color: COLORS.mutedForeground,
    marginBottom: SPACING.md,
    marginTop: 4,
    lineHeight: 18,
  },
  loadingBanner: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#eff6ff',
    borderWidth: 1,
    borderColor: '#bfdbfe',
    padding: SPACING.sm,
    borderRadius: RADIUS.md,
    marginBottom: SPACING.md,
  },
  loadingBannerText: {
    color: COLORS.primary,
    fontSize: FONTS.small,
    fontWeight: '600',
    flex: 1,
  },
  errorBox: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: COLORS.dangerLight,
    borderWidth: 1,
    borderColor: COLORS.dangerBorder,
    padding: SPACING.sm,
    borderRadius: RADIUS.md,
    marginBottom: SPACING.md,
    gap: 8,
  },
  errorText: {
    color: COLORS.dangerForeground,
    fontSize: FONTS.small,
    fontWeight: '600',
    flex: 1,
  },
  inputGroup: {
    marginBottom: SPACING.md,
  },
  passwordLabelRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 6,
  },
  inputLabel: {
    fontSize: FONTS.small,
    fontWeight: '600',
    color: COLORS.textPrimary,
    marginBottom: 6,
  },
  forgotText: {
    fontSize: 11,
    color: COLORS.mutedForeground,
    fontWeight: '500',
  },
  inputContainer: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#ffffff',
    borderWidth: 1,
    borderColor: COLORS.border,
    borderRadius: RADIUS.md,
    paddingHorizontal: SPACING.sm + 2,
  },
  inputIcon: {
    marginRight: 6,
  },
  textInput: {
    flex: 1,
    paddingVertical: 11,
    fontSize: FONTS.body,
    color: COLORS.textPrimary,
  },
  eyeBtn: {
    padding: 6,
  },
  loginBtn: {
    marginTop: SPACING.xs,
  },
  serverSettingsBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 6,
    marginTop: SPACING.md,
    paddingVertical: 8,
    paddingHorizontal: SPACING.sm,
    borderRadius: RADIUS.md,
    backgroundColor: '#f1f5f9',
    alignSelf: 'center',
    maxWidth: '90%',
  },
  serverSettingsBtnText: {
    fontSize: 11,
    color: COLORS.mutedForeground,
    fontWeight: '500',
  },
  modalBackdrop: {
    flex: 1,
    backgroundColor: 'rgba(0, 0, 0, 0.5)',
    justifyContent: 'center',
    alignItems: 'center',
    padding: SPACING.lg,
  },
  modalBox: {
    width: '100%',
    backgroundColor: '#ffffff',
    borderRadius: RADIUS.xl,
    padding: SPACING.lg,
    ...SHADOWS.large,
  },
  modalHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 6,
  },
  modalTitle: {
    fontSize: FONTS.title,
    fontWeight: '800',
    color: COLORS.textPrimary,
  },
  modalSub: {
    fontSize: FONTS.small,
    color: COLORS.mutedForeground,
    marginBottom: SPACING.md,
  },
  envList: {
    gap: 8,
  },
  envItem: {
    flexDirection: 'row',
    alignItems: 'center',
    padding: SPACING.md,
    borderRadius: RADIUS.md,
    borderWidth: 1,
    borderColor: COLORS.border,
    backgroundColor: '#f8fafc',
  },
  envItemActive: {
    borderColor: COLORS.primary,
    backgroundColor: '#eff6ff',
  },
  envLabel: {
    fontSize: FONTS.body,
    fontWeight: '700',
    color: COLORS.textPrimary,
  },
  envLabelActive: {
    color: COLORS.primary,
  },
  envUrl: {
    fontSize: 11,
    color: COLORS.mutedForeground,
    marginTop: 2,
  },
  textInputCustom: {
    backgroundColor: '#ffffff',
    borderWidth: 1,
    borderColor: COLORS.border,
    borderRadius: RADIUS.md,
    paddingHorizontal: SPACING.md,
    paddingVertical: 10,
    fontSize: FONTS.body,
    color: COLORS.textPrimary,
  },
});
