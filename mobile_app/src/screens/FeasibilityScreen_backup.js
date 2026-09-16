import React, { useState, useEffect, useRef } from 'react';
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
import AsyncStorage from '@react-native-async-storage/async-storage';
import { engineerApi } from '../api/engineerApi';
import { getApiBaseUrl } from '../api/client';
import { Header } from '../components/Header';
import { CustomButton } from '../components/CustomButton';
import { ZoomableImageModal } from '../components/ZoomableImageModal';
import { COLORS, FONTS, RADIUS, SPACING, SHADOWS } from '../constants/theme';
import { STORAGE_KEYS } from '../constants/config';

// 6 Structured Inspection Steps matching engineer/feasibility_form.php
const FEASIBILITY_STEPS = [
  { id: 1, title: 'ATM Info', shortLabel: 'ATM', icon: 'card-outline' },
  { id: 2, title: 'Network Info', shortLabel: 'Network', icon: 'wifi-outline' },
  { id: 3, title: 'Power & UPS', shortLabel: 'Power', icon: 'flash-outline' },
  { id: 4, title: 'Electrical', shortLabel: 'Electrical', icon: 'git-network-outline' },
  { id: 5, title: 'Site Access & Env', shortLabel: 'Access', icon: 'key-outline' },
  { id: 6, title: 'Review & Submit', shortLabel: 'Review', icon: 'checkmark-done-circle-outline' },
];

const SECTION_TO_STEP_MAP = {
  atm_information: 1,
  network_information: 2,
  power_infrastructure: 3,
  electrical_measurements: 4,
  site_access: 5,
  environmental_factors: 5,
  remarks: 6,
};

export const FeasibilityScreen = ({ route, navigation }) => {
  const { siteId, site, assignmentId: paramAssignmentId, viewOnly } = route.params || {};
  const assignmentId = paramAssignmentId || site?.assignment_id || site?.id || siteId;

  const [currentStep, setCurrentStep] = useState(1);
  const [isReportMode, setIsReportMode] = useState(Boolean(viewOnly));
  const [feasibilityStatus, setFeasibilityStatus] = useState(site?.feasibility_status || '');
  const [serverSiteInfo, setServerSiteInfo] = useState(null);
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

  // Form State initialized with all standard fields
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
          setForm((prev) => ({ ...prev, ...parsed }));
          setLastSavedTime('Restored from draft');
        } catch (e) {
          console.log('Error parsing draft:', e);
        }
      }

      // Fetch from API to check existing submitted feasibility or master info
      if (assignmentId) {
        const res = await engineerApi.getFeasibility(assignmentId);
        if (res && res.success && res.data) {
          const fetchedStatus = res.data.feasibility_status || '';
          setFeasibilityStatus(fetchedStatus);
          if (res.data.site_info) {
            setServerSiteInfo(res.data.site_info);
          }

          if (res.data.rejection_info && res.data.rejection_info.is_rejected) {
            setRejectionInfo(res.data.rejection_info);
            setIsReportMode(false); // Open in form mode so engineer can correct
            const rejSecs = res.data.rejection_info.rejected_sections || [];
            const isOverall = res.data.rejection_info.rejection_type === 'overall';
            if (isOverall) {
              setCurrentStep(1);
            } else if (Array.isArray(rejSecs) && rejSecs.length > 0) {
              const firstRejectedStep = SECTION_TO_STEP_MAP[rejSecs[0]] || 1;
              setCurrentStep(firstRejectedStep);
            }
          } else {
            setRejectionInfo(null);
            // If already filled and submitted/approved, enter full report view mode!
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
              // Snaps from server
              backroom_network_snap: sf.backroom_network_snap || prev.backroom_network_snap,
              ups_available_snap: sf.ups_available_snap || prev.ups_available_snap,
              no_of_ups_snap: sf.no_of_ups_snap || prev.no_of_ups_snap,
              ups_working_snap: sf.ups_working_snap || prev.ups_working_snap,
              power_socket_availability_snap: sf.power_socket_availability_snap || prev.power_socket_availability_snap,
              earthing_snap: sf.earthing_snap || prev.earthing_snap,
              power_fluctuation_snap: sf.power_fluctuation_snap || prev.power_fluctuation_snap,
              router_antenna_snap: sf.router_antenna_snap || prev.router_antenna_snap,
              antenna_routing_snap: sf.antenna_routing_snap || prev.antenna_routing_snap,
              remarks_snap: sf.remarks_snap || prev.remarks_snap,
            }));
          }
          if (res.data.site_info && res.data.site_info.site_name) {
            if (!form.atm_id_1) {
              setForm((prev) => ({ ...prev, atm_id_1: res.data.site_info.site_name }));
            }
          }
        }
      }
    } catch (e) {
      console.log('Error loading feasibility data:', e);
    } finally {
      setIsLoading(false);
    }
  };

  const updateFormField = (field, value) => {
    setForm((prev) => ({ ...prev, [field]: value }));
  };

  // Image Picker with 80% Compression
  const handlePickOrCapture = async (category, source = 'camera') => {
    try {
      const pickerOptions = {
        mediaTypes: ['images'],
        allowsEditing: false,
        quality: 0.8, // 80% lossless/compressed quality
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
        updateFormField(category, asset.uri);
      }
    } catch (e) {
      Alert.alert('Error', 'Failed to pick or capture image.');
    }
  };

  const handleRemovePhoto = (category) => {
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

  // Validation per step
  const validateStep = (stepNumber) => {
    switch (stepNumber) {
      case 1:
        if (form.no_of_atm === '' || form.no_of_atm === null) {
          Alert.alert('Required Field', 'Please select the number of ATMs.');
          return false;
        }
        return true;

      case 2:
        if (!form.operator) {
          Alert.alert('Required Field', 'Please select the Primary Network Operator.');
          return false;
        }
        if (!form.signal_status) {
          Alert.alert('Required Field', 'Please select the Signal Status.');
          return false;
        }
        return true;

      case 3:
        if (!form.ups_available) {
          Alert.alert('Required Field', 'Please specify if UPS is available.');
          return false;
        }
        return true;

      case 4:
        if (!form.earthing) {
          Alert.alert('Required Field', 'Please select Earthing status.');
          return false;
        }
        return true;

      case 5:
        return true;

      case 6:
        if (form.remarks && form.remarks.length > 2000) {
          Alert.alert('Character Limit', 'Remarks must not exceed 2000 characters.');
          return false;
        }
        return true;

      default:
        return true;
    }
  };

  const handleNextStep = () => {
    if (validateStep(currentStep)) {
      saveDraft(false); // auto-save draft silently on step progression
      if (currentStep < 6) {
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
    // Validate all required fields
    if (!form.no_of_atm) {
      Alert.alert('Validation Error', 'Number of ATMs is required (Step 1).');
      setCurrentStep(1);
      return;
    }
    if (!form.operator || !form.signal_status) {
      Alert.alert('Validation Error', 'Network Operator and Signal Status are required (Step 2).');
      setCurrentStep(2);
      return;
    }
    if (!form.ups_available) {
      Alert.alert('Validation Error', 'UPS availability is required (Step 3).');
      setCurrentStep(3);
      return;
    }
    if (!form.earthing) {
      Alert.alert('Validation Error', 'Earthing status is required (Step 4).');
      setCurrentStep(4);
      return;
    }

    const isResubmission = Boolean(rejectionInfo && rejectionInfo.is_rejected);

    Alert.alert(
      isResubmission ? 'Resubmit Feasibility Survey' : 'Submit Feasibility Check',
      isResubmission
        ? 'Are you ready to resubmit your corrected feasibility survey to the contractor admin for review?'
        : 'Are you sure all details are accurate? Once submitted, this feasibility inspection will be sent for contractor & customer review.',
      [
        { text: 'Cancel', style: 'cancel' },
        {
          text: isResubmission ? 'Confirm & Resubmit' : 'Confirm & Submit',
          onPress: async () => {
            try {
              setIsSubmitting(true);

              // Prepare JSON payload (excluding local image URIs which are uploaded separately)
              const payload = { ...form, assignment_id: assignmentId };
              const imageCategories = [
                'backroom_network_snap',
                'ups_available_snap',
                'earthing_snap',
                'router_antenna_snap',
                'antenna_routing_snap',
                'remarks_snap',
              ];

              // Filter out local URIs from main JSON
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

                // 2. Upload any compressed photos if present
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
                    ? 'Your corrected feasibility survey has been submitted and sent back to the supervisor for review!'
                    : 'Feasibility Check has been successfully submitted and logged!',
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
                console.log('Feasibility save returned failure:', res);
                Alert.alert('Submission Error', res?.message || 'Failed to submit feasibility check.');
              }
            } catch (err) {
              console.error('Feasibility submit exception:', err);
              const resData = err.response?.data;
              let msg = resData?.message || err.message || 'Submission failed. Please check your connection.';
              if (resData?.errors) {
                const firstErr = Object.values(resData.errors)[0];
                if (Array.isArray(firstErr) && firstErr[0]) {
                  msg = `${msg}: ${firstErr[0]}`;
                } else if (typeof firstErr === 'string') {
                  msg = `${msg}: ${firstErr}`;
                }
              }
              Alert.alert('Error', msg);
            } finally {
              setIsSubmitting(false);
            }
          },
        },
      ]
    );
  };

  // Render photo thumbnail with lightbox trigger in Submitted Report View
  const renderReportPhoto = (title, photoUri) => {
    const resolved = resolveImageUrl(photoUri);
    return (
      <View style={styles.reportPhotoContainer}>
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

  // Full Submitted Inspection Report View
  const renderSubmittedReportView = () => {
    const isApproved = ['contractor_approved', 'adv_approved', 'feasibility_completed'].includes(feasibilityStatus);
    const displayBank = serverSiteInfo?.bank_name || site?.bank_name || form.bank_name || 'N/A';
    const displayCustomer = serverSiteInfo?.customer_name || site?.customer_name || form.customer_name || 'N/A';
    const displayAddress = serverSiteInfo?.address || site?.address || form.address || 'N/A';
    const displayCity = serverSiteInfo?.city || site?.city || form.city || '';
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
            {form.updated_at || form.created_at ? (
              <Text style={styles.reportTimestamp}>
                Submitted: {form.updated_at || form.created_at}
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
              <Text style={styles.reportItemLabel}>Bank</Text>
              <Text style={styles.reportItemVal}>{displayBank}</Text>
            </View>
            <View style={styles.reportGridItem}>
              <Text style={styles.reportItemLabel}>Customer</Text>
              <Text style={styles.reportItemVal}>{displayCustomer}</Text>
            </View>
            <View style={styles.reportGridItem}>
              <Text style={styles.reportItemLabel}>City / Zone</Text>
              <Text style={styles.reportItemVal}>{displayCity || site?.zone || 'N/A'}</Text>
            </View>
            <View style={styles.reportGridItemFull}>
              <Text style={styles.reportItemLabel}>Site Address</Text>
              <Text style={styles.reportItemVal}>{displayAddress}</Text>
            </View>
          </View>
        </View>

        {/* Section 1: ATM Information */}
        <View style={styles.reportSectionCard}>
          <View style={styles.reportSectionHeader}>
            <Ionicons name="card-outline" size={18} color={COLORS.primary} />
            <Text style={styles.reportSectionTitle}>ATM Information</Text>
          </View>
          <View style={styles.reportGrid}>
            <View style={styles.reportGridItem}>
              <Text style={styles.reportItemLabel}>Number of ATMs</Text>
              <Text style={styles.reportItemVal}>{form.no_of_atm || '0'} ATM(s)</Text>
            </View>
            <View style={styles.reportGridItem}>
              <Text style={styles.reportItemLabel}>ATM 1 ID & Status</Text>
              <Text style={styles.reportItemVal}>
                {form.atm_id_1 || 'N/A'} ({form.atm_1_status || 'working'})
              </Text>
            </View>
            {parseInt(form.no_of_atm, 10) >= 2 && (
              <View style={styles.reportGridItem}>
                <Text style={styles.reportItemLabel}>ATM 2 ID & Status</Text>
                <Text style={styles.reportItemVal}>
                  {form.atm_id_2 || 'N/A'} ({form.atm_2_status || 'working'})
                </Text>
              </View>
            )}
            {parseInt(form.no_of_atm, 10) >= 3 && (
              <View style={styles.reportGridItem}>
                <Text style={styles.reportItemLabel}>ATM 3 ID & Status</Text>
                <Text style={styles.reportItemVal}>
                  {form.atm_id_3 || 'N/A'} ({form.atm_3_status || 'working'})
                </Text>
              </View>
            )}
          </View>
        </View>

        {/* Section 2: Network Infrastructure */}
        <View style={styles.reportSectionCard}>
          <View style={styles.reportSectionHeader}>
            <Ionicons name="wifi-outline" size={18} color={COLORS.primary} />
            <Text style={styles.reportSectionTitle}>Network Infrastructure</Text>
          </View>
          <View style={styles.reportGrid}>
            <View style={styles.reportGridItem}>
              <Text style={styles.reportItemLabel}>Primary Operator</Text>
              <Text style={styles.reportItemVal}>{form.operator || 'N/A'}</Text>
            </View>
            <View style={styles.reportGridItem}>
              <Text style={styles.reportItemLabel}>Signal Status</Text>
              <Text style={styles.reportItemVal}>{form.signal_status?.toUpperCase() || 'N/A'}</Text>
            </View>
            {form.operator_2 ? (
              <>
                <View style={styles.reportGridItem}>
                  <Text style={styles.reportItemLabel}>Secondary Operator</Text>
                  <Text style={styles.reportItemVal}>{form.operator_2}</Text>
                </View>
                <View style={styles.reportGridItem}>
                  <Text style={styles.reportItemLabel}>Secondary Signal</Text>
                  <Text style={styles.reportItemVal}>{form.signal_status_2?.toUpperCase() || 'N/A'}</Text>
                </View>
              </>
            ) : null}
            {form.backroom_network_remark ? (
              <View style={styles.reportGridItemFull}>
                <Text style={styles.reportItemLabel}>Backroom Network Remark</Text>
                <Text style={styles.reportItemVal}>{form.backroom_network_remark}</Text>
              </View>
            ) : null}
          </View>
          {renderReportPhoto('Backroom Network Photo', form.backroom_network_snap)}
        </View>

        {/* Section 3: Power & UPS */}
        <View style={styles.reportSectionCard}>
          <View style={styles.reportSectionHeader}>
            <Ionicons name="flash-outline" size={18} color={COLORS.primary} />
            <Text style={styles.reportSectionTitle}>Power & UPS Infrastructure</Text>
          </View>
          <View style={styles.reportGrid}>
            <View style={styles.reportGridItem}>
              <Text style={styles.reportItemLabel}>UPS Available</Text>
              <Text style={styles.reportItemVal}>{form.ups_available?.toUpperCase() || 'N/A'}</Text>
            </View>
            <View style={styles.reportGridItem}>
              <Text style={styles.reportItemLabel}>No. of UPS & Backup</Text>
              <Text style={styles.reportItemVal}>
                {form.no_of_ups || '1'} UPS ({form.ups_battery_backup || 'N/A'})
              </Text>
            </View>
            <View style={styles.reportGridItem}>
              <Text style={styles.reportItemLabel}>UPS Working</Text>
              <Text style={styles.reportItemVal}>
                UPS 1: {form.ups_working_1?.toUpperCase() || 'YES'}
                {parseInt(form.no_of_ups, 10) >= 2 ? ` • UPS 2: ${form.ups_working_2?.toUpperCase() || 'YES'}` : ''}
              </Text>
            </View>
            <View style={styles.reportGridItem}>
              <Text style={styles.reportItemLabel}>Power Socket</Text>
              <Text style={styles.reportItemVal}>
                General: {form.power_socket_availability} • UPS: {form.power_socket_availability_ups}
              </Text>
            </View>
          </View>
          {renderReportPhoto('UPS / Power Socket Photo', form.ups_available_snap || form.power_socket_availability_snap)}
        </View>

        {/* Section 4: Electrical Measurements */}
        <View style={styles.reportSectionCard}>
          <View style={styles.reportSectionHeader}>
            <Ionicons name="git-network-outline" size={18} color={COLORS.primary} />
            <Text style={styles.reportSectionTitle}>Electrical Measurements</Text>
          </View>
          <View style={styles.reportGrid}>
            <View style={styles.reportGridItem}>
              <Text style={styles.reportItemLabel}>Earthing Available</Text>
              <Text style={styles.reportItemVal}>{form.earthing?.toUpperCase() || 'N/A'}</Text>
            </View>
            <View style={styles.reportGridItem}>
              <Text style={styles.reportItemLabel}>Earthing Voltage</Text>
              <Text style={styles.reportItemVal}>{form.earthing_voltage ? `${form.earthing_voltage} V` : 'N/A'}</Text>
            </View>
            <View style={styles.reportGridItem}>
              <Text style={styles.reportItemLabel}>Phase - Earth (P-E)</Text>
              <Text style={styles.reportItemVal}>{form.power_fluctuation_pe ? `${form.power_fluctuation_pe} V` : 'N/A'}</Text>
            </View>
            <View style={styles.reportGridItem}>
              <Text style={styles.reportItemLabel}>Phase - Neutral (P-N)</Text>
              <Text style={styles.reportItemVal}>{form.power_fluctuation_pn ? `${form.power_fluctuation_pn} V` : 'N/A'}</Text>
            </View>
            <View style={styles.reportGridItem}>
              <Text style={styles.reportItemLabel}>Earth - Neutral (E-N)</Text>
              <Text style={styles.reportItemVal}>{form.power_fluctuation_en ? `${form.power_fluctuation_en} V` : 'N/A'}</Text>
            </View>
            <View style={styles.reportGridItem}>
              <Text style={styles.reportItemLabel}>Frequent Power Cut</Text>
              <Text style={styles.reportItemVal}>{form.frequent_power_cut?.toUpperCase() || 'NO'}</Text>
            </View>
            {form.frequent_power_cut === 'yes' ? (
              <View style={styles.reportGridItemFull}>
                <Text style={styles.reportItemLabel}>Power Cut Details</Text>
                <Text style={styles.reportItemVal}>
                  From: {form.frequent_power_cut_from || 'N/A'} To: {form.frequent_power_cut_to || 'N/A'}
                  {form.frequent_power_cut_remark ? ` • Remark: ${form.frequent_power_cut_remark}` : ''}
                </Text>
              </View>
            ) : null}
          </View>
          {renderReportPhoto('Earthing / Multimeter Photo', form.earthing_snap || form.power_fluctuation_snap)}
        </View>

        {/* Section 5: Site Access & Environment */}
        <View style={styles.reportSectionCard}>
          <View style={styles.reportSectionHeader}>
            <Ionicons name="key-outline" size={18} color={COLORS.primary} />
            <Text style={styles.reportSectionTitle}>Site Access & Environment</Text>
          </View>
          <View style={styles.reportGrid}>
            <View style={styles.reportGridItem}>
              <Text style={styles.reportItemLabel}>EM Lock Available</Text>
              <Text style={styles.reportItemVal}>{form.em_lock_available?.toUpperCase() || 'NO'}</Text>
            </View>
            <View style={styles.reportGridItem}>
              <Text style={styles.reportItemLabel}>Password Received</Text>
              <Text style={styles.reportItemVal}>{form.password_received?.toUpperCase() || 'NO'}</Text>
            </View>
            <View style={styles.reportGridItem}>
              <Text style={styles.reportItemLabel}>Backroom Key Status</Text>
              <Text style={styles.reportItemVal}>{form.backroom_key_status || 'N/A'}</Text>
            </View>
            <View style={styles.reportGridItem}>
              <Text style={styles.reportItemLabel}>Key Contact</Text>
              <Text style={styles.reportItemVal}>
                {form.backroom_key_name || 'N/A'} {form.backroom_key_number ? `(${form.backroom_key_number})` : ''}
              </Text>
            </View>
            <View style={styles.reportGridItem}>
              <Text style={styles.reportItemLabel}>Router Position</Text>
              <Text style={styles.reportItemVal}>{form.router_position || 'N/A'}</Text>
            </View>
            <View style={styles.reportGridItem}>
              <Text style={styles.reportItemLabel}>Antenna Position</Text>
              <Text style={styles.reportItemVal}>{form.router_antenna_position || 'N/A'}</Text>
            </View>
            {form.antenna_routing_detail ? (
              <View style={styles.reportGridItemFull}>
                <Text style={styles.reportItemLabel}>Antenna Routing</Text>
                <Text style={styles.reportItemVal}>{form.antenna_routing_detail}</Text>
              </View>
            ) : null}
            {form.nearest_shop_name ? (
              <View style={styles.reportGridItemFull}>
                <Text style={styles.reportItemLabel}>Nearest Shop</Text>
                <Text style={styles.reportItemVal}>
                  {form.nearest_shop_name} {form.nearest_shop_number ? `• Ph: ${form.nearest_shop_number}` : ''} {form.nearest_shop_distance ? `• Dist: ${form.nearest_shop_distance}` : ''}
                </Text>
              </View>
            ) : null}
          </View>
          {renderReportPhoto('Router & Antenna Photo', form.router_antenna_snap)}
          {form.antenna_routing_snap ? renderReportPhoto('Antenna Routing Photo', form.antenna_routing_snap) : null}
        </View>

        {/* Section 6: Remarks & Final Snap */}
        <View style={styles.reportSectionCard}>
          <View style={styles.reportSectionHeader}>
            <Ionicons name="chatbubble-ellipses-outline" size={18} color={COLORS.primary} />
            <Text style={styles.reportSectionTitle}>Remarks & Final Snapshot</Text>
          </View>
          <View style={styles.reportGrid}>
            <View style={styles.reportGridItemFull}>
              <Text style={styles.reportItemLabel}>Inspection Remarks</Text>
              <Text style={styles.reportItemVal}>{form.remarks || 'No additional remarks'}</Text>
            </View>
          </View>
          {renderReportPhoto('Remarks & Overall Site Photo', form.remarks_snap)}
        </View>
      </ScrollView>
    );
  };

  // Reusable Photo Upload Card Component with 80% Compression Notice
  const renderPhotoCard = (category, title, description) => {
    const photoUri = form[category];

    return (
      <View style={styles.photoCard}>
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
      <View style={styles.fieldGroup}>
        <Text style={styles.inputLabel}>
          {label} {required ? <Text style={{ color: COLORS.danger }}>*</Text> : null}
        </Text>
        <View style={styles.radioRow}>
          {options.map((opt) => {
            const isSelected = form[field] === opt.value;
            return (
              <TouchableOpacity
                key={opt.value}
                style={[styles.radioPill, isSelected && styles.radioPillActive]}
                onPress={() => updateFormField(field, opt.value)}
              >
                {isSelected && <Ionicons name="checkmark-circle" size={14} color="#fff" style={{ marginRight: 4 }} />}
                <Text style={[styles.radioPillText, isSelected && styles.radioPillTextActive]}>
                  {opt.label}
                </Text>
              </TouchableOpacity>
            );
          })}
        </View>
      </View>
    );
  };

  const progressPercent = Math.round((currentStep / 6) * 100);

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
          <Text style={styles.loaderText}>Loading Feasibility Details...</Text>
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
                Step {currentStep} of 6: <Text style={styles.stepTitleText}>{FEASIBILITY_STEPS[currentStep - 1].title}</Text>
              </Text>
              <Text style={styles.stepPercentText}>{progressPercent}%</Text>
            </View>

            {/* Progress Bar */}
            <View style={styles.progressBarTrack}>
              <View style={[styles.progressBarFill, { width: `${progressPercent}%` }]} />
            </View>

            {/* Horizontal Step Tabs */}
            <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={styles.stepperContainer}>
              {FEASIBILITY_STEPS.map((step) => {
                const isCurrent = currentStep === step.id;
                const isCompleted = currentStep > step.id;
                const isRejectedStep = (() => {
                  if (!rejectionInfo || !rejectionInfo.is_rejected) return false;
                  if (rejectionInfo.rejection_type === 'overall') return true;
                  const secs = rejectionInfo.rejected_sections || [];
                  return secs.some((s) => SECTION_TO_STEP_MAP[s] === step.id);
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
                "{rejectionInfo.reason || rejectionInfo.comments || 'Please review the flagged sections marked with red exclamation above, correct the details, and resubmit.'}"
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
            {/* ================= STEP 1: ATM INFORMATION ================= */}
            {currentStep === 1 && (
              <View style={styles.stepContainer}>
                <View style={styles.stepBanner}>
                  <Ionicons name="card" size={24} color={COLORS.primary} />
                  <View style={{ marginLeft: 10, flex: 1 }}>
                    <Text style={styles.stepBannerTitle}>ATM Information</Text>
                    <Text style={styles.stepBannerSub}>Specify number of ATMs and operational status</Text>
                  </View>
                </View>

                {/* Master Site Read-only Info */}
                <View style={styles.readOnlyInfoCard}>
                  <Text style={styles.readOnlyCardTitle}>Master Site Info</Text>
                  <View style={styles.readOnlyGrid}>
                    <View style={styles.readOnlyItem}>
                      <Text style={styles.readOnlyLabel}>Bank Name</Text>
                      <Text style={styles.readOnlyVal}>{site?.bank_name || 'N/A'}</Text>
                    </View>
                    <View style={styles.readOnlyItem}>
                      <Text style={styles.readOnlyLabel}>Customer</Text>
                      <Text style={styles.readOnlyVal}>{site?.customer_name || 'N/A'}</Text>
                    </View>
                    <View style={styles.readOnlyItemFull}>
                      <Text style={styles.readOnlyLabel}>Address</Text>
                      <Text style={styles.readOnlyVal}>{site?.address || site?.city || 'N/A'}</Text>
                    </View>
                  </View>
                </View>

                {/* Number of ATMs */}
                {renderRadioGroup(
                  'no_of_atm',
                  [
                    { label: '0 ATMs', value: '0' },
                    { label: '1 ATM', value: '1' },
                    { label: '2 ATMs', value: '2' },
                    { label: '3 ATMs', value: '3' },
                  ],
                  'Number of ATMs',
                  true
                )}

                {/* ATM 1 Details */}
                {parseInt(form.no_of_atm, 10) >= 1 && (
                  <View style={styles.subCard}>
                    <Text style={styles.subCardTitle}>ATM 1 Details</Text>
                    <View style={styles.fieldGroup}>
                      <Text style={styles.inputLabel}>ATM 1 ID</Text>
                      <TextInput
                        style={styles.input}
                        placeholder="e.g. S1AC00112"
                        value={form.atm_id_1}
                        onChangeText={(val) => updateFormField('atm_id_1', val)}
                      />
                    </View>
                    {renderRadioGroup(
                      'atm_1_status',
                      [
                        { label: 'Working', value: 'working' },
                        { label: 'Not Working', value: 'not_working' },
                        { label: 'Maintenance', value: 'maintenance' },
                      ],
                      'ATM 1 Status'
                    )}
                  </View>
                )}

                {/* ATM 2 Details */}
                {parseInt(form.no_of_atm, 10) >= 2 && (
                  <View style={styles.subCard}>
                    <Text style={styles.subCardTitle}>ATM 2 Details</Text>
                    <View style={styles.fieldGroup}>
                      <Text style={styles.inputLabel}>ATM 2 ID</Text>
                      <TextInput
                        style={styles.input}
                        placeholder="e.g. S1AC00113"
                        value={form.atm_id_2}
                        onChangeText={(val) => updateFormField('atm_id_2', val)}
                      />
                    </View>
                    {renderRadioGroup(
                      'atm_2_status',
                      [
                        { label: 'Working', value: 'working' },
                        { label: 'Not Working', value: 'not_working' },
                        { label: 'Maintenance', value: 'maintenance' },
                      ],
                      'ATM 2 Status'
                    )}
                  </View>
                )}

                {/* ATM 3 Details */}
                {parseInt(form.no_of_atm, 10) >= 3 && (
                  <View style={styles.subCard}>
                    <Text style={styles.subCardTitle}>ATM 3 Details</Text>
                    <View style={styles.fieldGroup}>
                      <Text style={styles.inputLabel}>ATM 3 ID</Text>
                      <TextInput
                        style={styles.input}
                        placeholder="e.g. S1AC00114"
                        value={form.atm_id_3}
                        onChangeText={(val) => updateFormField('atm_id_3', val)}
                      />
                    </View>
                    {renderRadioGroup(
                      'atm_3_status',
                      [
                        { label: 'Working', value: 'working' },
                        { label: 'Not Working', value: 'not_working' },
                        { label: 'Maintenance', value: 'maintenance' },
                      ],
                      'ATM 3 Status'
                    )}
                  </View>
                )}
              </View>
            )}

            {/* ================= STEP 2: NETWORK INFORMATION ================= */}
            {currentStep === 2 && (
              <View style={styles.stepContainer}>
                <View style={styles.stepBanner}>
                  <Ionicons name="wifi" size={24} color={COLORS.primary} />
                  <View style={{ marginLeft: 10, flex: 1 }}>
                    <Text style={styles.stepBannerTitle}>Network Information</Text>
                    <Text style={styles.stepBannerSub}>Signal quality and connectivity operators</Text>
                  </View>
                </View>

                {/* Primary Operator */}
                {renderRadioGroup(
                  'operator',
                  [
                    { label: 'Airtel', value: 'Airtel' },
                    { label: 'Vodafone', value: 'Vodafone' },
                    { label: 'Jio', value: 'Jio' },
                    { label: 'BSNL', value: 'BSNL' },
                  ],
                  'Primary Network Operator',
                  true
                )}

                {/* Primary Signal Status */}
                {renderRadioGroup(
                  'signal_status',
                  [
                    { label: 'Good', value: 'good' },
                    { label: 'Average', value: 'average' },
                    { label: 'Poor', value: 'poor' },
                  ],
                  'Primary Signal Status',
                  true
                )}

                {/* Secondary Operator (Optional) */}
                {renderRadioGroup(
                  'operator_2',
                  [
                    { label: 'None', value: '' },
                    { label: 'Airtel', value: 'Airtel' },
                    { label: 'Vodafone', value: 'Vodafone' },
                    { label: 'Jio', value: 'Jio' },
                    { label: 'BSNL', value: 'BSNL' },
                  ],
                  'Secondary Network Operator (Optional)'
                )}

                {form.operator_2 ? (
                  renderRadioGroup(
                    'signal_status_2',
                    [
                      { label: 'Good', value: 'good' },
                      { label: 'Average', value: 'average' },
                      { label: 'Poor', value: 'poor' },
                    ],
                    'Secondary Signal Status'
                  )
                ) : null}

                {/* Backroom Network Remark */}
                <View style={styles.fieldGroup}>
                  <Text style={styles.inputLabel}>Backroom Network Remark</Text>
                  <TextInput
                    style={[styles.input, styles.textArea]}
                    placeholder="e.g. Signal drops inside the backroom..."
                    value={form.backroom_network_remark}
                    onChangeText={(val) => updateFormField('backroom_network_remark', val)}
                    multiline
                  />
                </View>

                {/* Photo: Backroom Network Snap */}
                {renderPhotoCard(
                  'backroom_network_snap',
                  'Backroom Network Photo',
                  'Clear photo showing signal reception / antennas in the backroom'
                )}
              </View>
            )}

            {/* ================= STEP 3: POWER & UPS ================= */}
            {currentStep === 3 && (
              <View style={styles.stepContainer}>
                <View style={styles.stepBanner}>
                  <Ionicons name="flash" size={24} color={COLORS.primary} />
                  <View style={{ marginLeft: 10, flex: 1 }}>
                    <Text style={styles.stepBannerTitle}>Power & UPS Infrastructure</Text>
                    <Text style={styles.stepBannerSub}>UPS availability, battery backup, and sockets</Text>
                  </View>
                </View>

                {/* UPS Available */}
                {renderRadioGroup(
                  'ups_available',
                  [
                    { label: 'Yes', value: 'yes' },
                    { label: 'No', value: 'no' },
                  ],
                  'Is UPS Available?',
                  true
                )}

                {form.ups_available === 'yes' && (
                  <>
                    {/* No of UPS */}
                    {renderRadioGroup(
                      'no_of_ups',
                      [
                        { label: '1 UPS', value: '1' },
                        { label: '2 UPS', value: '2' },
                        { label: '3 UPS', value: '3' },
                      ],
                      'Number of UPS Units'
                    )}

                    {/* UPS Battery Backup */}
                    {renderRadioGroup(
                      'ups_battery_backup',
                      [
                        { label: 'Under 30m', value: 'under_30min' },
                        { label: '30m - 1h', value: '30min_to_1hr' },
                        { label: '1h - 2h', value: '1hr_to_2hr' },
                        { label: '2h - 4h', value: '2hr_to_4hr' },
                        { label: '4h+', value: 'more_than_4hr' },
                      ],
                      'Estimated Battery Backup'
                    )}

                    {/* UPS Working Statuses */}
                    <View style={styles.subCard}>
                      <Text style={styles.subCardTitle}>UPS Working Condition</Text>
                      {renderRadioGroup(
                        'ups_working_1',
                        [
                          { label: 'Working', value: 'yes' },
                          { label: 'Faulty', value: 'no' },
                        ],
                        'UPS Unit 1'
                      )}
                      {parseInt(form.no_of_ups, 10) >= 2 &&
                        renderRadioGroup(
                          'ups_working_2',
                          [
                            { label: 'Working', value: 'yes' },
                            { label: 'Faulty', value: 'no' },
                          ],
                          'UPS Unit 2'
                        )}
                      {parseInt(form.no_of_ups, 10) >= 3 &&
                        renderRadioGroup(
                          'ups_working_3',
                          [
                            { label: 'Working', value: 'yes' },
                            { label: 'Faulty', value: 'no' },
                          ],
                          'UPS Unit 3'
                        )}
                    </View>
                  </>
                )}

                {/* Power Sockets */}
                <View style={styles.subCard}>
                  <Text style={styles.subCardTitle}>Power Sockets</Text>
                  {renderRadioGroup(
                    'power_socket_availability',
                    [
                      { label: 'Available', value: 'available' },
                      { label: 'Not Available', value: 'not_available' },
                    ],
                    'Raw Power Socket'
                  )}
                  {renderRadioGroup(
                    'power_socket_availability_ups',
                    [
                      { label: 'Available', value: 'available' },
                      { label: 'Not Available', value: 'not_available' },
                    ],
                    'UPS Power Socket'
                  )}
                </View>

                {/* Photo: UPS Available Snap */}
                {renderPhotoCard(
                  'ups_available_snap',
                  'UPS & Power Photo',
                  'Photo of UPS units, battery bank, and available power sockets'
                )}
              </View>
            )}

            {/* ================= STEP 4: ELECTRICAL MEASUREMENTS ================= */}
            {currentStep === 4 && (
              <View style={styles.stepContainer}>
                <View style={styles.stepBanner}>
                  <Ionicons name="git-network" size={24} color={COLORS.primary} />
                  <View style={{ marginLeft: 10, flex: 1 }}>
                    <Text style={styles.stepBannerTitle}>Electrical Measurements</Text>
                    <Text style={styles.stepBannerSub}>Earthing, voltage fluctuations, and power cuts</Text>
                  </View>
                </View>

                {/* Earthing Status */}
                {renderRadioGroup(
                  'earthing',
                  [
                    { label: 'Yes', value: 'yes' },
                    { label: 'No', value: 'no' },
                  ],
                  'Earthing Connected?',
                  true
                )}

                {/* Voltage Measurements Input Grid */}
                <View style={styles.subCard}>
                  <Text style={styles.subCardTitle}>Multimeter Voltage Readings (Volts)</Text>
                  
                  <View style={styles.fieldGroup}>
                    <Text style={styles.inputLabel}>Earthing Voltage (Neutral - Earth / E-N)</Text>
                    <TextInput
                      style={styles.input}
                      placeholder="e.g. 2.1"
                      keyboardType="numeric"
                      value={form.earthing_voltage}
                      onChangeText={(val) => updateFormField('earthing_voltage', val)}
                    />
                  </View>

                  <View style={styles.twoColRow}>
                    <View style={[styles.fieldGroup, { flex: 1 }]}>
                      <Text style={styles.inputLabel}>Phase - Earth (P-E)</Text>
                      <TextInput
                        style={styles.input}
                        placeholder="e.g. 230"
                        keyboardType="numeric"
                        value={form.power_fluctuation_pe}
                        onChangeText={(val) => updateFormField('power_fluctuation_pe', val)}
                      />
                    </View>
                    <View style={[styles.fieldGroup, { flex: 1 }]}>
                      <Text style={styles.inputLabel}>Phase - Neutral (P-N)</Text>
                      <TextInput
                        style={styles.input}
                        placeholder="e.g. 230"
                        keyboardType="numeric"
                        value={form.power_fluctuation_pn}
                        onChangeText={(val) => updateFormField('power_fluctuation_pn', val)}
                      />
                    </View>
                  </View>

                  <View style={styles.fieldGroup}>
                    <Text style={styles.inputLabel}>Earth - Neutral (E-N Fluctuations)</Text>
                    <TextInput
                      style={styles.input}
                      placeholder="e.g. 1.5"
                      keyboardType="numeric"
                      value={form.power_fluctuation_en}
                      onChangeText={(val) => updateFormField('power_fluctuation_en', val)}
                    />
                  </View>
                </View>

                {/* Frequent Power Cut */}
                <View style={styles.subCard}>
                  <Text style={styles.subCardTitle}>Power Cut History</Text>
                  {renderRadioGroup(
                    'frequent_power_cut',
                    [
                      { label: 'Yes', value: 'yes' },
                      { label: 'No', value: 'no' },
                    ],
                    'Frequent Power Cuts in Area?'
                  )}

                  {form.frequent_power_cut === 'yes' && (
                    <>
                      <View style={styles.twoColRow}>
                        <View style={[styles.fieldGroup, { flex: 1 }]}>
                          <Text style={styles.inputLabel}>Cut From (Time)</Text>
                          <TextInput
                            style={styles.input}
                            placeholder="e.g. 14:00"
                            value={form.frequent_power_cut_from}
                            onChangeText={(val) => updateFormField('frequent_power_cut_from', val)}
                          />
                        </View>
                        <View style={[styles.fieldGroup, { flex: 1 }]}>
                          <Text style={styles.inputLabel}>Cut To (Time)</Text>
                          <TextInput
                            style={styles.input}
                            placeholder="e.g. 17:00"
                            value={form.frequent_power_cut_to}
                            onChangeText={(val) => updateFormField('frequent_power_cut_to', val)}
                          />
                        </View>
                      </View>
                      <View style={styles.fieldGroup}>
                        <Text style={styles.inputLabel}>Power Cut Remarks</Text>
                        <TextInput
                          style={[styles.input, styles.textArea]}
                          placeholder="e.g. Load shedding every Tuesday..."
                          value={form.frequent_power_cut_remark}
                          onChangeText={(val) => updateFormField('frequent_power_cut_remark', val)}
                          multiline
                        />
                      </View>
                    </>
                  )}
                </View>

                {/* Photo: Earthing / Multimeter Snap */}
                {renderPhotoCard(
                  'earthing_snap',
                  'Earthing & Multimeter Photo',
                  'Photo of multimeter showing voltage reading across Phase/Neutral/Earth'
                )}
              </View>
            )}

            {/* ================= STEP 5: SITE ACCESS & ENVIRONMENT ================= */}
            {currentStep === 5 && (
              <View style={styles.stepContainer}>
                <View style={styles.stepBanner}>
                  <Ionicons name="key" size={24} color={COLORS.primary} />
                  <View style={{ marginLeft: 10, flex: 1 }}>
                    <Text style={styles.stepBannerTitle}>Site Access & Environment</Text>
                    <Text style={styles.stepBannerSub}>Locks, keys, router position, and surrounding area</Text>
                  </View>
                </View>

                {/* EM Lock */}
                <View style={styles.subCard}>
                  <Text style={styles.subCardTitle}>Electronic / EM Lock</Text>
                  {renderRadioGroup(
                    'em_lock_available',
                    [
                      { label: 'Yes', value: 'yes' },
                      { label: 'No', value: 'no' },
                    ],
                    'EM Lock Available?'
                  )}

                  {form.em_lock_available === 'yes' && (
                    <>
                      {renderRadioGroup(
                        'password_received',
                        [
                          { label: 'Yes', value: 'yes' },
                          { label: 'No', value: 'no' },
                        ],
                        'EM Lock Password Received?'
                      )}
                      <View style={styles.fieldGroup}>
                        <Text style={styles.inputLabel}>EM Lock Password / Code</Text>
                        <TextInput
                          style={styles.input}
                          placeholder="e.g. 1234#"
                          value={form.em_lock_password}
                          onChangeText={(val) => updateFormField('em_lock_password', val)}
                        />
                      </View>
                    </>
                  )}
                </View>

                {/* Backroom Key Info */}
                <View style={styles.subCard}>
                  <Text style={styles.subCardTitle}>Backroom Key Information</Text>
                  {renderRadioGroup(
                    'backroom_key_status',
                    [
                      { label: 'Available', value: 'available' },
                      { label: 'With Guard', value: 'with_guard' },
                      { label: 'With Branch', value: 'with_branch' },
                      { label: 'Not Available', value: 'not_available' },
                    ],
                    'Key Status'
                  )}
                  <View style={styles.twoColRow}>
                    <View style={[styles.fieldGroup, { flex: 1 }]}>
                      <Text style={styles.inputLabel}>Contact Person</Text>
                      <TextInput
                        style={styles.input}
                        placeholder="e.g. Ramesh Kumar"
                        value={form.backroom_key_name}
                        onChangeText={(val) => updateFormField('backroom_key_name', val)}
                      />
                    </View>
                    <View style={[styles.fieldGroup, { flex: 1 }]}>
                      <Text style={styles.inputLabel}>Contact Number</Text>
                      <TextInput
                        style={styles.input}
                        placeholder="e.g. 9876543210"
                        keyboardType="phone-pad"
                        value={form.backroom_key_number}
                        onChangeText={(val) => updateFormField('backroom_key_number', val)}
                      />
                    </View>
                  </View>
                </View>

                {/* Router & Antenna Placement */}
                <View style={styles.subCard}>
                  <Text style={styles.subCardTitle}>Router & Antenna Placement</Text>
                  <View style={styles.fieldGroup}>
                    <Text style={styles.inputLabel}>Router Placement Position</Text>
                    <TextInput
                      style={styles.input}
                      placeholder="e.g. Top shelf of ATM backroom rack"
                      value={form.router_position}
                      onChangeText={(val) => updateFormField('router_position', val)}
                    />
                  </View>
                  <View style={styles.fieldGroup}>
                    <Text style={styles.inputLabel}>Antenna Position</Text>
                    <TextInput
                      style={styles.input}
                      placeholder="e.g. Outside backroom window, South-facing"
                      value={form.router_antenna_position}
                      onChangeText={(val) => updateFormField('router_antenna_position', val)}
                    />
                  </View>
                  <View style={styles.fieldGroup}>
                    <Text style={styles.inputLabel}>Antenna Cable Routing Detail</Text>
                    <TextInput
                      style={[styles.input, styles.textArea]}
                      placeholder="e.g. 5m cable routed through existing AC conduit"
                      value={form.antenna_routing_detail}
                      onChangeText={(val) => updateFormField('antenna_routing_detail', val)}
                      multiline
                    />
                  </View>
                </View>

                {/* Nearest Shop / Emergency Contact */}
                <View style={styles.subCard}>
                  <Text style={styles.subCardTitle}>Nearest Shop / Landmark</Text>
                  <View style={styles.fieldGroup}>
                    <Text style={styles.inputLabel}>Shop Name</Text>
                    <TextInput
                      style={styles.input}
                      placeholder="e.g. Gupta General Store"
                      value={form.nearest_shop_name}
                      onChangeText={(val) => updateFormField('nearest_shop_name', val)}
                    />
                  </View>
                  <View style={styles.twoColRow}>
                    <View style={[styles.fieldGroup, { flex: 1 }]}>
                      <Text style={styles.inputLabel}>Shop Contact</Text>
                      <TextInput
                        style={styles.input}
                        placeholder="Phone Number"
                        keyboardType="phone-pad"
                        value={form.nearest_shop_number}
                        onChangeText={(val) => updateFormField('nearest_shop_number', val)}
                      />
                    </View>
                    <View style={[styles.fieldGroup, { flex: 1 }]}>
                      <Text style={styles.inputLabel}>Distance (Meters)</Text>
                      <TextInput
                        style={styles.input}
                        placeholder="e.g. 20"
                        keyboardType="numeric"
                        value={form.nearest_shop_distance}
                        onChangeText={(val) => updateFormField('nearest_shop_distance', val)}
                      />
                    </View>
                  </View>
                </View>

                {/* Disturbing Materials in Backroom */}
                <View style={styles.subCard}>
                  <Text style={styles.subCardTitle}>Environment / Obstacles</Text>
                  {renderRadioGroup(
                    'backroom_disturbing_material',
                    [
                      { label: 'Yes', value: 'yes' },
                      { label: 'No', value: 'no' },
                    ],
                    'Disturbing / Hazardous Material in Backroom?'
                  )}
                  {form.backroom_disturbing_material === 'yes' && (
                    <View style={styles.fieldGroup}>
                      <Text style={styles.inputLabel}>Hazardous Material Remark</Text>
                      <TextInput
                        style={[styles.input, styles.textArea]}
                        placeholder="e.g. Water leakage, loose exposed cables..."
                        value={form.backroom_disturbing_material_remark}
                        onChangeText={(val) => updateFormField('backroom_disturbing_material_remark', val)}
                        multiline
                      />
                    </View>
                  )}
                </View>

                {/* Photos: Router & Antenna Snaps */}
                {renderPhotoCard(
                  'router_antenna_snap',
                  'Router & Antenna Photo',
                  'Photo showing proposed placement location for router & antenna'
                )}

                {renderPhotoCard(
                  'antenna_routing_snap',
                  'Antenna Routing Pathway Photo',
                  'Photo showing cable path from antenna location to router inside backroom'
                )}
              </View>
            )}

            {/* ================= STEP 6: REMARKS & REVIEW ================= */}
            {currentStep === 6 && (
              <View style={styles.stepContainer}>
                <View style={styles.stepBanner}>
                  <Ionicons name="checkmark-done-circle" size={24} color={COLORS.success} />
                  <View style={{ marginLeft: 10, flex: 1 }}>
                    <Text style={styles.stepBannerTitle}>Review & Submit</Text>
                    <Text style={styles.stepBannerSub}>Final remarks and inspection summary</Text>
                  </View>
                </View>

                {/* Final Remarks Field */}
                <View style={styles.fieldGroup}>
                  <Text style={styles.inputLabel}>Engineer Final Inspection Remarks</Text>
                  <TextInput
                    style={[styles.input, { height: 100, textAlignVertical: 'top' }]}
                    placeholder="Provide any additional comments, special instructions, or installation notes..."
                    value={form.remarks}
                    onChangeText={(val) => updateFormField('remarks', val)}
                    multiline
                    maxLength={2000}
                  />
                  <Text style={styles.charCount}>{(form.remarks || '').length} / 2000</Text>
                </View>

                {/* Photo: Remarks Snap */}
                {renderPhotoCard(
                  'remarks_snap',
                  'Overall Site / Additional Snapshot',
                  'Optional wide shot of the ATM lobby or any noteworthy observation'
                )}

                {/* Comprehensive Section-by-Section Summary Card */}
                <View style={styles.summaryCard}>
                  <Text style={styles.summaryTitle}>Inspection Summary</Text>

                  {/* Step 1 Item */}
                  <TouchableOpacity
                    style={styles.summaryItemRow}
                    onPress={() => setCurrentStep(1)}
                  >
                    <View style={styles.summaryIconBox}>
                      <Ionicons name="card-outline" size={18} color={COLORS.primary} />
                    </View>
                    <View style={{ flex: 1 }}>
                      <Text style={styles.summaryItemLabel}>ATM Information</Text>
                      <Text style={styles.summaryItemVal}>
                        {form.no_of_atm} ATM(s) • ID: {form.atm_id_1 || 'None'} ({form.atm_1_status})
                      </Text>
                    </View>
                    <Text style={styles.summaryEditLink}>Edit</Text>
                  </TouchableOpacity>

                  {/* Step 2 Item */}
                  <TouchableOpacity
                    style={styles.summaryItemRow}
                    onPress={() => setCurrentStep(2)}
                  >
                    <View style={styles.summaryIconBox}>
                      <Ionicons name="wifi-outline" size={18} color={COLORS.primary} />
                    </View>
                    <View style={{ flex: 1 }}>
                      <Text style={styles.summaryItemLabel}>Network Infrastructure</Text>
                      <Text style={styles.summaryItemVal}>
                        {form.operator} ({form.signal_status?.toUpperCase()})
                        {form.operator_2 ? ` • ${form.operator_2}` : ''}
                      </Text>
                    </View>
                    <Text style={styles.summaryEditLink}>Edit</Text>
                  </TouchableOpacity>

                  {/* Step 3 Item */}
                  <TouchableOpacity
                    style={styles.summaryItemRow}
                    onPress={() => setCurrentStep(3)}
                  >
                    <View style={styles.summaryIconBox}>
                      <Ionicons name="flash-outline" size={18} color={COLORS.primary} />
                    </View>
                    <View style={{ flex: 1 }}>
                      <Text style={styles.summaryItemLabel}>Power & UPS</Text>
                      <Text style={styles.summaryItemVal}>
                        UPS: {form.ups_available?.toUpperCase()} • Socket: {form.power_socket_availability}
                      </Text>
                    </View>
                    <Text style={styles.summaryEditLink}>Edit</Text>
                  </TouchableOpacity>

                  {/* Step 4 Item */}
                  <TouchableOpacity
                    style={styles.summaryItemRow}
                    onPress={() => setCurrentStep(4)}
                  >
                    <View style={styles.summaryIconBox}>
                      <Ionicons name="git-network-outline" size={18} color={COLORS.primary} />
                    </View>
                    <View style={{ flex: 1 }}>
                      <Text style={styles.summaryItemLabel}>Electrical Measurements</Text>
                      <Text style={styles.summaryItemVal}>
                        Earthing: {form.earthing?.toUpperCase()} • {form.earthing_voltage || 'No voltage entered'}
                      </Text>
                    </View>
                    <Text style={styles.summaryEditLink}>Edit</Text>
                  </TouchableOpacity>

                  {/* Step 5 Item */}
                  <TouchableOpacity
                    style={styles.summaryItemRow}
                    onPress={() => setCurrentStep(5)}
                  >
                    <View style={styles.summaryIconBox}>
                      <Ionicons name="key-outline" size={18} color={COLORS.primary} />
                    </View>
                    <View style={{ flex: 1 }}>
                      <Text style={styles.summaryItemLabel}>Site Access & Environment</Text>
                      <Text style={styles.summaryItemVal}>
                        Key: {form.backroom_key_status} • EM Lock: {form.em_lock_available}
                      </Text>
                    </View>
                    <Text style={styles.summaryEditLink}>Edit</Text>
                  </TouchableOpacity>
                </View>

                {/* Ready to Submit Banner */}
                <View style={styles.readyBanner}>
                  <Ionicons name="information-circle" size={20} color={COLORS.primary} />
                  <Text style={styles.readyBannerText}>
                    All 6 inspection steps completed. Click "Submit Feasibility Check" to transmit your assessment.
                  </Text>
                </View>
              </View>
            )}
          </ScrollView>

          {/* Sticky Bottom Navigation Bar for Form Mode */}
          <View style={styles.bottomBar}>
            {/* Previous Button */}
            {currentStep > 1 ? (
              <TouchableOpacity
                style={styles.prevButton}
                onPress={handlePrevStep}
                disabled={isSubmitting}
              >
                <Ionicons name="chevron-back" size={18} color={COLORS.textPrimary} />
                <Text style={styles.prevButtonText}>Prev</Text>
              </TouchableOpacity>
            ) : (
              <View style={{ width: 70 }} />
            )}

            {/* Save Draft Button */}
            <TouchableOpacity
              style={styles.saveDraftBarBtn}
              onPress={() => saveDraft(true)}
              disabled={isSavingDraft || isSubmitting}
            >
              {isSavingDraft ? (
                <ActivityIndicator size="small" color={COLORS.primary} />
              ) : (
                <>
                  <Ionicons name="bookmark-outline" size={16} color={COLORS.primary} />
                  <Text style={styles.saveDraftBarText}>Save Draft</Text>
                </>
              )}
            </TouchableOpacity>

            {/* Next / Submit Button */}
            {currentStep < 6 ? (
              <TouchableOpacity
                style={styles.nextButton}
                onPress={handleNextStep}
                disabled={isSubmitting}
              >
                <Text style={styles.nextButtonText}>Next</Text>
                <Ionicons name="chevron-forward" size={18} color="#fff" />
              </TouchableOpacity>
            ) : (
              <TouchableOpacity
                style={[
                  styles.submitFinalButton,
                  rejectionInfo && rejectionInfo.is_rejected && { backgroundColor: COLORS.danger },
                ]}
                onPress={handleSubmitFinal}
                disabled={isSubmitting}
              >
                {isSubmitting ? (
                  <ActivityIndicator size="small" color="#fff" />
                ) : (
                  <>
                    <Ionicons
                      name={rejectionInfo && rejectionInfo.is_rejected ? "refresh-circle" : "checkmark-circle"}
                      size={18}
                      color="#fff"
                    />
                    <Text style={styles.submitFinalButtonText}>
                      {rejectionInfo && rejectionInfo.is_rejected ? 'Resubmit Survey' : 'Submit Check'}
                    </Text>
                  </>
                )}
              </TouchableOpacity>
            )}
          </View>
        </>
      )}

      {/* Zoomable Image Modal with Pinch-to-zoom, Pan, Double-tap, and Controls */}
      <ZoomableImageModal
        visible={!!selectedPhotoPreview}
        imageUri={
          selectedPhotoPreview
            ? typeof selectedPhotoPreview === 'object'
              ? selectedPhotoPreview.uri
              : resolveImageUrl(selectedPhotoPreview)
            : null
        }
        title={
          selectedPhotoPreview && typeof selectedPhotoPreview === 'object'
            ? selectedPhotoPreview.title
            : 'Photo Preview'
        }
        onClose={() => setSelectedPhotoPreview(null)}
      />
    </SafeAreaView>
  );
};

const styles = StyleSheet.create({
  safeArea: {
    flex: 1,
    backgroundColor: COLORS.background,
  },
  draftTopBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
    paddingHorizontal: 10,
    paddingVertical: 6,
    borderRadius: 8,
    backgroundColor: `${COLORS.primary}15`,
  },
  draftTopBtnText: {
    color: COLORS.primary,
    fontWeight: '700',
    fontSize: 12,
  },
  headerCard: {
    backgroundColor: COLORS.cardBg,
    paddingHorizontal: SPACING.md,
    paddingTop: SPACING.sm,
    paddingBottom: SPACING.xs,
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
    fontSize: FONTS.small,
    color: COLORS.textSecondary,
    fontWeight: '600',
  },
  stepTitleText: {
    fontSize: FONTS.body,
    color: COLORS.textPrimary,
    fontWeight: '800',
  },
  stepPercentText: {
    fontSize: FONTS.small,
    fontWeight: '700',
    color: COLORS.primary,
  },
  progressBarTrack: {
    height: 6,
    backgroundColor: '#e2e8f0',
    borderRadius: 3,
    overflow: 'hidden',
    marginBottom: SPACING.sm,
  },
  progressBarFill: {
    height: '100%',
    backgroundColor: COLORS.primary,
    borderRadius: 3,
  },
  stepperContainer: {
    flexDirection: 'row',
    gap: 8,
    paddingBottom: 4,
  },
  stepChip: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 10,
    paddingVertical: 6,
    borderRadius: 20,
    backgroundColor: COLORS.background,
    borderWidth: 1,
    borderColor: COLORS.border,
    gap: 6,
  },
  stepChipCurrent: {
    backgroundColor: `${COLORS.primary}15`,
    borderColor: COLORS.primary,
  },
  stepChipCompleted: {
    backgroundColor: `${COLORS.success}15`,
    borderColor: COLORS.success,
  },
  stepChipCircle: {
    width: 20,
    height: 20,
    borderRadius: 10,
    backgroundColor: '#cbd5e1',
    alignItems: 'center',
    justifyContent: 'center',
  },
  stepChipCircleCurrent: {
    backgroundColor: COLORS.primary,
  },
  stepChipCircleCompleted: {
    backgroundColor: COLORS.success,
  },
  stepChipText: {
    fontSize: 11,
    fontWeight: '600',
    color: COLORS.textSecondary,
  },
  stepChipTextCurrent: {
    color: COLORS.primary,
    fontWeight: '800',
  },
  stepChipTextCompleted: {
    color: COLORS.success,
    fontWeight: '700',
  },
  draftNoticeBanner: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 6,
    backgroundColor: `${COLORS.success}10`,
    paddingVertical: 4,
    borderBottomWidth: 1,
    borderBottomColor: `${COLORS.success}25`,
  },
  draftNoticeText: {
    fontSize: 11,
    fontWeight: '600',
    color: COLORS.success,
  },
  scrollContent: {
    padding: SPACING.md,
    paddingBottom: 100, // accommodate bottom navigation bar
  },
  loaderBox: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
  },
  loaderText: {
    marginTop: SPACING.sm,
    color: COLORS.textSecondary,
    fontSize: FONTS.body,
  },
  stepContainer: {
    gap: SPACING.md,
  },
  stepBanner: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: `${COLORS.primary}08`,
    padding: SPACING.md,
    borderRadius: 12,
    borderLeftWidth: 4,
    borderLeftColor: COLORS.primary,
  },
  stepBannerTitle: {
    fontSize: FONTS.subtitle,
    fontWeight: '800',
    color: COLORS.textPrimary,
  },
  stepBannerSub: {
    fontSize: FONTS.small,
    color: COLORS.textSecondary,
    marginTop: 2,
  },
  readOnlyInfoCard: {
    backgroundColor: COLORS.cardBg,
    borderRadius: 12,
    padding: SPACING.md,
    borderWidth: 1,
    borderColor: COLORS.border,
  },
  readOnlyCardTitle: {
    fontSize: 12,
    fontWeight: '700',
    color: COLORS.textSecondary,
    textTransform: 'uppercase',
    marginBottom: SPACING.sm,
  },
  readOnlyGrid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 8,
  },
  readOnlyItem: {
    width: '48%',
  },
  readOnlyItemFull: {
    width: '100%',
    marginTop: 4,
  },
  readOnlyLabel: {
    fontSize: 10,
    color: COLORS.textMuted,
  },
  readOnlyVal: {
    fontSize: 12,
    fontWeight: '700',
    color: COLORS.textPrimary,
  },
  subCard: {
    backgroundColor: COLORS.cardBg,
    borderRadius: 14,
    padding: SPACING.md,
    borderWidth: 1,
    borderColor: COLORS.border,
    gap: SPACING.sm,
  },
  subCardTitle: {
    fontSize: FONTS.body,
    fontWeight: '700',
    color: COLORS.textPrimary,
    marginBottom: 4,
  },
  fieldGroup: {
    marginBottom: 4,
  },
  inputLabel: {
    fontSize: FONTS.small,
    fontWeight: '600',
    color: COLORS.textPrimary,
    marginBottom: 6,
  },
  input: {
    backgroundColor: COLORS.inputBg,
    borderWidth: 1,
    borderColor: COLORS.border,
    borderRadius: 10,
    paddingHorizontal: 12,
    paddingVertical: 10,
    fontSize: FONTS.body,
    color: COLORS.textPrimary,
  },
  textArea: {
    height: 75,
    textAlignVertical: 'top',
  },
  twoColRow: {
    flexDirection: 'row',
    gap: 10,
  },
  radioRow: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 8,
  },
  radioPill: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 14,
    paddingVertical: 8,
    borderRadius: 8,
    backgroundColor: COLORS.background,
    borderWidth: 1,
    borderColor: COLORS.border,
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
    fontWeight: '700',
  },
  photoCard: {
    backgroundColor: COLORS.cardBg,
    borderRadius: 14,
    padding: SPACING.md,
    borderWidth: 1,
    borderColor: COLORS.border,
    marginTop: 4,
  },
  photoCardHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
    marginBottom: SPACING.sm,
  },
  photoCardTitle: {
    fontSize: FONTS.body,
    fontWeight: '700',
    color: COLORS.textPrimary,
  },
  photoCardDesc: {
    fontSize: FONTS.small,
    color: COLORS.textSecondary,
    marginTop: 2,
  },
  compressionBadge: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 3,
    backgroundColor: `${COLORS.primary}12`,
    paddingHorizontal: 6,
    paddingVertical: 3,
    borderRadius: 6,
  },
  compressionBadgeText: {
    fontSize: 10,
    fontWeight: '700',
    color: COLORS.primary,
  },
  photoPreviewBox: {
    alignItems: 'center',
    backgroundColor: '#0f172a08',
    borderRadius: 10,
    padding: 8,
  },
  photoThumbnail: {
    width: 200,
    height: 140,
    borderRadius: 8,
  },
  photoOverlayHint: {
    position: 'absolute',
    bottom: 6,
    left: 6,
    backgroundColor: 'rgba(0,0,0,0.6)',
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
    paddingHorizontal: 6,
    paddingVertical: 2,
    borderRadius: 4,
  },
  photoOverlayText: {
    fontSize: 10,
    color: '#fff',
    fontWeight: '600',
  },
  photoActionRow: {
    flexDirection: 'row',
    gap: 16,
    marginTop: 8,
  },
  retakeBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
    paddingVertical: 4,
    paddingHorizontal: 8,
  },
  retakeBtnText: {
    color: COLORS.primary,
    fontSize: 12,
    fontWeight: '700',
  },
  removePhotoBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
    paddingVertical: 4,
    paddingHorizontal: 8,
  },
  removePhotoText: {
    color: COLORS.danger,
    fontSize: 12,
    fontWeight: '700',
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
    gap: 6,
    backgroundColor: COLORS.primary,
    paddingVertical: 10,
    borderRadius: 10,
  },
  captureBtnText: {
    color: '#fff',
    fontWeight: '700',
    fontSize: 12,
  },
  galleryBtn: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 6,
    backgroundColor: COLORS.background,
    borderWidth: 1,
    borderColor: COLORS.border,
    paddingVertical: 10,
    borderRadius: 10,
  },
  galleryBtnText: {
    color: COLORS.textPrimary,
    fontWeight: '700',
    fontSize: 12,
  },
  charCountText: {
    fontSize: 11,
    color: COLORS.textMuted,
    textAlign: 'right',
    marginTop: 4,
  },
  summaryCard: {
    backgroundColor: COLORS.cardBg,
    borderRadius: 14,
    padding: SPACING.md,
    borderWidth: 1,
    borderColor: COLORS.border,
  },
  summaryTitle: {
    fontSize: FONTS.body,
    fontWeight: '800',
    color: COLORS.textPrimary,
    marginBottom: SPACING.sm,
  },
  summaryItemRow: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingVertical: 8,
    borderBottomWidth: 1,
    borderBottomColor: '#f1f5f9',
    gap: 10,
  },
  summaryIconBox: {
    width: 32,
    height: 32,
    borderRadius: 8,
    backgroundColor: `${COLORS.primary}12`,
    alignItems: 'center',
    justifyContent: 'center',
  },
  summaryItemLabel: {
    fontSize: 12,
    fontWeight: '700',
    color: COLORS.textPrimary,
  },
  summaryItemVal: {
    fontSize: 11,
    color: COLORS.textSecondary,
    marginTop: 1,
  },
  summaryEditLink: {
    fontSize: 12,
    fontWeight: '700',
    color: COLORS.primary,
  },
  readyBanner: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    backgroundColor: `${COLORS.primary}10`,
    padding: SPACING.md,
    borderRadius: 12,
    borderWidth: 1,
    borderColor: `${COLORS.primary}30`,
  },
  readyBannerText: {
    flex: 1,
    fontSize: 12,
    color: COLORS.primaryDark || COLORS.primary,
    fontWeight: '600',
    lineHeight: 16,
  },
  bottomBar: {
    position: 'absolute',
    bottom: 0,
    left: 0,
    right: 0,
    backgroundColor: COLORS.cardBg,
    borderTopWidth: 1,
    borderTopColor: COLORS.border,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: SPACING.md,
    paddingVertical: SPACING.sm,
    ...SHADOWS.medium,
  },
  prevButton: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
    paddingHorizontal: 14,
    paddingVertical: 10,
    borderRadius: 10,
    backgroundColor: COLORS.background,
    borderWidth: 1,
    borderColor: COLORS.border,
  },
  prevButtonText: {
    fontSize: 13,
    fontWeight: '700',
    color: COLORS.textPrimary,
  },
  saveDraftBarBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    paddingHorizontal: 14,
    paddingVertical: 10,
    borderRadius: 10,
    backgroundColor: `${COLORS.primary}12`,
  },
  saveDraftBarText: {
    fontSize: 13,
    fontWeight: '700',
    color: COLORS.primary,
  },
  nextButton: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
    backgroundColor: COLORS.primary,
    paddingHorizontal: 20,
    paddingVertical: 10,
    borderRadius: 10,
  },
  nextButtonText: {
    fontSize: 13,
    fontWeight: '700',
    color: '#fff',
  },
  submitFinalButton: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    backgroundColor: COLORS.success,
    paddingHorizontal: 16,
    paddingVertical: 10,
    borderRadius: 10,
  },
  submitFinalButtonText: {
    fontSize: 13,
    fontWeight: '700',
    color: '#fff',
  },
  photoModalBackdrop: {
    flex: 1,
    backgroundColor: 'rgba(0,0,0,0.9)',
    alignItems: 'center',
    justifyContent: 'center',
  },
  photoModalCloseBtn: {
    position: 'absolute',
    top: 40,
    right: 20,
    zIndex: 10,
  },
  photoModalImage: {
    width: '90%',
    height: '75%',
  },
  // Compact Rejection Feedback Styles
  rejectionCard: {
    backgroundColor: COLORS.dangerLight,
    marginHorizontal: SPACING.md,
    marginTop: 6,
    marginBottom: 4,
    paddingHorizontal: 12,
    paddingVertical: 8,
    borderRadius: RADIUS.md,
    borderWidth: 1,
    borderColor: COLORS.dangerBorder,
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
    color: COLORS.dangerForeground,
  },
  rejectionReasonText: {
    fontSize: 11,
    color: COLORS.textPrimary,
    fontStyle: 'italic',
    lineHeight: 15,
  },
  stepChipRejected: {
    borderColor: COLORS.dangerBorder,
    backgroundColor: COLORS.dangerLight,
  },
  stepChipCircleRejected: {
    backgroundColor: COLORS.danger,
  },
  stepChipTextRejected: {
    color: COLORS.dangerForeground,
    fontWeight: '700',
  },
  // Submitted Feasibility Report Styles
  reportScrollContent: {
    padding: SPACING.md,
    paddingBottom: 110,
  },
  reportStatusCard: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    padding: SPACING.md,
    borderRadius: 14,
    borderWidth: 1,
    marginBottom: SPACING.md,
  },
  reportStatusPending: {
    backgroundColor: '#eff6ff',
    borderColor: '#bfdbfe',
  },
  reportStatusApproved: {
    backgroundColor: '#f0fdf4',
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
    fontSize: FONTS.body,
    fontWeight: '800',
    color: COLORS.primary,
  },
  reportStatusSub: {
    fontSize: 12,
    color: COLORS.textSecondary,
    marginTop: 2,
    lineHeight: 16,
  },
  reportTimestamp: {
    fontSize: 11,
    color: COLORS.textMuted,
    marginTop: 4,
    fontWeight: '600',
  },
  reportSectionCard: {
    backgroundColor: COLORS.cardBg,
    borderRadius: 14,
    padding: SPACING.md,
    borderWidth: 1,
    borderColor: COLORS.border,
    marginBottom: SPACING.md,
    gap: SPACING.sm,
  },
  reportSectionHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    paddingBottom: 8,
    borderBottomWidth: 1,
    borderBottomColor: '#f1f5f9',
  },
  reportSectionTitle: {
    fontSize: 14,
    fontWeight: '800',
    color: COLORS.textPrimary,
  },
  reportGrid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 10,
  },
  reportGridItem: {
    width: '47%',
  },
  reportGridItemFull: {
    width: '100%',
    marginTop: 2,
  },
  reportItemLabel: {
    fontSize: 11,
    color: COLORS.textMuted,
    fontWeight: '600',
  },
  reportItemVal: {
    fontSize: 13,
    color: COLORS.textPrimary,
    fontWeight: '700',
    marginTop: 2,
  },
  reportPhotoContainer: {
    marginTop: 8,
    paddingTop: 8,
    borderTopWidth: 1,
    borderTopColor: '#f1f5f9',
  },
  reportPhotoTitle: {
    fontSize: 12,
    fontWeight: '700',
    color: COLORS.textSecondary,
    marginBottom: 6,
  },
  reportPhotoWrapper: {
    width: '100%',
    height: 160,
    borderRadius: 10,
    overflow: 'hidden',
    backgroundColor: '#0f172a08',
    borderWidth: 1,
    borderColor: COLORS.border,
  },
  reportPhotoImg: {
    width: '100%',
    height: '100%',
  },
  reportPhotoBadge: {
    position: 'absolute',
    bottom: 8,
    right: 8,
    backgroundColor: 'rgba(0,0,0,0.65)',
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
    paddingHorizontal: 8,
    paddingVertical: 4,
    borderRadius: 6,
  },
  reportPhotoBadgeText: {
    fontSize: 11,
    color: '#fff',
    fontWeight: '700',
  },
  reportBottomBar: {
    position: 'absolute',
    bottom: 0,
    left: 0,
    right: 0,
    backgroundColor: COLORS.cardBg,
    borderTopWidth: 1,
    borderTopColor: COLORS.border,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: SPACING.md,
    paddingVertical: SPACING.sm,
    ...SHADOWS.medium,
  },
  reportBackBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    paddingHorizontal: 16,
    paddingVertical: 11,
    borderRadius: 10,
    backgroundColor: COLORS.background,
    borderWidth: 1,
    borderColor: COLORS.border,
  },
  reportBackBtnText: {
    fontSize: 13,
    fontWeight: '700',
    color: COLORS.textPrimary,
  },
  reportEditBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    paddingHorizontal: 20,
    paddingVertical: 11,
    borderRadius: 10,
    backgroundColor: COLORS.primary,
  },
  reportEditBtnText: {
    fontSize: 13,
    fontWeight: '700',
    color: '#fff',
  },
  reportApprovedBadge: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    paddingHorizontal: 14,
    paddingVertical: 10,
    borderRadius: 10,
    backgroundColor: `${COLORS.success}15`,
  },
  reportApprovedBadgeText: {
    fontSize: 12,
    fontWeight: '700',
    color: COLORS.success,
  },
  reportNoPhotoBox: {
    height: 52,
    borderRadius: 8,
    backgroundColor: '#f8fafc',
    borderWidth: 1,
    borderStyle: 'dashed',
    borderColor: '#cbd5e1',
    alignItems: 'center',
    justifyContent: 'center',
    flexDirection: 'row',
    gap: 6,
  },
  reportNoPhotoText: {
    fontSize: 12,
    color: COLORS.textMuted,
    fontWeight: '600',
  },
});
