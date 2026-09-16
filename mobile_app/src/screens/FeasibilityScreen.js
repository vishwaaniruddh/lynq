import React, { useState, useEffect, useRef, useMemo } from 'react';
import {
  View,
  Text,
  TextInput,
  StyleSheet,
  SafeAreaView,
  ScrollView,
  TouchableOpacity,
  Image,
  Alert,
  ActivityIndicator,
  Modal,
  Platform,
} from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import * as ImagePicker from 'expo-image-picker';
import * as FileSystem from 'expo-file-system/legacy';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { engineerApi } from '../api/engineerApi';
import { getApiBaseUrl } from '../api/client';
import { Header } from '../components/Header';
import { CustomButton } from '../components/CustomButton';
import { ZoomableImageModal } from '../components/ZoomableImageModal';
import { COLORS, FONTS, RADIUS, SPACING, SHADOWS } from '../constants/theme';
import { STORAGE_KEYS } from '../constants/config';

// Default Fallback Steps if offline or no custom form schema
const DEFAULT_FEASIBILITY_STEPS = [
  { id: 1, title: 'ATM Info', shortLabel: 'ATM', icon: 'card-outline', slug: 'atm_information' },
  { id: 2, title: 'Network Info', shortLabel: 'Network', icon: 'wifi-outline', slug: 'network_information' },
  { id: 3, title: 'Power & UPS', shortLabel: 'Power', icon: 'flash-outline', slug: 'power_infrastructure' },
  { id: 4, title: 'Electrical', shortLabel: 'Electrical', icon: 'git-network-outline', slug: 'electrical_measurements' },
  { id: 5, title: 'Site Access & Env', shortLabel: 'Access', icon: 'key-outline', slug: 'site_access' },
  { id: 6, title: 'Review & Submit', shortLabel: 'Review', icon: 'checkmark-done-circle-outline', slug: 'remarks' },
];

const SECTION_STYLE_MAP = {
  atm_information: { icon: 'card-outline', shortLabel: 'ATM', color: '#eab308' },
  network_information: { icon: 'wifi-outline', shortLabel: 'Network', color: '#10b981' },
  power_infrastructure: { icon: 'flash-outline', shortLabel: 'Power', color: '#f97316' },
  power_ups_infrastructure: { icon: 'flash-outline', shortLabel: 'Power', color: '#f97316' },
  electrical_measurements: { icon: 'git-network-outline', shortLabel: 'Electrical', color: '#3b82f6' },
  site_access: { icon: 'key-outline', shortLabel: 'Access', color: '#8b5cf6' },
  environmental_factors: { icon: 'leaf-outline', shortLabel: 'Env', color: '#14b8a6' },
  remarks_final_assessment: { icon: 'chatbubble-ellipses-outline', shortLabel: 'Remarks', color: '#64748b' },
  remarks: { icon: 'chatbubble-ellipses-outline', shortLabel: 'Remarks', color: '#64748b' },
};

function normalizeSlug(str) {
  if (!str) return 'general';
  return str.toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '');
}

export const FeasibilityScreen = ({ route, navigation }) => {
  const { siteId, site, assignmentId: paramAssignmentId, viewOnly } = route.params || {};
  const assignmentId = paramAssignmentId || site?.assignment_id || site?.id || siteId;

  const [currentStep, setCurrentStep] = useState(1);
  const [isReportMode, setIsReportMode] = useState(Boolean(viewOnly));
  const [feasibilityStatus, setFeasibilityStatus] = useState(site?.feasibility_status || '');
  const [serverSiteInfo, setServerSiteInfo] = useState(null);
  const [customForm, setCustomForm] = useState(null);
  const [isLoading, setIsLoading] = useState(true);
  const [isSavingDraft, setIsSavingDraft] = useState(false);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [lastSavedTime, setLastSavedTime] = useState(null);
  const [selectedPhotoPreview, setSelectedPhotoPreview] = useState(null);
  const [rejectionInfo, setRejectionInfo] = useState(null);

  const scrollViewRef = useRef(null);

  // Helper to resolve relative server image paths to full URLs
  const resolveImageUrl = (uri) => {
    if (!uri) return null;
    if (
      uri.startsWith('http://') ||
      uri.startsWith('https://') ||
      uri.startsWith('file://') ||
      uri.startsWith('content://') ||
      uri.startsWith('data:')
    ) {
      return uri;
    }
    const base = getApiBaseUrl().replace(/\/api\/?.*$/, '').replace(/\/+$/, '');
    const cleanPath = uri.startsWith('/') ? uri : `/${uri}`;
    return `${base}${cleanPath}`;
  };

  // Form State
  const [form, setForm] = useState({
    assignment_id: assignmentId,
    // Step 1: ATM Info
    no_of_atm: '1',
    atm_id_1: site?.atm_id || '',
    atm_1_status: 'working',
    atm_id_2: '',
    atm_2_status: '',
    atm_id_3: '',
    atm_3_status: '',

    // Step 2: Network
    operator: 'Airtel',
    signal_status: 'good',
    operator_2: '',
    signal_status_2: '',
    backroom_network_remark: '',
    backroom_network_snap: null,

    // Step 3: Power
    ups_available: 'yes',
    no_of_ups: '1',
    ups_battery_backup: '30min_to_1hr',
    ups_working_1: 'yes',
    ups_working_2: 'yes',
    ups_working_3: 'yes',
    power_socket_availability: 'available',
    power_socket_availability_ups: 'available',
    ups_available_snap: null,

    // Step 4: Electrical Measurements
    earthing: 'yes',
    earthing_voltage: '',
    power_fluctuation_en: '',
    power_fluctuation_pe: '',
    power_fluctuation_pn: '',
    frequent_power_cut: 'no',
    frequent_power_cut_from: '',
    frequent_power_cut_to: '',
    frequent_power_cut_remark: '',
    earthing_snap: null,

    // Step 5: Access & Environment
    em_lock_available: 'no',
    em_lock_password: '',
    password_received: 'no',
    backroom_key_name: '',
    backroom_key_number: '',
    backroom_key_status: 'available',
    router_antenna_position: '',
    router_position: '',
    antenna_routing_detail: '',
    nearest_shop_name: '',
    nearest_shop_number: '',
    nearest_shop_distance: '',
    backroom_disturbing_material: 'no',
    backroom_disturbing_material_remark: '',
    router_antenna_snap: null,
    antenna_routing_snap: null,

    // Step 6: Remarks
    remarks: '',
    remarks_snap: null,
  });

  // Load Draft & Existing Data
  useEffect(() => {
    loadFeasibilityData();
  }, [assignmentId]);

  const loadFeasibilityData = async () => {
    try {
      setIsLoading(true);
      const draftKey = `${STORAGE_KEYS.FEASIBILITY_DRAFT}${assignmentId}`;
      const storedDraft = await AsyncStorage.getItem(draftKey);

      if (storedDraft) {
        try {
          const parsed = JSON.parse(storedDraft);

          // Validate that persisted image files still exist on disk
          const validatedDraft = { ...parsed };
          const imageKeys = Object.keys(validatedDraft).filter(
            (k) => k.endsWith('_snap') || validatedDraft[k]?.toString().includes('feasibility_photos/')
          );
          for (const key of imageKeys) {
            const uri = validatedDraft[key];
            if (uri && typeof uri === 'string' && uri.startsWith('file://')) {
              try {
                const info = await FileSystem.getInfoAsync(uri);
                if (!info.exists) {
                  console.log(`[Feasibility] Draft image missing from disk: ${key}`);
                  validatedDraft[key] = null;
                }
              } catch (e) {
                validatedDraft[key] = null;
              }
            }
          }

          setForm((prev) => ({ ...prev, ...validatedDraft }));
          setLastSavedTime('Restored from draft');
        } catch (e) {
          console.log('Error parsing draft:', e);
        }
      }

      // Fetch from API
      if (assignmentId) {
        const res = await engineerApi.getFeasibility(assignmentId);
        if (res && res.success && res.data) {
          const fetchedStatus = res.data.feasibility_status || '';
          setFeasibilityStatus(fetchedStatus);
          
          if (res.data.site_info) {
            setServerSiteInfo(res.data.site_info);
          }

          if (res.data.custom_form) {
            setCustomForm(res.data.custom_form);
          }

          if (res.data.rejection_info && res.data.rejection_info.is_rejected) {
            setRejectionInfo(res.data.rejection_info);
            setIsReportMode(false);
          } else {
            setRejectionInfo(null);
            if (
              fetchedStatus === 'pending_contractor_review' ||
              fetchedStatus === 'contractor_approved' ||
              fetchedStatus === 'feasibility_completed' ||
              fetchedStatus === 'adv_approved'
            ) {
              setIsReportMode(true);
            }
          }

          if (res.data.feasibility) {
            const sf = res.data.feasibility;
            setForm((prev) => ({
              ...prev,
              ...sf,
              no_of_atm: sf.no_of_atm != null ? String(sf.no_of_atm) : prev.no_of_atm,
              no_of_ups: sf.no_of_ups != null ? String(sf.no_of_ups) : prev.no_of_ups,
              earthing_voltage: sf.earthing_voltage != null ? String(sf.earthing_voltage) : prev.earthing_voltage,
              power_fluctuation_en: sf.power_fluctuation_en != null ? String(sf.power_fluctuation_en) : prev.power_fluctuation_en,
              power_fluctuation_pe: sf.power_fluctuation_pe != null ? String(sf.power_fluctuation_pe) : prev.power_fluctuation_pe,
              power_fluctuation_pn: sf.power_fluctuation_pn != null ? String(sf.power_fluctuation_pn) : prev.power_fluctuation_pn,
              nearest_shop_distance: sf.nearest_shop_distance != null ? String(sf.nearest_shop_distance) : prev.nearest_shop_distance,
            }));
          }

          if (res.data.site_info && res.data.site_info.site_name) {
            setForm((prev) => ({
              ...prev,
              atm_id_1: prev.atm_id_1 || res.data.site_info.site_name,
            }));
          }
        }
      }
    } catch (e) {
      console.log('Error loading feasibility data:', e);
    } finally {
      setIsLoading(false);
    }
  };

  // Build Dynamic Steps based on Custom Form Schema
  const steps = useMemo(() => {
    if (customForm && Array.isArray(customForm.fields) && customForm.fields.length > 0) {
      const grouped = {};
      customForm.fields.forEach((field) => {
        const secTitle = field.section_title || 'General Information';
        if (!grouped[secTitle]) {
          grouped[secTitle] = [];
        }
        grouped[secTitle].push(field);
      });

      return Object.keys(grouped).map((title, idx) => {
        const slug = normalizeSlug(title);
        const styleInfo = SECTION_STYLE_MAP[slug] || {
          icon: 'layers-outline',
          shortLabel: title.split(' ')[0] || `S${idx + 1}`,
          color: COLORS.primary,
        };

        return {
          id: idx + 1,
          title: title,
          shortLabel: styleInfo.shortLabel || title.split(' ')[0],
          icon: styleInfo.icon || 'layers-outline',
          slug: slug,
          fields: grouped[title],
        };
      });
    }

    return DEFAULT_FEASIBILITY_STEPS;
  }, [customForm]);

  const updateFormField = (field, value) => {
    setForm((prev) => ({ ...prev, [field]: value }));
  };

  // Image Picker with 80% Compression — copies to persistent storage
  const handlePickOrCapture = async (category, source = 'camera') => {
    try {
      const pickerOptions = {
        mediaTypes: ['images'],
        allowsEditing: false,
        quality: 0.8,
      };

      let result;
      if (source === 'camera') {
        const perm = await ImagePicker.requestCameraPermissionsAsync();
        if (!perm.granted) {
          Alert.alert('Permission Denied', 'Camera permission is required to capture photos.');
          return;
        }
        result = await ImagePicker.launchCameraAsync(pickerOptions);
      } else {
        const perm = await ImagePicker.requestMediaLibraryPermissionsAsync();
        if (!perm.granted) {
          Alert.alert('Permission Denied', 'Photo library permission is required.');
          return;
        }
        result = await ImagePicker.launchImageLibraryAsync(pickerOptions);
      }

      if (!result.canceled && result.assets && result.assets[0]) {
        const asset = result.assets[0];
        const tempUri = asset.uri;

        // Copy to persistent directory so it survives app restarts
        try {
          const persistDir = `${FileSystem.documentDirectory}feasibility_photos/`;
          const dirInfo = await FileSystem.getInfoAsync(persistDir);
          if (!dirInfo.exists) {
            await FileSystem.makeDirectoryAsync(persistDir, { intermediates: true });
          }

          const ext = tempUri.split('.').pop()?.split('?')[0] || 'jpg';
          const persistFilename = `${assignmentId}_${category}_${Date.now()}.${ext}`;
          const persistUri = `${persistDir}${persistFilename}`;

          await FileSystem.copyAsync({ from: tempUri, to: persistUri });
          console.log(`[Feasibility] Photo saved to: ${persistUri}`);
          updateFormField(category, persistUri);
        } catch (copyErr) {
          console.log('[Feasibility] Failed to persist image, using temp URI:', copyErr);
          // Fallback to temp URI if copy fails
          updateFormField(category, tempUri);
        }
      }
    } catch (e) {
      Alert.alert('Error', 'Failed to pick or capture image.');
      console.log('[Feasibility] Image pick error:', e);
    }
  };

  const handleRemovePhoto = async (category) => {
    const existingUri = form[category];
    // Delete the persisted file if it's a local file
    if (existingUri && existingUri.startsWith(FileSystem.documentDirectory)) {
      try {
        await FileSystem.deleteAsync(existingUri, { idempotent: true });
      } catch (e) {
        // Ignore delete errors
      }
    }
    updateFormField(category, null);
  };

  // Save Local Draft
  const saveDraft = async (showNotification = true) => {
    try {
      setIsSavingDraft(true);
      const draftKey = `${STORAGE_KEYS.FEASIBILITY_DRAFT}${assignmentId}`;
      await AsyncStorage.setItem(draftKey, JSON.stringify(form));

      const now = new Date();
      const timeStr = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
      setLastSavedTime(`Saved draft at ${timeStr}`);

      if (showNotification) {
        Alert.alert('Draft Saved', `Feasibility form saved locally at ${timeStr}. You can resume anytime.`);
      }
    } catch (e) {
      if (showNotification) {
        Alert.alert('Error', 'Failed to save draft locally.');
      }
    } finally {
      setIsSavingDraft(false);
    }
  };

  // Dynamic Validation per step
  const validateStep = (stepNumber) => {
    const activeStepObj = steps[stepNumber - 1];
    if (!activeStepObj) return true;

    // If step has dynamic fields
    if (activeStepObj.fields && Array.isArray(activeStepObj.fields)) {
      for (const f of activeStepObj.fields) {
        // Skip hidden ATM subfields
        if (f.field_key === 'atm_id_2' && parseInt(form.no_of_atm || '0', 10) < 2) continue;
        if (f.field_key === 'atm_id_3' && parseInt(form.no_of_atm || '0', 10) < 3) continue;
        if (f.field_key === 'atm_2_status' && parseInt(form.no_of_atm || '0', 10) < 2) continue;
        if (f.field_key === 'atm_3_status' && parseInt(form.no_of_atm || '0', 10) < 3) continue;

        // Skip hidden UPS fields
        if (f.field_key.startsWith('ups_working_') && form.ups_available !== 'yes') continue;
        if (f.field_key === 'ups_battery_backup' && form.ups_available !== 'yes') continue;

        if (f.is_required && f.field_type !== 'file') {
          const val = form[f.field_key];
          if (val === undefined || val === null || val === '') {
            Alert.alert('Required Field', `Please complete "${f.field_label || f.field_key}".`);
            return false;
          }
        }
      }
    }

    if (form.remarks && form.remarks.length > 2000) {
      Alert.alert('Character Limit', 'Remarks must not exceed 2000 characters.');
      return false;
    }

    return true;
  };

  const handleNextStep = () => {
    if (validateStep(currentStep)) {
      saveDraft(false);
      if (currentStep < steps.length) {
        setCurrentStep(currentStep + 1);
        if (scrollViewRef.current) {
          scrollViewRef.current.scrollTo({ y: 0, animated: true });
        }
      }
    }
  };

  const handlePrevStep = () => {
    if (currentStep > 1) {
      setCurrentStep(currentStep - 1);
      if (scrollViewRef.current) {
        scrollViewRef.current.scrollTo({ y: 0, animated: true });
      }
    }
  };

  // Submit Final Feasibility Check
  const handleSubmitFinal = () => {
    // Validate required fields across all steps
    for (let i = 1; i <= steps.length; i++) {
      if (!validateStep(i)) {
        setCurrentStep(i);
        return;
      }
    }

    const isResubmission = Boolean(rejectionInfo && rejectionInfo.is_rejected);

    Alert.alert(
      isResubmission ? 'Resubmit Feasibility Survey' : 'Submit Feasibility Check',
      isResubmission
        ? 'Are you ready to resubmit your corrected feasibility survey for supervisor review?'
        : 'Are you sure all details are accurate? Once submitted, this feasibility inspection will be sent for review.',
      [
        { text: 'Cancel', style: 'cancel' },
        {
          text: isResubmission ? 'Confirm & Resubmit' : 'Confirm & Submit',
          onPress: async () => {
            try {
              setIsSubmitting(true);

              // Gather all photo fields dynamically
              const imageCategories = [];
              if (customForm && Array.isArray(customForm.fields)) {
                customForm.fields.forEach((f) => {
                  if (f.field_type === 'file' || f.field_key.endsWith('_snap')) {
                    imageCategories.push(f.field_key);
                  }
                });
              } else {
                imageCategories.push(
                  'backroom_network_snap',
                  'ups_available_snap',
                  'earthing_snap',
                  'router_antenna_snap',
                  'antenna_routing_snap',
                  'remarks_snap'
                );
              }

              // Filter out local URIs from main JSON payload
              const payload = { ...form, assignment_id: assignmentId };
              const jsonPayload = {};
              Object.keys(payload).forEach((key) => {
                if (!imageCategories.includes(key)) {
                  jsonPayload[key] = payload[key];
                }
              });

              // 1. Submit main form data
              const res = await engineerApi.saveFeasibility(jsonPayload);

              if (res && res.success) {
                const feasibilityId = res.data?.id;

                // 2. Upload any photos
                if (feasibilityId) {
                  for (const cat of imageCategories) {
                    const uri = form[cat];
                    if (uri && (uri.startsWith('file://') || uri.startsWith('content://') || uri.startsWith('/'))) {
                      try {
                        const imgRes = await engineerApi.uploadFeasibilityImage(feasibilityId, cat, uri);
                        if (imgRes && imgRes.data && imgRes.data.path) {
                          updateFormField(cat, imgRes.data.path);
                        }
                      } catch (imgErr) {
                        console.log(`Failed to upload ${cat}:`, imgErr);
                      }
                    }
                  }
                }

                // 3. Clear draft
                const draftKey = `${STORAGE_KEYS.FEASIBILITY_DRAFT}${assignmentId}`;
                await AsyncStorage.removeItem(draftKey);

                setFeasibilityStatus('pending_contractor_review');
                setRejectionInfo(null);
                setIsReportMode(true);
                await loadFeasibilityData();

                Alert.alert(
                  isResubmission ? 'Survey Resubmitted' : 'Feasibility Submitted',
                  isResubmission
                    ? 'Your corrected feasibility survey has been submitted for review!'
                    : 'Feasibility Check has been successfully submitted!',
                  [
                    {
                      text: 'View Submitted Report',
                      onPress: () => {
                        setIsReportMode(true);
                        loadFeasibilityData();
                      },
                    },
                    {
                      text: 'Back to Sites',
                      onPress: () => navigation.goBack(),
                    },
                  ]
                );
              } else {
                Alert.alert('Submission Error', res?.message || 'Failed to submit feasibility check.');
              }
            } catch (err) {
              const resData = err.response?.data;
              let msg = resData?.message || err.message || 'Submission failed. Please check your connection.';
              Alert.alert('Error', msg);
            } finally {
              setIsSubmitting(false);
            }
          },
        },
      ]
    );
  };

  // Render photo thumbnail in Report View
  const renderReportPhoto = (title, photoUri) => {
    const resolved = resolveImageUrl(photoUri);
    return (
      <View style={styles.reportPhotoContainer} key={title}>
        <Text style={styles.reportPhotoTitle}>{title}</Text>
        {photoUri ? (
          <TouchableOpacity
            style={styles.reportPhotoWrapper}
            onPress={() => setSelectedPhotoPreview({ uri: resolved, title })}
            activeOpacity={0.88}
          >
            <Image
              source={{ uri: resolved }}
              style={styles.reportPhotoImg}
              resizeMode="cover"
            />
            <View style={styles.reportPhotoBadge}>
              <Ionicons name="expand-outline" size={12} color="#fff" />
              <Text style={styles.reportPhotoBadgeText}>Tap to Enlarge</Text>
            </View>
          </TouchableOpacity>
        ) : (
          <View style={styles.reportNoPhotoBox}>
            <Ionicons name="image-outline" size={18} color={COLORS.textMuted} />
            <Text style={styles.reportNoPhotoText}>No photo attached</Text>
          </View>
        )}
      </View>
    );
  };

  // Submitted Report View (Dynamic & Static support)
  const renderSubmittedReportView = () => {
    const isApproved = ['contractor_approved', 'adv_approved', 'feasibility_completed'].includes(feasibilityStatus);
    const displayBank = serverSiteInfo?.bank_name || site?.bank_name || 'N/A';
    const displayCustomer = serverSiteInfo?.customer_name || site?.customer_name || 'N/A';
    const displayAddress = serverSiteInfo?.address || site?.address || 'N/A';
    const displayProject = serverSiteInfo?.project_name || customForm?.form_name || 'Standard';
    const displayAtmId = form.atm_id_1 || site?.atm_id || `Site #${siteId}`;

    return (
      <ScrollView
        ref={scrollViewRef}
        contentContainerStyle={styles.reportScrollContent}
        showsVerticalScrollIndicator={false}
      >
        {/* Status Card Banner */}
        <View
          style={[
            styles.reportStatusCard,
            isApproved ? styles.reportStatusApproved : styles.reportStatusPending,
          ]}
        >
          <View style={styles.reportStatusIconBox}>
            <Ionicons
              name={isApproved ? "checkmark-done-circle" : "time"}
              size={28}
              color={isApproved ? COLORS.success : COLORS.primary}
            />
          </View>
          <View style={{ flex: 1 }}>
            <Text style={[styles.reportStatusTitle, isApproved && { color: COLORS.success }]}>
              {isApproved ? 'Feasibility Approved' : 'Submitted & Under Review'}
            </Text>
            <Text style={styles.reportStatusSub}>
              {isApproved
                ? 'This inspection survey has been reviewed and approved.'
                : 'Inspection details submitted. Awaiting supervisor verification.'}
            </Text>
            {customForm?.form_name ? (
              <Text style={styles.reportTimestamp}>
                Form: {customForm.form_name}
              </Text>
            ) : null}
          </View>
        </View>

        {/* Master Site Card */}
        <View style={styles.reportSectionCard}>
          <View style={styles.reportSectionHeader}>
            <Ionicons name="business-outline" size={18} color={COLORS.primary} />
            <Text style={styles.reportSectionTitle}>Master Site Details</Text>
          </View>
          <View style={styles.reportGrid}>
            <View style={styles.reportGridItem}>
              <Text style={styles.reportItemLabel}>ATM ID</Text>
              <Text style={styles.reportItemVal}>{displayAtmId}</Text>
            </View>
            <View style={styles.reportGridItem}>
              <Text style={styles.reportItemLabel}>Project</Text>
              <Text style={styles.reportItemVal}>{displayProject}</Text>
            </View>
            <View style={styles.reportGridItem}>
              <Text style={styles.reportItemLabel}>Bank</Text>
              <Text style={styles.reportItemVal}>{displayBank}</Text>
            </View>
            <View style={styles.reportGridItem}>
              <Text style={styles.reportItemLabel}>Customer</Text>
              <Text style={styles.reportItemVal}>{displayCustomer}</Text>
            </View>
            <View style={styles.reportGridItemFull}>
              <Text style={styles.reportItemLabel}>Address</Text>
              <Text style={styles.reportItemVal}>{displayAddress}</Text>
            </View>
          </View>
        </View>

        {/* Dynamic Sections from Custom Form */}
        {steps.map((step) => {
          const photoFields = [];
          const textFields = [];

          if (step.fields && Array.isArray(step.fields)) {
            step.fields.forEach((f) => {
              if (f.field_type === 'file' || f.field_key.endsWith('_snap')) {
                photoFields.push(f);
              } else {
                textFields.push(f);
              }
            });
          }

          return (
            <View style={styles.reportSectionCard} key={step.id}>
              <View style={styles.reportSectionHeader}>
                <Ionicons name={step.icon || "layers-outline"} size={18} color={COLORS.primary} />
                <Text style={styles.reportSectionTitle}>{step.title}</Text>
              </View>

              {textFields.length > 0 ? (
                <View style={styles.reportGrid}>
                  {textFields.map((f) => {
                    const rawVal = form[f.field_key];
                    let displayVal = rawVal != null && rawVal !== '' ? String(rawVal) : 'N/A';
                    
                    // Format boolean/options
                    if (f.options && Array.isArray(f.options)) {
                      const match = f.options.find((o) => (typeof o === 'object' ? o.value === rawVal : o === rawVal));
                      if (match && typeof match === 'object') displayVal = match.label || displayVal;
                    }

                    const isFullWidth = (f.grid_width || 12) >= 12 || f.field_type === 'textarea';

                    return (
                      <View style={isFullWidth ? styles.reportGridItemFull : styles.reportGridItem} key={f.field_key}>
                        <Text style={styles.reportItemLabel}>{f.field_label || f.field_key}</Text>
                        <Text style={styles.reportItemVal}>{displayVal}</Text>
                      </View>
                    );
                  })}
                </View>
              ) : null}

              {photoFields.map((pf) => renderReportPhoto(pf.field_label || 'Photo', form[pf.field_key]))}
            </View>
          );
        })}
      </ScrollView>
    );
  };

  // Reusable Photo Upload Card Component
  const renderPhotoCard = (category, title, description) => {
    const photoUri = form[category];

    return (
      <View style={styles.photoCard} key={category}>
        <View style={styles.photoCardHeader}>
          <View style={{ flex: 1 }}>
            <Text style={styles.photoCardTitle}>{title}</Text>
            {description ? <Text style={styles.photoCardDesc}>{description}</Text> : null}
          </View>
        </View>

        {photoUri ? (
          <View style={styles.photoPreviewBox}>
            <TouchableOpacity
              onPress={() => setSelectedPhotoPreview({ uri: resolveImageUrl(photoUri), title })}
              activeOpacity={0.85}
            >
              <Image source={{ uri: resolveImageUrl(photoUri) }} style={styles.photoThumbnail} />
              <View style={styles.photoOverlayHint}>
                <Ionicons name="eye-outline" size={14} color="#fff" />
                <Text style={styles.photoOverlayText}>Tap to View</Text>
              </View>
            </TouchableOpacity>
            <View style={styles.photoActionRow}>
              <TouchableOpacity
                style={styles.retakeBtn}
                onPress={() => handlePickOrCapture(category, 'camera')}
              >
                <Ionicons name="camera" size={14} color={COLORS.primary} />
                <Text style={styles.retakeBtnText}>Retake</Text>
              </TouchableOpacity>
              <TouchableOpacity
                style={styles.removePhotoBtn}
                onPress={() => handleRemovePhoto(category)}
              >
                <Ionicons name="trash-outline" size={14} color={COLORS.danger} />
                <Text style={styles.removePhotoText}>Remove</Text>
              </TouchableOpacity>
            </View>
          </View>
        ) : (
          <View style={styles.photoUploadActions}>
            <TouchableOpacity
              style={styles.captureBtn}
              onPress={() => handlePickOrCapture(category, 'camera')}
            >
              <Ionicons name="camera" size={20} color="#fff" />
              <Text style={styles.captureBtnText}>Take Photo</Text>
            </TouchableOpacity>

            <TouchableOpacity
              style={styles.galleryBtn}
              onPress={() => handlePickOrCapture(category, 'gallery')}
            >
              <Ionicons name="images-outline" size={18} color={COLORS.textPrimary} />
              <Text style={styles.galleryBtnText}>Gallery</Text>
            </TouchableOpacity>
          </View>
        )}
      </View>
    );
  };

  // Reusable Radio / Select Pill Group
  const renderRadioGroup = (field, options, label, required = false) => {
    return (
      <View style={styles.fieldGroup} key={field}>
        <Text style={styles.inputLabel}>
          {label} {required ? <Text style={{ color: COLORS.danger }}>*</Text> : null}
        </Text>
        <View style={styles.radioRow}>
          {options.map((opt) => {
            const optVal = typeof opt === 'object' ? opt.value : opt;
            const optLbl = typeof opt === 'object' ? opt.label : opt;
            const isSelected = String(form[field]) === String(optVal);

            return (
              <TouchableOpacity
                key={String(optVal)}
                style={[styles.radioPill, isSelected && styles.radioPillActive]}
                onPress={() => updateFormField(field, optVal)}
              >
                {isSelected && <Ionicons name="checkmark-circle" size={14} color="#fff" style={{ marginRight: 4 }} />}
                <Text style={[styles.radioPillText, isSelected && styles.radioPillTextActive]}>
                  {optLbl}
                </Text>
              </TouchableOpacity>
            );
          })}
        </View>
      </View>
    );
  };

  // Render a Single Dynamic Field
  const renderDynamicField = (f) => {
    const key = f.field_key;
    const label = f.field_label || key;
    const req = Boolean(f.is_required);
    const placeholder = f.placeholder || '';
    const val = form[key] != null ? String(form[key]) : '';

    // ATM Conditional Visibility
    if (key === 'atm_id_1' && parseInt(form.no_of_atm || '0', 10) < 1) return null;
    if (key === 'atm_1_status' && parseInt(form.no_of_atm || '0', 10) < 1) return null;
    if (key === 'atm_id_2' && parseInt(form.no_of_atm || '0', 10) < 2) return null;
    if (key === 'atm_2_status' && parseInt(form.no_of_atm || '0', 10) < 2) return null;
    if (key === 'atm_id_3' && parseInt(form.no_of_atm || '0', 10) < 3) return null;
    if (key === 'atm_3_status' && parseInt(form.no_of_atm || '0', 10) < 3) return null;

    // UPS Conditional Visibility
    if (key === 'no_of_ups' && form.ups_available !== 'yes') return null;
    if (key === 'ups_battery_backup' && form.ups_available !== 'yes') return null;
    if (key === 'ups_working_1' && (form.ups_available !== 'yes' || parseInt(form.no_of_ups || '0', 10) < 1)) return null;
    if (key === 'ups_working_2' && (form.ups_available !== 'yes' || parseInt(form.no_of_ups || '0', 10) < 2)) return null;
    if (key === 'ups_working_3' && (form.ups_available !== 'yes' || parseInt(form.no_of_ups || '0', 10) < 3)) return null;

    // Power Cut Conditional Visibility
    if (key === 'frequent_power_cut_from' && form.frequent_power_cut !== 'yes') return null;
    if (key === 'frequent_power_cut_to' && form.frequent_power_cut !== 'yes') return null;
    if (key === 'frequent_power_cut_remark' && form.frequent_power_cut !== 'yes') return null;

    // File / Snap Field
    if (f.field_type === 'file' || key.endsWith('_snap')) {
      return renderPhotoCard(key, label, f.help_text || placeholder || 'Attach clear inspection photo');
    }

    // Select or Radio
    if (f.field_type === 'select' || f.field_type === 'radio') {
      const opts = f.resolved_options || f.options || [];
      if (Array.isArray(opts) && opts.length > 0) {
        return renderRadioGroup(key, opts, label, req);
      }
    }

    // Textarea
    if (f.field_type === 'textarea') {
      return (
        <View style={styles.fieldGroup} key={key}>
          <Text style={styles.inputLabel}>
            {label} {req ? <Text style={{ color: COLORS.danger }}>*</Text> : null}
          </Text>
          <TextInput
            style={[styles.input, styles.textArea]}
            placeholder={placeholder || `Enter ${label}`}
            value={val}
            onChangeText={(t) => updateFormField(key, t)}
            multiline
            numberOfLines={3}
          />
          {key === 'remarks' && (
            <Text style={styles.charCountText}>{val.length} / 2000 characters</Text>
          )}
        </View>
      );
    }

    // Standard Input (text, number, phone, email, date)
    const keyboardType =
      f.field_type === 'number'
        ? 'numeric'
        : f.field_type === 'phone'
        ? 'phone-pad'
        : f.field_type === 'email'
        ? 'email-address'
        : 'default';

    return (
      <View style={styles.fieldGroup} key={key}>
        <Text style={styles.inputLabel}>
          {label} {req ? <Text style={{ color: COLORS.danger }}>*</Text> : null}
        </Text>
        <TextInput
          style={styles.input}
          placeholder={placeholder || `Enter ${label}`}
          value={val}
          onChangeText={(t) => updateFormField(key, t)}
          keyboardType={keyboardType}
        />
        {f.help_text ? <Text style={styles.fieldHelpText}>{f.help_text}</Text> : null}
      </View>
    );
  };

  const progressPercent = Math.round((currentStep / Math.max(steps.length, 1)) * 100);
  const activeStep = steps[currentStep - 1] || steps[0];

  return (
    <SafeAreaView style={styles.safeArea}>
      <Header
        title={isReportMode ? "Feasibility Report" : "Feasibility Check"}
        subtitle={form.atm_id_1 || site?.atm_id || `Site #${siteId}`}
        showBack
        onBack={() => navigation.goBack()}
        rightAction={
          isReportMode ? (
            !['contractor_approved', 'adv_approved'].includes(feasibilityStatus) ? (
              <TouchableOpacity onPress={() => setIsReportMode(false)} style={styles.draftTopBtn}>
                <Ionicons name="create-outline" size={15} color={COLORS.primary} />
                <Text style={styles.draftTopBtnText}>Edit Form</Text>
              </TouchableOpacity>
            ) : null
          ) : (
            <View style={{ flexDirection: 'row', gap: 6 }}>
              {['pending_contractor_review', 'feasibility_completed', 'contractor_approved', 'adv_approved'].includes(feasibilityStatus) && (
                <TouchableOpacity onPress={() => setIsReportMode(true)} style={styles.draftTopBtn}>
                  <Ionicons name="document-text-outline" size={15} color={COLORS.primary} />
                  <Text style={styles.draftTopBtnText}>Report</Text>
                </TouchableOpacity>
              )}
              <TouchableOpacity onPress={() => saveDraft(true)} style={styles.draftTopBtn}>
                <Ionicons name="save-outline" size={15} color={COLORS.primary} />
                <Text style={styles.draftTopBtnText}>Save</Text>
              </TouchableOpacity>
            </View>
          )
        }
      />

      {isLoading ? (
        <View style={styles.loaderBox}>
          <ActivityIndicator size="large" color={COLORS.primary} />
          <Text style={styles.loaderText}>Loading Feasibility Form...</Text>
        </View>
      ) : isReportMode ? (
        <>
          {renderSubmittedReportView()}

          {/* Sticky Bottom Bar for Report Mode */}
          <View style={styles.reportBottomBar}>
            <TouchableOpacity
              style={styles.reportBackBtn}
              onPress={() => navigation.goBack()}
            >
              <Ionicons name="arrow-back" size={18} color={COLORS.textPrimary} />
              <Text style={styles.reportBackBtnText}>Back to Sites</Text>
            </TouchableOpacity>

            {!['contractor_approved', 'adv_approved'].includes(feasibilityStatus) ? (
              <TouchableOpacity
                style={styles.reportEditBtn}
                onPress={() => setIsReportMode(false)}
              >
                <Ionicons name="create-outline" size={18} color="#fff" />
                <Text style={styles.reportEditBtnText}>Edit Survey</Text>
              </TouchableOpacity>
            ) : (
              <View style={styles.reportApprovedBadge}>
                <Ionicons name="checkmark-done" size={16} color={COLORS.success} />
                <Text style={styles.reportApprovedBadgeText}>Verified & Approved</Text>
              </View>
            )}
          </View>
        </>
      ) : (
        <>
          {/* Progress & Step Indicator Header */}
          <View style={styles.headerCard}>
            <View style={styles.progressRow}>
              <Text style={styles.stepCountText}>
                Step {currentStep} of {steps.length}: <Text style={styles.stepTitleText}>{activeStep?.title}</Text>
              </Text>
              <Text style={styles.stepPercentText}>{progressPercent}%</Text>
            </View>

            {/* Progress Bar */}
            <View style={styles.progressBarTrack}>
              <View style={[styles.progressBarFill, { width: `${progressPercent}%` }]} />
            </View>

            {/* Horizontal Step Tabs */}
            <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={styles.stepperContainer}>
              {steps.map((step) => {
                const isCurrent = currentStep === step.id;
                const isCompleted = currentStep > step.id;
                const isRejectedStep = (() => {
                  if (!rejectionInfo || !rejectionInfo.is_rejected) return false;
                  if (rejectionInfo.rejection_type === 'overall') return true;
                  const secs = rejectionInfo.rejected_sections || [];
                  return secs.some((s) => s === step.slug || normalizeSlug(s) === step.slug);
                })();

                return (
                  <TouchableOpacity
                    key={step.id}
                    style={[
                      styles.stepChip,
                      isCurrent && styles.stepChipCurrent,
                      isCompleted && styles.stepChipCompleted,
                      isRejectedStep && styles.stepChipRejected,
                    ]}
                    onPress={() => {
                      if (step.id < currentStep || validateStep(currentStep) || isRejectedStep) {
                        setCurrentStep(step.id);
                      }
                    }}
                  >
                    <View
                      style={[
                        styles.stepChipCircle,
                        isCurrent && styles.stepChipCircleCurrent,
                        isCompleted && styles.stepChipCircleCompleted,
                        isRejectedStep && styles.stepChipCircleRejected,
                      ]}
                    >
                      {isRejectedStep ? (
                        <Ionicons name="alert" size={12} color="#ffffff" />
                      ) : isCompleted ? (
                        <Ionicons name="checkmark" size={12} color="#fff" />
                      ) : (
                        <Ionicons
                          name={step.icon}
                          size={12}
                          color={isCurrent ? '#fff' : COLORS.textSecondary}
                        />
                      )}
                    </View>
                    <Text
                      style={[
                        styles.stepChipText,
                        isCurrent && styles.stepChipTextCurrent,
                        isCompleted && styles.stepChipTextCompleted,
                        isRejectedStep && styles.stepChipTextRejected,
                      ]}
                    >
                      {step.shortLabel}
                    </Text>
                  </TouchableOpacity>
                );
              })}
            </ScrollView>
          </View>

          {/* Compact Rejection Notice Banner */}
          {rejectionInfo && rejectionInfo.is_rejected ? (
            <View style={styles.rejectionCard}>
              <View style={styles.rejectionHeaderRow}>
                <Ionicons name="alert-circle" size={16} color={COLORS.danger} />
                <Text style={styles.rejectionTitle}>
                  Rejected by {rejectionInfo.reviewer_role === 'adv' ? 'ADV Reviewer' : 'Contractor Admin'}
                </Text>
              </View>
              <Text style={styles.rejectionReasonText} numberOfLines={3}>
                "{rejectionInfo.reason || rejectionInfo.comments || 'Please review the flagged sections marked in red, correct the details, and resubmit.'}"
              </Text>
            </View>
          ) : null}

          {/* Draft Notification Banner */}
          {lastSavedTime && (!rejectionInfo || !rejectionInfo.is_rejected) ? (
            <View style={styles.draftNoticeBanner}>
              <Ionicons name="checkmark-done" size={14} color={COLORS.success} />
              <Text style={styles.draftNoticeText}>{lastSavedTime}</Text>
            </View>
          ) : null}

          {/* Form Step Body */}
          <ScrollView
            ref={scrollViewRef}
            contentContainerStyle={styles.scrollContent}
            keyboardShouldPersistTaps="handled"
          >
            <View style={styles.stepContainer}>
              <View style={styles.stepBanner}>
                <Ionicons name={activeStep?.icon || "layers"} size={24} color={COLORS.primary} />
                <View style={{ marginLeft: 10, flex: 1 }}>
                  <Text style={styles.stepBannerTitle}>{activeStep?.title}</Text>
                  <Text style={styles.stepBannerSub}>Complete the required site survey details below</Text>
                </View>
              </View>

              {/* Master Site Read-only Info on Step 1 */}
              {currentStep === 1 && (
                <View style={styles.readOnlyInfoCard}>
                  <Text style={styles.readOnlyCardTitle}>Master Site Info</Text>
                  <View style={styles.readOnlyGrid}>
                    <View style={styles.readOnlyItem}>
                      <Text style={styles.readOnlyLabel}>Bank Name</Text>
                      <Text style={styles.readOnlyVal}>{site?.bank_name || serverSiteInfo?.bank_name || 'N/A'}</Text>
                    </View>
                    <View style={styles.readOnlyItem}>
                      <Text style={styles.readOnlyLabel}>Customer</Text>
                      <Text style={styles.readOnlyVal}>{site?.customer_name || serverSiteInfo?.customer_name || 'N/A'}</Text>
                    </View>
                    <View style={styles.readOnlyItemFull}>
                      <Text style={styles.readOnlyLabel}>Address</Text>
                      <Text style={styles.readOnlyVal}>{site?.address || serverSiteInfo?.address || 'N/A'}</Text>
                    </View>
                  </View>
                </View>
              )}

              {/* Render Fields Dynamically for this Step */}
              {activeStep?.fields && activeStep.fields.length > 0 ? (
                activeStep.fields.map((f) => renderDynamicField(f))
              ) : (
                <Text style={{ color: COLORS.textMuted, fontStyle: 'italic', paddingVertical: 12 }}>
                  No additional fields in this section.
                </Text>
              )}
            </View>
          </ScrollView>

          {/* Sticky Bottom Navigation Bar */}
          <View style={styles.bottomBar}>
            {currentStep > 1 ? (
              <TouchableOpacity
                style={styles.prevBtn}
                onPress={handlePrevStep}
                disabled={isSubmitting}
              >
                <Ionicons name="arrow-back" size={16} color={COLORS.textPrimary} />
                <Text style={styles.prevBtnText}>Previous</Text>
              </TouchableOpacity>
            ) : (
              <View style={{ width: 100 }} />
            )}

            {currentStep < steps.length ? (
              <TouchableOpacity
                style={styles.nextBtn}
                onPress={handleNextStep}
                disabled={isSubmitting}
              >
                <Text style={styles.nextBtnText}>Next Step</Text>
                <Ionicons name="arrow-forward" size={16} color="#fff" />
              </TouchableOpacity>
            ) : (
              <TouchableOpacity
                style={[styles.submitBtn, isSubmitting && { opacity: 0.7 }]}
                onPress={handleSubmitFinal}
                disabled={isSubmitting}
              >
                {isSubmitting ? (
                  <ActivityIndicator size="small" color="#fff" style={{ marginRight: 6 }} />
                ) : (
                  <Ionicons name="checkmark-circle" size={18} color="#fff" style={{ marginRight: 6 }} />
                )}
                <Text style={styles.submitBtnText}>
                  {isSubmitting
                    ? 'Submitting...'
                    : rejectionInfo && rejectionInfo.is_rejected
                    ? 'Resubmit Survey'
                    : 'Submit Feasibility'}
                </Text>
              </TouchableOpacity>
            )}
          </View>
        </>
      )}

      {/* Full-Screen Zoomable Image Lightbox Modal */}
      <ZoomableImageModal
        visible={Boolean(selectedPhotoPreview)}
        imageUri={selectedPhotoPreview?.uri}
        title={selectedPhotoPreview?.title || 'Inspection Photo'}
        onClose={() => setSelectedPhotoPreview(null)}
      />
    </SafeAreaView>
  );
};

const styles = StyleSheet.create({
  safeArea: {
    flex: 1,
    backgroundColor: '#f8fafc',
  },
  loaderBox: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
  },
  loaderText: {
    marginTop: 12,
    fontSize: 13,
    color: COLORS.textMuted,
  },
  headerCard: {
    backgroundColor: '#fff',
    paddingHorizontal: SPACING.md,
    paddingTop: SPACING.sm,
    paddingBottom: SPACING.sm,
    borderBottomWidth: 1,
    borderBottomColor: COLORS.border,
  },
  progressRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 6,
  },
  stepCountText: {
    fontSize: 12,
    color: COLORS.textSecondary,
    fontWeight: '500',
  },
  stepTitleText: {
    color: COLORS.textPrimary,
    fontWeight: '700',
  },
  stepPercentText: {
    fontSize: 12,
    fontWeight: '700',
    color: COLORS.primary,
  },
  progressBarTrack: {
    height: 4,
    backgroundColor: '#e2e8f0',
    borderRadius: 2,
    overflow: 'hidden',
    marginBottom: SPACING.sm,
  },
  progressBarFill: {
    height: '100%',
    backgroundColor: COLORS.primary,
  },
  stepperContainer: {
    flexDirection: 'row',
    gap: 8,
    paddingVertical: 4,
  },
  stepChip: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#f1f5f9',
    paddingHorizontal: 10,
    paddingVertical: 5,
    borderRadius: 20,
    borderWidth: 1,
    borderColor: 'transparent',
  },
  stepChipCurrent: {
    backgroundColor: '#eff6ff',
    borderColor: COLORS.primary,
  },
  stepChipCompleted: {
    backgroundColor: '#f0fdf4',
  },
  stepChipRejected: {
    backgroundColor: '#fef2f2',
    borderColor: COLORS.danger,
  },
  stepChipCircle: {
    width: 18,
    height: 18,
    borderRadius: 9,
    backgroundColor: '#cbd5e1',
    alignItems: 'center',
    justifyContent: 'center',
    marginRight: 6,
  },
  stepChipCircleCurrent: {
    backgroundColor: COLORS.primary,
  },
  stepChipCircleCompleted: {
    backgroundColor: COLORS.success,
  },
  stepChipCircleRejected: {
    backgroundColor: COLORS.danger,
  },
  stepChipText: {
    fontSize: 11,
    fontWeight: '600',
    color: COLORS.textSecondary,
  },
  stepChipTextCurrent: {
    color: COLORS.primary,
  },
  stepChipTextCompleted: {
    color: COLORS.success,
  },
  stepChipTextRejected: {
    color: COLORS.danger,
  },
  rejectionCard: {
    backgroundColor: '#fef2f2',
    borderBottomWidth: 1,
    borderBottomColor: '#fecaca',
    paddingHorizontal: SPACING.md,
    paddingVertical: 8,
  },
  rejectionHeaderRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    marginBottom: 2,
  },
  rejectionTitle: {
    fontSize: 12,
    fontWeight: '700',
    color: COLORS.danger,
  },
  rejectionReasonText: {
    fontSize: 11,
    color: '#991b1b',
    lineHeight: 15,
  },
  draftNoticeBanner: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#f0fdf4',
    paddingHorizontal: SPACING.md,
    paddingVertical: 4,
    gap: 6,
    borderBottomWidth: 1,
    borderBottomColor: '#bbf7d0',
  },
  draftNoticeText: {
    fontSize: 11,
    color: '#15803d',
    fontWeight: '500',
  },
  scrollContent: {
    padding: SPACING.md,
    paddingBottom: 100,
  },
  stepContainer: {
    backgroundColor: '#fff',
    borderRadius: RADIUS.md,
    padding: SPACING.md,
    ...SHADOWS.small,
  },
  stepBanner: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#f8fafc',
    padding: SPACING.sm,
    borderRadius: RADIUS.sm,
    marginBottom: SPACING.md,
    borderLeftWidth: 3,
    borderLeftColor: COLORS.primary,
  },
  stepBannerTitle: {
    fontSize: 14,
    fontWeight: '700',
    color: COLORS.textPrimary,
  },
  stepBannerSub: {
    fontSize: 11,
    color: COLORS.textMuted,
    marginTop: 1,
  },
  readOnlyInfoCard: {
    backgroundColor: '#eff6ff',
    borderRadius: RADIUS.sm,
    padding: SPACING.sm,
    marginBottom: SPACING.md,
    borderWidth: 1,
    borderColor: '#bfdbfe',
  },
  readOnlyCardTitle: {
    fontSize: 11,
    fontWeight: '700',
    color: '#1e40af',
    textTransform: 'uppercase',
    marginBottom: 6,
  },
  readOnlyGrid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 8,
  },
  readOnlyItem: {
    width: '47%',
  },
  readOnlyItemFull: {
    width: '100%',
  },
  readOnlyLabel: {
    fontSize: 10,
    color: '#64748b',
    fontWeight: '500',
  },
  readOnlyVal: {
    fontSize: 11,
    fontWeight: '600',
    color: '#1e293b',
  },
  fieldGroup: {
    marginBottom: SPACING.md,
  },
  inputLabel: {
    fontSize: 12,
    fontWeight: '600',
    color: COLORS.textPrimary,
    marginBottom: 6,
  },
  input: {
    backgroundColor: '#fff',
    borderWidth: 1,
    borderColor: COLORS.border,
    borderRadius: RADIUS.sm,
    paddingHorizontal: 12,
    paddingVertical: 9,
    fontSize: 13,
    color: COLORS.textPrimary,
  },
  textArea: {
    height: 72,
    textAlignVertical: 'top',
  },
  charCountText: {
    fontSize: 10,
    color: COLORS.textMuted,
    textAlign: 'right',
    marginTop: 4,
  },
  fieldHelpText: {
    fontSize: 10,
    color: COLORS.textMuted,
    marginTop: 3,
  },
  radioRow: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 8,
  },
  radioPill: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#f1f5f9',
    paddingHorizontal: 12,
    paddingVertical: 8,
    borderRadius: 20,
    borderWidth: 1,
    borderColor: '#e2e8f0',
  },
  radioPillActive: {
    backgroundColor: COLORS.primary,
    borderColor: COLORS.primary,
  },
  radioPillText: {
    fontSize: 12,
    fontWeight: '600',
    color: COLORS.textSecondary,
  },
  radioPillTextActive: {
    color: '#fff',
  },
  photoCard: {
    backgroundColor: '#f8fafc',
    borderRadius: RADIUS.sm,
    padding: SPACING.sm,
    borderWidth: 1,
    borderColor: '#e2e8f0',
    marginBottom: SPACING.md,
  },
  photoCardHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: 8,
  },
  photoCardTitle: {
    fontSize: 12,
    fontWeight: '700',
    color: COLORS.textPrimary,
  },
  photoCardDesc: {
    fontSize: 10,
    color: COLORS.textMuted,
    marginTop: 1,
  },
  photoPreviewBox: {
    alignItems: 'center',
  },
  photoThumbnail: {
    width: '100%',
    height: 140,
    borderRadius: RADIUS.sm,
    backgroundColor: '#cbd5e1',
  },
  photoOverlayHint: {
    position: 'absolute',
    bottom: 8,
    right: 8,
    backgroundColor: 'rgba(0,0,0,0.6)',
    paddingHorizontal: 8,
    paddingVertical: 3,
    borderRadius: 12,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
  },
  photoOverlayText: {
    color: '#fff',
    fontSize: 10,
    fontWeight: '600',
  },
  photoActionRow: {
    flexDirection: 'row',
    gap: 12,
    marginTop: 8,
  },
  retakeBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#eff6ff',
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: 16,
    gap: 4,
  },
  retakeBtnText: {
    fontSize: 11,
    fontWeight: '600',
    color: COLORS.primary,
  },
  removePhotoBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#fef2f2',
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: 16,
    gap: 4,
  },
  removePhotoText: {
    fontSize: 11,
    fontWeight: '600',
    color: COLORS.danger,
  },
  photoUploadActions: {
    flexDirection: 'row',
    gap: 10,
  },
  captureBtn: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: COLORS.primary,
    paddingVertical: 10,
    borderRadius: RADIUS.sm,
    gap: 6,
  },
  captureBtnText: {
    color: '#fff',
    fontSize: 12,
    fontWeight: '700',
  },
  galleryBtn: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: '#fff',
    borderWidth: 1,
    borderColor: '#cbd5e1',
    paddingVertical: 10,
    borderRadius: RADIUS.sm,
    gap: 6,
  },
  galleryBtnText: {
    color: COLORS.textPrimary,
    fontSize: 12,
    fontWeight: '600',
  },
  bottomBar: {
    position: 'absolute',
    bottom: 0,
    left: 0,
    right: 0,
    backgroundColor: '#fff',
    paddingHorizontal: SPACING.md,
    paddingVertical: SPACING.sm,
    borderTopWidth: 1,
    borderTopColor: COLORS.border,
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    ...SHADOWS.medium,
  },
  prevBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 14,
    paddingVertical: 10,
    borderRadius: RADIUS.sm,
    backgroundColor: '#f1f5f9',
    gap: 6,
  },
  prevBtnText: {
    fontSize: 12,
    fontWeight: '600',
    color: COLORS.textPrimary,
  },
  nextBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 18,
    paddingVertical: 10,
    borderRadius: RADIUS.sm,
    backgroundColor: COLORS.primary,
    gap: 6,
  },
  nextBtnText: {
    fontSize: 12,
    fontWeight: '700',
    color: '#fff',
  },
  submitBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 18,
    paddingVertical: 10,
    borderRadius: RADIUS.sm,
    backgroundColor: '#16a34a',
  },
  submitBtnText: {
    fontSize: 12,
    fontWeight: '700',
    color: '#fff',
  },
  draftTopBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#eff6ff',
    paddingHorizontal: 10,
    paddingVertical: 5,
    borderRadius: 14,
    gap: 4,
  },
  draftTopBtnText: {
    fontSize: 11,
    fontWeight: '700',
    color: COLORS.primary,
  },
  reportScrollContent: {
    padding: SPACING.md,
    paddingBottom: 90,
  },
  reportStatusCard: {
    flexDirection: 'row',
    alignItems: 'center',
    padding: SPACING.md,
    borderRadius: RADIUS.md,
    marginBottom: SPACING.md,
    gap: 12,
  },
  reportStatusPending: {
    backgroundColor: '#eff6ff',
    borderWidth: 1,
    borderColor: '#bfdbfe',
  },
  reportStatusApproved: {
    backgroundColor: '#f0fdf4',
    borderWidth: 1,
    borderColor: '#bbf7d0',
  },
  reportStatusIconBox: {
    width: 44,
    height: 44,
    borderRadius: 22,
    backgroundColor: '#fff',
    alignItems: 'center',
    justifyContent: 'center',
  },
  reportStatusTitle: {
    fontSize: 14,
    fontWeight: '700',
    color: COLORS.primary,
  },
  reportStatusSub: {
    fontSize: 11,
    color: COLORS.textSecondary,
    marginTop: 2,
  },
  reportTimestamp: {
    fontSize: 10,
    color: COLORS.textMuted,
    marginTop: 4,
  },
  reportSectionCard: {
    backgroundColor: '#fff',
    borderRadius: RADIUS.md,
    padding: SPACING.md,
    marginBottom: SPACING.md,
    ...SHADOWS.small,
  },
  reportSectionHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    paddingBottom: SPACING.sm,
    borderBottomWidth: 1,
    borderBottomColor: '#f1f5f9',
    marginBottom: SPACING.sm,
  },
  reportSectionTitle: {
    fontSize: 13,
    fontWeight: '700',
    color: COLORS.textPrimary,
  },
  reportGrid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 10,
  },
  reportGridItem: {
    width: '48%',
    marginBottom: 4,
  },
  reportGridItemFull: {
    width: '100%',
    marginBottom: 4,
  },
  reportItemLabel: {
    fontSize: 10,
    color: COLORS.textMuted,
    fontWeight: '500',
    marginBottom: 1,
  },
  reportItemVal: {
    fontSize: 12,
    fontWeight: '600',
    color: COLORS.textPrimary,
  },
  reportPhotoContainer: {
    marginTop: SPACING.sm,
    paddingTop: SPACING.sm,
    borderTopWidth: 1,
    borderTopColor: '#f8fafc',
  },
  reportPhotoTitle: {
    fontSize: 11,
    fontWeight: '600',
    color: COLORS.textSecondary,
    marginBottom: 6,
  },
  reportPhotoWrapper: {
    position: 'relative',
    borderRadius: RADIUS.sm,
    overflow: 'hidden',
  },
  reportPhotoImg: {
    width: '100%',
    height: 140,
    backgroundColor: '#e2e8f0',
  },
  reportPhotoBadge: {
    position: 'absolute',
    bottom: 6,
    right: 6,
    backgroundColor: 'rgba(0,0,0,0.65)',
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
    paddingHorizontal: 8,
    paddingVertical: 3,
    borderRadius: 12,
  },
  reportPhotoBadgeText: {
    fontSize: 10,
    color: '#fff',
    fontWeight: '600',
  },
  reportNoPhotoBox: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    paddingVertical: 6,
  },
  reportNoPhotoText: {
    fontSize: 11,
    color: COLORS.textMuted,
    fontStyle: 'italic',
  },
  reportBottomBar: {
    position: 'absolute',
    bottom: 0,
    left: 0,
    right: 0,
    backgroundColor: '#fff',
    paddingHorizontal: SPACING.md,
    paddingVertical: SPACING.sm,
    borderTopWidth: 1,
    borderTopColor: COLORS.border,
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  reportBackBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    paddingVertical: 8,
    paddingHorizontal: 12,
    backgroundColor: '#f1f5f9',
    borderRadius: RADIUS.sm,
  },
  reportBackBtnText: {
    fontSize: 12,
    fontWeight: '600',
    color: COLORS.textPrimary,
  },
  reportEditBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    paddingVertical: 8,
    paddingHorizontal: 16,
    backgroundColor: COLORS.primary,
    borderRadius: RADIUS.sm,
  },
  reportEditBtnText: {
    fontSize: 12,
    fontWeight: '700',
    color: '#fff',
  },
  reportApprovedBadge: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
    backgroundColor: '#f0fdf4',
    paddingVertical: 6,
    paddingHorizontal: 12,
    borderRadius: RADIUS.sm,
  },
  reportApprovedBadgeText: {
    fontSize: 11,
    fontWeight: '700',
    color: COLORS.success,
  },
});
