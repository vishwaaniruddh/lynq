import React, { useState, useEffect, useCallback, useMemo } from 'react';
import {
  View,
  Text,
  TextInput,
  FlatList,
  StyleSheet,
  SafeAreaView,
  TouchableOpacity,
  ActivityIndicator,
  RefreshControl,
  Modal,
  Alert,
  ScrollView,
  Image,
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
import { COLORS, FONTS, RADIUS, SHADOWS, SPACING } from '../constants/theme';
import { STORAGE_KEYS } from '../constants/config';

const INSTALL_FILTERS = [
  { id: 'all', label: 'All' },
  { id: 'pending_eta', label: 'Pending ETA' },
  { id: 'pending_ada', label: 'Pending Arrival' },
  { id: 'pending_materials', label: 'Pending Materials' },
  { id: 'in_progress', label: 'In Progress' },
  { id: 'submitted', label: 'Submitted' },
];

const WIZARD_STEPS = [
  { id: 1, title: 'Router & Specs', shortLabel: 'Router', icon: 'hardware-chip-outline' },
  { id: 2, title: 'Power & LAN', shortLabel: 'Power & LAN', icon: 'flash-outline' },
  { id: 3, title: 'Antenna & GPS', shortLabel: 'Antenna', icon: 'radio-outline' },
  { id: 4, title: 'SIM Cards', shortLabel: 'SIMs', icon: 'card-outline' },
  { id: 5, title: 'Sign-off & QA', shortLabel: 'Sign-off', icon: 'checkmark-circle-outline' },
];

const getTodayDateString = () => {
  const d = new Date();
  const year = d.getFullYear();
  const month = String(d.getMonth() + 1).padStart(2, '0');
  const day = String(d.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
};

export const InstallationScreen = ({ navigation }) => {
  const [search, setSearch] = useState('');
  const [selectedFilter, setSelectedFilter] = useState('all');
  const [tasks, setTasks] = useState([]);
  const [counts, setCounts] = useState(null);
  const [isLoading, setIsLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [selectedTask, setSelectedTask] = useState(null);

  // Lifecycle Modals
  const [etaModalVisible, setEtaModalVisible] = useState(false);
  const [etaDate, setEtaDate] = useState(getTodayDateString());

  const [adaModalVisible, setAdaModalVisible] = useState(false);
  const [adaDate, setAdaDate] = useState(getTodayDateString());

  const [materialsModalVisible, setMaterialsModalVisible] = useState(false);

  // Installation Wizard Modal State
  const [wizardModalVisible, setWizardModalVisible] = useState(false);
  const [wizardStep, setWizardStep] = useState(1);
  const [isWizardLoading, setIsWizardLoading] = useState(false);
  const [isSavingDraft, setIsSavingDraft] = useState(false);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [uploadingField, setUploadingField] = useState(null);
  const [selectedPhotoPreview, setSelectedPhotoPreview] = useState(null);

  // Wizard Data State
  const [customFormSchema, setCustomFormSchema] = useState(null);
  const [siteDetails, setSiteDetails] = useState(null);
  const [isApproved, setIsApproved] = useState(false);
  const [canEditForm, setCanEditForm] = useState(true);
  const [rejectionFeedback, setRejectionFeedback] = useState(null);

  const [formData, setFormData] = useState({
    // Step 1: Router & Vendor Info
    vendor_name: '',
    engineer_name: '',
    engineer_number: '',
    router_serial: '',
    router_make: '4G Industrial',
    router_model: 'Dual SIM Standard',
    router_fixed: 'yes',
    router_status: 'working',
    router_fixed_remarks: '',
    router_fixed_snaps: null,
    router_status_remarks: '',
    router_status_snaps: null,

    // Step 2: Adaptor & LAN
    adaptor_installed: 'yes',
    adaptor_status: 'working',
    adaptor_snaps: null,
    adaptor_status_remarks: '',
    adaptor_status_snaps: null,
    lan_cable_installed: 'yes',
    lan_cable_status: 'working',
    lan_cable_install_remark: '',
    lan_cable_install_snap: null,
    lan_cable_status_not_working_reasons: '',
    lan_cable_status_remark: '',
    lan_cable_status_snap: null,

    // Step 3: Antenna, GPS & Wi-Fi
    antenna_installed: 'yes',
    antenna_status: 'working',
    antenna_remarks: '',
    antenna_snaps: null,
    antenna_status_remarks: '',
    antenna_status_snaps: null,
    gps_installed: 'yes',
    gps_status: 'working',
    gps_remarks: '',
    gps_snaps: null,
    gps_status_remarks: '',
    gps_status_snaps: null,
    wifi_installed: 'yes',
    wifi_status: 'working',
    wifi_remarks: '',
    wifi_snaps: null,
    wifi_status_remarks: '',
    wifi_status_snaps: null,

    // Step 4: SIM Cards (Airtel, Vodafone, Jio)
    airtel_sim_installed: 'yes',
    airtel_sim_status: 'working',
    airtel_sim_remarks: '',
    airtel_sim_snaps: null,
    airtel_sim_status_remarks: '',
    airtel_sim_status_snaps: null,

    vodafone_sim_installed: 'no',
    vodafone_sim_status: 'notWorking',
    vodafone_sim_remarks: '',
    vodafone_sim_snaps: null,
    vodafone_sim_status_remarks: '',
    vodafone_sim_status_snaps: null,

    jio_sim_installed: 'no',
    jio_sim_status: 'notWorking',
    jio_sim_remarks: '',
    jio_sim_snaps: null,
    jio_sim_status_remarks: '',
    jio_sim_status_snaps: null,

    // Step 5: Verification & Sign-off
    signature_image: null,
    vendor_stamp: null,
  });

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

  const fetchInstallationTasks = async () => {
    try {
      setIsLoading(true);
      const res = await engineerApi.getInstallationAssignments({
        status: selectedFilter === 'all' ? '' : selectedFilter,
        search: search.trim(),
      });
      if (res && res.success && res.data) {
        setTasks(res.data.installations || []);
        if (res.data.counts) {
          setCounts(res.data.counts);
        }
      } else {
        setTasks([]);
      }
    } catch (e) {
      console.log('Error loading installation tasks:', e);
      setTasks([]);
    } finally {
      setIsLoading(false);
      setRefreshing(false);
    }
  };

  useEffect(() => {
    fetchInstallationTasks();
  }, [selectedFilter]);

  const onRefresh = useCallback(() => {
    setRefreshing(true);
    fetchInstallationTasks();
  }, [selectedFilter, search]);

  const handleSearchSubmit = () => {
    fetchInstallationTasks();
  };

  // -------------------------------------------------------------
  // ETA SUBMISSION
  // -------------------------------------------------------------
  const handleOpenEtaModal = (task) => {
    setSelectedTask(task);
    setEtaDate(task.eta_date || getTodayDateString());
    setEtaModalVisible(true);
  };

  const handleSubmitEta = async () => {
    if (!etaDate.trim()) {
      Alert.alert('Required', 'Please enter a valid ETA date (YYYY-MM-DD).');
      return;
    }

    try {
      setIsSubmitting(true);
      const res = await engineerApi.submitInstallationETA(selectedTask.id, etaDate.trim());
      if (res && res.success) {
        Alert.alert('Success', 'ETA submitted successfully!');
        setEtaModalVisible(false);
        fetchInstallationTasks();
      } else {
        Alert.alert('Error', res?.message || 'Failed to submit ETA.');
      }
    } catch (e) {
      console.log('Error submitting ETA:', e);
      Alert.alert('Error', e?.response?.data?.message || 'Failed to submit ETA.');
    } finally {
      setIsSubmitting(false);
    }
  };

  // -------------------------------------------------------------
  // ADA (ARRIVAL) SUBMISSION
  // -------------------------------------------------------------
  const handleOpenAdaModal = (task) => {
    setSelectedTask(task);
    setAdaDate(task.ada_date || getTodayDateString());
    setAdaModalVisible(true);
  };

  const handleSubmitAda = async () => {
    try {
      setIsSubmitting(true);
      const res = await engineerApi.submitInstallationADA(selectedTask.id, adaDate.trim());
      if (res && res.success) {
        Alert.alert('Success', 'Arrival date recorded successfully!');
        setAdaModalVisible(false);
        fetchInstallationTasks();
      } else {
        Alert.alert('Error', res?.message || 'Failed to record arrival date.');
      }
    } catch (e) {
      console.log('Error submitting ADA:', e);
      Alert.alert('Error', e?.response?.data?.message || 'Failed to record arrival date.');
    } finally {
      setIsSubmitting(false);
    }
  };

  // -------------------------------------------------------------
  // MATERIAL RECEIPT CONFIRMATION
  // -------------------------------------------------------------
  const handleOpenMaterialsModal = (task) => {
    setSelectedTask(task);
    setMaterialsModalVisible(true);
  };

  const handleConfirmMaterials = async () => {
    try {
      setIsSubmitting(true);
      const res = await engineerApi.confirmMaterialsReceived(selectedTask.id);
      if (res && res.success) {
        Alert.alert('Confirmed', 'Material receipt confirmed successfully! You can now start the installation checklist.');
        setMaterialsModalVisible(false);
        fetchInstallationTasks();
      } else {
        Alert.alert('Error', res?.message || 'Failed to confirm material receipt.');
      }
    } catch (e) {
      console.log('Error confirming materials:', e);
      Alert.alert('Error', e?.response?.data?.message || 'Failed to confirm materials receipt.');
    } finally {
      setIsSubmitting(false);
    }
  };

  // -------------------------------------------------------------
  // OPEN FULL INSTALLATION WIZARD (Dynamic & Approval-Locked)
  // -------------------------------------------------------------
  const handleOpenWizard = async (task) => {
    // Stage validations: Installation starts strictly after materials are received
    if (task.status === 'pending_eta') {
      handleOpenEtaModal(task);
      return;
    }
    if (task.status === 'pending_ada') {
      handleOpenAdaModal(task);
      return;
    }
    if (task.status === 'pending_materials') {
      Alert.alert(
        'Materials Pending',
        'Hardware installation checklist can only start after materials are received. Would you like to confirm material receipt now?',
        [
          { text: 'Cancel', style: 'cancel' },
          { text: 'Confirm Materials', onPress: () => handleOpenMaterialsModal(task) }
        ]
      );
      return;
    }

    setSelectedTask(task);
    setWizardStep(1);
    setIsWizardLoading(true);
    setWizardModalVisible(true);

    try {
      // 1. Check local draft cache
      const draftKey = `@lynq_inst_draft_${task.id}`;
      const savedDraft = await AsyncStorage.getItem(draftKey);
      let localDraft = {};
      if (savedDraft) {
        try {
          localDraft = JSON.parse(savedDraft) || {};
        } catch (e) {}
      }

      // 2. Fetch full installation details from server
      const res = await engineerApi.getInstallationDetails(task.id);
      if (res && res.success && res.data) {
        const instData = res.data.installation || res.data;
        const siteData = res.data.site_info || null;
        const schema = res.data.custom_form || null;
        const approved = res.data.is_approved || ['contractor_approved', 'adv_approved'].includes(instData.status);
        const canEdit = res.data.can_edit !== undefined ? res.data.can_edit : !approved;

        setIsApproved(approved);
        setCanEditForm(canEdit);
        setSiteDetails(siteData);
        setCustomFormSchema(schema);

        if (res.data.has_rejections) {
          setRejectionFeedback(res.data.rejected_sections || [{ reason: 'Rework requested by supervisor' }]);
        } else {
          setRejectionFeedback(null);
        }

        // Initialize Form Values
        setFormData({
          vendor_name: instData.vendor_name || localDraft.vendor_name || task.contractor_name || '',
          engineer_name: instData.engineer_name || localDraft.engineer_name || '',
          engineer_number: instData.engineer_number || localDraft.engineer_number || '',
          router_serial: instData.router_serial || localDraft.router_serial || `RTR-${task.atm_id || task.id}`,
          router_make: instData.router_make || localDraft.router_make || '4G Industrial',
          router_model: instData.router_model || localDraft.router_model || 'Dual SIM Standard',
          router_fixed: instData.router_fixed || localDraft.router_fixed || 'yes',
          router_status: instData.router_status || localDraft.router_status || 'working',
          router_fixed_remarks: instData.router_fixed_remarks || localDraft.router_fixed_remarks || '',
          router_fixed_snaps: instData.router_fixed_snaps || localDraft.router_fixed_snaps || null,
          router_status_remarks: instData.router_status_remarks || localDraft.router_status_remarks || '',
          router_status_snaps: instData.router_status_snaps || localDraft.router_status_snaps || null,

          adaptor_installed: instData.adaptor_installed || localDraft.adaptor_installed || 'yes',
          adaptor_status: instData.adaptor_status || localDraft.adaptor_status || 'working',
          adaptor_snaps: instData.adaptor_snaps || localDraft.adaptor_snaps || null,
          adaptor_status_remarks: instData.adaptor_status_remarks || localDraft.adaptor_status_remarks || '',
          adaptor_status_snaps: instData.adaptor_status_snaps || localDraft.adaptor_status_snaps || null,

          lan_cable_installed: instData.lan_cable_installed || localDraft.lan_cable_installed || 'yes',
          lan_cable_status: instData.lan_cable_status || localDraft.lan_cable_status || 'working',
          lan_cable_install_remark: instData.lan_cable_install_remark || localDraft.lan_cable_install_remark || '',
          lan_cable_install_snap: instData.lan_cable_install_snap || localDraft.lan_cable_install_snap || null,
          lan_cable_status_not_working_reasons: instData.lan_cable_status_not_working_reasons || localDraft.lan_cable_status_not_working_reasons || '',
          lan_cable_status_remark: instData.lan_cable_status_remark || localDraft.lan_cable_status_remark || '',
          lan_cable_status_snap: instData.lan_cable_status_snap || localDraft.lan_cable_status_snap || null,

          antenna_installed: instData.antenna_installed || localDraft.antenna_installed || 'yes',
          antenna_status: instData.antenna_status || localDraft.antenna_status || 'working',
          antenna_remarks: instData.antenna_remarks || localDraft.antenna_remarks || '',
          antenna_snaps: instData.antenna_snaps || localDraft.antenna_snaps || null,
          antenna_status_remarks: instData.antenna_status_remarks || localDraft.antenna_status_remarks || '',
          antenna_status_snaps: instData.antenna_status_snaps || localDraft.antenna_status_snaps || null,

          gps_installed: instData.gps_installed || localDraft.gps_installed || 'yes',
          gps_status: instData.gps_status || localDraft.gps_status || 'working',
          gps_remarks: instData.gps_remarks || localDraft.gps_remarks || '',
          gps_snaps: instData.gps_snaps || localDraft.gps_snaps || null,
          gps_status_remarks: instData.gps_status_remarks || localDraft.gps_status_remarks || '',
          gps_status_snaps: instData.gps_status_snaps || localDraft.gps_status_snaps || null,

          wifi_installed: instData.wifi_installed || localDraft.wifi_installed || 'yes',
          wifi_status: instData.wifi_status || localDraft.wifi_status || 'working',
          wifi_remarks: instData.wifi_remarks || localDraft.wifi_remarks || '',
          wifi_snaps: instData.wifi_snaps || localDraft.wifi_snaps || null,
          wifi_status_remarks: instData.wifi_status_remarks || localDraft.wifi_status_remarks || '',
          wifi_status_snaps: instData.wifi_status_snaps || localDraft.wifi_status_snaps || null,

          airtel_sim_installed: instData.airtel_sim_installed || localDraft.airtel_sim_installed || 'yes',
          airtel_sim_status: instData.airtel_sim_status || localDraft.airtel_sim_status || 'working',
          airtel_sim_remarks: instData.airtel_sim_remarks || localDraft.airtel_sim_remarks || '',
          airtel_sim_snaps: instData.airtel_sim_snaps || localDraft.airtel_sim_snaps || null,
          airtel_sim_status_remarks: instData.airtel_sim_status_remarks || localDraft.airtel_sim_status_remarks || '',
          airtel_sim_status_snaps: instData.airtel_sim_status_snaps || localDraft.airtel_sim_status_snaps || null,

          vodafone_sim_installed: instData.vodafone_sim_installed || localDraft.vodafone_sim_installed || 'no',
          vodafone_sim_status: instData.vodafone_sim_status || localDraft.vodafone_sim_status || 'notWorking',
          vodafone_sim_remarks: instData.vodafone_sim_remarks || localDraft.vodafone_sim_remarks || '',
          vodafone_sim_snaps: instData.vodafone_sim_snaps || localDraft.vodafone_sim_snaps || null,
          vodafone_sim_status_remarks: instData.vodafone_sim_status_remarks || localDraft.vodafone_sim_status_remarks || '',
          vodafone_sim_status_snaps: instData.vodafone_sim_status_snaps || localDraft.vodafone_sim_status_snaps || null,

          jio_sim_installed: instData.jio_sim_installed || localDraft.jio_sim_installed || 'no',
          jio_sim_status: instData.jio_sim_status || localDraft.jio_sim_status || 'notWorking',
          jio_sim_remarks: instData.jio_sim_remarks || localDraft.jio_sim_remarks || '',
          jio_sim_snaps: instData.jio_sim_snaps || localDraft.jio_sim_snaps || null,
          jio_sim_status_remarks: instData.jio_sim_status_remarks || localDraft.jio_sim_status_remarks || '',
          jio_sim_status_snaps: instData.jio_sim_status_snaps || localDraft.jio_sim_status_snaps || null,

          signature_image: instData.signature_image || localDraft.signature_image || null,
          vendor_stamp: instData.vendor_stamp || localDraft.vendor_stamp || null,
        });
      }
    } catch (e) {
      console.log('Error opening wizard:', e);
      Alert.alert('Error', 'Failed to load installation details.');
    } finally {
      setIsWizardLoading(false);
    }
  };

  const updateField = (key, val) => {
    if (isApproved || !canEditForm) return;
    setFormData((prev) => ({ ...prev, [key]: val }));
  };

  // -------------------------------------------------------------
  // PHOTO CAPTURE & UPLOAD
  // -------------------------------------------------------------
  const handlePickOrCapturePhoto = async (fieldKey, sectionName = 'hardware') => {
    if (isApproved || !canEditForm) return;

    try {
      const { status } = await ImagePicker.requestCameraPermissionsAsync();
      if (status !== 'granted') {
        Alert.alert('Permission Denied', 'Camera permission is required to capture verification photos.');
        return;
      }

      const result = await ImagePicker.launchCameraAsync({
        mediaTypes: ['images'],
        allowsEditing: false,
        quality: 0.8,
      });

      if (!result.canceled && result.assets && result.assets.length > 0) {
        const localUri = result.assets[0].uri;
        setFormData((prev) => ({ ...prev, [fieldKey]: localUri }));

        // Upload to server in background
        try {
          setUploadingField(fieldKey);
          const uploadRes = await engineerApi.uploadInstallationImage(selectedTask.id, sectionName, localUri);
          if (uploadRes && uploadRes.success && uploadRes.data?.path) {
            setFormData((prev) => ({ ...prev, [fieldKey]: uploadRes.data.path }));
          }
        } catch (upErr) {
          console.log('Upload error (using local preview):', upErr);
        } finally {
          setUploadingField(null);
        }
      }
    } catch (e) {
      console.log('Photo capture error:', e);
      Alert.alert('Error', 'Failed to capture photo.');
    }
  };

  // -------------------------------------------------------------
  // SAVE DRAFT & SUBMIT
  // -------------------------------------------------------------
  const handleSaveDraft = async () => {
    if (isApproved || !canEditForm) {
      Alert.alert('Locked', 'This installation has already been approved and is read-only.');
      return;
    }

    try {
      setIsSavingDraft(true);
      // 1. Save locally
      const draftKey = `@lynq_inst_draft_${selectedTask.id}`;
      await AsyncStorage.setItem(draftKey, JSON.stringify(formData));

      // 2. Sync to server
      const res = await engineerApi.saveInstallation(selectedTask.id, formData);
      if (res && res.success) {
        Alert.alert('Draft Saved', 'Installation checklist draft saved successfully!');
        fetchInstallationTasks();
      } else {
        Alert.alert('Saved Offline', 'Draft saved locally on your device.');
      }
    } catch (e) {
      console.log('Save draft error:', e);
      Alert.alert('Saved Offline', 'Draft saved locally on your device.');
    } finally {
      setIsSavingDraft(false);
    }
  };

  const handleSubmitInstallation = async () => {
    if (isApproved || !canEditForm) {
      Alert.alert('Locked', 'This installation has already been approved.');
      return;
    }

    if (!formData.router_serial?.trim()) {
      Alert.alert('Validation Error', 'Router Serial Number is required.');
      setWizardStep(1);
      return;
    }

    try {
      setIsSubmitting(true);
      // Save latest values
      await engineerApi.saveInstallation(selectedTask.id, formData);
      // Final Submit
      const res = await engineerApi.submitInstallationForm(selectedTask.id);
      if (res && res.success) {
        Alert.alert('Submitted', 'Installation QA report submitted for supervisor review successfully!');
        setWizardModalVisible(false);
        fetchInstallationTasks();
      } else {
        Alert.alert('Submission Error', res?.message || 'Failed to submit installation.');
      }
    } catch (e) {
      console.log('Submit error:', e);
      Alert.alert('Error', e?.response?.data?.message || 'Failed to submit installation.');
    } finally {
      setIsSubmitting(false);
    }
  };

  // -------------------------------------------------------------
  // UI STATUS BADGES
  // -------------------------------------------------------------
  const getStatusBadge = (status) => {
    switch (status) {
      case 'contractor_approved':
      case 'adv_approved':
        return { label: 'Approved & Completed', bg: '#dcfce7', border: '#bbf7d0', text: '#15803d' };
      case 'submitted':
      case 'pending_contractor_review':
        return { label: 'Submitted (In Review)', bg: '#f3e8ff', border: '#d8b4fe', text: '#7e22ce' };
      case 'materials_received':
        return { label: 'Materials Received', bg: '#e0f2fe', border: '#bae6fd', text: '#0369a1' };
      case 'in_progress':
        return { label: 'In Progress', bg: '#e0e7ff', border: '#c7d2fe', text: '#4338ca' };
      case 'pending_materials':
        return { label: 'Pending Materials', bg: '#fef3c7', border: '#fde68a', text: '#d97706' };
      case 'pending_ada':
        return { label: 'Pending Arrival (ADA)', bg: '#ffedd5', border: '#fed7aa', text: '#c2410c' };
      case 'pending_eta':
        return { label: 'Pending ETA', bg: '#fef9c3', border: '#fef08a', text: '#a16207' };
      case 'contractor_rejected':
      case 'adv_rejected':
        return { label: 'Rejected - Needs Fix', bg: COLORS.destructiveLight, border: COLORS.destructiveBorder, text: COLORS.destructiveForeground };
      default:
        return { label: status || 'Assigned', bg: COLORS.secondary, border: COLORS.border, text: COLORS.secondaryForeground };
    }
  };

  const formatDateDisplay = (dateStr) => {
    if (!dateStr) return '-';
    try {
      const dt = new Date(dateStr);
      if (isNaN(dt.getTime())) return dateStr;
      return dt.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
    } catch {
      return dateStr;
    }
  };

  // -------------------------------------------------------------
  // RENDER PHOTO UPLOADER BOX
  // -------------------------------------------------------------
  const renderPhotoBox = (label, fieldKey, section = 'hardware') => {
    const photoUri = formData[fieldKey];
    const isUploading = uploadingField === fieldKey;

    return (
      <View style={styles.photoContainer}>
        <Text style={styles.fieldLabel}>{label}</Text>
        {photoUri ? (
          <View style={styles.photoPreviewCard}>
            <TouchableOpacity onPress={() => setSelectedPhotoPreview(resolveImageUrl(photoUri))}>
              <Image source={{ uri: resolveImageUrl(photoUri) }} style={styles.photoThumbnail} resizeMode="cover" />
            </TouchableOpacity>
            <View style={{ flex: 1, paddingLeft: 10 }}>
              <Text style={styles.photoAttachedText}>Photo Attached</Text>
              <Text style={styles.photoSubtext} numberOfLines={1}>
                {photoUri.split('/').pop()}
              </Text>
              <View style={{ flexDirection: 'row', gap: 8, marginTop: 6 }}>
                <TouchableOpacity
                  style={styles.photoActionPill}
                  onPress={() => setSelectedPhotoPreview(resolveImageUrl(photoUri))}
                >
                  <Ionicons name="eye" size={12} color="#0284c7" />
                  <Text style={[styles.photoActionPillText, { color: '#0284c7' }]}>Zoom</Text>
                </TouchableOpacity>
                {!isApproved && canEditForm ? (
                  <TouchableOpacity
                    style={[styles.photoActionPill, { backgroundColor: '#fee2e2' }]}
                    onPress={() => updateField(fieldKey, null)}
                  >
                    <Ionicons name="trash" size={12} color="#dc2626" />
                    <Text style={[styles.photoActionPillText, { color: '#dc2626' }]}>Remove</Text>
                  </TouchableOpacity>
                ) : null}
              </View>
            </View>
          </View>
        ) : (
          <TouchableOpacity
            style={[styles.photoUploadBtn, (isApproved || !canEditForm) && styles.disabledPhotoBtn]}
            onPress={() => handlePickOrCapturePhoto(fieldKey, section)}
            disabled={isApproved || !canEditForm || isUploading}
          >
            {isUploading ? (
              <ActivityIndicator size="small" color={COLORS.primary} />
            ) : (
              <>
                <Ionicons name="camera-outline" size={18} color={COLORS.primary} />
                <Text style={styles.photoUploadBtnText}>
                  {isApproved || !canEditForm ? 'No Photo Captured' : 'Take Verification Photo'}
                </Text>
              </>
            )}
          </TouchableOpacity>
        )}
      </View>
    );
  };

  // -------------------------------------------------------------
  // RENDER SELECT / RADIO PILL ROW
  // -------------------------------------------------------------
  const renderBinarySelect = (label, fieldKey, required = false) => {
    const val = formData[fieldKey];
    const isYes = val === 'yes';
    const isNo = val === 'no';

    return (
      <View style={styles.fieldGroup}>
        <Text style={styles.fieldLabel}>
          {label} {required ? <Text style={{ color: COLORS.destructive }}>*</Text> : null}
        </Text>
        <View style={styles.binaryPillRow}>
          <TouchableOpacity
            style={[styles.binaryPill, isYes && styles.binaryPillActiveYes, (isApproved || !canEditForm) && { opacity: 0.8 }]}
            onPress={() => updateField(fieldKey, 'yes')}
            disabled={isApproved || !canEditForm}
          >
            <Ionicons name={isYes ? 'checkmark-circle' : 'ellipse-outline'} size={15} color={isYes ? '#15803d' : COLORS.textMuted} />
            <Text style={[styles.binaryPillText, isYes && styles.binaryPillTextActiveYes]}>Yes / Installed</Text>
          </TouchableOpacity>

          <TouchableOpacity
            style={[styles.binaryPill, isNo && styles.binaryPillActiveNo, (isApproved || !canEditForm) && { opacity: 0.8 }]}
            onPress={() => updateField(fieldKey, 'no')}
            disabled={isApproved || !canEditForm}
          >
            <Ionicons name={isNo ? 'close-circle' : 'ellipse-outline'} size={15} color={isNo ? '#b91c1c' : COLORS.textMuted} />
            <Text style={[styles.binaryPillText, isNo && styles.binaryPillTextActiveNo]}>No / N/A</Text>
          </TouchableOpacity>
        </View>
      </View>
    );
  };

  const renderStatusSelect = (label, fieldKey, required = false) => {
    const val = formData[fieldKey];
    const isWorking = val === 'working';
    const isNotWorking = val === 'notWorking';

    return (
      <View style={styles.fieldGroup}>
        <Text style={styles.fieldLabel}>
          {label} {required ? <Text style={{ color: COLORS.destructive }}>*</Text> : null}
        </Text>
        <View style={styles.binaryPillRow}>
          <TouchableOpacity
            style={[styles.binaryPill, isWorking && styles.binaryPillActiveYes, (isApproved || !canEditForm) && { opacity: 0.8 }]}
            onPress={() => updateField(fieldKey, 'working')}
            disabled={isApproved || !canEditForm}
          >
            <Ionicons name={isWorking ? 'checkmark-circle' : 'ellipse-outline'} size={15} color={isWorking ? '#15803d' : COLORS.textMuted} />
            <Text style={[styles.binaryPillText, isWorking && styles.binaryPillTextActiveYes]}>Working OK</Text>
          </TouchableOpacity>

          <TouchableOpacity
            style={[styles.binaryPill, isNotWorking && styles.binaryPillActiveNo, (isApproved || !canEditForm) && { opacity: 0.8 }]}
            onPress={() => updateField(fieldKey, 'notWorking')}
            disabled={isApproved || !canEditForm}
          >
            <Ionicons name={isNotWorking ? 'alert-circle' : 'ellipse-outline'} size={15} color={isNotWorking ? '#b91c1c' : COLORS.textMuted} />
            <Text style={[styles.binaryPillText, isNotWorking && styles.binaryPillTextActiveNo]}>Not Working</Text>
          </TouchableOpacity>
        </View>
      </View>
    );
  };

  return (
    <SafeAreaView style={styles.safeArea}>
      <Header
        title="Hardware Installation"
        subtitle="Deployment lifecycle: ETA → ADA → Materials → Commission"
        showBack={navigation.canGoBack()}
        onBackPress={() => navigation.goBack()}
      />

      {/* Search & Filters */}
      <View style={styles.searchHeader}>
        <View style={styles.searchBar}>
          <Ionicons name="search-outline" size={16} color={COLORS.mutedForeground} />
          <TextInput
            style={styles.searchInput}
            placeholder="Search ATM ID, Site Name, City..."
            placeholderTextColor={COLORS.textMuted}
            value={search}
            onChangeText={setSearch}
            onSubmitEditing={handleSearchSubmit}
            returnKeyType="search"
          />
          {search ? (
            <TouchableOpacity onPress={() => { setSearch(''); fetchInstallationTasks(); }}>
              <Ionicons name="close-circle" size={16} color={COLORS.textMuted} />
            </TouchableOpacity>
          ) : null}
        </View>

        <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={styles.chipsRow}>
          {INSTALL_FILTERS.map((f) => {
            const isActive = selectedFilter === f.id;
            return (
              <TouchableOpacity
                key={f.id}
                style={[styles.chip, isActive && styles.chipActive]}
                onPress={() => setSelectedFilter(f.id)}
              >
                <Text style={[styles.chipText, isActive && styles.chipTextActive]}>
                  {f.label}
                </Text>
              </TouchableOpacity>
            );
          })}
        </ScrollView>
      </View>

      {/* Installation Cards List */}
      {isLoading && !refreshing ? (
        <View style={styles.loaderBox}>
          <ActivityIndicator size="large" color={COLORS.primary} />
          <Text style={styles.loaderText}>Loading installation sites...</Text>
        </View>
      ) : (
        <FlatList
          data={tasks}
          keyExtractor={(item) => String(item.id)}
          contentContainerStyle={styles.listContent}
          refreshControl={
            <RefreshControl
              refreshing={refreshing}
              onRefresh={onRefresh}
              tintColor={COLORS.primary}
              colors={[COLORS.primary]}
            />
          }
          renderItem={({ item }) => {
            const badge = getStatusBadge(item.status);
            const isPendingEta = item.status === 'pending_eta';
            const isPendingAda = item.status === 'pending_ada';
            const isPendingMaterials = item.status === 'pending_materials';
            const isApprovedRecord = ['contractor_approved', 'adv_approved'].includes(item.status);
            const isRejected = ['contractor_rejected', 'adv_rejected'].includes(item.status);

            return (
              <View style={[styles.card, SHADOWS.small, isRejected && styles.cardRejected]}>
                {/* Rejection Alert */}
                {isRejected ? (
                  <View style={styles.rejectionBanner}>
                    <Ionicons name="alert-circle" size={15} color={COLORS.destructive} />
                    <Text style={styles.rejectionBannerText}>
                      Supervisor requested rework. Please correct form and resubmit.
                    </Text>
                  </View>
                ) : null}

                {/* Card Header */}
                <View style={styles.cardHeader}>
                  <View style={{ flex: 1, paddingRight: 8 }}>
                    <Text style={styles.atmIdText}>
                      {item.atm_id || item.site_name || `Site #${item.site_id}`}
                    </Text>
                    <Text style={styles.bankText}>
                      {[item.bank_name, item.customer_name, item.city || item.site_city].filter(Boolean).join(' • ')}
                    </Text>
                  </View>

                  <View style={[styles.statusBadge, { backgroundColor: badge.bg, borderColor: badge.border }]}>
                    <Text style={[styles.statusBadgeText, { color: badge.text }]}>
                      {badge.label}
                    </Text>
                  </View>
                </View>

                {/* Lifecycle Timeline */}
                <View style={styles.timelineRow}>
                  <View style={[styles.timelinePill, item.eta_date && styles.timelinePillActive]}>
                    <Ionicons name="calendar-outline" size={11} color={item.eta_date ? '#0369a1' : COLORS.textMuted} />
                    <Text style={[styles.timelinePillText, item.eta_date && styles.timelinePillTextActive]}>
                      ETA: {item.eta_date ? formatDateDisplay(item.eta_date) : 'Pending'}
                    </Text>
                  </View>

                  <View style={[styles.timelinePill, item.ada_date && styles.timelinePillActive]}>
                    <Ionicons name="location-outline" size={11} color={item.ada_date ? '#0369a1' : COLORS.textMuted} />
                    <Text style={[styles.timelinePillText, item.ada_date && styles.timelinePillTextActive]}>
                      Arrival: {item.ada_date ? formatDateDisplay(item.ada_date) : 'Pending'}
                    </Text>
                  </View>
                </View>

                {/* Card Actions */}
                <View style={styles.cardFooter}>
                  <Text style={styles.locationMetaText} numberOfLines={1}>
                    <Ionicons name="navigate-outline" size={11} color={COLORS.mutedForeground} /> {item.address || item.site_address || 'Address pending'}
                  </Text>

                  {isPendingEta ? (
                    <TouchableOpacity
                      style={[styles.installActionBtn, { backgroundColor: '#d97706' }]}
                      onPress={() => handleOpenEtaModal(item)}
                    >
                      <Ionicons name="calendar" size={13} color="#fff" />
                      <Text style={styles.installActionBtnText}>Submit ETA</Text>
                    </TouchableOpacity>
                  ) : isPendingAda ? (
                    <TouchableOpacity
                      style={[styles.installActionBtn, { backgroundColor: '#ea580c' }]}
                      onPress={() => handleOpenAdaModal(item)}
                    >
                      <Ionicons name="checkmark-circle" size={13} color="#fff" />
                      <Text style={styles.installActionBtnText}>Record Arrival</Text>
                    </TouchableOpacity>
                  ) : isPendingMaterials ? (
                    <TouchableOpacity
                      style={[styles.installActionBtn, { backgroundColor: '#0284c7' }]}
                      onPress={() => handleOpenMaterialsModal(item)}
                    >
                      <Ionicons name="cube" size={13} color="#fff" />
                      <Text style={styles.installActionBtnText}>Confirm Materials</Text>
                    </TouchableOpacity>
                  ) : isApprovedRecord ? (
                    <TouchableOpacity
                      style={[styles.installActionBtn, { backgroundColor: '#f0fdf4', borderWidth: 1, borderColor: '#bbf7d0' }]}
                      onPress={() => handleOpenWizard(item)}
                    >
                      <Ionicons name="shield-checkmark" size={13} color="#15803d" />
                      <Text style={[styles.installActionBtnText, { color: '#15803d' }]}>
                        Approved (View)
                      </Text>
                    </TouchableOpacity>
                  ) : (
                    <TouchableOpacity
                      style={[
                        styles.installActionBtn,
                        { backgroundColor: isRejected ? COLORS.destructive : COLORS.primary }
                      ]}
                      onPress={() => handleOpenWizard(item)}
                    >
                      <Ionicons name={isRejected ? 'refresh-outline' : 'create-outline'} size={13} color="#fff" />
                      <Text style={styles.installActionBtnText}>
                        {isRejected ? 'Correct & Resubmit' : item.status === 'submitted' ? 'Edit Checklist' : 'Fill Form Wizard'}
                      </Text>
                    </TouchableOpacity>
                  )}
                </View>
              </View>
            );
          }}
          ListEmptyComponent={
            <View style={styles.emptyContainer}>
              <View style={styles.emptyIconCircle}>
                <Ionicons name="construct-outline" size={38} color={COLORS.mutedForeground} />
              </View>
              <Text style={styles.emptyTitle}>No Installation Sites</Text>
              <Text style={styles.emptySubtitle}>
                You currently have no hardware installation assignments under this filter.
              </Text>
            </View>
          }
        />
      )}

      {/* ========================================================================= */}
      {/* 1. ETA MODAL */}
      {/* ========================================================================= */}
      <Modal visible={etaModalVisible} transparent animationType="fade">
        <View style={styles.modalBackdrop}>
          <View style={styles.dialogCard}>
            <View style={styles.dialogHeader}>
              <View style={[styles.dialogIconCircle, { backgroundColor: '#fef3c7' }]}>
                <Ionicons name="calendar" size={20} color="#d97706" />
              </View>
              <View style={{ flex: 1 }}>
                <Text style={styles.dialogTitle}>Submit Estimated Arrival (ETA)</Text>
                <Text style={styles.dialogSubtitle}>{selectedTask?.atm_id || selectedTask?.site_name}</Text>
              </View>
              <TouchableOpacity onPress={() => setEtaModalVisible(false)} style={styles.closeIconBtn}>
                <Ionicons name="close" size={18} color={COLORS.textPrimary} />
              </TouchableOpacity>
            </View>
            <View style={styles.dialogBody}>
              <Text style={styles.fieldLabel}>ETA Date (YYYY-MM-DD) *</Text>
              <TextInput
                style={styles.dialogInput}
                placeholder="YYYY-MM-DD"
                placeholderTextColor={COLORS.textMuted}
                value={etaDate}
                onChangeText={setEtaDate}
              />
            </View>
            <View style={styles.dialogFooter}>
              <TouchableOpacity style={styles.dialogCancelBtn} onPress={() => setEtaModalVisible(false)}>
                <Text style={styles.dialogCancelBtnText}>Cancel</Text>
              </TouchableOpacity>
              <TouchableOpacity style={[styles.dialogSubmitBtn, { backgroundColor: '#d97706' }]} onPress={handleSubmitEta}>
                {isSubmitting ? <ActivityIndicator size="small" color="#fff" /> : <Text style={styles.dialogSubmitBtnText}>Submit ETA</Text>}
              </TouchableOpacity>
            </View>
          </View>
        </View>
      </Modal>

      {/* ========================================================================= */}
      {/* 2. ADA (ARRIVAL) MODAL */}
      {/* ========================================================================= */}
      <Modal visible={adaModalVisible} transparent animationType="fade">
        <View style={styles.modalBackdrop}>
          <View style={styles.dialogCard}>
            <View style={styles.dialogHeader}>
              <View style={[styles.dialogIconCircle, { backgroundColor: '#ffedd5' }]}>
                <Ionicons name="location" size={20} color="#ea580c" />
              </View>
              <View style={{ flex: 1 }}>
                <Text style={styles.dialogTitle}>Record Actual Arrival (ADA)</Text>
                <Text style={styles.dialogSubtitle}>{selectedTask?.atm_id || selectedTask?.site_name}</Text>
              </View>
              <TouchableOpacity onPress={() => setAdaModalVisible(false)} style={styles.closeIconBtn}>
                <Ionicons name="close" size={18} color={COLORS.textPrimary} />
              </TouchableOpacity>
            </View>
            <View style={styles.dialogBody}>
              <Text style={styles.fieldLabel}>Arrival Date (YYYY-MM-DD) *</Text>
              <TextInput
                style={styles.dialogInput}
                placeholder="YYYY-MM-DD"
                placeholderTextColor={COLORS.textMuted}
                value={adaDate}
                onChangeText={setAdaDate}
              />
            </View>
            <View style={styles.dialogFooter}>
              <TouchableOpacity style={styles.dialogCancelBtn} onPress={() => setAdaModalVisible(false)}>
                <Text style={styles.dialogCancelBtnText}>Cancel</Text>
              </TouchableOpacity>
              <TouchableOpacity style={[styles.dialogSubmitBtn, { backgroundColor: '#ea580c' }]} onPress={handleSubmitAda}>
                {isSubmitting ? <ActivityIndicator size="small" color="#fff" /> : <Text style={styles.dialogSubmitBtnText}>Confirm Arrival</Text>}
              </TouchableOpacity>
            </View>
          </View>
        </View>
      </Modal>

      {/* ========================================================================= */}
      {/* 3. MATERIAL RECEIPT MODAL */}
      {/* ========================================================================= */}
      <Modal visible={materialsModalVisible} transparent animationType="fade">
        <View style={styles.modalBackdrop}>
          <View style={styles.dialogCard}>
            <View style={styles.dialogHeader}>
              <View style={[styles.dialogIconCircle, { backgroundColor: '#e0f2fe' }]}>
                <Ionicons name="cube" size={20} color="#0284c7" />
              </View>
              <View style={{ flex: 1 }}>
                <Text style={styles.dialogTitle}>Confirm Material Receipt</Text>
                <Text style={styles.dialogSubtitle}>{selectedTask?.atm_id || selectedTask?.site_name}</Text>
              </View>
              <TouchableOpacity onPress={() => setMaterialsModalVisible(false)} style={styles.closeIconBtn}>
                <Ionicons name="close" size={18} color={COLORS.textPrimary} />
              </TouchableOpacity>
            </View>
            <View style={styles.dialogBody}>
              <View style={[styles.infoBox, { backgroundColor: '#f0fdf4', borderColor: '#bbf7d0' }]}>
                <Ionicons name="checkmark-circle-outline" size={18} color="#16a34a" style={{ marginRight: 6 }} />
                <Text style={[styles.infoBoxText, { color: '#15803d', flex: 1 }]}>
                  Please confirm that the 4G Router, SIMs, Power Adaptor, Antenna & Cables are received in good condition.
                </Text>
              </View>
            </View>
            <View style={styles.dialogFooter}>
              <TouchableOpacity style={styles.dialogCancelBtn} onPress={() => setMaterialsModalVisible(false)}>
                <Text style={styles.dialogCancelBtnText}>Cancel</Text>
              </TouchableOpacity>
              <TouchableOpacity style={[styles.dialogSubmitBtn, { backgroundColor: '#0284c7' }]} onPress={handleConfirmMaterials}>
                {isSubmitting ? <ActivityIndicator size="small" color="#fff" /> : <Text style={styles.dialogSubmitBtnText}>I Received Materials</Text>}
              </TouchableOpacity>
            </View>
          </View>
        </View>
      </Modal>

      {/* ========================================================================= */}
      {/* 4. DYNAMIC MULTI-STEP INSTALLATION WIZARD (Editable vs Approved Locked) */}
      {/* ========================================================================= */}
      <Modal visible={wizardModalVisible} animationType="slide" presentationStyle="pageSheet">
        <SafeAreaView style={styles.wizardSafeArea}>
          {/* Top App Bar */}
          <View style={styles.wizardHeader}>
            <View style={{ flex: 1 }}>
              <Text style={styles.wizardTitle}>
                {isApproved ? 'Installation Record (Locked)' : 'Installation Commissioning'}
              </Text>
              <Text style={styles.wizardSubtitle}>
                {selectedTask?.atm_id || selectedTask?.site_name} • {selectedTask?.bank_name || 'Bank ATM'}
              </Text>
            </View>
            <TouchableOpacity onPress={() => setWizardModalVisible(false)} style={styles.closeIconBtn}>
              <Ionicons name="close" size={20} color={COLORS.textPrimary} />
            </TouchableOpacity>
          </View>

          {/* Status Banners */}
          {isApproved ? (
            <View style={styles.approvedBanner}>
              <Ionicons name="shield-checkmark" size={18} color="#15803d" />
              <View style={{ flex: 1 }}>
                <Text style={styles.approvedBannerTitle}>Approved & Closed</Text>
                <Text style={styles.approvedBannerSub}>
                  This installation form has been approved by ADV / Supervisor and is locked for editing.
                </Text>
              </View>
            </View>
          ) : rejectionFeedback ? (
            <View style={styles.rejectionAlertBanner}>
              <Ionicons name="alert-circle" size={18} color="#dc2626" />
              <View style={{ flex: 1 }}>
                <Text style={styles.rejectionAlertTitle}>Supervisor Rejection Feedback</Text>
                <Text style={styles.rejectionAlertSub}>
                  {rejectionFeedback[0]?.reason || rejectionFeedback[0]?.comments || 'Please revise inputs and resubmit.'}
                </Text>
              </View>
            </View>
          ) : null}

          {/* Wizard Step Navigation Pills */}
          <View style={styles.wizardStepsBar}>
            <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={styles.wizardStepsScroll}>
              {WIZARD_STEPS.map((step) => {
                const isActive = wizardStep === step.id;
                const isCompleted = wizardStep > step.id;
                return (
                  <TouchableOpacity
                    key={step.id}
                    style={[styles.wizardStepPill, isActive && styles.wizardStepPillActive, isCompleted && styles.wizardStepPillDone]}
                    onPress={() => setWizardStep(step.id)}
                  >
                    <Ionicons
                      name={isCompleted ? 'checkmark-circle' : step.icon}
                      size={14}
                      color={isActive ? '#ffffff' : isCompleted ? '#16a34a' : COLORS.textMuted}
                    />
                    <Text style={[styles.wizardStepPillText, isActive && styles.wizardStepPillTextActive, isCompleted && styles.wizardStepPillTextDone]}>
                      {step.shortLabel}
                    </Text>
                  </TouchableOpacity>
                );
              })}
            </ScrollView>
          </View>

          {/* Form Scroll Area */}
          {isWizardLoading ? (
            <View style={styles.loaderBox}>
              <ActivityIndicator size="large" color={COLORS.primary} />
              <Text style={styles.loaderText}>Loading installation data...</Text>
            </View>
          ) : (
            <ScrollView contentContainerStyle={styles.wizardBodyContent}>
              {/* ========================================================================= */}
              {/* STEP 1: ROUTER & VENDOR SPECIFICATIONS */}
              {/* ========================================================================= */}
              {wizardStep === 1 ? (
                <View style={styles.stepSection}>
                  <Text style={styles.sectionHeaderTitle}>1. Site & Vendor Information</Text>

                  {/* Site Summary Box */}
                  <View style={styles.siteInfoBox}>
                    <Text style={styles.siteInfoAtm}>{selectedTask?.atm_id || siteDetails?.atm_id || 'ATM ID N/A'}</Text>
                    <Text style={styles.siteInfoText}>
                      LHO: {selectedTask?.lho || siteDetails?.lho || '-'} • City: {selectedTask?.city || siteDetails?.city || '-'}
                    </Text>
                    <Text style={styles.siteInfoText}>
                      Address: {selectedTask?.address || siteDetails?.address || 'Site address recorded'}
                    </Text>
                  </View>

                  <View style={styles.fieldGroup}>
                    <Text style={styles.fieldLabel}>Vendor Name *</Text>
                    <TextInput
                      style={[styles.fieldInput, isApproved && styles.readOnlyInput]}
                      placeholder="e.g. Diebold / CMS"
                      placeholderTextColor={COLORS.textMuted}
                      value={formData.vendor_name}
                      editable={!isApproved && canEditForm}
                      onChangeText={(val) => updateField('vendor_name', val)}
                    />
                  </View>

                  <View style={styles.rowTwoCols}>
                    <View style={[styles.fieldGroup, { flex: 1 }]}>
                      <Text style={styles.fieldLabel}>Engineer Name *</Text>
                      <TextInput
                        style={[styles.fieldInput, isApproved && styles.readOnlyInput]}
                        placeholder="Name"
                        placeholderTextColor={COLORS.textMuted}
                        value={formData.engineer_name}
                        editable={!isApproved && canEditForm}
                        onChangeText={(val) => updateField('engineer_name', val)}
                      />
                    </View>
                    <View style={[styles.fieldGroup, { flex: 1 }]}>
                      <Text style={styles.fieldLabel}>Engineer Number *</Text>
                      <TextInput
                        style={[styles.fieldInput, isApproved && styles.readOnlyInput]}
                        placeholder="10-digit mobile"
                        placeholderTextColor={COLORS.textMuted}
                        value={formData.engineer_number}
                        keyboardType="phone-pad"
                        editable={!isApproved && canEditForm}
                        onChangeText={(val) => updateField('engineer_number', val)}
                      />
                    </View>
                  </View>

                  <Text style={[styles.sectionHeaderTitle, { marginTop: SPACING.md }]}>Router Specifications</Text>

                  <View style={styles.fieldGroup}>
                    <Text style={styles.fieldLabel}>Router Serial Number *</Text>
                    <TextInput
                      style={[styles.fieldInput, isApproved && styles.readOnlyInput]}
                      placeholder="e.g. RTR-884291"
                      placeholderTextColor={COLORS.textMuted}
                      value={formData.router_serial}
                      editable={!isApproved && canEditForm}
                      onChangeText={(val) => updateField('router_serial', val)}
                    />
                  </View>

                  <View style={styles.rowTwoCols}>
                    <View style={[styles.fieldGroup, { flex: 1 }]}>
                      <Text style={styles.fieldLabel}>Router Make *</Text>
                      <TextInput
                        style={[styles.fieldInput, isApproved && styles.readOnlyInput]}
                        placeholder="e.g. Advantech"
                        placeholderTextColor={COLORS.textMuted}
                        value={formData.router_make}
                        editable={!isApproved && canEditForm}
                        onChangeText={(val) => updateField('router_make', val)}
                      />
                    </View>
                    <View style={[styles.fieldGroup, { flex: 1 }]}>
                      <Text style={styles.fieldLabel}>Router Model *</Text>
                      <TextInput
                        style={[styles.fieldInput, isApproved && styles.readOnlyInput]}
                        placeholder="e.g. ICR-3211"
                        placeholderTextColor={COLORS.textMuted}
                        value={formData.router_model}
                        editable={!isApproved && canEditForm}
                        onChangeText={(val) => updateField('router_model', val)}
                      />
                    </View>
                  </View>

                  {renderBinarySelect('Router Fixed & Mounted Securely', 'router_fixed', true)}
                  {renderStatusSelect('Router Operational Status', 'router_status', true)}

                  <View style={styles.fieldGroup}>
                    <Text style={styles.fieldLabel}>Router Mounting Remarks</Text>
                    <TextInput
                      style={[styles.fieldInput, isApproved && styles.readOnlyInput]}
                      placeholder="Mounting details or backroom location..."
                      placeholderTextColor={COLORS.textMuted}
                      value={formData.router_fixed_remarks}
                      editable={!isApproved && canEditForm}
                      onChangeText={(val) => updateField('router_fixed_remarks', val)}
                    />
                  </View>

                  {renderPhotoBox('Router Fixed / Mounted Photo', 'router_fixed_snaps', 'router')}
                  {renderPhotoBox('Router Status / LED Lights Photo', 'router_status_snaps', 'router')}
                </View>
              ) : null}

              {/* ========================================================================= */}
              {/* STEP 2: POWER ADAPTOR & LAN CABLE */}
              {/* ========================================================================= */}
              {wizardStep === 2 ? (
                <View style={styles.stepSection}>
                  <Text style={styles.sectionHeaderTitle}>2. Adaptor & Power Section</Text>
                  {renderBinarySelect('Power Adaptor Installed', 'adaptor_installed', true)}
                  {renderStatusSelect('Adaptor Working Status', 'adaptor_status', true)}

                  <View style={styles.fieldGroup}>
                    <Text style={styles.fieldLabel}>Adaptor Remarks</Text>
                    <TextInput
                      style={[styles.fieldInput, isApproved && styles.readOnlyInput]}
                      placeholder="Power socket or UPS connection notes..."
                      placeholderTextColor={COLORS.textMuted}
                      value={formData.adaptor_status_remarks}
                      editable={!isApproved && canEditForm}
                      onChangeText={(val) => updateField('adaptor_status_remarks', val)}
                    />
                  </View>

                  {renderPhotoBox('Power Adaptor Photo', 'adaptor_snaps', 'adaptor')}

                  <Text style={[styles.sectionHeaderTitle, { marginTop: SPACING.md }]}>LAN Cable Section</Text>
                  {renderBinarySelect('LAN Cable Installed to ATM Port', 'lan_cable_installed', true)}
                  {renderStatusSelect('LAN Cable Working / Ping Status', 'lan_cable_status', true)}

                  {formData.lan_cable_status === 'notWorking' ? (
                    <View style={styles.fieldGroup}>
                      <Text style={[styles.fieldLabel, { color: COLORS.destructive }]}>Not Working Reasons *</Text>
                      <TextInput
                        style={[styles.fieldInput, isApproved && styles.readOnlyInput, { borderColor: COLORS.destructive }]}
                        placeholder="Explain why LAN connectivity is failing..."
                        placeholderTextColor={COLORS.textMuted}
                        value={formData.lan_cable_status_not_working_reasons}
                        editable={!isApproved && canEditForm}
                        onChangeText={(val) => updateField('lan_cable_status_not_working_reasons', val)}
                      />
                    </View>
                  ) : null}

                  <View style={styles.fieldGroup}>
                    <Text style={styles.fieldLabel}>LAN Cable Install Remarks</Text>
                    <TextInput
                      style={[styles.fieldInput, isApproved && styles.readOnlyInput]}
                      placeholder="Cable pathway, length or crimping notes..."
                      placeholderTextColor={COLORS.textMuted}
                      value={formData.lan_cable_install_remark}
                      editable={!isApproved && canEditForm}
                      onChangeText={(val) => updateField('lan_cable_install_remark', val)}
                    />
                  </View>

                  {renderPhotoBox('LAN Cable Install Photo', 'lan_cable_install_snap', 'lan_cable')}
                  {renderPhotoBox('LAN Cable Status / Port Snap', 'lan_cable_status_snap', 'lan_cable')}
                </View>
              ) : null}

              {/* ========================================================================= */}
              {/* STEP 3: ANTENNA, GPS & WI-FI */}
              {/* ========================================================================= */}
              {wizardStep === 3 ? (
                <View style={styles.stepSection}>
                  <Text style={styles.sectionHeaderTitle}>3. 4G Antenna Section</Text>
                  {renderBinarySelect('4G Antenna Installed', 'antenna_installed', true)}
                  {renderStatusSelect('4G Antenna Signal Status', 'antenna_status', true)}

                  <View style={styles.fieldGroup}>
                    <Text style={styles.fieldLabel}>Antenna Mounting Remarks</Text>
                    <TextInput
                      style={[styles.fieldInput, isApproved && styles.readOnlyInput]}
                      placeholder="Exterior/interior position, height, signal level..."
                      placeholderTextColor={COLORS.textMuted}
                      value={formData.antenna_remarks}
                      editable={!isApproved && canEditForm}
                      onChangeText={(val) => updateField('antenna_remarks', val)}
                    />
                  </View>

                  {renderPhotoBox('Antenna Mounted Photo', 'antenna_snaps', 'antenna')}

                  <Text style={[styles.sectionHeaderTitle, { marginTop: SPACING.md }]}>GPS & Wi-Fi Configuration</Text>
                  {renderBinarySelect('GPS Antenna Connected', 'gps_installed')}
                  {renderStatusSelect('GPS Receiver Status', 'gps_status')}

                  {renderPhotoBox('GPS Antenna Snap', 'gps_snaps', 'gps')}

                  {renderBinarySelect('Wi-Fi Antenna Connected', 'wifi_installed')}
                  {renderStatusSelect('Wi-Fi Signal Status', 'wifi_status')}

                  {renderPhotoBox('Wi-Fi Antenna Snap', 'wifi_snaps', 'wifi')}
                </View>
              ) : null}

              {/* ========================================================================= */}
              {/* STEP 4: SIM CARDS CONFIGURATION */}
              {/* ========================================================================= */}
              {wizardStep === 4 ? (
                <View style={styles.stepSection}>
                  <Text style={styles.sectionHeaderTitle}>4. SIM Cards (Slot 1 & Slot 2)</Text>

                  {/* Airtel SIM Box */}
                  <View style={styles.simCardBox}>
                    <View style={styles.simCardHeader}>
                      <Ionicons name="card" size={16} color="#dc2626" />
                      <Text style={[styles.simCardTitle, { color: '#dc2626' }]}>Primary: Airtel SIM</Text>
                    </View>
                    {renderBinarySelect('Airtel SIM Inserted', 'airtel_sim_installed', true)}
                    {renderStatusSelect('Airtel Network Registration', 'airtel_sim_status', true)}
                    <View style={styles.fieldGroup}>
                      <Text style={styles.fieldLabel}>Airtel SIM Number & Details</Text>
                      <TextInput
                        style={[styles.fieldInput, isApproved && styles.readOnlyInput]}
                        placeholder="e.g. SIM No. 8991000984928"
                        placeholderTextColor={COLORS.textMuted}
                        value={formData.airtel_sim_remarks}
                        editable={!isApproved && canEditForm}
                        onChangeText={(val) => updateField('airtel_sim_remarks', val)}
                      />
                    </View>
                    {renderPhotoBox('Airtel SIM / Network Snap', 'airtel_sim_snaps', 'sim')}
                  </View>

                  {/* Vodafone SIM Box */}
                  <View style={[styles.simCardBox, { borderColor: '#fca5a5' }]}>
                    <View style={styles.simCardHeader}>
                      <Ionicons name="card" size={16} color="#b91c1c" />
                      <Text style={[styles.simCardTitle, { color: '#b91c1c' }]}>Secondary: Vodafone SIM</Text>
                    </View>
                    {renderBinarySelect('Vodafone SIM Inserted', 'vodafone_sim_installed')}
                    {renderStatusSelect('Vodafone Network Registration', 'vodafone_sim_status')}
                    <View style={styles.fieldGroup}>
                      <Text style={styles.fieldLabel}>Vodafone SIM Number & Details</Text>
                      <TextInput
                        style={[styles.fieldInput, isApproved && styles.readOnlyInput]}
                        placeholder="e.g. SIM No. 8991000123456"
                        placeholderTextColor={COLORS.textMuted}
                        value={formData.vodafone_sim_remarks}
                        editable={!isApproved && canEditForm}
                        onChangeText={(val) => updateField('vodafone_sim_remarks', val)}
                      />
                    </View>
                    {renderPhotoBox('Vodafone SIM Snap', 'vodafone_sim_snaps', 'sim')}
                  </View>

                  {/* JIO SIM Box */}
                  <View style={[styles.simCardBox, { borderColor: '#93c5fd' }]}>
                    <View style={styles.simCardHeader}>
                      <Ionicons name="card" size={16} color="#2563eb" />
                      <Text style={[styles.simCardTitle, { color: '#2563eb' }]}>Secondary / Fallback: Jio SIM</Text>
                    </View>
                    {renderBinarySelect('Jio SIM Inserted', 'jio_sim_installed')}
                    {renderStatusSelect('Jio Network Registration', 'jio_sim_status')}
                    <View style={styles.fieldGroup}>
                      <Text style={styles.fieldLabel}>Jio SIM Number & Details</Text>
                      <TextInput
                        style={[styles.fieldInput, isApproved && styles.readOnlyInput]}
                        placeholder="e.g. Jio SIM No. 8991000654321"
                        placeholderTextColor={COLORS.textMuted}
                        value={formData.jio_sim_remarks}
                        editable={!isApproved && canEditForm}
                        onChangeText={(val) => updateField('jio_sim_remarks', val)}
                      />
                    </View>
                    {renderPhotoBox('Jio SIM Snap', 'jio_sim_snaps', 'sim')}
                  </View>
                </View>
              ) : null}

              {/* ========================================================================= */}
              {/* STEP 5: VERIFICATION & SIGN-OFF */}
              {/* ========================================================================= */}
              {wizardStep === 5 ? (
                <View style={styles.stepSection}>
                  <Text style={styles.sectionHeaderTitle}>5. Verification & Sign-off</Text>

                  {/* Checklist Summary Card */}
                  <View style={styles.summaryCard}>
                    <Text style={styles.summaryCardTitle}>Installation Checklist Summary</Text>
                    <View style={styles.summaryRow}>
                      <Text style={styles.summaryLabel}>Router Serial:</Text>
                      <Text style={styles.summaryValue}>{formData.router_serial || 'Pending'}</Text>
                    </View>
                    <View style={styles.summaryRow}>
                      <Text style={styles.summaryLabel}>Router Mounting:</Text>
                      <Text style={[styles.summaryValue, { color: formData.router_fixed === 'yes' ? '#15803d' : '#b91c1c' }]}>
                        {formData.router_fixed === 'yes' ? 'Mounted (Yes)' : 'No'}
                      </Text>
                    </View>
                    <View style={styles.summaryRow}>
                      <Text style={styles.summaryLabel}>Router Status:</Text>
                      <Text style={[styles.summaryValue, { color: formData.router_status === 'working' ? '#15803d' : '#b91c1c' }]}>
                        {formData.router_status === 'working' ? 'Working OK' : 'Not Working'}
                      </Text>
                    </View>
                    <View style={styles.summaryRow}>
                      <Text style={styles.summaryLabel}>LAN Connected:</Text>
                      <Text style={[styles.summaryValue, { color: formData.lan_cable_installed === 'yes' ? '#15803d' : '#b91c1c' }]}>
                        {formData.lan_cable_installed === 'yes' ? 'Yes' : 'No'}
                      </Text>
                    </View>
                    <View style={styles.summaryRow}>
                      <Text style={styles.summaryLabel}>Antenna Installed:</Text>
                      <Text style={[styles.summaryValue, { color: formData.antenna_installed === 'yes' ? '#15803d' : '#b91c1c' }]}>
                        {formData.antenna_installed === 'yes' ? 'Yes' : 'No'}
                      </Text>
                    </View>
                  </View>

                  {renderPhotoBox('Digital Signature / Handover Sheet *', 'signature_image', 'signature')}
                  {renderPhotoBox('Bank / Vendor Stamp Photo', 'vendor_stamp', 'signature')}
                </View>
              ) : null}
            </ScrollView>
          )}

          {/* Bottom Wizard Actions */}
          <View style={styles.wizardFooter}>
            {wizardStep > 1 ? (
              <TouchableOpacity
                style={styles.wizardBackBtn}
                onPress={() => setWizardStep((prev) => Math.max(1, prev - 1))}
              >
                <Ionicons name="arrow-back" size={15} color={COLORS.textPrimary} />
                <Text style={styles.wizardBackBtnText}>Previous</Text>
              </TouchableOpacity>
            ) : null}

            {!isApproved && canEditForm ? (
              <TouchableOpacity
                style={styles.wizardDraftBtn}
                onPress={handleSaveDraft}
                disabled={isSavingDraft || isSubmitting}
              >
                {isSavingDraft ? (
                  <ActivityIndicator size="small" color={COLORS.textPrimary} />
                ) : (
                  <>
                    <Ionicons name="save-outline" size={15} color={COLORS.textPrimary} />
                    <Text style={styles.wizardDraftBtnText}>Save Draft</Text>
                  </>
                )}
              </TouchableOpacity>
            ) : null}

            {wizardStep < 5 ? (
              <TouchableOpacity
                style={styles.wizardNextBtn}
                onPress={() => setWizardStep((prev) => Math.min(5, prev + 1))}
              >
                <Text style={styles.wizardNextBtnText}>Next</Text>
                <Ionicons name="arrow-forward" size={15} color="#ffffff" />
              </TouchableOpacity>
            ) : !isApproved && canEditForm ? (
              <TouchableOpacity
                style={[styles.wizardNextBtn, { backgroundColor: '#16a34a' }]}
                onPress={handleSubmitInstallation}
                disabled={isSubmitting}
              >
                {isSubmitting ? (
                  <ActivityIndicator size="small" color="#fff" />
                ) : (
                  <>
                    <Ionicons name="paper-plane" size={15} color="#ffffff" />
                    <Text style={styles.wizardNextBtnText}>Submit Installation</Text>
                  </>
                )}
              </TouchableOpacity>
            ) : (
              <TouchableOpacity
                style={[styles.wizardNextBtn, { backgroundColor: COLORS.secondary, borderWidth: 1, borderColor: COLORS.border }]}
                onPress={() => setWizardModalVisible(false)}
              >
                <Text style={[styles.wizardNextBtnText, { color: COLORS.textPrimary }]}>Close</Text>
              </TouchableOpacity>
            )}
          </View>
        </SafeAreaView>
      </Modal>

      {/* Image Preview Modal */}
      <ZoomableImageModal
        visible={!!selectedPhotoPreview}
        imageUrl={selectedPhotoPreview}
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
  searchHeader: {
    paddingHorizontal: SPACING.md,
    paddingTop: SPACING.sm,
    backgroundColor: '#ffffff',
    borderBottomWidth: 1,
    borderBottomColor: COLORS.border,
  },
  searchBar: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: COLORS.secondary,
    borderRadius: RADIUS.md,
    paddingHorizontal: SPACING.sm,
    height: 40,
    gap: 8,
  },
  searchInput: {
    flex: 1,
    fontSize: FONTS.body,
    color: COLORS.textPrimary,
  },
  chipsRow: {
    paddingVertical: SPACING.sm,
    gap: 8,
  },
  chip: {
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: RADIUS.full,
    backgroundColor: COLORS.secondary,
    borderWidth: 1,
    borderColor: COLORS.border,
  },
  chipActive: {
    backgroundColor: COLORS.primary,
    borderColor: COLORS.primary,
  },
  chipText: {
    fontSize: 12,
    fontWeight: '600',
    color: COLORS.textSecondary,
  },
  chipTextActive: {
    color: '#ffffff',
  },
  listContent: {
    padding: SPACING.md,
    gap: SPACING.md,
  },
  card: {
    backgroundColor: '#ffffff',
    borderRadius: RADIUS.lg,
    borderWidth: 1,
    borderColor: COLORS.border,
    overflow: 'hidden',
  },
  cardRejected: {
    borderColor: COLORS.destructiveBorder,
  },
  rejectionBanner: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    backgroundColor: COLORS.destructiveLight,
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderBottomWidth: 1,
    borderBottomColor: COLORS.destructiveBorder,
  },
  rejectionBannerText: {
    fontSize: 11,
    fontWeight: '600',
    color: COLORS.destructiveForeground,
    flex: 1,
  },
  cardHeader: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    justifyContent: 'space-between',
    padding: SPACING.md,
    paddingBottom: 8,
  },
  atmIdText: {
    fontSize: FONTS.subtitle,
    fontWeight: '800',
    color: COLORS.textPrimary,
  },
  bankText: {
    fontSize: 12,
    color: COLORS.mutedForeground,
    marginTop: 2,
  },
  statusBadge: {
    paddingHorizontal: 8,
    paddingVertical: 4,
    borderRadius: RADIUS.full,
    borderWidth: 1,
  },
  statusBadgeText: {
    fontSize: 10,
    fontWeight: '700',
  },
  timelineRow: {
    flexDirection: 'row',
    paddingHorizontal: SPACING.md,
    paddingBottom: 8,
    gap: 8,
  },
  timelinePill: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
    paddingHorizontal: 8,
    paddingVertical: 4,
    borderRadius: RADIUS.sm,
    backgroundColor: COLORS.secondary,
  },
  timelinePillActive: {
    backgroundColor: '#e0f2fe',
  },
  timelinePillText: {
    fontSize: 10,
    color: COLORS.textMuted,
    fontWeight: '500',
  },
  timelinePillTextActive: {
    color: '#0369a1',
    fontWeight: '700',
  },
  cardFooter: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    padding: SPACING.md,
    paddingTop: 8,
    borderTopWidth: 1,
    borderTopColor: '#f4f4f5',
    gap: 8,
  },
  locationMetaText: {
    fontSize: 11,
    color: COLORS.mutedForeground,
    flex: 1,
  },
  installActionBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 5,
    paddingHorizontal: 12,
    paddingVertical: 7,
    borderRadius: RADIUS.md,
  },
  installActionBtnText: {
    fontSize: 12,
    fontWeight: '700',
    color: '#ffffff',
  },
  loaderBox: {
    padding: SPACING.xxl,
    alignItems: 'center',
    justifyContent: 'center',
  },
  loaderText: {
    fontSize: FONTS.body,
    color: COLORS.mutedForeground,
    marginTop: SPACING.sm,
  },
  emptyContainer: {
    alignItems: 'center',
    justifyContent: 'center',
    paddingVertical: 60,
  },
  emptyIconCircle: {
    width: 64,
    height: 64,
    borderRadius: 32,
    backgroundColor: COLORS.secondary,
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: SPACING.md,
  },
  emptyTitle: {
    fontSize: FONTS.title,
    fontWeight: '700',
    color: COLORS.textPrimary,
  },
  emptySubtitle: {
    fontSize: FONTS.body,
    color: COLORS.mutedForeground,
    textAlign: 'center',
    marginTop: 6,
    paddingHorizontal: SPACING.lg,
  },

  // Modal styles
  modalBackdrop: {
    flex: 1,
    backgroundColor: 'rgba(0,0,0,0.55)',
    justifyContent: 'center',
    alignItems: 'center',
    padding: SPACING.md,
  },
  dialogCard: {
    backgroundColor: '#ffffff',
    borderRadius: RADIUS.xl,
    width: '100%',
    maxWidth: 420,
    overflow: 'hidden',
    ...SHADOWS.large,
  },
  dialogHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    padding: SPACING.md,
    borderBottomWidth: 1,
    borderBottomColor: COLORS.border,
    gap: 10,
  },
  dialogIconCircle: {
    width: 38,
    height: 38,
    borderRadius: 19,
    alignItems: 'center',
    justifyContent: 'center',
  },
  dialogTitle: {
    fontSize: FONTS.subtitle,
    fontWeight: '700',
    color: COLORS.textPrimary,
  },
  dialogSubtitle: {
    fontSize: FONTS.small,
    color: COLORS.mutedForeground,
  },
  closeIconBtn: {
    padding: 6,
    borderRadius: RADIUS.sm,
    backgroundColor: COLORS.secondary,
  },
  dialogBody: {
    padding: SPACING.md,
  },
  dialogInput: {
    backgroundColor: '#ffffff',
    borderWidth: 1,
    borderColor: COLORS.border,
    borderRadius: RADIUS.md,
    paddingHorizontal: 12,
    paddingVertical: 10,
    fontSize: FONTS.body,
    color: COLORS.textPrimary,
    marginTop: 4,
  },
  infoBox: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#f8fafc',
    borderWidth: 1,
    borderColor: COLORS.border,
    borderRadius: RADIUS.md,
    padding: 10,
    marginBottom: 10,
  },
  infoBoxText: {
    fontSize: 12,
    color: COLORS.textSecondary,
  },
  dialogFooter: {
    flexDirection: 'row',
    justifyContent: 'flex-end',
    gap: 10,
    padding: SPACING.md,
    borderTopWidth: 1,
    borderTopColor: COLORS.border,
    backgroundColor: '#f8fafc',
  },
  dialogCancelBtn: {
    paddingHorizontal: 14,
    paddingVertical: 8,
    borderRadius: RADIUS.md,
    backgroundColor: COLORS.secondary,
    borderWidth: 1,
    borderColor: COLORS.border,
  },
  dialogCancelBtnText: {
    fontSize: 12,
    fontWeight: '600',
    color: COLORS.textSecondary,
  },
  dialogSubmitBtn: {
    paddingHorizontal: 16,
    paddingVertical: 8,
    borderRadius: RADIUS.md,
    alignItems: 'center',
    justifyContent: 'center',
  },
  dialogSubmitBtnText: {
    fontSize: 12,
    fontWeight: '700',
    color: '#ffffff',
  },

  // Wizard Styles
  wizardSafeArea: {
    flex: 1,
    backgroundColor: '#ffffff',
  },
  wizardHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingHorizontal: SPACING.md,
    paddingVertical: SPACING.sm,
    borderBottomWidth: 1,
    borderBottomColor: COLORS.border,
  },
  wizardTitle: {
    fontSize: FONTS.subtitle,
    fontWeight: '800',
    color: COLORS.textPrimary,
  },
  wizardSubtitle: {
    fontSize: FONTS.small,
    color: COLORS.mutedForeground,
    marginTop: 2,
  },
  approvedBanner: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    backgroundColor: '#f0fdf4',
    paddingHorizontal: SPACING.md,
    paddingVertical: 8,
    borderBottomWidth: 1,
    borderBottomColor: '#bbf7d0',
  },
  approvedBannerTitle: {
    fontSize: 12,
    fontWeight: '700',
    color: '#15803d',
  },
  approvedBannerSub: {
    fontSize: 10,
    color: '#166534',
    marginTop: 1,
  },
  rejectionAlertBanner: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    backgroundColor: '#fef2f2',
    paddingHorizontal: SPACING.md,
    paddingVertical: 8,
    borderBottomWidth: 1,
    borderBottomColor: '#fecaca',
  },
  rejectionAlertTitle: {
    fontSize: 12,
    fontWeight: '700',
    color: '#dc2626',
  },
  rejectionAlertSub: {
    fontSize: 10,
    color: '#991b1b',
    marginTop: 1,
  },
  wizardStepsBar: {
    backgroundColor: '#f8fafc',
    borderBottomWidth: 1,
    borderBottomColor: COLORS.border,
  },
  wizardStepsScroll: {
    paddingHorizontal: SPACING.sm,
    paddingVertical: 8,
    gap: 6,
  },
  wizardStepPill: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 5,
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: RADIUS.full,
    backgroundColor: '#ffffff',
    borderWidth: 1,
    borderColor: COLORS.border,
  },
  wizardStepPillActive: {
    backgroundColor: COLORS.primary,
    borderColor: COLORS.primary,
  },
  wizardStepPillDone: {
    backgroundColor: '#f0fdf4',
    borderColor: '#bbf7d0',
  },
  wizardStepPillText: {
    fontSize: 11,
    fontWeight: '600',
    color: COLORS.textMuted,
  },
  wizardStepPillTextActive: {
    color: '#ffffff',
    fontWeight: '700',
  },
  wizardStepPillTextDone: {
    color: '#15803d',
    fontWeight: '600',
  },
  wizardBodyContent: {
    padding: SPACING.md,
  },
  stepSection: {
    gap: 12,
  },
  sectionHeaderTitle: {
    fontSize: 12,
    fontWeight: '800',
    color: COLORS.textPrimary,
    letterSpacing: 0.5,
    textTransform: 'uppercase',
  },
  siteInfoBox: {
    backgroundColor: '#f8fafc',
    borderWidth: 1,
    borderColor: COLORS.border,
    borderRadius: RADIUS.md,
    padding: 10,
    marginBottom: 4,
  },
  siteInfoAtm: {
    fontSize: 13,
    fontWeight: '800',
    color: COLORS.textPrimary,
  },
  siteInfoText: {
    fontSize: 11,
    color: COLORS.mutedForeground,
    marginTop: 2,
  },
  fieldGroup: {
    marginBottom: 2,
  },
  rowTwoCols: {
    flexDirection: 'row',
    gap: 10,
  },
  fieldLabel: {
    fontSize: 12,
    fontWeight: '600',
    color: COLORS.textPrimary,
    marginBottom: 4,
  },
  fieldInput: {
    backgroundColor: '#ffffff',
    borderWidth: 1,
    borderColor: COLORS.border,
    borderRadius: RADIUS.md,
    paddingHorizontal: 12,
    paddingVertical: 9,
    fontSize: 13,
    color: COLORS.textPrimary,
  },
  readOnlyInput: {
    backgroundColor: '#f8fafc',
    color: '#334155',
  },
  binaryPillRow: {
    flexDirection: 'row',
    gap: 10,
  },
  binaryPill: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 6,
    paddingVertical: 9,
    borderRadius: RADIUS.md,
    backgroundColor: '#ffffff',
    borderWidth: 1,
    borderColor: COLORS.border,
  },
  binaryPillActiveYes: {
    backgroundColor: '#f0fdf4',
    borderColor: '#86efac',
  },
  binaryPillActiveNo: {
    backgroundColor: '#fef2f2',
    borderColor: '#fca5a5',
  },
  binaryPillText: {
    fontSize: 12,
    fontWeight: '600',
    color: COLORS.textMuted,
  },
  binaryPillTextActiveYes: {
    color: '#15803d',
    fontWeight: '700',
  },
  binaryPillTextActiveNo: {
    color: '#b91c1c',
    fontWeight: '700',
  },
  photoContainer: {
    marginVertical: 4,
  },
  photoUploadBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
    borderWidth: 1.5,
    borderStyle: 'dashed',
    borderColor: COLORS.primary,
    borderRadius: RADIUS.md,
    paddingVertical: 12,
    backgroundColor: '#f5f3ff',
  },
  disabledPhotoBtn: {
    borderColor: COLORS.border,
    backgroundColor: '#f8fafc',
  },
  photoUploadBtnText: {
    fontSize: 12,
    fontWeight: '600',
    color: COLORS.primary,
  },
  photoPreviewCard: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#f8fafc',
    borderWidth: 1,
    borderColor: COLORS.border,
    borderRadius: RADIUS.md,
    padding: 8,
  },
  photoThumbnail: {
    width: 60,
    height: 60,
    borderRadius: RADIUS.sm,
    backgroundColor: '#e2e8f0',
  },
  photoAttachedText: {
    fontSize: 12,
    fontWeight: '700',
    color: COLORS.textPrimary,
  },
  photoSubtext: {
    fontSize: 10,
    color: COLORS.mutedForeground,
    marginTop: 1,
  },
  photoActionPill: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 3,
    backgroundColor: '#e0f2fe',
    paddingHorizontal: 8,
    paddingVertical: 4,
    borderRadius: RADIUS.sm,
  },
  photoActionPillText: {
    fontSize: 10,
    fontWeight: '700',
  },
  simCardBox: {
    backgroundColor: '#ffffff',
    borderWidth: 1,
    borderColor: COLORS.border,
    borderRadius: RADIUS.lg,
    padding: SPACING.md,
    gap: 8,
    marginBottom: 10,
  },
  simCardHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    marginBottom: 4,
  },
  simCardTitle: {
    fontSize: 13,
    fontWeight: '800',
  },
  summaryCard: {
    backgroundColor: '#f8fafc',
    borderWidth: 1,
    borderColor: COLORS.border,
    borderRadius: RADIUS.lg,
    padding: SPACING.md,
    gap: 8,
    marginBottom: 10,
  },
  summaryCardTitle: {
    fontSize: 13,
    fontWeight: '800',
    color: COLORS.textPrimary,
    marginBottom: 4,
  },
  summaryRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    paddingVertical: 3,
    borderBottomWidth: 1,
    borderBottomColor: '#f1f5f9',
  },
  summaryLabel: {
    fontSize: 12,
    color: COLORS.mutedForeground,
  },
  summaryValue: {
    fontSize: 12,
    fontWeight: '700',
    color: COLORS.textPrimary,
  },
  wizardFooter: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    padding: SPACING.md,
    borderTopWidth: 1,
    borderTopColor: COLORS.border,
    backgroundColor: '#ffffff',
  },
  wizardBackBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 5,
    paddingHorizontal: 14,
    paddingVertical: 10,
    borderRadius: RADIUS.md,
    backgroundColor: COLORS.secondary,
    borderWidth: 1,
    borderColor: COLORS.border,
  },
  wizardBackBtnText: {
    fontSize: 13,
    fontWeight: '600',
    color: COLORS.textPrimary,
  },
  wizardDraftBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 5,
    flex: 1,
    paddingVertical: 10,
    borderRadius: RADIUS.md,
    backgroundColor: COLORS.secondary,
    borderWidth: 1,
    borderColor: COLORS.border,
  },
  wizardDraftBtnText: {
    fontSize: 13,
    fontWeight: '700',
    color: COLORS.textPrimary,
  },
  wizardNextBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 5,
    flex: 1.2,
    paddingVertical: 10,
    borderRadius: RADIUS.md,
    backgroundColor: COLORS.primary,
  },
  wizardNextBtnText: {
    fontSize: 13,
    fontWeight: '700',
    color: '#ffffff',
  },
});
