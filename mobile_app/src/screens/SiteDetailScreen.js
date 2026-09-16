import React, { useState, useEffect } from 'react';
import {
  View,
  Text,
  StyleSheet,
  SafeAreaView,
  ScrollView,
  TouchableOpacity,
  Linking,
  Modal,
  TextInput,
  Alert,
  ActivityIndicator,
  Platform,
} from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import DateTimePicker from '@react-native-community/datetimepicker';
import * as Location from 'expo-location';
import { engineerApi } from '../api/engineerApi';
import { Header } from '../components/Header';
import { CustomButton } from '../components/CustomButton';
import { COLORS, FONTS, RADIUS, SPACING, SHADOWS } from '../constants/theme';

export const SiteDetailScreen = ({ route, navigation }) => {
  const { siteId, site: initialSite, openEta, openAda } = route.params || {};
  const [site, setSite] = useState(initialSite || {});
  const [isLoading, setIsLoading] = useState(!initialSite);
  const [showEtaModal, setShowEtaModal] = useState(!!openEta);

  // ADA Modal & Location States
  const [showAdaModal, setShowAdaModal] = useState(!!openAda);
  const [isSubmittingAda, setIsSubmittingAda] = useState(false);
  const [isFetchingLocation, setIsFetchingLocation] = useState(false);
  const [currentCoords, setCurrentCoords] = useState(null);
  const [locationError, setLocationError] = useState(null);

  // Date Picker States
  const [etaDate, setEtaDate] = useState(new Date(Date.now() + 2 * 3600 * 1000)); // Default: +2 hours
  const [pickerMode, setPickerMode] = useState('date');
  const [showPicker, setShowPicker] = useState(false);

  const [etaRemarks, setEtaRemarks] = useState('');
  const [isUpdatingEta, setIsUpdatingEta] = useState(false);
  const [isUpdatingStatus, setIsUpdatingStatus] = useState(false);

  const fetchSiteDetail = async () => {
    try {
      setIsLoading(true);
      const res = await engineerApi.getSiteDetails(siteId);
      if (res && res.success && res.data) {
        setSite(res.data.site || res.data.assignment || res.data);
      }
    } catch (e) {
      console.log('Error fetching site detail:', e);
    } finally {
      setIsLoading(false);
    }
  };

  useEffect(() => {
    if (siteId) {
      fetchSiteDetail();
    }
  }, [siteId]);

  const handleCall = (phoneNumber) => {
    if (!phoneNumber) {
      Alert.alert('No Number', 'No phone number is listed for this contact.');
      return;
    }
    Linking.openURL(`tel:${phoneNumber}`);
  };

  const handleOpenMaps = () => {
    const lat = site.latitude;
    const lng = site.longitude;
    const address = site.address || `${site.city || ''} ${site.state || ''}`;

    if (lat && lng) {
      Linking.openURL(`https://www.google.com/maps/search/?api=1&query=${lat},${lng}`);
    } else if (address) {
      Linking.openURL(`https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(address)}`);
    } else {
      Alert.alert('No Location', 'No coordinate or address found for navigation.');
    }
  };

  // Format Date for SQL / API: YYYY-MM-DD HH:mm:ss
  const formatForApi = (d) => {
    const pad = (n) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}:00`;
  };

  // Format for human-readable display
  const formatDisplayDate = (d) => {
    const options = { day: '2-digit', month: 'short', year: 'numeric' };
    return d.toLocaleDateString('en-GB', options);
  };

  const formatDisplayTime = (d) => {
    return d.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true });
  };

  const onDateChange = (event, selected) => {
    setShowPicker(Platform.OS === 'ios');
    if (selected) {
      setEtaDate(selected);
    }
  };

  const openDatePicker = (mode) => {
    setPickerMode(mode);
    setShowPicker(true);
  };

  const applyPreset = (hoursFromNow, targetHour = null) => {
    const d = new Date();
    if (targetHour !== null) {
      d.setDate(d.getDate() + (hoursFromNow > 12 ? 1 : 0));
      d.setHours(targetHour, 0, 0, 0);
    } else {
      d.setTime(d.getTime() + hoursFromNow * 3600 * 1000);
    }
    setEtaDate(d);
  };

  const handleSaveEta = async () => {
    if (hasAda) {
      Alert.alert('ETA Locked', 'ETA cannot be modified after site arrival (ADA) has already been submitted.');
      setShowEtaModal(false);
      return;
    }
    try {
      setIsUpdatingEta(true);
      const assignmentId = site.assignment_id || site.id || siteId;
      const apiFormattedEta = formatForApi(etaDate);

      const res = await engineerApi.updateETA(
        assignmentId,
        apiFormattedEta,
        etaRemarks.trim(),
        site.site_id || site.id || siteId
      );

      if (res && res.success) {
        Alert.alert('Success', 'Visit ETA updated successfully.');
        setSite((prev) => ({
          ...prev,
          eta: `${formatDisplayDate(etaDate)} ${formatDisplayTime(etaDate)}`,
          eta_datetime: apiFormattedEta,
        }));
        setShowEtaModal(false);
      } else {
        Alert.alert('Error', res.message || res.error || 'Failed to update ETA');
      }
    } catch (e) {
      const msg = e.response?.data?.message || e.response?.data?.error || e.message || 'Failed to update ETA';
      Alert.alert('Error', msg);
    } finally {
      setIsUpdatingEta(false);
    }
  };

  // Location / ADA Handler
  const fetchCurrentLocation = async () => {
    try {
      setIsFetchingLocation(true);
      setLocationError(null);
      let { status } = await Location.requestForegroundPermissionsAsync();
      if (status !== 'granted') {
        const fallbackLat = site.latitude ? parseFloat(site.latitude) : 19.0760;
        const fallbackLng = site.longitude ? parseFloat(site.longitude) : 72.8777;
        const fallback = { latitude: fallbackLat, longitude: fallbackLng, isFallback: true };
        setCurrentCoords(fallback);
        setLocationError('GPS permission denied. Using estimated location.');
        return fallback;
      }

      let location = await Location.getCurrentPositionAsync({ accuracy: Location.Accuracy.Balanced });
      const coords = {
        latitude: parseFloat(location.coords.latitude.toFixed(6)),
        longitude: parseFloat(location.coords.longitude.toFixed(6)),
        isFallback: false,
      };
      setCurrentCoords(coords);
      return coords;
    } catch (err) {
      console.log('Location error:', err);
      const fallbackLat = site.latitude ? parseFloat(site.latitude) : 19.0760;
      const fallbackLng = site.longitude ? parseFloat(site.longitude) : 72.8777;
      const fallback = { latitude: fallbackLat, longitude: fallbackLng, isFallback: true };
      setCurrentCoords(fallback);
      setLocationError('Could not fetch GPS. Using fallback.');
      return fallback;
    } finally {
      setIsFetchingLocation(false);
    }
  };

  const handleOpenAdaModal = () => {
    setShowAdaModal(true);
    fetchCurrentLocation();
  };

  const handleSaveAda = async () => {
    try {
      setIsSubmittingAda(true);
      let coords = currentCoords;
      if (!coords || !coords.latitude) {
        coords = await fetchCurrentLocation();
      }

      const assignmentId = site.assignment_id || site.id || siteId;
      const res = await engineerApi.submitADA(assignmentId, coords.latitude, coords.longitude);

      if (res && res.success) {
        const nowFormatted = new Date().toISOString().replace('T', ' ').substring(0, 19);
        Alert.alert(
          'Arrival Recorded',
          'Site Arrival (ADA) recorded successfully! Feasibility Inspection is now unlocked.'
        );
        setSite((prev) => ({
          ...prev,
          ada_datetime: res.data?.ada_datetime || nowFormatted,
          ada_latitude: coords.latitude,
          ada_longitude: coords.longitude,
          feasibility_status: 'ada_submitted',
          status: 'in_progress',
        }));
        setShowAdaModal(false);
      } else {
        Alert.alert('Error', res.message || 'Failed to submit ADA');
      }
    } catch (e) {
      const msg = e.response?.data?.message || e.response?.data?.error || e.message || 'Failed to submit ADA';
      Alert.alert('Error', msg);
    } finally {
      setIsSubmittingAda(false);
    }
  };

  const handleUpdateStatus = async (newStatus) => {
    try {
      setIsUpdatingStatus(true);
      const assignmentId = site.assignment_id || site.id || siteId;
      const res = await engineerApi.updateStatus(assignmentId, newStatus);
      if (res && res.success) {
        Alert.alert('Status Updated', `Site status changed to ${newStatus}.`);
        setSite((prev) => ({ ...prev, status: newStatus }));
      } else {
        Alert.alert('Error', res.message || 'Failed to update status');
      }
    } catch (e) {
      Alert.alert('Error', 'Failed to update status.');
    } finally {
      setIsUpdatingStatus(false);
    }
  };

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

  return (
    <SafeAreaView style={styles.safeArea}>
      <Header
        title={site.atm_id || site.site_name || `Site #${siteId}`}
        subtitle={site.bank_name || 'Site Details'}
        showBack
        onBack={() => navigation.goBack()}
      />

      {isLoading ? (
        <View style={styles.loaderBox}>
          <ActivityIndicator size="large" color={COLORS.primary} />
          <Text style={styles.loaderText}>Loading site details...</Text>
        </View>
      ) : (
        <ScrollView contentContainerStyle={styles.scrollContent}>
          {/* Status & Quick Action Banner */}
          <View style={[styles.card, SHADOWS.small]}>
            <View style={styles.statusRow}>
              <View>
                <Text style={styles.labelMuted}>Current Status</Text>
                <Text style={styles.statusText}>{site.status?.toUpperCase() || 'ASSIGNED'}</Text>
              </View>
              <View style={styles.statusButtonsRow}>
                {site.status !== 'in_progress' && site.status !== 'completed' && (
                  <TouchableOpacity
                    onPress={() => handleUpdateStatus('in_progress')}
                    style={[styles.statusToggleBtn, { backgroundColor: COLORS.warningLight }]}
                  >
                    <Text style={{ color: COLORS.warning, fontWeight: '700', fontSize: 12 }}>
                      Start Visit
                    </Text>
                  </TouchableOpacity>
                )}
                {site.status !== 'completed' && (
                  <TouchableOpacity
                    onPress={() => handleUpdateStatus('completed')}
                    style={[styles.statusToggleBtn, { backgroundColor: COLORS.successLight }]}
                  >
                    <Text style={{ color: COLORS.success, fontWeight: '700', fontSize: 12 }}>
                      Complete
                    </Text>
                  </TouchableOpacity>
                )}
              </View>
            </View>

            {/* ETA info & Button */}
            <View style={styles.etaRow}>
              <View style={styles.etaInfo}>
                <Ionicons
                  name={hasAda ? "lock-closed" : "time"}
                  size={18}
                  color={hasAda ? COLORS.textSecondary : COLORS.primary}
                />
                <View>
                  <Text style={styles.etaLabelSmall}>Visit ETA</Text>
                  <Text style={[styles.etaLabel, hasAda && { color: COLORS.textPrimary }]}>
                    {hasEta ? (site.eta || site.eta_datetime) : 'No ETA scheduled'}
                  </Text>
                </View>
              </View>

              {!hasAda ? (
                <TouchableOpacity
                  onPress={() => setShowEtaModal(true)}
                  style={styles.etaBtn}
                >
                  <Text style={styles.etaBtnText}>{hasEta ? 'Edit ETA' : 'Set ETA'}</Text>
                </TouchableOpacity>
              ) : (
                <View style={[styles.badge, { backgroundColor: '#f1f5f9', flexDirection: 'row', alignItems: 'center', gap: 4 }]}>
                  <Ionicons name="lock-closed" size={12} color={COLORS.textSecondary} />
                  <Text style={{ color: COLORS.textSecondary, fontSize: 11, fontWeight: '700' }}>Locked (Arrived)</Text>
                </View>
              )}
            </View>

            {/* ADA (Arrival) info & Button */}
            <View style={[styles.etaRow, { marginTop: 8, backgroundColor: hasAda ? `${COLORS.success}10` : `${COLORS.warning}10` }]}>
              <View style={styles.etaInfo}>
                <Ionicons
                  name={hasAda ? "checkmark-circle" : "location"}
                  size={18}
                  color={hasAda ? COLORS.success : COLORS.warning}
                />
                <View>
                  <Text style={styles.etaLabelSmall}>Actual Arrival (ADA)</Text>
                  <Text style={[styles.etaLabel, { color: hasAda ? COLORS.success : COLORS.warning }]}>
                    {hasAda ? (site.ada_datetime || 'Arrived on Site') : 'Not Arrived Yet'}
                  </Text>
                </View>
              </View>

              {!hasAda ? (
                <TouchableOpacity
                  onPress={() => {
                    if (!hasEta) {
                      Alert.alert('ETA Required', 'Please set Visit ETA first before marking arrival.');
                      return;
                    }
                    handleOpenAdaModal();
                  }}
                  style={[styles.etaBtn, { backgroundColor: hasEta ? COLORS.warning : '#94a3b8' }]}
                >
                  <Text style={styles.etaBtnText}>Mark Arrival</Text>
                </TouchableOpacity>
              ) : (
                <View style={[styles.badge, { backgroundColor: COLORS.successLight }]}>
                  <Text style={{ color: COLORS.success, fontSize: 11, fontWeight: '700' }}>Logged</Text>
                </View>
              )}
            </View>
          </View>

          {/* Location & Navigation Card */}
          <View style={[styles.card, SHADOWS.small]}>
            <Text style={styles.cardHeader}>Location & Navigation</Text>

            <View style={styles.detailItem}>
              <Ionicons name="business-outline" size={20} color={COLORS.textSecondary} />
              <View style={styles.detailTextCol}>
                <Text style={styles.detailLabel}>Bank & Customer</Text>
                <Text style={styles.detailValue}>
                  {site.bank_name || 'N/A'} {site.customer_name ? `• ${site.customer_name}` : ''}
                </Text>
              </View>
            </View>

            <View style={styles.detailItem}>
              <Ionicons name="location-outline" size={20} color={COLORS.textSecondary} />
              <View style={styles.detailTextCol}>
                <Text style={styles.detailLabel}>Full Address</Text>
                <Text style={styles.detailValue}>{site.address || 'N/A'}</Text>
                <Text style={styles.detailSubValue}>
                  {[site.city, site.state, site.pincode].filter(Boolean).join(', ')}
                </Text>
              </View>
            </View>

            {/* Maps CTA */}
            <CustomButton
              title="Open in Google Maps"
              icon="navigate-outline"
              variant="outline"
              onPress={handleOpenMaps}
              style={styles.mapBtn}
            />
          </View>

          {/* Contact Person Card */}
          <View style={[styles.card, SHADOWS.small]}>
            <Text style={styles.cardHeader}>Site Contact Person</Text>
            <View style={styles.contactRow}>
              <View>
                <Text style={styles.contactName}>{site.contact_person || 'Site In-Charge'}</Text>
                <Text style={styles.contactPhone}>{site.contact_number || 'No phone listed'}</Text>
              </View>
              {site.contact_number ? (
                <TouchableOpacity
                  onPress={() => handleCall(site.contact_number)}
                  style={styles.callButton}
                >
                  <Ionicons name="call" size={18} color="#fff" />
                  <Text style={styles.callBtnText}>Call</Text>
                </TouchableOpacity>
              ) : null}
            </View>
          </View>

          {/* Feasibility Inspection CTA */}
          {(() => {
            const isFeasibilityUnlocked = hasEta && hasAda;
            const isRejected = Boolean(
              site?.feasibility_status &&
              ['contractor_rejected', 'adv_rejected'].includes(site.feasibility_status)
            );
            const isSubmitted = Boolean(
              site?.feasibility_status &&
              ['pending_contractor_review', 'feasibility_completed', 'contractor_approved', 'adv_approved'].includes(site.feasibility_status)
            );

            return (
              <View style={[styles.card, SHADOWS.small, styles.feasibilityCard, isFeasibilityUnlocked && styles.feasibilityCardUnlocked]}>
                <View style={styles.feasHeaderRow}>
                  <View>
                    <Text style={styles.cardHeader}>Feasibility Inspection</Text>
                    <Text style={styles.feasSub}>
                      Status: {site.feasibility_status?.replace(/_/g, ' ')?.toUpperCase() || 'PENDING'}
                    </Text>
                  </View>
                  <Ionicons
                    name={isSubmitted ? "shield-checkmark" : isFeasibilityUnlocked ? "clipboard-outline" : "lock-closed-outline"}
                    size={32}
                    color={isSubmitted ? COLORS.success : isFeasibilityUnlocked ? COLORS.primary : COLORS.textMuted}
                  />
                </View>

                {isRejected ? (
                  <View style={[styles.feasibilityWarningBox, { backgroundColor: COLORS.dangerLight, borderColor: COLORS.dangerBorder }]}>
                    <Ionicons name="alert-circle" size={20} color={COLORS.danger} />
                    <View style={{ flex: 1 }}>
                      <Text style={{ fontSize: 12, fontWeight: '700', color: COLORS.dangerForeground }}>
                        Survey Rejected by {site.feasibility_status === 'adv_rejected' ? 'ADV Reviewer' : 'Contractor Admin'}
                      </Text>
                      <Text style={{ fontSize: 11, color: COLORS.dangerForeground, marginTop: 2, lineHeight: 15 }}>
                        Specific sections were flagged for revision. Open the survey to review remarks, update measurements/snaps, and resubmit.
                      </Text>
                    </View>
                  </View>
                ) : isSubmitted ? (
                  <View style={[styles.feasibilityWarningBox, { backgroundColor: '#f0fdf4', borderColor: '#bbf7d0' }]}>
                    <Ionicons name="checkmark-circle" size={18} color="#16a34a" />
                    <Text style={[styles.feasibilityWarningText, { color: '#16a34a', fontWeight: '600' }]}>
                      Feasibility check submitted. You can review all entered details and attached photos.
                    </Text>
                  </View>
                ) : !hasEta ? (
                  <View style={styles.feasibilityWarningBox}>
                    <Ionicons name="alert-circle-outline" size={18} color={COLORS.warning} />
                    <Text style={styles.feasibilityWarningText}>
                      Step 1: Visit ETA is required before you can start Feasibility inspection.
                    </Text>
                  </View>
                ) : !hasAda ? (
                  <View style={[styles.feasibilityWarningBox, { backgroundColor: '#eff6ff', borderColor: '#bfdbfe' }]}>
                    <Ionicons name="navigate-circle-outline" size={18} color={COLORS.primary} />
                    <Text style={[styles.feasibilityWarningText, { color: COLORS.primary }]}>
                      Step 2: Please mark Site Arrival (ADA) upon reaching the location to unlock Feasibility inspection.
                    </Text>
                  </View>
                ) : null}

                <CustomButton
                  title={
                    isRejected
                      ? 'Fix & Resubmit Feasibility'
                      : isSubmitted
                      ? 'View Submitted Feasibility'
                      : 'Start Feasibility Check'
                  }
                  icon={isRejected ? "construct-outline" : isSubmitted ? "eye-outline" : isFeasibilityUnlocked ? "create-outline" : "lock-closed-outline"}
                  variant={isRejected ? "destructive" : "default"}
                  disabled={!isFeasibilityUnlocked && !isSubmitted}
                  onPress={() => {
                    if (!hasEta) {
                      Alert.alert(
                        'ETA Required',
                        'Please set your Visit ETA first.',
                        [
                          { text: 'Cancel', style: 'cancel' },
                          { text: 'Set ETA', onPress: () => setShowEtaModal(true) }
                        ]
                      );
                      return;
                    }
                    if (!hasAda) {
                      Alert.alert(
                        'Arrival (ADA) Required',
                        'Please mark your Site Arrival (ADA) before starting the Feasibility inspection form.',
                        [
                          { text: 'Cancel', style: 'cancel' },
                          { text: 'Mark Arrival', onPress: handleOpenAdaModal }
                        ]
                      );
                      return;
                    }
                    navigation.navigate('Feasibility', {
                      siteId,
                      site,
                      assignmentId: site.assignment_id || site.id || siteId,
                    });
                  }}
                  style={styles.feasBtn}
                />

                {!hasEta ? (
                  <TouchableOpacity
                    onPress={() => setShowEtaModal(true)}
                    style={styles.setEtaPromptBtn}
                  >
                    <Ionicons name="time-outline" size={16} color={COLORS.primary} />
                    <Text style={styles.setEtaPromptText}>Set Visit ETA</Text>
                  </TouchableOpacity>
                ) : !hasAda ? (
                  <TouchableOpacity
                    onPress={handleOpenAdaModal}
                    style={[styles.setEtaPromptBtn, { backgroundColor: `${COLORS.warning}18` }]}
                  >
                    <Ionicons name="location-outline" size={16} color={COLORS.warning} />
                    <Text style={[styles.setEtaPromptText, { color: COLORS.warning }]}>Mark Arrival (ADA) to Unlock</Text>
                  </TouchableOpacity>
                ) : null}
              </View>
            );
          })()}
        </ScrollView>
      )}

      {/* ETA Date & Time Picker Modal */}
      <Modal visible={showEtaModal} transparent animationType="slide">
        <View style={styles.modalBackdrop}>
          <View style={styles.modalBox}>
            <View style={styles.modalHeader}>
              <Text style={styles.modalTitle}>Set Visit ETA</Text>
              <TouchableOpacity onPress={() => setShowEtaModal(false)}>
                <Ionicons name="close" size={24} color={COLORS.textPrimary} />
              </TouchableOpacity>
            </View>

            {/* Quick Presets */}
            <Text style={styles.presetLabel}>Quick Presets:</Text>
            <View style={styles.presetsRow}>
              <TouchableOpacity onPress={() => applyPreset(1)} style={styles.presetChip}>
                <Text style={styles.presetChipText}>+1 hr</Text>
              </TouchableOpacity>
              <TouchableOpacity onPress={() => applyPreset(2)} style={styles.presetChip}>
                <Text style={styles.presetChipText}>+2 hrs</Text>
              </TouchableOpacity>
              <TouchableOpacity onPress={() => applyPreset(4)} style={styles.presetChip}>
                <Text style={styles.presetChipText}>+4 hrs</Text>
              </TouchableOpacity>
              <TouchableOpacity onPress={() => applyPreset(24, 10)} style={styles.presetChip}>
                <Text style={styles.presetChipText}>Tomorrow 10 AM</Text>
              </TouchableOpacity>
            </View>

            {/* Date & Time Selectors */}
            <View style={styles.dateTimeContainer}>
              {/* Date Card */}
              <TouchableOpacity
                onPress={() => openDatePicker('date')}
                style={styles.pickerTriggerCard}
              >
                <Ionicons name="calendar-outline" size={22} color={COLORS.primary} />
                <View style={{ marginLeft: 10, flex: 1 }}>
                  <Text style={styles.triggerLabel}>Visit Date</Text>
                  <Text style={styles.triggerValue}>{formatDisplayDate(etaDate)}</Text>
                </View>
                <Ionicons name="chevron-forward" size={16} color={COLORS.textMuted} />
              </TouchableOpacity>

              {/* Time Card */}
              <TouchableOpacity
                onPress={() => openDatePicker('time')}
                style={styles.pickerTriggerCard}
              >
                <Ionicons name="time-outline" size={22} color={COLORS.primary} />
                <View style={{ marginLeft: 10, flex: 1 }}>
                  <Text style={styles.triggerLabel}>Arrival Time</Text>
                  <Text style={styles.triggerValue}>{formatDisplayTime(etaDate)}</Text>
                </View>
                <Ionicons name="chevron-forward" size={16} color={COLORS.textMuted} />
              </TouchableOpacity>
            </View>

            {/* Remarks Input */}
            <Text style={styles.inputLabel}>Remarks (Optional)</Text>
            <TextInput
              style={[styles.modalInput, styles.textArea]}
              placeholder="e.g. Traveling by bike, tools ready, traffic delay"
              multiline
              numberOfLines={3}
              value={etaRemarks}
              onChangeText={setEtaRemarks}
            />

            {/* Native DateTimePicker component */}
            {showPicker && (
              <DateTimePicker
                value={etaDate}
                mode={pickerMode}
                is24Hour={false}
                display={Platform.OS === 'ios' ? 'spinner' : 'default'}
                minimumDate={new Date()}
                onChange={onDateChange}
              />
            )}

            <View style={styles.modalActions}>
              <CustomButton
                title="Cancel"
                variant="outline"
                onPress={() => setShowEtaModal(false)}
                style={{ flex: 1 }}
              />
              <CustomButton
                title="Save ETA"
                loading={isUpdatingEta}
                onPress={handleSaveEta}
                icon="checkmark-outline"
                style={{ flex: 1 }}
              />
            </View>
          </View>
        </View>
      </Modal>

      {/* ADA (Arrival) Modal */}
      <Modal visible={showAdaModal} transparent animationType="slide">
        <View style={styles.modalBackdrop}>
          <View style={styles.modalBox}>
            <View style={styles.modalHeader}>
              <View>
                <Text style={styles.modalTitle}>Mark Site Arrival (ADA)</Text>
                <Text style={styles.modalSubtitle}>Record arrival timestamp & GPS location</Text>
              </View>
              <TouchableOpacity onPress={() => setShowAdaModal(false)}>
                <Ionicons name="close" size={24} color={COLORS.textPrimary} />
              </TouchableOpacity>
            </View>

            {/* Arrival Time Box */}
            <View style={styles.adaTimeCard}>
              <Ionicons name="time-outline" size={22} color={COLORS.primary} />
              <View style={{ marginLeft: 10, flex: 1 }}>
                <Text style={styles.triggerLabel}>Arrival Timestamp</Text>
                <Text style={styles.triggerValue}>
                  {formatDisplayDate(new Date())} • {formatDisplayTime(new Date())}
                </Text>
              </View>
              <View style={[styles.badge, { backgroundColor: COLORS.successLight }]}>
                <Text style={{ color: COLORS.success, fontSize: 11, fontWeight: '700' }}>Live</Text>
              </View>
            </View>

            {/* Live GPS Coordinates Card */}
            <View style={styles.gpsCard}>
              <View style={styles.gpsHeader}>
                <View style={{ flexDirection: 'row', alignItems: 'center', gap: 6 }}>
                  <Ionicons name="navigate-circle" size={20} color={COLORS.primary} />
                  <Text style={styles.gpsTitle}>GPS Location</Text>
                </View>
                <TouchableOpacity
                  onPress={fetchCurrentLocation}
                  disabled={isFetchingLocation}
                  style={styles.refreshGpsBtn}
                >
                  {isFetchingLocation ? (
                    <ActivityIndicator size="small" color={COLORS.primary} />
                  ) : (
                    <>
                      <Ionicons name="refresh" size={14} color={COLORS.primary} />
                      <Text style={styles.refreshGpsText}>Refresh GPS</Text>
                    </>
                  )}
                </TouchableOpacity>
              </View>

              {currentCoords ? (
                <View style={styles.coordsRow}>
                  <View style={styles.coordCol}>
                    <Text style={styles.coordLabel}>Latitude</Text>
                    <Text style={styles.coordVal}>{currentCoords.latitude}</Text>
                  </View>
                  <View style={styles.coordDivider} />
                  <View style={styles.coordCol}>
                    <Text style={styles.coordLabel}>Longitude</Text>
                    <Text style={styles.coordVal}>{currentCoords.longitude}</Text>
                  </View>
                </View>
              ) : (
                <View style={{ paddingVertical: 10, alignItems: 'center' }}>
                  <ActivityIndicator size="small" color={COLORS.primary} />
                  <Text style={{ color: COLORS.textSecondary, fontSize: 12, marginTop: 4 }}>
                    Fetching device location...
                  </Text>
                </View>
              )}

              {locationError ? (
                <Text style={styles.locationErrorText}>{locationError}</Text>
              ) : null}
            </View>

            <View style={styles.siteReferenceBox}>
              <Ionicons name="business" size={16} color={COLORS.textSecondary} />
              <Text style={styles.siteRefText} numberOfLines={2}>
                {site.atm_id || site.site_name} • {site.address || `${site.city || ''}, ${site.state || ''}`}
              </Text>
            </View>

            <View style={styles.modalActions}>
              <CustomButton
                title="Cancel"
                variant="outline"
                onPress={() => setShowAdaModal(false)}
                style={{ flex: 1 }}
              />
              <CustomButton
                title="Confirm Arrival"
                loading={isSubmittingAda}
                disabled={isFetchingLocation}
                onPress={handleSaveAda}
                icon="checkmark-circle-outline"
                style={{ flex: 1 }}
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
  scrollContent: {
    padding: SPACING.md,
    paddingBottom: SPACING.xxl,
  },
  loaderBox: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
  },
  loaderText: {
    marginTop: SPACING.sm,
    color: COLORS.textSecondary,
  },
  card: {
    backgroundColor: COLORS.cardBg,
    borderRadius: 16,
    padding: SPACING.md,
    marginBottom: SPACING.md,
    borderWidth: 1,
    borderColor: COLORS.border,
  },
  cardHeader: {
    fontSize: FONTS.subtitle,
    fontWeight: '700',
    color: COLORS.textPrimary,
    marginBottom: SPACING.sm,
  },
  statusRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: SPACING.sm,
  },
  labelMuted: {
    fontSize: 11,
    color: COLORS.textSecondary,
  },
  statusText: {
    fontSize: FONTS.title,
    fontWeight: '800',
    color: COLORS.primary,
  },
  statusButtonsRow: {
    flexDirection: 'row',
    gap: 6,
  },
  statusToggleBtn: {
    paddingHorizontal: 10,
    paddingVertical: 6,
    borderRadius: 8,
  },
  etaRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    backgroundColor: `${COLORS.primary}10`,
    padding: SPACING.sm,
    borderRadius: 10,
    marginTop: 4,
  },
  etaInfo: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    flex: 1,
  },
  etaLabelSmall: {
    fontSize: 10,
    color: COLORS.textSecondary,
    fontWeight: '600',
    textTransform: 'uppercase',
  },
  etaLabel: {
    fontSize: FONTS.body,
    fontWeight: '700',
    color: COLORS.primary,
  },
  etaBtn: {
    backgroundColor: COLORS.primary,
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: 8,
  },
  etaBtnText: {
    color: '#fff',
    fontSize: 12,
    fontWeight: '700',
  },
  badge: {
    paddingHorizontal: 8,
    paddingVertical: 3,
    borderRadius: 6,
  },
  detailItem: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    gap: 12,
    marginBottom: SPACING.sm,
  },
  detailTextCol: {
    flex: 1,
  },
  detailLabel: {
    fontSize: 11,
    color: COLORS.textSecondary,
  },
  detailValue: {
    fontSize: FONTS.body,
    fontWeight: '700',
    color: COLORS.textPrimary,
  },
  detailSubValue: {
    fontSize: FONTS.small,
    color: COLORS.textSecondary,
    marginTop: 2,
  },
  mapBtn: {
    marginTop: SPACING.xs,
  },
  contactRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  contactName: {
    fontSize: FONTS.body,
    fontWeight: '700',
    color: COLORS.textPrimary,
  },
  contactPhone: {
    fontSize: FONTS.small,
    color: COLORS.textSecondary,
    marginTop: 2,
  },
  callButton: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: COLORS.success,
    paddingHorizontal: 14,
    paddingVertical: 8,
    borderRadius: 10,
    gap: 6,
  },
  callBtnText: {
    color: '#fff',
    fontWeight: '700',
    fontSize: 13,
  },
  feasibilityCard: {
    borderLeftWidth: 4,
    borderLeftColor: COLORS.border,
  },
  feasibilityCardUnlocked: {
    borderLeftColor: COLORS.primary,
  },
  feasHeaderRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  feasSub: {
    fontSize: FONTS.small,
    color: COLORS.textSecondary,
  },
  feasBtn: {
    marginTop: SPACING.md,
  },
  feasibilityWarningBox: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#fffbeb',
    borderColor: '#fde68a',
    borderWidth: 1,
    padding: SPACING.sm,
    borderRadius: 8,
    marginTop: SPACING.sm,
    gap: 8,
  },
  feasibilityWarningText: {
    fontSize: 12,
    color: '#b45309',
    flex: 1,
    fontWeight: '600',
  },
  setEtaPromptBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: `${COLORS.primary}12`,
    paddingVertical: 10,
    borderRadius: 8,
    marginTop: 8,
    gap: 6,
  },
  setEtaPromptText: {
    color: COLORS.primary,
    fontWeight: '700',
    fontSize: 13,
  },
  modalBackdrop: {
    flex: 1,
    backgroundColor: 'rgba(0,0,0,0.6)',
    justifyContent: 'center',
    padding: SPACING.md,
  },
  modalBox: {
    backgroundColor: COLORS.cardBg,
    borderRadius: 20,
    padding: SPACING.lg,
  },
  modalHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: SPACING.md,
  },
  modalTitle: {
    fontSize: FONTS.title,
    fontWeight: '800',
    color: COLORS.textPrimary,
  },
  modalSubtitle: {
    fontSize: 11,
    color: COLORS.textSecondary,
    marginTop: 2,
  },
  inputLabel: {
    fontSize: FONTS.small,
    fontWeight: '600',
    color: COLORS.textPrimary,
    marginBottom: 6,
    marginTop: SPACING.xs,
  },
  modalInput: {
    backgroundColor: COLORS.inputBg,
    borderWidth: 1,
    borderColor: COLORS.border,
    borderRadius: 10,
    padding: 10,
    fontSize: FONTS.body,
    color: COLORS.textPrimary,
    marginBottom: SPACING.sm,
  },
  textArea: {
    height: 70,
    textAlignVertical: 'top',
  },
  modalActions: {
    flexDirection: 'row',
    gap: 10,
    marginTop: SPACING.md,
  },
  presetLabel: {
    fontSize: 12,
    fontWeight: '600',
    color: COLORS.textSecondary,
    marginBottom: 6,
  },
  presetsRow: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 6,
    marginBottom: SPACING.md,
  },
  presetChip: {
    backgroundColor: `${COLORS.primary}12`,
    paddingHorizontal: 10,
    paddingVertical: 5,
    borderRadius: 14,
    borderWidth: 1,
    borderColor: `${COLORS.primary}30`,
  },
  presetChipText: {
    fontSize: 11,
    fontWeight: '600',
    color: COLORS.primary,
  },
  dateTimeContainer: {
    gap: 8,
    marginBottom: SPACING.md,
  },
  pickerTriggerCard: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: COLORS.inputBg,
    borderRadius: 12,
    borderWidth: 1,
    borderColor: COLORS.border,
    padding: 12,
  },
  adaTimeCard: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: `${COLORS.primary}10`,
    borderRadius: 12,
    padding: 12,
    marginBottom: SPACING.sm,
  },
  gpsCard: {
    backgroundColor: COLORS.inputBg,
    borderRadius: 12,
    borderWidth: 1,
    borderColor: COLORS.border,
    padding: 12,
    marginBottom: SPACING.sm,
  },
  gpsHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 8,
  },
  gpsTitle: {
    fontSize: 13,
    fontWeight: '700',
    color: COLORS.textPrimary,
  },
  refreshGpsBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
    backgroundColor: `${COLORS.primary}15`,
    paddingHorizontal: 8,
    paddingVertical: 4,
    borderRadius: 6,
  },
  refreshGpsText: {
    fontSize: 11,
    fontWeight: '600',
    color: COLORS.primary,
  },
  coordsRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-around',
    backgroundColor: COLORS.cardBg,
    padding: 8,
    borderRadius: 8,
    borderWidth: 1,
    borderColor: COLORS.border,
  },
  coordCol: {
    alignItems: 'center',
  },
  coordLabel: {
    fontSize: 10,
    color: COLORS.textSecondary,
    fontWeight: '500',
  },
  coordVal: {
    fontSize: 13,
    fontWeight: '700',
    color: COLORS.primary,
    marginTop: 2,
  },
  coordDivider: {
    width: 1,
    height: 24,
    backgroundColor: COLORS.border,
  },
  locationErrorText: {
    fontSize: 11,
    color: COLORS.warning,
    marginTop: 6,
  },
  siteReferenceBox: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    padding: 8,
    backgroundColor: '#f8fafc',
    borderRadius: 8,
    marginBottom: SPACING.xs,
  },
  siteRefText: {
    fontSize: 11,
    color: COLORS.textSecondary,
    flex: 1,
  },
  triggerLabel: {
    fontSize: 11,
    color: COLORS.textSecondary,
    fontWeight: '500',
  },
  triggerValue: {
    fontSize: FONTS.body,
    fontWeight: '700',
    color: COLORS.textPrimary,
    marginTop: 2,
  },
});
