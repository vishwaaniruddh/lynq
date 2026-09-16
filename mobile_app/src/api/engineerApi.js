import { Platform } from 'react-native';
import AsyncStorage from '@react-native-async-storage/async-storage';
import apiClient, { getApiBaseUrl } from './client';
import { STORAGE_KEYS } from '../constants/config';

export const engineerApi = {
  // Get all assigned sites for the logged-in engineer
  getAssignments: async (filters = {}) => {
    const params = new URLSearchParams();
    if (filters.search) params.append('search', filters.search);
    if (filters.status) params.append('status', filters.status);
    if (filters.feasibility_status) params.append('feasibility_status', filters.feasibility_status);
    if (filters.city) params.append('city', filters.city);
    if (filters.state) params.append('state', filters.state);
    if (filters.page) params.append('page', filters.page);
    if (filters.limit) params.append('limit', filters.limit || 20);

    const query = params.toString();
    const url = `/api/engineer/index.php${query ? `?${query}` : ''}`;
    const response = await apiClient.get(url);
    return response.data;
  },

  // Get status counts (total, assigned, in_progress, completed)
  getCounts: async () => {
    const response = await apiClient.post('/api/engineer/index.php', {
      action: 'get_counts',
    });
    return response.data;
  },

  // Get available filter values (cities, states)
  getFilters: async () => {
    const response = await apiClient.post('/api/engineer/index.php', {
      action: 'get_filters',
    });
    return response.data;
  },

  // Update assignment status
  updateStatus: async (assignmentId, status) => {
    const response = await apiClient.post('/api/engineer/index.php', {
      action: 'update_status',
      assignment_id: assignmentId,
      status: status,
    });
    return response.data;
  },

  // Get site details
  getSiteDetails: async (siteId) => {
    const response = await apiClient.get(`/api/engineer/site.php?site_id=${siteId}`);
    return response.data;
  },

  // Update ETA for site visit
  updateETA: async (assignmentId, etaDateTime, remarks = '', siteId = null) => {
    const response = await apiClient.post('/api/engineer/eta.php', {
      assignment_id: assignmentId,
      site_id: siteId,
      eta_datetime: etaDateTime,
      remarks: remarks,
    });
    return response.data;
  },

  // Submit ADA (Actual Date of Arrival) with GPS coordinates
  submitADA: async (assignmentId, latitude, longitude) => {
    const response = await apiClient.post('/api/engineer/ada.php', {
      assignment_id: assignmentId,
      latitude: latitude,
      longitude: longitude,
    });
    return response.data;
  },

  // Get ADA details
  getADA: async (assignmentId) => {
    const response = await apiClient.get(`/api/engineer/ada.php?assignment_id=${assignmentId}`);
    return response.data;
  },

  // Get feasibility details / existing form & rejection feedback
  getFeasibility: async (assignmentId) => {
    const response = await apiClient.get(`/api/app/feasibility.php?assignment_id=${assignmentId}`);
    return response.data;
  },

  // Save feasibility draft or submit
  saveFeasibility: async (formData) => {
    const response = await apiClient.post('/api/app/feasibility.php', formData);
    return response.data;
  },

  // Upload feasibility category image with robust multipart handling
  uploadFeasibilityImage: async (feasibilityId, category, imageUri) => {
    try {
      // Use expo-file-system uploadAsync for reliable multipart uploads
      const FileSystem = require('expo-file-system/legacy');

      const token = await AsyncStorage.getItem(STORAGE_KEYS.ACCESS_TOKEN);
      const baseUrl = getApiBaseUrl().replace(/\/+$/, '');
      const uploadUrl = `${baseUrl}/api/app/feasibility.php?action=upload`;

      const filename = imageUri.split('/').pop() || `${category}_${Date.now()}.jpg`;

      const response = await FileSystem.uploadAsync(uploadUrl, imageUri, {
        httpMethod: 'POST',
        uploadType: FileSystem.FileSystemUploadType.MULTIPART,
        fieldName: 'file',
        parameters: {
          action: 'upload',
          feasibility_id: String(feasibilityId),
          category: category,
        },
        headers: {
          'Accept': 'application/json',
          ...(token ? { 'Authorization': `Bearer ${token}` } : {}),
        },
      });

      const result = JSON.parse(response.body);
      return result;
    } catch (e) {
      console.log('uploadFeasibilityImage error:', e);
      throw e;
    }
  },

  // Resubmit feasibility if requested
  resubmitFeasibility: async (formData) => {
    const response = await apiClient.post('/api/app/feasibility.php', formData);
    return response.data;
  },

  // -------------------------------------------------------------
  // INSTALLATION APIs (Separate assignment workflow from feasibility)
  // -------------------------------------------------------------
  getInstallationAssignments: async (filters = {}) => {
    const params = new URLSearchParams();
    if (filters.search) params.append('search', filters.search);
    if (filters.status) params.append('status', filters.status);
    if (filters.page) params.append('page', filters.page);
    if (filters.limit) params.append('limit', filters.limit || 50);

    const query = params.toString();
    const url = `/api/app/installation.php${query ? `?${query}` : ''}`;
    const response = await apiClient.get(url);
    return response.data;
  },

  getInstallationDetails: async (installationId) => {
    const response = await apiClient.get(`/api/app/installation.php?installation_id=${installationId}`);
    return response.data;
  },

  submitInstallationETA: async (installationId, etaDate) => {
    const response = await apiClient.post('/api/app/installation.php', {
      action: 'eta',
      installation_id: installationId,
      eta_date: etaDate,
    });
    return response.data;
  },

  submitInstallationADA: async (installationId, adaDate = null) => {
    const response = await apiClient.post('/api/app/installation.php', {
      action: 'ada',
      installation_id: installationId,
      ada_date: adaDate,
    });
    return response.data;
  },

  confirmMaterialsReceived: async (installationId) => {
    const response = await apiClient.post('/api/app/installation.php', {
      action: 'confirm_materials',
      installation_id: installationId,
    });
    return response.data;
  },

  saveInstallation: async (installationId, data) => {
    const response = await apiClient.post('/api/app/installation.php', {
      action: 'save',
      installation_id: installationId,
      ...data,
    });
    return response.data;
  },

  submitInstallationForm: async (installationId) => {
    const response = await apiClient.post('/api/app/installation.php', {
      action: 'submit',
      installation_id: installationId,
    });
    return response.data;
  },

  uploadInstallationImage: async (installationId, section, imageUri) => {
    try {
      const FileSystem = require('expo-file-system/legacy');

      const token = await AsyncStorage.getItem(STORAGE_KEYS.ACCESS_TOKEN);
      const baseUrl = getApiBaseUrl().replace(/\/+$/, '');
      const uploadUrl = `${baseUrl}/api/app/installation.php?action=upload`;

      const response = await FileSystem.uploadAsync(uploadUrl, imageUri, {
        httpMethod: 'POST',
        uploadType: FileSystem.FileSystemUploadType.MULTIPART,
        fieldName: 'file',
        parameters: {
          installation_id: String(installationId),
          section: section,
        },
        headers: {
          'Accept': 'application/json',
          ...(token ? { 'Authorization': `Bearer ${token}` } : {}),
        },
      });

      const result = JSON.parse(response.body);
      return result;
    } catch (e) {
      console.log('uploadInstallationImage error:', e);
      throw e;
    }
  },

  // -------------------------------------------------------------
  // INVENTORY & PENDING RECEIVES APIs
  // -------------------------------------------------------------
  getInventory: async (filters = {}) => {
    const params = new URLSearchParams();
    if (filters.search) params.append('search', filters.search);
    if (filters.category && filters.category !== 'All') params.append('category', filters.category);
    if (filters.status) params.append('status', filters.status);

    const query = params.toString();
    const url = `/api/app/inventory.php${query ? `?${query}` : ''}`;
    const response = await apiClient.get(url);
    return response.data;
  },

  acceptPendingReceive: async (pendingReceiveId, notes = '', condition = 'good') => {
    const response = await apiClient.post('/api/app/inventory.php', {
      action: 'accept',
      pending_receive_id: pendingReceiveId,
      notes,
      condition,
    });
    return response.data;
  },

  partialAcceptPendingReceive: async (pendingReceiveId, items, notes = '') => {
    const response = await apiClient.post('/api/app/inventory.php', {
      action: 'partial_accept',
      pending_receive_id: pendingReceiveId,
      items,
      notes,
    });
    return response.data;
  },

  rejectPendingReceive: async (pendingReceiveId, reason) => {
    const response = await apiClient.post('/api/app/inventory.php', {
      action: 'reject',
      pending_receive_id: pendingReceiveId,
      reason,
    });
    return response.data;
  },

  requestMaterial: async (data) => {
    const response = await apiClient.post('/api/app/inventory.php', {
      action: 'request',
      ...data,
    });
    return response.data;
  },
};


