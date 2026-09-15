import AsyncStorage from '@react-native-async-storage/async-storage';
import axios from 'axios';

export const DEFAULT_SERVER_URL = 'http://10.0.2.2/api'; // Android Emulator default or replace with LAN IP

// Demo mock data matching LYNQ database for offline/instant testing
export const MOCK_ENGINEER_USER = {
  id: 2326,
  username: 'eng',
  first_name: 'eng',
  last_name: 'eng',
  name: 'eng eng',
  email: 'eng@eng.com',
  role: 'engineer',
  role_id: 4,
  company_id: 2,
  company_name: 'Cleared Secured Services',
  phone: '9876543210',
  active_sites: 24,
};

export const MOCK_ASSIGNED_SITES = [
  {
    id: 1,
    site_name: 'XTPL0001',
    project_id: 1,
    project_name: 'XTPL',
    bank_name: 'The Faridkot Central Cooperative Bank Ltd., Faridkot',
    customer_name: 'Hitachi',
    branch_name: 'RATTI RORI',
    branch_id: '317',
    bank_manager: 'Dalvinder',
    contact_numbers: ['7737500004'],
    city: 'Faridkot',
    state: 'Punjab',
    address: 'Village Ratti Rori, Tehsil Faridkot, Distt. Faridkot',
    status: 'assigned',
    delegation_status: 'accepted',
    feasibility_status: 'pending',
    installation_status: 'pending',
    latitude: 30.6769,
    longitude: 74.7583,
    assigned_at: '2026-08-25',
    material_status: 'dispatched',
    manifest_number: 'MF-2026-089',
    router_serial_number: 'RT-992140',
    router_ip: '10.240.12.1',
    site_ip: '172.16.4.10'
  },
  {
    id: 2,
    site_name: 'XTPL0002',
    project_id: 1,
    project_name: 'XTPL',
    bank_name: 'State Bank of India',
    customer_name: 'Hitachi',
    branch_name: 'MUMBAI CENTRAL',
    branch_id: '104',
    bank_manager: 'R. Sharma',
    contact_numbers: ['9820112233', '9820112234'],
    city: 'Mumbai',
    state: 'Maharashtra',
    address: 'Plot 45, Near Railway Station, Mumbai Central',
    status: 'in_progress',
    delegation_status: 'accepted',
    feasibility_status: 'adv_approved',
    installation_status: 'in_progress',
    latitude: 18.9696,
    longitude: 72.8193,
    assigned_at: '2026-08-24',
    material_status: 'delivered',
    manifest_number: 'MF-2026-077',
    router_serial_number: 'RT-992141',
    router_ip: '10.240.12.2',
    site_ip: '172.16.4.11'
  },
  {
    id: 3,
    site_name: 'XTPL0003',
    project_id: 1,
    project_name: 'XTPL',
    bank_name: 'Punjab National Bank',
    customer_name: 'Hitachi',
    branch_name: 'KOTKAPURA MAIN',
    branch_id: '208',
    bank_manager: 'Harpreet Singh',
    contact_numbers: ['9417234567'],
    city: 'Kotkapura',
    state: 'Punjab',
    address: 'Main Market, Kotkapura',
    status: 'completed',
    delegation_status: 'accepted',
    feasibility_status: 'adv_approved',
    installation_status: 'completed',
    latitude: 30.5828,
    longitude: 74.8294,
    assigned_at: '2026-08-22',
    material_status: 'delivered',
    manifest_number: 'MF-2026-065',
    router_serial_number: 'RT-992142',
    router_ip: '10.240.12.3',
    site_ip: '172.16.4.12'
  }
];

class ApiClient {
  constructor() {
    this.serverUrl = DEFAULT_SERVER_URL;
    this.token = null;
    this.init();
  }

  async init() {
    try {
      const savedUrl = await AsyncStorage.getItem('@lynnq_server_url');
      if (savedUrl) this.serverUrl = savedUrl;
      const savedToken = await AsyncStorage.getItem('@lynnq_auth_token');
      if (savedToken) this.token = savedToken;
    } catch (e) {
      console.warn('Could not read storage', e);
    }
  }

  async setServerUrl(url) {
    this.serverUrl = url;
    await AsyncStorage.setItem('@lynnq_server_url', url);
  }

  async login(username, password) {
    try {
      const res = await axios.post(`${this.serverUrl}/auth/login.php`, { username, password }, { timeout: 4000 });
      if (res.data && res.data.success) {
        return { success: true, user: res.data.data.user, token: res.data.data.token || 'mock_token' };
      }
    } catch (e) {
      console.log('Backend not reachable, falling back to local engineer profile', e.message);
    }

    // Fallback demo login
    if (username.toLowerCase().includes('eng') || username === 'admin') {
      return { success: true, user: MOCK_ENGINEER_USER, token: 'demo_jwt_token_engineer' };
    }
    return { success: false, message: 'Invalid credentials. Use username "eng" to test.' };
  }

  async getDashboardSummary() {
    const sites = await this.getAssignedSites();
    const total = sites.length;
    const newSites = sites.filter(s => s.status === 'assigned' || s.feasibility_status === 'pending').length;
    const inProgress = sites.filter(s => s.status === 'in_progress' || s.installation_status === 'in_progress').length;
    const completed = sites.filter(s => s.status === 'completed' || s.installation_status === 'completed').length;

    return {
      kpi: {
        total,
        new: newSites,
        inProgress,
        completed
      },
      recentSites: sites.slice(0, 5)
    };
  }

  async getAssignedSites(filter = 'all') {
    try {
      const res = await axios.get(`${this.serverUrl}/sites/index.php`, { timeout: 3000 });
      if (res.data && res.data.success && res.data.data.sites) {
        return res.data.data.sites;
      }
    } catch (e) {
      // offline fallback
    }

    let list = [...MOCK_ASSIGNED_SITES];
    if (filter === 'new') list = list.filter(s => s.status === 'assigned');
    else if (filter === 'in_progress') list = list.filter(s => s.status === 'in_progress');
    else if (filter === 'completed') list = list.filter(s => s.status === 'completed');
    return list;
  }

  async getSiteDetails(id) {
    const sites = await this.getAssignedSites();
    return sites.find(s => s.id === parseInt(id)) || sites[0];
  }

  async submitFeasibilityCheck(payload) {
    try {
      const res = await axios.post(`${this.serverUrl}/feasibility/submit.php`, payload, { timeout: 4000 });
      if (res.data && res.data.success) return res.data;
    } catch (e) {}

    // Mock local save
    const idx = MOCK_ASSIGNED_SITES.findIndex(s => s.id === payload.site_id);
    if (idx >= 0) {
      MOCK_ASSIGNED_SITES[idx].feasibility_status = 'adv_approved';
      MOCK_ASSIGNED_SITES[idx].status = 'in_progress';
    }
    return { success: true, message: 'Feasibility survey submitted successfully!' };
  }

  async updateInstallationStatus(payload) {
    try {
      const res = await axios.post(`${this.serverUrl}/installation/update.php`, payload, { timeout: 4000 });
      if (res.data && res.data.success) return res.data;
    } catch (e) {}

    // Mock local save
    const idx = MOCK_ASSIGNED_SITES.findIndex(s => s.id === payload.site_id);
    if (idx >= 0) {
      MOCK_ASSIGNED_SITES[idx].installation_status = payload.status || 'in_progress';
      if (payload.status === 'completed') MOCK_ASSIGNED_SITES[idx].status = 'completed';
    }
    return { success: true, message: 'Installation progress updated successfully!' };
  }

  async getPendingReceives() {
    return [
      {
        id: 101,
        site_name: 'XTPL0001',
        material_name: 'Standard 4G Router Kit + Antenna & CAT6',
        manifest_number: 'MF-2026-089',
        courier_name: 'BlueDart Express',
        tracking_number: 'BD889210492',
        dispatch_date: '2026-08-24',
        status: 'in_transit'
      },
      {
        id: 102,
        site_name: 'XTPL0002',
        material_name: 'Router Kit + Mounting Brackets',
        manifest_number: 'MF-2026-077',
        courier_name: 'DTDC Courier',
        tracking_number: 'DT9921044',
        dispatch_date: '2026-08-23',
        status: 'delivered'
      }
    ];
  }

  async acknowledgeMaterial(dispatchId) {
    return { success: true, message: 'Material kit receipt acknowledged!' };
  }
}

export const api = new ApiClient();
