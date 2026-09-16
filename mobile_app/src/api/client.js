import axios from 'axios';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { DEFAULT_ENVIRONMENTS, STORAGE_KEYS } from '../constants/config';

let currentBaseUrl = DEFAULT_ENVIRONMENTS[0].url;

export const setApiBaseUrl = (url) => {
  currentBaseUrl = url.replace(/\/+$/, '');
  console.log('[API Client] Base URL set to:', currentBaseUrl);
};

export const getApiBaseUrl = () => currentBaseUrl;

const apiClient = axios.create({
  timeout: 30000, // 30s — generous for flaky Wi-Fi
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
});

// Request interceptor — uses the in-memory base URL (set by AuthContext on boot)
// IMPORTANT: We do NOT read AsyncStorage here because it can contain stale
// localtunnel/ngrok URLs from previous sessions that no longer resolve.
apiClient.interceptors.request.use(
  async (config) => {
    // Always use the in-memory currentBaseUrl (set by AuthContext.bootstrapAsync or updateServerUrl)
    config.baseURL = currentBaseUrl;

    // Localtunnel requires a bypass header to avoid the interstitial page
    if (currentBaseUrl.includes('loca.lt')) {
      config.headers['Bypass-Tunnel-Reminder'] = 'true';
    }

    // Attach JWT Access Token
    try {
      const token = await AsyncStorage.getItem(STORAGE_KEYS.ACCESS_TOKEN);
      if (token) {
        config.headers.Authorization = `Bearer ${token}`;
      }
    } catch (e) {
      // Proceed without token
    }

    console.log(`[API] ${config.method?.toUpperCase()} ${config.baseURL}${config.url}`);
    return config;
  },
  (error) => Promise.reject(error)
);

// Response interceptor for error handling and token refresh
apiClient.interceptors.response.use(
  (response) => response,
  async (error) => {
    const originalRequest = error.config;

    // Log the failed request details for debugging
    if (error.code === 'ECONNABORTED') {
      console.warn(`[API] TIMEOUT: ${originalRequest?.baseURL}${originalRequest?.url} (${apiClient.defaults.timeout}ms)`);
    } else if (!error.response) {
      console.warn(`[API] NETWORK ERROR: ${originalRequest?.baseURL}${originalRequest?.url} — ${error.message}`);
    }

    if (error.response && error.response.status === 401 && !originalRequest._retry) {
      originalRequest._retry = true;
      try {
        const refreshToken = await AsyncStorage.getItem(STORAGE_KEYS.REFRESH_TOKEN);

        if (refreshToken) {
          const res = await axios.post(`${currentBaseUrl}/api/auth/refresh.php`, {
            refresh_token: refreshToken,
          });

          if (res.data && res.data.success && res.data.data.access_token) {
            const newAccessToken = res.data.data.access_token;
            await AsyncStorage.setItem(STORAGE_KEYS.ACCESS_TOKEN, newAccessToken);
            originalRequest.headers.Authorization = `Bearer ${newAccessToken}`;
            return apiClient(originalRequest);
          }
        }
      } catch (refreshErr) {
        // Clear auth tokens on refresh failure
        await AsyncStorage.multiRemove([
          STORAGE_KEYS.ACCESS_TOKEN,
          STORAGE_KEYS.REFRESH_TOKEN,
          STORAGE_KEYS.USER_DATA,
        ]);
      }
    }
    return Promise.reject(error);
  }
);

export default apiClient;
