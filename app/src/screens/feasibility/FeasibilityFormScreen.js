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

export const FeasibilityFormScreen = ({ route, navigation }) => {
  const { siteId } = route.params;
  const [site, setSite] = useState(null);

  // Form State
  const [powerAvailable, setPowerAvailable] = useState('Yes');
  const [upsStatus, setUpsStatus] = useState('Working');
  const [cableLength, setCableLength] = useState('15');
  const [signalStrength, setSignalStrength] = useState('Good (4G)');
  const [remarks, setRemarks] = useState('');
  const [photos, setPhotos] = useState([]);
  const [isSubmitting, setIsSubmitting] = useState(false);

  useEffect(() => {
    loadSite();
  }, [siteId]);

  const loadSite = async () => {
    const data = await api.getSiteDetails(siteId);
    setSite(data);
  };

  const handlePickImage = async () => {
    try {
      const { status } = await ImagePicker.requestMediaLibraryPermissionsAsync();
      if (status !== 'granted') {
        Alert.alert('Permission Denied', 'Camera roll permissions are required to upload site survey photos.');
        return;
      }

      const result = await ImagePicker.launchImageLibraryAsync({
        mediaTypes: ImagePicker.MediaTypeOptions.Images,
        allowsEditing: true,
        quality: 0.8,
      });

      if (!result.canceled && result.assets && result.assets.length > 0) {
        setPhotos([...photos, result.assets[0].uri]);
      }
    } catch (e) {
      console.warn('Image picker error', e);
    }
  };

  const handleTakePhoto = async () => {
    try {
      const { status } = await ImagePicker.requestCameraPermissionsAsync();
      if (status !== 'granted') {
        Alert.alert('Permission Denied', 'Camera permission is required to snap photos.');
        return;
      }

      const result = await ImagePicker.launchCameraAsync({
        allowsEditing: true,
        quality: 0.8,
      });

      if (!result.canceled && result.assets && result.assets.length > 0) {
        setPhotos([...photos, result.assets[0].uri]);
      }
    } catch (e) {
      console.warn('Camera error', e);
    }
  };

  const removePhoto = (idx) => {
    const updated = [...photos];
    updated.splice(idx, 1);
    setPhotos(updated);
  };

  const handleSubmit = async () => {
    setIsSubmitting(true);
    const payload = {
      site_id: siteId,
      power_available: powerAvailable,
      ups_status: upsStatus,
      cable_length: cableLength,
      signal_strength: signalStrength,
      remarks: remarks,
      photos: photos,
    };

    const res = await api.submitFeasibilityCheck(payload);
    setIsSubmitting(false);

    if (res.success) {
      Alert.alert('Success', 'Feasibility check submitted successfully for ADV review!', [
        {
          text: 'OK',
          onPress: () => navigation.goBack(),
        },
      ]);
    } else {
      Alert.alert('Error', res.message || 'Failed to submit feasibility check');
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
        <Text style={styles.topBarTitle}>Feasibility Survey</Text>
        <View style={{ width: 24 }} />
      </View>

      <ScrollView contentContainerStyle={styles.scrollContent}>
        {/* Site Header Banner */}
        <View style={styles.siteHeaderBox}>
          <Text style={styles.siteName}>{site.site_name}</Text>
          <Text style={styles.bankName}>{site.bank_name}</Text>
          <Text style={styles.locationText}>{site.city}, {site.state}</Text>
        </View>

        {/* 1. Power & Infrastructure */}
        <View style={styles.card}>
          <Text style={styles.cardTitle}>Power & Infrastructure</Text>

          {/* Power Availability */}
          <Text style={styles.label}>230V AC Power Available?</Text>
          <View style={styles.toggleRow}>
            {['Yes', 'No', 'Partial'].map(opt => (
              <TouchableOpacity
                key={opt}
                style={[styles.toggleBtn, powerAvailable === opt && styles.toggleBtnActive]}
                onPress={() => setPowerAvailable(opt)}
              >
                <Text style={[styles.toggleText, powerAvailable === opt && styles.toggleTextActive]}>{opt}</Text>
              </TouchableOpacity>
            ))}
          </View>

          {/* UPS Status */}
          <Text style={styles.label}>UPS / Battery Backup Status</Text>
          <View style={styles.toggleRow}>
            {['Working', 'Faulty', 'No UPS'].map(opt => (
              <TouchableOpacity
                key={opt}
                style={[styles.toggleBtn, upsStatus === opt && styles.toggleBtnActive]}
                onPress={() => setUpsStatus(opt)}
              >
                <Text style={[styles.toggleText, upsStatus === opt && styles.toggleTextActive]}>{opt}</Text>
              </TouchableOpacity>
            ))}
          </View>

          {/* Cable Length */}
          <Text style={styles.label}>Estimated CAT6 Cable Needed (Meters)</Text>
          <TextInput
            style={styles.textInput}
            value={cableLength}
            onChangeText={setCableLength}
            keyboardType="numeric"
            placeholder="e.g. 15"
          />
        </View>

        {/* 2. Network & Signal Strength */}
        <View style={styles.card}>
          <Text style={styles.cardTitle}>Network & Signal Check</Text>
          <Text style={styles.label}>Mobile / 4G Signal Quality at Router Point</Text>
          <View style={styles.toggleRow}>
            {['Good (4G)', 'Moderate', 'Weak (Antenna Req)'].map(opt => (
              <TouchableOpacity
                key={opt}
                style={[styles.toggleBtn, signalStrength === opt && styles.toggleBtnActive]}
                onPress={() => setSignalStrength(opt)}
              >
                <Text style={[styles.toggleText, signalStrength === opt && styles.toggleTextActive]}>{opt}</Text>
              </TouchableOpacity>
            ))}
          </View>
        </View>

        {/* 3. Site Photo Proofs */}
        <View style={styles.card}>
          <Text style={styles.cardTitle}>Site Survey Photos ({photos.length})</Text>
          <Text style={styles.sublabel}>Upload photos of ATM back, power point, and antenna placement.</Text>

          <View style={styles.photoActionsRow}>
            <TouchableOpacity style={styles.photoBtn} onPress={handleTakePhoto}>
              <Ionicons name="camera" size={16} color={colors.primary} style={{ marginRight: 6 }} />
              <Text style={styles.photoBtnText}>Take Photo</Text>
            </TouchableOpacity>

            <TouchableOpacity style={styles.photoBtn} onPress={handlePickImage}>
              <Ionicons name="images-outline" size={16} color={colors.primary} style={{ marginRight: 6 }} />
              <Text style={styles.photoBtnText}>Choose from Gallery</Text>
            </TouchableOpacity>
          </View>

          {/* Photo Grid */}
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

        {/* 4. Engineer Remarks */}
        <View style={styles.card}>
          <Text style={styles.cardTitle}>Engineer Remarks</Text>
          <TextInput
            style={[styles.textInput, { height: 80, textAlignVertical: 'top' }]}
            value={remarks}
            onChangeText={setRemarks}
            placeholder="Add any specific observations or installation notes..."
            multiline
          />
        </View>

        {/* Submit Button */}
        <TouchableOpacity
          style={[styles.submitBtn, isSubmitting && styles.submitBtnDisabled]}
          onPress={handleSubmit}
          disabled={isSubmitting}
        >
          {isSubmitting ? (
            <ActivityIndicator color={colors.white} />
          ) : (
            <View style={{ flexDirection: 'row', alignItems: 'center' }}>
              <Ionicons name="checkmark-circle-outline" size={18} color={colors.white} style={{ marginRight: 6 }} />
              <Text style={styles.submitBtnText}>Submit Feasibility Check</Text>
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
  label: {
    fontSize: 12,
    fontWeight: '600',
    color: colors.textSecondary,
    marginTop: 8,
    marginBottom: 6,
  },
  sublabel: {
    fontSize: 11,
    color: colors.textMuted,
    marginBottom: 10,
  },
  toggleRow: {
    flexDirection: 'row',
    gap: 8,
    marginBottom: 6,
  },
  toggleBtn: {
    flex: 1,
    paddingVertical: 8,
    borderRadius: 10,
    backgroundColor: colors.background,
    alignItems: 'center',
    borderWidth: 1,
    borderColor: colors.border,
  },
  toggleBtnActive: {
    backgroundColor: colors.primary,
    borderColor: colors.primary,
  },
  toggleText: {
    fontSize: 11,
    fontWeight: '700',
    color: colors.textSecondary,
  },
  toggleTextActive: {
    color: colors.white,
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
    backgroundColor: colors.primary + '10',
    borderWidth: 1,
    borderColor: colors.primary + '30',
  },
  photoBtnText: {
    fontSize: 11,
    fontWeight: '700',
    color: colors.primary,
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
    backgroundColor: colors.primary,
    borderRadius: 14,
    paddingVertical: 14,
    alignItems: 'center',
    justifyContent: 'center',
    marginTop: 10,
    shadowColor: colors.primary,
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
