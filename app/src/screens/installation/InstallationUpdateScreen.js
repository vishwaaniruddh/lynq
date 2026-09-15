import React, { useState, useEffect } from 'react';
import {
  View,
  Text,
  StyleSheet,
  ScrollView,
  TextInput,
  TouchableOpacity,
  SafeAreaView,
  Image,
  Alert,
  ActivityIndicator,
} from 'react-native';
import * as ImagePicker from 'expo-image-picker';
import { Ionicons } from '@expo/vector-icons';
import { colors } from '../../theme/colors';
import { api } from '../../api/client';

const MILESTONES = [
  { key: 'equipment_mounted', label: '1. Equipment Mounted' },
  { key: 'cable_laid', label: '2. CAT6 / Power Cable Laid' },
  { key: 'router_configured', label: '3. Router Serial & IP Setup' },
  { key: 'ping_tested', label: '4. Network Ping Tested' },
  { key: 'completed', label: '5. Installation Completed' },
];

export const InstallationUpdateScreen = ({ route, navigation }) => {
  const { siteId } = route.params;
  const [site, setSite] = useState(null);

  // Form State
  const [milestone, setMilestone] = useState('router_configured');
  const [routerSerial, setRouterSerial] = useState('');
  const [routerIp, setRouterIp] = useState('');
  const [siteIp, setSiteIp] = useState('');
  const [notes, setNotes] = useState('');
  const [photos, setPhotos] = useState([]);
  const [isSubmitting, setIsSubmitting] = useState(false);

  useEffect(() => {
    loadSite();
  }, [siteId]);

  const loadSite = async () => {
    const data = await api.getSiteDetails(siteId);
    setSite(data);
    if (data) {
      setRouterSerial(data.router_serial_number || '');
      setRouterIp(data.router_ip || '');
      setSiteIp(data.site_ip || '');
    }
  };

  const handlePickImage = async () => {
    const result = await ImagePicker.launchImageLibraryAsync({
      mediaTypes: ImagePicker.MediaTypeOptions.Images,
      allowsEditing: true,
      quality: 0.8,
    });
    if (!result.canceled && result.assets && result.assets.length > 0) {
      setPhotos([...photos, result.assets[0].uri]);
    }
  };

  const handleTakePhoto = async () => {
    const { status } = await ImagePicker.requestCameraPermissionsAsync();
    if (status === 'granted') {
      const result = await ImagePicker.launchCameraAsync({
        allowsEditing: true,
        quality: 0.8,
      });
      if (!result.canceled && result.assets && result.assets.length > 0) {
        setPhotos([...photos, result.assets[0].uri]);
      }
    }
  };

  const removePhoto = (idx) => {
    const updated = [...photos];
    updated.splice(idx, 1);
    setPhotos(updated);
  };

  const handleSave = async () => {
    setIsSubmitting(true);
    const payload = {
      site_id: siteId,
      milestone: milestone,
      status: milestone === 'completed' ? 'completed' : 'in_progress',
      router_serial_number: routerSerial,
      router_ip: routerIp,
      site_ip: siteIp,
      notes: notes,
      photos: photos,
    };

    const res = await api.updateInstallationStatus(payload);
    setIsSubmitting(false);

    if (res.success) {
      Alert.alert('Success', 'Installation progress and milestone saved successfully!', [
        {
          text: 'OK',
          onPress: () => navigation.goBack(),
        },
      ]);
    } else {
      Alert.alert('Error', res.message || 'Failed to update installation');
    }
  };

  if (!site) return null;

  return (
    <SafeAreaView style={styles.container}>
      {/* Top Bar */}
      <View style={styles.topBar}>
        <TouchableOpacity style={styles.backBtn} onPress={() => navigation.goBack()}>
          <Ionicons name="arrow-back" size={20} color={colors.textPrimary} />
        </TouchableOpacity>
        <Text style={styles.topBarTitle}>Update Installation</Text>
        <View style={{ width: 24 }} />
      </View>

      <ScrollView contentContainerStyle={styles.scrollContent}>
        {/* Site Header Box */}
        <View style={styles.siteHeaderBox}>
          <Text style={styles.siteName}>{site.site_name}</Text>
          <Text style={styles.bankName}>{site.bank_name}</Text>
          <Text style={styles.locationText}>{site.city}, {site.state}</Text>
        </View>

        {/* 1. Milestone Selection */}
        <View style={styles.card}>
          <Text style={styles.cardTitle}>Current Milestone Status</Text>
          {MILESTONES.map(m => {
            const isSelected = milestone === m.key;
            return (
              <TouchableOpacity
                key={m.key}
                style={[styles.milestoneRow, isSelected && styles.milestoneRowSelected]}
                onPress={() => setMilestone(m.key)}
              >
                <Ionicons
                  name={isSelected ? 'radio-button-on' : 'radio-button-off'}
                  size={18}
                  color={isSelected ? colors.purple : colors.textMuted}
                  style={{ marginRight: 10 }}
                />
                <Text style={[styles.milestoneText, isSelected && styles.milestoneTextSelected]}>
                  {m.label}
                </Text>
              </TouchableOpacity>
            );
          })}
        </View>

        {/* 2. Network & Router Configuration */}
        <View style={styles.card}>
          <Text style={styles.cardTitle}>Router & IP Verification</Text>

          <Text style={styles.label}>Router Serial Number</Text>
          <TextInput
            style={styles.textInput}
            value={routerSerial}
            onChangeText={setRouterSerial}
            placeholder="e.g. RT-992140"
          />

          <Text style={styles.label}>Router Management IP</Text>
          <TextInput
            style={styles.textInput}
            value={routerIp}
            onChangeText={setRouterIp}
            placeholder="e.g. 10.240.12.1"
          />

          <Text style={styles.label}>ATM / Site IP</Text>
          <TextInput
            style={styles.textInput}
            value={siteIp}
            onChangeText={setSiteIp}
            placeholder="e.g. 172.16.4.10"
          />
        </View>

        {/* 3. Photo Proofs */}
        <View style={styles.card}>
          <Text style={styles.cardTitle}>Installation Photos ({photos.length})</Text>
          <Text style={styles.sublabel}>Capture photos of router cabling, LEDs, and rack mounting.</Text>

          <View style={styles.photoActionsRow}>
            <TouchableOpacity style={styles.photoBtn} onPress={handleTakePhoto}>
              <Ionicons name="camera" size={16} color={colors.purple} style={{ marginRight: 6 }} />
              <Text style={styles.photoBtnText}>Take Photo</Text>
            </TouchableOpacity>

            <TouchableOpacity style={styles.photoBtn} onPress={handlePickImage}>
              <Ionicons name="images-outline" size={16} color={colors.purple} style={{ marginRight: 6 }} />
              <Text style={styles.photoBtnText}>Gallery</Text>
            </TouchableOpacity>
          </View>

          <View style={styles.photoGrid}>
            {photos.map((uri, idx) => (
              <View key={idx} style={styles.photoItem}>
                <Image source={{ uri }} style={styles.photoThumb} />
                <TouchableOpacity style={styles.removePhotoBtn} onPress={() => removePhoto(idx)}>
                  <Ionicons name="close-circle" size={18} color={colors.error} />
                </TouchableOpacity>
              </View>
            ))}
          </View>
        </View>

        {/* 4. Notes */}
        <View style={styles.card}>
          <Text style={styles.cardTitle}>Engineer Field Notes</Text>
          <TextInput
            style={[styles.textInput, { height: 75, textAlignVertical: 'top' }]}
            value={notes}
            onChangeText={setNotes}
            placeholder="Notes regarding installation and testing..."
            multiline
          />
        </View>

        {/* Save Button */}
        <TouchableOpacity
          style={[styles.submitBtn, isSubmitting && styles.submitBtnDisabled]}
          onPress={handleSave}
          disabled={isSubmitting}
        >
          {isSubmitting ? (
            <ActivityIndicator color={colors.white} />
          ) : (
            <View style={{ flexDirection: 'row', alignItems: 'center' }}>
              <Ionicons name="save-outline" size={18} color={colors.white} style={{ marginRight: 6 }} />
              <Text style={styles.submitBtnText}>Save Milestone & Status</Text>
            </View>
          )}
        </TouchableOpacity>
      </ScrollView>
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
  scrollContent: {
    padding: 16,
    paddingBottom: 40,
  },
  siteHeaderBox: {
    backgroundColor: colors.card,
    borderRadius: 16,
    padding: 16,
    marginBottom: 14,
    borderWidth: 1,
    borderColor: colors.borderLight,
  },
  siteName: {
    fontSize: 16,
    fontWeight: '800',
    color: colors.textPrimary,
  },
  bankName: {
    fontSize: 12,
    color: colors.textSecondary,
    marginTop: 2,
    fontWeight: '600',
  },
  locationText: {
    fontSize: 11,
    color: colors.textMuted,
    marginTop: 2,
  },
  card: {
    backgroundColor: colors.card,
    borderRadius: 16,
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
  milestoneRow: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingVertical: 10,
    paddingHorizontal: 12,
    borderRadius: 12,
    backgroundColor: colors.background,
    marginVertical: 4,
    borderWidth: 1,
    borderColor: colors.border,
  },
  milestoneRowSelected: {
    backgroundColor: colors.purpleBg,
    borderColor: colors.purple,
  },
  milestoneText: {
    fontSize: 12,
    fontWeight: '600',
    color: colors.textSecondary,
  },
  milestoneTextSelected: {
    color: colors.purple,
    fontWeight: '800',
  },
  label: {
    fontSize: 11,
    fontWeight: '700',
    color: colors.textSecondary,
    marginTop: 8,
    marginBottom: 4,
  },
  sublabel: {
    fontSize: 11,
    color: colors.textMuted,
    marginBottom: 10,
  },
  textInput: {
    borderWidth: 1,
    borderColor: colors.border,
    borderRadius: 10,
    paddingHorizontal: 12,
    paddingVertical: 8,
    fontSize: 13,
    backgroundColor: colors.background,
    color: colors.textPrimary,
  },
  photoActionsRow: {
    flexDirection: 'row',
    gap: 10,
    marginBottom: 12,
  },
  photoBtn: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    paddingVertical: 10,
    borderRadius: 10,
    backgroundColor: colors.purpleBg,
    borderWidth: 1,
    borderColor: colors.purple + '40',
  },
  photoBtnText: {
    fontSize: 11,
    fontWeight: '700',
    color: colors.purple,
  },
  photoGrid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 10,
  },
  photoItem: {
    position: 'relative',
  },
  photoThumb: {
    width: 72,
    height: 72,
    borderRadius: 10,
  },
  removePhotoBtn: {
    position: 'absolute',
    top: -6,
    right: -6,
    backgroundColor: colors.white,
    borderRadius: 10,
  },
  submitBtn: {
    backgroundColor: colors.purple,
    borderRadius: 14,
    paddingVertical: 14,
    alignItems: 'center',
    justifyContent: 'center',
    marginTop: 10,
    shadowColor: colors.purple,
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.25,
    shadowRadius: 8,
    elevation: 4,
  },
  submitBtnDisabled: {
    opacity: 0.6,
  },
  submitBtnText: {
    color: colors.white,
    fontSize: 14,
    fontWeight: '800',
  },
});
