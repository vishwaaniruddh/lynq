import React, { createContext, useState, useEffect, useContext } from 'react';
import AsyncStorage from '@react-native-async-storage/async-storage';
import axios from 'axios';
import { authApi } from '../api/authApi';
import { setApiBaseUrl, getApiBaseUrl } from '../api/client';
import { DEFAULT_ENVIRONMENTS, STORAGE_KEYS } from '../constants/config';

const AuthContext = createContext(null);

/**
 * Quick connectivity check against a server URL.
 * Returns true if the server responds within 5 seconds.
 */
const testServerReachable = async (url) => {
  try {
    const cleanUrl = url.replace(/\/+$/, '');
    const headers = { 'Accept': 'application/json' };
    if (cleanUrl.includes('loca.lt')) {
      headers['Bypass-Tunnel-Reminder'] = 'true';
    }

    const res = await axios.get(`${cleanUrl}/api/auth/login.php`, {
      timeout: 5000,
      validateStatus: () => true,
      headers,
    });

    // Must be a valid HTTP response (not 502/503/504 gateway errors)
    if (res.status >= 500) {
      console.log(`[Auth] Server error ${res.status} from: ${url}`);
      return false;
    }

    // Verify it's actually JSON from our API (not an HTML interstitial or error page)
    const contentType = res.headers?.['content-type'] || '';
    if (contentType.includes('text/html') && !contentType.includes('json')) {
      console.log(`[Auth] Server returned HTML (not API JSON) from: ${url}`);
      return false;
    }

    console.log(`[Auth] Server reachable: ${url} (HTTP ${res.status})`);
    return true;
  } catch (e) {
    console.log(`[Auth] Server unreachable: ${url} — ${e.message}`);
    return false;
  }
};

export const AuthProvider = ({ children }) => {
  const [user, setUser] = useState(null);
  const [accessToken, setAccessToken] = useState(null);
  const [serverUrl, setServerUrl] = useState(DEFAULT_ENVIRONMENTS[0].url);
  const [isBootstrapping, setIsBootstrapping] = useState(true);
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState(null);

  // Load initial auth state and server configuration
  useEffect(() => {
    const bootstrapAsync = async () => {
      try {
        const storedServerUrl = await AsyncStorage.getItem(STORAGE_KEYS.SERVER_URL);
        let resolvedUrl = DEFAULT_ENVIRONMENTS[0].url;

        if (
          storedServerUrl &&
          storedServerUrl.startsWith('http') &&
          !storedServerUrl.includes('exp.direct')
        ) {
          // Test if the stored URL is actually reachable
          const isReachable = await testServerReachable(storedServerUrl);
          if (isReachable) {
            resolvedUrl = storedServerUrl;
            console.log('[Auth] Using stored server URL:', resolvedUrl);
          } else {
            // Stored URL is stale (old tunnel, VPN changed, etc.)
            // Fall back to auto-detected Local Wi-Fi
            console.log('[Auth] Stored URL unreachable, falling back to:', resolvedUrl);
          }
        }

        // Apply the resolved URL
        setServerUrl(resolvedUrl);
        setApiBaseUrl(resolvedUrl);
        await AsyncStorage.setItem(STORAGE_KEYS.SERVER_URL, resolvedUrl);

        // Restore authentication tokens
        const storedToken = await AsyncStorage.getItem(STORAGE_KEYS.ACCESS_TOKEN);
        const storedUserJson = await AsyncStorage.getItem(STORAGE_KEYS.USER_DATA);

        if (storedToken && storedUserJson) {
          const parsedUser = JSON.parse(storedUserJson);
          setAccessToken(storedToken);
          setUser(parsedUser);
        }
      } catch (e) {
        console.error('Failed to restore session:', e);
      } finally {
        setIsBootstrapping(false);
      }
    };

    bootstrapAsync();
  }, []);

  const updateServerUrl = async (newUrl) => {
    const cleanUrl = newUrl.replace(/\/+$/, '');
    setServerUrl(cleanUrl);
    setApiBaseUrl(cleanUrl);
    await AsyncStorage.setItem(STORAGE_KEYS.SERVER_URL, cleanUrl);
    console.log('[Auth] Server URL updated to:', cleanUrl);
  };

  const login = async (username, password) => {
    setIsLoading(true);
    setError(null);
    try {
      // Confirm server is reachable first, give a clear error if not
      const reachable = await testServerReachable(getApiBaseUrl());
      if (!reachable) {
        const msg = `Cannot reach server at ${getApiBaseUrl()}. Check your Wi-Fi network or tap "Server" at the bottom to change the URL.`;
        setError(msg);
        return { success: false, error: msg };
      }

      const response = await authApi.login(username, password);

      if (response && response.success && response.data) {
        const { user: userData, access_token, refresh_token } = response.data;

        setUser(userData);
        setAccessToken(access_token);

        await AsyncStorage.multiSet([
          [STORAGE_KEYS.ACCESS_TOKEN, access_token],
          [STORAGE_KEYS.REFRESH_TOKEN, refresh_token || ''],
          [STORAGE_KEYS.USER_DATA, JSON.stringify(userData)],
        ]);

        return { success: true };
      } else {
        const msg = response.message || response.error || 'Login failed';
        setError(msg);
        return { success: false, error: msg };
      }
    } catch (err) {
      let msg = err.response?.data?.message || err.response?.data?.error || err.message || 'Network error';
      // Provide a friendlier message for timeouts
      if (err.code === 'ECONNABORTED' || msg.includes('timeout')) {
        msg = `Server timeout. Make sure ${getApiBaseUrl()} is reachable from your phone. Try switching to a different server in settings.`;
      }
      setError(msg);
      return { success: false, error: msg };
    } finally {
      setIsLoading(false);
    }
  };

  const logout = async () => {
    setIsLoading(true);
    try {
      await AsyncStorage.multiRemove([
        STORAGE_KEYS.ACCESS_TOKEN,
        STORAGE_KEYS.REFRESH_TOKEN,
        STORAGE_KEYS.USER_DATA,
      ]);
      setUser(null);
      setAccessToken(null);
    } catch (e) {
      console.error('Logout error:', e);
    } finally {
      setIsLoading(false);
    }
  };

  return (
    <AuthContext.Provider
      value={{
        user,
        accessToken,
        serverUrl,
        isBootstrapping,
        isLoading,
        error,
        isAuthenticated: !!user && !!accessToken,
        login,
        logout,
        updateServerUrl,
      }}
    >
      {children}
    </AuthContext.Provider>
  );
};

export const useAuth = () => {
  const context = useContext(AuthContext);
  if (!context) {
    throw new Error('useAuth must be used within an AuthProvider');
  }
  return context;
};
